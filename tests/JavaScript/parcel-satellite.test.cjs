const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { readObservations, createRequestSlot, validDay, responseError, createRetryCooldown, indexScale } = require('../../public/js/parcel-satellite.js');
const scripts = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, '../Support/export-sentinel-evalscripts.php')], { encoding: 'utf8' }));
const period = { from: '2026-08-01', to: '2026-08-03' };
const row = { date: '2026-08-01', mean: 0, min: -0.2, max: 0.2, valid_pixels: 14, status: 'available' };

function evaluate(script, pixel) {
  const sandbox = { pixel, colorBlend: () => [0.2, 0.4, 0.6] };
  vm.runInNewContext(script + '; result = evaluatePixel(pixel);', sandbox);
  return JSON.parse(JSON.stringify(sandbox.result));
}

test('actual provider scripts compute NDVI and mask cloud/shadow/snow/no-data pixels consistently', () => {
  const pixel = { B04: 0.2, B08: 0.6, B02: 0.1, B03: 0.15, SCL: 4, dataMask: 1 };
  assert.ok(Math.abs(evaluate(scripts.statistics, pixel).ndvi[0] - 0.5) < 1e-9);
  for (let scl = 0; scl <= 11; scl++) {
    const expectedMask = [4, 5, 6].includes(scl) ? 1 : 0;
    assert.equal(evaluate(scripts.statistics, { ...pixel, SCL: scl }).dataMask[0], expectedMask);
    assert.equal(evaluate(scripts.ndvi, { ...pixel, SCL: scl })[3], expectedMask);
    assert.equal(evaluate(scripts.trueColor, { ...pixel, SCL: scl })[3], expectedMask);
  }
  assert.equal(evaluate(scripts.statistics, { ...pixel, dataMask: 0 }).dataMask[0], 0);
  assert.equal(evaluate(scripts.statistics, { ...pixel, B04: 0, B08: 0 }).dataMask[0], 0);
});

test('water and bare soil preserve negative and zero NDVI; true color uses the visible bands', () => {
  const pixel = { B04: 0.2, B08: 0.1, B02: 0.1, B03: 0.15, SCL: 6, dataMask: 1 };
  const water = evaluate(scripts.statistics, pixel);
  assert.equal(water.dataMask[0], 1);
  assert.ok(Math.abs(water.ndvi[0] + 1 / 3) < 1e-9);
  assert.deepEqual(evaluate(scripts.statistics, { ...pixel, SCL: 5, B08: 0.2 }), { ndvi: [0], dataMask: [1] });
  assert.deepEqual(evaluate(scripts.trueColor, pixel), [0.5, 0.375, 0.25, 1]);
});

test('actual NDMI, SAVI and EVI scripts use reflectance formulas and identical image/statistics masks', () => {
  const pixel = { B02: 0.1, B04: 0.2, B08: 0.6, B11: 0.3, SCL: 4, dataMask: 1 };
  const expected = { ndvi: 0.5, ndmi: 1 / 3, savi: 1.5 * 0.4 / 1.3, evi: 2.5 * 0.4 / 2.05 };
  for (const [id, scriptsForIndex] of Object.entries(scripts.indexes)) {
    const value = evaluate(scriptsForIndex.statistics, pixel);
    assert.ok(Math.abs(value[id][0] - expected[id]) < 1e-9, id + ' formula');
    assert.equal(value.dataMask[0], 1);
    const sandbox = {};
    vm.runInNewContext(scriptsForIndex.statistics + '; config = setup();', sandbox);
    const input = sandbox.config.input[0];
    input.bands.forEach((band, i) => assert.equal(input.units[i], band.startsWith('B') ? 'REFLECTANCE' : 'DN'));
    for (let scl = 0; scl <= 11; scl++) {
      const mask = [4, 5, 6].includes(scl) ? 1 : 0;
      assert.equal(evaluate(scriptsForIndex.statistics, { ...pixel, SCL: scl }).dataMask[0], mask, id);
      assert.equal(evaluate(scriptsForIndex.image, { ...pixel, SCL: scl })[3], mask, id);
    }
    for (const bad of [{ dataMask: 0 }, { B08: NaN }, { B08: Infinity }, { B08: -0.1 }, { B08: undefined }]) {
      const invalid = { ...pixel, ...bad };
      assert.equal(evaluate(scriptsForIndex.statistics, invalid).dataMask[0], 0, id);
      assert.equal(evaluate(scriptsForIndex.image, invalid)[3], 0, id);
    }
  }
});

