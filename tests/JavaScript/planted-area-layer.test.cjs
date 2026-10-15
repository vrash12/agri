const test = require('node:test');
const assert = require('node:assert/strict');
const layer = require('../../public/js/planted-area-layer.js');
const period = {enabled:true,year:2026,season:'dry'};
const area = {crop:'corn',crop_label:'Corn',color:'#D4A017',polygon:[{lat:15.6,lng:120.47},{lat:15.6,lng:120.4705},{lat:15.6005,lng:120.47}],area_ha:.2};
function response(ids, areas = [area]) { return {ok:true,json:async()=>({available:true,year:2026,season:'dry',records:ids.map(id=>({plot_id:id,areas}))})}; }
test('all loaded crop parcels use 20-ID batches and unchanged requests reuse data', async()=>{
  let calls=0,requested=[],progress=[];
  const model=layer.create({url:'/areas',fetch:async url=>{calls++;requested.push(new URL(url,'http://localhost').searchParams.getAll('plot_ids[]'));return response(requested.at(-1));},onChange:state=>progress.push([state.status,state.loaded])});
  await model.load(period,Array.from({length:25},(_,i)=>i+1));
  assert.deepEqual(requested.map(ids=>ids.length),[20,5]);assert.equal(model.state.partial,false);assert.equal(model.state.status,'ready');
  assert.equal(model.state.records.length,25);assert.equal(model.state.total,25);
  assert.deepEqual(progress,[['loading',0],['loading',20],['loading',25],['ready',25]]);
  await model.load(period,Array.from({length:25},(_,i)=>25-i));assert.equal(calls,2);
});
test('switching off or changing owner invalidates late geometry responses',async()=>{
  let release;
  const model=layer.create({url:'/areas',fetch:()=>new Promise(resolve=>{release=resolve;}),onChange:()=>{}});
  const pending=model.load(period,[1]);await model.load({enabled:false},[]);release(response([1]));await pending;assert.equal(model.state.status,'off');assert.deepEqual(model.state.records,[]);
});
test('unsafe colors, coordinates and oversized boundaries fail closed',async()=>{
  for(const value of [response([1],[{...area,color:'red'}]),response([1],[{...area,polygon:[{lat:200,lng:1},{lat:0,lng:1},{lat:1,lng:2}]}]),response([1],[{...area,polygon:Array(51).fill({lat:1,lng:1})}])]){
    const model=layer.create({url:'/areas',fetch:async()=>value,onChange:()=>{}});await model.load(period,[1]);assert.equal(model.state.status,'error');assert.deepEqual(model.state.records,[]);
  }
});
test('visual crop lines accept two points without an area measurement',async()=>{
  const line={type:'line',crop:'rice',crop_label:'Rice',color:'#228B22',path:[{lat:15.6,lng:120.47},{lat:15.6005,lng:120.4705}]};
  const model=layer.create({url:'/areas',fetch:async()=>response([1],[line]),onChange:()=>{}});
  await model.load(period,[1]);
  assert.equal(model.state.status,'ready');
  assert.deepEqual(model.state.records[0].areas[0].path,line.path);
});
test('missing migration is an explicit unavailable state',async()=>{
  const model=layer.create({url:'/areas',fetch:async()=>({ok:true,json:async()=>({available:false,message:'Drawing is unavailable'})}),onChange:()=>{}});
  await model.load(period,[1]);assert.equal(model.state.status,'error');assert.equal(model.state.error,'Drawing is unavailable');
});

test('a modal save can insert a new drawing into the current map state immediately', async () => {
  let requests=0;
  const model = layer.create({url:'/areas',fetch:async()=>{requests++;return response([1],[]);},onChange:()=>{}});
  await model.load(period, []);
  const drawing = {plot_id:1, areas:[{type:'line', crop:'corn', crop_label:'Corn', color:'#D4A017', path:[{lat:15.6,lng:120.47},{lat:15.6005,lng:120.4705}]}]};
  assert.equal(model.upsert(drawing, period), true);
  assert.equal(model.state.status, 'ready');
  assert.deepEqual(model.state.records[0], drawing);
  await model.load(period,[1]);
  assert.equal(requests,0,'The first map refresh reuses the validated saved row');
  assert.deepEqual(model.state.records[0],drawing);
});

test('modal drawing updates can defer rendering until the combined layer refresh', async () => {
  let changes = 0;
  const model = layer.create({url:'/areas',fetch:async()=>response([]),onChange:()=>{ changes++; }});
  await model.load(period, []);
  changes = 0;
  const drawing = {plot_id:1, areas:[]};
  assert.equal(model.upsert(drawing, period, {silent:true}), true);
  assert.equal(changes, 0);
  model.refresh();
  assert.equal(changes, 1);
});
test('an empty list does not request crop drawings',async()=>{
  let calls=0;const model=layer.create({url:'/areas',fetch:async()=>{calls++;return response([]);},onChange:()=>{}});
  await model.load(period,[]);assert.equal(calls,0);assert.equal(model.state.status,'off');
});

