const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/municipality-boundaries.js'), 'utf8');

function boundary(id, lng = 120 + id / 100, lat = 15) {
  return {
    id, municipality_id: id, municipality_name: 'Town ' + id,
    name: 'Boundary ' + id, status: 'active', color: '#15803d', area_ha: 100,
    centroid_lat: lat + .005, centroid_lng: lng + .005, vertex_count: 5,
    label_position: {lat: lat + .005, lng: lng + .005},
    updated_at: '2026-09-19T00:00:00Z', _record_version: 'version-' + id,
    geojson: { type: 'Polygon', coordinates: [[[lng, lat], [lng + .01, lat], [lng + .01, lat + .01], [lng, lat + .01], [lng, lat]]] },
  };
}

const latLng = point => ({
  lat: () => typeof point.lat === 'function' ? point.lat() : Number(point.lat),
  lng: () => typeof point.lng === 'function' ? point.lng() : Number(point.lng),
});

class Bounds {
  constructor(sw, ne) {
    this.south = Infinity; this.west = Infinity; this.north = -Infinity; this.east = -Infinity;
    if (sw) this.extend(sw);
    if (ne) this.extend(ne);
  }
  extend(point) {
    const p = latLng(point);
    this.south = Math.min(this.south, p.lat()); this.north = Math.max(this.north, p.lat());
    this.west = Math.min(this.west, p.lng()); this.east = Math.max(this.east, p.lng());
    return this;
  }
  getNorthEast() { return latLng({ lat: this.north, lng: this.east }); }
  getSouthWest() { return latLng({ lat: this.south, lng: this.west }); }
  isEmpty() { return this.south === Infinity; }
  toJSON() { return { south: this.south, west: this.west, north: this.north, east: this.east }; }
}

const bounds = (south, west, north, east) => new Bounds({ lat: south, lng: west }, { lat: north, lng: east });

