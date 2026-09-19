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