test('undefined divisions are masked and valid zero, negative and above-one values remain measurements', () => {
  const pixel = { B02: 0.1, B04: 0.2, B08: 0.6, B11: 0.3, SCL: 4, dataMask: 1 };
  for (const [id, changes] of [['ndmi', { B08: 0, B11: 0 }], ['evi', { B02: 2.8 / 7.5 }], ['evi', { B02: (2.8 - 1e-8) / 7.5 }]]) {
    assert.equal(evaluate(scripts.indexes[id].statistics, { ...pixel, ...changes }).dataMask[0], 0, id);
    assert.equal(evaluate(scripts.indexes[id].image, { ...pixel, ...changes })[3], 0, id);
  }
  for (const id of ['ndmi', 'savi', 'evi']) {
    const zero = evaluate(scripts.indexes[id].statistics, { ...pixel, B08: 0.2, B11: 0.2 });
    assert.deepEqual(zero, { [id]: [0], dataMask: [1] });
    assert.ok(evaluate(scripts.indexes[id].statistics, { ...pixel, B08: 0.1 })[id][0] < 0, id);
  }
  const evi = evaluate(scripts.indexes.evi.statistics, { ...pixel, B02: 2 / 7.5 });
  assert.ok(evi.evi[0] > 1); assert.equal(evi.dataMask[0], 1);
  const savi = evaluate(scripts.indexes.savi.statistics, { ...pixel, B08: 3 });
  assert.ok(savi.savi[0] > 1); assert.equal(savi.dataMask[0], 1);
});

test('responses are tied to the selected index/grid and EVI/SAVI axes preserve values above one', () => {
  for (const id of ['ndmi', 'savi', 'evi']) {
    const definition = scripts.definitions[id], selected = { ...period, index: id };
    const value = { ...row, mean: id === 'ndmi' ? 0 : 1.2, min: 0, max: id === 'ndmi' ? 0.2 : 1.3 };
    const payload = { index: id, resolution_m: definition.resolution_m, observations: [value] };
    assert.equal(readObservations(payload, selected, definition)[0].mean, value.mean);
    assert.throws(() => readObservations({ ...payload, index: 'ndvi' }, selected, definition), /selected index/);
    assert.throws(() => readObservations({ ...payload, resolution_m: 99 }, selected, definition), /selected index/);
    assert.throws(() => readObservations({ ...payload, index: undefined }, selected, definition), /selected index/);
    assert.throws(() => readObservations({ ...payload, observations: [{ ...value, mean: Infinity }] }, selected, definition));
  }
  const scale = indexScale([{ ...row, mean: 1.2, min: -1.2, max: 1.4 }]);
  assert.deepEqual(scale, { min: -1.2, max: 1.4 });
});

test('daily observations sort dates and preserve zero separately from a fully masked day', () => {
  const masked = { ...row, date: '2026-08-03', mean: null, min: null, max: null, valid_pixels: 0, status: 'no_data' };
  const output = readObservations({ observations: [masked, row] }, period);
  assert.equal(output[0].mean, 0);
  assert.equal(output[1].mean, null);
  assert.deepEqual(readObservations({ observations: [] }, period), []);
});

test('malformed, duplicate, out-of-period and impossible measurements are rejected', () => {
  const invalid = [null, { ...row, date: '2026-02-30' }, { ...row, date: '2026-08-04' },
    { ...row, mean: null }, { ...row, mean: 2 }, { ...row, mean: NaN }, { ...row, valid_pixels: 1.5 },
    { ...row, min: 0.4 }, { ...row, status: 'no_data' }];
  for (const item of invalid) assert.throws(() => readObservations({ observations: [item] }, period));
  assert.throws(() => readObservations({ observations: [row, row] }, period));
  assert.throws(() => readObservations({ observations: Array(32).fill(row) }, period));
  assert.equal(validDay('2024-02-29'), true);
  assert.equal(validDay('2026-02-29'), false);
});