function workspace(initialBoundaries, options = {}) {
  let clock = 0;
  let nextHandle = 1;
  const timers = new Map();
  const frames = new Map();
  const elements = new Map();
  const polygons = [];
  const labels = [];
  const maps = [];
  const requests = [];
  const schedule = (callback, delay = 0) => {
    const id = nextHandle++;
    timers.set(id, { at: clock + delay, callback });
    return id;
  };
  const raf = callback => { const id = nextHandle++; frames.set(id, callback); return id; };

  function element(id) {
    if (!elements.has(id)) {
      const listeners = new Map();
      elements.set(id, {
        value: '', disabled: id === 'toggleMunicipalityMapLabels', hidden: false, textContent: '', innerHTML: '', dataset: {},
        attributes: {},
        classList: { toggle() {}, add() {}, remove() {} },
        setAttribute(name, value) { this.attributes[name] = String(value); }, querySelectorAll: () => [],
        addEventListener(type, callback) {
          if (!listeners.has(type)) listeners.set(type, []);
          listeners.get(type).push(callback);
        },
        dispatch(type, additions = {}) {
          const event = { target: this, currentTarget: this, preventDefault() {}, ...additions };
          for (const callback of listeners.get(type) || []) callback(event);
        },
      });
    }
    return elements.get(id);
  }

  class MapMock {
    constructor(node, config) {
      this.node = node; this.config = config; this.zoom = config.zoom; this.center = config.center; this.mapTypeId = config.mapTypeId;
      this.bounds = bounds(-80, -179, 80, 179);
      this.listeners = new Map(); this.fits = [];
      maps.push(this);
    }
    addListener(type, callback) {
      if (!this.listeners.has(type)) this.listeners.set(type, []);
      this.listeners.get(type).push(callback);
      return { remove() {} };
    }
    trigger(type, event) { for (const callback of this.listeners.get(type) || []) callback(event); }
    getBounds() { return this.bounds; }
    getZoom() { return this.zoom; }
    getMapTypeId() { return this.mapTypeId; }
    setMapTypeId(value) { this.mapTypeId = value; this.trigger('maptypeid_changed'); }
    setZoom(value) { this.zoom = value; }
    setCenter(value) { this.center = value; }
    fitBounds(value, padding) { this.fits.push({ bounds: value.toJSON(), padding }); this.bounds = value; }
  }

  class PolygonMock {
    constructor(config) {
      this.config = { ...config }; this.map = config.map || null; this.mapChanges = [];
      this.listeners = new Map();
      const paths = config.paths || [];
      this.paths = (Array.isArray(paths[0]) ? paths : [paths]).map(ring => ring.map(latLng));
      polygons.push(this);
    }
    setMap(map) { this.map = map; this.mapChanges.push(map); }
    setOptions(config) { Object.assign(this.config, config); }
    addListener(type, callback) { this.listeners.set(type, callback); return { remove() {} }; }
    trigger(type, event) { this.listeners.get(type)?.(event); }
    getPaths() { return this.paths.map(ring => ({ forEach: callback => ring.forEach(callback), getArray: () => ring })); }
    getPath() { const ring = this.paths[0]; return { forEach: callback => ring.forEach(callback), getArray: () => ring, getLength: () => ring.length }; }
  }

  class MarkerMock {
    constructor(config) { this.config = config; this.map = config.map || null; labels.push(this); }
    setMap(map) { this.map = map; }
    getMap() { return this.map; }
    setVisible(visible) { this.visible = visible; }
  }

  const settings = {
    key: 'local-test-key', mapId: '', canChooseMunicipality: true, canManage: false,
    assignedMunicipalityId: null, initialBoundaries,
    municipalities: initialBoundaries.map(item => ({ id: item.municipality_id, name: item.municipality_name })),
    initialSummary: { configured: initialBoundaries.length, farmers: 0, parcels: 0, mapped_area_ha: 0 },
    dataUrl: '/municipality-boundaries/data', ...options,
  };
  const window = { GeofenceStyle: require('../../public/js/geofence-style.js'), __municipalityBoundarySettings: settings, requestAnimationFrame: raf, cancelAnimationFrame: id => frames.delete(id) };
  const context = vm.createContext({
    window, console, AbortController, Map, Set, Number, Math, JSON, Date, URL,
    setTimeout: schedule, clearTimeout: id => timers.delete(id),
    requestAnimationFrame: raf, cancelAnimationFrame: id => frames.delete(id),
    performance: { now: () => clock },
    document: {
      hidden: false, getElementById: element,
      createElement: tag => tag === 'div' ? {
        set textContent(value) { this.innerHTML = String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); },
        innerHTML: '',
      } : {},
      head: { appendChild() {} }, addEventListener() {},
    },
    google: { maps: {
      Map: MapMock, Polygon: PolygonMock, Marker: MarkerMock, LatLngBounds: Bounds,
      SymbolPath: { CIRCLE: 0 },
      InfoWindow: class { setContent() {} setPosition() {} open() {} close() {} },
      event: { removeListener() {}, clearInstanceListeners() {} },
    } },
    fetch(url, config = {}) {
      let resolve;
      const promise = new Promise(done => { resolve = done; });
      requests.push({ url, config, respond(payload, status = 200) { resolve({ ok: status < 400, status, json: async () => payload }); },
        respondHtml(status = 200) { resolve({ ok: status < 400, status, redirected: status === 200, json: async () => { throw new SyntaxError('HTML response'); } }); } });
      return promise;
    },
  });
  vm.runInContext(source, context);

  function advance(milliseconds) {
    const end = clock + milliseconds;
    for (let count = 0; count < 1000; count++) {
      const next = [...timers.entries()].filter(([, timer]) => timer.at <= end).sort((a, b) => a[1].at - b[1].at)[0];
      if (!next) break;
      timers.delete(next[0]); clock = next[1].at; next[1].callback();
    }
    clock = end;
  }
  function frame() {
    const queued = [...frames.entries()];
    for (const [id, callback] of queued) {
      if (!frames.delete(id)) continue;
      callback(clock);
    }
  }
  function flushFrames() {
    for (let count = 0; frames.size && count < 200; count++) frame();
    assert.equal(frames.size, 0, 'Rendering must finish in a bounded number of frames');
  }
  function select(id) { element('municipalityFilter').value = String(id); element('municipalityFilter').dispatch('change'); }
  function search(text) { element('boundarySearch').value = text; element('boundarySearch').dispatch('input'); }
  function pan(viewport, zoom = 11) { maps[0].bounds = viewport; maps[0].zoom = zoom; maps[0].trigger('idle'); advance(100); flushFrames(); }
  async function settle() { for (let i = 0; i < 10; i++) await Promise.resolve(); }
  if (options.initializeMap !== false) window.initMunicipalityGeofenceMap();
  return { maps, polygons, labels, requests, element, advance, frame, flushFrames, select, search, pan, settle, frames, initialize: window.initMunicipalityGeofenceMap };
}

