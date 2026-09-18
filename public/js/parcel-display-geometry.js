/* Display-only parcel simplification. Stored, edited and exported coordinates stay exact. */
(function (root, factory) {
  'use strict';

  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  if (root) root.ParcelDisplayGeometry = api;
})(typeof window !== 'undefined' ? window : null, function () {
  'use strict';

  var METRES_PER_DEGREE = Math.PI * 6371008.8 / 180;
  var MAX_TOLERANCE_METRES = 2;
  var MAX_AREA_CHANGE = 0.02;
  var EPSILON = 1e-8;

  function samePoint(a, b) {
    return a.lat === b.lat && a.lng === b.lng;
  }

  function squareDistance(a, b) {
    var x = a.x - b.x;
    var y = a.y - b.y;
    return x * x + y * y;
  }

  function segmentDistanceSquared(point, a, b) {
    var x = b.x - a.x;
    var y = b.y - a.y;
    var length = x * x + y * y;
    if (!length) return squareDistance(point, a);

    var fraction = Math.max(0, Math.min(1, ((point.x - a.x) * x + (point.y - a.y) * y) / length));
    var dx = point.x - a.x - fraction * x;
    var dy = point.y - a.y - fraction * y;
    return dx * dx + dy * dy;
  }

  function simplifyChain(chain, toleranceSquared) {
    var keep = new Set([0, chain.length - 1]);
    var pending = [[0, chain.length - 1]];

    while (pending.length) {
      var range = pending.pop();
      var furthest = -1;
      var maximum = toleranceSquared;
      for (var i = range[0] + 1; i < range[1]; i++) {
        var distance = segmentDistanceSquared(chain[i], chain[range[0]], chain[range[1]]);
        if (distance > maximum) {
          maximum = distance;
          furthest = i;
        }
      }
      if (furthest !== -1) {
        keep.add(furthest);
        pending.push([range[0], furthest], [furthest, range[1]]);
      }
    }

    return chain.filter(function (_, index) { return keep.has(index); });
  }

  function signedArea(points) {
    var total = 0;
    for (var i = 0; i < points.length; i++) {
      var a = points[i];
      var b = points[(i + 1) % points.length];
      total += a.x * b.y - b.x * a.y;
    }
    return total / 2;
  }

  function orientation(a, b, c) {
    var cross = (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
    var tolerance = EPSILON * Math.max(1, Math.sqrt(squareDistance(a, b)));
    return Math.abs(cross) <= tolerance ? 0 : (cross > 0 ? 1 : -1);
  }

  function onSegment(a, b, point) {
    return point.x >= Math.min(a.x, b.x) - EPSILON && point.x <= Math.max(a.x, b.x) + EPSILON &&
      point.y >= Math.min(a.y, b.y) - EPSILON && point.y <= Math.max(a.y, b.y) + EPSILON;
  }

  function segmentsIntersect(a, b, c, d) {
    var ac = orientation(a, b, c);
    var ad = orientation(a, b, d);
    var ca = orientation(c, d, a);
    var cb = orientation(c, d, b);
    if (ac * ad < 0 && ca * cb < 0) return true;

    return (ac === 0 && onSegment(a, b, c)) || (ad === 0 && onSegment(a, b, d)) ||
      (ca === 0 && onSegment(c, d, a)) || (cb === 0 && onSegment(c, d, b));
  }

  function selfIntersects(points) {
    for (var i = 0; i < points.length; i++) {
      var next = (i + 1) % points.length;
      for (var j = i + 1; j < points.length; j++) {
        var otherNext = (j + 1) % points.length;
        if (j === next || otherNext === i) continue;
        if (segmentsIntersect(points[i], points[next], points[j], points[otherNext])) return true;
      }
    }
    return false;
  }

  function overviewRing(ring, toleranceMeters) {
    if (!Array.isArray(ring)) return [];
    var original = ring.slice();
    if (typeof toleranceMeters !== 'number' || !Number.isFinite(toleranceMeters) || toleranceMeters <= 0) return original;
    if (ring.length < 4) return original;

    var latitudeSum = 0;
    for (var i = 0; i < ring.length; i++) {
      var point = ring[i];
      if (!point || typeof point.lat !== 'number' || typeof point.lng !== 'number' ||
          !Number.isFinite(point.lat) || !Number.isFinite(point.lng) ||
          Math.abs(point.lat) >= 85 || Math.abs(point.lng) > 180) return original;
      latitudeSum += point.lat;
    }

    var closed = samePoint(ring[0], ring[ring.length - 1]);
    var open = closed ? ring.slice(0, -1) : original;
    // Duplicate vertices can describe invalid or touching rings; do not reinterpret them.
    var seen = new Set();
    for (var j = 0; j < open.length; j++) {
      var key = open[j].lat + ':' + open[j].lng;
      if (seen.has(key)) return original;
      seen.add(key);
    }
    if (open.length < 4) return original;

    var xScale = METRES_PER_DEGREE * Math.cos(latitudeSum / ring.length * Math.PI / 180);
    var projected = open.map(function (point) {
      return { x: (point.lng - open[0].lng) * xScale, y: (point.lat - open[0].lat) * METRES_PER_DEGREE, source: point };
    });
    // Local parcel projection is inappropriate across the antimeridian.
    if (open.some(function (point) { return Math.abs(point.lng - open[0].lng) > 180; })) return original;

    var tolerance = Math.min(toleranceMeters, MAX_TOLERANCE_METRES);
    var originalArea = signedArea(projected);
    if (Math.abs(originalArea) <= Math.max(1, tolerance * tolerance * 4)) return original;

    // Two open chains avoid treating the identical start/end of a ring as a single line.
    var split = 1;
    for (var k = 2; k < projected.length; k++) {
      if (squareDistance(projected[0], projected[k]) > squareDistance(projected[0], projected[split])) split = k;
    }
    var first = simplifyChain(projected.slice(0, split + 1), tolerance * tolerance);
    var second = simplifyChain(projected.slice(split).concat(projected[0]), tolerance * tolerance);
    var reduced = first.slice(0, -1).concat(second.slice(0, -1));
    if (reduced.length < 3 || reduced.length >= projected.length) return original;

    var reducedArea = signedArea(reduced);
    if (originalArea * reducedArea <= 0 || Math.abs(reducedArea - originalArea) / Math.abs(originalArea) > MAX_AREA_CHANGE) return original;
    if (selfIntersects(reduced) || selfIntersects(projected)) return original;

    var result = reduced.map(function (point) { return point.source; });
    if (closed) result.push(ring[ring.length - 1]);
    return result;
  }

  return { overviewRing: overviewRing };
});
