const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/farmers-maps.js'), 'utf8');
const view = fs.readFileSync(path.join(__dirname, '../../resources/views/farmers/maps.blade.php'), 'utf8');
const controlStart = source.indexOf("    var mapLabelsButton =", source.indexOf('host.appendChild(map3d);'));
const controlEnd = source.indexOf('    var dataById = new Map();', controlStart);
assert.ok(controlStart > 0 && controlEnd > controlStart, 'Use the real map label event binding');
const controlSource = source.slice(controlStart, controlEnd);

function labelsControl() {
  const attributes = new Map([['aria-pressed', 'true']]);
  const listeners = new Map();
  const button = {
    disabled: true, textContent: 'Map labels: On',
    setAttribute: (name, value) => attributes.set(name, value),
    addEventListener: (event, listener) => listeners.set(event, listener),
  };
  const MapMode = { HYBRID: 'HYBRID', SATELLITE: 'SATELLITE' };
  const camera = Object.freeze({ center: { lat: 17, lng: 122 }, range: 10000, heading: 20, tilt: 45 });
  const overlays = Object.freeze([{ id: 'parcel' }, { id: 'municipality-label' }]);
  const map = { mode: MapMode.HYBRID, ...camera, children: overlays };
  const writes = [];
  const map3d = new Proxy(map, {
    set(target, key, value) {
      writes.push(key);
      target[key] = value;
      return true;
    },
  });
  vm.runInNewContext(controlSource, {
    document: { getElementById: id => id === 'toggleFarmerMapLabels' ? button : null },
    map3d, MapMode,
  });
  return { button, attributes, map, camera, overlays, writes, click: () => listeners.get('click')() };
}

test('farmers map labels control switches imagery without changing camera or AgriGOV overlays', () => {
  const control = labelsControl();
  assert.equal(control.button.disabled, false);
  control.click();
  assert.equal(control.map.mode, 'SATELLITE');
  assert.equal(control.attributes.get('aria-pressed'), 'false');
  assert.equal(control.button.textContent, 'Map labels: Off');
  control.click();
  assert.equal(control.map.mode, 'HYBRID');
  assert.equal(control.attributes.get('aria-pressed'), 'true');
  assert.equal(control.button.textContent, 'Map labels: On');
  assert.deepEqual(control.writes, ['mode', 'mode']);
  assert.equal(control.map.center, control.camera.center);
  assert.equal(control.map.children, control.overlays);
});

test('farmers map labels control stays safe when disabled or absent', () => {
  const control = labelsControl();
  control.button.disabled = true;
  control.click();
  assert.equal(control.map.mode, 'HYBRID');
  assert.deepEqual(control.writes, []);
  assert.doesNotThrow(() => vm.runInNewContext(controlSource, {
    document: { getElementById: () => null },
  }));
});

test('farmers map labels control starts disabled and is disabled after map startup failure', () => {
  const markup = view.match(/<button\b[^>]*\bid="toggleFarmerMapLabels"[^>]*>[\s\S]*?<\/button>/)[0];
  assert.match(markup, /type="button"/);
  assert.match(markup, /aria-pressed="true"/);
  assert.match(markup, /\bdisabled\b/);
  assert.match(markup, /Google place and road labels/);
  const failureStart = source.indexOf("          var mapLabelsButton =", source.indexOf('startup = initFarmersMap3D()'));
  const failureEnd = source.indexOf('          showToast(', failureStart);
  assert.ok(failureStart > 0 && failureEnd > failureStart);
  const button = { disabled: false };
  vm.runInNewContext(source.slice(failureStart, failureEnd), {
    document: { getElementById: id => id === 'toggleFarmerMapLabels' ? button : null },
  });
  assert.equal(button.disabled, true);
});
