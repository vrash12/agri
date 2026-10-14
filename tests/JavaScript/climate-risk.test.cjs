const {test} = require('node:test');
const assert = require('node:assert/strict');
const climate = require('../../public/js/climate-risk.js');

test('rainfall shows real zero and never substitutes zero for missing days', () => {
  const daily = Array.from({length: 7}, (_, index) => ({date: `2026-10-${14 + index}`, precipitation_sum: 0}));
  assert.equal(climate.rainfallSummary({daily}).total, 0);
  daily[1].precipitation_sum = null;
  assert.equal(climate.rainfallSummary({daily}).total, null);
  assert.equal(climate.rainfallSummary({daily: daily.slice(0, 6)}).complete, false);
  daily[1].precipitation_sum = 5.2;
  assert.equal(climate.rainfallSummary({daily}).total, 5.2);
  daily[2].precipitation_sum = NaN;
  assert.equal(climate.rainfallSummary({daily}).complete, false);
});

function network(response) {
  let calls = 0, clears = 0;
  return {AbortController, setTimeout: () => 1, clearTimeout: () => {clears++;},
    fetch: async (url, options) => {calls++; assert.equal(options.credentials, 'same-origin'); return response;},
    count: () => calls, clears: () => clears};
}

test('expired sessions, denial, HTTP limits and HTML responses produce safe messages without retries', async () => {
  for (const status of [401, 403, 419, 429, 500]) {
    const win = network({ok: false, status, text: () => 'private backend error'});
    await assert.rejects(climate.request(win, '/climate-risk/map'), error => !error.message.includes('private backend error'));
    assert.equal(win.count(), 1); assert.equal(win.clears(), 1);
  }
  const win = network({ok: true, headers: {get: () => 'text/html'}});
  await assert.rejects(climate.request(win, '/climate-risk/map'), /Sign in again/);
  assert.equal(win.count(), 1);
});

test('accepted dashboard requests return JSON once and clear their deadline', async () => {
  const data = {type: 'FeatureCollection', features: []};
  const win = network({ok: true, headers: {get: () => 'application/json'}, json: async () => data});
  assert.equal(await climate.request(win, '/climate-risk/map'), data);
  assert.equal(win.count(), 1); assert.equal(win.clears(), 1);
});

function element(tag = 'div', text = '') {
  return {tag, textContent: text, children: [], hidden: false, disabled: false, attributes: {}, events: {},
    append(...nodes) { this.children.push(...nodes); }, replaceChildren(...nodes) { this.children = nodes; },
    setAttribute(name, value) { this.attributes[name] = value; }, removeAttribute(name) { delete this.attributes[name]; },
    addEventListener(name, callback) { this.events[name] = callback; }};
}

function screen(payload) {
  const ids = {climateMapStatus: element(), climateMap: element(), loadClimateMap: element('button', 'Open risk map'),
    climateRainfall: element(), loadClimateRainfall: element('button', 'Load forecast')};
  const features = [], metrics = {maps: 0, requests: 0, geometry: 0, clears: 0};
  const doc = {getElementById: id => ids[id], createElement: element, querySelector: () => null};
  const data = {addListener() {}, addGeoJson(feature) {
    metrics.geometry++; features.push({getGeometry: () => ({forEachLatLng: callback => callback({lat: 15, lng: 120})}),
      getProperty: key => feature.properties[key], getId: () => feature.id});
  }, forEach: callback => [...features].forEach(callback), remove: feature => {features.splice(features.indexOf(feature), 1); metrics.clears++;}, setStyle() {}};
  class FakeMap { constructor() { metrics.maps++; this.data = data; } fitBounds() {} }
  class FakeBounds {constructor() { this.points = []; } extend(p) { this.points.push(p); } isEmpty() { return this.points.length === 0; }}
  const win = {AbortController, setTimeout: () => 1, clearTimeout() {}, google: {maps: {Map: FakeMap, LatLngBounds: FakeBounds,
    InfoWindow: class {close() {}}, importLibrary: async () => ({})}},
    fetch: async () => {metrics.requests++; return {ok: true, headers: {get: () => 'application/json'}, json: async () => payload};}};
  return {doc, win, ids, metrics};
}

test('map loads once per click, reuses its instance and clears old overlays on explicit reload', async () => {
  const s = screen({features: [{id: 1, geometry: {type: 'Polygon'}, properties: {name: 'Synthetic municipality', level: 'unassessed'}}], parcels: [], total: 1});
  climate.mount(s.doc, s.win, {mapUrl: '/climate-risk/map', areaUrl: '/climate-risk'});
  const pending = s.ids.loadClimateMap.events.click();
  assert.equal(s.ids.loadClimateMap.disabled, true);
  await s.ids.loadClimateMap.events.click();
  assert.equal(s.metrics.requests, 1, 'A second click cannot create an in-flight duplicate');
  await pending;
  assert.equal(s.ids.loadClimateMap.disabled, false);
  assert.equal(s.ids.loadClimateMap.textContent, 'Reload map');
  assert.match(s.ids.climateMapStatus.textContent, /1 of 1 municipalities/);
  await s.ids.loadClimateMap.events.click();
  assert.equal(s.metrics.maps, 1); assert.equal(s.metrics.requests, 2); assert.equal(s.metrics.clears, 1);
});

test('an empty risk filter avoids Maps initialization and retains an honest empty message', async () => {
  const s = screen({features: [], parcels: [], total: 0});
  climate.mount(s.doc, s.win, {mapUrl: '/climate-risk/map', areaUrl: '/climate-risk'});
  await s.ids.loadClimateMap.events.click();
  assert.equal(s.metrics.maps, 0); assert.equal(s.ids.climateMap.hidden, true);
  assert.equal(s.ids.climateMapStatus.textContent, 'No municipalities match this risk filter.');
});

test('rainfall controls preserve incomplete data and never infer a drought level', async () => {
  const s = screen({available: true, fetched_at: '2026-10-14T08:00:00Z', daily: [{date: '2026-10-14', precipitation_sum: null}]});
  climate.mount(s.doc, s.win, {rainfallUrl: '/climate-risk/rainfall'});
  await s.ids.loadClimateRainfall.events.click();
  assert.equal(s.ids.climateRainfall.children[0].textContent, 'Rainfall data incomplete');
  assert.equal(s.metrics.requests, 1); assert.equal(s.metrics.maps, 0);
  assert.equal(s.ids.loadClimateRainfall.disabled, false);
});
