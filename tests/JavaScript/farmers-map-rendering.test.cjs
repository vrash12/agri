const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const geometry = require('../../public/js/parcel-display-geometry.js');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/farmers-maps.js'), 'utf8');

test('land plot actions stay collapsed inside an accessible details card', () => {
  assert.match(source, /<details class="map-plot-item map-plot-card">/);
  assert.match(source, /<summary class="map-plot-toggle">/);
  assert.match(source, /map-plot-show-label/);
  assert.match(source, /map-plot-hide-label/);
  assert.match(source, /map-plot-actions/);
});

// Run the real renderer with a lightweight Maps DOM adapter. No Google service,
// credentials, database, or source farmer data are used by these regressions.
function declaration(name) {
  const match = new RegExp('(?:async )?function ' + name + '\\s*\\(').exec(source);
  assert.ok(match, 'Missing renderer function ' + name);
  const start = source.indexOf('{', match.index);
  let depth = 0, quote = null, comment = null;
  for (let i = start; i < source.length; i++) {
    const c = source[i], next = source[i + 1];
    if (comment === 'line') { if (c === '\n') comment = null; continue; }
    if (comment === 'block') { if (c === '*' && next === '/') { comment = null; i++; } continue; }
    if (quote) { if (c === '\\') { i++; continue; } if (c === quote) quote = null; continue; }
    if (c === '/' && next === '/') { comment = 'line'; i++; continue; }
    if (c === '/' && next === '*') { comment = 'block'; i++; continue; }
    if (c === '"' || c === "'" || c === '`') { quote = c; continue; }
    if (c === '{') depth++;
    if (c === '}' && --depth === 0) return source.slice(match.index, i + 1);
  }
  throw new Error('Unclosed function ' + name);
}

function ring(offset = 0) {
  const corners = [[120 + offset, 15], [120.001 + offset, 15], [120.001 + offset, 15.001], [120 + offset, 15.001]];
  return corners.flatMap(([lng, lat], index) => {
    const next = corners[(index + 1) % 4];
    return Array.from({ length: 10 }, (_, i) => Object.freeze({ lng: lng + (next[0] - lng) * i / 10, lat: lat + (next[1] - lat) * i / 10 }));
  });
}

test('hover motion batches pointer events and measures the card only when its content changes', () => {
  const frames = [];
  let reads = 0;
  const card = {style: {}, classList: {contains: () => true, add() {}}, setAttribute() {},
    get offsetWidth() {reads++; return 250;}, get offsetHeight() {reads++; return 110;}};
  const context = vm.createContext({
    window: {requestAnimationFrame: callback => {frames.push(callback); return frames.length;}},
    parcelHoverCardEl: card, parcelHoverSize: null, parcelHoverPositionFrame: null, lastParcelPointer: null,
    stageEl: {getBoundingClientRect: () => ({left: 0, top: 0, width: 800, height: 600})},
    plotMode: false, parcelHoverHideTimer: null, farmersById: new Map(), dataById: new Map(),
    normalizePolygonRing: () => {throw new Error('Stored areas must not scan geometry on hover');},
    escapeHtml: String, formatName: () => 'Synthetic farmer', cropCaption: () => '',
  });
  vm.runInContext(['rememberParcelPointer', 'positionParcelHoverCard', 'showParcelHoverCard'].map(declaration).join('\n'), context);
  for (let i = 0; i < 100; i++) context.positionParcelHoverCard({clientX: i, clientY: i});
  assert.equal(frames.length, 1);
  frames.shift()();
  assert.equal(card.style.translate, '115px 115px');
  assert.equal(reads, 2);
  context.positionParcelHoverCard({clientX: 790, clientY: 590}); frames.shift()();
  assert.equal(card.style.translate, '524px 464px', 'Card flips inside the map near its edges');
  assert.equal(reads, 2);
  context.showParcelHoverCard(1, {id: 1, area_ha: 4.5}, {clientX: 10, clientY: 10}); frames.shift()();
  assert.match(card.innerHTML, /4.50 ha/);
  assert.equal(reads, 4, 'New content is measured again');
});