test('a late image response cannot replace the newer day or layer', async () => {
  const calls = [];
  const slot = createRequestSlot((url, options) => new Promise(resolve => calls.push({ url, options, resolve })));
  const older = slot.run('/first', {}, async value => value);
  const newer = slot.run('/second', {}, async value => value);
  assert.equal(calls[0].options.signal.aborted, true);
  calls[1].resolve('second');
  assert.deepEqual(await newer, { value: 'second' });
  calls[0].resolve('first');
  assert.equal(await older, null);
});

test('leaving the page or clearing observations also discards a response during decoding', async () => {
  let decoded;
  const slot = createRequestSlot(async () => 'response');
  const pending = slot.run('/image', {}, () => new Promise(resolve => { decoded = resolve; }));
  await new Promise(resolve => setImmediate(resolve));
  slot.cancel(); decoded('bytes');
  assert.equal(await pending, null);
});

test('superseded failures are ignored while the current failure remains visible', async () => {
  const failures = [];
  const slot = createRequestSlot(() => new Promise((resolve, reject) => failures.push(reject)));
  const older = slot.run('/old', {}, value => value);
  const current = slot.run('/new', {}, value => value);
  failures[0](new Error('old failure'));
  assert.equal(await older, null);
  failures[1](new Error('current failure'));
  await assert.rejects(current, /current failure/);
});

test('request-limit errors carry a bounded server wait while provider allowances stay distinct', async()=>{
  const response=(status,payload,wait)=>({status,headers:{get:()=>wait},json:async()=>payload});
  const limit=await responseError(response(429,{message:'Satellite picture searches are temporarily limited.'},'48'));
  assert.equal(limit.retryAfterSeconds,48);
  const allowance=await responseError(response(429,{message:'The local satellite request allowance has been reached.'},null));
  assert.match(allowance.message,/allowance/);assert.equal(allowance.retryAfterSeconds,undefined,'A provider/day budget is not presented as a one-minute wait');
  assert.equal((await responseError(response(429,{},'99999'))).retryAfterSeconds,300);
  assert.equal((await responseError(response(429,{},'invalid'))).retryAfterSeconds,undefined);
  assert.equal((await responseError(response(422,{errors:{to:['Choose up to 31 days.']}},'50'))).retryAfterSeconds,undefined);
  const expiry=await responseError(response(419,{message:'<html>'},null));assert.match(expiry.message,/session expired/);
});

test('cooldown follows elapsed time, restores controls without retrying and cancels its timer',()=>{
  let now=0,callback=null,cancelled=0;const ticks=[];
  const cooldown=createRetryCooldown(seconds=>ticks.push(seconds),{now:()=>now,setTimeout:fn=>{callback=fn;return 1;},clearTimeout:()=>{callback=null;cancelled++;}});
  cooldown.start(5);assert.equal(cooldown.remaining(),5);assert.deepEqual(ticks,[5]);
  now=3000;callback();assert.deepEqual(ticks,[5,2],'A sleeping/background tab uses elapsed time');
  now=6000;const end=callback;callback=null;end();assert.equal(cooldown.remaining(),0);assert.equal(callback,null);
  assert.deepEqual(ticks,[5,2,0]);
  cooldown.start(30);cooldown.stop();assert.equal(callback,null);assert.equal(cooldown.remaining(),0);assert.equal(cancelled,1);
});

