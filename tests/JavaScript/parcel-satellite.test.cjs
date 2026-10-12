const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const path = require('node:path');
const fs = require('node:fs');
const { execFileSync } = require('node:child_process');
const { readObservations, createRequestSlot, validDay, responseError, createRetryCooldown } = require('../../public/js/parcel-satellite.js');
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
    constructor(){this.value='';this.disabled=false;this.style={};this.dataset={};this.children=[];this.events={};this.classList={toggle(){}};}
    setAttribute(){} removeAttribute(){} appendChild(child){this.children.push(child);} replaceChildren(...children){this.children=children;}
    addEventListener(name,callback){this.events[name]=callback;} add(child){this.children.push(child);} reportValidity(){return true;}
    querySelector(){return {value:'synthetic-token'};}
  }
  const byId=id=>{if(!elements.has(id))elements.set(id,new Element());return elements.get(id);};
  byId('satelliteConfig').textContent=JSON.stringify({geometry:null,bounds:null,imageUrl:'/image'});
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