function payload(item, parcels = []) {
  return {
    municipality: { id: item.municipality_id, name: item.municipality_name },
    boundaries: [item], parcels, review: [], snapshot: null,
    stats: { farmers: 0, mapped_farmers: 0, parcels: parcels.length, mapped_area_ha: 0, outside: 0, partial: 0, near_boundary: 0 },
  };
}

test('overview omits duplicate outlines and close zoom restores casing without changing coordinates', () => {
  const item = boundary(1);
  const snapshot = JSON.stringify(item.geojson);
  const view = workspace([item]);
  view.flushFrames();
  assert.equal(view.polygons.filter(p => p.map).length, 1);
  view.pan(bounds(14, 119, 16, 122), 13);
  assert.equal(view.polygons.filter(p => p.map).length, 2);
  assert.ok(view.polygons.filter(p => p.map).every(p => p.paths[0].length === 5));
  view.pan(bounds(14, 119, 16, 122), 10);
  assert.equal(view.polygons.filter(p => p.map).length, 1);
  assert.equal(JSON.stringify(item.geojson), snapshot);
});

test('map labels become available after map initialization and switch hybrid imagery without labels', () => {
  const template = fs.readFileSync(path.join(__dirname, '../../resources/views/municipality_boundaries/index.blade.php'), 'utf8');
  assert.match(template, /<button\b[^>]*type="button"[^>]*id="toggleMunicipalityMapLabels"[^>]*aria-pressed="true"[^>]*disabled>Map labels: On<\/button>/);
  const view = workspace([boundary(1)], { initializeMap: false, mapId: 'configured-map-id' });
  const button = view.element('toggleMunicipalityMapLabels');
  assert.equal(button.disabled, true);
  view.initialize();
  const map = view.maps[0];
  assert.equal(map.config.mapId, 'configured-map-id');
  assert.equal(map.config.styles, undefined, 'A map ID must not be combined with local map styles');
  assert.equal(button.disabled, false);
  assert.equal(button.textContent, 'Map labels: On');
  assert.equal(button.attributes['aria-pressed'], 'true');
  button.dispatch('click');
  assert.equal(map.getMapTypeId(), 'satellite');
  assert.equal(button.textContent, 'Map labels: Off');
  assert.equal(button.attributes['aria-pressed'], 'false');
  button.dispatch('click');
  assert.equal(map.getMapTypeId(), 'hybrid');
  assert.equal(button.attributes['aria-pressed'], 'true');
});

