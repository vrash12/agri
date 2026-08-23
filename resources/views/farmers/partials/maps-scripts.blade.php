@push('scripts')
<script>
  var GOOGLE_MAPS_API_KEY = window.__gmapsApiKey || "";
  var GOOGLE_MAPS_MAP_ID  = window.__gmapsMapId  || "YOUR_REAL_MAP_ID_HERE";

  (function (g) {
    var h, a, k,
      p = "The Google Maps JavaScript API",
      c = "google",
      l = "importLibrary",
      q = "__ib__",
      m = document,
      b = window;

    b = b[c] || (b[c] = {});
    var d = b.maps || (b.maps = {});
    var r = d.__libset || (d.__libset = {});
    var e = new URLSearchParams();

    function addLib(lib) { if (lib) r[lib] = true; }

    function u() {
      if (h) return h;
      h = new Promise(function (resolve, reject) {
        a = m.createElement("script");
        var libs = Object.keys(r).join(",");
        e.set("libraries", libs);

        for (k in g) {
          e.set(k.replace(/[A-Z]/g, function (t) { return "_" + t[0].toLowerCase(); }), g[k]);
        }

        e.set("callback", c + ".maps." + q);
        a.src = "https://maps." + c + "apis.com/maps/api/js?" + e.toString();
        d[q] = resolve;
        a.onerror = function () { reject(Error(p + " could not load.")); };

        var nonceEl = m.querySelector("script[nonce]");
        a.nonce = nonceEl ? nonceEl.nonce : "";
        m.head.appendChild(a);
      });

      return h;
    }

    if (!d[l]) {
      d[l] = function () {
        var args = arguments;
        addLib(args[0]);
        return u().then(function () {
          return d[l].apply(d, args);
        });
      };
    } else {
      console.warn(p + " only loads once. Ignoring:", g);
    }
  })({
    key: GOOGLE_MAPS_API_KEY,
    v: "beta",
    mapIds: GOOGLE_MAPS_MAP_ID
  });
</script>

<script>
  (function () {
    var toastTimer = null;

    function showToast(msg, kind) {
      var el = document.getElementById('mapToast');
      if (!el) return;

      el.className = 'map-toast is-show' + (kind ? (' is-' + kind) : '');
      el.textContent = msg;

      if (toastTimer) clearTimeout(toastTimer);
      toastTimer = setTimeout(function () {
        el.className = 'map-toast';
        el.textContent = '';
      }, 2600);
    }

    function setText(id, value) {
      var el = document.getElementById(id);
      if (el) el.textContent = (value == null || value === '') ? '—' : String(value);
    }

    function setHref(id, href) {
      var el = document.getElementById(id);
      if (el) el.setAttribute('href', href || '#');
    }

    window.__mapToast = showToast;
    window.__mapUiSetText = setText;
    window.__mapUiSetHref = setHref;

    function bindRowClickToMap() {
      if (!window.jQuery) return;

      $('#farmersTable')
        .off('click.__rowToMap')
        .on('click.__rowToMap', 'tbody tr', function (e) {
          if ($(e.target).closest('a,button,input,select,textarea,label').length) return;

          var id = this && this.dataset ? this.dataset.farmerId : null;
          if (!id) return;

          if (typeof window.__openFarmer3d === 'function') {
window.__openFarmer3d(String(id), { showMarker: false });
          } else if (typeof window.__mapToast === 'function') {
            window.__mapToast('Map is still loading… try again in a moment.', 'warn');
          }
        });
    }


function bindButtons() {
  var btnFit = document.getElementById('recenterMapBtn');
  if (btnFit) btnFit.addEventListener('click', function () {
    if (typeof window.__fit3dToVisibleMarkers === "function") {
      window.__fit3dToVisibleMarkers();
    }
  });

  var btnReset = document.getElementById('resetMapBtn');
  if (btnReset) btnReset.addEventListener('click', function () {
    if (typeof window.__reset3dMap === "function") {
      window.__reset3dMap();
    }
  });

  var btnClear = document.getElementById('clearSelectionBtn');
  if (btnClear) btnClear.addEventListener('click', function () {
    if (typeof window.__clearFarmerSelection === "function") {
      window.__clearFarmerSelection();
    }
  });

  var btnFocus = document.getElementById('focusSelectedBtn');
  if (btnFocus) btnFocus.addEventListener('click', function () {
    if (typeof window.__focusSelectedFarmer === "function") {
      window.__focusSelectedFarmer();
    }
  });

 var btnDownloadAll = document.getElementById('downloadAllPlotsBtn');
if (btnDownloadAll) {
  btnDownloadAll.addEventListener('click', function () {
    if (typeof window.__handleDownloadAllPlots === 'function') {
      window.__handleDownloadAllPlots();
    } else if (typeof window.__mapToast === 'function') {
      window.__mapToast('Map is still loading… try again in a moment.', 'warn');
    }
  });
}

  var tMarkers = document.getElementById('toggleMarkers');
  if (tMarkers) tMarkers.addEventListener('change', function () {
    if (typeof window.__applyMarkerVisibility === "function") {
      window.__applyMarkerVisibility();
    }
  });

  var tPlots = document.getElementById('togglePlots');
  if (tPlots) tPlots.addEventListener('change', function () {
    if (typeof window.__applyPlotVisibility === "function") {
      window.__applyPlotVisibility();
    }
    if (typeof window.__setPlotsLoadingEnabled === "function") {
      window.__setPlotsLoadingEnabled(!!tPlots.checked);
    }
  });
}
   $(function () {
  bindRowClickToMap();
  bindButtons();

  initFarmersMap3D().catch(function (e) {
    console.error(e);
    var statusEl = document.getElementById('mapStatus');
    var statusSmall = document.getElementById('mapStatusSmall');

    if (statusEl) statusEl.textContent = '3D map failed to load.';
    if (statusSmall) statusSmall.textContent = 'Check your API key / Map ID.';

    showToast('3D map failed to load. Check API key and Map ID.', 'bad');
  });
});
  })();

  async function initFarmersMap3D() {
function buildCornerHandleElement(isSelected) {
  var el = document.createElement('div');
  el.style.width = '18px';
  el.style.height = '18px';
  el.style.position = 'relative';
  el.style.boxSizing = 'border-box';
  el.style.border = '1.5px solid rgba(255,255,255,0.98)';
  el.style.background = 'rgba(255,255,255,0.06)';
  el.style.borderRadius = '2px';
  el.style.pointerEvents = 'auto';
  el.style.boxShadow = isSelected
    ? '0 0 0 2px rgba(59,130,246,0.35), 0 1px 6px rgba(0,0,0,0.35)'
    : '0 1px 4px rgba(0,0,0,0.28)';

  function makeLine(styles) {
    var line = document.createElement('span');
    line.style.position = 'absolute';
    for (var key in styles) line.style[key] = styles[key];
    return line;
  }

  var lineColor = isSelected ? '#60a5fa' : '#ffffff';

  el.appendChild(makeLine({
    left: '2px',
    right: '2px',
    top: '50%',
    height: '1px',
    background: lineColor,
    transform: 'translateY(-50%)'
  }));

  el.appendChild(makeLine({
    top: '2px',
    bottom: '2px',
    left: '50%',
    width: '1px',
    background: lineColor,
    transform: 'translateX(-50%)'
  }));

  return el;
}


function buildCornerHandleTemplate(isSelected) {
  var tpl = document.createElement('template');
  tpl.content.appendChild(buildCornerHandleElement(isSelected));
  return tpl;
}
  // =========================
// KMZ / KML IMPORT FOR 3D MAP
// =========================

function getKmzImportBtn() {
  return document.getElementById('importKmzBtn');
}

function getKmzFileInput() {
  return document.getElementById('kmzFileInput');
}

function bindKmzImport() {
  var btn = getKmzImportBtn();
  var input = getKmzFileInput();

  if (!btn) {
    console.warn('KMZ import button #importKmzBtn not found.');
    return;
  }

  if (!input) {
    console.warn('KMZ file input #kmzFileInput not found.');
    return;
  }

  btn.addEventListener('click', function () {
    if (!selectedFarmerId) {
      toast('Select a farmer first before importing KMZ.', 'warn');
      return;
    }

    input.value = '';
    input.click();
  });

  input.addEventListener('change', async function (e) {
    var file = e.target && e.target.files ? e.target.files[0] : null;
    if (!file) return;

    try {
      await importKmzOrKmlToSelectedFarmer(file);
    } catch (err) {
      console.error(err);
      toast(err && err.message ? err.message : 'KMZ import failed.', 'bad');
      setStatus('KMZ import failed.', '');
    } finally {
      input.value = '';
    }
  });
}

function pointEquals(a, b) {
  return Math.abs(Number(a.lat) - Number(b.lat)) < 1e-10 &&
         Math.abs(Number(a.lng) - Number(b.lng)) < 1e-10;
}

function orientation(a, b, c) {
  var v = (Number(b.lng) - Number(a.lng)) * (Number(c.lat) - Number(a.lat)) -
          (Number(b.lat) - Number(a.lat)) * (Number(c.lng) - Number(a.lng));

  if (Math.abs(v) < 1e-12) return 0;
  return v > 0 ? 1 : 2;
}

function onSegment(a, p, b) {
  return (
    Math.min(Number(a.lng), Number(b.lng)) - 1e-12 <= Number(p.lng) &&
    Number(p.lng) <= Math.max(Number(a.lng), Number(b.lng)) + 1e-12 &&
    Math.min(Number(a.lat), Number(b.lat)) - 1e-12 <= Number(p.lat) &&
    Number(p.lat) <= Math.max(Number(a.lat), Number(b.lat)) + 1e-12
  );
}

function segmentsIntersect(a1, a2, b1, b2) {
  // ignore shared endpoints so touching your own corner does not count as overlap
  if (pointEquals(a1, b1) || pointEquals(a1, b2) || pointEquals(a2, b1) || pointEquals(a2, b2)) {
    return false;
  }

  var o1 = orientation(a1, a2, b1);
  var o2 = orientation(a1, a2, b2);
  var o3 = orientation(b1, b2, a1);
  var o4 = orientation(b1, b2, a2);

  if (o1 !== o2 && o3 !== o4) return true;

  if (o1 === 0 && onSegment(a1, b1, a2)) return true;
  if (o2 === 0 && onSegment(a1, b2, a2)) return true;
  if (o3 === 0 && onSegment(b1, a1, b2)) return true;
  if (o4 === 0 && onSegment(b1, a2, b2)) return true;

  return false;
}

function pointInPolygon(point, ring) {
  ring = openRing(ring);
  if (!ring || ring.length < 3) return false;

  var x = Number(point.lng);
  var y = Number(point.lat);
  var inside = false;

  for (var i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    var xi = Number(ring[i].lng), yi = Number(ring[i].lat);
    var xj = Number(ring[j].lng), yj = Number(ring[j].lat);

    var intersect = ((yi > y) !== (yj > y)) &&
      (x < ((xj - xi) * (y - yi)) / ((yj - yi) || 1e-12) + xi);

    if (intersect) inside = !inside;
  }

  return inside;
}

function getClosedEdges(ring) {
  var pts = openRing(ring);
  var edges = [];
  if (!pts || pts.length < 2) return edges;

  for (var i = 0; i < pts.length; i++) {
    edges.push([pts[i], pts[(i + 1) % pts.length]]);
  }

  return edges;
}

function polygonEdgesIntersect(ringA, ringB) {
  var edgesA = getClosedEdges(ringA);
  var edgesB = getClosedEdges(ringB);

  for (var i = 0; i < edgesA.length; i++) {
    for (var j = 0; j < edgesB.length; j++) {
      if (segmentsIntersect(edgesA[i][0], edgesA[i][1], edgesB[j][0], edgesB[j][1])) {
        return true;
      }
    }
  }

  return false;
}

function lineIntersectsPolygon(a, b, ring) {
  var edges = getClosedEdges(ring);

  for (var i = 0; i < edges.length; i++) {
    if (segmentsIntersect(a, b, edges[i][0], edges[i][1])) {
      return true;
    }
  }

  return false;
}

function polygonsOverlap(ringA, ringB) {
  var a = openRing(ringA);
  var b = openRing(ringB);

  if (!a.length || !b.length) return false;

  if (polygonEdgesIntersect(a, b)) return true;
  if (pointInPolygon(a[0], b)) return true;
  if (pointInPolygon(b[0], a)) return true;

  return false;
}

function getOtherSavedPlotsForCollisionCheck() {
  var out = [];

  plotsCacheByFarmerId.forEach(function (plots, farmerId) {
    if (!Array.isArray(plots)) return;

    for (var i = 0; i < plots.length; i++) {
      var pl = plots[i];
      if (!pl) continue;

      if (editingPlotId && String(pl.id) === String(editingPlotId)) {
        continue;
      }

      var ring = normalizePolygonRing(pl.polygon_json || pl.polygon || pl.polygonJson);
      if (!ring || ring.length < 3) continue;

      out.push({
        plotId: String(pl.id),
        farmerId: String(farmerId),
        name: pl.name || ('Plot #' + pl.id),
        ring: openRing(ring)
      });
    }
  });

  return out;
}

function findDraftOverlap(candidateVertices) {
  var pts = openRing(candidateVertices);
  if (!pts || pts.length < 2) return null;

  var others = getOtherSavedPlotsForCollisionCheck();

  for (var i = 0; i < others.length; i++) {
    var other = others[i];
    var ring = other.ring;

    // only a line so far (point 1 -> point 2)
    if (pts.length === 2) {
      if (
        lineIntersectsPolygon(pts[0], pts[1], ring) ||
        pointInPolygon(pts[0], ring) ||
        pointInPolygon(pts[1], ring)
      ) {
        return other;
      }
      continue;
    }

    // 3+ points: treat as polygon draft
    if (polygonsOverlap(pts, ring)) {
      return other;
    }
  }

  return null;
}

function canUseDraftVertices(candidateVertices, silent) {
  var hit = findDraftOverlap(candidateVertices);
  if (!hit) return true;

  if (!silent) {
    toast('This draft overlaps with existing ' + hit.name + '. Move it away from the other plot.', 'warn');
    setStatus('Overlap detected', 'Draft cannot overlap another saved plot.');
  }

  return false;
}

function stripXmlNs(tagName) {
  return String(tagName || '').replace(/^.*:/, '');
}

function firstDirectChildByTag(parent, tagName) {
  if (!parent) return null;
  var nodes = parent.children || [];
  tagName = String(tagName || '').toLowerCase();

  for (var i = 0; i < nodes.length; i++) {
    if (stripXmlNs(nodes[i].tagName).toLowerCase() === tagName) {
      return nodes[i];
    }
  }
  return null;
}

function childText(parent, tagName) {
  var node = firstDirectChildByTag(parent, tagName);
  return node ? String(node.textContent || '').trim() : '';
}

function parseKmlColorToHex(kmlColor, fallback) {
  fallback = fallback || '#22c55e';
  var s = String(kmlColor || '').trim();

  // KML color format: aabbggrr
  if (!/^[0-9a-fA-F]{8}$/.test(s)) return fallback;

  var bb = s.slice(2, 4);
  var gg = s.slice(4, 6);
  var rr = s.slice(6, 8);

  return ('#' + rr + gg + bb).toLowerCase();
}

function parseCoordinatesBlock(text) {
  text = String(text || '').trim();
  if (!text) return [];

  var out = [];
  var chunks = text.split(/\s+/);

  for (var i = 0; i < chunks.length; i++) {
    var p = chunks[i].split(',');
    if (p.length < 2) continue;

    var lng = parseFloat(p[0]);
    var lat = parseFloat(p[1]);

    if (!isFinite(lat) || !isFinite(lng)) continue;
    out.push({ lat: lat, lng: lng });
  }

  // remove duplicate closing point before save
  if (out.length > 1) {
    var a = out[0];
    var b = out[out.length - 1];
    if (Math.abs(a.lat - b.lat) < 1e-10 && Math.abs(a.lng - b.lng) < 1e-10) {
      out.pop();
    }
  }

  return out;
}

function parseOuterBoundaryPolygon(polygonNode) {
  if (!polygonNode) return [];

  var outer = null;
  var kids = polygonNode.getElementsByTagName('*');
  for (var i = 0; i < kids.length; i++) {
    if (stripXmlNs(kids[i].tagName).toLowerCase() === 'outerboundaryis') {
      outer = kids[i];
      break;
    }
  }
  if (!outer) return [];

  var coordsNode = null;
  var outerKids = outer.getElementsByTagName('*');
  for (var j = 0; j < outerKids.length; j++) {
    if (stripXmlNs(outerKids[j].tagName).toLowerCase() === 'coordinates') {
      coordsNode = outerKids[j];
      break;
    }
  }

  if (!coordsNode) return [];
  return parseCoordinatesBlock(coordsNode.textContent || '');
}

function buildKmlStyleLookup(xmlDoc) {
  var styleById = new Map();
  var styleMapById = new Map();

  var all = xmlDoc.getElementsByTagName('*');
  for (var i = 0; i < all.length; i++) {
    var tag = stripXmlNs(all[i].tagName).toLowerCase();

    if (tag === 'style') {
      var id = all[i].getAttribute('id');
      if (!id) continue;

      var polyColor = '';
      var lineColor = '';

      var descendants = all[i].getElementsByTagName('*');
      for (var j = 0; j < descendants.length; j++) {
        var dtag = stripXmlNs(descendants[j].tagName).toLowerCase();

        if (dtag === 'polystyle') {
          polyColor = childText(descendants[j], 'color') || polyColor;
        }
        if (dtag === 'linestyle') {
          lineColor = childText(descendants[j], 'color') || lineColor;
        }
      }

      styleById.set('#' + id, {
        line: parseKmlColorToHex(lineColor, ''),
        poly: parseKmlColorToHex(polyColor, '')
      });
    }

    if (tag === 'stylemap') {
      var mapId = all[i].getAttribute('id');
      if (!mapId) continue;

      var normalUrl = '';
      var pairs = all[i].getElementsByTagName('*');
      for (var k = 0; k < pairs.length; k++) {
        if (stripXmlNs(pairs[k].tagName).toLowerCase() !== 'pair') continue;
        var key = childText(pairs[k], 'key');
        var styleUrl = childText(pairs[k], 'styleUrl');
        if (key === 'normal' && styleUrl) {
          normalUrl = styleUrl;
          break;
        }
      }

      if (normalUrl) {
        styleMapById.set('#' + mapId, normalUrl);
      }
    }
  }

  return {
    resolve: function (styleUrl) {
      var url = String(styleUrl || '').trim();
      if (!url) return '#22c55e';

      if (styleMapById.has(url)) {
        url = styleMapById.get(url);
      }

      var style = styleById.get(url);
      if (!style) return '#22c55e';

      return style.line || style.poly || '#22c55e';
    }
  };
}

function extractKmlPolygons(xmlText) {
  var parser = new DOMParser();
  var xmlDoc = parser.parseFromString(xmlText, 'application/xml');

  var parseError = xmlDoc.getElementsByTagName('parsererror');
  if (parseError && parseError.length) {
    throw new Error('The KMZ/KML file could not be parsed.');
  }

  var styleLookup = buildKmlStyleLookup(xmlDoc);
  var placemarks = xmlDoc.getElementsByTagName('*');
  var imported = [];

  for (var i = 0; i < placemarks.length; i++) {
    if (stripXmlNs(placemarks[i].tagName).toLowerCase() !== 'placemark') continue;

    var placemark = placemarks[i];
    var placeName = childText(placemark, 'name') || ('Imported Plot ' + (imported.length + 1));
    var styleUrl = childText(placemark, 'styleUrl');
    var colorHex = styleLookup.resolve(styleUrl);

    var descendants = placemark.getElementsByTagName('*');
    var polygonCountForPlacemark = 0;

    for (var j = 0; j < descendants.length; j++) {
      if (stripXmlNs(descendants[j].tagName).toLowerCase() !== 'polygon') continue;

      var ring = parseOuterBoundaryPolygon(descendants[j]);
      if (!ring || ring.length < 3) continue;

      polygonCountForPlacemark++;

      imported.push({
        name: polygonCountForPlacemark > 1 ? (placeName + ' ' + polygonCountForPlacemark) : placeName,
        color: colorHex || '#22c55e',
        polygon: ring
      });
    }
  }

  return imported;
}

async function readKmzOrKmlText(file) {
  var fileName = String(file && file.name ? file.name : '').toLowerCase();

  if (fileName.endsWith('.kml') || fileName.endsWith('.xml')) {
    return await file.text();
  }

  if (!fileName.endsWith('.kmz')) {
    throw new Error('Please select a .kmz or .kml file.');
  }

  if (typeof JSZip === 'undefined') {
    throw new Error('JSZip is required for KMZ import.');
  }

  var zip = await JSZip.loadAsync(file);
  var kmlEntry = null;

  zip.forEach(function (relativePath, zipEntry) {
    if (!kmlEntry && /\.kml$/i.test(relativePath)) {
      kmlEntry = zipEntry;
    }
  });

  if (!kmlEntry) {
    throw new Error('No KML file was found inside the KMZ.');
  }

  return await kmlEntry.async('string');
}

async function saveImportedPlotsToSelectedFarmer(importedPlots) {
  if (!selectedFarmerId) {
    throw new Error('Select a farmer first before importing.');
  }

  var total = importedPlots.length;
  var saved = 0;

  for (var i = 0; i < importedPlots.length; i++) {
    var item = importedPlots[i];

    setStatus('Importing KMZ…', 'Saving plot ' + (i + 1) + ' of ' + total);

    var res = await fetch('/farmers/' + encodeURIComponent(String(selectedFarmerId)) + '/plots', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrfToken()
      },
      body: JSON.stringify({
        name: item.name,
        color: item.color,
        polygon: item.polygon
      })
    });

    if (!res.ok) {
      throw new Error('Saving imported plot failed at item ' + (i + 1) + '.');
    }

    saved++;
  }

  return saved;
}

