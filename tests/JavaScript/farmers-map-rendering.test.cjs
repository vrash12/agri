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
  const metrics = { constructed: 0, selectionConstructed: 0, frames: 0, mounts: 0, clears: 0 };
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
        if (options.outerWidth != null) assert.ok(options.outerWidth >= 0 && options.outerWidth <= 1,
          'Maps 3D outerWidth is a stroke proportion between 0 and 1, not pixels');
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
    Polyline3DElement: class {
      constructor(options) {
        assert.ok(options.outerWidth >= 0 && options.outerWidth <= 1, 'Selection halo uses a valid outer stroke ratio');
        Object.assign(this, options);
        metrics.selectionConstructed++;
      }
    },
    AltitudeMode: { CLAMP_TO_GROUND: 'ground' },
    savedPlotOverlays: [], renderedPlotDataByFarmerId: new Map(), plotsCacheByFarmerId: new Map(),
    completePlotFarmers: new Set(), plotFetchPromisesByFarmerId: new Map(),
    parcelStatsState: {farmerId:null,landStatus:'empty',plots:[]}, parcelStatsRevision: 0,
    savedPlotsHiddenForEditing: false, focusedParcelFarmerId: null, selectedFarmerId: null, editingPlotId: null,
    selectedPlotId: null, selectedPlotOverlay: null, selectedPlotHalo: null, parcelOpenRevision: 0, plotMode: false,
    cropLayer: null, queueCropLayer: () => {}, plantedAreaLayer: null, refreshPlantedAreas: () => {}, refreshPlotListMetadata: () => {},
    cropSettings: {enabled:false,year:2026,season:'dry',filter:'all'}, plantedAreaOverlays: [], schedulePlotDisplayRefresh: () => {},
    plantedAreaRenderedRecords: new Map(), plantedAreaRenderRevision: 0, plantedAreaRetryTimer: null, plantedAreaRefreshPending: false,
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
    'refreshSavedPlotDisplay', 'clearPlotsForFarmer', 'renderPlotsForFarmer', 'loadAllMunicipalPlots', 'applyCropStyle', 'applyParcelCropBadge', 'buildPlantedCropOverlays', 'showPlantedCropOverlay',
    'sameLatLng', 'applySavedPlotAppearance', 'selectSavedPlot', 'syncSavedPlotSelection', 'refreshSelectedParcelStats'];
  vm.runInContext(functions.map(declaration).join('\n'), context);
  const clear = context.clearPlotsForFarmer;
  context.clearPlotsForFarmer = id => { metrics.clears++; clear(id); };
  context.window.__applyPlotVisibility = context.refreshSavedPlotDisplay;
  return { context, metrics, mounted, toggle };
}

test('white parcel styling reuses overlays without changing saved colors, geometry or edit visibility', async () => {
  const plot = { id: 1, farmer_id: 1, color: '#123456', polygon_json: ring() };
  const { context, metrics } = harness();
  const original = JSON.stringify(plot);
  context.renderPlotsForFarmer('1', [plot]);
  const overlay = context.savedPlotOverlays[0];
  context.cropLayer = { color: () => '#219653', visible: () => true };
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.style.fillSoft, '#FFFFFF');
  assert.equal(metrics.constructed, 1);
  context.cropLayer.visible = () => false;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.isConnected, false);
  context.cropLayer = null;
  context.savedPlotsHiddenForEditing = true;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.isConnected, false);
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.savedColor, '#123456');
  assert.equal(JSON.stringify(plot), original);
});

test('parcel selection uses one red boundary and dark halo while keeping crop fills and saved geometry', async () => {
  const {context, metrics} = harness();
  const plots = [{id:1,farmer_id:1,color:'#123456',polygon_json:ring()}, {id:2,farmer_id:1,color:'#789ABC',polygon_json:ring(.01)}];
  const original = JSON.stringify(plots);
  context.renderPlotsForFarmer('1',plots);
  context.cropLayer = {record:()=>({crop:'corn',color:'#D4A017',has_planted_areas:false}),visible:()=>true};
  await context.refreshSavedPlotDisplay();
  const [first, second] = context.savedPlotOverlays;
  context.selectSavedPlot(1);
  const halo = context.selectedPlotHalo;
  assert.equal(first.poly.strokeColor,'#EF4444');
  assert.equal(first.poly.fillColor,'#D4A017','Selection cannot replace the crop tint');
  assert.equal(second.poly.strokeColor,'#FFFFFF');
  assert.equal(halo.isConnected,true);
  assert.equal(halo.outerColor,'#0F172A66');
  assert.equal(halo.strokeColor,'#EF4444');
  assert.equal(halo.path.length,first.ring.length+1,'An open canonical ring gets a display-only closing segment');
  assert.deepEqual(halo.path[0],halo.path.at(-1));
  context.map3d.range=300;
  context.cropLayer.record=()=>({crop:'rice',color:'#219653',has_planted_areas:false});
  await context.refreshSavedPlotDisplay();
  assert.equal(first.poly.strokeColor,'#EF4444','Camera and crop updates preserve selection');
  assert.equal(first.poly.fillColor,'#219653');
  context.selectSavedPlot(2);
  assert.equal(first.poly.strokeColor,'#FFFFFF');
  assert.equal(second.poly.strokeColor,'#EF4444');
  assert.equal(context.selectedPlotHalo,halo);
  assert.equal(halo.path[0],second.ring[0]);
  assert.equal(metrics.constructed,2,'Selection reuses the parcel polygons');
  assert.equal(metrics.selectionConstructed,1,'Only the selected parcel has an extra GPU object');
  context.selectSavedPlot(null);
  assert.equal(second.poly.strokeColor,'#FFFFFF');
  assert.equal(halo.isConnected,false);
  assert.equal(JSON.stringify(plots),original,'Selection cannot change stored colors or ring coordinates');
});

