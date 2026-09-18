const { test } = require('node:test');
const assert = require('node:assert/strict');
const { overviewRing } = require('../../public/js/parcel-display-geometry.js');

const metresPerDegree = Math.PI * 6371008.8 / 180;
const at = (x, y) => ({ lat: 15 + y / metresPerDegree, lng: 120 + x / (metresPerDegree * Math.cos(15 * Math.PI / 180)) });
function dense(corners, segments = 10) {
  return corners.flatMap(([x, y], index) => {
    const [nextX, nextY] = corners[(index + 1) % corners.length];
    return Array.from({ length: segments }, (_, step) => at(x + (nextX - x) * step / segments, y + (nextY - y) * step / segments));
  });
}
function signedArea(ring) {
  return ring.reduce((area, point, index) => {
    const next = ring[(index + 1) % ring.length];
    return area + (point.lng - ring[0].lng) * (next.lat - ring[0].lat) - (next.lng - ring[0].lng) * (point.lat - ring[0].lat);
  }, 0) / 2;
}

test('dense convex boundaries keep their exact corner objects and precision', () => {
  const ring = dense([[0, 0], [100, 0], [100, 100], [0, 100]]);
  const reduced = overviewRing(ring, 2);
  assert.equal(reduced.length, 4);
  assert.deepEqual(reduced, [ring[0], ring[10], ring[20], ring[30]]);
  reduced.forEach(point => assert.ok(ring.includes(point)));
});

test('concave boundaries preserve their interior corner and winding', () => {
  const ring = dense([[0, 0], [100, 0], [100, 40], [40, 40], [40, 100], [0, 100]]);
  const reduced = overviewRing(ring, 2);
  assert.equal(reduced.length, 6);
  assert.ok(reduced.includes(ring[30]));
  assert.ok(Math.abs(signedArea(reduced) / signedArea(ring) - 1) < 0.000001);
});

test('closed rings remain closed with the original duplicate endpoint object', () => {
  const ring = dense([[0, 0], [100, 0], [100, 100], [0, 100]]);
  const closure = { ...ring[0] };
  ring.push(closure);
  const reduced = overviewRing(ring, 1);
  assert.equal(reduced.length, 5);
  assert.equal(reduced[0], ring[0]);
  assert.equal(reduced.at(-1), closure);
  assert.deepEqual(reduced[0], reduced.at(-1));
});

test('skinny triangles and tiny parcels never collapse', () => {
  const triangle = [at(0, 0), at(100, 0), at(50, 0.01)];
  assert.deepEqual(overviewRing(triangle, 2), triangle);
  const tiny = dense([[0, 0], [1, 0], [1, 1], [0, 1]]);
  assert.deepEqual(overviewRing(tiny, 2), tiny);
});

test('reversing a ring preserves the reversed winding and source vertices', () => {
  const ring = dense([[0, 0], [100, 0], [100, 40], [40, 40], [40, 100], [0, 100]]).reverse();
  const reduced = overviewRing(ring, 2);
  assert.ok(reduced.length < ring.length);
  assert.equal(Math.sign(signedArea(reduced)), Math.sign(signedArea(ring)));
  reduced.forEach(point => assert.ok(ring.includes(point)));
});

test('original coordinates, arrays and bounds remain unchanged', () => {
  const ring = dense([[0, 0], [100, 0], [100, 100], [0, 100]]);
  const before = structuredClone(ring);
  ring.forEach(Object.freeze);
  Object.freeze(ring);
  const reduced = overviewRing(ring, 2);
  assert.notEqual(reduced, ring);
  assert.deepEqual(ring, before);
  for (const coordinate of ['lat', 'lng']) {
    assert.equal(Math.min(...ring.map(point => point[coordinate])), Math.min(...before.map(point => point[coordinate])));
    assert.equal(Math.max(...ring.map(point => point[coordinate])), Math.max(...before.map(point => point[coordinate])));
  }
});

test('area changes above two percent fall back to the exact source shape', () => {
  const ring = [[0, 0], [10, 0], [10, 10], [5, 11], [0, 10]].map(([x, y]) => at(x, y));
  const reduced = overviewRing(ring, 2);
  assert.notEqual(reduced, ring);
  assert.deepEqual(reduced, ring);
});

test('self-crossing and repeated-vertex source boundaries are not reinterpreted', () => {
  const crossed = dense([[0, 0], [100, 100], [0, 100], [80, 0]]);
  assert.deepEqual(overviewRing(crossed, 2), crossed);
  const repeated = [at(0, 0), at(100, 0), at(100, 100), at(100, 0), at(0, 100)];
  assert.deepEqual(overviewRing(repeated, 2), repeated);
});

test('invalid arguments return a safe copy without dropping coordinates', () => {
  const ring = dense([[0, 0], [100, 0], [100, 100], [0, 100]]);
  for (const tolerance of [undefined, null, NaN, Infinity, -2, 0, '2']) {
    const result = overviewRing(ring, tolerance);
    assert.notEqual(result, ring);
    assert.deepEqual(result, ring);
  }
  assert.deepEqual(overviewRing(null, 2), []);
  for (const invalid of [null, { lat: NaN, lng: 120 }, { lat: 90, lng: 120 }, { lat: 15, lng: 181 }, { lat: '15', lng: 120 }]) {
    const badRing = [...ring, invalid];
    assert.deepEqual(overviewRing(badRing, 2), badRing);
  }
  const insufficient = [at(0, 0), at(10, 10), at(0, 0)];
  assert.deepEqual(overviewRing(insufficient, 2), insufficient);
});

test('requested tolerance cannot exceed the conservative two-metre maximum', () => {
  const ring = dense([[0, 0], [100, 0], [100, 100], [70, 100], [50, 96], [30, 100], [0, 100]]);
  assert.deepEqual(overviewRing(ring, 1000), overviewRing(ring, 2));
});
