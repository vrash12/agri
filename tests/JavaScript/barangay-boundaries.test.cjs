const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/barangay-boundaries.js'), 'utf8');
const data = JSON.parse(fs.readFileSync(path.join(__dirname, '../../database/seeders/data/ramos_barangay_reference_boundaries.geojson'), 'utf8'));
const payload = {municipality_id: 4, available: true, ...data, source: {label: 'Test source', url: 'https://example.test', note: 'Planning reference'}};

function setup() {
  const nodes = new Map(), requests = [], overlays = [], timers = new Map();
  let timerId = 0;
  function element(id) {
    if (!nodes.has(id)) nodes.set(id, {
      value: '', hidden: false, disabled: true, checked: true, textContent: '', children: [], listeners: {}, attrs: {},
      addEventListener(type, fn) { this.listeners[type] = fn; },
      fire(type) { return this.listeners[type]?.(); },
      add(option) { this.children.push(option); },
      append(...children) { this.children.push(...children); },
      replaceChildren(...children) { this.children = children; this.value = ''; },
      setAttribute(key, val) { this.attrs[key] = val; },
    });
    return nodes.get(id);
  }
  class Overlay {
    constructor(config) { this.config = {...config}; this.map = config.map; this.listeners = {}; overlays.push(this); }
    setMap(map) { this.map = map; }
    setVisible(value) { this.visible = value; }
    setOptions(options) { Object.assign(this.config, options); }
    addListener(type, fn) { this.listeners[type] = fn; }
  }
  class Bounds {
    constructor() { this.points = []; }
    extend(point) { this.points.push(point); }
    union(other) { this.points.push(...other.points); }
    isEmpty() { return !this.points.length; }
  }
  class Info {
    close() {} setContent() {} setPosition() {} open() {}
  }
  const map = {zoom: 13, listeners: {}, fits: [], addListener(type, fn) { this.listeners[type] = fn; }, getZoom() { return this.zoom; }, fitBounds(bounds) { this.fits.push(bounds); }};
  const context = {window: {}, document: {getElementById: element, createElement: () => element(Symbol())},
    Option: function(text, value) { this.text = text; this.value = value; }, AbortController,
    setTimeout(fn) { timers.set(++timerId, fn); return timerId; }, clearTimeout(id) { timers.delete(id); },
    google: {maps: {InfoWindow: Info, Polygon: Overlay, Marker: Overlay, LatLngBounds: Bounds, SymbolPath: {CIRCLE: 0}, event: {clearInstanceListeners(overlay) { overlay.listeners = {}; }}}},
  };
  vm.runInNewContext(source, context);
  const layer = context.window.createBarangayBoundaryLayer({map, url: '/barangays', municipalityIds: [4],
    request(url, options) { return new Promise((resolve, reject) => requests.push({url, options, resolve, reject})); },
  });
  return {layer, element, requests, overlays, map, timers};
}

test('only supported municipality loads geometry, nine overlays can be focused and cached', async () => {
  const w = setup();
  await w.layer.selectMunicipality('');
  await w.layer.selectMunicipality(5);
  assert.equal(w.requests.length, 0);
  assert.equal(w.element('showBarangays').disabled, true);
  const pending = w.layer.selectMunicipality(4);
  assert.equal(w.requests[0].url, '/barangays?municipality_id=4');
  w.requests[0].resolve(payload); await pending;
  assert.equal(w.overlays.filter(o => o.map).length, 27);
  assert.equal(w.element('barangaySelect').children.length, 10);
  w.element('barangaySelect').value = '0306912004';
  w.element('barangaySelect').fire('change');
  w.element('focusBarangay').fire('click');
  assert.equal(w.map.fits[0].points.length, payload.features[3].geometry.coordinates[0].length);
  w.element('showBarangays').checked = false;
  await w.element('showBarangays').fire('change');
  assert.equal(w.overlays.filter(o => o.map).length, 0);
  w.element('showBarangays').checked = true;
  await w.element('showBarangays').fire('change');
  assert.equal(w.requests.length, 1);
  assert.equal(w.overlays.filter(o => o.map).length, 27);
});

test('switching municipality discards late geometry and removes all previous overlays', async () => {
  const w = setup();
  const pending = w.layer.selectMunicipality(4);
  await w.layer.selectMunicipality(5);
  assert.equal(w.requests[0].options.signal.aborted, true);
  w.requests[0].resolve(payload); await pending;
  assert.equal(w.overlays.length, 0);
  const second = w.layer.selectMunicipality(4);
  w.requests[1].resolve(payload); await second;
  await w.layer.selectMunicipality(5);
  assert.equal(w.overlays.filter(o => o.map).length, 0);
  assert.equal(w.element('barangaySelect').disabled, true);
});

test('turning the layer off during loading cannot draw a late response', async () => {
  const w = setup();
  const pending = w.layer.selectMunicipality(4);
  w.element('showBarangays').checked = false;
  await w.element('showBarangays').fire('change');
  w.requests[0].resolve(payload); await pending;
  assert.equal(w.overlays.length, 0);
  assert.equal(w.element('barangayControls').attrs['aria-busy'], 'false');
});

test('errors preserve a working retry and reject geometry for a different municipality', async () => {
  const w = setup();
  const first = w.layer.selectMunicipality(4);
  w.requests[0].reject(new Error('Sign in again.')); await first;
  assert.equal(w.element('barangayStatus').textContent, 'Sign in again.');
  assert.equal(w.element('retryBarangays').hidden, false);
  const retry = w.element('retryBarangays').fire('click');
  w.requests[1].resolve({...payload, municipality_id: 5}); await retry;
  assert.equal(w.overlays.length, 0);
  assert.equal(w.element('retryBarangays').hidden, false);
  const last = w.element('retryBarangays').fire('click');
  w.requests[2].resolve(payload); await last;
  assert.equal(w.element('retryBarangays').hidden, true);
});

test('editing hides read-only polygons even if their request finishes while editing', async () => {
  const w = setup();
  const pending = w.layer.selectMunicipality(4);
  w.layer.setEditing(true);
  w.requests[0].resolve(payload); await pending;
  assert.equal(w.overlays.filter(o => o.map).length, 0);
  assert.equal(w.element('showBarangays').disabled, true);
  w.layer.setEditing(false);
  assert.equal(w.overlays.filter(o => o.map).length, 27);
  assert.equal(w.element('focusBarangay').disabled, false);
});
