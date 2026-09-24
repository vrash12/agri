const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { readObservations, createRequestSlot, validDay } = require('../../public/js/parcel-satellite.js');
const scripts = JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, '../Support/export-sentinel-evalscripts.php')], { encoding: 'utf8' }));
const period = { from: '2026-08-01', to: '2026-08-03' };
const row = { date: '2026-08-01', mean: 0, min: -0.2, max: 0.2, valid_pixels: 14, status: 'available' };

function evaluate(script, pixel) {
  const sandbox = { pixel, colorBlend: () => [0.2, 0.4, 0.6] };
  vm.runInNewContext(script + '; result = evaluatePixel(pixel);', sandbox);
  return JSON.parse(JSON.stringify(sandbox.result));
}

test('actual provider scripts compute NDVI and mask cloud/shadow/snow/no-data pixels consistently', () => {
  const pixel = { B04: 0.2, B08: 0.6, B02: 0.1, B03: 0.15, SCL: 4, dataMask: 1 };
  assert.ok(Math.abs(evaluate(scripts.statistics, pixel).ndvi[0] - 0.5) < 1e-9);
  for (let scl = 0; scl <= 11; scl++) {
    const expectedMask = [4, 5, 6].includes(scl) ? 1 : 0;
    assert.equal(evaluate(scripts.statistics, { ...pixel, SCL: scl }).dataMask[0], expectedMask);
    assert.equal(evaluate(scripts.ndvi, { ...pixel, SCL: scl })[3], expectedMask);
    assert.equal(evaluate(scripts.trueColor, { ...pixel, SCL: scl })[3], expectedMask);
  }
  assert.equal(evaluate(scripts.statistics, { ...pixel, dataMask: 0 }).dataMask[0], 0);
  assert.equal(evaluate(scripts.statistics, { ...pixel, B04: 0, B08: 0 }).dataMask[0], 0);
});

test('water and bare soil preserve negative and zero NDVI; true color uses the visible bands', () => {
  const pixel = { B04: 0.2, B08: 0.1, B02: 0.1, B03: 0.15, SCL: 6, dataMask: 1 };
  const water = evaluate(scripts.statistics, pixel);
  assert.equal(water.dataMask[0], 1);
  assert.ok(Math.abs(water.ndvi[0] + 1 / 3) < 1e-9);
  assert.deepEqual(evaluate(scripts.statistics, { ...pixel, SCL: 5, B08: 0.2 }), { ndvi: [0], dataMask: [1] });
  assert.deepEqual(evaluate(scripts.trueColor, pixel), [0.5, 0.375, 0.25, 1]);
});

test('daily observations sort dates and preserve zero separately from a fully masked day', () => {
  const masked = { ...row, date: '2026-08-03', mean: null, min: null, max: null, valid_pixels: 0, status: 'no_data' };
  const output = readObservations({ observations: [masked, row] }, period);
  assert.equal(output[0].mean, 0);
  assert.equal(output[1].mean, null);
  assert.deepEqual(readObservations({ observations: [] }, period), []);
});

test('malformed, duplicate, out-of-period and impossible measurements are rejected', () => {
  const invalid = [null, { ...row, date: '2026-02-30' }, { ...row, date: '2026-08-04' },
    { ...row, mean: null }, { ...row, mean: 2 }, { ...row, mean: NaN }, { ...row, valid_pixels: 1.5 },
    { ...row, min: 0.4 }, { ...row, status: 'no_data' }];
  for (const item of invalid) assert.throws(() => readObservations({ observations: [item] }, period));
  assert.throws(() => readObservations({ observations: [row, row] }, period));
  assert.throws(() => readObservations({ observations: Array(32).fill(row) }, period));
  assert.equal(validDay('2024-02-29'), true);
  assert.equal(validDay('2026-02-29'), false);
});

test('a late image response cannot replace the newer day or layer', async () => {
  const calls = [];
  const slot = createRequestSlot((url, options) => new Promise(resolve => calls.push({ url, options, resolve })));
  const older = slot.run('/first', {}, async value => value);
  const newer = slot.run('/second', {}, async value => value);
  assert.equal(calls[0].options.signal.aborted, true);
  calls[1].resolve('second');
  assert.deepEqual(await newer, { value: 'second' });
  calls[0].resolve('first');
  assert.equal(await older, null);
});

test('leaving the page or clearing observations also discards a response during decoding', async () => {
  let decoded;
  const slot = createRequestSlot(async () => 'response');
  const pending = slot.run('/image', {}, () => new Promise(resolve => { decoded = resolve; }));
  await new Promise(resolve => setImmediate(resolve));
  slot.cancel(); decoded('bytes');
  assert.equal(await pending, null);
});

test('superseded failures are ignored while the current failure remains visible', async () => {
  const failures = [];
  const slot = createRequestSlot(() => new Promise((resolve, reject) => failures.push(reject)));
  const older = slot.run('/old', {}, value => value);
  const current = slot.run('/new', {}, value => value);
  failures[0](new Error('old failure'));
  assert.equal(await older, null);
  failures[1](new Error('current failure'));
  await assert.rejects(current, /current failure/);
});
