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
