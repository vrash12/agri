const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const root = path.resolve(__dirname, '../..');
// The workspace script now lives in its own file rather than inside the Blade
// page, so it can be linted and read here without stripping template syntax.
const source = fs.readFileSync(path.join(root, 'public/js/municipality-boundaries.js'), 'utf8');
const functions = ['geometryPolygons', 'googlePaths', 'boundaryFillOpacity', 'applyFillOpacity', 'editBoundary', 'cancelEditor', 'showEditorFeedback', 'updateDrawState', 'pointsToGeoJson', 'saveEditor'];
const editorCode = functions.map(name => {
  const match = source.match(new RegExp('^  (?:async )?function ' + name + '\\b[\\s\\S]*?(?=^  (?:async )?function )', 'm'));
  assert.ok(match, 'Missing editor function ' + name);
  return match[0];
}).join('\n');

// Model the numeric longitude normalization performed by the map SDK.
const point = ([lng, lat]) => ({ lng: () => ((lng + 180) % 360 + 360) % 360 - 180, lat: () => lat });
const fixtures = ['tarlac_reference_boundaries', 'tarlac_extended_reference_boundaries', 'tarlac_remaining_reference_boundaries', 'benguet_reference_boundaries', 'benguet_remaining_reference_boundaries', 'baguio_reference_boundary']
  .flatMap(name => JSON.parse(fs.readFileSync(path.join(root, 'database/seeders/data', name + '.geojson'), 'utf8')).features)
  .filter(feature => feature.geometry.type === 'Polygon' && feature.geometry.coordinates.length === 1);

function editor(geometry) {
  const controls = new Map();
  const calls = [];
  const context = vm.createContext({
    state: { draftPoints: [], originalEditorCoordinates: new Map(), editorRevision: 0, boundaryFills: new Map(), fillOpacity: .2 },
    settings: { canManage: true, updateTemplate: '/boundaries/__ID__', storeUrl: '/boundaries', csrf: 'test' },
    el: id => { if (!controls.has(id)) controls.set(id, { value: '', checked: false }); return controls.get(id); },
    endpoint: (url, id) => url.replace('__ID__', id),
    focusOverlay: () => {}, toast: () => {}, filter: {}, loadMunicipality: async () => {},
    request: async (url, options) => { calls.push({ url, ...options, body: JSON.parse(options.body) }); return { boundary: { id: 7 } }; },
    google: { maps: { Polygon: class {
      constructor(options) { this.points = options.paths[0].map(p => point([p.lng, p.lat])); }
      setMap() {}
      setOptions() {}
      getPath() { return { getArray: () => this.points, getLength: () => this.points.length }; }
    } } },
  });
  vm.runInContext(editorCode, context);
  context.editBoundary({ id: 7, municipality_id: 3, name: 'Reference boundary', color: '#236344', status: 'active', _record_version: 'existing-version', geojson: geometry });
  return { context, calls, controls };
}

test('name/color saves preserve every editable reference geometry without submitting replacement geometry', async () => {
  assert.ok(fixtures.length >= 25);
  for (const feature of fixtures) {
    const { context, calls, controls } = editor(feature.geometry);
    controls.get('editorName').value = 'Updated name';
    controls.get('editorColor').value = '#FACC15';
    await context.saveEditor();
    assert.equal(calls.length, 1, feature.properties.shapeName);
    assert.equal(calls[0].method, 'PUT');
    assert.equal(Object.hasOwn(calls[0].body, 'geojson'), false, feature.properties.shapeName);
    assert.equal(calls[0].body.replace_confirmed, 0);
    assert.equal(calls[0].body._record_version, 'existing-version');
  }
});

test('moving one vertex keeps all untouched shared-border coordinates exactly as stored', async () => {
  const geometry = fixtures.find(f => f.properties.shapeName === 'Atok').geometry;
  const { context, calls, controls } = editor(geometry);
  const moved = point([geometry.coordinates[0][1][0] + 0.000123456789, geometry.coordinates[0][1][1]]);
  context.state.editableOverlay.points[1] = moved;
  controls.get('replaceConfirmed').checked = true;
  await context.saveEditor();
  const coordinates = calls[0].body.geojson.coordinates[0];
  assert.deepEqual(coordinates[1], [moved.lng(), moved.lat()]);
  for (let i = 0; i < coordinates.length; i++) {
    if (i !== 1) assert.deepEqual(coordinates[i], geometry.coordinates[0][i]);
  }
});

test('inserting and removing vertices preserves surviving source coordinates', async () => {
  const geometry = fixtures[0].geometry;
  const { context, calls, controls } = editor(geometry);
  controls.get('replaceConfirmed').checked = true;
  const inserted = point([geometry.coordinates[0][1][0] + 0.0000123456789, geometry.coordinates[0][1][1]]);
  context.state.editableOverlay.points.splice(1, 1, inserted, point([120.123456789123, 15.987654321987]));
  await context.saveEditor();
  const coordinates = calls[0].body.geojson.coordinates[0];
  assert.deepEqual(coordinates[0], geometry.coordinates[0][0]);
  assert.deepEqual(coordinates[1], [inserted.lng(), inserted.lat()]);
  assert.deepEqual(coordinates.slice(3), geometry.coordinates[0].slice(2));
});

test('color changes ignore GeoJSON property order and do not require shape replacement', async () => {
  const original = fixtures.find(feature => feature.properties.shapeName === 'Paniqui').geometry;
  const geometry = { coordinates: original.coordinates, type: original.type };
  const { context, calls, controls } = editor(geometry);
  controls.get('editorColor').value = '#D97706';
  await context.saveEditor();
  assert.equal(calls.length, 1);
  assert.equal(Object.hasOwn(calls[0].body, 'geojson'), false);
  assert.equal(calls[0].body.replace_confirmed, 0);
});

test('changed active geometry without confirmation remains open and displays a persistent error', async () => {
  const { context, calls, controls } = editor(fixtures[0].geometry);
  context.state.editableOverlay.points[1] = point([120.1234, 15.1234]);
  await context.saveEditor();
  assert.equal(calls.length, 0);
  assert.equal(controls.get('boundaryEditor').hidden, false);
  assert.equal(controls.get('editorFeedback').hidden, false);
  assert.match(controls.get('editorFeedback').textContent, /replacement confirmation/);
});

test('new drawings retain precision and close the ring only once', () => {
  const { context } = editor(fixtures[0].geometry);
  const points = [[120.123456789123, 15.123456789123], [120.123556789123, 15.123456789123], [120.123556789123, 15.123556789123]].map(point);
  const expected = points.map(p => [p.lng(), p.lat()]);
  expected.push(expected[0]);
  assert.deepEqual(JSON.parse(JSON.stringify(context.pointsToGeoJson(points).coordinates[0])), expected);
  assert.deepEqual(JSON.parse(JSON.stringify(context.pointsToGeoJson([...points, points[0]]).coordinates[0])), expected);
});