function harness(plots = []) {
  const metrics = { constructed: 0, frames: 0, mounts: 0, clears: 0 };
  const mounted = new Set();
  let clock = 0;
  const toggle = { checked: true };
  const context = vm.createContext({
    console, setTimeout, clearTimeout, Map, Set,
    performance: { now: () => (clock += 2) },
    document: { getElementById: () => toggle },
    window: { __allFarmPlotsUrl: '/farm-plots/all?municipality_id=12', ParcelDisplayGeometry: geometry,
      requestAnimationFrame: callback => setImmediate(() => { metrics.frames++; callback(); }) },
    map3d: { range: 16000, tilt: 22, center: { lat: 15, lng: 120 },
      append: object => { assert.ok(!object.isConnected); object.isConnected = true; mounted.add(object); metrics.mounts++; },
      removeChild: object => { object.isConnected = false; mounted.delete(object); } },
    Polygon3DInteractiveElement: class {
      constructor(options) {
        Object.assign(this, options);
        this.ready = true;
        this.isConnected = false;
        metrics.constructed++;
      }
      // Reproduce the Maps beta lifecycle failure observed in the browser.
      set path(value) { assert.ok(this.ready, 'Set interactive polygon paths after construction'); this.currentPath = value; }
      get path() { return this.currentPath; }
      addEventListener(name, callback) { (this.events ||= {})[name] = callback; }
    },
    // Any accidental restoration of the invisible line doubles the map workload.
    Polyline3DInteractiveElement: class { constructor() { throw new Error('Unexpected duplicate outline'); } },
    AltitudeMode: { CLAMP_TO_GROUND: 'ground' },
    savedPlotOverlays: [], renderedPlotDataByFarmerId: new Map(), plotsCacheByFarmerId: new Map(),
    savedPlotsHiddenForEditing: false, focusedParcelFarmerId: null, selectedFarmerId: null, editingPlotId: null,
    cropLayer: null, queueCropLayer: () => {}, plantedAreaLayer: null, refreshPlantedAreas: () => {},
    cropSettings: {enabled:false,year:2026,season:'dry',filter:'all'}, plantedAreaOverlays: [], schedulePlotDisplayRefresh: () => {},
    overviewCropDetailsRef: null, overviewCropDetails: new Map(),
    plotDisplayRevision: 0, plotDisplayTimer: null, mapGeocodedPillEl: { textContent: '' },
    normalizePolygonRing: points => points.slice(), getEffectivePlotColor: plot => plot.color || '#22c55e',
    hexAlpha: color => color, hexToRgba: color => color, bindClickablePlotOverlay: () => {},
    extendBounds: (bounds, points) => points.forEach(p => {
      bounds.minLat = Math.min(bounds.minLat, p.lat); bounds.maxLat = Math.max(bounds.maxLat, p.lat);
      bounds.minLng = Math.min(bounds.minLng, p.lng); bounds.maxLng = Math.max(bounds.maxLng, p.lng);
    }),
    setStatus: () => {}, setProgress: () => {}, toast: () => {},
    fetch: async () => ({ ok: true, json: async () => ({ plots, total: plots.length, returned: plots.length, truncated: false }) }),
  });
  const functions = ['toLatLng', 'setOverlayVisible', 'nextPlotFrame', 'shouldShowSavedPlot', 'needsFullPlotDetail',
    'refreshSavedPlotDisplay', 'clearPlotsForFarmer', 'renderPlotsForFarmer', 'loadAllMunicipalPlots', 'applyCropStyle', 'applyParcelCropBadge', 'useWhiteParcelBase'];
  vm.runInContext(functions.map(declaration).join('\n'), context);
  const clear = context.clearPlotsForFarmer;
  context.clearPlotsForFarmer = id => { metrics.clears++; clear(id); };
  context.window.__applyPlotVisibility = context.refreshSavedPlotDisplay;
  return { context, metrics, mounted, toggle };
}

