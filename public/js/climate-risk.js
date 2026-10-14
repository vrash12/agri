(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else { root.ClimateRisk = api; api.mount(root.document, root, root.__climateRiskConfig || {}); }
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';
  var levels = new Set(['normal', 'watch', 'alert', 'critical', 'unassessed']);

  function rainfallSummary(forecast) {
    var days = Array.isArray(forecast.daily) ? forecast.daily.slice(0, 7) : [];
    var valid = days.filter(function (day) {
      return typeof day.precipitation_sum === 'number' && Number.isFinite(day.precipitation_sum) && day.precipitation_sum >= 0;
    });
    return {days: days, complete: valid.length === 7 && days.length === 7,
      total: valid.length === 7 ? Math.round(valid.reduce(function (sum, day) { return sum + day.precipitation_sum; }, 0) * 10) / 10 : null};
  }

  async function request(win, url) {
    var controller = new win.AbortController();
    var timer = win.setTimeout(function () { controller.abort(); }, 22000);
    try {
      var response = await win.fetch(url, {credentials: 'same-origin', signal: controller.signal, headers: {Accept: 'application/json'}});
      if (!response.ok) {
        var message = response.status === 401 || response.status === 419 ? 'Your session expired. Sign in again to continue.'
          : response.status === 403 ? 'Your account cannot view this information.'
          : response.status === 429 ? 'Requests are temporarily limited. Wait before trying again.'
          : 'This information could not load. Try again shortly.';
        throw new Error(message);
      }
      if (!(response.headers.get('Content-Type') || '').includes('application/json')) throw new Error('Sign in again, then retry this request.');
      return await response.json();
    } catch (error) {
      if (error.name === 'AbortError') throw new Error('The request took too long. Try again shortly.');
      if (error instanceof TypeError) throw new Error('The connection was interrupted. Check your connection, then retry.');
      throw error;
    } finally { win.clearTimeout(timer); }
  }

  function mapsLibrary(win, doc, key) {
    if (win.google?.maps?.importLibrary) return win.google.maps.importLibrary('maps');
    if (!key) return Promise.reject(new Error('Map access needs administrator setup. The area register remains available.'));
    if (win.__climateMapsLoad) return win.__climateMapsLoad;
    win.__climateMapsLoad = new Promise(function (resolve, reject) {
      var script = doc.createElement('script');
      var timeout = win.setTimeout(function () { reject(new Error('The map service took too long. Try again later.')); }, 20000);
      win.__climateMapsReady = function () { win.clearTimeout(timeout); resolve(win.google.maps); };
      script.src = 'https://maps.googleapis.com/maps/api/js?' + new URLSearchParams({key: key, loading: 'async', callback: '__climateMapsReady'}).toString();
      script.nonce = doc.querySelector('script[nonce]')?.nonce || '';
      script.onerror = function () { win.clearTimeout(timeout); reject(new Error('The map service is unavailable. The area register remains available.')); };
      doc.head.appendChild(script);
    });
    return win.__climateMapsLoad;
  }

  function mount(doc, win, config) {
    var map = null, markers = [], polygons = [], info = null;
    function node(tag, text) { var el = doc.createElement(tag); if (text !== undefined) el.textContent = text; return el; }
    function bindLoad(buttonId, work, resultNode) {
      var button = doc.getElementById(buttonId);
      if (!button) return;
      button.addEventListener('click', async function () {
        if (button.disabled) return;
        var label = button.textContent;
        button.disabled = true; button.textContent = 'Loading…';
        resultNode.setAttribute('aria-busy', 'true');
        try { var nextLabel = await work(); if (typeof nextLabel === 'string') label = nextLabel; }
        catch (error) { resultNode.textContent = error.message; }
        finally { button.disabled = false; button.textContent = label; resultNode.removeAttribute('aria-busy'); }
      });
    }
    var rainfall = doc.getElementById('climateRainfall');
    if (rainfall && config.rainfallUrl) bindLoad('loadClimateRainfall', async function () {
      var forecast = await request(win, config.rainfallUrl);
      rainfall.replaceChildren();
      if (!forecast.available) { rainfall.textContent = forecast.status_message || 'Forecast unavailable. Review the PAGASA source links below.'; return; }
      var summary = rainfallSummary(forecast);
      var amount = node('div', summary.complete ? summary.total.toFixed(1) + ' mm' : 'Rainfall data incomplete');
      amount.className = 'climate-rain-total'; rainfall.append(amount);
      rainfall.append(node('p', 'Forecast total · ' + (summary.days[0]?.date || 'Unknown start') + ' to ' + (summary.days.at(-1)?.date || 'Unknown end')));
      rainfall.append(node('small', 'Open-Meteo · Retrieved ' + (forecast.fetched_at || 'date unavailable') + (forecast.is_stale ? ' · Cached earlier forecast; review its dates.' : '')));
      var days = node('div'); days.className = 'climate-rain-days';
      summary.days.forEach(function (day) {
        var card = node('div'); card.className = 'climate-rain-day';
        card.append(node('small', day.day_label || day.date));
        card.append(node('strong', typeof day.precipitation_sum === 'number' && Number.isFinite(day.precipitation_sum) && day.precipitation_sum >= 0 ? day.precipitation_sum.toFixed(1) + ' mm' : 'Not available'));
        days.append(card);
      }); rainfall.append(days);
    }, rainfall);

    var status = doc.getElementById('climateMapStatus'), host = doc.getElementById('climateMap');
    if (host && status) bindLoad('loadClimateMap', async function () {
      var payload = await request(win, config.mapUrl);
      if (!Array.isArray(payload.features) || payload.features.length > 80 || !Array.isArray(payload.parcels) || payload.parcels.length > 80) throw new Error('The map data needs a reload. Try again.');
      if (payload.features.length === 0) { host.hidden = true; status.textContent = 'No municipalities match this risk filter.'; return; }
      await mapsLibrary(win, doc, config.mapsKey);
      var maps = win.google.maps;
      host.hidden = false;
      if (!map) {
        map = new maps.Map(host, {center: {lat: 12.88, lng: 121.77}, zoom: 6, mapTypeId: 'hybrid', streetViewControl: false, gestureHandling: 'cooperative'});
        info = new maps.InfoWindow();
        map.data.addListener('click', function (event) {
          var content = node('div'), name = event.feature.getProperty('name') || 'Area';
          content.append(node('strong', name));
          content.append(node('p', event.feature.getProperty('label') || 'Not assessed'));
          content.append(node('p', 'Observed: ' + (event.feature.getProperty('observed_on') || 'No current assessment') + ' · Review by: ' + (event.feature.getProperty('review_on') || '—')));
          if (event.feature.getProperty('kind') === 'municipality') {
            var link = node('a', 'Open area'); link.href = config.areaUrl + '?municipality_id=' + encodeURIComponent(event.feature.getId()); content.append(link);
          }
          info.setContent(content); info.setPosition(event.latLng); info.open(map);
        });
      }
      info.close(); map.data.forEach(function (feature) { map.data.remove(feature); });
      markers.forEach(function (marker) { marker.setMap(null); }); markers = [];
      polygons.forEach(function (polygon) { polygon.setMap(null); }); polygons = [];
      var bounds = new maps.LatLngBounds();
      payload.features.forEach(function (feature) {
        var props = feature.properties || {};
        if (!levels.has(props.level)) props.level = 'unassessed';
        props.kind = 'municipality';
        if (feature.geometry) map.data.addGeoJson(feature);
        else if (props.center && Number.isFinite(Number(props.center.lat)) && Number.isFinite(Number(props.center.lng))) {
          var marker = new maps.Marker({map: map, position: props.center, title: props.name + ' · ' + props.label,
            icon: {path: maps.SymbolPath.CIRCLE, scale: 7, strokeWeight: 2, strokeColor: '#FFFFFF', fillColor: props.color, fillOpacity: 1}});
          marker.addListener('click', function () { win.location.href = config.areaUrl + '?municipality_id=' + encodeURIComponent(props.id); });
          markers.push(marker); bounds.extend(props.center);
        }
      });
      var reference = payload.barangays;
      if (reference?.available) {
        reference.features.forEach(function (feature) { feature.properties.kind = 'barangay'; map.data.addGeoJson(feature); });
      }
      map.data.setStyle(function (feature) { return {fillColor: feature.getProperty('color') || '#75857D', fillOpacity: feature.getProperty('kind') === 'barangay' ? .32 : .18, strokeColor: feature.getProperty('color') || '#75857D', strokeWeight: 2, zIndex: feature.getProperty('kind') === 'barangay' ? 3 : 1}; });
      map.data.forEach(function (feature) { feature.getGeometry()?.forEachLatLng(function (point) { bounds.extend(point); }); });
      payload.parcels.forEach(function (parcel) {
        if (!Array.isArray(parcel.path)) return;
        var polygon = new maps.Polygon({map: map, paths: parcel.path, strokeColor: '#FFFFFF', strokeWeight: 2, fillColor: '#FFFFFF', fillOpacity: .1, zIndex: 8});
        polygon.addListener('click', function (event) {
          var content = node('div'); content.append(node('strong', parcel.name));
          content.append(node('p', parcel.ndvi ? 'NDVI ' + Number(parcel.ndvi.mean).toFixed(3) + ' · ' + parcel.ndvi.observed_on + (parcel.ndvi.is_stale ? ' · Older reading' : '') : 'No saved NDVI reading'));
          var link = node('a', 'Satellite field check'); link.href = parcel.satellite_url; content.append(link);
          info.setContent(content); info.setPosition(event.latLng); info.open(map);
        }); polygons.push(polygon); parcel.path.forEach(function (point) { bounds.extend(point); });
      });
      if (!bounds.isEmpty()) map.fitBounds(bounds, 32);
      else { host.hidden = true; throw new Error('No boundary reference is available for this area. Use the register below.'); }
      status.textContent = payload.features.length + ' of ' + payload.total + ' municipalities loaded. '
        + (payload.total > 80 ? 'Choose a municipality for its full local view. ' : '')
        + (config.municipalityId ? (reference?.available ? 'Barangay outlines are planning references; confirm positions locally. ' : 'Barangay outlines are not available here; recorded barangay assessments remain in the register. ')
          + polygons.length + ' of ' + payload.affected_total + ' reported parcel outlines shown. ' : '')
        + (reference?.source?.label ? 'Barangay source: ' + reference.source.label : '');
      return 'Reload map';
    }, status);

    var searchButton = doc.getElementById('searchClimateParcels');
    var searchInput = doc.getElementById('climateParcelSearch');
    var results = doc.getElementById('climateParcelResults'), selected = doc.getElementById('climateSelectedParcels');
    var searchStatus = doc.getElementById('climateParcelSearchStatus');
    if (searchButton && searchInput) bindLoad('searchClimateParcels', async function () {
      var q = searchInput.value.trim();
      if (q.length < 2) { searchStatus.textContent = 'Enter at least two characters.'; return; }
      var response = await request(win, config.lookupUrl + (config.lookupUrl.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q));
      results.replaceChildren();
      response.plots.forEach(function (plot) {
        if (selected.querySelector('input[value="' + Number(plot.id) + '"]')) return;
        var label = node('label'); label.className = 'climate-parcel-option';
        var checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.value = plot.id; checkbox.name = 'plot_ids[]';
        label.append(checkbox, doc.createTextNode(plot.name || 'Parcel #' + plot.id));
        checkbox.addEventListener('change', function () { if (checkbox.checked) selected.append(label); });
        results.append(label);
      });
      searchStatus.textContent = response.truncated ? 'First 20 matches. Refine the parcel name to find another.' : response.plots.length ? 'Select affected parcels from these matches.' : 'No parcels match this name in the selected municipality.';
    }, searchStatus);
    searchInput?.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); searchButton.click(); } });
    doc.querySelector('[data-climate-withdraw]')?.addEventListener('submit', function (event) {
      if (!win.confirm('Withdraw this assessment and its reported parcel links?')) event.preventDefault();
    });
  }

  return {rainfallSummary: rainfallSummary, request: request, mount: mount};
});
