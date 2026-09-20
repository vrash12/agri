const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const geometry = require('../../public/js/parcel-display-geometry.js');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/farmers-maps.js'), 'utf8');

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
    },
    // Any accidental restoration of the invisible line doubles the map workload.
    Polyline3DInteractiveElement: class { constructor() { throw new Error('Unexpected duplicate outline'); } },
    AltitudeMode: { CLAMP_TO_GROUND: 'ground' },
    savedPlotOverlays: [], renderedPlotDataByFarmerId: new Map(), plotsCacheByFarmerId: new Map(),
    savedPlotsHiddenForEditing: false, focusedParcelFarmerId: null, selectedFarmerId: null, editingPlotId: null,
    cropLayer: null, queueCropLayer: () => {},
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
    'refreshSavedPlotDisplay', 'clearPlotsForFarmer', 'renderPlotsForFarmer', 'loadAllMunicipalPlots', 'applyCropStyle'];
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