test('crop styling reuses overlays and restores saved colors without changing geometry or edit visibility', async () => {
  const plot = { id: 1, farmer_id: 1, color: '#123456', polygon_json: ring() };
  const { context, metrics } = harness();
  const original = JSON.stringify(plot);
  context.renderPlotsForFarmer('1', [plot]);
  const overlay = context.savedPlotOverlays[0];
  context.cropLayer = { color: () => '#219653', visible: () => true };
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#219653');
  assert.equal(overlay.style.fillSoft, '#219653');
  assert.equal(metrics.constructed, 1);
  context.cropLayer.visible = () => false;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.isConnected, false);
  context.cropLayer = null;
  context.savedPlotsHiddenForEditing = true;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.isConnected, false);
  assert.equal(overlay.poly.fillColor, '#123456');
  assert.equal(JSON.stringify(plot), original);
});

test('Ramos parcel presentation uses a white base while saved crop colors and records remain intact', async () => {
  const plot = { id: 1, farmer_id: 1, color: '#123456', polygon_json: ring() };
  const { context } = harness();
  context.window.__whiteParcelBase = true;
  const original = JSON.stringify(plot);
  context.renderPlotsForFarmer('1', [plot]);
  const overlay = context.savedPlotOverlays[0];
  assert.equal(overlay.savedColor, '#123456', 'The stored plot color remains available for data and exports');
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.poly.strokeColor, '#FFFFFF');
  context.cropLayer = { color: () => '#219653', visible: () => true };
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.style.fillSoft, '#FFFFFF');
  assert.equal(JSON.stringify(plot), original, 'The imported parcel color remains unchanged');
});

test('all 1044 parcels load in batches with one overlay each and untouched full geometry', async () => {
  const plots = Array.from({ length: 1044 }, (_, i) => ({ id: i + 1, farmer_id: Math.floor(i / 2) + 1,
    color: '#008855', polygon_json: ring(), _record_version: 'version-' + i }));
  const originals = JSON.stringify(plots);
  const { context, metrics, mounted } = harness(plots);
  await context.loadAllMunicipalPlots();
  assert.equal(metrics.constructed, 1044);
  assert.equal(mounted.size, 1044);
  assert.equal(metrics.clears, 0, 'initial rendering must not scan/rebuild all prior farmers');
  assert.ok(metrics.frames > 1, 'the browser gets frames during the load');
  assert.equal(context.savedPlotOverlays.length, 1044);
  assert.equal(context.plotsCacheByFarmerId.get('1')[0], plots[0]);
  assert.equal(JSON.stringify(plots), originals);
  assert.equal(context.savedPlotOverlays[0].ring.length, 40);
  assert.equal(context.savedPlotOverlays[0].poly.path.length, 4);
});

test('reopening cached farmer data reuses overlays; refreshed data replaces it once', () => {
  const { context, metrics, mounted } = harness();
  const plots = [{ id: 1, farmer_id: 7, polygon_json: ring() }];
  context.renderPlotsForFarmer('7', plots);
  const first = context.savedPlotOverlays[0];
  context.renderPlotsForFarmer('7', plots);
  assert.equal(metrics.constructed, 1);
  assert.equal(context.savedPlotOverlays[0], first);
  context.renderPlotsForFarmer('7', [...plots]);
  assert.equal(metrics.constructed, 2);
  assert.equal(first.disposed, true);
  assert.equal(first.poly.isConnected, false);
  assert.equal(mounted.size, 1);
});

test('selection and close inspection restore exact paths; distant parcels stay lighter', async () => {
  const { context } = harness();
  context.renderPlotsForFarmer('7', [{ id: 1, polygon_json: ring() }]);
  context.renderPlotsForFarmer('8', [{ id: 2, polygon_json: ring(1) }]);
  context.selectedFarmerId = '8';
  await context.refreshSavedPlotDisplay();
  assert.equal(context.savedPlotOverlays[1].poly.path, context.savedPlotOverlays[1].ring);
  context.selectedFarmerId = null;
  context.map3d.range = 500;
  await context.refreshSavedPlotDisplay();
  assert.equal(context.savedPlotOverlays[0].poly.path, context.savedPlotOverlays[0].ring);
  assert.equal(context.savedPlotOverlays[1].poly.path, context.savedPlotOverlays[1].overviewRing);
});

