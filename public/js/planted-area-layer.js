(function (root) {
  'use strict';
  function create(options) {
    var revision = 0, controller, key = '';
    var state = {status:'off',records:[],error:'',partial:false};
    function notify(){options.onChange(state);}
    async function load(settings, ids, force) {
      var wanted = [...new Set(ids.map(String))];
      if(!settings.enabled || !wanted.length){revision++;controller?.abort();key='';state={status:'off',records:[],error:'',partial:false};notify();return;}
      var partial=wanted.length>20;wanted=wanted.slice(0,20);
      var nextKey=settings.year+'/'+settings.season+'/'+wanted.join(',');
      if(!force&&key===nextKey&&(state.status==='ready'||state.status==='loading')){notify();return;}
      key=nextKey;var current=++revision;controller?.abort();controller=new AbortController();
      state={status:'loading',records:[],error:'',partial:partial};notify();
      try{
        var query=new URLSearchParams({year:String(settings.year),season:settings.season});wanted.forEach(id=>query.append('plot_ids[]',id));
        var response=await options.fetch(options.url+'?'+query,{headers:{Accept:'application/json'},signal:controller.signal});
        if(!response.ok)throw new Error('Crop-area boundaries could not load. Retry the seasonal layer.');
        var data=await response.json();if(current!==revision)return;
        if(!data.available)throw new Error(data.message||'Crop-area drawing is not available yet.');
        if(Number(data.year)!==Number(settings.year)||data.season!==settings.season||!Array.isArray(data.records))throw new Error('Crop-area response did not match the selected season.');
        if(data.records.length!==wanted.length)throw new Error('Some selected parcels are no longer available. Reload the map.');
        var total=0, seen=new Set();
        data.records.forEach(record=>{
          if(!wanted.includes(String(record.plot_id))||!Array.isArray(record.areas)||record.areas.length>8)throw new Error('Invalid crop-area response.');
          if(seen.has(String(record.plot_id)))throw new Error('Duplicate crop-area parcel.');
          seen.add(String(record.plot_id));
          record.areas.forEach(area=>{
            if(!/^#[a-f0-9]{6}$/i.test(area.color)||!Array.isArray(area.polygon)||area.polygon.length<3||area.polygon.length>50)throw new Error('Invalid crop-area boundary.');
            area.polygon.forEach(point=>{if(!Number.isFinite(Number(point.lat))||!Number.isFinite(Number(point.lng))||Math.abs(Number(point.lat))>90||Math.abs(Number(point.lng))>180)throw new Error('Invalid crop-area coordinate.');});
            total+=area.polygon.length;
          });
        });
        if(total>8000)throw new Error('Crop-area response is too large.');
        state={status:'ready',records:data.records,error:'',partial:partial};notify();
      }catch(error){if(current!==revision||error.name==='AbortError')return;state={status:'error',records:[],error:error.message,partial:partial};notify();}
    }
    return {load:load,get state(){return state;}};
  }
  root.PlantedAreaLayer={create:create};
  if(typeof module!=='undefined'&&module.exports)module.exports=root.PlantedAreaLayer;
})(typeof window!=='undefined'?window:globalThis);
