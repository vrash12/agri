const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/planted-area-editor.js'), 'utf8');

function editor(saved = []) {
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
  class Map extends Events {
    constructor() { super(); map = this; }
    fitBounds() {}
    getCenter() { return latLng({ lat: 15.6005, lng: 120.4705 }); }
  }
  class LatLngBounds { extend() {} }
  const elements = {};
  const byId = id => elements[id] ??= new Element();
  byId('plantedAreaConfig').textContent = JSON.stringify({
    canDraw: true, areas: saved, crops: { corn: 'Corn', rice: 'Rice' }, colors: { corn: '#D4A017', rice: '#228B22' },
    polygon: [{ lat: 15.6, lng: 120.47 }, { lat: 15.6, lng: 120.471 }, { lat: 15.601, lng: 120.471 }, { lat: 15.601, lng: 120.47 }]
  });
  byId('plantedCrop').value = 'corn';
  const form = new Element(); byId('plantedAreasInput').form = form;
  const maps = { Map, Polygon, MVCArray, LatLngBounds };
  vm.runInNewContext(source, { document: { getElementById: byId, createElement: () => new Element() },
    google: { maps }, window: { google: { maps }, matchMedia: () => ({ matches: true }) }, confirm: () => true, setTimeout });
  return { byId, polygons, form, click: id => byId(id).fire('click'),
    point: (lat, lng) => map.fire('click', { latLng: latLng({ lat, lng }) }),
    areas: () => JSON.parse(byId('plantedAreasInput').value) };
}

test('empty-ring draft starts, collects corners, finishes and serializes for save', () => {
  const e = editor();
  e.click('plantedStart');
  assert.equal(e.byId('plantedFinish').disabled, true);
  assert.equal(e.byId('plantedUndo').disabled, true);
  assert.equal(e.polygons.at(-1).getPath().getLength(), 0);
  e.point(15.6, 120.47); e.point(15.6, 120.4705); e.point(15.6005, 120.47);
  assert.equal(e.byId('plantedFinish').disabled, false);
  e.click('plantedFinish');
  assert.equal(e.areas().length, 1); assert.equal(e.areas()[0].polygon.length, 3);
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

test('draft point limit and three-corner finish guard work with a real empty ring', () => {
  const e = editor(); e.click('plantedStart'); e.click('plantedFinish'); assert.equal(e.areas().length, 0);
  for (let i = 0; i < 51; i++) e.click('plantedCenter');
  assert.equal(e.polygons.at(-1).getPath().getLength(), 50);
  assert.match(e.byId('plantedMapStatus').textContent, /50 corners/);
});