test('focus, all-parcels, hide, edit and restore keep the correct visibility', async () => {
  const { context, mounted, toggle } = harness();
  context.renderPlotsForFarmer('7', [{ id: 1, polygon_json: ring() }]);
  context.renderPlotsForFarmer('8', [{ id: 2, polygon_json: ring() }]);
  context.focusedParcelFarmerId = '7';
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 1);
  context.focusedParcelFarmerId = null;
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 2);
  toggle.checked = false;
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 0);
  toggle.checked = true; context.savedPlotsHiddenForEditing = true;
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 0, 'camera changes must not unhide saved plots during editing');
  context.savedPlotsHiddenForEditing = false; context.editingPlotId = '2';
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 1);
  context.editingPlotId = null;
  await context.refreshSavedPlotDisplay();
  assert.equal(mounted.size, 2);
});

test('a stale frame cannot remount a cleared or newly hidden plot', async () => {
  const { context, mounted } = harness();
  for (let i = 0; i < 40; i++) context.renderPlotsForFarmer(String(i), [{ id: i, polygon_json: ring() }], { append: true });
  const firstRefresh = context.refreshSavedPlotDisplay();
  context.clearPlotsForFarmer('20');
  context.focusedParcelFarmerId = '1';
  const secondRefresh = context.refreshSavedPlotDisplay();
  await Promise.all([firstRefresh, secondRefresh]);
  assert.equal(mounted.size, 1);
  assert.equal(context.savedPlotOverlays.find(p => p.farmerId === '20'), undefined);
});

test('initial background batches cannot replace a newer selected-farmer response', () => {
  const { context, mounted } = harness();
  const old = [{ id: 1, polygon_json: ring() }];
  context.renderPlotsForFarmer('7', [{ id: 2, polygon_json: ring() }]);
  context.renderPlotsForFarmer('7', old, { append: true });
  assert.equal(context.savedPlotOverlays.length, 1);
  assert.equal(context.savedPlotOverlays[0].plotId, 2);
  context.renderPlotsForFarmer('7', []);
  context.renderPlotsForFarmer('7', old, { append: true });
  assert.equal(mounted.size, 0, 'a newly deleted plot stays deleted');
});

test('parcel crop icons appear on initial overview without selection and reuse markers on camera changes', async () => {
  const {context,toggle}=harness();
  context.window.__whiteParcelBase=true;
  const helper=require('../../public/js/crop-area-badges.js');
  let markers=0,anchors=0;
  context.window.CropAreaBadges={...helper,parcelPosition:points=>{anchors++;return helper.parcelPosition(points);},parcelMarkerContent:(_,codes)=>codes.slice()};
  context.Marker3DElement=class{constructor(options){Object.assign(this,options);markers++;}replaceChildren(value){this.content=value;}};
  context.google={maps:{CollisionBehavior:{OPTIONAL_AND_HIDES_LOWER_PRIORITY:'optional',REQUIRED:'required'}}};
  const rows=new Map([['1',{crop:'rice',area_crops:[]}],['2',{crop:'mixed',area_crops:['corn','rice']}],['3',{crop:'not_recorded',area_crops:[]}]]);
  context.cropLayer={record:id=>rows.get(String(id)),color:(_,saved)=>saved,visible:()=>true};
  for(let id=1;id<=3;id++)context.renderPlotsForFarmer(String(id),[{id,farmer_id:id,polygon_json:ring()}]);
  const [one,two,empty]=context.savedPlotOverlays;
  assert.equal(context.selectedFarmerId,null);
  assert.equal(context.savedPlotOverlays[0].poly.fillColor,'#FFFFFF','Ramos parcels keep a neutral base beneath crop icons');
  assert.equal(one.cropBadge.isConnected,true);assert.equal(two.cropBadge.isConnected,true);
  assert.equal(one.cropBadge.drawsWhenOccluded,true,'Whole-map crop icons remain visible over terrain');
  assert.equal(one.cropBadge.collisionBehavior,'required','Whole-map crop icons are not suppressed by map labels');
  assert.deepEqual(Array.from(two.cropBadge.content),['corn','rice']);
  assert.equal(empty.cropBadge,null,'No crop icon without a saved seasonal record');
  assert.match(one.cropBadge.title,/Rice.*2026 Dry season/);
  const first=one.cropBadge;
  await context.refreshSavedPlotDisplay();await context.refreshSavedPlotDisplay();
  assert.equal(markers,2);assert.equal(anchors,2);assert.equal(one.cropBadge,first);
  toggle.checked=false;await context.refreshSavedPlotDisplay();assert.equal(first.isConnected,false);
  toggle.checked=true;context.focusedParcelFarmerId='2';await context.refreshSavedPlotDisplay();
  assert.equal(first.isConnected,false);assert.equal(two.cropBadge.isConnected,true);
  context.focusedParcelFarmerId=null;context.savedPlotsHiddenForEditing=true;await context.refreshSavedPlotDisplay();
  assert.equal(two.cropBadge.isConnected,false);
  context.savedPlotsHiddenForEditing=false;await context.refreshSavedPlotDisplay();assert.equal(first.isConnected,true);
  context.clearPlotsForFarmer('1');assert.equal(first.isConnected,false,'Removing a parcel must remove its badge');
  rows.clear();await context.refreshSavedPlotDisplay();assert.equal(two.cropBadge,null,'Loading failures/new periods cannot retain stale icons');
});

