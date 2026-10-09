(() => {
  'use strict';
  const configElement = document.getElementById('plantedAreaConfig');
  if (!configElement) return;
  const config = JSON.parse(configElement.textContent);
  const byId = id => document.getElementById(id);
  let areas = (config.areas || []).filter(a => a && Array.isArray(a.polygon));
  let map, outline, draft = null, draftGuide = null, drawing = false, selected = -1, shapes = [], badges = [], dirty = false;
  const status = text => { byId('plantedMapStatus').textContent = text; };
  const sync = () => {
    const input = byId('plantedAreasInput');
    if (input) input.value = JSON.stringify(areas);
    byId('plantedAreaCount').textContent = `${areas.length}/8`;
    byId('plantedAreaSummary').textContent = dirty ? 'Changes are not saved yet.' : areas.length ? 'Recorded for this season.' : 'No areas added yet.';
    if (config.canDraw) {
      byId('plantedSaveState').textContent = drawing ? 'Finish or cancel your drawing first.' : dirty ? 'Unsaved changes. Save to keep your crop areas.' : 'No changes yet.';
      byId('plantedIdleActions').hidden = drawing;
      byId('plantedDraftActions').hidden = !drawing;
      byId('plantedControlHeading').textContent = drawing ? 'Drawing your crop area' : selected >= 0 ? 'Edit this crop area' : 'Add a crop area';
      byId('plantedControlHint').textContent = drawing ? 'Mark corners inside the white outline.' : selected >= 0 ? 'Move corners on the map or change the details below.' : 'Which crop is planted here?';
      ['plantedCrop', 'plantedName', 'plantedVariety'].forEach(id => { byId(id).disabled = drawing; });
      byId('plantedUpdate').hidden = drawing || selected < 0;
      byId('plantedStart').textContent = selected >= 0 ? 'Draw another area' : 'Start drawing';
      byId('plantedStart').className = selected >= 0 ? 'module-button' : 'module-button module-button-primary';
      const crops = [...new Set(areas.map(area => area.crop))];
      if (crops.length && byId('crop')) byId('crop').value = crops.length === 1 ? crops[0] : 'mixed';
      byId('plantedStart').disabled = !map || drawing || areas.length >= 8;
      byId('plantedFinish').disabled = !drawing || draft.getPath().getLength() < 3;
      byId('plantedUndo').disabled = !drawing || !draft.getPath().getLength();
      byId('plantedCancel').disabled = !drawing;
      byId('plantedCenter').disabled = !drawing;
      byId('plantedUpdate').disabled = drawing || selected < 0;
    }
    badges.forEach(badge => badge?.setDisabled(drawing));
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
      byId('plantedSectionDetails').open = !!(areas[index].name || areas[index].variety);
      sync();
    }
    shapes.forEach((shape, i) => shape.setEditable(config.canDraw && i === index));
    const bounds = new google.maps.LatLngBounds();
    areas[index].polygon.forEach(point => bounds.extend(point));
    map.fitBounds(bounds, 60);
    pulse(shapes[index]);
    badges.forEach((badge, i) => badge?.select(i === index));
    badges[index]?.animate();
    renderList();
    status(`${config.crops[areas[index].crop]} · ${areas[index].name || 'Planted section'}${config.canDraw ? '. Drag corners to adjust this boundary.' : ''}`);
  }
  function renderList() {
    const list = byId('plantedAreaList'); list.replaceChildren();
    areas.forEach((area, index) => {
      const item = document.createElement('div'); item.className = 'planted-area-item' + (index === selected ? ' is-selected' : '');
      const swatch = document.createElement('span'); swatch.className = 'planted-area-swatch'; swatch.style.backgroundColor = config.colors[area.crop] || '#8A9299'; swatch.setAttribute('aria-hidden','true');
      const text = document.createElement('p');
      text.textContent = [config.crops[area.crop] || 'Crop', area.name, area.variety, area.area_ha !== undefined ? `${Number(area.area_ha).toFixed(4)} mapped ha` : 'Area calculated on save'].filter(Boolean).join(' · ');
      const button = document.createElement('button'); button.type = 'button'; button.className = 'module-button'; button.textContent = config.canDraw ? 'Edit area' : 'View on map'; button.disabled = !map || drawing; button.setAttribute('aria-pressed', String(index === selected)); button.addEventListener('click',()=>focus(index));
      const actions = document.createElement('div'); actions.className = 'planted-area-actions';
      actions.append(button);
      item.append(swatch,text,actions);
      if (config.canDraw) {
        const remove = document.createElement('button'); remove.type='button';remove.className='module-button';remove.textContent='Remove';remove.disabled=drawing;
        remove.addEventListener('click',()=>{if(!confirm('Remove this planted section? It will be removed when you save this season.'))return;areas.splice(index,1);selected=-1;dirty=true;render();});
        actions.append(remove);
      }
      list.append(item);
    });
    if (!areas.length) list.textContent = config.canDraw ? 'Your crop areas will appear here after you finish a boundary.' : 'No crop areas recorded for this season.';
  }
  function render() {
    badges.forEach(badge=>badge?.remove());badges=[];
    shapes.forEach(shape=>shape.setMap(null)); shapes=[];
    if (map) areas.forEach((area,index)=>{
      const color = config.colors[area.crop] || '#8A9299';
      const shape = new google.maps.Polygon({map,paths:area.polygon,fillColor:color,fillOpacity:.25,strokeColor:color,strokeWeight:2,editable:config.canDraw && index===selected,zIndex:5});
      const path=shape.getPath();
      const changed=()=>{areas[index].polygon=path.getArray().map(p=>({lat:p.lat(),lng:p.lng()}));delete areas[index].area_ha;badges[index]?.update(areas[index].polygon);dirty=true;sync();renderList();};
      ['set_at','insert_at','remove_at'].forEach(event=>path.addListener(event,changed));
      shape.addListener('click',()=>focus(index));shapes.push(shape);
      const badge=window.CropAreaBadges?.overlay(google.maps,document,map,area,()=>focus(index)) || null;
      badge?.select(index===selected);badges.push(badge);
    });
    renderList();sync();
  }
  function cancel() {
    draftGuide?.remove();draftGuide=null;
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
    const addPoint=event=>{if(!drawing||!event.latLng)return;if(!inside(event.latLng)){status('That corner is outside the saved parcel. Place it inside the white outline.');return;}if(draft.getPath().getLength()>=50){status('This boundary already has 50 corners. Finish it or undo a point.');return;}draft.getPath().push(event.latLng);draftGuide?.update(draft.getPath().getArray());sync();status(draft.getPath().getLength()===1?'Corner 1 marked in green. Click the next corner to connect them.':`${draft.getPath().getLength()} corners connected. Keep adding corners or finish after at least three.`);};
    map.addListener('click',addPoint);outline.addListener('click',addPoint);
    if(config.canDraw){
      ['crop', 'notes'].forEach(id => byId(id)?.addEventListener('input', () => { dirty = true; sync(); }));
      byId('plantedUpdate').addEventListener('click',()=>{
        if(selected<0||drawing)return;
        Object.assign(areas[selected],{crop:byId('plantedCrop').value,name:byId('plantedName').value.trim(),variety:byId('plantedVariety').value.trim()});
        dirty=true;render();status('Section details updated. Save the season to keep these changes.');
      });
      byId('plantedStart').addEventListener('click',()=>{
        selected=-1;shapes.forEach(s=>s.setEditable(false));drawing=true;outline.setOptions({clickable:true});
        const color=config.colors[byId('plantedCrop').value];
        draft=new google.maps.Polygon({map,paths:[],clickable:false,strokeColor:color,fillColor:color,strokeWeight:3,fillOpacity:.25,zIndex:10});
        // An empty paths array contains no ring, so getPath() may be undefined.
        draft.setPath(new google.maps.MVCArray());
        draftGuide=window.MapDrawingGuide?.create(google.maps,document,map) || null;
        sync();renderList();status('Click or tap corners inside the white parcel outline, then choose Finish boundary.');
      });
      byId('plantedFinish').addEventListener('click',()=>{
        if(!drawing||draft.getPath().getLength()<3)return;
        areas.push({crop:byId('plantedCrop').value,name:byId('plantedName').value.trim(),variety:byId('plantedVariety').value.trim(),polygon:draft.getPath().getArray().map(p=>({lat:p.lat(),lng:p.lng()}))});
        dirty=true;cancel();render();focus(areas.length-1);status('Area added. Save the season to keep this boundary.');
      });
      byId('plantedUndo').addEventListener('click',()=>{if(drawing){draft.getPath().pop();draftGuide?.update(draft.getPath().getArray());status(draft.getPath().getLength()?`${draft.getPath().getLength()} corners left. Click to continue the boundary.`:'All corners undone. Click the first corner to start again.');}sync();});
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