test('selection follows visibility and refreshed parcel geometry, and clears after deletion', async () => {
  const {context, toggle, metrics} = harness();
  const plot={id:1,farmer_id:1,polygon_json:ring()};
  context.renderPlotsForFarmer('1',[plot]);
  context.selectSavedPlot(1);
  const halo=context.selectedPlotHalo;
  const hideAndRestore=async (hide,restore)=>{
    hide(); await context.refreshSavedPlotDisplay(); assert.equal(halo.isConnected,false);
    restore(); await context.refreshSavedPlotDisplay(); assert.equal(halo.isConnected,true);
  };
  await hideAndRestore(()=>toggle.checked=false,()=>toggle.checked=true);
  await hideAndRestore(()=>context.cropLayer={visible:()=>false},()=>context.cropLayer=null);
  await hideAndRestore(()=>context.focusedParcelFarmerId='2',()=>context.focusedParcelFarmerId=null);
  await hideAndRestore(()=>context.savedPlotsHiddenForEditing=true,()=>context.savedPlotsHiddenForEditing=false);
  await hideAndRestore(()=>context.plotMode=true,()=>context.plotMode=false);
  await hideAndRestore(()=>context.editingPlotId='1',()=>context.editingPlotId=null);
  context.renderPlotsForFarmer('1',[{...plot,polygon_json:ring(.02)}]);
  assert.equal(context.selectedPlotId,'1');
  assert.equal(context.selectedPlotOverlay,context.savedPlotOverlays[0]);
  assert.equal(halo.path[0].lng,120.02,'A saved boundary change updates the shared halo');
  assert.equal(metrics.selectionConstructed,1);
  context.renderPlotsForFarmer('1',[]);
  assert.equal(context.selectedPlotId,null);
  assert.equal(context.selectedPlotOverlay,null);
  assert.equal(halo.isConnected,false);
});

test('hover remains white and native duplicate clicks select the exact parcel only once', () => {
  const {context, metrics} = harness();
  const openings=[];
  Object.assign(context,{
    positionParcelHoverCard(){},showParcelHoverCard(){},hideParcelHoverCard(){},setPlotHoverCursor(){},
    farmersById:new Map(),formatName:()=> 'Synthetic owner',
  });
  context.window.__openFarmer3d=(id,opts)=>openings.push({id,opts});
  vm.runInContext(declaration('bindClickablePlotOverlay'),context);
  context.renderPlotsForFarmer('1',[{id:1,polygon_json:ring()},{id:2,polygon_json:ring(.01)}]);
  const [first,second]=context.savedPlotOverlays;
  first.poly.events.mouseenter({});
  assert.equal(first.poly.strokeColor,'#FFFFFF');
  assert.equal(context.selectedPlotId,null);
  assert.equal(metrics.selectionConstructed,0,'Hover never creates a selection halo');
  first.poly.events['gmp-click']({stopPropagation(){}});
  first.poly.events.click({stopPropagation(){}});
  assert.equal(openings.length,1);
  assert.equal(openings[0].opts.plotId,1);
  assert.equal(openings[0].opts.focusParcels,true);
  first.poly.events.mouseleave();
  assert.equal(first.poly.strokeColor,'#EF4444','Leaving the selected parcel retains its red outline');
  second.poly.events.mouseenter({});
  assert.equal(second.poly.strokeColor,'#FFFFFF','Hovering another parcel cannot look selected');
  second.poly.events['gmp-click']({stopPropagation(){}});
  assert.equal(first.poly.strokeColor,'#FFFFFF');
  assert.equal(second.poly.strokeColor,'#EF4444');
  assert.equal(metrics.selectionConstructed,1);
});

test('selection still works when the optional non-interactive halo API is unavailable',()=>{
  const {context}=harness();
  context.Polyline3DElement=null;
  context.renderPlotsForFarmer('1',[{id:1,polygon_json:ring()}]);
  context.selectSavedPlot(1);
  assert.equal(context.savedPlotOverlays[0].poly.strokeColor,'#EF4444');
  assert.equal(context.selectedPlotHalo,null);
});

test('late farmer responses and background clicks cannot restore an older parcel selection',async()=>{
  const {context}=harness();
  const pending=new Map();
  const plots=new Map([
    ['1',[{id:1,polygon_json:ring()}]],
    ['2',[{id:2,polygon_json:ring(.01)}]],
  ]);
  for(const [id,rows] of plots)context.renderPlotsForFarmer(id,rows);
  Object.assign(context,{
    ensureFarmerData:id=>new Promise(resolve=>pending.set(id,resolve)),
    loadPlotsForSelectedFarmer:id=>Promise.resolve(plots.get(id)),
    highlightRow(){},syncSelectedPanel(){},syncParcelFocusUi(){},zoomToPlots(){},syncSuggestedPlotName(){},
    hintEl:null,popover:{open:false},selectedVertexIndex:-1,formatName:()=> 'Synthetic owner',
  });
  const start=source.indexOf('window.__openFarmer3d = function');
  const end=source.indexOf('\n};',start)+4;
  vm.runInContext(source.slice(start,end)+'\n'+declaration('handleParcelMapClick'),context);
  context.selectSavedPlot(1);
  const first=context.window.__openFarmer3d('1',{focusParcels:true,plotId:1});
  context.selectSavedPlot(2);
  const second=context.window.__openFarmer3d('2',{focusParcels:true,plotId:2});
  pending.get('2')({id:2}); await second;
  pending.get('1')({id:1}); await first;
  assert.equal(context.selectedFarmerId,'2');
  assert.equal(context.selectedPlotId,'2');
  assert.equal(context.savedPlotOverlays[0].poly.strokeColor,'#FFFFFF');
  assert.equal(context.savedPlotOverlays[1].poly.strokeColor,'#EF4444');
  const reopening=context.window.__openFarmer3d('1',{focusParcels:true,plotId:1});
  context.handleParcelMapClick({});
  pending.get('1')({id:1}); await reopening;
  assert.equal(context.selectedPlotId,null,'A blank-map click clears the selection even during a pending owner load');
  assert.equal(context.selectedFarmerId,'2','The old request cannot reopen its owner after clearing');
  assert.equal(context.selectedPlotHalo.isConnected,false);
  const directory=context.window.__openFarmer3d('1',{});
  pending.get('1')({id:1}); await directory;
  assert.equal(context.selectedPlotId,null,'Selecting a farmer from the directory does not arbitrarily mark one parcel');
});

test('whole-parcel crop classifications tint immediately while keeping white outlines and saved data', async () => {
  const plot = { id: 1, farmer_id: 1, color: '#123456', polygon_json: ring() };
  const { context } = harness();
  const original = JSON.stringify(plot);
  context.renderPlotsForFarmer('1', [plot]);
  const overlay = context.savedPlotOverlays[0];
  assert.equal(overlay.savedColor, '#123456', 'The stored plot color remains available for data and exports');
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.poly.strokeColor, '#FFFFFF');
  context.cropLayer = { record: () => null, color: () => {throw new Error('Classification colors cannot recolor parcel bases');}, visible: () => true };
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#FFFFFF');
  assert.equal(overlay.style.fillSoft, '#FFFFFF');
  context.cropLayer.record = () => ({crop:'corn',color:'#D4A017',has_planted_areas:false});
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#D4A017', 'The default map shows the saved whole-parcel crop');
  context.cropSettings.enabled=true;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#D4A017','Filtering keeps the crop tint');
  assert.equal(overlay.poly.strokeColor, '#FFFFFF');
  assert.equal(overlay.style.strokeHover, '#FFFFFF','Hover cannot restore imported or crop classification colors');
  assert.equal(overlay.style.fillHover, '#D4A017');
  context.cropLayer.record = () => ({crop:'rice',color:'#219653',has_planted_areas:false});
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#219653', 'A saved crop change replaces the previous tint');
  context.cropLayer.record = () => null;
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#FFFFFF', 'Changing period or losing metadata removes the previous crop tint');
  assert.equal(JSON.stringify(plot), original, 'The imported parcel color remains unchanged');
});

