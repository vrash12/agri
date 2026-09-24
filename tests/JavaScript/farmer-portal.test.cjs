const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function mapHarness(response) {
  const events = {}, status = {}, canvas = { hidden: true }, button = { addEventListener: (name, fn) => { events[name] = fn; } };
  const calls = { fetch: 0, maps: 0, polygons: [] };
  const root = { dataset: { geometryUrl: '/farmer-portal/parcels/1/geometry', mapsKey: '' },
    querySelector: selector => ({ '[data-load-map]': button, '[data-map-status]': status, '[data-map-canvas]': canvas })[selector] };
  const google = { maps: {
    Map: class { constructor() { calls.maps++; } fitBounds() {} },
    Polygon: class { constructor(options) { calls.polygons.push(options); } },
    LatLngBounds: class { extend() {} },
  } };
  const context = { document: { querySelector: () => root }, window: { google }, google, URL, AbortController,
    setTimeout: () => 1, clearTimeout() {}, fetch: async () => { calls.fetch++; return response(); } };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/farmer-portal-map.js'), 'utf8'), context);
  return { events, calls, status, canvas, button };
}

test('parcel maps request geometry only on demand and render an uneditable boundary', async () => {
  const h = mapHarness(() => ({ ok: true, json: async () => ({ plot: { paths: [[{ lat: 15, lng: 120 }]], color: '#236344' } }) }));
  assert.equal(h.calls.fetch, 0);
  await h.events.click();
  assert.equal(h.calls.fetch, 1);
  assert.equal(h.calls.maps, 1);
  assert.equal(h.calls.polygons[0].editable, false);
  assert.equal(h.canvas.hidden, false);
  assert.equal(h.button.hidden, true);
});

test('expired private geometry requests never render a map and explain sign-in', async () => {
  const h = mapHarness(() => ({ ok: false, status: 401, json: async () => ({}) }));
  await h.events.click();
  assert.equal(h.calls.maps, 0);
  assert.equal(h.canvas.hidden, true);
  assert.match(h.status.textContent, /Sign in again/);
  assert.equal(h.button.disabled, false);
});

test('network timeouts permit a later retry without a partial parcel map', async () => {
  let fail = true;
  const h = mapHarness(() => {
    if (fail) { const error = new Error(); error.name = 'AbortError'; throw error; }
    return { ok: true, json: async () => ({ plot: { paths: [[{ lat: 15, lng: 120 }]], color: '#236344' } }) };
  });
  await h.events.click();
  assert.match(h.status.textContent, /too long/);
  assert.equal(h.calls.maps, 0);
  fail = false;
  await h.events.click();
  assert.equal(h.calls.maps, 1);
});

test('malformed geometry responses show a helpful retry message without raw errors', async () => {
  const h = mapHarness(() => ({ ok: true, json: async () => { throw new SyntaxError('PRIVATE RESPONSE CONTENT'); } }));
  await h.events.click();
  assert.match(h.status.textContent, /Check your connection and try again/);
  assert.doesNotMatch(h.status.textContent, /PRIVATE|SyntaxError/);
  assert.equal(h.canvas.hidden, true);
  assert.equal(h.button.disabled, false);
  assert.equal(h.calls.maps, 0);
});

test('failed HTTP responses never parse or display provider error content', async () => {
  for (const status of [401, 404, 422, 429, 500]) {
    let parsed = false;
    const h = mapHarness(() => ({ ok: false, status, json: async () => { parsed = true; return { message: 'PRIVATE SERVER DETAIL' }; } }));
    await h.events.click();
    assert.equal(parsed, false);
    assert.doesNotMatch(h.status.textContent, /PRIVATE/);
    assert.equal(h.button.disabled, false);
    assert.equal(h.canvas.hidden, true);
  }
});

function collectionHarness(respond, mapsAvailable = true) {
  const calls = { requests: [], maps: 0, polygons: 0, fits: 0, removed: 0 };
  const makeElement = () => ({ hidden: false, disabled: false, textContent: '', value: '', events: {}, appendChild() {},
    addEventListener(name, fn) { this.events[name] = fn; } });
  const select = makeElement(), detail = makeElement(), detailName = makeElement(), detailArea = makeElement(), crops = makeElement(), warning = makeElement(), fit = makeElement(), retry = makeElement(), canvas = makeElement(), status = makeElement(), records = makeElement();
  records.open = false;
  const root = { dataset: { collectionUrl: '/farmer-portal/parcels/geometry', mapsKey: '', cropYear: '2026' },
    querySelector: selector => ({ '[data-map-canvas]': canvas, '[data-map-status]': status, '[data-map-parcel-select]': select, '[data-map-detail]': detail,
      '[data-map-detail-name]': detailName, '[data-map-detail-area]': detailArea, '[data-map-crops]': crops,
      '[data-map-warning]': warning, '[data-fit-map]': fit, '[data-map-retry]': retry })[selector] };
  const google = { maps: {
    Map: class { constructor() { calls.maps++; } fitBounds() { calls.fits++; } },
    Polygon: class { constructor() { calls.polygons++; } addListener() {} setOptions() {} setMap(map) { if (map === null) calls.removed++; } },
    LatLngBounds: class { extend() {} }
  } };
  const context = { document: { querySelector: selector => selector === '[data-map-records]' ? records : root, createElement: makeElement, head: { appendChild() {} } },
    window: { google: mapsAvailable ? google : undefined, location: { href: 'https://example.test/farmer-portal/parcels' } }, google, URL, AbortController,
    setTimeout: () => 1, clearTimeout() {}, fetch: async (url, options) => { calls.requests.push({ url, options }); return respond(calls.requests.length); } };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/farmer-portal-map.js'), 'utf8'), context);
  return { calls, select, detail, detailName, crops, fit, retry, canvas, status, records };
}