test('a failed batch preserves earlier drawings and explicit retry resumes only missing parcels',async()=>{
  let requests=[],fail=true;
  const model=layer.create({url:'/areas',fetch:async url=>{
    const ids=new URL(url,'http://localhost').searchParams.getAll('plot_ids[]');requests.push(ids);
    return requests.length===2 && fail ? {ok:false,status:503} : response(ids);
  },onChange:()=>{}});
  const ids=Array.from({length:45},(_,i)=>i+1);
  await model.load(period,ids);assert.equal(model.state.status,'error');assert.equal(model.state.records.length,20);
  const first=model.state.records[0];
  await model.load(period,ids);assert.equal(requests.length,2,'Camera refreshes cannot retry a failed request');
  fail=false;await model.load(period,ids,false,true);
  assert.deepEqual(requests.map(ids=>ids.length),[20,20,20,5]);assert.equal(model.state.records.length,45);
  assert.equal(model.state.records[0],first,'Accepted record objects stay reusable');assert.equal(model.state.partial,false);
});

test('removing a loaded parcel preserves other cached drawings and does not refetch them',async()=>{
  let requests=[];
  const model=layer.create({url:'/areas',fetch:async url=>{
    const ids=new URL(url,'http://localhost').searchParams.getAll('plot_ids[]');requests.push(ids);return response(ids);
  },onChange:()=>{}});
  await model.load(period,[1,2,3]);const first=model.state.records[0];
  await model.load(period,[1,3]);assert.equal(requests.length,1);assert.equal(model.state.records[0],first);
  await model.load(period,[1,3,4]);assert.deepEqual(requests[1],['4']);
  await model.load(period,[1,3,4],true);assert.equal(requests[2].length,3,'Saving/refreshing a season rereads the drawing data');
});

test('a late batch cannot reappear after a period or map change',async()=>{
  let release,calls=0;
  const model=layer.create({url:'/areas',fetch:async url=>{
    calls++;const query=new URL(url,'http://localhost').searchParams;
    if(calls===2)return new Promise(resolve=>{release=resolve;});
    const ids=query.getAll('plot_ids[]');
    return {ok:true,json:async()=>({available:true,year:Number(query.get('year')),season:query.get('season'),records:ids.map(id=>({plot_id:id,areas:[area]}))})};
  },onChange:()=>{}});
  const pending=model.load(period,Array.from({length:21},(_,i)=>i+1));
  while(!release)await new Promise(resolve=>setImmediate(resolve));
  await model.load({...period,season:'wet'},[99]);
  release(response(['9']));await pending;
  assert.equal(model.state.season,'wet');assert.deepEqual(model.state.records.map(row=>row.plot_id),['99']);
});

test('unexpected or duplicate response IDs and null coordinates fail closed',async()=>{
  for(const value of [response([999,2]),response([1,1]),response([1,2],[{...area,polygon:[null,{lat:0,lng:1},{lat:1,lng:2}]}]),response([1,2],[{...area,polygon:[{lat:null,lng:1},{lat:0,lng:1},{lat:1,lng:2}]}])]){
    const model=layer.create({url:'/areas',fetch:async()=>value,onChange:()=>{}});
    await model.load(period,[1,2]);assert.equal(model.state.status,'error');assert.equal(model.state.records.length,0);
  }
});

test('session expiry discards prior private drawing batches',async()=>{
  let calls=0;
  const model=layer.create({url:'/areas',fetch:async url=>{
    const ids=new URL(url,'http://localhost').searchParams.getAll('plot_ids[]');
    return ++calls===1 ? response(ids) : {ok:false,status:401};
  },onChange:()=>{}});
  await model.load(period,Array.from({length:21},(_,i)=>i+1));
  assert.equal(model.state.status,'error');assert.deepEqual(model.state.records,[]);assert.match(model.state.error,/Sign in again/);
});

test('429 honors a bounded wait and never sends an automatic retry',async()=>{
  const original=Date.now;let clock=1000,calls=0;
  Date.now=()=>clock;
  try{
    const model=layer.create({url:'/areas',fetch:async url=>{
      calls++;return calls===1 ? {ok:false,status:429,headers:{get:()=>99999}} : response(new URL(url,'http://localhost').searchParams.getAll('plot_ids[]'));
    },onChange:()=>{}});
    await model.load(period,[1]);assert.equal(model.state.retryAt,61000);
    await model.load(period,[1],false,true);assert.equal(calls,1);
    clock=61001;await model.load(period,[1]);assert.equal(calls,1,'Elapsed time is not permission to retry automatically');
    await model.load(period,[1],false,true);assert.equal(calls,2);assert.equal(model.state.status,'ready');
  }finally{Date.now=original;}
});

test('a requested refresh survives a cooldown instead of keeping old accepted drawings',async()=>{
  const original=Date.now;let clock=1000,calls=0;
  Date.now=()=>clock;
  try{
    const model=layer.create({url:'/areas',fetch:async url=>{
      calls++;return calls===2 ? {ok:false,status:429,headers:{get:()=>1}} : response(new URL(url,'http://localhost').searchParams.getAll('plot_ids[]'));
    },onChange:()=>{}});
    const ids=Array.from({length:21},(_,i)=>i+1);
    await model.load(period,ids);const first=model.state.records[0];
    await model.load(period,ids,true);assert.equal(calls,2);
    clock=2001;await model.load(period,ids,false,true);
    assert.equal(calls,4,'Refresh rereads both batches after the bounded wait');
    assert.notEqual(model.state.records[0],first);assert.equal(model.state.records.length,21);
  }finally{Date.now=original;}
});