test('native map type changes synchronize labels and restore the last labeled map type', () => {
  const view = workspace([boundary(1)]);
  const map = view.maps[0];
  const button = view.element('toggleMunicipalityMapLabels');
  for (const mapType of ['roadmap', 'terrain', 'hybrid']) {
    map.setMapTypeId(mapType);
    assert.equal(button.textContent, 'Map labels: On');
    button.dispatch('click');
    assert.equal(map.getMapTypeId(), 'satellite');
    button.dispatch('click');
    assert.equal(map.getMapTypeId(), mapType);
    map.setMapTypeId('satellite');
    assert.equal(button.attributes['aria-pressed'], 'false');
    button.dispatch('click');
    assert.equal(map.getMapTypeId(), mapType);
  }
  map.setMapTypeId('satellite');
  map.setMapTypeId('roadmap');
  assert.equal(button.attributes['aria-pressed'], 'true', 'A native selection updates the control immediately');
  assert.equal(map.getMapTypeId(), 'roadmap', 'Changing the base map must not override a native selection');
});

test('hiding base-map labels preserves camera, municipal labels, geometry and parcel overlays without requests', async () => {
  const item = boundary(1);
  const parcel = { id: 9, name: 'Parcel', area_ha: 1, color: '#123456', polygon: [{ lat: 15, lng: 120 }, { lat: 15.01, lng: 120 }, { lat: 15.01, lng: 120.01 }], farmer: { name: 'Farmer' } };
  const view = workspace([item]);
  view.flushFrames(); view.select(1);
  view.requests[0].respond(payload(item, [parcel]));
  await view.settle(); view.flushFrames();
  view.pan(bounds(14, 119, 16, 122), 11);
  const map = view.maps[0];
  const camera = { center: map.center, bounds: map.bounds, zoom: map.zoom, fits: map.fits.length };
  const polygons = view.polygons.slice();
  const labels = view.labels.slice();
  const visibility = view.polygons.map(polygon => polygon.map);
  const labelVisibility = view.labels.map(label => ({ map: label.map, visible: label.visible }));
  const geometry = JSON.stringify({ boundary: item.geojson, parcel: parcel.polygon });
  view.element('toggleMunicipalityMapLabels').dispatch('click');
  assert.deepEqual({ center: map.center, bounds: map.bounds, zoom: map.zoom, fits: map.fits.length }, camera);
  assert.deepEqual(view.polygons, polygons);
  assert.deepEqual(view.labels, labels);
  assert.deepEqual(view.polygons.map(polygon => polygon.map), visibility);
  assert.deepEqual(view.labels.map(label => ({ map: label.map, visible: label.visible })), labelVisibility);
  assert.equal(JSON.stringify({ boundary: item.geojson, parcel: parcel.polygon }), geometry);
  assert.equal(view.requests.length, 1, 'The display choice does not request or save operational records');
});

test('panning off screen detaches boundaries and returning reuses their polygons', () => {
  const view = workspace([boundary(1), boundary(2)]);
  view.flushFrames();
  const count = view.polygons.length;
  assert.equal(count, 2, 'Overview uses one interactive polygon per boundary');
  view.pan(bounds(20, 125, 21, 126));
  assert.equal(view.polygons.filter(item => item.map).length, 0);
  view.pan(bounds(14.9, 120, 15.1, 120.2));
  assert.equal(view.polygons.length, count, 'Returning to the same viewport must reuse retained map objects');
  assert.equal(view.polygons.filter(item => item.map).length, count);
});

test('municipality selection cancels pending overview batches', async () => {
  const items = Array.from({ length: 80 }, (_, index) => boundary(index + 1));
  const view = workspace(items);
  view.frame();
  assert.ok(view.frames.size > 0, 'A large overview yields between draw batches');
  view.select(80);
  assert.equal(view.requests.length, 1);
  view.requests[0].respond(payload(items[79]));
  await view.settle();
  view.flushFrames();
  const visible = view.polygons.filter(item => item.map);
  assert.equal(visible.length, 2, 'Stale overview batches cannot restore other municipalities');
  assert.ok(visible.every(item => item.paths[0][0].lng() === items[79].geojson.coordinates[0][0][0]));
});