function satellitePage(fetcher) {
  const elements=new Map(),events=new Map(),timers=new Map();let clock=0,timerId=0;
  class Element {
    constructor(){this.value='';this.disabled=false;this.style={};this.dataset={};this.children=[];this.events={};this.attributes={};this.classList={toggle(){}};}
    setAttribute(key,value){this.attributes[key]=value;} removeAttribute(key){delete this.attributes[key];} appendChild(child){this.children.push(child);} replaceChildren(...children){this.children=children;}
    addEventListener(name,callback){this.events[name]=callback;} add(child){this.children.push(child);} reportValidity(){return true;}
    querySelector(){return {value:'synthetic-token'};}
  }
  const byId=id=>{if(!elements.has(id))elements.set(id,new Element());return elements.get(id);};
  byId('satelliteConfig').textContent=JSON.stringify({geometry:null,bounds:null,imageUrl:'/image',indexes:scripts.definitions});
  byId('satelliteIndex').value='ndvi';
  byId('satelliteForm').action='/observations';byId('satelliteFrom').value=period.from;byId('satelliteTo').value=period.to;byId('satelliteCloud').value='60';byId('satelliteLayer').value='ndvi';
  class MockDate extends Date {static now(){return clock;}}
  vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../public/js/parcel-satellite.js'),'utf8'),{
    document:{getElementById:byId,createElement:()=>new Element(),createElementNS:()=>new Element()},
    window:{fetch:fetcher,location:{href:'https://example.test/parcel'},addEventListener:(name,callback)=>events.set(name,callback)},
    Option:class{constructor(text,value){this.text=text;this.value=value;}},Date:MockDate,URL,URLSearchParams,AbortController,
    setTimeout:fn=>{timers.set(++timerId,fn);return timerId;},clearTimeout:id=>timers.delete(id)
  });
  return {byId,events,timers,advance:ms=>{clock+=ms;const pending=[...timers.entries()];for(const [id,fn] of pending){if(timers.has(id)){timers.delete(id);fn();}}},submit:()=>byId('satelliteForm').events.submit({preventDefault(){}})};
}

test('the initial satellite screen has no empty result, image controls or history and starts no search', () => {
  let calls = 0;
  const page = satellitePage(() => { calls++; throw new Error('An explicit search is required'); });
  assert.equal(page.byId('satelliteWorkspace').dataset.hasResult, 'false');
  assert.equal(page.byId('satelliteSummary').hidden, true);
  assert.equal(page.byId('satelliteMapControls').hidden, true);
  assert.equal(page.byId('satelliteHistoryPanel').hidden, true);
  assert.equal(calls, 0);
});

test('a returned period reveals its result and date controls; switching checks hides the old result', async () => {
  let calls = 0;
  const page = satellitePage(async url => {
    calls++;
    return url === '/observations'
      ? { ok: true, json: async () => ({ observations: [row] }) }
      : { ok: false, status: 502, headers: { get: () => null }, json: async () => ({ message: 'Synthetic image unavailable.' }) };
  });
  await page.submit(); await new Promise(resolve => setImmediate(resolve));
  assert.equal(page.byId('satelliteWorkspace').dataset.hasResult, 'true');
  assert.equal(page.byId('satelliteSummary').hidden, false);
  assert.equal(page.byId('satelliteMapControls').hidden, false);
  assert.equal(page.byId('satelliteHistoryPanel').hidden, false);
  assert.equal(page.byId('satelliteScore').hidden, false);
  assert.equal(page.byId('satelliteDisplayOptions').hidden, true, 'Image-only controls stay hidden if the image fails');
  assert.match(page.byId('satelliteStatus').textContent, /1 usable picture found/);
  assert.equal(calls, 2, 'One explicit statistics search and one image request');
  page.byId('satelliteIndex').value = 'ndmi'; page.byId('satelliteIndex').events.change();
  assert.equal(page.byId('satelliteSummary').hidden, true);
  assert.equal(page.byId('satelliteMapControls').hidden, true);
  assert.equal(page.byId('satelliteHistoryPanel').hidden, true);
  assert.equal(calls, 2, 'Changing checks never starts provider work');
});

test('an empty search explains what to try without exposing a zero score or unusable controls', async () => {
  let calls = 0;
  const page = satellitePage(async () => { calls++; return { ok: true, json: async () => ({ observations: [] }) }; });
  await page.submit();
  assert.equal(page.byId('satelliteSummary').hidden, false);
  assert.equal(page.byId('satelliteResult').dataset.state, 'no-data');
  assert.match(page.byId('satelliteResultTitle').textContent, /No pictures found/);
  assert.match(page.byId('satelliteNextStep').textContent, /nearby dates.*All scenes/);
  assert.equal(page.byId('satelliteScore').hidden, true);
  assert.equal(page.byId('satelliteMean').textContent, '—');
  assert.equal(page.byId('satelliteMapControls').hidden, true);
  assert.equal(page.byId('satelliteHistoryPanel').hidden, true);
  assert.equal(calls, 1, 'No image request or automatic retry when the search is empty');
});