async function importKmzOrKmlToSelectedFarmer(file) {
  if (!selectedFarmerId) {
    toast('Select a farmer first before importing KMZ.', 'warn');
    return;
  }

  var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
  var farmerName = farmer ? formatName(farmer) : 'selected farmer';

  setStatus('Reading KMZ…', 'Opening ' + (file.name || 'file'));
  toast('Reading KMZ/KML for ' + farmerName + '…', 'ok');

  var xmlText = await readKmzOrKmlText(file);
  var importedPlots = extractKmlPolygons(xmlText);

  if (!importedPlots.length) {
    throw new Error('No polygon placemarks were found in that KMZ/KML.');
  }

  setStatus('KMZ parsed.', importedPlots.length + ' polygon(s) found');

  var saved = await saveImportedPlotsToSelectedFarmer(importedPlots);

  plotsCacheByFarmerId.delete(String(selectedFarmerId));
  await loadPlotsForSelectedFarmer(String(selectedFarmerId), { force: true, autoZoom: true });

  var plots = findSelectedFarmerPlots();
  syncSuggestedPlotName(plots || [], false);

  setStatus('KMZ import complete.', saved + ' plot(s) imported');
  toast(saved + ' KMZ plot(s) imported successfully.', 'ok');
}
    var statusEl = document.getElementById('mapStatus');
    var statusSmallEl = document.getElementById('mapStatusSmall');
    var progressBar = document.getElementById('mapProgressBar');
    var hintEl = document.getElementById('mapHint');
    var plotModeBadge = document.getElementById('plotModeBadge');
    var mapGeocodedPillEl = document.getElementById('mapGeocodedPill');
    var plotNameInputEl = document.getElementById('plotNameInput');

    var data = Array.isArray(window.__farmersMapData) ? window.__farmersMapData : [];
    var farmersById = new Map();
    for (var i = 0; i < data.length; i++) {
      farmersById.set(String(data[i].id), data[i]);
    }

    var DEFAULT_CENTER = { lat: 15.325834, lng: 120.822706 };
    var DEFAULT_RANGE = 160000;
    var DEFAULT_TILT = 22;
    var DEFAULT_HEADING = 0;
    var PLOT_CONCURRENCY = 2;

    function setStatus(main, small) {
      if (statusEl) statusEl.textContent = main || '';
      if (statusSmallEl) statusSmallEl.textContent = small || '';
    }

    function setProgress(pct) {
      if (!progressBar) return;
      var v = Math.max(0, Math.min(100, pct || 0));
      progressBar.style.width = v.toFixed(0) + '%';
    }

    function toast(msg, kind) {
      if (typeof window.__mapToast === "function") {
        window.__mapToast(msg, kind);
      }
    }

    function csrfToken() {
      var el = document.querySelector('meta[name="csrf-token"]');
      return el ? el.getAttribute('content') : '';
    }

    function readJsonResponse(response, fallbackMessage) {
      return response.json().catch(function () {
        return {};
      }).then(function (data) {
        if (response.ok) return data;

        var validationMessage = data && data.errors && data.errors._record_version
          ? data.errors._record_version[0]
          : '';
        throw new Error(validationMessage || data.message || fallbackMessage);
      });
    }

    function escapeHtml(str) {
      str = String(str == null ? '' : str);
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function escapeXml(str) {
      str = String(str == null ? '' : str);
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function safeFilename(str) {
      return String(str || 'plot')
        .trim()
        .replace(/[^\w\-]+/g, '_')
        .replace(/^_+|_+$/g, '') || 'plot';
    }

function formatName(f) {
  if (!f) return 'Farmer';

  var parts = [];
  if ((f.first_name || '').trim()) parts.push((f.first_name || '').trim());
  if ((f.middle_name || '').trim()) parts.push((f.middle_name || '').trim());
  if ((f.last_name || '').trim()) parts.push((f.last_name || '').trim());
  if ((f.ext_name || '').trim()) parts.push((f.ext_name || '').trim());

  var full = parts.join(' ').replace(/\s+/g, ' ').trim();
  return full || (f.last_name || 'Farmer');
}

    function getFarmerGlyph(f) {
      var fn = String(f && f.first_name ? f.first_name : '').trim();
      var ln = String(f && f.last_name ? f.last_name : '').trim();
      var a = fn ? fn.charAt(0).toUpperCase() : '';
      var b = ln ? ln.charAt(0).toUpperCase() : '';
      return (a + b) || 'F';
    }

    function updateGeocodedPill(geocoded, total) {
      if (!mapGeocodedPillEl) return;
      mapGeocodedPillEl.textContent = geocoded + ' / ' + total + ' geocoded';
    }

    function toLatLng(pos) {
      var lat = (typeof pos.lat === "function") ? pos.lat() : pos.lat;
      var lng = (typeof pos.lng === "function") ? pos.lng() : pos.lng;
      return { lat: lat, lng: lng };
    }

    function haversineMeters(a, b) {
      var R = 6371000;
      function toRad(x) { return (x * Math.PI) / 180; }
      var dLat = toRad(b.lat - a.lat);
      var dLng = toRad(b.lng - a.lng);
      var lat1 = toRad(a.lat);
      var lat2 = toRad(b.lat);
      var s = Math.pow(Math.sin(dLat / 2), 2) +
              Math.cos(lat1) * Math.cos(lat2) * Math.pow(Math.sin(dLng / 2), 2);
      return 2 * R * Math.asin(Math.sqrt(s));
    }

    function clamp(n, min, max) {
      return Math.max(min, Math.min(max, n));
    }

    function flyTo(lat, lng, range, durationMillis, tilt, heading) {
      durationMillis = durationMillis == null ? 900 : durationMillis;

      map3d.flyCameraTo({
        endCamera: {
          center: { lat: lat, lng: lng, altitude: 200 },
          tilt: tilt == null ? DEFAULT_TILT : tilt,
          heading: heading == null ? DEFAULT_HEADING : heading,
          range: range
        },
        durationMillis: durationMillis
      });
    }

    function extendBounds(b, ring) {
      for (var i = 0; i < ring.length; i++) {
        var p = ring[i];
        if (!p) continue;
        b.minLat = Math.min(b.minLat, p.lat);
        b.maxLat = Math.max(b.maxLat, p.lat);
        b.minLng = Math.min(b.minLng, p.lng);
        b.maxLng = Math.max(b.maxLng, p.lng);
      }
    }

    function zoomToRing(ring, multiplier, minRange, maxRange) {
      if (!ring || ring.length < 3) return;

      var b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
      extendBounds(b, ring);

      var centerLat = (b.minLat + b.maxLat) / 2;
      var centerLng = (b.minLng + b.maxLng) / 2;
      var diag = haversineMeters(
        { lat: b.minLat, lng: b.minLng },
        { lat: b.maxLat, lng: b.maxLng }
      );
      var range = clamp(diag * (multiplier || 4), minRange || 700, maxRange || 15000);
      flyTo(centerLat, centerLng, range, 900);
    }

    function zoomToPlots(plots) {
      if (!plots || !plots.length) return;

      var b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
      var any = false;

      for (var i = 0; i < plots.length; i++) {
        var ring = normalizePolygonRing(plots[i].polygon_json || plots[i].polygon || plots[i].polygonJson);
        if (!ring || ring.length < 3) continue;
        extendBounds(b, ring);
        any = true;
      }

      if (!any) return;

      var centerLat = (b.minLat + b.maxLat) / 2;
      var centerLng = (b.minLng + b.maxLng) / 2;
      var diag = haversineMeters(
        { lat: b.minLat, lng: b.minLng },
        { lat: b.maxLat, lng: b.maxLng }
      );
      var range = clamp(diag * 4.0, 700, 15000);
      flyTo(centerLat, centerLng, range, 900);
    }

    function highlightRow(id) {
      var highlighted = document.querySelectorAll('#farmersTable tbody tr.row-highlight');
      for (var i = 0; i < highlighted.length; i++) {
        highlighted[i].classList.remove('row-highlight');
      }

      var row = document.getElementById('farmer-row-' + id);
      if (row) row.classList.add('row-highlight');
    }

    function estimateAreaHa(points) {
      if (!points || points.length < 3) return 0;

      var lat0 = 0;
      var lng0 = 0;
      for (var i = 0; i < points.length; i++) {
        lat0 += points[i].lat;
        lng0 += points[i].lng;
      }
      lat0 /= points.length;
      lng0 /= points.length;

      var R = 6371000;
      function toRad(x) { return x * Math.PI / 180; }
      var lat0r = toRad(lat0);

      var xy = [];
      for (var j = 0; j < points.length; j++) {
        var x = toRad(points[j].lng - lng0) * R * Math.cos(lat0r);
        var y = toRad(points[j].lat - lat0) * R;
        xy.push({ x: x, y: y });
      }

      var area = 0;
      for (var k = 0; k < xy.length; k++) {
        var p1 = xy[k];
        var p2 = xy[(k + 1) % xy.length];
        area += (p1.x * p2.y - p2.x * p1.y);
      }

      return (Math.abs(area) / 2) / 10000;
    }

    function hexToRgba(hex, alpha) {
      if (!hex) return "rgba(34,197,94," + alpha + ")";
      hex = String(hex).trim();

      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        var r = parseInt(hex[1] + hex[1], 16);
        var g = parseInt(hex[2] + hex[2], 16);
        var b = parseInt(hex[3] + hex[3], 16);
        return "rgba(" + r + "," + g + "," + b + "," + alpha + ")";
      }

      if (/^#([0-9a-fA-F]{6})$/.test(hex)) {
        var r6 = parseInt(hex.slice(1, 3), 16);
        var g6 = parseInt(hex.slice(3, 5), 16);
        var b6 = parseInt(hex.slice(5, 7), 16);
        return "rgba(" + r6 + "," + g6 + "," + b6 + "," + alpha + ")";
      }

      return "rgba(34,197,94," + alpha + ")";
    }

    function hexAlpha(hex, aHex) {
      hex = String(hex || '').trim();
      if (!hex) return '#22c55e' + aHex;
      if (hex[0] !== '#') hex = '#' + hex;

      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        hex = '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
      }
      if (/^#([0-9a-fA-F]{6})$/.test(hex)) return hex + aHex;
      if (/^#([0-9a-fA-F]{8})$/.test(hex)) return hex.slice(0, 7) + aHex;

      return '#22c55e' + aHex;
    }

    function normalizeHexColor(hex) {
      hex = String(hex || '').trim();
      if (!hex) return '#3b82f6';
      if (hex[0] !== '#') hex = '#' + hex;

      if (/^#([0-9a-fA-F]{3})$/.test(hex)) {
        return '#' + hex[1] + hex[1] + hex[2] + hex[2] + hex[3] + hex[3];
      }
      if (/^#([0-9a-fA-F]{6})$/.test(hex)) return hex;
      if (/^#([0-9a-fA-F]{8})$/.test(hex)) return hex.slice(0, 7);

      return '#3b82f6';
    }


function getEffectivePlotColor(plot) {
  return normalizeHexColor(
    plot && plot.color ? String(plot.color) : '#22c55e'
  );
}


    function hexToRgb(hex) {
      hex = normalizeHexColor(hex);
      return {
        r: parseInt(hex.slice(1, 3), 16),
        g: parseInt(hex.slice(3, 5), 16),
        b: parseInt(hex.slice(5, 7), 16)
      };
    }

    function normalizePolygonRing(ringRaw) {
      if (!ringRaw) return [];

      if (typeof ringRaw === "string") {
        var s = ringRaw.trim();
        if (!s) return [];
        try { ringRaw = JSON.parse(s); } catch (e) { return []; }
      }

      if (ringRaw && typeof ringRaw === "object" && !Array.isArray(ringRaw) && ringRaw.coordinates) {
        try {
          var coords = ringRaw.coordinates;
          if (Array.isArray(coords) && Array.isArray(coords[0])) {
            ringRaw = coords[0].map(function (pt) {
              return Array.isArray(pt) ? { lng: pt[0], lat: pt[1] } : pt;
            });
          }
        } catch (e2) {
          return [];
        }
      }

      if (!Array.isArray(ringRaw)) return [];

      var ring = [];
      for (var i2 = 0; i2 < ringRaw.length; i2++) {
        var p = ringRaw[i2];

        if (Array.isArray(p)) {
          if (p.length >= 2) {
            var a0 = parseFloat(p[0]);
            var a1 = parseFloat(p[1]);
            if (isFinite(a0) && isFinite(a1)) {
              var latGuess = a0;
              var lngGuess = a1;
              if (Math.abs(a0) > 90 && Math.abs(a1) <= 90) {
                lngGuess = a0;
                latGuess = a1;
              }
              ring.push({ lat: latGuess, lng: lngGuess });
            }
          }
          continue;
        }

        if (p && typeof p === "object") {
          var lat = (p.lat != null) ? parseFloat(p.lat) : ((p.latitude != null) ? parseFloat(p.latitude) : NaN);
          var lng = (p.lng != null) ? parseFloat(p.lng) : ((p.longitude != null) ? parseFloat(p.longitude) : NaN);
          if (isFinite(lat) && isFinite(lng)) ring.push({ lat: lat, lng: lng });
        }
      }

      ring = ring.filter(function (pt) {
        return pt && isFinite(pt.lat) && isFinite(pt.lng);
      });

      if (ring.length < 3) return [];

      var f = ring[0];
      var l = ring[ring.length - 1];
      var eps = 1e-10;
      if (Math.abs(f.lat - l.lat) > eps || Math.abs(f.lng - l.lng) > eps) {
        ring.push({ lat: f.lat, lng: f.lng });
      }

      return ring;
    }

    function sameLatLng(a, b) {
      if (!a || !b) return false;
      return Math.abs(Number(a.lat) - Number(b.lat)) < 1e-10 &&
             Math.abs(Number(a.lng) - Number(b.lng)) < 1e-10;
    }

    function openRing(ring) {
      ring = Array.isArray(ring) ? ring.slice() : [];
      if (ring.length > 1 && sameLatLng(ring[0], ring[ring.length - 1])) {
        ring.pop();
      }
      return ring;
    }

    function ringCentroid(ring) {
      ring = openRing(ring);
      if (!ring.length) return { lat: 0, lng: 0 };

      var lat = 0;
      var lng = 0;
      for (var i3 = 0; i3 < ring.length; i3++) {
        lat += Number(ring[i3].lat || 0);
        lng += Number(ring[i3].lng || 0);
      }

      return {
        lat: lat / ring.length,
        lng: lng / ring.length
      };
    }

    function midpointLatLng(a, b) {
      return {
        lat: (Number(a.lat) + Number(b.lat)) / 2,
        lng: (Number(a.lng) + Number(b.lng)) / 2
      };
    }

    if (!GOOGLE_MAPS_API_KEY || !GOOGLE_MAPS_MAP_ID || GOOGLE_MAPS_MAP_ID === "YOUR_REAL_MAP_ID_HERE") {
      setStatus('Map error.', 'Check API key or Map ID.');
      return;
    }

    var maps3d = await google.maps.importLibrary("maps3d");
    var markerLib = await google.maps.importLibrary("marker");

var Map3DElement = maps3d.Map3DElement;
var MapMode = maps3d.MapMode;
var AltitudeMode = maps3d.AltitudeMode;
var Marker3DInteractiveElement = maps3d.Marker3DInteractiveElement;
var Marker3DElement = maps3d.Marker3DElement;
var PopoverElement = maps3d.PopoverElement;
    var Polyline3DInteractiveElement = maps3d.Polyline3DInteractiveElement;
    var Polygon3DElement = maps3d.Polygon3DElement;
    var Polygon3DInteractiveElement = maps3d.Polygon3DInteractiveElement;
    var PinElement = markerLib.PinElement;

    var host = document.getElementById("farmersMap");
    if (!host) return;
    host.innerHTML = "";

    var map3d = new Map3DElement({
      mapId: GOOGLE_MAPS_MAP_ID,
      center: { lat: DEFAULT_CENTER.lat, lng: DEFAULT_CENTER.lng, altitude: 220 },
      tilt: DEFAULT_TILT,
      heading: DEFAULT_HEADING,
      range: DEFAULT_RANGE,
      mode: MapMode.HYBRID,
      gestureHandling: "GREEDY"
    });
    host.appendChild(map3d);

    var markersById = new Map();
    var dataById = new Map();
    var geocodePromisesById = new Map();

    var popover = new PopoverElement({ open: false });
    map3d.append(popover);

    var selectedFarmerId = null;
    var selectedVertexIndex = -1;
    var selectedMarkerVisible = true;

    var plotsCacheByFarmerId = new Map();
    var plotFetchPromisesByFarmerId = new Map();
    var savedPlotOverlays = [];

    var plotsLoadingEnabled = true;
    var plotQueue = [];
    var plotQueueSet = new Set();
    var plotInFlight = 0;
    bindKmzImport();

    function buildMarkerPin(f, isSelected) {
      return new PinElement({
        scale: isSelected ? 1.15 : 1.0,
        background: isSelected ? '#1d4ed8' : '#3b82f6',
        borderColor: isSelected ? '#0f172a' : '#1d4ed8',
        glyphColor: '#ffffff',
        glyphText: getFarmerGlyph(f)
      });
    }

    function applyMarkerPin(marker, f, isSelected) {
      if (!marker || !PinElement) return;
      marker.replaceChildren(buildMarkerPin(f, !!isSelected));
      marker.zIndex = isSelected ? 999 : 1;
    }

    function farmerHasSavedPlots(farmerId) {
      var plots = plotsCacheByFarmerId.get(String(farmerId));
      return Array.isArray(plots) && plots.length > 0;
    }

    function refreshAllMarkerPins() {
      var t = document.getElementById('toggleMarkers');
      var on = !t || t.checked;

      markersById.forEach(function (marker, id) {
        var isSelected = String(id) === String(selectedFarmerId || '');
        var farmer = dataById.get(String(id)) || farmersById.get(String(id));

        applyMarkerPin(marker, farmer, isSelected);

        var shouldShow = on && selectedMarkerVisible && isSelected && farmerHasSavedPlots(id);

        try {
          if (shouldShow) {
            if (!marker.isConnected) map3d.append(marker);
          } else {
            if (marker.isConnected) map3d.removeChild(marker);
          }
        } catch (e3) {}
      });
    }

    window.__applyMarkerVisibility = function () {
      refreshAllMarkerPins();
    };

    window.__setPlotsLoadingEnabled = function (on) {
      plotsLoadingEnabled = !!on;
      if (plotsLoadingEnabled) runPlotQueue();
    };

    function queuePlotFetch(farmerId) {
      farmerId = String(farmerId);
      if (!farmerId) return;
      if (plotsCacheByFarmerId.has(farmerId)) return;
      if (plotQueueSet.has(farmerId)) return;

      plotQueue.push(farmerId);
      plotQueueSet.add(farmerId);
      runPlotQueue();
    }

    function runPlotQueue() {
      if (!plotsLoadingEnabled) return;

      while (plotInFlight < PLOT_CONCURRENCY && plotQueue.length) {
        (function (fid) {
          plotQueueSet.delete(fid);
          plotInFlight++;

          fetchPlotsForFarmer(fid).then(function (plots) {
            renderPlotsForFarmer(fid, plots);
            if (typeof window.__applyPlotVisibility === "function") {
              window.__applyPlotVisibility();
            }
          }).finally(function () {
            plotInFlight--;
            runPlotQueue();
          });
        })(String(plotQueue.shift()));
      }
    }

    function fetchPlotsForFarmer(farmerId, opts) {
      opts = opts || {};
      farmerId = String(farmerId);

      if (!opts.force && plotsCacheByFarmerId.has(farmerId)) {
        return Promise.resolve(plotsCacheByFarmerId.get(farmerId));
      }

      if (!opts.force && plotFetchPromisesByFarmerId.has(farmerId)) {
        return plotFetchPromisesByFarmerId.get(farmerId);
      }

      var req = fetch("/farmers/" + encodeURIComponent(farmerId) + "/plots", {
        method: "GET",
        headers: { "Accept": "application/json" }
      }).then(function (r) {
        if (!r.ok) return { plots: [] };
        return r.json();
      }).then(function (json) {
        var plots = (json && json.plots) ? json.plots : [];
        plotsCacheByFarmerId.set(farmerId, plots);
        return plots;
      }).catch(function () {
        plotsCacheByFarmerId.set(farmerId, []);
        return [];
      }).finally(function () {
        plotFetchPromisesByFarmerId.delete(farmerId);
      });

      plotFetchPromisesByFarmerId.set(farmerId, req);
      return req;
    }

    async function ensureFarmerData(id) {
  id = String(id);

  var existing = dataById.get(id) || farmersById.get(id);
  if (existing) return existing;

  var tpl = window.__farmerMapCardUrlTemplate || '';
  if (!tpl) throw new Error('Farmer map-card URL is missing.');

  var url = tpl.replace('__ID__', encodeURIComponent(id));

  var res = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' }
  });

  if (!res.ok) {
    throw new Error('Farmer not found.');
  }

  var farmer = await res.json();
  if (!farmer || !farmer.id) {
    throw new Error('Farmer not found.');
  }

  farmersById.set(String(farmer.id), farmer);
  dataById.set(String(farmer.id), farmer);

  return farmer;
}

    function clearPlotsForFarmer(farmerId) {
      farmerId = String(farmerId);
      var keep = [];

      for (var i4 = 0; i4 < savedPlotOverlays.length; i4++) {
        var it = savedPlotOverlays[i4];
        if (String(it.farmerId) !== farmerId) {
          keep.push(it);
          continue;
        }

        try { if (it.poly && it.poly.isConnected) map3d.removeChild(it.poly); } catch (e4) {}
        try { if (it.line && it.line.isConnected) map3d.removeChild(it.line); } catch (e5) {}
      }

      savedPlotOverlays = keep;
    }


    function setOverlayVisible(el, visible) {
  if (!el) return;

  try {
    if (visible) {
      if (!el.isConnected) map3d.append(el);
    } else {
      if (el.isConnected) map3d.removeChild(el);
    }
  } catch (e) {}
}

function setSavedPlotOverlayVisible(plotId, visible) {
  plotId = String(plotId);

  for (var i = 0; i < savedPlotOverlays.length; i++) {
    var it = savedPlotOverlays[i];
    if (!it || String(it.plotId) !== plotId) continue;

    setOverlayVisible(it.poly, visible);
    setOverlayVisible(it.line, visible);
  }
}

function setAllSavedPlotsVisible(visible) {
  for (var i = 0; i < savedPlotOverlays.length; i++) {
    var it = savedPlotOverlays[i];
    if (!it) continue;

    setOverlayVisible(it.poly, visible);
    setOverlayVisible(it.line, visible);
  }
}

function restoreSavedPlotsVisibility() {
  if (typeof window.__applyPlotVisibility === "function") {
    window.__applyPlotVisibility();
  }
}

    function setSavedPlotOverlayVisible(plotId, visible) {
  plotId = String(plotId);

  for (var i = 0; i < savedPlotOverlays.length; i++) {
    var it = savedPlotOverlays[i];
    if (!it || String(it.plotId) !== plotId) continue;

    try {
      if (visible) {
        if (it.poly && !it.poly.isConnected) map3d.append(it.poly);
        if (it.line && !it.line.isConnected) map3d.append(it.line);
      } else {
        if (it.poly && it.poly.isConnected) map3d.removeChild(it.poly);
        if (it.line && it.line.isConnected) map3d.removeChild(it.line);
      }
    } catch (e) {}
  }
}

function reloadSelectedFarmerPlots(autoZoom) {
  if (!selectedFarmerId) return Promise.resolve([]);
  plotsCacheByFarmerId.delete(String(selectedFarmerId));
  return loadPlotsForSelectedFarmer(String(selectedFarmerId), {
    force: true,
    autoZoom: !!autoZoom
  });
}
    function setPlotHoverCursor(on) {
      if (moduleEl && moduleEl.classList.contains('is-plot-mode')) return;
      var cursor = on ? 'pointer' : '';
      if (stageEl) stageEl.style.cursor = cursor;
      if (host) host.style.cursor = cursor;
    }

    function bindClickablePlotOverlay(outline, poly, farmerId, plotLabel, styleOpts) {
      styleOpts = styleOpts || {};

      function applyHoverState() {
        if (plotMode) return;

        if (poly) {
          poly.fillColor = styleOpts.fillHover || styleOpts.fillSoft;
          poly.strokeColor = styleOpts.strokeHover || styleOpts.strokeStrong;
          poly.strokeWidth = styleOpts.polyHoverWidth || 10;
        }

        if (outline) {
          outline.strokeColor = styleOpts.strokeHover || styleOpts.strokeStrong;
          outline.strokeWidth = styleOpts.lineHoverWidth || 12;
          outline.outerWidth = styleOpts.lineHoverOuterWidth || 0.7;
        }

        setPlotHoverCursor(true);
        setStatus('Plot ready', 'Click this plot to select ' + (plotLabel || 'farmer'));
      }

    function resetHoverState() {
  if (plotMode) return;

  if (poly) {
    poly.fillColor = styleOpts.fillSoft;
    poly.strokeColor = styleOpts.strokeStrong;
    poly.strokeWidth = styleOpts.polyWidth || 8;
  }

  if (outline) {
    outline.strokeColor = styleOpts.strokeStrong;
    outline.strokeWidth = styleOpts.lineWidth || 10;
    outline.outerWidth = styleOpts.lineOuterWidth || 0.45;
  }

  setPlotHoverCursor(false);

  if (!plotMode) {
    if (selectedFarmerId) setStatus('Selected plot owner', 'Click the map or another plot to change selection.');
    else setStatus('Ready', 'Hover a plot or row, then click to select a farmer.');
  }
}

      function handlePlotOverlayClick(ev) {
        if (ev && ev.stopPropagation) ev.stopPropagation();
        if (plotMode) return;
        window.__openFarmer3d(String(farmerId), { showMarker: false });
      }

      var targets = [outline, poly];
      for (var i5 = 0; i5 < targets.length; i5++) {
        var target = targets[i5];
        if (!target || typeof target.addEventListener !== 'function') continue;

        target.addEventListener('gmp-click', handlePlotOverlayClick);
        target.addEventListener('click', handlePlotOverlayClick);
        target.addEventListener('mouseenter', applyHoverState);
        target.addEventListener('mouseleave', resetHoverState);
        target.addEventListener('mouseover', applyHoverState);
        target.addEventListener('mouseout', resetHoverState);
        target.addEventListener('gmp-mouseenter', applyHoverState);
        target.addEventListener('gmp-mouseleave', resetHoverState);
      }
    }

function renderPlotsForFarmer(farmerId, plots) {
  farmerId = String(farmerId);
  clearPlotsForFarmer(farmerId);

  if (!plots || !plots.length || !Polygon3DInteractiveElement) return;

  var show = true;
  var t = document.getElementById('togglePlots');
  if (t) show = t.checked;

  for (var i6 = 0; i6 < plots.length; i6++) {
    var pl = plots[i6];
    var ring = normalizePolygonRing(pl.polygon_json || pl.polygon || pl.polygonJson);
    if (!ring || ring.length < 3) continue;

    var fillHex = getEffectivePlotColor(pl);

    // very subtle border, same family as fill color
    var visibleBorder = hexAlpha(fillHex, '90');

    // stronger fill so polygon is easier to see on satellite view
    var fillSoft = hexToRgba(fillHex, 0.38);

    // invisible click target only
    var outline = null;
    if (Polyline3DInteractiveElement) {
      outline = new Polyline3DInteractiveElement({
        path: ring,
        strokeColor: '#ffffff01',
        outerColor: '#ffffff00',
        strokeWidth: 14,
        outerWidth: 0,
        altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
        drawsOccludedSegments: true
      });
      if (show) map3d.append(outline);
    }

    // actual visible polygon
    var poly = new Polygon3DInteractiveElement({
      path: ring,
      strokeColor: visibleBorder,
      strokeWidth: 0.8,
      fillColor: fillSoft,
      altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
      drawsOccludedSegments: true
    });
    if (show) map3d.append(poly);

    bindClickablePlotOverlay(outline, poly, farmerId, (pl.name || ('Plot #' + pl.id)), {
      strokeStrong: visibleBorder,
      strokeHover: hexAlpha(fillHex, 'B0'),
      fillSoft: fillSoft,
      fillHover: hexToRgba(fillHex, 0.46),
      polyWidth: 0.8,
      polyHoverWidth: 1.1,
      lineWidth: 14,
      lineHoverWidth: 14,
      lineOuterWidth: 0,
      lineHoverOuterWidth: 0
    });

    savedPlotOverlays.push({
      farmerId: farmerId,
      plotId: pl.id,
      poly: poly,
      line: outline,
      ring: ring
    });
  }
}


    async function loadAllMunicipalPlots() {
  var url = window.__allFarmPlotsUrl;
  if (!url) throw new Error('All plots URL is missing.');

  setStatus('Loading plots…', 'Fetching all saved polygons');
  setProgress(15);

  var res = await fetch(url, {
    method: 'GET',
    headers: { 'Accept': 'application/json' }
  });

  if (!res.ok) {
    throw new Error('Could not load all farm plots.');
  }

  var json = await res.json();
  var plots = Array.isArray(json && json.plots) ? json.plots : [];

  savedPlotOverlays = [];
  plotsCacheByFarmerId.clear();

  var grouped = new Map();

  for (var i = 0; i < plots.length; i++) {
    var pl = plots[i];
    var fid = String(pl.farmer_id || '0');

    if (!grouped.has(fid)) grouped.set(fid, []);
    grouped.get(fid).push(pl);
  }

  grouped.forEach(function (items, fid) {
    plotsCacheByFarmerId.set(String(fid), items);
    renderPlotsForFarmer(String(fid), items);
  });

  if (typeof window.__applyPlotVisibility === 'function') {
    window.__applyPlotVisibility();
  }

  setProgress(100);

  if (mapGeocodedPillEl) {
    mapGeocodedPillEl.textContent = plots.length + ' plots loaded';
  }

  setStatus('Ready', plots.length + ' plot(s) shown on the map.');
  toast(plots.length + ' plots loaded.', 'ok');

  return plots;
}

function zoomToAllLoadedPlots() {
  var b = { minLat: Infinity, maxLat: -Infinity, minLng: Infinity, maxLng: -Infinity };
  var any = false;

  for (var i = 0; i < savedPlotOverlays.length; i++) {
    var ring = savedPlotOverlays[i] && savedPlotOverlays[i].ring;
    if (!ring || !ring.length) continue;
    extendBounds(b, ring);
    any = true;
  }

  if (!any) return;

  var centerLat = (b.minLat + b.maxLat) / 2;
  var centerLng = (b.minLng + b.maxLng) / 2;
  var diag = haversineMeters(
    { lat: b.minLat, lng: b.minLng },
    { lat: b.maxLat, lng: b.maxLng }
  );

  var range = clamp(diag * 2.8, 6000, 250000);
  flyTo(centerLat, centerLng, range, 1200);
}

function syncSelectedPanel(f) {
  var avatarEl = document.getElementById('selAvatar');

  if (!f) {
    window.__mapUiSetText('selName', 'No farmer selected');
    window.__mapUiSetText('selFfrs', 'Choose a farmer to load their record');
    window.__mapUiSetText('selLocation', 'Use the finder, a map marker, or a directory row');
    window.__mapUiSetText('selRecords', '0');
    window.__mapUiSetText('selKgs', '0.00');

    window.__mapUiSetText('selOwnerName', '—');
    window.__mapUiSetText('selOwnerFfrs', '—');
    window.__mapUiSetText('selOwnerBarangay', '—');
    window.__mapUiSetText('selOwnerMunicipality', '—');
    window.__mapUiSetText('selOwnerProvince', '—');
    window.__mapUiSetText('selOwnerFarmArea', '—');

    window.__mapUiSetHref('viewRecordsBtn', '#');

    if (avatarEl) {
      avatarEl.textContent = '—';
      avatarEl.style.backgroundImage = '';
    }
    return;
  }

  var fullName = formatName(f);
  var farmLocation = (f.farm_location || f.location || '').trim();
  var farmMunicipality = (f.farm_municipality || '').trim();
  var farmProvince = (f.farm_province || '').trim();

  var prettyLocation = [farmLocation, farmMunicipality, farmProvince]
    .filter(function (v) { return String(v || '').trim() !== ''; })
    .join(', ');

  window.__mapUiSetText('selName', fullName || '—');
  window.__mapUiSetText('selFfrs', f.ffrs || '—');
  window.__mapUiSetText('selLocation', prettyLocation || '—');
  window.__mapUiSetText('selRecords', Number(f.records_count || 0));
  window.__mapUiSetText('selKgs', (Number(f.total_kgs || 0)).toFixed(2));

var ownerName = (f.owner_name || '').trim();

window.__mapUiSetText('selOwnerName', ownerName || fullName || '—');
  window.__mapUiSetText('selOwnerFfrs', f.ffrs || '—');
  window.__mapUiSetText('selOwnerBarangay', farmLocation || '—');
  window.__mapUiSetText('selOwnerMunicipality', farmMunicipality || '—');
  window.__mapUiSetText('selOwnerProvince', farmProvince || '—');
  window.__mapUiSetText(
    'selOwnerFarmArea',
    f.farm_area_ha != null && f.farm_area_ha !== ''
      ? Number(f.farm_area_ha).toFixed(2) + ' ha'
      : '—'
  );

  if (avatarEl) {
    var a = (f.first_name || 'F').charAt(0);
    var b = (f.last_name || 'L').charAt(0);
    if (f.profile_photo_url) {
      avatarEl.textContent = '';
      avatarEl.style.backgroundImage = 'url("' + String(f.profile_photo_url).replace(/"/g, '\\"') + '")';
    } else {
      avatarEl.textContent = (a + b).toUpperCase();
      avatarEl.style.backgroundImage = '';
    }
  }

  var base = window.__farmersRecordsBaseUrl || "/farmers";
  window.__mapUiSetHref(
    'viewRecordsBtn',
    base.replace(/\/$/, '') + "/" + encodeURIComponent(String(f.id)) + "/records"
  );
}
  function updatePlotCount(n) {
  var el = document.getElementById('plotCountPill');
  if (el) el.textContent = (n || 0) + ((n === 1) ? ' plot' : ' plots');

  var heroEl = document.getElementById('plotCountHero');
  if (heroEl) heroEl.textContent = (n || 0);
}

    function updatePlotTotalArea(ha) {
      var el = document.getElementById('plotAreaTotalPill');
      if (el) el.textContent = (Number(ha || 0)).toFixed(2) + ' ha total';
    }

    function sumPlotAreas(plots) {
      var sum = 0;
      for (var i7 = 0; i7 < plots.length; i7++) {
        var ring = normalizePolygonRing(plots[i7].polygon_json || plots[i7].polygon || plots[i7].polygonJson);
        var ha = (plots[i7].area_ha != null ? plots[i7].area_ha : (plots[i7].areaHa != null ? plots[i7].areaHa : null));
        if (ha == null) ha = estimateAreaHa(ring);
        sum += Number(ha || 0);
      }
      return sum;
    }


    function latRad(lat) {
  var sin = Math.sin((Number(lat) || 0) * Math.PI / 180);
  var radX2 = Math.log((1 + sin) / (1 - sin)) / 2;
  return Math.max(Math.min(radX2, Math.PI), -Math.PI) / 2;
}

function estimateStaticMapZoom(points, widthPx, heightPx, paddingPx) {
  paddingPx = paddingPx == null ? 64 : paddingPx;
  if (!points || points.length < 2) return 20;

  var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
  for (var i = 0; i < points.length; i++) {
    minLat = Math.min(minLat, Number(points[i].lat));
    maxLat = Math.max(maxLat, Number(points[i].lat));
    minLng = Math.min(minLng, Number(points[i].lng));
    maxLng = Math.max(maxLng, Number(points[i].lng));
  }

  var latFraction = Math.max(1e-9, Math.abs(latRad(maxLat) - latRad(minLat)) / Math.PI);
  var lngDiff = maxLng - minLng;
  if (lngDiff < 0) lngDiff += 360;
  if (lngDiff > 180) lngDiff = 360 - lngDiff;
  var lngFraction = Math.max(1e-9, lngDiff / 360);

  function zoom(mapPx, worldPx, fraction) {
    return Math.floor(Math.log(mapPx / worldPx / fraction) / Math.LN2);
  }

  var usableW = Math.max(64, widthPx - (paddingPx * 2));
  var usableH = Math.max(64, heightPx - (paddingPx * 2));
  var z = Math.min(
    zoom(usableW, 256, lngFraction),
    zoom(usableH, 256, latFraction),
    21
  );

  return clamp(z, 1, 21);
}

function buildStaticPlotMapUrl(plot) {
  if (!plot || !plot.id) throw new Error('Plot ID is missing.');

  var template = window.__farmPlotStaticMapUrlTemplate
    || '/farm-plots/__PLOT__/static-map';

  return template.replace(
    '__PLOT__',
    encodeURIComponent(String(plot.id))
  );
}
var PRINT_LEFT_LOGO  = @json(asset('images/mao-logo.jpg'));
var PRINT_RIGHT_LOGO = @json(asset('images/ramos-logo.jpg'));
function buildPrintablePlotHtml(farmer, plot) {
  var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
  if (!ring || ring.length < 3) throw new Error('Plot has no valid polygon.');

  var pts = openRing(ring);
  if (!pts || pts.length < 3) throw new Error('Plot has no valid polygon.');

  var farmerName = farmer ? formatName(farmer) : 'Selected Farmer';
  var farmerFfrs = farmer && farmer.ffrs ? farmer.ffrs : '—';
  var farmerLocation = farmer && farmer.location ? farmer.location : '—';
  var plotName = plot.name || ('Plot #' + plot.id);
  var areaHa = plot.area_ha != null ? Number(plot.area_ha) : estimateAreaHa(pts);
  var createdAt = plot.created_at ? String(plot.created_at).split('T')[0] : '—';
  var staticMapUrl = buildStaticPlotMapUrl(plot);

  var rows = '';
  for (var i = 0; i < pts.length; i++) {
    rows += `
      <tr>
        <td>P${i + 1}</td>
        <td>${Number(pts[i].lat).toFixed(6)}</td>
        <td>${Number(pts[i].lng).toFixed(6)}</td>
      </tr>
    `;
  }

  return `<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Printable Land Plot Sheet</title>
<style>
  @page{
    size: A4 landscape;
    margin: 8mm;
  }

  *{
    box-sizing:border-box;
  }

  html,body{
    margin:0;
    padding:0;
    background:#ffffff;
    font-family:Arial,Helvetica,sans-serif;
    color:#111827;
  }

  body{
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .sheet-wrap{
    padding:0;
  }

  .page{
    width:auto;
    min-height:auto;
    margin:0;
    background:#ffffff;
    box-shadow:none;
    padding:8mm 10mm 10mm;
    break-inside:avoid;
    page-break-inside:avoid;
  }

  .header{
    display:grid;
    grid-template-columns:22mm 1fr 22mm;
    align-items:center;
    gap:8mm;
    margin-bottom:4mm;
  }

  .logo-wrap{
    display:flex;
    align-items:center;
    justify-content:center;
  }

  .logo-wrap img{
    width:18mm;
    height:18mm;
    object-fit:contain;
    display:block;
  }

  .header-center{
    text-align:center;
  }

  .office-title{
    font-size:18px;
    font-weight:800;
    line-height:1.2;
    color:#111827;
  }

  .office-sub{
    margin-top:3px;
    font-size:11px;
    color:#64748b;
    letter-spacing:.2px;
  }

  .header-rule{
    margin-top:3mm;
    border-top:1.5px solid #111827;
  }

  .sheet-title{
    text-align:center;
    font-size:18px;
    font-weight:900;
    letter-spacing:.6px;
    margin:5mm 0 5mm;
  }

  .content-grid{
    display:grid;
    grid-template-columns:1.55fr 1fr;
    gap:8mm;
    align-items:start;
    break-inside:avoid;
    page-break-inside:avoid;
  }

  .map-card,
  .side-col,
  .details-grid,
  .coords{
    break-inside:avoid;
    page-break-inside:avoid;
  }

  .map-card{
    border:1px solid #cbd5e1;
    border-radius:14px;
    overflow:hidden;
    background:#f8fafc;
  }

  .map-card-head{
    padding:10px 12px;
    border-bottom:1px solid #dbe4ef;
    background:#ffffff;
  }

  .map-title{
    font-size:16px;
    font-weight:800;
    margin:0;
    color:#111827;
  }

  .map-subtitle{
    margin:4px 0 0;
    font-size:11px;
    color:#64748b;
  }

  .map-card img{
    width:100%;
    height:auto;
    max-height:105mm;
    display:block;
    object-fit:contain;
    object-position:center center;
    background:#eef4fb;
  }

  .side-col{
    display:flex;
    flex-direction:column;
    gap:5mm;
  }

  .details-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:3.5mm;
  }

  .detail-card{
    border:1px solid #dbe4ef;
    border-radius:12px;
    background:#ffffff;
    padding:8px 10px;
    min-height:58px;
  }

  .detail-k{
    font-size:10px;
    font-weight:700;
    color:#6b7280;
    text-transform:uppercase;
    letter-spacing:.3px;
    margin-bottom:3px;
  }

  .detail-v{
    font-size:13px;
    font-weight:800;
    color:#111827;
    word-break:break-word;
    line-height:1.25;
  }

  .coords{
    border:1px solid #dbe4ef;
    border-radius:12px;
    overflow:hidden;
    background:#ffffff;
  }

  .coords-head{
    padding:8px 10px;
    background:#f8fafc;
    border-bottom:1px solid #dbe4ef;
    font-size:13px;
    font-weight:800;
    color:#111827;
  }

  table{
    width:100%;
    border-collapse:collapse;
  }

  thead th{
    background:#eef4ff;
    color:#1d4ed8;
    font-size:10px;
    text-align:left;
    padding:6px 8px;
    border-bottom:1px solid #dbe4ef;
  }

  tbody td{
    font-size:10px;
    padding:6px 8px;
    border-bottom:1px solid #e5e7eb;
    vertical-align:top;
  }

  tbody td:nth-child(2),
  tbody td:nth-child(3){
    font-family:monospace;
  }

  tbody tr:last-child td{
    border-bottom:none;
  }

  .footer{
    margin-top:8mm;
    position:static;
  }

  .signature-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:14mm;
    align-items:end;
  }

  .signature-item{
    display:flex;
    align-items:flex-end;
    gap:8px;
  }

  .signature-label{
    font-size:12px;
    font-weight:500;
    color:#111827;
    white-space:nowrap;
  }

  .signature-line{
    flex:1;
    min-width:40mm;
    height:16px;
    border-bottom:1.2px solid #111827;
  }

  @media print{
    html,body{
      background:#ffffff;
    }

    .sheet-wrap{
      padding:0;
    }

    .page{
      margin:0;
      box-shadow:none;
    }
  }
</style>
</head>
<body>
  <div class="sheet-wrap">
    <div class="page">
      <div class="header">
        <div class="logo-wrap">
          <img src="${PRINT_LEFT_LOGO}" alt="Left Logo" onerror="this.style.visibility='hidden';">
        </div>

        <div class="header-center">
          <div class="office-title">Municipal Agriculture Office – Ramos</div>
          <div class="office-sub">Printable land plot information sheet</div>
          <div class="header-rule"></div>
        </div>

        <div class="logo-wrap">
          <img src="${PRINT_RIGHT_LOGO}" alt="Right Logo" onerror="this.style.visibility='hidden';">
        </div>
      </div>

      <div class="sheet-title">LAND PLOT INFORMATION</div>

      <div class="content-grid">
        <div class="map-card">
          <div class="map-card-head">
            <div class="map-title">Plot Map</div>
            <div class="map-subtitle">Exported view focused on the selected land plot.</div>
          </div>
          <img id="plotStaticMap" src="${staticMapUrl}" alt="Plot map">
        </div>

        <div class="side-col">
          <div class="details-grid">
            <div class="detail-card">
              <div class="detail-k">Farmer</div>
              <div class="detail-v">${escapeXml(farmerName)}</div>
            </div>

            <div class="detail-card">
              <div class="detail-k">FFRS</div>
              <div class="detail-v">${escapeXml(farmerFfrs)}</div>
            </div>

            <div class="detail-card">
              <div class="detail-k">Location</div>
              <div class="detail-v">${escapeXml(farmerLocation)}</div>
            </div>

            <div class="detail-card">
              <div class="detail-k">Plot Name</div>
              <div class="detail-v">${escapeXml(plotName)}</div>
            </div>

            <div class="detail-card">
              <div class="detail-k">Area</div>
              <div class="detail-v">${areaHa.toFixed(2)} ha</div>
            </div>

            <div class="detail-card">
              <div class="detail-k">Created</div>
              <div class="detail-v">${escapeXml(createdAt)}</div>
            </div>
          </div>

          <div class="coords">
            <div class="coords-head">Coordinates</div>
            <table>
              <thead>
                <tr>
                  <th>Point</th>
                  <th>Latitude</th>
                  <th>Longitude</th>
                </tr>
              </thead>
              <tbody>
                ${rows}
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="footer">
        <div class="signature-grid">
          <div class="signature-item">
            <span class="signature-label">Prepared By:</span>
            <span class="signature-line"></span>
          </div>
          <div class="signature-item">
            <span class="signature-label">Certified By:</span>
            <span class="signature-line"></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>`;
}

function printPlotSheet(farmer, plot) {
  var html = buildPrintablePlotHtml(farmer, plot);

  var iframe = document.createElement('iframe');
  iframe.style.position = 'fixed';
  iframe.style.right = '0';
  iframe.style.bottom = '0';
  iframe.style.width = '0';
  iframe.style.height = '0';
  iframe.style.border = '0';
  document.body.appendChild(iframe);

  var doc = iframe.contentWindow.document;
  doc.open();
  doc.write(html);
  doc.close();

  var waitUntilReady = function () {
    var img = doc.getElementById('plotStaticMap');

    if (!img) {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();
      return;
    }

    if (img.complete && img.naturalWidth > 0) {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();

      setTimeout(function () {
        if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
      }, 1500);
      return;
    }

    img.onload = function () {
      iframe.contentWindow.focus();
      iframe.contentWindow.print();

      setTimeout(function () {
        if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
      }, 1500);
    };

    img.onerror = function () {
      alert('Static map failed to load in print view.');
    };
  };

  setTimeout(waitUntilReady, 400);
}
    function buildSuggestedPlotName(plots) {
      var f = selectedFarmerId ? (farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId))) : null;
      if (!f) return 'e.g., North Field';

      var parts = [];
      if ((f.last_name || '').trim()) parts.push((f.last_name || '').trim());
      else parts.push(formatName(f));

      var nextNo = ((plots && plots.length) ? plots.length : 0) + 1;
      return 'e.g., ' + parts.join(' ') + ' Plot ' + nextNo;
    }

    function syncSuggestedPlotName(plots, fillIfEmpty) {
      if (!plotNameInputEl) return;
      plotNameInputEl.placeholder = buildSuggestedPlotName(plots);
      if (fillIfEmpty && !String(plotNameInputEl.value || '').trim()) {
        plotNameInputEl.value = buildSuggestedPlotName(plots).replace(/^e\.g\.,\s*/i, '');
      }
    }

    function findSelectedFarmerPlots() {
      if (!selectedFarmerId) return [];
      return plotsCacheByFarmerId.get(String(selectedFarmerId)) || [];
    }

    function getPlotById(plotId) {
      var plots = findSelectedFarmerPlots();
      for (var i9 = 0; i9 < plots.length; i9++) {
        if (String(plots[i9].id) === String(plotId)) return plots[i9];
      }
      return null;
    }

    function focusPlotById(plotId) {
      var plots = findSelectedFarmerPlots();
      for (var i10 = 0; i10 < plots.length; i10++) {
        if (String(plots[i10].id) !== String(plotId)) continue;
        var ring = normalizePolygonRing(plots[i10].polygon_json || plots[i10].polygon || plots[i10].polygonJson);
        if (ring && ring.length >= 3) zoomToRing(ring, 4.2, 700, 18000);
        return;
      }
    }

    function buildPlotSheetSvg(farmer, plot) {
      var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
      if (!ring || ring.length < 3) throw new Error('Plot has no valid polygon.');

var plotColor = getEffectivePlotColor(plot);
      var rgb = hexToRgb(plotColor);
      var areaHa = plot.area_ha != null ? Number(plot.area_ha) : estimateAreaHa(ring);

      var centroidLat = 0;
      var centroidLng = 0;
      for (var c2 = 0; c2 < ring.length; c2++) {
        centroidLat += ring[c2].lat;
        centroidLng += ring[c2].lng;
      }
      centroidLat /= ring.length;
      centroidLng /= ring.length;

      var width = 1600;
      var height = 1250;
      var mapX = 70, mapY = 170, mapW = 920, mapH = 470;
      var rightX = 1040, rightY = 170, rightW = 490, rightH = 740;

      var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
      for (var j3 = 0; j3 < ring.length; j3++) {
        minLat = Math.min(minLat, ring[j3].lat);
        maxLat = Math.max(maxLat, ring[j3].lat);
        minLng = Math.min(minLng, ring[j3].lng);
        maxLng = Math.max(maxLng, ring[j3].lng);
      }

      var pad = 70;
      var innerW = mapW - (pad * 2);
      var innerH = mapH - (pad * 2);

      function proj(pt) {
        var xNorm = (pt.lng - minLng) / ((maxLng - minLng) || 1);
        var yNorm = (maxLat - pt.lat) / ((maxLat - minLat) || 1);
        return {
          x: mapX + pad + (xNorm * innerW),
          y: mapY + pad + (yNorm * innerH)
        };
      }

      var points = [];
      for (var k2 = 0; k2 < ring.length; k2++) points.push(proj(ring[k2]));

      var polyPoints = points.map(function (p) {
        return p.x.toFixed(2) + ',' + p.y.toFixed(2);
      }).join(' ');

      var cx = 0, cy = 0;
      for (var n2 = 0; n2 < points.length; n2++) {
        cx += points[n2].x;
        cy += points[n2].y;
      }
      cx /= points.length;
      cy /= points.length;

      var coordinatePoints = openRing(ring);
      var rows = '';
      var maxRows = Math.min(coordinatePoints.length, 10);
      for (var r2 = 0; r2 < maxRows; r2++) {
        var y = 835 + (r2 * 28);
        rows += ''
          + '<line x1="85" y1="' + (y + 12) + '" x2="975" y2="' + (y + 12) + '" stroke="#dbe4ef" stroke-width="1"/>'
          + '<text x="110" y="' + y + '" font-size="16" font-weight="700" fill="#0f172a">P' + (r2 + 1) + '</text>'
          + '<text x="250" y="' + y + '" font-size="16" fill="#0f172a" font-family="monospace">' + coordinatePoints[r2].lat.toFixed(6) + '</text>'
          + '<text x="550" y="' + y + '" font-size="16" fill="#0f172a" font-family="monospace">' + coordinatePoints[r2].lng.toFixed(6) + '</text>';
      }
      if (coordinatePoints.length > maxRows) {
        rows += '<text x="110" y="1125" font-size="15" font-weight="700" fill="#64748b">+ ' + (coordinatePoints.length - maxRows) + ' additional corner point(s)</text>';
      }


      var createdAt = plot.created_at ? String(plot.created_at).split('T')[0] : '—';
      var farmerName = farmer ? formatName(farmer) : 'Selected Farmer';
      var farmerLocation = farmer && farmer.location ? farmer.location : '—';
      var farmerFfrs = farmer && farmer.ffrs ? farmer.ffrs : '—';
      var plotName = plot.name || ('Plot #' + plot.id);

      var svg = '';
      svg += '<svg xmlns="http://www.w3.org/2000/svg" width="' + width + '" height="' + height + '" viewBox="0 0 ' + width + ' ' + height + '">';
      svg += '<rect width="100%" height="100%" fill="#f4f7fb"/>';
      svg += '<rect x="20" y="20" width="1560" height="' + (height - 40) + '" rx="28" fill="#f8fbff" stroke="#d7e3f1" stroke-width="2"/>';

      svg += '<text x="60" y="90" font-size="42" font-weight="800" fill="#0f172a">Printable Land Plot Sheet</text>';
      svg += '<text x="60" y="125" font-size="20" fill="#64748b">Generated from your saved polygon data for download and printing.</text>';

      svg += '<rect x="60" y="145" width="135" height="34" rx="17" fill="#e8f0ff"/>';
      svg += '<text x="128" y="168" font-size="15" font-weight="700" text-anchor="middle" fill="#2563eb">LAND PLOT</text>';

      svg += '<rect x="' + mapX + '" y="' + mapY + '" width="' + mapW + '" height="' + mapH + '" rx="24" fill="#edf3f8" stroke="#d7e3f1" stroke-width="2"/>';

      for (var gx = mapX + 25; gx < mapX + mapW; gx += 55) {
        svg += '<line x1="' + gx + '" y1="' + (mapY + 15) + '" x2="' + gx + '" y2="' + (mapY + mapH - 15) + '" stroke="#d7e3f1" stroke-width="1"/>';
      }
      for (var gy = mapY + 15; gy < mapY + mapH; gy += 55) {
        svg += '<line x1="' + (mapX + 15) + '" y1="' + gy + '" x2="' + (mapX + mapW - 15) + '" y2="' + gy + '" stroke="#d7e3f1" stroke-width="1"/>';
      }

      svg += '<text x="85" y="210" font-size="34" font-weight="800" fill="#0f172a">Plot preview</text>';
      svg += '<text x="85" y="242" font-size="18" fill="#64748b">Polygon preview exported from the selected farmer plot.</text>';

      svg += '<polygon points="' + polyPoints + '" fill="rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',0.18)" stroke="' + plotColor + '" stroke-width="6"/>';

      svg += '<text x="' + (mapX + mapW - 70) + '" y="' + (mapY + 55) + '" font-size="28" font-weight="800" text-anchor="middle" fill="#0f172a">N</text>';
      svg += '<polygon points="' + (mapX + mapW - 70) + ',' + (mapY + 68) + ' ' + (mapX + mapW - 90) + ',' + (mapY + 110) + ' ' + (mapX + mapW - 50) + ',' + (mapY + 110) + '" fill="#0f172a"/>';

      svg += '<rect x="70" y="670" width="920" height="500" rx="22" fill="#ffffff" stroke="#dbe4ef" stroke-width="2"/>';
      svg += '<text x="85" y="710" font-size="30" font-weight="800" fill="#0f172a">Coordinates</text>';
      svg += '<text x="85" y="738" font-size="17" fill="#64748b">GPS corner points used to define this parcel.</text>';

      svg += '<rect x="82" y="760" width="895" height="42" rx="16" fill="#e8f0ff"/>';
      svg += '<text x="110" y="787" font-size="18" font-weight="700" fill="#2563eb">Point</text>';
      svg += '<text x="250" y="787" font-size="18" font-weight="700" fill="#2563eb">Latitude</text>';
      svg += '<text x="550" y="787" font-size="18" font-weight="700" fill="#2563eb">Longitude</text>';
      svg += rows;

      svg += '<rect x="' + rightX + '" y="' + rightY + '" width="' + rightW + '" height="' + rightH + '" rx="24" fill="#ffffff" stroke="#dbe4ef" stroke-width="2"/>';
      svg += '<text x="' + (rightX + 25) + '" y="' + (rightY + 45) + '" font-size="34" font-weight="800" fill="#0f172a">Plot summary</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 80) + '" width="205" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 110) + '" font-size="16" font-weight="700" fill="#64748b">Plot Name</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 145) + '" font-size="22" font-weight="800" fill="#0f172a">' + escapeXml(plotName) + '</text>';

      svg += '<rect x="' + (rightX + 255) + '" y="' + (rightY + 80) + '" width="210" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 110) + '" font-size="16" font-weight="700" fill="#64748b">Plot Color</text>';
      svg += '<rect x="' + (rightX + 270) + '" y="' + (rightY + 124) + '" width="28" height="28" rx="6" fill="' + plotColor + '"/>';
      svg += '<text x="' + (rightX + 308) + '" y="' + (rightY + 145) + '" font-size="24" font-weight="800" fill="#0f172a">' + plotColor + '</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 190) + '" width="205" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 220) + '" font-size="16" font-weight="700" fill="#64748b">Area</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 255) + '" font-size="24" font-weight="800" fill="#0f172a">' + areaHa.toFixed(2) + ' ha</text>';

      svg += '<rect x="' + (rightX + 255) + '" y="' + (rightY + 190) + '" width="210" height="90" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 220) + '" font-size="16" font-weight="700" fill="#64748b">Vertices</text>';
      svg += '<text x="' + (rightX + 270) + '" y="' + (rightY + 255) + '" font-size="24" font-weight="800" fill="#0f172a">' + coordinatePoints.length + ' corners</text>';

      svg += '<rect x="' + (rightX + 25) + '" y="' + (rightY + 305) + '" width="' + (rightW - 50) + '" height="405" rx="18" fill="#fbfdff" stroke="#dbe4ef"/>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 345) + '" font-size="16" font-weight="700" fill="#64748b">Farmer</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 377) + '" font-size="24" font-weight="800" fill="#0f172a">' + escapeXml(farmerName) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 395) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 395) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 430) + '" font-size="16" font-weight="700" fill="#64748b">FFRS</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 462) + '" font-size="22" fill="#0f172a">' + escapeXml(farmerFfrs) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 480) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 480) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 515) + '" font-size="16" font-weight="700" fill="#64748b">Location</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 547) + '" font-size="20" fill="#0f172a">' + escapeXml(farmerLocation) + '</text>';
      svg += '<line x1="' + (rightX + 40) + '" y1="' + (rightY + 565) + '" x2="' + (rightX + rightW - 40) + '" y2="' + (rightY + 565) + '" stroke="#dbe4ef"/>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 600) + '" font-size="16" font-weight="700" fill="#64748b">Centroid Lat</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 632) + '" font-size="22" fill="#0f172a" font-family="monospace">' + centroidLat.toFixed(6) + '</text>';

      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 675) + '" font-size="16" font-weight="700" fill="#64748b">Centroid Lng</text>';
      svg += '<text x="' + (rightX + 40) + '" y="' + (rightY + 707) + '" font-size="22" fill="#0f172a" font-family="monospace">' + centroidLng.toFixed(6) + '</text>';

      svg += '<text x="' + (rightX + 25) + '" y="' + (rightY + 735) + '" font-size="16" fill="#64748b">Created: ' + escapeXml(createdAt) + '</text>';

      svg += '<text x="60" y="' + (height - 45) + '" font-size="16" fill="#64748b">Generated from the selected plot. Suitable for download or print.</text>';
      svg += '<text x="1530" y="' + (height - 45) + '" font-size="16" font-weight="700" text-anchor="end" fill="#0f172a">PNG export</text>';

      svg += '</svg>';
      return svg;
    }

    function svgToPngBlob(svgMarkup, width, height) {
      width = width || 1600;
      height = height || 1000;

      return new Promise(function (resolve, reject) {
        var blob = new Blob([svgMarkup], { type: 'image/svg+xml;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var img = new Image();

        img.onload = function () {
          try {
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;

            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            canvas.toBlob(function (pngBlob) {
              URL.revokeObjectURL(url);
              if (!pngBlob) return reject(new Error('Could not create PNG.'));
              resolve(pngBlob);
            }, 'image/png', 1);
          } catch (err) {
            URL.revokeObjectURL(url);
            reject(err);
          }
        };

        img.onerror = function () {
          URL.revokeObjectURL(url);
          reject(new Error('Could not render SVG.'));
        };

        img.src = url;
      });
    }

async function downloadPlotSheetPng(farmer, plot) {
  var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
  if (!ring || ring.length < 3) {
    throw new Error('Plot has no valid polygon.');
  }

  var fileBase = safeFilename((farmer ? formatName(farmer) : 'farmer') + '_' + (plot.name || ('plot_' + plot.id)));
  var exportWidth = 2600;
  var exportPoints = openRing(ring);
  var exportVisibleRows = Math.min(exportPoints.length, 12);
  var exportHasMoreRows = exportPoints.length > exportVisibleRows;
  var exportTableHeight = 188 + (exportVisibleRows * 34) + (exportHasMoreRows ? 36 : 0);
  var exportHeight = Math.max(1700, 1180 + exportTableHeight + 160);

  function downloadBlob(blob, name) {
    return new Promise(function (resolve) {
      var a = document.createElement('a');
      var dlUrl = URL.createObjectURL(blob);
      a.href = dlUrl;
      a.download = name;
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () {
        URL.revokeObjectURL(dlUrl);
        resolve();
      }, 1500);
    });
  }

  function loadImage(url, allowFailure) {
    return new Promise(function (resolve, reject) {
      if (!url) {
        if (allowFailure) return resolve(null);
        return reject(new Error('Image URL is missing.'));
      }

      var img = new Image();
      if (/^https?:/i.test(url)) img.crossOrigin = 'anonymous';

      img.onload = function () { resolve(img); };
      img.onerror = function () {
        if (allowFailure) return resolve(null);
        reject(new Error('Failed to load image.'));
      };
      img.src = url;
    });
  }

  function roundedRectPath(ctx, x, y, w, h, r) {
    r = Math.max(0, Math.min(r, Math.min(w, h) / 2));
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function fillRoundedRect(ctx, x, y, w, h, r, color) {
    ctx.save();
    roundedRectPath(ctx, x, y, w, h, r);
    ctx.fillStyle = color;
    ctx.fill();
    ctx.restore();
  }

  function strokeRoundedRect(ctx, x, y, w, h, r, color, lineWidth) {
    ctx.save();
    roundedRectPath(ctx, x, y, w, h, r);
    ctx.strokeStyle = color;
    ctx.lineWidth = lineWidth || 1;
    ctx.stroke();
    ctx.restore();
  }

 function drawImageContain(ctx, img, x, y, w, h, bgColor) {
  var iw = img.naturalWidth || img.width;
  var ih = img.naturalHeight || img.height;

  if (bgColor) {
    ctx.fillStyle = bgColor;
    ctx.fillRect(x, y, w, h);
  }

  var scale = Math.min(w / iw, h / ih);
  var dw = iw * scale;
  var dh = ih * scale;
  var dx = x + (w - dw) / 2;
  var dy = y + (h - dh) / 2;

  ctx.drawImage(img, dx, dy, dw, dh);
}
  function drawWrappedText(ctx, text, x, y, maxWidth, lineHeight, maxLines) {
    text = String(text || '');
    var words = text.split(/\s+/).filter(Boolean);
    var lines = [];
    var line = '';

    for (var i = 0; i < words.length; i++) {
      var test = line ? (line + ' ' + words[i]) : words[i];
      if (ctx.measureText(test).width <= maxWidth || !line) {
        line = test;
      } else {
        lines.push(line);
        line = words[i];
      }
    }
    if (line) lines.push(line);

    if (maxLines && lines.length > maxLines) {
      lines = lines.slice(0, maxLines);
      var last = lines[lines.length - 1];
      while (last && ctx.measureText(last + '...').width > maxWidth) {
        last = last.slice(0, -1);
      }
      lines[lines.length - 1] = (last || '').trim() + '...';
    }

    for (var j = 0; j < lines.length; j++) {
      ctx.fillText(lines[j], x, y + (j * lineHeight));
    }

    return lines.length;
  }

  function drawLabelValueCard(ctx, x, y, w, h, label, value, colorSwatch) {
    fillRoundedRect(ctx, x, y, w, h, 26, '#ffffff');
    strokeRoundedRect(ctx, x, y, w, h, 26, '#dbe4ef', 2);

    ctx.fillStyle = '#64748b';
    ctx.font = '700 24px Arial';
    ctx.fillText(label, x + 28, y + 42);

    if (colorSwatch) {
      fillRoundedRect(ctx, x + 28, y + 58, 34, 34, 8, colorSwatch);
      ctx.fillStyle = '#0f172a';
      ctx.font = '800 34px Arial';
      ctx.fillText(String(value || ''), x + 76, y + 86);
      return;
    }

    ctx.fillStyle = '#0f172a';
    ctx.font = '800 36px Arial';
    drawWrappedText(ctx, String(value || ''), x + 28, y + 86, w - 56, 40, 2);
  }

  function drawFallbackPlot(ctx, ringPts, x, y, w, h, colorHex) {
    var pts = openRing(ringPts);
    if (!pts.length) return;

    var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    for (var i = 0; i < pts.length; i++) {
      minLat = Math.min(minLat, pts[i].lat);
      maxLat = Math.max(maxLat, pts[i].lat);
      minLng = Math.min(minLng, pts[i].lng);
      maxLng = Math.max(maxLng, pts[i].lng);
    }

    var pad = 70;
    var innerW = Math.max(10, w - (pad * 2));
    var innerH = Math.max(10, h - (pad * 2));

    ctx.save();
    roundedRectPath(ctx, x, y, w, h, 28);
    ctx.clip();

    ctx.fillStyle = '#eef4fb';
    ctx.fillRect(x, y, w, h);

    ctx.strokeStyle = '#d7e3f1';
    ctx.lineWidth = 1;
    for (var gx = x + 20; gx < x + w; gx += 70) {
      ctx.beginPath();
      ctx.moveTo(gx, y + 20);
      ctx.lineTo(gx, y + h - 20);
      ctx.stroke();
    }
    for (var gy = y + 20; gy < y + h; gy += 70) {
      ctx.beginPath();
      ctx.moveTo(x + 20, gy);
      ctx.lineTo(x + w - 20, gy);
      ctx.stroke();
    }

    var rgb = hexToRgb(colorHex);
    ctx.beginPath();
    for (var j = 0; j < pts.length; j++) {
      var px = x + pad + (((pts[j].lng - minLng) / ((maxLng - minLng) || 1)) * innerW);
      var py = y + pad + (((maxLat - pts[j].lat) / ((maxLat - minLat) || 1)) * innerH);
      if (j === 0) ctx.moveTo(px, py);
      else ctx.lineTo(px, py);
    }
    ctx.closePath();
    ctx.fillStyle = 'rgba(' + rgb.r + ',' + rgb.g + ',' + rgb.b + ',0.22)';
    ctx.fill();
    ctx.lineWidth = 8;
    ctx.strokeStyle = colorHex;
    ctx.stroke();

    ctx.restore();
  }

  function composeCanvas(mapImg, leftLogo, rightLogo) {
    var canvas = document.createElement('canvas');
    canvas.width = exportWidth;
    canvas.height = exportHeight;
    var ctx = canvas.getContext('2d');
var colorHex = getEffectivePlotColor(plot);
    var plotName = plot.name || ('Plot #' + plot.id);
    var farmerName = farmer ? formatName(farmer) : 'Selected Farmer';
    var farmerFfrs = farmer && farmer.ffrs ? farmer.ffrs : '-';
    var farmerLocation = farmer && farmer.location ? farmer.location : '-';
    var areaHa = plot.area_ha != null ? Number(plot.area_ha) : estimateAreaHa(ring);
    var createdAt = plot.created_at ? String(plot.created_at).split('T')[0] : '-';
    var vertexCount = openRing(ring).length;

    ctx.fillStyle = '#edf3f8';
    ctx.fillRect(0, 0, exportWidth, exportHeight);

    fillRoundedRect(ctx, 26, 26, exportWidth - 52, exportHeight - 52, 34, '#f8fbff');
    strokeRoundedRect(ctx, 26, 26, exportWidth - 52, exportHeight - 52, 34, '#d7e3f1', 2);

    if (leftLogo) ctx.drawImage(leftLogo, 72, 48, 82, 82);
    if (rightLogo) ctx.drawImage(rightLogo, exportWidth - 154, 48, 82, 82);

    ctx.fillStyle = '#0f172a';
    ctx.font = '800 42px Arial';
    ctx.fillText('LAND PLOT EXPORT', 190, 82);
    ctx.fillStyle = '#64748b';
    ctx.font = '500 24px Arial';
    ctx.fillText('High resolution downloaded plot image', 190, 118);

    fillRoundedRect(ctx, 72, 142, 168, 38, 19, '#e8f0ff');
    ctx.fillStyle = '#2563eb';
    ctx.font = '700 20px Arial';
    ctx.fillText('MAXIMIZED VIEW', 96, 168);

    var mapX = 72;
    var mapY = 210;
    var mapW = 1650;
    var mapH = 930;
    var sideX = 1760;
    var sideY = 210;
    var sideW = 760;
    var tableX = 72;
    var tableY = 1180;
    var tableW = 2448;
    var tableH = exportTableHeight;

    fillRoundedRect(ctx, mapX, mapY, mapW, mapH, 28, '#ffffff');
    strokeRoundedRect(ctx, mapX, mapY, mapW, mapH, 28, '#dbe4ef', 2);

    ctx.fillStyle = '#0f172a';
    ctx.font = '800 30px Arial';
    ctx.fillText(mapImg ? 'Satellite plot map' : 'Parcel boundary preview', mapX + 28, mapY + 44);
    ctx.fillStyle = '#64748b';
    ctx.font = '500 20px Arial';
    ctx.fillText(
      mapImg
        ? 'Satellite view focused on the selected land plot.'
        : 'Coordinate-grid fallback. The server-side satellite image was unavailable.',
      mapX + 28,
      mapY + 74
    );

    var mapInnerX = mapX + 24;
    var mapInnerY = mapY + 92;
    var mapInnerW = mapW - 48;
    var mapInnerH = mapH - 116;

    ctx.save();
    roundedRectPath(ctx, mapInnerX, mapInnerY, mapInnerW, mapInnerH, 24);
    ctx.clip();
    if (mapImg) {
     drawImageContain(ctx, mapImg, mapInnerX, mapInnerY, mapInnerW, mapInnerH, '#eef4fb');
    } else {
      drawFallbackPlot(ctx, ring, mapInnerX, mapInnerY, mapInnerW, mapInnerH, colorHex);
    }
    ctx.restore();
    strokeRoundedRect(ctx, mapInnerX, mapInnerY, mapInnerW, mapInnerH, 24, '#dbe4ef', 2);

    fillRoundedRect(ctx, mapX + mapW - 230, mapY + 30, 180, 40, 20, 'rgba(255,255,255,0.92)');
    strokeRoundedRect(ctx, mapX + mapW - 230, mapY + 30, 180, 40, 20, '#dbe4ef', 1.5);
    ctx.fillStyle = '#0f172a';
    ctx.font = '700 20px Arial';
    ctx.fillText('Area: ' + areaHa.toFixed(2) + ' ha', mapX + mapW - 210, mapY + 57);

    fillRoundedRect(ctx, sideX, sideY, sideW, 930, 28, '#ffffff');
    strokeRoundedRect(ctx, sideX, sideY, sideW, 930, 28, '#dbe4ef', 2);
    ctx.fillStyle = '#0f172a';
    ctx.font = '800 30px Arial';
    ctx.fillText('Plot summary', sideX + 28, sideY + 46);

    drawLabelValueCard(ctx, sideX + 24, sideY + 74, 330, 120, 'Plot Name', plotName);
    drawLabelValueCard(ctx, sideX + 382, sideY + 74, 330, 120, 'Plot Color', colorHex, colorHex);
    drawLabelValueCard(ctx, sideX + 24, sideY + 216, 330, 120, 'Area', areaHa.toFixed(2) + ' ha');
    drawLabelValueCard(ctx, sideX + 382, sideY + 216, 330, 120, 'Vertices', String(vertexCount));
    drawLabelValueCard(ctx, sideX + 24, sideY + 358, 688, 120, 'Farmer', farmerName);
    drawLabelValueCard(ctx, sideX + 24, sideY + 500, 688, 120, 'FFRS', farmerFfrs);
    drawLabelValueCard(ctx, sideX + 24, sideY + 642, 688, 150, 'Location', farmerLocation);
    drawLabelValueCard(ctx, sideX + 24, sideY + 814, 688, 88, 'Created', createdAt);

    fillRoundedRect(ctx, tableX, tableY, tableW, tableH, 28, '#ffffff');
    strokeRoundedRect(ctx, tableX, tableY, tableW, tableH, 28, '#dbe4ef', 2);
    ctx.fillStyle = '#0f172a';
    ctx.font = '800 28px Arial';
    ctx.fillText('Coordinates', tableX + 24, tableY + 42);
    ctx.fillStyle = '#64748b';
    ctx.font = '500 18px Arial';
    ctx.fillText('GPS corner points used to define this parcel.', tableX + 24, tableY + 70);

    var headerY = tableY + 94;
    fillRoundedRect(ctx, tableX + 18, headerY, tableW - 36, 48, 18, '#e8f0ff');
    ctx.fillStyle = '#2563eb';
    ctx.font = '700 19px Arial';
    ctx.fillText('Point', tableX + 48, headerY + 31);
    ctx.fillText('Latitude', tableX + 240, headerY + 31);
    ctx.fillText('Longitude', tableX + 710, headerY + 31);

    var pts = exportPoints;
    var maxRows = exportVisibleRows;
    var rowY = headerY + 72;
    ctx.font = '600 18px Arial';
    for (var r = 0; r < maxRows; r++) {
      var y = rowY + (r * 34);
      ctx.strokeStyle = '#dbe4ef';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(tableX + 24, y + 12);
      ctx.lineTo(tableX + tableW - 24, y + 12);
      ctx.stroke();

      ctx.fillStyle = '#0f172a';
      ctx.fillText('P' + (r + 1), tableX + 48, y);
      ctx.font = '16px monospace';
      ctx.fillText(Number(pts[r].lat).toFixed(6), tableX + 240, y);
      ctx.fillText(Number(pts[r].lng).toFixed(6), tableX + 710, y);
      ctx.font = '600 18px Arial';
    }

    if (pts.length > maxRows) {
      ctx.fillStyle = '#64748b';
      ctx.font = '600 18px Arial';
      ctx.fillText('... ' + (pts.length - maxRows) + ' more point(s)', tableX + 48, rowY + (maxRows * 34) + 8);
    }

    ctx.fillStyle = '#64748b';
    ctx.font = '500 18px Arial';
    ctx.fillText('Prepared By:', exportWidth - 700, exportHeight - 70);
    ctx.fillText('Certified By:', exportWidth - 360, exportHeight - 70);
    ctx.strokeStyle = '#0f172a';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(exportWidth - 560, exportHeight - 76);
    ctx.lineTo(exportWidth - 390, exportHeight - 76);
    ctx.moveTo(exportWidth - 215, exportHeight - 76);
    ctx.lineTo(exportWidth - 45, exportHeight - 76);
    ctx.stroke();

    return canvas;
  }

  function canvasToPngBlob(canvas) {
    return new Promise(function (resolve, reject) {
      try {
        canvas.toBlob(function (blob) {
          if (!blob) return reject(new Error('Could not create PNG.'));
          resolve(blob);
        }, 'image/png', 1);
      } catch (err) {
        reject(err);
      }
    });
  }

  try {
    var mapImg = await loadImage(buildStaticPlotMapUrl(plot), false);
    var leftLogo = await loadImage(PRINT_LEFT_LOGO, true);
    var rightLogo = await loadImage(PRINT_RIGHT_LOGO, true);

    var canvas = composeCanvas(mapImg, leftLogo, rightLogo);
    var pngBlob = await canvasToPngBlob(canvas);

    await downloadBlob(pngBlob, fileBase + '_max.png');
    return;
  } catch (err) {
    console.warn('Static map export failed, using the offline parcel preview.', err);
    toast(
      'Satellite imagery was unavailable, so the plot sheet used the offline boundary preview.',
      'warn'
    );
  }

  try {
    var fallbackLeftLogo = await loadImage(PRINT_LEFT_LOGO, true);
    var fallbackRightLogo = await loadImage(PRINT_RIGHT_LOGO, true);
    var fallbackCanvas = composeCanvas(null, fallbackLeftLogo, fallbackRightLogo);
    var fallbackCanvasBlob = await canvasToPngBlob(fallbackCanvas);
    await downloadBlob(fallbackCanvasBlob, fileBase + '_max.png');
    return;
  } catch (fallbackCanvasError) {
    console.warn('Offline canvas export failed, using the SVG renderer.', fallbackCanvasError);
  }

  var svg = buildPlotSheetSvg(farmer, plot);
  var fallbackBlob = await svgToPngBlob(svg, 2600, 2032);
  await downloadBlob(fallbackBlob, fileBase + '_max.png');
}



    async function handleDownloadPlot(plotId) {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }

      var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
      var plot = plotId ? getPlotById(plotId) : null;

      if (!plot) {
        var plots = findSelectedFarmerPlots();
        if (!plots.length) {
          toast('This farmer has no saved plots yet.', 'warn');
          return;
        }
        if (plots.length > 1) {
          toast('Use the Download button on a specific plot row.', 'warn');
          return;
        }
        plot = plots[0];
      }

      try {
        setStatus('Generating image…', 'Preparing printable plot sheet');
        await downloadPlotSheetPng(farmer, plot);
        setStatus('Ready', 'Plot image downloaded.');
        toast('Plot image downloaded.', 'ok');
      } catch (err) {
        console.error(err);
        setStatus('Export failed.', '');
        toast(err && err.message ? err.message : 'Could not export plot image.', 'bad');
      }
    }

 async function handleDownloadAllPlots() {
  if (!selectedFarmerId) {
    toast('Select a farmer first.', 'warn');
    return;
  }

  var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
  var plots = findSelectedFarmerPlots();

  if (!plots || !plots.length) {
    toast('This farmer has no saved plots yet.', 'warn');
    return;
  }

  var btn = document.getElementById('downloadAllPlotsBtn');
  if (btn) {
    btn.disabled = true;
    btn.textContent = 'Downloading...';
  }

  try {
    setStatus('Downloading all plot maps…', 'Preparing ' + plots.length + ' file(s)');
    toast('Starting download of ' + plots.length + ' plot map(s).', 'ok');

    for (var i = 0; i < plots.length; i++) {
      var plot = plots[i];

      setStatus(
        'Downloading all plot maps…',
        'Downloading ' + (i + 1) + ' of ' + plots.length + ': ' + (plot.name || ('Plot #' + plot.id))
      );

      await downloadPlotSheetPng(farmer, plot);

      await new Promise(function (resolve) {
        setTimeout(resolve, 900);
      });
    }

    setStatus('Ready', 'All plot maps downloaded.');
    toast('All plot maps downloaded.', 'ok');
  } catch (err) {
    console.error(err);
    setStatus('Batch download failed.', '');
    toast(err && err.message ? err.message : 'Could not download all plot maps.', 'bad');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.textContent = 'Download All Maps';
    }
  }
}

