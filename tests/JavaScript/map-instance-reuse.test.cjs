const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/reusable-google-map.js', 'utf8');

function harness() {
  class Element {
    constructor() {this.style = {}; this.children = []; this.parent = null;}
    replaceChildren(child) {for(const old of this.children) old.parent = null; child.remove(); this.children = [child]; child.parent = this;}
    remove() {if(this.parent) this.parent.children = this.parent.children.filter(c => c !== this); this.parent = null;}
  }
  const created = [], events = [], resized = [];
  class GoogleMap {
    constructor(canvas, options) {this.canvas = canvas; this.options = options; created.push(this);}
    setOptions(options) {this.options = options;}
  }
  const window = {document:{createElement:() => new Element()},
    google:{maps:{Map:GoogleMap, event:{trigger:(map, name) => resized.push({map, name})}}},
    location:{href:'https://office.example/farmers', origin:'https://office.example'},
    __mapUsage:{url:'/usage/map-events', token:'synthetic-csrf'},
    fetch:async (url, options) => {events.push(JSON.parse(options.body)); return {};}};
  vm.runInNewContext(source, {window, URL, Map});
  return {pool:window.ReusableGoogleMap, Element, created, events, resized, window};
}

test('crop map reuses the actual canvas and instance across new modal forms', () => {
  const h = harness(), firstHost = new h.Element(), secondHost = new h.Element();
  const first = h.pool.acquire('crop-editor-2d', firstHost, {center:{lat:15,lng:120}});
  assert.equal(h.created.length, 1);
  assert.throws(() => h.pool.acquire('crop-editor-2d', secondHost, {}), /already in use/);
  const canvas = firstHost.children[0];
  first.release(); assert.equal(firstHost.children.length, 0);
  const second = h.pool.acquire('crop-editor-2d', secondHost, {center:{lat:16,lng:121}});
  assert.equal(h.created.length, 1);
  assert.equal(second.map, first.map);
  assert.equal(secondHost.children[0], canvas);
  assert.equal(second.map.options.center.lat, 16);
  first.release(); assert.equal(secondHost.children[0], canvas, 'An old release cannot detach the active canvas');
  assert.equal(h.resized.length, 1);
  assert.deepEqual(h.events, [{map:'crop-editor-2d',event:'created'}, {map:'crop-editor-2d',event:'reused'}]);
  second.release();
});

test('telemetry cannot send to another origin or break mapping when unavailable', () => {
  const h = harness(); h.window.__mapUsage.url = 'https://different.example/collect';
  h.pool.report('farmers-3d', 'created'); assert.equal(h.events.length, 0);
  h.window.__mapUsage.url = '/usage/map-events'; h.window.fetch = () => {throw new Error('offline');};
  assert.doesNotThrow(() => h.pool.acquire('crop-editor-2d', new h.Element(), {}));
});

test('parcel map starts on visibility or explicit selection and keeps one startup promise', async () => {
  const listeners = {}, callbacks = {}, observers = []; let initializations = 0;
  const workspace = {open:true, addEventListener:(event, fn) => {callbacks[event] = fn;}};
  const document = {addEventListener:(name, fn) => {listeners[name] = fn;},
    getElementById:id => id === 'farmerMapWorkspace' ? workspace : id === 'farmersMap' ? {} : null};
  const window = {document, IntersectionObserver:class {
    constructor(fn) {this.notify = fn; observers.push(this);}
    observe() {} disconnect() {this.disconnected = true;}
  }};
  const context = vm.createContext({window, document, URLSearchParams, console, setTimeout, clearTimeout});
  vm.runInContext(fs.readFileSync('public/js/farmers-maps.js', 'utf8'), context);
  context.initFarmersMap3D = async () => {initializations++;};
  listeners.DOMContentLoaded();
  callbacks.toggle();
  assert.equal(initializations, 0, 'An open disclosure below the viewport must not load Google Maps');
  observers[0].notify([{isIntersecting:true}]);
  const first = window.__startFarmerMap();
  await first;
  assert.equal(window.__startFarmerMap(), first);
  workspace.open = false; callbacks.toggle(); workspace.open = true; callbacks.toggle();
  assert.equal(initializations, 1);
  assert.equal(observers[0].disconnected, true);
});
