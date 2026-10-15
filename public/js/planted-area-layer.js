(function (root) {
  'use strict';
  function create(options) {
    var revision = 0, controller, key = '', periodKey = '', accepted = new Map(), retryAt = 0, refreshRequired = false;
    var state = {status:'off',records:[],error:'',partial:false,loaded:0,total:0};
    function notify(change){options.onChange(state, change || {});}
    function upsert(record, settings, change) {
      if (!record || record.plot_id === null || record.plot_id === undefined || !Array.isArray(record.areas)) return false;
      if (settings && state.year != null && (Number(settings.year) !== Number(state.year) || settings.season !== state.season)) return false;
      accepted.set(String(record.plot_id), record);
      var total = Math.max(Number(state.total) || 0, accepted.size);
      state = { ...state, status: 'ready', year: Number(settings?.year ?? state.year), season: settings?.season ?? state.season,
        records: [...accepted.values()], error: '', loaded: accepted.size, total: total, partial: accepted.size < total };
      // A first modal save seeds this period before the metadata refresh. Keep
      // the validated row instead of discarding it and requesting it again.
      periodKey = state.year + '/' + state.season;
      if (!change?.silent) notify(change);
      return true;
    }
    function refresh(change){notify(change);}
    function validate(data, settings, ids) {
      if(!data.available)throw new Error(data.message || 'Crop drawing is not available yet.');
      if(Number(data.year)!==Number(settings.year) || data.season!==settings.season || !Array.isArray(data.records))throw new Error('Crop drawings did not match the selected season. Retry.');
      if(data.records.length!==ids.length)throw new Error('Some parcels are no longer available. Reload the map.');
      var total=0, seen=new Set(), requested=new Set(ids);
      data.records.forEach(record=>{
        if(!record || !requested.has(String(record.plot_id)) || !Array.isArray(record.areas) || record.areas.length>8)throw new Error('Invalid crop drawing response.');
        if(seen.has(String(record.plot_id)))throw new Error('Duplicate crop drawing parcel.');
        seen.add(String(record.plot_id));
        record.areas.forEach(area=>{
          if(!area)throw new Error('Invalid crop drawing.');
          var line=area.type==='line' || Array.isArray(area.path);
          var points=line?area.path:area.polygon;
          if(!/^#[a-f0-9]{6}$/i.test(area.color) || !Array.isArray(points) || (line?points.length<2:points.length<3) || points.length>50)throw new Error('Invalid crop drawing.');
          points.forEach(point=>{if(!point || point.lat===null || point.lng===null || !Number.isFinite(Number(point.lat)) || !Number.isFinite(Number(point.lng)) || Math.abs(Number(point.lat))>90 || Math.abs(Number(point.lng))>180)throw new Error('Invalid crop drawing coordinate.');});
          total+=points.length;
        });
      });
      if(total>8000)throw new Error('Crop drawing response is too large.');
    }
    async function load(settings, ids, force, resume) {
      var wanted=[...new Set(ids.map(String))].sort();
      if(!settings.enabled || !wanted.length){
        if(state.status==='off')return;
        revision++;controller?.abort();key='';periodKey='';accepted.clear();retryAt=0;refreshRequired=false;
        state={status:'off',records:[],error:'',partial:false,loaded:0,total:0};notify();return;
      }
      var nextPeriod=settings.year+'/'+settings.season;
      var nextKey=nextPeriod+'/'+wanted.join(',');
      if(force)refreshRequired=true;
      if(!force && key===nextKey && (state.status==='ready' || state.status==='loading'))return;
      if(!force && !resume && key===nextKey && state.status==='error')return;
      if(key===nextKey && Date.now()<retryAt){notify();return;}
      // An explicit retry resumes unfinished batches. A refresh/new period replaces data.
      if(refreshRequired || periodKey!==nextPeriod){accepted.clear();retryAt=0;refreshRequired=false;}
      else {var keep=new Set(wanted);accepted.forEach((record,id)=>{if(!keep.has(id))accepted.delete(id);});}
      periodKey=nextPeriod;
      key=nextKey;var current=++revision;controller?.abort();controller=new AbortController();
      var signal=controller.signal;
      state={status:'loading',records:[...accepted.values()],error:'',partial:accepted.size>0,loaded:accepted.size,total:wanted.length,year:settings.year,season:settings.season};notify();
      try{
        var pending=wanted.filter(id=>!accepted.has(id));
        for(var start=0;start<pending.length;start+=20){
          var batch=pending.slice(start,start+20);
          var query=new URLSearchParams({year:String(settings.year),season:settings.season});batch.forEach(id=>query.append('plot_ids[]',id));
          var response=await options.fetch(options.url+'?'+query,{headers:{Accept:'application/json'},signal:signal});
          if(current!==revision)return;
          if(!response.ok){
            if(response.status===429){
              var seconds=Number(response.headers?.get('Retry-After'));
              seconds=Number.isFinite(seconds) && seconds>0 ? Math.min(60,Math.ceil(seconds)) : 60;
              retryAt=Date.now()+seconds*1000;
              throw new Error('Crop drawings are temporarily limited. Wait '+seconds+' seconds, then retry to load the remaining drawings.');
            }
            if(response.status===401 || response.status===419){accepted.clear();throw new Error('Your session ended. Sign in again.');}
            throw new Error('Some crop drawings could not load. Retry to load the remaining drawings.');
          }
          var data=await response.json();if(current!==revision)return;
          validate(data,settings,batch);
          data.records.forEach(record=>accepted.set(String(record.plot_id),record));
          state={...state,records:[...accepted.values()],loaded:accepted.size,partial:accepted.size<wanted.length};notify();
        }
        if(current!==revision)return;
        state={...state,status:'ready',partial:false};notify();
      }catch(error){
        if(current!==revision || error.name==='AbortError')return;
        state={...state,status:'error',records:[...accepted.values()],loaded:accepted.size,error:error.message,partial:accepted.size<wanted.length,retryAt:retryAt};notify();
      }
    }
    return {load:load,upsert:upsert,refresh:refresh,get state(){return state;}};
  }
  root.PlantedAreaLayer={create:create};
  if(typeof module!=='undefined'&&module.exports)module.exports=root.PlantedAreaLayer;
})(typeof window!=='undefined'?window:globalThis);