test('a failed replacement search cannot leave the previous measurements on screen', async () => {
  let searches = 0, finish;
  const page = satellitePage(async url => {
    if (url !== '/observations') return { ok: false, status: 502, headers: { get: () => null }, json: async () => ({ message: 'Synthetic image unavailable.' }) };
    searches++;
    if (searches === 1) return { ok: true, json: async () => ({ observations: [row] }) };
    return new Promise(resolve => { finish = resolve; });
  });
  await page.submit(); await new Promise(resolve => setImmediate(resolve));
  const pending = page.submit();
  assert.equal(page.byId('satelliteSummary').hidden, true);
  assert.equal(page.byId('satelliteHistoryPanel').hidden, true);
  assert.equal(page.byId('satelliteScore').hidden, true);
  finish({ ok: false, status: 503, headers: { get: () => null }, json: async () => ({ message: 'Satellite access is unavailable.' }) });
  await pending;
  assert.equal(page.byId('satelliteSummary').hidden, true);
  assert.equal(page.byId('satelliteMapControls').hidden, true);
  assert.match(page.byId('satelliteStatus').textContent, /unavailable/);
  assert.equal(page.byId('satelliteLoad').disabled, false);
});

test('the real search controls suppress duplicate requests and pause only until Retry-After expires',async()=>{
  let resolve,calls=0;
  const page=satellitePage(()=>{calls++;return new Promise(r=>{resolve=r;});});
  const pending=page.submit();await page.submit();assert.equal(calls,1,'Repeated Enter/click during the request must not send twice');
  resolve({ok:false,status:429,headers:{get:()=> '2'},json:async()=>({message:'Satellite searches limited.'})});await pending;
  assert.equal(page.byId('satelliteLoad').disabled,true);assert.match(page.byId('satelliteLoad').textContent,/2s/);
  await page.submit();assert.equal(calls,1);
  page.advance(3000);assert.equal(page.byId('satelliteLoad').disabled,false);assert.match(page.byId('satelliteStatus').textContent,/search.*again/);
  assert.equal(calls,1,'Countdown completion cannot automatically request paid satellite work');
  assert.equal(page.timers.size,0);
});

test('image cooldown leaves picture searches available and prevents image retries across date changes',async()=>{
  let searches=0,images=0;
  const page=satellitePage(async url=>{
    if(url==='/observations'){searches++;return {ok:true,json:async()=>({observations:[row]})};}
    images++;return {ok:false,status:429,headers:{get:()=> '3'},json:async()=>({message:'Satellite image loads limited.'})};
  });
  await page.submit();await new Promise(resolve=>setImmediate(resolve));
  assert.equal(searches,1);assert.equal(images,1);
  assert.equal(page.byId('satelliteImageRetry').disabled,true);assert.equal(page.byId('satelliteLoad').disabled,false);
  await page.byId('satelliteLayer').events.change();assert.equal(images,1);
  page.advance(4000);assert.equal(page.byId('satelliteImageRetry').disabled,false);assert.equal(images,1);
  page.events.get('pagehide')();assert.equal(page.timers.size,0);
});

test('changing the selected index discards old data without fetching and does not let old completion unlock a new search', async () => {
  const calls = [];
  const page = satellitePage((url, options) => new Promise(resolve => calls.push({ url, options, resolve })));
  const first = page.submit();
  assert.equal(JSON.parse(calls[0].options.body).index, 'ndvi');
  page.byId('satelliteIndex').value = 'ndmi'; page.byId('satelliteIndex').events.change();
  assert.equal(calls.length, 1, 'Selection alone cannot start paid work');
  assert.equal(calls[0].options.signal.aborted, true);
  assert.equal(page.byId('satelliteDate').disabled, true);
  assert.equal(page.byId('satelliteScore').hidden, true);
  assert.match(page.byId('satelliteResolution').textContent, /20 m/);
  assert.match(page.byId('satelliteLegendHigh').textContent, /water signal/);
  const second = page.submit();
  calls[0].resolve({ ok: true, json: async () => ({ observations: [row] }) });
  await first;
  assert.equal(page.byId('satelliteLoad').disabled, true, 'A superseded request cannot unlock the current search');
  calls[1].resolve({ ok: true, json: async () => ({ index: 'ndmi', resolution_m: 20, observations: [] }) });
  await second;
  assert.equal(page.byId('satelliteLoad').disabled, false);
  assert.equal(calls.length, 2);
});