function samplePlot(id, name) {
  return { id, name, area_ha: 0.25, color: '#236344', paths: [[{ lat: 15, lng: 120 }, { lat: 15, lng: 120.01 }, { lat: 15.01, lng: 120.01 }]], crops: [{ season: 'Wet season', crop: 'Rice / Palay' }] };
}

const mapResponse = page => ({ ok: true, json: async () => page });
const settle = () => new Promise(resolve => setImmediate(resolve));

test('farmer parcel overview loads every map page, fits all boundaries and exposes selection details', async () => {
  const pages = [
    { plots: [samplePlot(4, 'North plot')], total: 2, next_after_id: 4 },
    { plots: [samplePlot(9, 'South plot')], total: 2, next_after_id: null }
  ];
  const h = collectionHarness(index => mapResponse(pages[index - 1]));
  await settle();
  assert.equal(h.calls.requests.length, 2);
  assert.equal(new URL(h.calls.requests[1].url).searchParams.get('after_id'), '4');
  assert.equal(h.calls.requests[0].options.cache, 'no-store');
  assert.equal(h.calls.requests[0].options.credentials, 'same-origin');
  assert.equal(h.calls.maps, 1);
  assert.equal(h.calls.polygons, 2);
  assert.ok(h.calls.fits >= 1);
  assert.equal(h.select.disabled, false);
  assert.equal(h.detailName.textContent, 'North plot');
  h.select.value = '9'; h.select.events.change();
  assert.equal(h.detailName.textContent, 'South plot');
  assert.match(h.status.textContent, /2 of 2 recorded parcels/);
  assert.equal(h.retry.hidden, true);
});

test('a failed later map page preserves loaded land and retries from the same cursor', async () => {
  const h = collectionHarness(index => index === 2 ? { ok: false, status: 500 } : mapResponse({
    plots: [samplePlot(index === 1 ? 4 : 9, index === 1 ? 'North plot' : 'South plot')], total: 2, next_after_id: index === 1 ? 4 : null
  }));
  await settle();
  assert.equal(h.calls.polygons, 1);
  assert.equal(h.canvas.hidden, false);
  assert.match(h.status.textContent, /Only 1 of 2/);
  assert.equal(h.records.open, true);
  await h.retry.events.click();
  assert.equal(h.calls.requests[1].url, h.calls.requests[2].url);
  assert.equal(h.calls.polygons, 2);
  assert.equal(h.calls.maps, 1);
  assert.equal(h.retry.hidden, true);
});

test('session expiry during map paging removes already displayed private boundaries', async () => {
  const h = collectionHarness(index => index === 1
    ? mapResponse({ plots: [samplePlot(4, 'North plot')], total: 2, next_after_id: 4 })
    : { ok: false, status: 401 });
  await settle();
  assert.equal(h.calls.removed, 1);
  assert.equal(h.canvas.hidden, true);
  assert.equal(h.detail.hidden, true);
  assert.equal(h.select.disabled, true);
  assert.equal(h.records.open, false);
  assert.match(h.status.textContent, /Sign in again/);
});

test('unavailable maps reveal parcel records and keep owned area and crop selection usable', async () => {
  const h = collectionHarness(() => mapResponse({ plots: [samplePlot(4, 'North plot')], total: 1, next_after_id: null }), false);
  await settle();
  assert.equal(h.calls.maps, 0);
  assert.equal(h.canvas.hidden, true);
  assert.equal(h.records.open, true);
  assert.equal(h.select.disabled, false);
  assert.equal(h.detailName.textContent, 'North plot');
  assert.match(h.status.textContent, /Maps are unavailable/);
});

function sessionHarness() {
  let time = 100000, tick;
  const events = {}, calls = [], redirects = [];
  const session = { dataset: { portalHeartbeat: '/farmer-portal/heartbeat', idleSeconds: '900', loginUrl: '/farmer-portal/login' } };
  const document = { hidden: false, documentElement: { style: {} }, querySelectorAll: () => [],
    querySelector: selector => ({ '[data-portal-heartbeat]': session, 'form[data-portal-logout]': { action: '/farmer-portal/logout' }, 'meta[name="csrf-token"]': { content: 'test-only' } })[selector] };
  const context = { document, Date: { now: () => time }, setTimeout: () => 1, clearTimeout() {},
    window: { addEventListener: (name, fn) => { events[name] = fn; }, setInterval: fn => { tick = fn; }, location: { replace: url => redirects.push(url) } },
    fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, status: 204 }; } };
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/farmer-portal.js'), 'utf8'), context);
  return { events, calls, document, redirects, tick: () => tick(), advance: ms => { time += ms; } };
}

test('inactive pages do not keep sessions alive; user activity sends a CSRF-protected heartbeat', async () => {
  const h = sessionHarness();
  h.advance(61000); h.tick();
  assert.equal(h.calls.length, 0);
  h.events.keydown(); h.tick();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.calls[0].url, '/farmer-portal/heartbeat');
  assert.equal(h.calls[0].options.headers['X-CSRF-TOKEN'], 'test-only');
});

test('idle expiry hides private content, requests logout once and goes to sign-in', async () => {
  const h = sessionHarness();
  h.advance(901000); h.tick(); h.tick();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(h.calls.length, 1);
  assert.equal(h.calls[0].url, '/farmer-portal/logout');
  assert.equal(h.document.documentElement.style.visibility, 'hidden');
  assert.deepEqual(h.redirects, ['/farmer-portal/login']);
});