test('selected details replace matching overview icons and reset restores the overview', async () => {
  const {context}=harness();
  context.window.CropAreaBadges={...require('../../public/js/crop-area-badges.js'),parcelMarkerContent:(_,codes)=>codes.slice()};
  context.Marker3DElement=class{constructor(options){Object.assign(this,options);}replaceChildren(value){this.content=value;}};
  context.google={maps:{CollisionBehavior:{OPTIONAL_AND_HIDES_LOWER_PRIORITY:'optional'}}};
  context.cropLayer={record:()=>({crop:'mixed',area_crops:['rice','corn']}),color:(_,saved)=>saved,visible:()=>true};
  context.renderPlotsForFarmer('1',[{id:1,polygon_json:ring()}]);
  const overlay=context.savedPlotOverlays[0];
  context.selectedFarmerId='1';context.map3d.range=2000;
  context.plantedAreaOverlays=[{plotId:1,crop:'rice',badge:{}}];
  await context.refreshSavedPlotDisplay();
  assert.deepEqual(Array.from(overlay.cropBadge.content),['corn'],'Other crop icons remain until their detail arrives');
  context.plantedAreaOverlays=context.plantedAreaOverlays.concat({plotId:1,crop:'corn',badge:{}});
  await context.refreshSavedPlotDisplay();assert.equal(overlay.cropBadge,null);
  context.selectedFarmerId=null;await context.refreshSavedPlotDisplay();
  assert.deepEqual(Array.from(overlay.cropBadge.content),['rice','corn']);assert.equal(overlay.cropBadge.isConnected,true);
  context.cropSettings.enabled=true;context.cropSettings.filter='corn';await context.refreshSavedPlotDisplay();
  assert.deepEqual(Array.from(overlay.cropBadge.content),['corn']);
});

test('overview classification loads once after the initial parcel batch without a click', async () => {
  const plots=Array.from({length:42},(_,index)=>({id:index+1,farmer_id:index+1,polygon_json:ring()}));
  const {context}=harness(plots);let queued=0,request;
  context.queueCropLayer=()=>{queued++;};
  await context.loadAllMunicipalPlots();assert.equal(queued,1,'Initial rendering must not reload crop metadata per farmer');
  context.cropSettings={enabled:false,icons:true,year:2026,season:'dry',filter:'all'};
  context.cropLayer={load:(settings,ids,force)=>{request={settings,ids,force};},color:(_,saved)=>saved,visible:()=>true,record:()=>null};
  vm.runInContext(declaration('loadCropLayer'),context);context.loadCropLayer(false);
  assert.equal(request.settings.icons,true);assert.equal(request.settings.enabled,false);
  assert.equal(request.ids.length,42);assert.equal(context.selectedFarmerId,null);
});


