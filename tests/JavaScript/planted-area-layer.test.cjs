const test = require('node:test');
const assert = require('node:assert/strict');
const layer = require('../../public/js/planted-area-layer.js');
const period = {enabled:true,year:2026,season:'dry'};
const area = {crop:'corn',crop_label:'Corn',color:'#D4A017',polygon:[{lat:15.6,lng:120.47},{lat:15.6,lng:120.4705},{lat:15.6005,lng:120.47}],area_ha:.2};
function response(ids, areas = [area]) { return {ok:true,json:async()=>({available:true,year:2026,season:'dry',records:ids.map(id=>({plot_id:id,areas}))})}; }
test('selected parcel requests are bounded and unchanged selections reuse data', async()=>{
  let calls=0,requested=[];
  const model=layer.create({url:'/areas',fetch:async url=>{calls++;requested=new URL(url,'http://localhost').searchParams.getAll('plot_ids[]');return response(requested);},onChange:()=>{}});
  await model.load(period,Array.from({length:25},(_,i)=>i+1));
  assert.equal(requested.length,20);assert.equal(model.state.partial,true);assert.equal(model.state.status,'ready');
  await model.load(period,Array.from({length:25},(_,i)=>i+1));assert.equal(calls,1);
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
test('missing migration is an explicit unavailable state',async()=>{
  const model=layer.create({url:'/areas',fetch:async()=>({ok:true,json:async()=>({available:false,message:'Drawing is unavailable'})}),onChange:()=>{}});
  await model.load(period,[1]);assert.equal(model.state.status,'error');assert.equal(model.state.error,'Drawing is unavailable');
});
test('no owner selected does not request or mount global crop areas',async()=>{
  let calls=0;const model=layer.create({url:'/areas',fetch:async()=>{calls++;return response([]);},onChange:()=>{}});
  await model.load(period,[]);assert.equal(calls,0);assert.equal(model.state.status,'off');
});