window.__handleDownloadAllPlots = handleDownloadAllPlots;

    function handlePrintPlot(plotId) {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }

      var farmer = farmersById.get(String(selectedFarmerId)) || dataById.get(String(selectedFarmerId));
      var plot = plotId ? getPlotById(plotId) : null;

      if (!plot) {
        var plots = findSelectedFarmerPlots();
        if (!plots.length) {
          toast('This farmer has no saved plots yet.', 'warn');
          return;
        }
        if (plots.length > 1) {
          toast('Use the Print button on a specific plot row.', 'warn');
          return;
        }
        plot = plots[0];
      }

      try {
        printPlotSheet(farmer, plot);
        toast('Print sheet opened.', 'ok');
      } catch (err) {
        console.error(err);
        toast('Could not open printable sheet.', 'bad');
      }
    }

  function updatePlotList(plots) {
  var list = document.getElementById('plotList');
  if (!list) return;

  if (!plots || !plots.length) {
    list.innerHTML = window.__canManageOperationalData
      ? '<div class="map-empty">No plots yet. Select a farmer, then click <b>Plot Land</b>.</div>'
      : '<div class="map-empty">No saved plots are available for this farmer.</div>';
    syncSuggestedPlotName([], false);
    return;
  }

  syncSuggestedPlotName(plots, false);

  var html = '';
  for (var i12 = 0; i12 < plots.length; i12++) {
    var pl = plots[i12];
    var name = pl.name || ('Plot #' + pl.id);
    var created = pl.created_at ? String(pl.created_at).split('T')[0] : '';
    var ring = normalizePolygonRing(pl.polygon_json || pl.polygon || pl.polygonJson);
    var ha = (pl.area_ha != null ? pl.area_ha : (pl.areaHa != null ? pl.areaHa : null));
    if (ha == null) ha = estimateAreaHa(ring);

    var sub = (created ? created : '—') + (' • ' + Number(ha || 0).toFixed(2) + ' ha');
    var c = pl.color ? String(pl.color) : "#22c55e";

    html +=
      '<div class="map-plot-item">' +
        '<div>' +
          '<div class="map-plot-name"><span class="plot-swatch" style="background:' + escapeHtml(c) + ';"></span>' + escapeHtml(name) + '</div>' +
          '<div class="map-plot-sub">' + escapeHtml(sub) + '</div>' +
        '</div>' +
        '<div class="map-plot-actions">' +
          '<button type="button" class="btn btn-soft btn-sm" data-action="focusPlot" data-plot-id="' + escapeHtml(pl.id) + '">Focus</button>' +
          (window.__canManageOperationalData ? '<button type="button" class="btn btn-soft btn-sm" data-action="editPlot" data-plot-id="' + escapeHtml(pl.id) + '">Edit</button>' : '') +
          '<button type="button" class="btn btn-soft btn-sm" data-action="downloadPlot" data-plot-id="' + escapeHtml(pl.id) + '">Download</button>' +
          '<button type="button" class="btn btn-soft btn-sm" data-action="printPlot" data-plot-id="' + escapeHtml(pl.id) + '">Print</button>' +
          (window.__canManageOperationalData ? '<button type="button" class="btn btn-soft btn-sm" data-action="deletePlot" data-plot-id="' + escapeHtml(pl.id) + '">Delete</button>' : '') +
        '</div>' +
      '</div>';
  }

  list.innerHTML = html;

  var btnsFocus = list.querySelectorAll('button[data-action="focusPlot"]');
  for (var j4 = 0; j4 < btnsFocus.length; j4++) {
    btnsFocus[j4].addEventListener('click', function () {
      var pid = this.getAttribute('data-plot-id');
      if (!pid) return;
      focusPlotById(pid);
    });
  }

  var btnsEdit = list.querySelectorAll('button[data-action="editPlot"]');
  for (var e = 0; e < btnsEdit.length; e++) {
    btnsEdit[e].addEventListener('click', function () {
      var pid = this.getAttribute('data-plot-id');
      if (!pid) return;
      startEditPlot(pid);
    });
  }

  var btnsDownload = list.querySelectorAll('button[data-action="downloadPlot"]');
  for (var d2 = 0; d2 < btnsDownload.length; d2++) {
    btnsDownload[d2].addEventListener('click', function () {
      var pid = this.getAttribute('data-plot-id');
      if (!pid) return;
      handleDownloadPlot(pid);
    });
  }

  var btnsPrint = list.querySelectorAll('button[data-action="printPlot"]');
  for (var p3 = 0; p3 < btnsPrint.length; p3++) {
    btnsPrint[p3].addEventListener('click', function () {
      var pid = this.getAttribute('data-plot-id');
      if (!pid) return;
      handlePrintPlot(pid);
    });
  }

  var btnsDelete = list.querySelectorAll('button[data-action="deletePlot"]');
  for (var k3 = 0; k3 < btnsDelete.length; k3++) {
    btnsDelete[k3].addEventListener('click', function () {
      var pid = this.getAttribute('data-plot-id');
      if (!pid || !selectedFarmerId) return;
      if (!confirm('Delete this plot?')) return;

      var deletingPlot = getPlotById(pid);
      if (!deletingPlot || !deletingPlot._record_version) {
        toast('The plot data is out of date. Reload the farmer before deleting.', 'warn');
        return;
      }

      setStatus('Deleting plot…', '');
      fetch("/farm-plots/" + encodeURIComponent(String(pid)), {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Accept": "application/json",
          "X-CSRF-TOKEN": csrfToken()
        },
        body: JSON.stringify({
          _record_version: deletingPlot._record_version
        })
      }).then(function (r) {
        return readJsonResponse(r, "Delete failed (" + r.status + ")");
      }).then(function () {
        toast("Plot deleted.", "ok");

        plotsCacheByFarmerId.delete(String(selectedFarmerId));
        return loadPlotsForSelectedFarmer(String(selectedFarmerId), { force: true, autoZoom: false });
      }).catch(function (err) {
        console.error(err);
        toast(err && err.message ? err.message : "Delete failed", "bad");
        setStatus('Delete failed.', '');
      });
    });
  }
}

    function loadPlotsForSelectedFarmer(farmerId, opts) {
      opts = opts || {};
      farmerId = String(farmerId);

      return fetchPlotsForFarmer(farmerId, opts).then(function (plots) {
        renderPlotsForFarmer(farmerId, plots);

        if (typeof window.__applyPlotVisibility === "function") {
          window.__applyPlotVisibility();
        }

        if (opts.autoZoom && plots && plots.length) {
          zoomToPlots(plots);
        }

        if (String(selectedFarmerId || "") === farmerId) {
          updatePlotCount(plots.length || 0);
          updatePlotTotalArea(sumPlotAreas(plots));
          updatePlotList(plots);
        }


        return plots;
      });
    }

  function showPlotButtons(on) {
  var elClear = document.getElementById('plotClearBtn');
  var elDeleteCorner = document.getElementById('plotDeleteCornerBtn');
  var elSave = document.getElementById('plotSaveBtn');
  var elCancel = document.getElementById('plotCancelBtn');

  if (elClear) elClear.style.display = on ? "" : "none";
  if (elDeleteCorner) elDeleteCorner.style.display = on ? "" : "none";
  if (elSave) elSave.style.display = on ? "" : "none";
  if (elCancel) elCancel.style.display = on ? "" : "none";
  if (plotModeBadge) plotModeBadge.style.display = on ? "" : "none";

  var draftInfo = document.getElementById('plotDraftInfo');
  if (draftInfo) draftInfo.style.display = on ? "" : "none";

  updateDeleteCornerButtonState();
}

    function fitToMarkers(onlyVisible) {
      var minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
      var count = 0;

      markersById.forEach(function (marker) {
        if (onlyVisible && !marker.isConnected) return;
        if (!marker.position) return;

        var ll = toLatLng(marker.position);
        minLat = Math.min(minLat, ll.lat);
        maxLat = Math.max(maxLat, ll.lat);
        minLng = Math.min(minLng, ll.lng);
        maxLng = Math.max(maxLng, ll.lng);
        count++;
      });

      if (count <= 0) return;

      var centerLat = (minLat + maxLat) / 2;
      var centerLng = (minLng + maxLng) / 2;
      var diag = haversineMeters(
        { lat: minLat, lng: minLng },
        { lat: maxLat, lng: maxLng }
      );
      var range = clamp(diag * 2.0, 2500, 1200000);
      flyTo(centerLat, centerLng, range, 1100);
    }

    window.__fit3dToVisibleMarkers = function () {
      fitToMarkers(false);
    };

