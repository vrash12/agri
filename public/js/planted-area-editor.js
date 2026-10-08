(() => {
  'use strict';
  const configElement = document.getElementById('plantedAreaConfig');
  if (!configElement) return;
  const config = JSON.parse(configElement.textContent);
  const byId = id => document.getElementById(id);
  let areas = (config.areas || []).filter(a => a && Array.isArray(a.polygon));
  let map, outline, draft = null, drawing = false, selected = -1, shapes = [], dirty = false;
  const status = text => { byId('plantedMapStatus').textContent = text; };
  const sync = () => {
    const input = byId('plantedAreasInput');
    if (input) input.value = JSON.stringify(areas);
    byId('plantedAreaSummary').textContent = `${areas.length} of 8 crop areas. ${dirty ? 'Changes are not saved yet.' : 'Recorded for the selected season.'}`;
    if (config.canDraw) {
      byId('plantedStart').disabled = !map || drawing || areas.length >= 8;
      byId('plantedFinish').disabled = !drawing || draft.getPath().getLength() < 3;
      byId('plantedUndo').disabled = !drawing || !draft.getPath().getLength();
      byId('plantedCancel').disabled = !drawing;
      byId('plantedCenter').disabled = !drawing;
      byId('plantedUpdate').disabled = drawing || selected < 0;
    }
  };
  const pulse = shape => {
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    shape.setOptions({strokeWeight:5,fillOpacity:.45});
    setTimeout(() => shape.setOptions({strokeWeight:2,fillOpacity:.25}), 700);
  };
  function focus(index) {
    if (drawing) return;
    selected = index;
    if (config.canDraw) {
      byId('plantedCrop').value = areas[index].crop;
      byId('plantedName').value = areas[index].name || '';
      byId('plantedVariety').value = areas[index].variety || '';
      sync();
    }
    shapes.forEach((shape, i) => shape.setEditable(config.canDraw && i === index));
    const bounds = new google.maps.LatLngBounds();
    areas[index].polygon.forEach(point => bounds.extend(point));
    map.fitBounds(bounds, 60);
    pulse(shapes[index]);
    status(`${config.crops[areas[index].crop]} · ${areas[index].name || 'Planted section'}${config.canDraw ? '. Drag corners to adjust this boundary.' : ''}`);
  }
  function renderList() {
    const list = byId('plantedAreaList'); list.replaceChildren();
    areas.forEach((area, index) => {
      const item = document.createElement('div'); item.className = 'planted-area-item';
      const swatch = document.createElement('span'); swatch.className = 'planted-area-swatch'; swatch.style.backgroundColor = config.colors[area.crop] || '#8A9299'; swatch.setAttribute('aria-hidden','true');
      const text = document.createElement('p');
      text.textContent = [config.crops[area.crop] || 'Crop', area.name, area.variety, area.area_ha !== undefined ? `${Number(area.area_ha).toFixed(4)} mapped ha` : 'Area calculated on save'].filter(Boolean).join(' · ');
      const button = document.createElement('button'); button.type = 'button'; button.className = 'module-button'; button.textContent = config.canDraw ? 'Focus / adjust' : 'Focus'; button.disabled = !map; button.addEventListener('click',()=>focus(index));
      item.append(swatch,text,button);
      if (config.canDraw) {
        const remove = document.createElement('button'); remove.type='button';remove.className='module-button';remove.textContent='Remove';remove.disabled=drawing;
        remove.addEventListener('click',()=>{if(!confirm('Remove this planted section? It will be removed when you save this season.'))return;areas.splice(index,1);selected=-1;dirty=true;render();});
        item.append(remove);
      }
      list.append(item);
    });
    if (!areas.length) list.textContent = 'No planted-area boundaries recorded. The parcel-wide crop classification remains available below.';
  }
  function render() {
    shapes.forEach(shape=>shape.setMap(null)); shapes=[];
    if (map) areas.forEach((area,index)=>{
      const color = config.colors[area.crop] || '#8A9299';
      const shape = new google.maps.Polygon({map,paths:area.polygon,fillColor:color,fillOpacity:.25,strokeColor:color,strokeWeight:2,editable:config.canDraw && index===selected,zIndex:5});
      const path=shape.getPath();
      const changed=()=>{areas[index].polygon=path.getArray().map(p=>({lat:p.lat(),lng:p.lng()}));delete areas[index].area_ha;dirty=true;sync();renderList();};
      ['set_at','insert_at','remove_at'].forEach(event=>path.addListener(event,changed));
      shape.addListener('click',()=>focus(index));shapes.push(shape);
    });
    renderList();sync();
  }
  function cancel() {
    draft?.setMap(null);draft=null;drawing=false;
    if(outline)outline.setOptions({clickable:false});
    renderList();sync();
    status('Drawing cancelled. Existing crop areas are unchanged.');
  }
  function init() {
    if (!Array.isArray(config.polygon) || config.polygon.length < 3 || config.polygon.length > 1000) {status('The parcel boundary needs office review before drawing crop areas.');return;}
    map = new google.maps.Map(byId('plantedAreaMap'), {mapTypeId:'satellite',center:config.polygon[0],zoom:18,gestureHandling:'cooperative',streetViewControl:false});
    outline = new google.maps.Polygon({map,paths:config.polygon,fillOpacity:.02,strokeColor:'#FFFFFF',strokeWeight:3,clickable:false,zIndex:1});
    const bounds=new google.maps.LatLngBounds();config.polygon.forEach(p=>bounds.extend(p));map.fitBounds(bounds,30);
    const inside = point => {
      const x=point.lng(), y=point.lat(), ring=config.polygon;
      let included=false;
      for(let i=0,j=ring.length-1;i<ring.length;j=i++){
        const a=ring[j],b=ring[i],cross=(x-a.lng)*(b.lat-a.lat)-(y-a.lat)*(b.lng-a.lng);
        if(Math.abs(cross)<1e-12&&x>=Math.min(a.lng,b.lng)-1e-10&&x<=Math.max(a.lng,b.lng)+1e-10&&y>=Math.min(a.lat,b.lat)-1e-10&&y<=Math.max(a.lat,b.lat)+1e-10)return true;
        if((a.lat>y)!==(b.lat>y)&&x<(b.lng-a.lng)*(y-a.lat)/(b.lat-a.lat)+a.lng)included=!included;
      }
      return included;
    };
    const addPoint=event=>{if(!drawing||!event.latLng)return;if(!inside(event.latLng)){status('That corner is outside the saved parcel. Place it inside the white outline.');return;}if(draft.getPath().getLength()>=50){status('This boundary already has 50 corners. Finish it or undo a point.');return;}draft.getPath().push(event.latLng);sync();status(`${draft.getPath().getLength()} corners added. Finish after at least three corners.`);};
    map.addListener('click',addPoint);outline.addListener('click',addPoint);
    if(config.canDraw){
      byId('plantedUpdate').addEventListener('click',()=>{
        if(selected<0||drawing)return;
        Object.assign(areas[selected],{crop:byId('plantedCrop').value,name:byId('plantedName').value.trim(),variety:byId('plantedVariety').value.trim()});
        dirty=true;render();status('Section details updated. Save the season to keep these changes.');
      });
      byId('plantedStart').addEventListener('click',()=>{
        selected=-1;shapes.forEach(s=>s.setEditable(false));drawing=true;outline.setOptions({clickable:true});
        const color=config.colors[byId('plantedCrop').value];
        draft=new google.maps.Polygon({map,paths:[],clickable:false,strokeColor:color,fillColor:color,strokeWeight:3,fillOpacity:.25,zIndex:10});
        sync();renderList();status('Click or tap corners inside the white parcel outline, then choose Finish boundary.');
      });
      byId('plantedFinish').addEventListener('click',()=>{
        if(!drawing||draft.getPath().getLength()<3)return;
        areas.push({crop:byId('plantedCrop').value,name:byId('plantedName').value.trim(),variety:byId('plantedVariety').value.trim(),polygon:draft.getPath().getArray().map(p=>({lat:p.lat(),lng:p.lng()}))});
        dirty=true;cancel();render();focus(areas.length-1);status('Area added. Save the season to keep this boundary.');
      });
      byId('plantedUndo').addEventListener('click',()=>{if(drawing)draft.getPath().pop();sync();});
      byId('plantedCenter').addEventListener('click',()=>addPoint({latLng:map.getCenter()}));
      byId('plantedCancel').addEventListener('click',cancel);
      byId('plantedAreasInput').form.addEventListener('submit',event=>{if(drawing){event.preventDefault();status('Finish or cancel the drawing before saving.');return;}sync();});
    }
    render();status('White outline: saved parcel. Colored boundaries: recorded crop sections. Choose a section to highlight it.');
  }
  function failure(){status('The map could not load. Existing crop-area boundaries remain in the list. Reload to draw or adjust boundaries.');renderList();}
  renderList();sync();
  if(window.google?.maps?.Map)init();
  else if(!config.key)failure();
  else{
    const timer=setTimeout(failure,15000);
    window.initPlantedAreaMap=()=>{clearTimeout(timer);init();};
    const previous=window.gm_authFailure;window.gm_authFailure=()=>{failure();previous?.();};
    const script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(config.key)+'&v=weekly&loading=async&callback=initPlantedAreaMap';script.async=true;script.onerror=()=>{clearTimeout(timer);failure();};document.head.append(script);
  }
})();