test('missing crops and unavailable or unclosed crop sections never imply whole-parcel coverage', async () => {
  const {context} = harness();
  context.renderPlotsForFarmer('1', [{id:1,farmer_id:1,polygon_json:ring()}]);
  const overlay = context.savedPlotOverlays[0];
  const rows = [null,
    {crop:'not_recorded',color:'#8A9299',has_planted_areas:false},
    {crop:'corn',color:'#D4A017',has_planted_areas:true},
    {crop:'corn',color:'#D4A017'},
  ];
  for (const row of rows) {
    context.cropLayer = {record:()=>row,visible:()=>true};
    await context.refreshSavedPlotDisplay();
    assert.equal(overlay.poly.fillColor, '#FFFFFF');
  }
  context.cropLayer.record = () => ({crop:'corn',color:'#D4A017',has_planted_areas:false});
  context.plantedAreaRenderedRecords.set('1',{record:{areas:[],needs_review:true}});
  await context.refreshSavedPlotDisplay();
  assert.equal(overlay.poly.fillColor, '#FFFFFF','Sections awaiting review must not become a whole-parcel fill');
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
  const helper=require('../../public/js/crop-area-badges.js');
  let markers=0,anchors=0,modes=[];
  context.window.CropAreaBadges={...helper,parcelPosition:points=>{anchors++;return helper.parcelPosition(points);},parcelMarkerContent:(_,codes,options)=>{modes.push(!!options?.compact);return codes.slice();}};
  context.Marker3DElement=class{constructor(options){Object.assign(this,options);markers++;}replaceChildren(value){this.content=value;}};
  context.google={maps:{CollisionBehavior:{OPTIONAL_AND_HIDES_LOWER_PRIORITY:'optional',REQUIRED:'required'}}};
  const rows=new Map([['1',{crop:'rice',area_crops:[]}],['2',{crop:'mixed',area_crops:['corn','rice']}],['3',{crop:'not_recorded',area_crops:[]}]]);
  context.cropLayer={record:id=>rows.get(String(id)),color:(_,saved)=>saved,visible:()=>true};
  for(let id=1;id<=3;id++)context.renderPlotsForFarmer(String(id),[{id,farmer_id:id,polygon_json:ring()}]);
  const [one,two,empty]=context.savedPlotOverlays;
  assert.equal(context.selectedFarmerId,null);
  assert.equal(context.savedPlotOverlays[0].poly.fillColor,'#FFFFFF','All parcels keep a white base beneath crop icons');
  assert.equal(one.cropBadge.isConnected,true);assert.equal(two.cropBadge.isConnected,true);
  assert.equal(one.cropBadge.drawsWhenOccluded,true,'Whole-map crop icons remain visible over terrain');
  assert.equal(one.cropBadge.collisionBehavior,'required','Whole-map crop icons are not suppressed by map labels');
  assert.equal(modes[0],true,'Distant grouped badges use the compact icon treatment');
  assert.deepEqual(Array.from(two.cropBadge.content),['corn','rice']);
  assert.equal(empty.cropBadge,null,'No crop icon without a saved seasonal record');
  assert.match(one.cropBadge.title,/Rice.*2026 Dry season/);
  const first=one.cropBadge;
  await context.refreshSavedPlotDisplay();await context.refreshSavedPlotDisplay();
  assert.equal(markers,2);assert.equal(anchors,2);assert.equal(one.cropBadge,first);
  context.map3d.range=2500;await context.refreshSavedPlotDisplay();
  assert.equal(modes.at(-1),false,'Close parcel view restores the full crop badge');
  toggle.checked=false;await context.refreshSavedPlotDisplay();assert.equal(first.isConnected,false);
  toggle.checked=true;context.focusedParcelFarmerId='2';await context.refreshSavedPlotDisplay();
  assert.equal(first.isConnected,false);assert.equal(two.cropBadge.isConnected,true);
  context.focusedParcelFarmerId=null;context.savedPlotsHiddenForEditing=true;await context.refreshSavedPlotDisplay();
  assert.equal(two.cropBadge.isConnected,false);
  context.savedPlotsHiddenForEditing=false;await context.refreshSavedPlotDisplay();assert.equal(one.cropBadge.isConnected,true);
  context.clearPlotsForFarmer('1');assert.equal(first.isConnected,false,'Removing a parcel must remove its badge');
  rows.clear();await context.refreshSavedPlotDisplay();assert.equal(two.cropBadge,null,'Loading failures/new periods cannot retain stale icons');
});