window.__applyPlotVisibility = function () {
  var t = document.getElementById('togglePlots');
  var on = !t || t.checked;
  var selectedId = selectedFarmerId ? String(selectedFarmerId) : null;

  // show all saved plots while creating a new plot
  var showAllPlots = !!plotMode && !editingPlotId;

  for (var i13 = 0; i13 < savedPlotOverlays.length; i13++) {
    var it = savedPlotOverlays[i13];
    if (!it || !it.poly) continue;

var belongsToSelected = selectedId && String(it.farmerId) === selectedId;
var shouldShow = on && (!selectedId || belongsToSelected);

    var shouldShow = on && (
      showAllPlots ||       // while plotting a new land, show every farmer plot
      !selectedId ||        // if no selected farmer, show all
      belongsToSelected     // otherwise keep current selected-only behavior
    );

    try {
      if (shouldShow) {
        if (!it.poly.isConnected) map3d.append(it.poly);
        if (it.line && !it.line.isConnected) map3d.append(it.line);
      } else {
        if (it.poly.isConnected) map3d.removeChild(it.poly);
        if (it.line && it.line.isConnected) map3d.removeChild(it.line);
      }
    } catch (e6) {}
  }
};

  window.__reset3dMap = function () {
  popover.open = false;
  selectedFarmerId = null;
  selectedMarkerVisible = false;
  plotMode = false;

  setPlotModeUi(false);
  showPlotButtons(false);
  clearDraftPlot();

  if (hintEl) hintEl.style.display = "";
  flyTo(DEFAULT_CENTER.lat, DEFAULT_CENTER.lng, DEFAULT_RANGE, 900);

  setStatus("Ready", "Reset camera.");
  refreshAllMarkerPins();

  if (typeof window.__applyPlotVisibility === "function") {
    window.__applyPlotVisibility();
  }

  toast('Map reset.', 'ok');
};

    window.__focusSelectedFarmer = function () {
      if (!selectedFarmerId) {
        toast('Select a farmer first.', 'warn');
        return;
      }

      var marker = markersById.get(String(selectedFarmerId));
      if (!marker || !marker.position) return;

      var ll = toLatLng(marker.position);
      flyTo(ll.lat, ll.lng, 9000, 700);
    };

 window.__clearFarmerSelection = function () {
  popover.open = false;
  selectedFarmerId = null;
  selectedMarkerVisible = false;
  plotMode = false;

  setPlotModeUi(false);
  showPlotButtons(false);
  clearDraftPlot();
  refreshAllMarkerPins();

  if (typeof window.__applyPlotVisibility === "function") {
    window.__applyPlotVisibility();
  }

      if (hintEl) hintEl.style.display = "";
      syncSelectedPanel(null);

      updatePlotCount(0);
      updatePlotTotalArea(0);
      updatePlotList([]);
      setStatus("Ready", "Select a farmer.");

      if (plotNameInputEl) plotNameInputEl.value = '';
    };

 var plotMode = false;