test('planted section overlays reuse GPU objects, follow visibility and respect reduced motion', () => {
  const {context,metrics,toggle} = harness();
  context.window.__whiteParcelBase=true;
  const status={textContent:''};
  context.document.getElementById=id=>id==='togglePlots'?toggle:id==='plantedAreaLayerStatus'?status:null;
  context.window.matchMedia=()=>({matches:true});
  let badgeCount=0;
  context.Marker3DElement=class {constructor(options){Object.assign(this,options);badgeCount++;}replaceChildren(value){this.content=value;}};
  context.google={maps:{CollisionBehavior:{OPTIONAL_AND_HIDES_LOWER_PRIORITY:'optional',REQUIRED:'required'}}};
  context.window.CropAreaBadges={position:require('../../public/js/crop-area-badges.js').position,template:(_,crop)=>crop};
  context.map3d.range=3000;
  context.selectedFarmerId=1;
  context.plantedAreaOverlays=[];context.plantedAreaKey='';context.plantedAreaRecords=null;
  context.cropSettings={enabled:true,year:2026,season:'dry',filter:'all'};context.plotMode=false;
  vm.runInContext(declaration('renderPlantedAreas'),context);
  const parcel={id:1,farmer_id:1,color:'#123456',polygon_json:ring()};
  context.renderPlotsForFarmer('1',[parcel]);
  const state={status:'ready',partial:false,records:[{plot_id:1,areas:[{crop:'corn',crop_label:'Corn',name:'Section A',variety:'Recorded variety',color:'#D4A017',area_ha:.4,polygon:ring().filter((_,index)=>index%10===0)}]}]};
  context.renderPlantedAreas(state);
  const polygon=context.plantedAreaOverlays[0].poly;
  const badge=context.plantedAreaOverlays[0].badge;
  assert.equal(context.savedPlotOverlays[0].poly.fillColor,'#FFFFFF');
  assert.equal(polygon.fillColor,'#D4A017','Plotted crop shading keeps its crop color above the white parent');
  context.renderPlantedAreas(state);
  assert.equal(metrics.constructed,2,'One parent and one section; camera refresh must not recreate geometry');
  assert.equal(polygon.isConnected,true);
  assert.equal(badge.isConnected,true);assert.equal(badge.content,'corn');assert.equal(badgeCount,1,'Camera refresh reuses crop badges');
  assert.equal(badge.drawsWhenOccluded,true,'Selected crop icon stays visible over terrain');
  assert.equal(badge.collisionBehavior,'required','Selected crop icon cannot be suppressed by another label');
  toggle.checked=false;context.renderPlantedAreas(state);assert.equal(polygon.isConnected,false);
  assert.equal(badge.isConnected,false);
  toggle.checked=true;context.cropSettings.filter='rice';context.renderPlantedAreas(state);assert.equal(polygon.isConnected,false);
  context.cropSettings.filter='corn';context.renderPlantedAreas(state);assert.equal(polygon.isConnected,true);
  assert.equal(badge.isConnected,true);
  context.cropSettings.enabled=false;context.cropSettings.filter='rice';context.renderPlantedAreas(state);assert.equal(badge.isConnected,true,'Saved icons remain visible when optional parcel recoloring is off');
  context.selectedFarmerId=null;context.renderPlantedAreas(state);assert.equal(badge.isConnected,false,'Clearing farmer selection hides cached icons immediately');
  context.selectedFarmerId=1;context.renderPlantedAreas(state);assert.equal(badge.isConnected,true);
  context.map3d.range=10000;context.renderPlantedAreas(state);assert.equal(badge.isConnected,false,'Zoomed-out labels stay uncluttered');
  polygon.events['gmp-click']({stopPropagation(){}});
  assert.match(status.textContent,/Corn.*Recorded variety.*0\.4000/);
  assert.equal(polygon.strokeWidth,2,'Reduced motion keeps a steady boundary');
  context.renderPlantedAreas({status:'off',records:[]});assert.equal(polygon.isConnected,false);
  assert.equal(badge.isConnected,false);
  context.map3d.range=3000;
  context.window.CropAreaBadges=require('../../public/js/crop-area-badges.js');
  context.window.CropAreaBadges={...context.window.CropAreaBadges,markerContent:(_,crop)=>crop};
  const path=[{lat:15.0002,lng:120.0002},{lat:15.0002,lng:120.0008},{lat:15.0008,lng:120.0008},{lat:15.0008,lng:120.0002}];
  const marked={type:'line',path,crop:'rice',crop_label:'Rice',color:'#219653'};
  const before=JSON.stringify(marked);
  context.renderPlantedAreas({status:'ready',records:[{plot_id:1,areas:[marked]}]});
  const shaded=context.plantedAreaOverlays[0];
  assert.equal(shaded.poly.strokeWidth,2,'A simple enclosed line is displayed as a shaded section');
  assert.equal(shaded.poly.fillColor,context.hexToRgba('#219653',.38));
  assert.equal(shaded.badge.content,'rice');assert.equal(shaded.badge.isConnected,true);
  assert.equal(JSON.stringify(marked),before,'Shading cannot change stored line data or add hectares');
  assert.equal(shaded.badge.position.lat>15.0002 && shaded.badge.position.lat<15.0008,true);
  context.Polyline3DInteractiveElement=context.Polygon3DInteractiveElement;
  context.renderPlantedAreas({status:'ready',records:[{plot_id:1,areas:[marked,{...marked,crop:'corn'}]}]});
  assert.equal(context.plantedAreaOverlays.length,2);
  for(const item of context.plantedAreaOverlays){
    assert.equal(item.poly.fillColor,undefined,'Existing conflicting line enclosures have outlines only');
    assert.equal(item.badge.isConnected,true,'Keep crop icons for records needing correction');
  }
  assert.match(status.textContent,/sections overlap/);
});

