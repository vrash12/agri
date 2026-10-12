const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/planted-area-editor.js'), 'utf8');

function editor(saved = [], scoped = false) {
  class Events {
    constructor() { this.listeners = {}; }
    addListener(name, fn) { (this.listeners[name] ??= []).push(fn); }
    fire(name, event = {}) { for (const fn of this.listeners[name] || []) fn(event); }
  }
  class Element extends Events {
    constructor() { super(); this.children = []; this.value = ''; this.textContent = ''; this.style = {}; }
    addEventListener(name, fn) { this.addListener(name, fn); }
    append(...children) { this.children.push(...children); }
    replaceChildren() { this.children = []; }
    setAttribute() {}
  }
  class MVCArray extends Events {
    constructor(points = []) { super(); this.points = points; }
    getLength() { return this.points.length; }
    getArray() { return this.points; }
    push(point) { this.points.push(point); this.fire('insert_at'); }
    pop() { const p = this.points.pop(); this.fire('remove_at'); return p; }
  }
  const latLng = p => typeof p.lat === 'function' ? p : { lat: () => p.lat, lng: () => p.lng };
  const polygons = []; let map;
  class Polygon extends Events {
    constructor(opts) {
      super(); this.opts = opts;
      // Match the reported provider behavior: paths: [] has no first ring.
      this.path = opts.paths?.length ? new MVCArray(opts.paths.map(latLng)) : undefined;
      polygons.push(this);
    }
    getPath() { return this.path; }
    setPath(points) { this.path = points instanceof MVCArray ? points : new MVCArray(points.map(latLng)); }
    setMap(value) { this.map = value; }
    setEditable() {}
    setOptions() {}
  }
  class Polyline extends Polygon {
    constructor(opts) { super({...opts, paths: opts.path || []}); }
  }
  class Map extends Events {
    constructor() { super(); map = this; }
    fitBounds() {}
    getCenter() { return latLng({ lat: 15.6005, lng: 120.4705 }); }
  }
  class LatLngBounds { extend() {} }
  const elements = {};
  const byId = id => elements[id] ??= new Element();
  if (scoped) {
    const root = new Element();
    root.querySelector = selector => selector === '#plantedSaveState' ? null : byId(selector.slice(1));
    elements.plantedAreaEditor = root;
  }
  byId('plantedAreaConfig').textContent = JSON.stringify({
    canDraw: true, areas: saved, crops: { corn: 'Corn', rice: 'Rice' }, colors: { corn: '#D4A017', rice: '#228B22' },
    polygon: [{ lat: 15.6, lng: 120.47 }, { lat: 15.6, lng: 120.471 }, { lat: 15.601, lng: 120.471 }, { lat: 15.601, lng: 120.47 }]
  });
  byId('plantedCrop').value = 'corn';
  const form = new Element(); byId('plantedAreasInput').form = form;
  const maps = { Map, Polygon, Polyline, MVCArray, LatLngBounds };
  vm.runInNewContext(source, { document: { getElementById: byId, createElement: () => new Element() },
    google: { maps }, window: { google: { maps }, matchMedia: () => ({ matches: true }) }, confirm: () => true, setTimeout });
  return { byId, polygons, form, click: id => byId(id).fire('click'),
    point: (lat, lng) => map.fire('click', { latLng: latLng({ lat, lng }) }),
    areas: () => JSON.parse(byId('plantedAreasInput').value) };
}

test('freehand crop line starts, collects points, finishes and serializes for save', () => {
  const e = editor();
  e.click('plantedStart');
  assert.equal(e.byId('plantedFinish').disabled, true);
  assert.equal(e.byId('plantedUndo').disabled, true);
  assert.equal(e.polygons.at(-1).getPath().getLength(), 0);
  e.point(15.6, 120.47); e.point(15.6005, 120.4705);
  assert.equal(e.byId('plantedFinish').disabled, false);
  e.click('plantedFinish');
  assert.equal(e.areas().length, 1); assert.equal(e.areas()[0].type, 'line'); assert.equal(e.areas()[0].path.length, 2);
  let prevented = false; e.form.fire('submit', { preventDefault: () => { prevented = true; } });
  assert.equal(prevented, false); assert.equal(e.areas()[0].crop, 'corn');
});

test('undo, outside point refusal, cancel and restart retain recorded sections', () => {
  const saved = [{ crop: 'rice', polygon: [{ lat: 15.6, lng: 120.47 }, { lat: 15.6, lng: 120.4704 }, { lat: 15.6004, lng: 120.47 }] }];
  const e = editor(saved); e.click('plantedStart'); e.click('plantedCenter');
  assert.equal(e.polygons.at(-1).getPath().getLength(), 1);
  e.click('plantedUndo'); assert.equal(e.polygons.at(-1).getPath().getLength(), 0);
  e.point(16, 121); assert.match(e.byId('plantedMapStatus').textContent, /outside/);
  e.click('plantedCancel'); assert.deepEqual(e.areas(), saved);
  e.click('plantedStart'); assert.equal(e.polygons.at(-1).getPath().getLength(), 0);
  let prevented = false; e.form.fire('submit', { preventDefault: () => { prevented = true; } });
  assert.equal(prevented, true); assert.deepEqual(e.areas(), saved);
});

test('crop line point limit and two-point finish guard work with a real empty path', () => {
  const e = editor(); e.click('plantedStart'); e.click('plantedFinish'); assert.equal(e.areas().length, 0);
  for (let i = 0; i < 51; i++) e.click('plantedCenter');
  assert.equal(e.polygons.at(-1).getPath().getLength(), 50);
  assert.match(e.byId('plantedMapStatus').textContent, /50 points/);
});

test('controls follow drawing state and the whole-parcel crop follows finished sections', () => {
  const e = editor();
  assert.equal(e.byId('plantedDraftActions').hidden, true);
  e.click('plantedStart');
  assert.equal(e.byId('plantedIdleActions').hidden, true);
  assert.equal(e.byId('plantedCrop').disabled, true);
  assert.match(e.byId('plantedSaveState').textContent, /Finish or cancel/);
  e.point(15.6, 120.47); e.point(15.6, 120.4705);
  e.click('plantedFinish');
  assert.equal(e.byId('plantedDraftActions').hidden, true);
  assert.equal(e.byId('plantedUpdate').hidden, false);
  assert.equal(e.byId('plantedCrop').disabled, false);
  assert.equal(e.byId('crop').value, 'corn');
  assert.equal(e.byId('plantedAreaCount').textContent, '1/8');
  assert.match(e.byId('plantedSaveState').textContent, /Unsaved changes/);
});

test('modal-scoped editor can update save status outside its editor root', () => {
  const e = editor([], true);
  e.click('plantedStart');
  assert.match(e.byId('plantedSaveState').textContent, /Finish or cancel/);
});