var editingPlotId = null;
var plotVertices = [];
var draftDots = [];
var draftEdgeHandles = [];
var draftLine = null;
var draftPoly = null;
var btnDownloadAll = document.getElementById('downloadAllPlotsBtn');
if (btnDownloadAll) btnDownloadAll.disabled = false;

    function normalizeHex(h) {
      h = String(h || '').trim();
if (!h) return '#22c55e';
      if (h[0] !== '#') h = '#' + h;

      if (/^#([0-9a-fA-F]{3})$/.test(h) ||
          /^#([0-9a-fA-F]{6})$/.test(h) ||
          /^#([0-9a-fA-F]{8})$/.test(h)) {
        return h;
      }

      return '#3b82f6';
    }

    function getPlotColor() {
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');
var h = colorHexInput ? colorHexInput.value : (colorInput ? colorInput.value : '#22c55e');
      return normalizeHex(h);
    }

    function setPlotColor(h) {
      h = normalizeHex(h);
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');

      if (colorInput) colorInput.value = h.length === 9 ? h.slice(0, 7) : h;
      if (colorHexInput) colorHexInput.value = h;

      syncColorPresetState();
    }

    function randomColor() {
      var letters = '0123456789ABCDEF';
      var c3 = '#';
      for (var i14 = 0; i14 < 6; i14++) {
        c3 += letters[Math.floor(Math.random() * 16)];
      }
      return c3;
    }

    function syncColorPresetState() {
      var current = normalizeHex(getPlotColor()).slice(0, 7).toLowerCase();
      var chips = document.querySelectorAll('#plotColorPresets [data-color]');

      for (var i15 = 0; i15 < chips.length; i15++) {
        var chipColor = normalizeHex(chips[i15].getAttribute('data-color') || '').slice(0, 7).toLowerCase();
        if (chipColor === current) chips[i15].classList.add('is-active');
        else chips[i15].classList.remove('is-active');
      }
    }

    (function bindColorControls() {
      var colorInput = document.getElementById('plotColorInput');
      var colorHexInput = document.getElementById('plotColorHex');
      var colorRandomBtn = document.getElementById('plotColorRandomBtn');
      var presetBtns = document.querySelectorAll('#plotColorPresets [data-color]');

      if (colorInput) {
        colorInput.addEventListener('input', function () {
          setPlotColor(colorInput.value);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      if (colorHexInput) {
        colorHexInput.addEventListener('input', function () {
          setPlotColor(colorHexInput.value);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      if (colorRandomBtn) {
        colorRandomBtn.addEventListener('click', function () {
          setPlotColor(randomColor());
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      for (var i16 = 0; i16 < presetBtns.length; i16++) {
        presetBtns[i16].addEventListener('click', function () {
          var c4 = this.getAttribute('data-color') || '#3b82f6';
          setPlotColor(c4);
          if (plotMode && plotVertices.length) refreshDraftDots();
        });
      }

      syncColorPresetState();
    })();

    function updateDraftStats() {
      var areaEl = document.getElementById('plotDraftArea');
      var pointsEl = document.getElementById('plotDraftPoints');
      var ha = estimateAreaHa(plotVertices);

      if (areaEl) areaEl.textContent = (ha || 0).toFixed(2);
      if (pointsEl) pointsEl.textContent = plotVertices.length || 0;

      if (plotMode) {
        if (plotVertices.length === 0) {
          setStatus('Plot mode', 'Click the map to place point 1.');
        } else if (selectedVertexIndex >= 0) {
          setStatus('Adjusting plot', 'Corner ' + (selectedVertexIndex + 1) + ' selected.');
        } else if (plotVertices.length < 4) {
          setStatus('Plot mode', 'Point ' + plotVertices.length + ' added. Click point ' + (plotVertices.length + 1) + '.');
        } else {
          setStatus('Draft ready', 'Click a blue edge to add more points, or click a corner to move it.');
        }
      }
    }

function clearDraftDots() {
  for (var i17 = 0; i17 < draftDots.length; i17++) {
    try { if (draftDots[i17] && draftDots[i17].isConnected) map3d.removeChild(draftDots[i17]); } catch (e7) {}
  }
  draftDots = [];

  for (var j5 = 0; j5 < draftEdgeHandles.length; j5++) {
    try { if (draftEdgeHandles[j5] && draftEdgeHandles[j5].isConnected) map3d.removeChild(draftEdgeHandles[j5]); } catch (e8) {}
  }
  draftEdgeHandles = [];

  if (draftLine && draftLine.isConnected) {
    try { map3d.removeChild(draftLine); } catch (e9) {}
  }
  draftLine = null;

  if (draftPoly && draftPoly.isConnected) {
    try { map3d.removeChild(draftPoly); } catch (e10) {}
  }
  draftPoly = null;
}

    function renderDraftEdgeHandles() {
      if (!Polyline3DInteractiveElement) return;
      if (!plotVertices || plotVertices.length < 2) return;

      var edgeCount = plotVertices.length >= 3 ? plotVertices.length : (plotVertices.length - 1);

      for (var i18 = 0; i18 < edgeCount; i18++) {
        var a = plotVertices[i18];
        var b = plotVertices[(i18 + 1) % plotVertices.length];
        if (!a || !b) continue;

      var edge = new Polyline3DInteractiveElement({
  path: [a, b],
  strokeColor: "#3b82f601",
  outerColor: "#3b82f600",
  strokeWidth: 14,
  outerWidth: 0,
  altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
  drawsOccludedSegments: true
});

        edge.addEventListener("gmp-click", (function (edgeIndex, start, end) {
          return function (ev) {
            if (ev && ev.stopPropagation) ev.stopPropagation();
            if (!plotMode) return;

            var point = (ev && ev.position) ? toLatLng(ev.position) : midpointLatLng(start, end);
            insertDraftVertexAfter(edgeIndex, point.lat, point.lng);
          };
        })(i18, a, b));

        map3d.append(edge);
        draftEdgeHandles.push(edge);
      }
    }

    function refreshDraftDots() {
  clearDraftDots();

  var strokeHex = getPlotColor();
  var fillRgba = hexToRgba(strokeHex, 0.42);
  var draftStroke = hexAlpha(strokeHex, "CC");

  // visible line for Point 1 -> Point 2 -> Point 3...
  if (plotVertices.length >= 2 && Polyline3DInteractiveElement) {
    draftLine = new Polyline3DInteractiveElement({
      path: plotVertices,
      strokeColor: draftStroke,
      outerColor: "#ffffff00",
      strokeWidth: 4,
      outerWidth: 0,
      altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
      drawsOccludedSegments: true
    });
    map3d.append(draftLine);
  }

  // filled polygon once there are at least 3 points
  if (plotVertices.length >= 3 && Polygon3DElement) {
    draftPoly = new Polygon3DElement({
      path: plotVertices,
      strokeColor: draftStroke,
      strokeWidth: 1,
      fillColor: fillRgba,
      altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
      drawsOccludedSegments: true
    });
    map3d.append(draftPoly);
  }

for (var i19 = 0; i19 < plotVertices.length; i19++) {
  (function (idx) {
    var v = plotVertices[idx];
    var dot = new Marker3DElement({
      altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
      position: { lat: v.lat, lng: v.lng },
      title: "Corner " + (idx + 1),
      sizePreserved: true,
      drawsWhenOccluded: true
    });

    dot.replaceChildren(buildCornerHandleTemplate(idx === selectedVertexIndex));

    dot.addEventListener("click", function (ev) {
      if (ev && ev.stopPropagation) ev.stopPropagation();
      if (!plotMode) return;

      selectedVertexIndex = idx;
      toast(
        "Selected corner " + (idx + 1) + ". Click the map to move it, use arrow keys, or press Delete to remove it.",
        "ok"
      );
      refreshDraftDots();
    });

    map3d.append(dot);
    draftDots.push(dot);
  })(i19);
}

  renderDraftEdgeHandles();
  updateDraftStats();
  updateDeleteCornerButtonState();
}

  function clearDraftPlot() {
  plotVertices = [];
  selectedVertexIndex = -1;
  clearDraftDots();
  updateDraftStats();
  updateDeleteCornerButtonState();
}

    function resetPlotEditorFields() {
  editingPlotId = null;

  if (plotNameInputEl) {
    plotNameInputEl.value = '';
  }

setPlotColor('#22c55e');

  var btnSave = document.getElementById('plotSaveBtn');
  if (btnSave) btnSave.textContent = 'Save Plot';

  var badge = document.getElementById('plotModeBadge');
  if (badge) badge.textContent = 'Plotting Mode Active';
}

function startEditPlot(plotId) {
  var plot = getPlotById(plotId);
  if (!plot) {
    toast('Plot not found.', 'warn');
    return;
  }

  var ring = normalizePolygonRing(plot.polygon_json || plot.polygon || plot.polygonJson);
  if (!ring || ring.length < 3) {
    toast('This plot has no valid polygon.', 'warn');
    return;
  }

  clearDraftPlot();

  plotMode = true;
  editingPlotId = String(plot.id);
  selectedVertexIndex = -1;

  setPlotModeUi(true);
  showPlotButtons(true);

  // Hide all saved polygons while editing to reduce lag
  setAllSavedPlotsVisible(false);

  plotVertices = openRing(ring).map(function (p) {
    return { lat: Number(p.lat), lng: Number(p.lng) };
  });

  if (plotNameInputEl) {
    plotNameInputEl.value = plot.name || '';
  }

  setPlotColor(plot.color || '#22c55e');
  refreshDraftDots();

  var btnSave = document.getElementById('plotSaveBtn');
  if (btnSave) btnSave.textContent = 'Update Plot';

  var badge = document.getElementById('plotModeBadge');
  if (badge) badge.textContent = 'Editing Plot';

  if (hintEl) hintEl.style.display = 'none';

  zoomToRing(ring, 4.2, 700, 18000);
  setStatus('Edit mode', 'Adjust the plot, then click Update Plot.');
  toast('Editing plot: ' + (plot.name || ('Plot #' + plot.id)), 'ok');
}
function updateDeleteCornerButtonState() {
  var btn = document.getElementById('plotDeleteCornerBtn');
  if (!btn) return;

  var hasSelectedCorner = selectedVertexIndex >= 0 && selectedVertexIndex < plotVertices.length;
  var canDelete = hasSelectedCorner && plotVertices.length > 3;

  btn.disabled = !canDelete;

  if (!hasSelectedCorner) {
    btn.title = 'Select a corner first';
  } else if (plotVertices.length <= 3) {
    btn.title = 'A plot must keep at least 3 corners';
  } else {
    btn.title = 'Delete selected corner';
  }
}

function deleteSelectedVertex() {
  if (selectedVertexIndex < 0 || selectedVertexIndex >= plotVertices.length) {
    toast("Select a corner first.", "warn");
    return;
  }

  if (plotVertices.length <= 3) {
    toast("A plot must keep at least 3 corners.", "warn");
    return;
  }

  var removedCornerNo = selectedVertexIndex + 1;

  plotVertices.splice(selectedVertexIndex, 1);
  selectedVertexIndex = -1;

  refreshDraftDots();
  toast("Deleted corner " + removedCornerNo + ".", "ok");
}
 function moveSelectedVertex(lat, lng) {
  if (selectedVertexIndex < 0 || selectedVertexIndex >= plotVertices.length) return;

  var candidate = plotVertices.slice();
  candidate[selectedVertexIndex] = { lat: lat, lng: lng };

  if (!canUseDraftVertices(candidate, false)) {
    return;
  }

  plotVertices = candidate;
  selectedVertexIndex = -1;

  refreshDraftDots();
  updateDeleteCornerButtonState();
  toast("Moved corner.", "ok");
}

function insertDraftVertexAfter(edgeIndex, lat, lng) {
  edgeIndex = Number(edgeIndex);
  if (edgeIndex < 0 || edgeIndex >= plotVertices.length) return;

  var candidate = plotVertices.slice();
  candidate.splice(edgeIndex + 1, 0, { lat: lat, lng: lng });

  if (!canUseDraftVertices(candidate, false)) {
    return;
  }

  plotVertices = candidate;
  selectedVertexIndex = edgeIndex + 1;

  refreshDraftDots();
  toast("New point added on the line. Click the map to move it if needed.", "ok");
}

function addDraftVertex(lat, lng) {
  var candidate = plotVertices.slice();
  candidate.push({ lat: lat, lng: lng });

  if (!canUseDraftVertices(candidate, false)) {
    return;
  }

  plotVertices = candidate;
  selectedVertexIndex = -1;
  refreshDraftDots();

  if (plotVertices.length < 4) {
    toast("Point " + plotVertices.length + " added.", "ok");
  } else {
    setStatus("Draft ready", "Use an edge to add more points or click a corner to move it.");
  }
}

function nudgeSelectedVertex(latStep, lngStep) {
  if (selectedVertexIndex < 0 || selectedVertexIndex >= plotVertices.length) return;

  var candidate = plotVertices.slice();
  candidate[selectedVertexIndex] = {
    lat: candidate[selectedVertexIndex].lat + latStep,
    lng: candidate[selectedVertexIndex].lng + lngStep
  };

  if (!canUseDraftVertices(candidate, false)) {
    return;
  }

  plotVertices = candidate;
  refreshDraftDots();
}
    function setPlotModeUi(on) {
      var module = document.getElementById('farmersMapModule');
      if (!module) return;

      if (on) module.classList.add('is-plot-mode');
      else module.classList.remove('is-plot-mode');
    }

 function enterPlotMode() {
  if (!selectedFarmerId) {
    toast("Select a farmer first, then plot.", "warn");
    return;
  }

  plotMode = true;
  editingPlotId = null;
  setPlotModeUi(true);
  showPlotButtons(true);
  clearDraftPlot();
  resetPlotEditorFields();

  if (typeof window.__applyPlotVisibility === "function") {
    window.__applyPlotVisibility();
  }

  if (hintEl) hintEl.style.display = "none";

  var plots = findSelectedFarmerPlots();
  syncSuggestedPlotName(plots, true);

  setStatus("Plot mode", "Click the map to place point 1 of 4.");
  toast("Plot mode enabled. Click 4 points on the map.", "ok");
}

    var moduleEl = document.getElementById('farmersMapModule');
    var stageEl = document.querySelector('#farmersMapModule .farmers-map-stage');
    var cursorEl = document.getElementById('plotCursor');

    function updateFakeCursor(e) {
      if (!moduleEl || !cursorEl || !stageEl) return;
      if (!moduleEl.classList.contains('is-plot-mode')) return;

      var r3 = stageEl.getBoundingClientRect();
      var x = e.clientX - r3.left;
      var y = e.clientY - r3.top;

      x = Math.max(0, Math.min(r3.width, x));
      y = Math.max(0, Math.min(r3.height, y));

      cursorEl.style.left = x + "px";
      cursorEl.style.top = y + "px";
    }

    if (stageEl) {
      stageEl.addEventListener('pointermove', updateFakeCursor, true);
      stageEl.addEventListener('mousemove', updateFakeCursor, true);
      stageEl.addEventListener('mouseenter', function (e) { updateFakeCursor(e); }, true);
      stageEl.addEventListener('mouseleave', function () {
        if (!cursorEl) return;
        cursorEl.style.left = "-9999px";
        cursorEl.style.top = "-9999px";
      }, true);
    }

var btnPlotMode = document.getElementById('plotModeBtn');
var btnClear2 = document.getElementById('plotClearBtn');
var btnDeleteCorner = document.getElementById('plotDeleteCornerBtn');
var btnSave = document.getElementById('plotSaveBtn');
var btnCancel = document.getElementById('plotCancelBtn');

    if (btnPlotMode) {
      btnPlotMode.addEventListener('click', function () {
        enterPlotMode();
      });
    }

    if (btnDeleteCorner) {
  btnDeleteCorner.addEventListener('click', function () {
    deleteSelectedVertex();
  });
}

if (btnCancel) {
  btnCancel.addEventListener('click', function () {
    plotMode = false;
    editingPlotId = null;
    selectedVertexIndex = -1;

    setPlotModeUi(false);
    showPlotButtons(false);
    clearDraftPlot();
    resetPlotEditorFields();

    restoreSavedPlotsVisibility();

    setStatus('Ready', selectedFarmerId ? 'Selected farmer loaded.' : 'Select a farmer.');
    toast("Plot cancelled.", "warn");
  });
}

    if (btnClear2) {
      btnClear2.addEventListener('click', function () {
        clearDraftPlot();
        toast("Draft cleared.", "warn");
      });
    }

 if (btnSave) {
  btnSave.addEventListener('click', function () {
    if (!selectedFarmerId) return;

    if (plotVertices.length < 3) {
      toast("Please place at least 3 points first.", "warn");
      return;
    }

    if (!canUseDraftVertices(plotVertices, false)) {
      return;
    }

    var plotName = (plotNameInputEl && plotNameInputEl.value) ? String(plotNameInputEl.value).trim() : '';
    if (!plotName) plotName = "Plot";

    var plotColor = getPlotColor();

    var isEditing = !!editingPlotId;
    var currentEditingPlotId = editingPlotId;
    var editingPlot = isEditing ? getPlotById(editingPlotId) : null;

    if (isEditing && (!editingPlot || !editingPlot._record_version)) {
      toast('The plot data is out of date. Reload the farmer before saving.', 'warn');
      return;
    }

    var url = isEditing
      ? ("/farm-plots/" + encodeURIComponent(String(editingPlotId)))
      : ("/farmers/" + encodeURIComponent(String(selectedFarmerId)) + "/plots");

    var method = isEditing ? "PUT" : "POST";

    setStatus(isEditing ? "Updating plot…" : "Saving plot…", "");
    toast(isEditing ? "Updating plot…" : "Saving plot…", "ok");

    fetch(url, {
      method: method,
      headers: {
        "Content-Type": "application/json",
        "Accept": "application/json",
        "X-CSRF-TOKEN": csrfToken()
      },
      body: JSON.stringify({
        name: plotName,
        color: plotColor,
        polygon: plotVertices,
        _record_version: editingPlot ? editingPlot._record_version : null
      })
    }).then(function (r) {
      return readJsonResponse(
        r,
        (isEditing ? "Update" : "Save") + " failed (" + r.status + ")"
      );
    }).then(function () {
  plotMode = false;
  editingPlotId = null;
  selectedVertexIndex = -1;

  setPlotModeUi(false);
  showPlotButtons(false);
  clearDraftPlot();
  resetPlotEditorFields();

  setStatus(isEditing ? "Updated ✅" : "Saved ✅", isEditing ? "Plot updated." : "Plot added.");
  toast(isEditing ? "Updated plot ✅" : "Saved plot ✅", "ok");

  restoreSavedPlotsVisibility();

  plotsCacheByFarmerId.delete(String(selectedFarmerId));
  return loadPlotsForSelectedFarmer(String(selectedFarmerId), { force: true, autoZoom: true });
}).then(function (plots) {
      syncSuggestedPlotName(plots || [], false);
      if (plotNameInputEl) plotNameInputEl.value = '';
    }).catch(function (err) {
      console.error(err);

      if (currentEditingPlotId) {
        setSavedPlotOverlayVisible(currentEditingPlotId, true);
      }

      toast(err && err.message ? err.message : (isEditing ? "Update failed" : "Save failed"), "bad");
      setStatus(isEditing ? "Update failed." : "Save failed.", "");
    });
  });
}

    document.addEventListener('keydown', function (e) {
      if (!plotMode) return;

      var active = document.activeElement;
      var typing = active && /^(input|textarea)$/i.test(active.tagName);

      if (e.key === 'Escape') {
        e.preventDefault();
        if (btnCancel) btnCancel.click();
        return;
      }

      if (e.key === 'Enter') {
        if (typing && active && active.id === 'plotNameInput') return;
        e.preventDefault();
        if (btnSave) btnSave.click();
        return;
      }

      if (typing) return;

    if ((e.key === 'Delete' || e.key === 'Del') && selectedVertexIndex >= 0) {
  e.preventDefault();
  deleteSelectedVertex();
  return;
}

if (e.key === 'Backspace' && selectedVertexIndex >= 0) {
  e.preventDefault();
  deleteSelectedVertex();
  return;
}

if (e.key === 'Backspace') {
  e.preventDefault();
  if (btnClear2) btnClear2.click();
  return;
}

if (selectedVertexIndex < 0) return;

      var step = e.shiftKey ? 0.00008 : 0.00002;
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        nudgeSelectedVertex(step, 0);
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        nudgeSelectedVertex(-step, 0);
      } else if (e.key === 'ArrowLeft') {
        e.preventDefault();
        nudgeSelectedVertex(0, -step);
      } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        nudgeSelectedVertex(0, step);
      }
    });

  map3d.addEventListener("gmp-click", function (ev) {
  if (plotMode) {
    var pos = ev.position;
    if (!pos) return;

    var ll = toLatLng(pos);

    if (selectedVertexIndex >= 0) {
      moveSelectedVertex(ll.lat, ll.lng);
      return;
    }

    // only allow direct point adding during new plot creation
    if (!editingPlotId && plotVertices.length < 4) {
      addDraftVertex(ll.lat, ll.lng);
      return;
    }

    // no toast here, just keep the status text
    setStatus("Draft ready", "Click an edge to add points or click a corner to move it.");
    return;
  }

  popover.open = false;
});

    function createFarmerMarker(f, lat, lng) {
      dataById.set(String(f.id), f);

      var marker = new Marker3DInteractiveElement({
        altitudeMode: AltitudeMode.CLAMP_TO_GROUND,
        position: { lat: lat, lng: lng },
        title: formatName(f),
        sizePreserved: true,
        drawsWhenOccluded: true
      });

      applyMarkerPin(marker, f, false);

    marker.addEventListener("gmp-click", function (event) {
  if (event && event.stopPropagation) event.stopPropagation();
  window.__openFarmer3d(String(f.id), { showMarker: false });
});

      markersById.set(String(f.id), marker);
      queuePlotFetch(String(f.id));

      if (typeof window.__applyMarkerVisibility === "function") {
        window.__applyMarkerVisibility();
      }

      return marker;
    }

  function ensureMarkerForFarmer(id) {
  id = String(id);

  if (markersById.has(id)) {
    return Promise.resolve(markersById.get(id));
  }

  if (geocodePromisesById.has(id)) {
    return geocodePromisesById.get(id);
  }

  var p = ensureFarmerData(id).then(function (f) {
    var loc = (f.location || '').trim();

    if (!loc || loc === '—') {
      return null;
    }

    var endpoint = window.__farmerGeocodeUrl || '/api/geocode';
    var separator = endpoint.indexOf('?') >= 0 ? '&' : '?';

    return fetch(
      endpoint + separator + 'q=' + encodeURIComponent(loc + ', Philippines'),
      { headers: { 'Accept': 'application/json' } }
    )
      .then(function (response) {
        if (!response.ok) return null;
        return response.json();
      })
      .then(function (result) {
        if (!result || result.lat == null || result.lng == null) return null;
        return createFarmerMarker(f, Number(result.lat), Number(result.lng));
      })
      .catch(function () {
        return null;
      });
  });

  geocodePromisesById.set(id, p);
  p.finally(function () {
    geocodePromisesById.delete(id);
  });

  return p;
}

window.__openFarmer3d = function (id, opts) {
  opts = opts || {};
  id = String(id);

  var showMarker = opts.showMarker !== false;
  var markerPromise = ensureMarkerForFarmer(id).catch(function () {
    return null;
  });

  return ensureFarmerData(id).then(function (f) {
    if (!f) {
      throw new Error('Farmer not found.');
    }

    selectedFarmerId = id;
    selectedVertexIndex = -1;
    selectedMarkerVisible = showMarker;

    highlightRow(f.id);
    syncSelectedPanel(f);

    if (typeof window.__applyPlotVisibility === 'function') {
      window.__applyPlotVisibility();
    }

    if (hintEl) hintEl.style.display = 'none';

    return Promise.all([
      loadPlotsForSelectedFarmer(selectedFarmerId, { autoZoom: false }),
      markerPromise
    ]).then(function (results) {
      var plots = Array.isArray(results[0]) ? results[0] : [];
      var marker = results[1];
      refreshAllMarkerPins();

      if (!plots.length) {
        popover.open = false;
        setStatus('No saved plot', 'This farmer has not been plotted yet.');
        toast(formatName(f) + ' has no saved plot yet.', 'warn');
        syncSuggestedPlotName([], false);
        return;
      }

    if (marker && marker.position) {
  var ll = toLatLng(marker.position);
  flyTo(ll.lat, ll.lng, 9000, 900);

  if (showMarker) {
    popover.positionAnchor = marker;
    popover.open = true;

    var html = document.createElement('div');
    html.style.minWidth = '260px';
    html.style.fontFamily = 'ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial';
    html.innerHTML =
      '<div style="font-weight:900; font-size:14px; margin-bottom:6px;">' +
        escapeHtml(formatName(f)) +
      '</div>' +
      '<div style="font-size:12px; line-height:1.35; color:#0b1220;">' +
        '<div><strong>FFRS:</strong> ' + escapeHtml(f.ffrs || '—') + '</div>' +
        '<div><strong>DOB:</strong> ' + escapeHtml(f.date_of_birth || '—') + '</div>' +
        '<div><strong>Gender:</strong> ' + escapeHtml(f.gender || '—') + '</div>' +
        '<div style="margin-top:6px;"><strong>Location:</strong> ' + escapeHtml((f.location || '').trim()) + '</div>' +
        '<div style="margin-top:6px;"><strong>Records:</strong> ' + Number(f.records_count || 0) + '</div>' +
        '<div><strong>Total Kgs:</strong> ' + (Number(f.total_kgs || 0)).toFixed(2) + '</div>' +
        '<div><strong>Last Received:</strong> ' + escapeHtml(f.last_received || '—') + '</div>' +
      '</div>';

    popover.replaceChildren(html);
  } else {
    popover.open = false;
  }
} else {
  popover.open = false;
}

      zoomToPlots(plots);

      setStatus('Selected: ' + (f.last_name || 'Farmer'), 'Plot owner loaded');
      toast('Selected ' + formatName(f) + '.', 'ok');
      syncSuggestedPlotName(plots, false);
    });
  }).catch(function (err) {
    console.error(err);
    toast(err && err.message ? err.message : 'Could not open farmer.', 'bad');
  });
};

    setPlotModeUi(false);
    showPlotButtons(false);
    syncSelectedPanel(null);
    updatePlotCount(0);
    updatePlotTotalArea(0);
    updatePlotList([]);
    syncSuggestedPlotName([], false);

  setPlotModeUi(false);
showPlotButtons(false);
syncSelectedPanel(null);
updatePlotCount(0);
updatePlotTotalArea(0);
updatePlotList([]);
syncSuggestedPlotName([], false);

setStatus('Loading 3D map…', 'Loading all saved plots…');
setProgress(0);

if (mapGeocodedPillEl) {
  mapGeocodedPillEl.textContent = 'Loading plots…';
}

try {
  await loadAllMunicipalPlots();
  zoomToAllLoadedPlots();
} catch (err) {
  console.error(err);
  setStatus('Plot loading failed.', '');
  toast(err && err.message ? err.message : 'Could not load all plots.', 'bad');
}
  }
</script>
@endpush
