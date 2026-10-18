const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/parcel-satellite-modal.js', 'utf8');
const settle = () => new Promise(resolve => setImmediate(resolve));

function harness() {
  class Element {
    constructor() {this.listeners = {}; this.hidden = false; this.open = false; this.writes = []; this.isConnected = true;}
    addEventListener(name, fn) {(this.listeners[name] ??= []).push(fn);}
    fire(name, event = {}) {for(const fn of this.listeners[name] || []) fn(event);}
    setAttribute() {} removeAttribute() {} focus() {}
    showModal() {this.open = true;}
    close() {this.open = false; this.fire('close');}
    set src(value) {this.value = value; this.writes.push(value);}
    get src() {return this.value;}
  }
  const elements = {}, el = id => elements[id] ??= new Element();
  const documentFor = config => ({getElementById:id => id === 'satellitePage' ? {} : id === 'satelliteConfig' ? {textContent:config} : null,
    querySelector:() => ({content:'fresh-csrf'}), addEventListener() {}});
  let paused = 0, resumed = 0;
  const frame = el('parcelSatelliteFrame');
  frame.contentDocument = documentFor('config-a');
  frame.contentWindow = {location:{href:'about:blank'}, __mapUsage:{token:'old'},
    __pauseParcelSatellite:() => paused++, __resumeParcelSatellite:() => resumed++};
  el('parcelSatelliteModalLogin').href = 'https://office.example/login';
  const calls = [];
  const fetch = (url, options) => new Promise(resolve => calls.push({url, options, resolve}));
  const window = {__parcelSatelliteUrl:'https://office.example/farm-plots/__ID__/satellite',
    location:{href:'https://office.example/farmers',origin:'https://office.example'}, dispatchEvent() {}};
  const document = {getElementById:el, body:{style:{overflow:''}}, activeElement:new Element()};
  vm.runInNewContext(source, {window, document, fetch, URL, AbortController, setTimeout, clearTimeout,
    DOMParser:class {parseFromString(html) {return documentFor(html);}}});
  function loaded() {frame.contentWindow.location.href = frame.src; frame.fire('load');}
  function respond(status = 200, config = 'config-a') {calls.at(-1).resolve({ok:status === 200,status,url:calls.at(-1).url,text:async () => config});}
  return {el, frame, window, calls, loaded, respond, paused:() => paused, resumed:() => resumed};
}

test('a closed satellite modal retains one iframe and reauthorizes before reuse', async () => {
  const h = harness(); h.window.__openParcelSatelliteModal(1); h.loaded();
  const url = h.frame.src;
  h.el('parcelSatelliteModalClose').fire('click');
  assert.equal(h.frame.src, url); assert.equal(h.paused(), 1);
  h.window.__openParcelSatelliteModal(1);
  assert.equal(h.calls.length, 1); assert.equal(h.frame.hidden, true);
  assert.equal(h.resumed(), 0);
  h.respond(); await settle();
  assert.equal(h.frame.src, url); assert.equal(h.frame.writes.length, 1);
  assert.equal(h.frame.hidden, false); assert.equal(h.resumed(), 1);
  assert.equal(h.frame.contentWindow.__mapUsage.token, 'fresh-csrf');
});

test('revoked access discards retained content instead of revealing the old map', async () => {
  const h = harness(); h.window.__openParcelSatelliteModal(1); h.loaded();
  h.el('parcelSatelliteModalClose').fire('click'); h.window.__openParcelSatelliteModal(1);
  h.respond(403); await settle();
  assert.equal(h.frame.src, 'about:blank'); assert.equal(h.frame.hidden, true);
  assert.equal(h.resumed(), 0); assert.equal(h.el('parcelSatelliteModalError').hidden, false);
});

test('a changed boundary/configuration reloads the workspace and stale verification stays hidden', async () => {
  const h = harness(); h.window.__openParcelSatelliteModal(1); h.loaded();
  h.el('parcelSatelliteModalClose').fire('click'); h.window.__openParcelSatelliteModal(1);
  h.respond(200, 'config-b'); await settle();
  assert.equal(h.frame.writes.length, 2); assert.equal(h.resumed(), 0);
  h.loaded(); h.el('parcelSatelliteModalClose').fire('click'); h.window.__openParcelSatelliteModal(1);
  h.el('parcelSatelliteModalClose').fire('click');
  assert.equal(h.calls.at(-1).options.signal.aborted, true);
  h.respond(); await settle();
  assert.equal(h.frame.hidden, true); assert.equal(h.resumed(), 0);
});