test('each new index requests its matching image and labels; switching indices preserves a provider cooldown', async () => {
  for (const id of ['ndmi', 'savi', 'evi']) {
    const calls = [];
    const page = satellitePage(async (url, options) => {
      calls.push({ url, options });
      if (url === '/observations') return { ok: true, json: async () => ({ index: id, resolution_m: scripts.definitions[id].resolution_m, observations: [row] }) };
      return { ok: false, status: 429, headers: { get: () => '5' }, json: async () => ({ message: 'Satellite image loads limited.' }) };
    });
    page.byId('satelliteIndex').value = id; page.byId('satelliteIndex').events.change();
    await page.submit(); await new Promise(resolve => setImmediate(resolve));
    assert.equal(new URL(calls[1].url).searchParams.get('layer'), id);
    assert.match(page.byId('satelliteMeanLabel').textContent, new RegExp(id.toUpperCase()));
    page.byId('satelliteIndex').value = 'ndvi'; page.byId('satelliteIndex').events.change();
    assert.equal(calls.length, 2); assert.equal(page.byId('satelliteImageRetry').disabled, true);
    assert.equal(page.byId('satelliteScore').hidden, true);
    page.events.get('pagehide')(); assert.equal(page.timers.size, 0);
  }
});

test('the rendered EVI summary and chart show measurements above one without clipping or health grades', async () => {
  const page = satellitePage(async url => url === '/observations'
    ? { ok: true, json: async () => ({ index: 'evi', resolution_m: 10, observations: [{ ...row, mean: 1.2, min: -1.1, max: 1.4 }] }) }
    : { ok: false, status: 502, headers: { get: () => null }, json: async () => ({ message: 'Synthetic image unavailable.' }) });
  page.byId('satelliteIndex').value = 'evi'; page.byId('satelliteIndex').events.change();
  await page.submit(); await new Promise(resolve => setImmediate(resolve));
  assert.equal(page.byId('satelliteMean').textContent, '1.200');
  assert.equal(page.byId('satelliteScoreMeter').value, 1.2);
  assert.equal(page.byId('satelliteScoreMeter').min, -1.1);
  assert.equal(page.byId('satelliteScoreMeter').max, 1.4);
  assert.match(page.byId('satelliteQuality').textContent, /outside the colour scale/);
  assert.equal(page.byId('satelliteHistory').children[0].children[1].textContent, '1.200');
  const svg = page.byId('satelliteChart').children[0];
  const dot = svg.children.find(node => node.attributes.cy !== undefined);
  assert.ok(Number(dot.attributes.cy) >= 16 && Number(dot.attributes.cy) <= 146);
  page.events.get('pagehide')();
});

test('an image completing after an index switch cannot restore the old layer or legend', async () => {
  let finish;
  const page = satellitePage(async url => url === '/observations'
    ? { ok: true, json: async () => ({ observations: [row] }) }
    : new Promise(resolve => { finish = resolve; }));
  await page.submit(); await new Promise(resolve => setImmediate(resolve));
  page.byId('satelliteIndex').value = 'ndmi'; page.byId('satelliteIndex').events.change();
  finish({ ok: true, headers: { get: () => 'image/png' }, blob: async () => new Blob(['synthetic png']) });
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(page.byId('satelliteLegend').hidden, true);
  assert.equal(page.byId('satellitePreview').hidden, true);
  assert.equal(page.byId('satelliteOpacity').disabled, true);
  assert.match(page.byId('satelliteImageCaption').textContent, /No NDMI picture/);
  page.events.get('pagehide')();
});