test('search waits for typing to pause and aborts old requests without painting stale responses', async () => {
  const alpha = { ...boundary(1), municipality_name: 'Alpha' };
  const beta = { ...boundary(2), municipality_name: 'Beta' };
  const view = workspace([alpha, beta]);
  view.flushFrames();
  view.search('A'); view.advance(100); view.search('Al'); view.advance(100); view.search('Alpha');
  view.advance(249);
  assert.equal(view.requests.length, 0, 'Typing must not issue a request for every character');
  view.advance(1);
  assert.equal(view.requests.length, 1);
  view.search('Beta'); view.advance(250);
  assert.equal(view.requests.length, 2);
  assert.equal(view.requests[0].config.signal.aborted, true);
  view.requests[1].respond(payload(beta));
  await view.settle(); view.flushFrames();
  view.requests[0].respond(payload(alpha));
  await view.settle(); view.flushFrames();
  assert.equal(view.element('panelTitle').textContent, 'Beta');
  view.select(2);
  assert.equal(view.requests.length, 2, 'Reselecting the loaded municipality must not repeat the request');
});

test('fit covers source boundaries even when they have been culled from the viewport', () => {
  const first = boundary(1, 120, 15);
  const last = boundary(2, 123, 18);
  const view = workspace([first, last]);
  view.flushFrames();
  view.pan(bounds(14.9, 119.9, 15.1, 120.1));
  assert.equal(view.polygons.filter(item => item.map).length, 1);
  view.element('fitVisible').dispatch('click');
  const fit = view.maps[0].fits.at(-1).bounds;
  assert.equal(fit.south, 15); assert.equal(fit.west, 120);
  assert.equal(fit.north, 18.01); assert.equal(fit.east, 123.01);
});

test('selected workspace fit includes its boundary and outside parcels while preserving source geometry', async () => {
  const item = boundary(1, 120, 15);
  const original = JSON.stringify(item.geojson);
  const parcel = { id: 9, name: 'Outside parcel', area_ha: 1, color: '#123456', polygon: [{ lat: 16, lng: 122 }, { lat: 16.01, lng: 122 }, { lat: 16.01, lng: 122.01 }], farmer: { name: 'Farmer' } };
  const view = workspace([item]);
  view.flushFrames(); view.select(1);
  view.requests[0].respond(payload(item, [parcel]));
  await view.settle(); view.flushFrames();
  const fit = view.maps[0].fits.at(-1).bounds;
  assert.equal(fit.south, 15); assert.equal(fit.west, 120);
  assert.equal(fit.north, 16.01); assert.equal(fit.east, 122.01);
  assert.equal(JSON.stringify(item.geojson), original, 'Rendering must leave canonical coordinates untouched');
});

test('zoomed-out overview suppresses labels and restores them for close inspection', () => {
  const view = workspace([boundary(1)]);
  view.flushFrames();
  view.pan(bounds(14, 119, 16, 122), 7);
  assert.equal(view.labels.filter(item => item.map && item.visible !== false).length, 0);
  view.pan(bounds(14, 119, 16, 122), 11);
  assert.equal(view.labels.filter(item => item.map && item.visible !== false).length, 1);
});