test('saved crop areas load automatically for the selected farmer with recoloring off', () => {
  const {context}=harness();let request;
  context.selectedFarmerId=1;context.focusedCropPlotId=null;
  context.savedPlotOverlays=[{farmerId:1,plotId:10},{farmerId:2,plotId:20}];
  context.cropSettings={enabled:false,year:2025,season:'wet',filter:'all'};
  context.plantedAreaLayer={load:(settings,ids,force)=>{request={settings,ids,force};}};
  vm.runInContext(declaration('refreshPlantedAreas'),context);
  context.refreshPlantedAreas(false);
  assert.equal(request.settings.enabled,true);assert.equal(request.settings.year,2025);assert.equal(request.settings.season,'wet');
  assert.deepEqual(Array.from(request.ids),[10]);
  context.selectedFarmerId=null;context.refreshPlantedAreas(false);assert.deepEqual(Array.from(request.ids),[],'No global crop geometry is requested');
});

test('parcel clicks show the first dot, connect each next corner and continue past four points', () => {
  const {context} = harness();
  class DraftElement {
    constructor(options) {Object.assign(this,options);}
    addEventListener() {}
    replaceChildren(content) {this.content=content;}
  }
  Object.assign(context,{
    Polyline3DInteractiveElement:DraftElement,Polygon3DElement:DraftElement,Marker3DElement:DraftElement,
    plotMode:true,editingPlotId:null,selectedVertexIndex:-1,plotVertices:[],draftDots:[],draftEdgeHandles:[],draftLine:null,draftPoly:null,
    canUseDraftVertices:()=>true,getPlotColor:()=>'#2864B4',updateDeleteCornerButtonState:()=>{},estimateAreaHa:()=>0,
    midpointLatLng:()=>({lat:15,lng:120}),
  });
  context.window.MapDrawingGuide={dotTemplate:(_,index,selected)=>({number:index+1,selected})};
  vm.runInContext(['clearDraftDots','renderDraftEdgeHandles','updateDraftStats','refreshDraftDots','addDraftVertex','handleParcelMapClick'].map(declaration).join('\n'),context);
  const points=[{lat:15,lng:120},{lat:15,lng:120.1},{lat:15.1,lng:120.1},{lat:15.2,lng:120.05},{lat:15.1,lng:120}];
  context.handleParcelMapClick({position:points[0]});assert.equal(context.draftDots.length,1);assert.equal(context.draftDots[0].content.number,1);assert.equal(context.draftLine,null);
  context.handleParcelMapClick({position:points[1]});assert.equal(context.draftDots.length,2);assert.equal(context.draftLine.path.length,2);
  points.slice(2).forEach(position=>context.handleParcelMapClick({position}));assert.equal(context.plotVertices.length,5);assert.equal(context.draftDots[4].content.number,5);
  context.editingPlotId=1;context.handleParcelMapClick({position:{lat:15.3,lng:120}});assert.equal(context.plotVertices.length,5,'Existing parcel edits retain edge insertion instead of appending stray corners');
  context.clearDraftDots();assert.equal(context.draftDots.length,0);assert.equal(context.draftLine,null);
});
