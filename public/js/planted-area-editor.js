(() => {
  'use strict';

  function mount(scope) {
  const root = scope && typeof scope.querySelector === 'function' ? scope : document;
  // The modal mounts the editor inside #plantedAreaEditor, while its save
  // status lives in the surrounding form. Prefer the editor scope, then fall
  // back to the document for those form-level controls.
  const byId = id => root === document ? document.getElementById(id) : (root.querySelector('#' + id) || document.getElementById(id));
  const configElement = byId('plantedAreaConfig') || document.getElementById('plantedAreaConfig');
  if (!configElement) return null;
  const config = JSON.parse(configElement.textContent);
  const isLine = area => area?.type === 'line' || Array.isArray(area?.path);
  const pointsFor = area => isLine(area) ? area.path : area.polygon;
  let areas = (config.areas || []).filter(a => a && Array.isArray(pointsFor(a)));
  let map, outline, draft = null, draftGuide = null, drawing = false, selected = -1, shapes = [], shades = [], badges = [], dirty = false;
  const status = text => { byId('plantedMapStatus').textContent = text; };
  const sections = list => window.CropAreaBadges?.sections?.(list,config.polygon) || {rings:[],conflicts:[]};
  const overlapMessage = pairs => `Crop marks ${pairs[0][0]+1} and ${pairs[0][1]+1} cover the same land. Move the points so the sections sit beside each other. Shared edges are allowed.`;
  const sync = () => {
    const input = byId('plantedAreasInput');
    if (input) input.value = JSON.stringify(areas);
    byId('plantedAreaCount').textContent = `${areas.length}/8`;
    const conflicts=sections(areas).conflicts;
    byId('plantedAreaSummary').textContent = conflicts.length ? overlapMessage(conflicts) : dirty ? 'Changes are not saved yet.' : areas.length ? 'Recorded for this season.' : 'No areas added yet.';
    if (config.canDraw) {
      byId('plantedSaveState').textContent = drawing ? 'Finish or cancel your drawing first.' : dirty ? 'Unsaved changes. Save to keep your crop areas.' : 'No changes yet.';
      byId('plantedIdleActions').hidden = drawing;
      byId('plantedDraftActions').hidden = !drawing;
      byId('plantedControlHeading').textContent = drawing ? 'Drawing a crop mark' : selected >= 0 ? (isLine(areas[selected]) ? 'Edit this crop mark' : 'Edit this crop area') : 'Add a crop mark';
      byId('plantedControlHint').textContent = drawing ? 'Click around the planted section to shade it, or use two points for a line. No hectares are calculated.' : selected >= 0 ? 'Move points on the map or change the details below.' : 'Which crop is planted here?';
      ['plantedCrop', 'plantedName', 'plantedVariety'].forEach(id => { byId(id).disabled = drawing; });
      byId('plantedUpdate').hidden = drawing || selected < 0;
      byId('plantedStart').textContent = selected >= 0 ? 'Draw another crop mark' : 'Start crop line';
      byId('plantedStart').className = selected >= 0 ? 'module-button' : 'module-button module-button-primary';
      const crops = [...new Set(areas.map(area => area.crop))];
      if (crops.length && byId('crop')) byId('crop').value = crops.length === 1 ? crops[0] : 'mixed';
      byId('plantedStart').disabled = !map || drawing || areas.length >= 8;
      byId('plantedFinish').disabled = !drawing || draft.getPath().getLength() < 2;
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
    setTimeout(() => shape.setOptions({strokeWeight:2,fillOpacity:.38}), 700);
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
    pointsFor(areas[index]).forEach(point => bounds.extend(point));
    map.fitBounds(bounds, 60);
    if(!sections(areas).conflicts.some(pair=>pair.includes(index)))pulse(shapes[index]);
    badges.forEach((badge, i) => badge?.select(i === index));
    badges[index]?.animate();
    renderList();
    status(`${config.crops[areas[index].crop]} · ${areas[index].name || (isLine(areas[index]) ? 'Crop mark' : 'Planted area')}${config.canDraw ? '. Drag points to adjust this mark.' : ''}`);
  }
  function renderList() {
    const list = byId('plantedAreaList'); list.replaceChildren();
    areas.forEach((area, index) => {
      const item = document.createElement('div'); item.className = 'planted-area-item' + (index === selected ? ' is-selected' : '');
      const swatch = document.createElement('span'); swatch.className = 'planted-area-swatch'; swatch.style.backgroundColor = config.colors[area.crop] || '#8A9299'; swatch.setAttribute('aria-hidden','true');
      const text = document.createElement('p');
      text.textContent = [config.crops[area.crop] || 'Crop', isLine(area) ? 'Visual crop mark · no area calculated' : area.area_ha !== undefined ? `${Number(area.area_ha).toFixed(4)} mapped ha` : 'Area calculated on save', area.name, area.variety].filter(Boolean).join(' · ');
      const button = document.createElement('button'); button.type = 'button'; button.className = 'module-button'; button.textContent = config.canDraw ? (isLine(area) ? 'Edit line' : 'Edit area') : 'View on map'; button.disabled = !map || drawing; button.setAttribute('aria-pressed', String(index === selected)); button.addEventListener('click',()=>focus(index));
      const actions = document.createElement('div'); actions.className = 'planted-area-actions';
      actions.append(button);
      item.append(swatch,text,actions);
      if (config.canDraw) {
        const remove = document.createElement('button'); remove.type='button';remove.className='module-button';remove.textContent='Remove';remove.disabled=drawing;
        remove.addEventListener('click',()=>{if(!confirm('Remove this crop mark? It will be removed when you save this season.'))return;areas.splice(index,1);selected=-1;dirty=true;render();});
        actions.append(remove);
      }
      list.append(item);
    });
    if (!areas.length) list.textContent = config.canDraw ? 'Your crop marks will appear here after you finish a line.' : 'No crop marks recorded for this season.';
  }
  function render() {
    badges.forEach(badge=>badge?.remove());badges=[];
    shapes.forEach(shape=>shape.setMap(null)); shapes=[];
    shades.forEach(shape=>shape?.setMap(null)); shades=[];
    const conflicts=sections(areas).conflicts;
    const conflicting=new Set(conflicts.flat());
    if (map) areas.forEach((area,index)=>{
      const color = config.colors[area.crop] || '#8A9299';
      const line = isLine(area);
      const enclosure=()=>line ? window.CropAreaBadges?.shadedRing?.(area.path,config.polygon) : null;
      const ring=conflicting.has(index) ? null : enclosure();
      const shade=ring ? new google.maps.Polygon({map,paths:ring,fillColor:color,fillOpacity:.38,strokeOpacity:0,clickable:false,zIndex:4}) : null;
      shades.push(shade);
      const shape = line
        ? new google.maps.Polyline({map,path:area.path,strokeColor:color,strokeOpacity:1,strokeWeight:4,editable:config.canDraw && index===selected,zIndex:5})
        : new google.maps.Polygon({map,paths:area.polygon,fillColor:color,fillOpacity:conflicting.has(index)?0:.38,strokeColor:color,strokeWeight:2,editable:config.canDraw && index===selected,zIndex:5});
      const path=shape.getPath();
      const changed=()=>{
        const previous=pointsFor(area),previousHectares=area.area_ha;
        const points=path.getArray().map(p=>({lat:p.lat(),lng:p.lng()}));
        if(line)areas[index].path=points;else{areas[index].polygon=points;delete areas[index].area_ha;}
        const pairs=sections(areas).conflicts;
        if(pairs.length){
          // Keep the last accepted geometry instead of introducing overlapping fills.
          if(line)area.path=previous;else{area.polygon=previous;if(previousHectares!==undefined)area.area_ha=previousHectares;}
          render();status(overlapMessage(pairs));return;
        }
        const nextRing=enclosure();
        shades[index]?.setMap(null);
        shades[index]=nextRing ? new google.maps.Polygon({map,paths:nextRing,fillColor:color,fillOpacity:.38,strokeOpacity:0,clickable:false,zIndex:4}) : null;
        badges[index]?.remove();
        badges[index]=window.CropAreaBadges?.overlay(google.maps,document,map,nextRing ? {crop:area.crop,name:area.name,polygon:nextRing} : area,()=>focus(index)) || null;
        badges[index]?.select(index===selected);dirty=true;
        if(conflicts.length)render();else{sync();renderList();}
      };
      ['set_at','insert_at','remove_at'].forEach(event=>path.addListener(event,changed));
      shape.addListener('click',()=>focus(index));shapes.push(shape);
      const badge=window.CropAreaBadges?.overlay(google.maps,document,map,ring ? {crop:area.crop,name:area.name,polygon:ring} : area,()=>focus(index)) || null;
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
    if (!Array.isArray(config.polygon) || config.polygon.length < 3 || config.polygon.length > 1000) {status('The parcel boundary needs office review before drawing crop marks.');return;}
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
    const addPoint=event=>{if(!drawing||!event.latLng)return;if(!inside(event.latLng)){status('That point is outside the saved parcel. Click inside the white outline, or update the parcel boundary first.');return;}if(draft.getPath().getLength()>=50){status('This crop line already has 50 points. Finish it or undo a point.');return;}draft.getPath().push(event.latLng);draftGuide?.update(draft.getPath().getArray());sync();status(draft.getPath().getLength()===1?'Point 1 marked in green. Click along the planted section to continue.':`${draft.getPath().getLength()} points connected. Finish after at least two.`);};
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
        draft=new google.maps.Polyline({map,path:[],clickable:false,strokeColor:color,strokeOpacity:1,strokeWeight:4,zIndex:10});
        draft.setPath(new google.maps.MVCArray());
        draftGuide=window.MapDrawingGuide?.create(google.maps,document,map) || null;
        sync();renderList();status('Click or tap along the planted section inside the white parcel outline, then choose Finish crop line.');
      });
      byId('plantedFinish').addEventListener('click',()=>{
        if(!drawing||draft.getPath().getLength()<2)return;
        const mark={type:'line',crop:byId('plantedCrop').value,name:byId('plantedName').value.trim(),variety:byId('plantedVariety').value.trim(),path:draft.getPath().getArray().map(p=>({lat:p.lat(),lng:p.lng()}))};
        const conflicts=sections([...areas,mark]).conflicts;
        if(conflicts.length){status(overlapMessage(conflicts)+' Undo a point or cancel this drawing.');return;}
        areas.push(mark);
        dirty=true;cancel();render();focus(areas.length-1);status('Crop line added. Save the season to keep this visual mark.');
      });
      byId('plantedUndo').addEventListener('click',()=>{if(drawing){draft.getPath().pop();draftGuide?.update(draft.getPath().getArray());status(draft.getPath().getLength()?`${draft.getPath().getLength()} points left. Click to continue the crop line.`:'All points undone. Click the first point to start again.');}sync();});
      byId('plantedCenter').addEventListener('click',()=>addPoint({latLng:map.getCenter()}));
      byId('plantedCancel').addEventListener('click',cancel);
      byId('plantedAreasInput').form.addEventListener('submit',event=>{if(drawing){event.preventDefault();status('Finish or cancel the drawing before saving.');return;}const conflicts=sections(areas).conflicts;if(conflicts.length){event.preventDefault();status(overlapMessage(conflicts));return;}sync();});
    }
    render();status('White outline: saved parcel. Colored lines: visual crop marks. Choose a mark to highlight it.');
  }
  function failure(){status('The map could not load. Existing crop marks remain in the list. Reload to draw or adjust them.');renderList();}
  renderList();sync();
  if(window.google?.maps?.Map)init();
  else if(!config.key)failure();
  else{
    const timer=setTimeout(failure,15000);
    window.initPlantedAreaMap=()=>{clearTimeout(timer);init();};
    const previous=window.gm_authFailure;window.gm_authFailure=()=>{failure();previous?.();};
    const script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?key='+encodeURIComponent(config.key)+'&v=weekly&loading=async&callback=initPlantedAreaMap';script.async=true;script.onerror=()=>{clearTimeout(timer);failure();};document.head.append(script);
  }
  return {
    isDrawing: () => drawing,
    sync,
    destroy: () => {
      draftGuide?.remove();
      badges.forEach(badge => badge?.remove());
      shapes.forEach(shape => shape.setMap(null));
      shades.forEach(shape => shape?.setMap(null));
      draft?.setMap(null);
      draftGuide = null;
      badges = [];
      shapes = [];
      shades = [];
      draft = null;
      map = null;
    }
  };
  }

  window.PlantedAreaEditor = { mount };
  const initialRoot = document.getElementById('plantedAreaEditor');
  if (initialRoot) mount(initialRoot);
})();