test('saving an edit refreshes the same municipality and replaces stale cached colors', async () => {
  const item = boundary(1);
  const changed = { ...item, color: '#a855f7', updated_at: '2026-09-19T01:00:00Z', _record_version: 'changed-version' };
  const original = JSON.stringify(item.geojson);
  const view = workspace([item], { canManage: true, updateTemplate: '/boundaries/__ID__', csrf: 'local-test-token' });
  view.flushFrames(); view.select(1);
  view.requests[0].respond(payload(item));
  await view.settle(); view.flushFrames();

  view.element('panelContent').dispatch('click', {
    target: { closest: selector => selector === '[data-edit-boundary]' ? { dataset: { editBoundary: '1' } } : null },
  });
  view.element('editorColor').value = changed.color;
  view.element('saveBoundary').dispatch('click');
  assert.equal(view.requests[1].config.method, 'PUT');
  const saved = JSON.parse(view.requests[1].config.body);
  assert.equal(saved.color, changed.color);
  assert.equal(Object.hasOwn(saved, 'geojson'), false, 'A color change must not submit replacement geometry');
  view.requests[1].respond({ boundary: changed, message: 'Saved' });
  await view.settle();
  assert.equal(view.requests.length, 3, 'A successful save must refresh even though the same municipality is selected');
  view.requests[2].respond(payload(changed));
  await view.settle(); view.flushFrames();
  const visibleFills = view.polygons.filter(overlay => overlay.map && overlay.config.fillColor);
  assert.equal(visibleFills.length, 1);
  assert.equal(visibleFills[0].config.fillColor, changed.color.toUpperCase());
  assert.equal(JSON.stringify(item.geojson), original);
});

test('saved opacity survives culling and read-only users cannot change the appearance', () => {
  const view = workspace([{...boundary(1), fill_opacity: .65}]);
  view.flushFrames();
  view.pan(bounds(20, 125, 21, 126));
  view.element('geofenceOpacity').value = '0';
  view.element('geofenceOpacity').dispatch('input');
  view.pan(bounds(14.9, 120, 15.1, 120.2));
  const visibleFills = view.polygons.filter(overlay => overlay.map && overlay.config.fillColor);
  assert.equal(visibleFills.length, 1);
  assert.equal(visibleFills[0].config.fillOpacity, .65);
});

test('selecting another boundary in the same workspace cancels an open editor', async () => {
  const active = boundary(1);
  const draft = { ...boundary(2), municipality_id: 1, status: 'draft' };
  const view = workspace([active, draft], { canManage: true, updateTemplate: '/boundaries/__ID__', csrf: 'local-test-token' });
  view.flushFrames(); view.select(1);
  view.requests[0].respond({ ...payload(active), boundaries: [active, draft] });
  await view.settle(); view.flushFrames();
  view.element('panelContent').dispatch('click', {
    target: { closest: selector => selector === '[data-edit-boundary]' ? { dataset: { editBoundary: '1' } } : null },
  });
  const editable = view.polygons.find(overlay => overlay.config.editable);
  assert.ok(editable?.map);
  assert.equal(view.element('boundaryEditor').hidden, false);
  const draftShape = view.polygons.find(overlay => overlay.map && overlay.config.fillColor && overlay.paths[0][0].lng() === draft.geojson.coordinates[0][0][0]);
  assert.ok(draftShape);
  draftShape.trigger('click', { latLng: latLng({ lat: draft.centroid_lat, lng: draft.centroid_lng }) });
  assert.equal(view.element('boundaryEditor').hidden, true);
  assert.equal(editable.map, null, 'An editor must not retain geometry from a different selected boundary');
  view.element('saveBoundary').dispatch('click');
  assert.equal(view.requests.length, 1, 'Selecting within the workspace must not permit saving stale editor coordinates to another boundary');
});

async function editableWorkspace() {
  const item = boundary(1);
  const view = workspace([item], { canManage: true, updateTemplate: '/boundaries/__ID__', csrf: 'test' });
  view.flushFrames(); view.select(1);
  view.requests[0].respond(payload(item));
  await view.settle(); view.flushFrames();
  view.element('panelContent').dispatch('click', {
    target: { closest: selector => selector === '[data-edit-boundary]' ? { dataset: { editBoundary: '1' } } : null },
  });
  view.element('editorName').value = 'Reviewed name';
  return { view, item };
}

