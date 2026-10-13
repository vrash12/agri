const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/planted-area-editor.js'), 'utf8');

function editor(saved = [], scoped = false, shading = false, harvestUrl = null) {
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
    canDraw: true, areas: saved, harvestUrl, crops: { corn: 'Corn', rice: 'Rice' }, colors: { corn: '#D4A017', rice: '#228B22' },
    polygon: [{ lat: 15.6, lng: 120.47 }, { lat: 15.6, lng: 120.471 }, { lat: 15.601, lng: 120.471 }, { lat: 15.601, lng: 120.47 }]
  });
  byId('plantedCrop').value = 'corn';
  const form = new Element(); byId('plantedAreasInput').form = form;
  const maps = { Map, Polygon, Polyline, MVCArray, LatLngBounds };
  vm.runInNewContext(source, { document: { getElementById: byId, createElement: () => new Element() },
    google: { maps }, window: { google: { maps }, CropAreaBadges:shading ? require('../../public/js/crop-area-badges.js') : null, matchMedia: () => ({ matches: true }) }, confirm: () => true, setTimeout });
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

test('enclosed crop marks shade the editor while saved data stays a line',()=>{
  const saved=[{type:'line',crop:'rice',path:[{lat:15.6002,lng:120.4702},{lat:15.6002,lng:120.4708},{lat:15.6008,lng:120.4708},{lat:15.6008,lng:120.4702}]}];
  const e=editor(saved,false,true);
  const fill=e.polygons.find(p=>p.opts.zIndex===4);
  assert.equal(fill.opts.fillOpacity,.38);assert.equal(fill.opts.fillColor,'#228B22');
  assert.deepEqual(e.areas(),saved);
  const line=e.polygons.find(p=>p instanceof Object && p.opts.zIndex===5);
  line.path.points[1]={lat:()=>15.6004,lng:()=>120.4706};
  line.path.fire('set_at');
  assert.equal(fill.map,null,'Editing removes the previous shaded overlay');
  assert.equal(e.polygons.filter(p=>p.opts.zIndex===4).length,2);
  assert.equal(e.areas()[0].type,'line');assert.equal(e.areas()[0].area_ha,undefined);
});

test('overlapping crop enclosure cannot finish, while adjacent enclosures can',()=>{
  const saved=[{type:'line',crop:'rice',path:[{lat:15.6,lng:120.47},{lat:15.6,lng:120.4705},{lat:15.601,lng:120.4705},{lat:15.601,lng:120.47}]}];
  const e=editor(saved,false,true);
  e.click('plantedStart');
  [[15.6001,120.4704],[15.6001,120.4709],[15.6009,120.4709],[15.6009,120.4704]].forEach(p=>e.point(...p));
  e.click('plantedFinish');assert.deepEqual(e.areas(),saved);
  assert.match(e.byId('plantedMapStatus').textContent,/cover the same land/);
  assert.equal(e.byId('plantedDraftActions').hidden,false,'Keep the draft available for undo or cancel');
  e.click('plantedCancel');e.click('plantedStart');
  [[15.6,120.4705],[15.6,120.471],[15.601,120.471],[15.601,120.4705]].forEach(p=>e.point(...p));
  e.click('plantedFinish');assert.equal(e.areas().length,2);
  const accepted=e.areas();
  const line=e.polygons.filter(p=>p.opts.zIndex===5 && p.map!==null).at(-1);
  line.path.points[0]={lat:()=>15.6,lng:()=>120.4704};line.path.fire('set_at');
  assert.deepEqual(e.areas(),accepted,'A conflicting drag restores the last accepted points');
});

test('existing overlapping sections show outlines and cannot save unchanged',()=>{
  const path=[{lat:15.6001,lng:120.4701},{lat:15.6001,lng:120.4709},{lat:15.6009,lng:120.4709},{lat:15.6009,lng:120.4701}];
  const e=editor([{type:'line',crop:'rice',path},{crop:'corn',polygon:path}],false,true);
  assert.equal(e.polygons.some(p=>p.opts.zIndex===4),false);
  assert.equal(e.polygons.find(p=>p.opts.zIndex===5 && p.opts.fillColor).opts.fillOpacity,0);
  let prevented=false;e.form.fire('submit',{preventDefault(){prevented=true;}});
  assert.equal(prevented,true);assert.match(e.byId('plantedMapStatus').textContent,/cover the same land/);
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

test('saved crop sections offer a keyed direct harvest link', () => {
  const key = '11111111-1111-4111-8111-111111111111';
  const e = editor([{ area_key: key, crop: 'corn', type: 'line', path: [{ lat: 15.6, lng: 120.47 }, { lat: 15.6005, lng: 120.4705 }] }], false, false, '/harvest-records/create?parcel_crop_season_id=4');
  const actions = e.byId('plantedAreaList').children[0].children[2];
  const link = actions.children.find(child => child.textContent === 'Record harvest');
  assert.equal(link.href, `/harvest-records/create?parcel_crop_season_id=4&planted_area_key=${encodeURIComponent(key)}`);
});