test('whole-map details replace matching overview icons and zooming out restores grouped icons', async () => {
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
  assert.equal(overlay.cropBadge,null,'Clearing a farmer selection keeps the crop detail icons');
  context.map3d.range=10000;await context.refreshSavedPlotDisplay();
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


test('planted section overlays reuse GPU objects, follow visibility and respect reduced motion', async () => {
  const {context,metrics,toggle} = harness();
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
  await context.renderPlantedAreas(state);
  const polygon=context.plantedAreaOverlays[0].poly;
  const badge=context.plantedAreaOverlays[0].badge;
  assert.equal(context.savedPlotOverlays[0].poly.fillColor,'#FFFFFF');
  assert.equal(polygon.fillColor,'#D4A017','Plotted crop shading keeps its crop color above the white parent');
  await context.renderPlantedAreas(state);
  assert.equal(metrics.constructed,2,'One parent and one section; camera refresh must not recreate geometry');
  assert.equal(polygon.isConnected,true);
  assert.equal(badge.isConnected,true);assert.equal(badge.content,'corn');assert.equal(badgeCount,1,'Camera refresh reuses crop badges');
  assert.equal(badge.drawsWhenOccluded,true,'Selected crop icon stays visible over terrain');
  assert.equal(badge.collisionBehavior,'required','Selected crop icon cannot be suppressed by another label');
  toggle.checked=false;await context.renderPlantedAreas(state);assert.equal(polygon.isConnected,false);
  assert.equal(badge.isConnected,false);
  toggle.checked=true;context.cropSettings.filter='rice';await context.renderPlantedAreas(state);assert.equal(polygon.isConnected,false);
  context.cropSettings.filter='corn';await context.renderPlantedAreas(state);assert.equal(polygon.isConnected,true);
  assert.equal(badge.isConnected,true);
  context.cropSettings.enabled=false;context.cropSettings.filter='rice';await context.renderPlantedAreas(state);assert.equal(badge.isConnected,true,'Saved icons remain visible when optional parcel recoloring is off');
  context.selectedFarmerId=null;await context.renderPlantedAreas(state);assert.equal(badge.isConnected,true,'Clearing farmer selection keeps all saved crop drawings visible');
  context.selectedFarmerId=1;await context.renderPlantedAreas(state);assert.equal(badge.isConnected,true);
  context.map3d.range=10000;await context.renderPlantedAreas(state);assert.equal(badge.isConnected,false,'Zoomed-out labels stay uncluttered');
  polygon.events['gmp-click']({stopPropagation(){}});
  assert.match(status.textContent,/Corn.*Recorded variety.*0\.4000/);
  assert.equal(polygon.strokeWidth,2,'Reduced motion keeps a steady boundary');
  await context.renderPlantedAreas({status:'off',records:[]});assert.equal(polygon.isConnected,false);
  assert.equal(badge.isConnected,false);
  context.map3d.range=3000;
  context.window.CropAreaBadges=require('../../public/js/crop-area-badges.js');
  context.window.CropAreaBadges={...context.window.CropAreaBadges,markerContent:(_,crop)=>crop};
  const path=[{lat:15.0002,lng:120.0002},{lat:15.0002,lng:120.0008},{lat:15.0008,lng:120.0008},{lat:15.0008,lng:120.0002}];
  const marked={type:'line',path,crop:'rice',crop_label:'Rice',color:'#219653'};
  const before=JSON.stringify(marked);
  await context.renderPlantedAreas({status:'ready',records:[{plot_id:1,areas:[marked]}]});
  const shaded=context.plantedAreaOverlays[0];
  assert.equal(shaded.poly.strokeWidth,2,'A simple enclosed line is displayed as a shaded section');
  assert.equal(shaded.poly.fillColor,context.hexToRgba('#219653',.38));
  assert.equal(shaded.badge.content,'rice');assert.equal(shaded.badge.isConnected,true);
  assert.equal(JSON.stringify(marked),before,'Shading cannot change stored line data or add hectares');
  assert.equal(shaded.badge.position.lat>15.0002 && shaded.badge.position.lat<15.0008,true);
  context.Polyline3DInteractiveElement=context.Polygon3DInteractiveElement;
  await context.renderPlantedAreas({status:'ready',records:[{plot_id:1,areas:[marked,{...marked,crop:'corn'}]}]});
  assert.equal(context.plantedAreaOverlays.length,2);
  for(const item of context.plantedAreaOverlays){
    assert.equal(item.poly.fillColor,undefined,'Existing conflicting line enclosures have outlines only');
    assert.equal(item.badge.isConnected,true,'Keep crop icons for records needing correction');
  }
  assert.match(status.textContent,/sections overlap/);
});

test('saved crop drawings load across metadata-qualified parcels without farmer selection', () => {
  const {context}=harness();let request;
  context.selectedFarmerId=1;context.focusedCropPlotId=null;
  context.savedPlotOverlays=[{farmerId:1,plotId:10},{farmerId:2,plotId:20},{farmerId:3,plotId:30}];
  context.cropSettings={enabled:false,year:2025,season:'wet',filter:'all'};
  context.plantedAreaLayer={load:(settings,ids,force)=>{request={settings,ids,force};}};
  context.cropLayer={state:{status:'ready'},record:id=>({has_planted_areas:id!==30})};
  vm.runInContext(declaration('refreshPlantedAreas'),context);
  context.refreshPlantedAreas(false);
  assert.equal(request.settings.enabled,true);assert.equal(request.settings.year,2025);assert.equal(request.settings.season,'wet');
  assert.deepEqual(Array.from(request.ids),[10,20]);
  context.selectedFarmerId=null;context.refreshPlantedAreas(false);assert.deepEqual(Array.from(request.ids),[10,20],'Selection does not gate crop drawings');
  context.focusedCropPlotId=10;context.refreshPlantedAreas(false);assert.deepEqual(Array.from(request.ids),[10,20],'Focusing a parcel cannot drop other cached crop drawings');
});

test('crop batches across many farmers draw progressively without rebuilding earlier GPU objects',async()=>{
  const {context,metrics,toggle}=harness();
  const status={textContent:''};
  context.document.getElementById=id=>id==='togglePlots'?toggle:id==='plantedAreaLayerStatus'?status:null;
  const helper=require('../../public/js/crop-area-badges.js');
  context.window.CropAreaBadges={...helper,markerContent:(_,crop)=>crop,parcelMarkerContent:(_,crops)=>crops.slice()};
  context.Marker3DElement=class{constructor(options){Object.assign(this,options);}replaceChildren(content){this.content=content;}};
  context.google={maps:{CollisionBehavior:{REQUIRED:'required'}}};
  context.map3d.range=2000;context.plotMode=false;
  context.cropLayer={record:()=>({crop:'corn',area_crops:['corn']}),color:(_,saved)=>saved,visible:()=>true};
  vm.runInContext(declaration('renderPlantedAreas'),context);
  const records=Array.from({length:64},(_,index)=>({plot_id:index+1,areas:[{
    crop:'corn',crop_label:'Corn',color:'#D4A017',polygon:ring().filter((_,i)=>i%10===0),area_ha:.4,
  }]}));
  for(const record of records)context.renderPlotsForFarmer(String(record.plot_id),[{id:record.plot_id,polygon_json:ring()}]);
  const before=JSON.stringify(records);
  await context.renderPlantedAreas({status:'loading',records:records.slice(0,20),loaded:20,total:64});
  assert.equal(context.selectedFarmerId,null);assert.equal(context.plantedAreaOverlays.length,20);
  const first=context.plantedAreaOverlays[0];
  assert.equal(first.poly.isConnected,true);assert.equal(first.badge.isConnected,true);
  assert.match(status.textContent,/20 of 64/);
  await context.renderPlantedAreas({status:'error',records:records.slice(0,40),loaded:40,total:64,error:'Retry the remaining drawings.'});
  assert.equal(context.plantedAreaOverlays.length,40);assert.equal(context.plantedAreaOverlays[0],first);
  assert.equal(first.poly.isConnected,true,'An unfinished later batch cannot hide verified crop drawings');
  await context.renderPlantedAreas({status:'ready',records,loaded:64,total:64});
  assert.equal(metrics.constructed,128,'One parcel polygon and one crop polygon per record');
  assert.ok(metrics.frames>1,'Drawing the whole map yields browser frames');
  assert.ok(context.plantedAreaOverlays.every(item=>item.poly.isConnected && item.badge.isConnected));
  await context.renderPlantedAreas({status:'ready',records});assert.equal(metrics.constructed,128);
  context.focusedParcelFarmerId='1';await context.renderPlantedAreas({status:'ready',records});
  assert.equal(context.plantedAreaOverlays.filter(item=>item.poly.isConnected).length,1);
  context.focusedParcelFarmerId=null;await context.renderPlantedAreas({status:'ready',records});
  assert.equal(context.plantedAreaOverlays.filter(item=>item.poly.isConnected).length,64);
  let opened;
  context.window.__openFarmer3d=(id,options)=>{opened={id,options};return Promise.resolve();};
  first.poly.events['gmp-click']({stopPropagation(){}});
  assert.equal(opened.id,'1');assert.equal(opened.options.focusParcels,false,'Crop inspection keeps the whole map visible');
  context.clearPlotsForFarmer('1');await context.renderPlantedAreas({status:'ready',records});
  assert.equal(first.poly.isConnected,false);assert.equal(first.badge.isConnected,false);
  assert.equal(context.plantedAreaOverlays.length,63);assert.equal(metrics.constructed,128);
  assert.equal(JSON.stringify(records),before,'Overview rendering cannot rewrite saved crop coordinates');
});

test('a cancelled drawing frame cannot restore crop overlays from a previous map period',async()=>{
  const {context,mounted}=harness();
  context.window.CropAreaBadges={position:()=>({lat:15,lng:120}),template:(_,crop)=>crop};
  context.Marker3DElement=class{constructor(options){Object.assign(this,options);}replaceChildren(){}};
  context.google={maps:{CollisionBehavior:{REQUIRED:'required'}}};
  context.map3d.range=2000;
  vm.runInContext(declaration('renderPlantedAreas'),context);
  const records=Array.from({length:30},(_,index)=>({plot_id:index+1,areas:[{
    crop:'rice',crop_label:'Rice',color:'#219653',polygon:ring().filter((_,i)=>i%10===0),
  }]}));
  for(const record of records)context.renderPlotsForFarmer(String(record.plot_id),[{id:record.plot_id,polygon_json:ring()}]);
  const pending=context.renderPlantedAreas({status:'ready',records});
  await context.renderPlantedAreas({status:'loading',records:[],loaded:0,total:30});
  await pending;
  assert.equal(context.plantedAreaOverlays.length,0);assert.equal(context.plantedAreaRenderedRecords.size,0);
  assert.equal(mounted.size,30,'Only the parcel boundaries remain after the new period clears drawings');
});

test('geometry waits for crop metadata and a saved-season refresh runs once when metadata is ready',()=>{
  const {context}=harness();const requests=[];
  context.savedPlotOverlays=[{plotId:1},{plotId:2}];
  context.plantedAreaLayer={state:{status:'ready',year:2026,season:'dry'},load:(settings,ids,force,resume)=>requests.push({settings,ids,force,resume})};
  context.cropLayer={state:{status:'loading'},record:()=>({has_planted_areas:true})};
  vm.runInContext(declaration('refreshPlantedAreas'),context);
  context.refreshPlantedAreas(true);assert.equal(requests.length,0);
  context.cropLayer.state.status='ready';context.refreshPlantedAreas(false);
  assert.equal(requests.length,1);assert.equal(requests[0].force,true);assert.deepEqual(Array.from(requests[0].ids),[1,2]);
  context.refreshPlantedAreas(false);assert.equal(requests[1].force,false,'Ordinary camera/selection refreshes reuse accepted data');
  context.cropSettings.year=2025;context.cropLayer.state.status='loading';context.refreshPlantedAreas(false);
  assert.equal(requests.at(-1).settings.enabled,false,'A new period removes old crop geometry before its metadata arrives');
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

// Exercise the actual layer subscriptions, not just the renderer in isolation.
// A failure in this callback can prevent every crop request before rendering starts.
function connectedCropHarness(options = {}) {
  const result = harness();
  const {context, toggle} = result;
  const status = {textContent:''};
  context.document.getElementById = id => id === 'togglePlots' ? toggle : id === 'parcelCropStatus' ? status : null;
  context.hideParcelHoverCard = () => {};
  context.window.ParcelCropLayer = require('../../public/js/parcel-crop-layer.js');
  context.window.PlantedAreaLayer = require('../../public/js/planted-area-layer.js');
  const badges = require('../../public/js/crop-area-badges.js');
  context.window.CropAreaBadges = {...badges, markerContent: (_,crop) => crop, parcelMarkerContent: (_,crops) => crops.slice()};
  context.window.__parcelCropLayerUrl = '/classifications';
  context.window.__plantedAreaLayerUrl = '/drawings';
  context.cropModalEpoch = 0;
  context.Marker3DElement = class {constructor(options) {Object.assign(this,options);} replaceChildren(content) {this.content=content;}};
  context.google = {maps:{CollisionBehavior:{REQUIRED:'required'}}};
  context.map3d.range = 2000;
  context.cropSettings.icons = true;
  context.renderPlotsForFarmer('1', [{id:1,farmer_id:1,polygon_json:ring()}]);
  const areas = [
    {type:'line',crop:'rice',crop_label:'Rice / Palay',color:'#219653',path:[
      {lat:15.0001,lng:120.0001},{lat:15.0001,lng:120.0009},{lat:15.0004,lng:120.0009},{lat:15.0004,lng:120.0001}]},
    {type:'line',crop:'corn',crop_label:'Corn',color:'#D4A017',path:[
      {lat:15.0005,lng:120.0001},{lat:15.0005,lng:120.0009},{lat:15.0008,lng:120.0009},{lat:15.0008,lng:120.0001}]},
  ];
  const crop = {plot_id:1,crop:'mixed',crop_label:'Mixed crops',color:'#607D8B',area_crops:['rice','corn'],has_planted_areas:true};
  const planted = {plot_id:1,areas};
  const requests = [];
  context.window.fetch = async url => {
    requests.push(url);
    if (options.fetch) return options.fetch(url);
    return {ok:true,json:async()=>({available:true,year:2026,season:'dry',records:[url.startsWith('/classifications') ? crop : planted]})};
  };
  vm.runInContext(['loadCropLayer','refreshPlantedAreas','renderPlantedAreas','schedulePlotDisplayRefresh'].map(declaration).join('\n'), context);
  const start = source.indexOf('var plantedAreaLayer = window.PlantedAreaLayer');
  const end = source.indexOf('var currentCropYear =', start);
  assert.ok(start >= 0 && end > start);
  vm.runInContext(source.slice(start,end), context);
  return {...result,status,requests,crop,planted};
}

function assertCropSectionsVisible(context) {
  assert.equal(context.plantedAreaOverlays.length,2);
  assert.deepEqual(Array.from(context.plantedAreaOverlays, item => item.poly.fillColor), ['#219653','#D4A017']);
  assert.deepEqual(Array.from(context.plantedAreaOverlays, item => item.badge.content), ['rice','corn']);
  assert.ok(context.plantedAreaOverlays.every(item => item.poly.isConnected && item.badge.isConnected));
  assert.equal(context.savedPlotOverlays[0].poly.fillColor,'#FFFFFF');
}

test('real map subscriptions load colored crop sections and icons on opening without selecting a farmer', async () => {
  const {context,requests,status} = connectedCropHarness();
  await context.loadCropLayer(false);
  await new Promise(resolve=>setTimeout(resolve,50));
  assert.equal(context.selectedFarmerId,null);
  assert.equal(context.cropLayer.state.status,'ready');
  assert.equal(context.plantedAreaLayer.state.status,'ready');
  assert.equal(requests.length,2,'Metadata must trigger one drawing read without a click or reload');
  assert.match(status.textContent,/2026.*Dry season/);
  assertCropSectionsVisible(context);
});

test('real map subscriptions tint classification-only parcels and show their icon without a selection', async () => {
  const {context,requests,crop,planted} = connectedCropHarness();
  Object.assign(crop,{crop:'corn',crop_label:'Corn',color:'#D4A017',area_crops:[],has_planted_areas:false});
  planted.areas=[];
  await context.loadCropLayer(false);
  await new Promise(resolve=>setTimeout(resolve,50));
  const overlay=context.savedPlotOverlays[0];
  assert.equal(context.selectedFarmerId,null);
  assert.equal(overlay.poly.fillColor,'#D4A017');
  assert.equal(overlay.poly.strokeColor,'#FFFFFF');
  assert.ok(overlay.cropBadge.isConnected);
  assert.deepEqual(Array.from(overlay.cropBadge.content),['corn']);
  assert.equal(context.plantedAreaOverlays.length,0);
  assert.equal(requests.length,1,'Classification tint needs no extra drawing request');
});

test('real modal save subscriptions paint returned crop sections before closing without another layer request', async () => {
  const {context,requests,crop,planted} = connectedCropHarness();
  // Begin with an empty season that has already loaded, as on a new crop save.
  Object.assign(context.cropLayer.state,{status:'ready',year:2026,season:'dry'});
  const messages=[];
  let closed=false;
  context.FormData = class {};
  context.fetch = async () => ({ok:true,json:async()=>({year:2026,season:'dry',crop_record:crop,planted_record:planted})});
  context.cropModalMessage = (text,kind) => messages.push({text,kind});
  context.closeCropModal = () => {assertCropSectionsVisible(context);closed=true;};
  vm.runInContext(declaration('submitCropModal'),context);
  context.submitCropModal({action:'/save-crops',querySelector:()=>({disabled:false})});
  for (let i=0; i<10; i++) await new Promise(resolve=>setImmediate(resolve));
  assert.deepEqual(messages.filter(message=>message.kind==='error'),[]);
  assert.equal(closed,true,'Successful save closes after the visible colors and icons are updated');
  assert.equal(requests.length,0,'The save response supplies the validated crop data');
  context.selectedFarmerId=null;
  await context.renderPlantedAreas(context.plantedAreaLayer.state);
  assertCropSectionsVisible(context);
});

test('whole-parcel crop saves paint their color and icon before the modal closes', async () => {
  const {context,requests,crop,planted} = connectedCropHarness();
  Object.assign(crop,{crop:'corn',crop_label:'Corn',color:'#D4A017',area_crops:[],has_planted_areas:false});
  planted.areas=[];
  Object.assign(context.cropLayer.state,{status:'ready',year:2026,season:'dry'});
  context.FormData=class {};
  context.fetch=async()=>({ok:true,json:async()=>({year:2026,season:'dry',crop_record:crop,planted_record:planted})});
  const errors=[];
  context.cropModalMessage=(message,kind)=>{if(kind==='error') errors.push(message);};
  let closed=false;
  context.closeCropModal=()=>{
    const overlay=context.savedPlotOverlays[0];
    assert.equal(overlay.poly.fillColor,'#D4A017');
    assert.equal(overlay.cropBadge.isConnected,true);
    closed=true;
  };
  vm.runInContext(declaration('submitCropModal'),context);
  context.submitCropModal({action:'/save-crops',querySelector:()=>({disabled:false})});
  await new Promise(resolve=>setTimeout(resolve,50));
  assert.deepEqual(errors,[]);
  assert.equal(closed,true);
  assert.equal(requests.length,0);
});

function attachParcelStatistics(context) {
  const statistics = require('../../public/js/farmer-parcel-stats.js');
  const frames = [];
  const root = {};
  const originalGetElement = context.document.getElementById;
  context.document.getElementById = id => id === 'farmerParcelStats' ? root : originalGetElement(id);
  context.window.FarmerParcelStats = {
    render: (element, input) => {
      assert.equal(element, root);
      frames.push(statistics.summarize(input));
    }
  };
  return {frames, latest: () => frames.at(-1)};
}

function parcelLoadingHarness(plots = []) {
  const result = harness(plots);
  const {context} = result;
  const statistics = attachParcelStatistics(context);
  const panelUpdates = [];
  Object.assign(context, {
    updatePlotCount: value => panelUpdates.push(['count', value]),
    updatePlotTotalArea: value => panelUpdates.push(['area', value]),
    updatePlotList: rows => panelUpdates.push(['plots', rows.map(row => row.id)]),
    setPlotListState: state => panelUpdates.push(['state', state]),
    sumPlotAreas: rows => rows.reduce((sum, row) => sum + Number(row.area_ha || 0), 0),
    zoomToPlots: rows => panelUpdates.push(['zoom', rows.map(row => row.id)]),
  });
  vm.runInContext(['fetchPlotsForFarmer', 'loadPlotsForSelectedFarmer'].map(declaration).join('\n'), context);
  return {...result, ...statistics, panelUpdates};
}

test('a truncated overview cannot become a complete farmer land chart, and a complete read is reused', async () => {
  const first = {id:1,farmer_id:1,area_ha:1.5,polygon_json:ring()};
  const second = {id:2,farmer_id:1,area_ha:2.5,polygon_json:ring(.01)};
  const {context, latest} = parcelLoadingHarness();
  context.fetch = async () => ({ok:true,json:async()=>({plots:[first],total:2,returned:1,truncated:true})});
  await context.loadAllMunicipalPlots();
  assert.equal(context.completePlotFarmers.has('1'),false);
  assert.equal(context.plotsCacheByFarmerId.get('1').length,1,'The incomplete overview is still useful for map drawing');
  let requests = 0;
  context.fetch = async url => {
    requests++;
    assert.equal(url,'/farmers/1/plots');
    return {ok:true,json:async()=>({plots:[first,second]})};
  };
  context.selectedFarmerId = '1';
  await context.loadPlotsForSelectedFarmer('1');
  assert.equal(latest().totalParcels,2);
  assert.equal(latest().totalAreaHa,4);
  assert.equal(context.completePlotFarmers.has('1'),true);
  await context.loadPlotsForSelectedFarmer('1');
  assert.equal(requests,1,'Reopening an unchanged farmer reuses their complete authorized records');
});

test('failed and malformed parcel responses cannot become zero-parcel charts or cached empty successes', async () => {
  const {context, latest, panelUpdates} = parcelLoadingHarness();
  context.selectedFarmerId = '1';
  context.fetch = async () => ({ok:false,status:500});
  await assert.rejects(context.loadPlotsForSelectedFarmer('1'),/Could not load/);
  assert.equal(latest().landStatus,'error');
  assert.equal(latest().totalParcels,null,'Unavailable land must not be reported as zero saved parcels');
  assert.equal(context.plotsCacheByFarmerId.has('1'),false);
  assert.equal(context.completePlotFarmers.has('1'),false);
  assert.equal(context.plotFetchPromisesByFarmerId.has('1'),false);
  assert.deepEqual(panelUpdates.filter(row => row[0] === 'state'), [['state', 'loading'], ['state', 'error']]);
  assert.equal(panelUpdates.some(row => row[0] === 'plots'), false, 'An unavailable parcel list never renders an empty success');
  context.fetch = async () => ({ok:true,json:async()=>({plots:null})});
  await assert.rejects(context.loadPlotsForSelectedFarmer('1'),/Could not read/);
  assert.equal(context.plotsCacheByFarmerId.has('1'),false);
  const plot = {id:7,farmer_id:1,area_ha:3,polygon_json:ring()};
  context.fetch = async () => ({ok:true,json:async()=>({plots:[plot]})});
  await context.loadPlotsForSelectedFarmer('1',{force:true});
  assert.equal(latest().landStatus,'ready');
  assert.equal(latest().totalParcels,1);
  assert.equal(latest().totalAreaHa,3);
});

test('a slow previous farmer cannot overwrite the current farmer charts, plot list or camera', async () => {
  const {context, latest, panelUpdates} = parcelLoadingHarness();
  const pending = new Map();
  context.fetch = url => new Promise(resolve => pending.set(url,resolve));
  context.selectedFarmerId = '1';
  const first = context.loadPlotsForSelectedFarmer('1',{autoZoom:true});
  assert.equal(latest().landStatus,'loading');
  context.selectedFarmerId = '2';
  const second = context.loadPlotsForSelectedFarmer('2',{autoZoom:true});
  const secondPlots = [{id:20,farmer_id:2,area_ha:2,polygon_json:ring(.01)}];
  pending.get('/farmers/2/plots')({ok:true,json:async()=>({plots:secondPlots})});
  await second;
  const updatesAfterSelection = panelUpdates.length;
  pending.get('/farmers/1/plots')({ok:true,json:async()=>({plots:[{id:10,farmer_id:1,area_ha:99,polygon_json:ring()}]})});
  await first;
  assert.equal(context.parcelStatsState.farmerId,'2');
  assert.equal(latest().totalAreaHa,2);
  assert.deepEqual(latest().landRows.map(row=>row.id),['20']);
  assert.equal(panelUpdates.length,updatesAfterSelection,'An old response cannot touch the latest farmer panel or camera');
  assert.deepEqual(Array.from(context.savedPlotOverlays,overlay=>String(overlay.plotId)),['20']);
});

test('an older forced parcel refresh cannot overwrite a newer accepted cache or clear its pending request', async () => {
  const {context} = parcelLoadingHarness();
  const pending = [];
  context.fetch = () => new Promise(resolve=>pending.push(resolve));
  const first = context.fetchPlotsForFarmer('1');
  const second = context.fetchPlotsForFarmer('1',{force:true});
  pending[0]({ok:true,json:async()=>({plots:[{id:1}]})});
  await first;
  assert.equal(context.plotsCacheByFarmerId.has('1'),false);
  assert.equal(context.plotFetchPromisesByFarmerId.get('1'),second,'An old finally block cannot drop the newer request');
  pending[1]({ok:true,json:async()=>({plots:[{id:2}]})});
  await second;
  assert.equal(context.plotsCacheByFarmerId.get('1')[0].id,2);
  assert.equal(context.plotFetchPromisesByFarmerId.has('1'),false);
});

test('real crop save subscriptions update selected farmer crop charts before closing without another request', async () => {
  const {context,requests,crop,planted} = connectedCropHarness();
  const {latest} = attachParcelStatistics(context);
  context.selectedFarmerId = '1';
  context.parcelStatsState = {farmerId:'1',landStatus:'ready',plots:[{id:1,area_ha:6.62}]};
  Object.assign(context.cropLayer.state,{status:'ready',year:2026,season:'dry'});
  context.FormData = class {};
  context.fetch = async () => ({ok:true,json:async()=>({year:2026,season:'dry',crop_record:crop,planted_record:planted})});
  const errors = [];
  context.cropModalMessage = (text,kind) => { if (kind==='error') errors.push(text); };
  let closed = false;
  context.closeCropModal = () => {
    assert.equal(latest().coverage.recorded,1);
    assert.deepEqual(latest().cropRows.map(row=>row.code).sort(),['corn','rice']);
    assert.equal(latest().totalAreaHa,6.62,'Two visual crop marks cannot double the mapped land total');
    closed = true;
  };
  vm.runInContext(declaration('submitCropModal'),context);
  context.submitCropModal({action:'/save-crops',querySelector:()=>({disabled:false})});
  for (let i=0; i<10; i++) await new Promise(resolve=>setImmediate(resolve));
  assert.deepEqual(errors,[]);
  assert.equal(closed,true);
  assert.equal(requests.length,0,'Charts reuse the validated save response');
});

test('real crop period changes clear prior chart counts and distinguish loading from explicitly unrecorded crops', async () => {
  let resolveWet;
  const corn = {plot_id:1,crop:'corn',color:'#D4A017',area_crops:[],has_planted_areas:false};
  const {context} = connectedCropHarness({fetch:url=>{
    if (url.includes('season=wet')) return new Promise(resolve=>{resolveWet=resolve;});
    return {ok:true,json:async()=>({year:2026,season:'dry',records:[corn]})};
  }});
  const {latest} = attachParcelStatistics(context);
  context.selectedFarmerId = '1';
  context.parcelStatsState = {farmerId:'1',landStatus:'ready',plots:[{id:1,area_ha:2}]};
  await context.loadCropLayer(false);
  assert.equal(latest().coverage.recorded,1);
  context.cropSettings = {...context.cropSettings,season:'wet'};
  const changing = context.loadCropLayer(false);
  assert.equal(latest().periodLabel,'2026 · Wet season');
  assert.equal(latest().cropStatus,'loading');
  assert.equal(latest().cropRows.length,0,'The previous season cannot remain in the new chart');
  assert.equal(latest().coverage.unknown,1);
  assert.equal(latest().coverage.notRecorded,0,'Pending records are not absent crop registrations');
  resolveWet({ok:true,json:async()=>({year:2026,season:'wet',records:[{...corn,crop:'not_recorded',color:'#8A9299'}]})});
  await changing;
  assert.equal(latest().coverage.unknown,0);
  assert.equal(latest().coverage.notRecorded,1);
  assert.equal(latest().coverage.percentage,0);
});
const parcelListHarness = () => {
  const retry = {addEventListener(name, callback) { this.click = callback; }};
  const list = {innerHTML:'',attributes:{},setAttribute(name,value) {this.attributes[name]=value;},
    querySelector(selector) {return selector === '[data-parcel-list-retry]' && this.innerHTML.includes('data-parcel-list-retry') ? retry : null;},
    querySelectorAll() {return [];}};
  const count = {textContent:''}, period = {textContent:''};
  const context = vm.createContext({document:{getElementById:id=>({plotList:list,parcelListCount:count,parcelListPeriod:period})[id] || null},
    window:{__canManageOperationalData:true,__parcelSatelliteUrl:'/parcels/__ID__/satellite',__parcelCropEditUrl:'/parcels/__ID__/crops'},
    selectedFarmerId:'1',selectedPlotId:null,cropSettings:{year:2026,season:'dry'},cropLayer:null,
    syncSuggestedPlotName:()=>{},escapeHtml:value=>String(value).replace(/[&<>"']/g, value=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[value]))});
  vm.runInContext(['setPlotListState','parcelCropSummary','refreshPlotListMetadata','updatePlotList'].map(declaration).join('\n'),context);
  return {context,list,count,period,retry};
};

test('parcel list distinguishes no selection, loading, failed and confirmed empty results and retries the current farmer', async () => {
  const {context,list,count,retry} = parcelListHarness();
  context.selectedFarmerId = null;
  context.updatePlotList([]);
  assert.match(list.innerHTML,/Choose a farmer/);
  assert.doesNotMatch(list.innerHTML,/No saved parcels/);
  context.selectedFarmerId = '9';
  context.setPlotListState('loading');
  assert.equal(list.attributes['aria-busy'],'true');
  assert.equal(count.textContent,'Loading…');
  context.setPlotListState('error');
  assert.match(list.innerHTML,/does not mean this farmer has no parcels/);
  assert.equal(count.textContent,'Unavailable');
  let retried;
  context.loadPlotsForSelectedFarmer=(id,options)=>{retried={id,options};return Promise.resolve();};
  retry.click();
  assert.equal(retried.id,'9');
  assert.equal(retried.options.force,true);
  context.updatePlotList([]);
  assert.match(list.innerHTML,/No saved parcels for this farmer/);
  assert.equal(count.textContent,'0 parcels');
  assert.equal(list.attributes['aria-busy'],'false');
});

test('parcel cards label saved areas honestly and escape names while preserving read-only record actions', () => {
  const {context,list,count} = parcelListHarness();
  context.window.__canManageOperationalData=false;
  context.updatePlotList([{id:1,name:'<img src=x onerror=alert(1)>',area_ha:null,color:'red;position:fixed'},
    {id:2,name:'East field',area_ha:2.25}]);
  assert.equal(count.textContent,'2 parcels');
  assert.match(list.innerHTML,/Recorded area unavailable/);
  assert.match(list.innerHTML,/2.25 ha recorded/);
  assert.match(list.innerHTML,/&lt;img/);
  assert.doesNotMatch(list.innerHTML,/<img|position:fixed|data-action="editPlot"|data-action="deletePlot"/);
  assert.match(list.innerHTML,/data-action="openCropModal"/);
  assert.match(list.innerHTML,/data-action="openSatellite"/);
});

test('parcel crop summaries separate not-recorded, unavailable and loading values for the applied period', () => {
  const {context} = parcelListHarness();
  context.cropLayer={state:{status:'ready'},record:()=>({crop:'not_recorded',crop_label:'Not recorded'})};
  assert.equal(context.parcelCropSummary('1'),'2026 · Dry season · No crop recorded');
  context.cropLayer.record=()=>null;
  assert.match(context.parcelCropSummary('1'),/Crop details unavailable/);
  context.cropSettings={year:2025,season:'wet'};
  context.cropLayer.state.status='loading';
  assert.equal(context.parcelCropSummary('1'),'2025 · Wet season · Loading crop record…');
  context.cropLayer.state.status='ready';
  context.cropLayer.record=()=>({crop:'rice',crop_label:'Rice'});
  assert.match(context.parcelCropSummary('1'),/2025 · Wet season · Saved crop: Rice/);
});

test('crop and selection refresh updates text without rebuilding an expanded parcel card', () => {
  const {context,list,period} = parcelListHarness();
  const card={open:true,classList:{toggle(name,value) {this[name]=value;}}};
  const crop={textContent:'Old crop',getAttribute:()=> '2'};
  const selection={hidden:true,getAttribute:()=> '2',closest:()=>card};
  list.innerHTML='Preserved card';
  list.querySelectorAll=selector=>selector === '[data-parcel-crop-summary]' ? [crop] : [selection];
  context.selectedPlotId='2';
  context.cropLayer={state:{status:'ready'},record:()=>({crop:'rice',crop_label:'Rice'})};
  context.refreshPlotListMetadata();
  assert.equal(list.innerHTML,'Preserved card');
  assert.equal(card.open,true);
  assert.equal(selection.hidden,false);
  assert.equal(card.classList['is-selected'],true);
  assert.match(crop.textContent,/Saved crop: Rice/);
  assert.match(period.textContent,/2026 · Dry season/);
});