async function styleWorkspace() {
  const item = {...boundary(1), fill_opacity: .35};
  // Style-only saves must work even when the geometry editor cannot handle islands or holes.
  item.geojson = {type: 'MultiPolygon', coordinates: [item.geojson.coordinates]};
  const view = workspace([item, {...boundary(2), fill_opacity: .8}], {canManage: true, styleTemplate: '/boundaries/__ID__/style', csrf: 'test'});
  view.flushFrames(); view.select(1); view.requests[0].respond(payload(item));
  await view.settle(); view.flushFrames();
  return {view, item};
}

test('multipart style preview saves only color opacity and version; zero persists on fresh load', async () => {
  const {view, item} = await styleWorkspace();
  view.element('geofenceColor').value = '#ffffff'; view.element('geofenceColor').dispatch('input');
  view.element('geofenceOpacity').value = '0'; view.element('geofenceOpacity').dispatch('input');
  assert.match(view.element('geofenceStyleStatus').textContent, /Preview only/);
  assert.equal(view.polygons.filter(p => p.map && p.config.fillColor).at(-1).config.fillOpacity, 0);
  view.element('saveGeofenceStyle').dispatch('click'); view.element('saveGeofenceStyle').dispatch('click');
  assert.equal(view.requests.length, 2);
  assert.equal(view.requests[1].config.method, 'PATCH');
  assert.deepEqual(JSON.parse(view.requests[1].config.body), {color: '#FFFFFF', fill_opacity: 0, _record_version: item._record_version});
  const saved = {...item, color: '#FFFFFF', fill_opacity: 0, _record_version: 'new-version'};
  view.requests[1].respond({boundary: saved, message: 'Saved'}); await view.settle(); view.flushFrames();
  assert.equal(view.element('saveGeofenceStyle').disabled, true);
  assert.equal(view.element('geofenceOpacity').value, '0');
  assert.match(view.element('geofenceStyleStatus').textContent, /Saved appearance/);
  const reload = workspace([saved]); reload.flushFrames();
  assert.equal(reload.polygons.filter(p => p.config.fillColor)[0].config.fillOpacity, 0);
});

test('discarding preview restores stored appearance and does not change another boundary', async () => {
  const {view} = await styleWorkspace();
  view.element('geofenceOpacity').value = '90'; view.element('geofenceOpacity').dispatch('input');
  view.element('resetGeofenceStyle').dispatch('click');
  assert.equal(view.element('geofenceOpacity').value, '35');
  view.select(''); view.flushFrames();
  assert.equal(view.polygons.find(p => p.map && p.config.fillOpacity === .8).config.fillOpacity, .8);
  assert.equal(view.requests.length, 1);
});

test('style conflicts retain the preview and error, and save completion cannot switch municipalities', async () => {
  const {view, item} = await styleWorkspace();
  view.element('geofenceOpacity').value = '60'; view.element('geofenceOpacity').dispatch('input');
  view.element('saveGeofenceStyle').dispatch('click');
  view.requests[1].respond({errors: {_record_version: ['Another user changed this boundary. Reload first.']}}, 422);
  await view.settle();
  assert.equal(view.element('geofenceOpacity').value, '60');
  assert.equal(view.element('geofenceStyleError').hidden, false);
  assert.match(view.element('geofenceStyleError').textContent, /Another user/);
  view.element('saveGeofenceStyle').dispatch('click'); view.select(2);
  view.requests[3].respond(payload({...boundary(2), fill_opacity: .8})); await view.settle();
  view.requests[2].respond({boundary: {...item, fill_opacity: .6, _record_version: 'saved'}, message: 'Saved'}); await view.settle();
  assert.equal(view.element('geofenceOpacity').value, '80');
  assert.equal(view.element('geofenceStyleBoundary').value, '2');
});

