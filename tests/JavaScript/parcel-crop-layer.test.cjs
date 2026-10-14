const test = require('node:test');
const assert = require('node:assert/strict');
const { create } = require('../../public/js/parcel-crop-layer.js');
const settings = { enabled: true, year: 2026, season: 'dry', filter: 'all' };
function response(year, season, ids, crop = 'rice') {
  return { ok: true, json: async () => ({ year, season, records: ids.map(id => ({ plot_id: id, crop, color: '#219653', crop_label: 'Rice' })), legend: [] }) };
}

test('layer styling preserves saved color and filters only a ready classification', async () => {
  const layer = create({url:'/layer', fetch:async()=>response(2026,'dry',[1]), onChange:()=>{}});
  const plot = Object.freeze({id:1,color:'#123456'});
  assert.equal(layer.color(1,plot.color), '#123456');
  await layer.load(settings,[1]);
  assert.equal(layer.color(1,plot.color),'#219653');
  await layer.load({...settings,filter:'corn'},[1]);
  assert.equal(layer.visible(1),false);
  await layer.load({...settings,enabled:false},[1]);
  assert.equal(layer.color(1,plot.color),'#123456');
  assert.equal(layer.visible(1),true);
});

test('late previous-season response cannot replace the newer selected season', async () => {
  const resolvers=[];
  const layer=create({url:'/layer',fetch:()=>new Promise(resolve=>resolvers.push(resolve)),onChange:()=>{}});
  const dry=layer.load(settings,[1]);
  const wet=layer.load({...settings,season:'wet'},[1]);
  resolvers[1](response(2026,'wet',[1],'corn')); await wet;
  resolvers[0](response(2026,'dry',[1],'rice')); await dry;
  assert.equal(layer.state.season,'wet'); assert.equal(layer.record(1).crop,'corn');
});

test('switching off during loading never restores a crop overlay', async () => {
  let resolve;
  const layer=create({url:'/layer',fetch:()=>new Promise(r=>resolve=r),onChange:()=>{}});
  const pending=layer.load(settings,[1]);
  await layer.load({...settings,enabled:false},[1]);
  resolve(response(2026,'dry',[1])); await pending;
  assert.equal(layer.state.status,'off'); assert.equal(layer.record(1),null);
});

test('loaded plot requests are bounded and unchanged filters reuse loaded data', async () => {
  const calls=[];
  const layer=create({url:'/layer',fetch:async url=>{
    const query=new URL(url,'http://localhost').searchParams;
    const ids=query.getAll('plot_ids[]'); calls.push(ids.length);
    return response(2026,'dry',ids);
  },onChange:()=>{}});
  const ids=Array.from({length:451},(_,i)=>i+1);
  await layer.load(settings,ids);
  await layer.load({...settings,filter:'rice'},ids);
  assert.deepEqual(calls,[200,200,51]); assert.equal(layer.state.status,'ready');
});

test('failed or incomplete responses are unavailable instead of Not recorded', async () => {
  const layer=create({url:'/layer',fetch:async()=>response(2026,'dry',[]),onChange:()=>{}});
  await layer.load({...settings,filter:'rice'},[1]);
  assert.equal(layer.state.status,'error'); assert.equal(layer.record(1),null);
  assert.equal(layer.visible(1),true); assert.match(layer.state.error,/Reload/);
});

test('switching the loaded municipality replaces the prior classifications', async () => {
  const layer=create({url:'/layer',fetch:async url=>response(2026,'dry',new URL(url,'http://localhost').searchParams.getAll('plot_ids[]')),onChange:()=>{}});
  await layer.load(settings,[1]); await layer.load(settings,[2]);
  assert.equal(layer.record(1),null); assert.equal(layer.record(2).crop,'rice');
});

test('a modal save can update the open map without another network load', async () => {
  let calls = 0;
  const layer = create({url:'/layer',fetch:async()=>{ calls++; return response(2026,'dry',[1]); },onChange:()=>{}});
  await layer.load(settings,[1]);
  const changed = {...layer.record(1), crop:'corn', crop_label:'Corn', color:'#D4A017', area_crops:['corn'], has_planted_areas:true};
  assert.equal(layer.upsert(changed, {year:2026, season:'dry'}), true);
  assert.equal(layer.record(1).crop, 'corn');
  assert.equal(calls, 1);
});


test('crop filter includes the corn section of a mixed-crop parcel', async () => {
  const layer=create({url:'/layer',fetch:async()=>({ok:true,json:async()=>({year:2026,season:'dry',records:[{plot_id:1,crop:'mixed',area_crops:['corn','rice'],color:'#4263C7'}],legend:[]})}),onChange:()=>{}});
  await layer.load({...settings,filter:'corn'},[1]);
  assert.equal(layer.visible(1),true);
  await layer.load({...settings,filter:'vegetables'},[1]);
  assert.equal(layer.visible(1),false);
});

test('overview icons load crop metadata without enabling recoloring or requiring farmer selection', async () => {
  const calls=[];
  const layer=create({url:'/layer',fetch:async url=>{
    const ids=new URL(url,'http://localhost').searchParams.getAll('plot_ids[]');
    calls.push(ids.length);return response(2026,'dry',ids);
  },onChange:()=>{}});
  const iconSettings={...settings,enabled:false,icons:true};
  const ids=Array.from({length:451},(_,i)=>i+1);
  await layer.load(iconSettings,ids);
  assert.deepEqual(calls,[200,200,51]);
  assert.equal(layer.record(1).crop,'rice');
  assert.equal(layer.color(1,'#123456'),'#123456');
  assert.equal(layer.visible(1),true);
  await layer.load(iconSettings,ids);assert.equal(calls.length,3,'Camera/visibility refresh must reuse metadata');
  await layer.load({...iconSettings,enabled:true},ids);assert.equal(calls.length,3,'Recoloring can reuse the same records');
});

test('changing periods with icons on and colors off discards late crop metadata', async () => {
  const pending=[];
  const layer=create({url:'/layer',fetch:()=>new Promise(resolve=>pending.push(resolve)),onChange:()=>{}});
  const iconSettings={...settings,enabled:false,icons:true};
  const old=layer.load(iconSettings,[1]);
  const next=layer.load({...iconSettings,season:'wet'},[1]);
  assert.equal(layer.record(1),null);
  pending[1](response(2026,'wet',[1],'corn'));await next;
  pending[0](response(2026,'dry',[1],'rice'));await old;
  assert.equal(layer.record(1).crop,'corn');assert.equal(layer.state.season,'wet');
  assert.equal(layer.color(1,'#123456'),'#123456');
});