test('geometry editing retains saved opacity, disables style controls and restores saved fill on cancel', async () => {
  const { view } = await editableWorkspace();
  const saved = view.polygons.find(overlay => overlay.map && overlay.config.fillColor && !overlay.config.editable);
  const editable = view.polygons.find(overlay => overlay.map && overlay.config.editable);
  assert.equal(saved.config.fillOpacity, 0, 'The saved fill must not blend with the color being previewed');
  for (const percentage of [0, 65, 100]) {
    view.element('geofenceOpacity').value = String(percentage);
    view.element('geofenceOpacity').dispatch('input');
    assert.equal(editable.config.fillOpacity, .2);
    assert.equal(saved.config.fillOpacity, 0);
  }
  view.pan(bounds(14, 119, 16, 122));
  assert.equal(saved.config.fillOpacity, 0, 'A camera refresh must not restore the underlying fill');
  view.element('cancelEditor').dispatch('click');
  assert.equal(editable.map, null);
  assert.equal(saved.config.fillOpacity, .2);
  assert.equal(view.requests.length, 1, 'Display changes must not write records');
});

test('new drawings use the persisted default without borrowing another boundary preview', () => {
  const view = workspace([], { canManage: true });
  view.element('startBoundary').dispatch('click');
  view.element('geofenceOpacity').value = '0';
  view.element('geofenceOpacity').dispatch('input');
  view.maps[0].trigger('click', { latLng: latLng({lat: 15, lng: 120}) });
  assert.equal(view.polygons.at(-1).config.fillOpacity, .2);
  view.element('geofenceOpacity').value = '100';
  view.element('geofenceOpacity').dispatch('change');
  assert.equal(view.polygons.at(-1).config.fillOpacity, .2);
  view.maps[0].trigger('click', { latLng: latLng({lat: 15.01, lng: 120.01}) });
  assert.equal(view.polygons.at(-1).config.fillOpacity, .2);
  assert.equal(view.requests.length, 0);
});

for (const status of [401, 419, 422]) {
  test(`failed save (${status}) retains edits, displays the error and permits retry`, async () => {
    const { view } = await editableWorkspace();
    view.element('saveBoundary').dispatch('click');
    assert.equal(view.element('saveBoundary').disabled, true);
    assert.equal(view.element('saveBoundary').textContent, 'Saving…');
    view.element('saveBoundary').dispatch('click');
    assert.equal(view.requests.length, 2, 'Repeated clicks must not send another write');
    view.requests[1].respond({ errors: { _record_version: ['This boundary was changed by another user. Reload it before saving.'] } }, status);
    await view.settle();
    assert.equal(view.element('boundaryEditor').hidden, false);
    assert.equal(view.element('editorName').value, 'Reviewed name');
    assert.equal(view.element('editorFeedback').hidden, false);
    assert.match(view.element('editorFeedback').textContent, status === 422 ? /another user/ : /session has expired/);
    assert.equal(view.element('saveBoundary').disabled, false);
    view.advance(6000);
    assert.equal(view.element('editorFeedback').hidden, false, 'Editor error persists after the toast expires');
  });
}

test('an HTML login response cannot masquerade as a successful save or close the editor', async () => {
  const { view } = await editableWorkspace();
  view.element('saveBoundary').dispatch('click');
  view.requests[1].respondHtml();
  await view.settle();
  assert.equal(view.element('boundaryEditor').hidden, false);
  assert.match(view.element('editorFeedback').textContent, /session has expired/);
  assert.equal(view.requests.length, 2);
});

test('a completed save cannot close a newly opened editor or switch the workspace back', async () => {
  const { view, item } = await editableWorkspace();
  view.element('saveBoundary').dispatch('click');
  view.element('cancelEditor').dispatch('click');
  view.element('startBoundary').dispatch('click');
  view.element('editorName').value = 'New unsaved draft';
  view.requests[1].respond({ boundary: item, message: 'Saved' });
  await view.settle();
  assert.equal(view.element('boundaryEditor').hidden, false);
  assert.equal(view.element('editorName').value, 'New unsaved draft');
  assert.equal(view.requests.length, 2);
});
