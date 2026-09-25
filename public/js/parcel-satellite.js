(function () {
  'use strict';

  function validDay(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) &&
      Number.isFinite(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
  }

  function readObservations(payload, period) {
    if (!payload || !Array.isArray(payload.observations) || payload.observations.length > 31) {
      throw new Error('The observation response is incomplete. Please reload.');
    }
    const days = new Set();
    return payload.observations.map(function (row) {
      if (!row || !validDay(row.date) || row.date < period.from || row.date > period.to || days.has(row.date) ||
          !Number.isInteger(row.valid_pixels) || row.valid_pixels < 0 || row.valid_pixels > 300000) {
        throw new Error('The observation response contains an invalid day or pixel count.');
      }
      days.add(row.date);
      const available = row.valid_pixels > 0;
      if (row.status !== (available ? 'available' : 'no_data') || ['mean', 'min', 'max'].some(function (metric) {
        return available ? typeof row[metric] !== 'number' || !Number.isFinite(row[metric]) || Math.abs(row[metric]) > 1.001 : row[metric] !== null;
      }) || (available && (row.min > row.mean || row.mean > row.max))) {
        throw new Error('The observation response contains invalid NDVI values.');
      }
      return { date: row.date, valid_pixels: row.valid_pixels, status: row.status, mean: row.mean, min: row.min, max: row.max };
    }).sort((a, b) => a.date.localeCompare(b.date));
  }

  // Even a response already being decoded must not replace a newer selection.
  function createRequestSlot(fetcher) {
    let sequence = 0;
    let controller;
    function cancel() { sequence += 1; if (controller) controller.abort(); }
    return {
      cancel: cancel,
      run: async function (url, options, decode) {
        cancel();
        const request = sequence;
        controller = new AbortController();
        const current = controller;
        let timedOut = false;
        const timeout = setTimeout(function () { timedOut = true; current.abort(); }, 45000);
        try {
          const response = await fetcher(url, Object.assign({}, options, { signal: current.signal, credentials: 'same-origin' }));
          const value = await decode(response);
          return request === sequence ? { value: value } : null;
        } catch (error) {
          if (request !== sequence) return null;
          if (timedOut) throw new Error('The satellite request timed out. Please retry.');
          throw error;
        } finally { clearTimeout(timeout); }
      }
    };
  }

  async function responseError(response) {
    if (response.status === 401 || response.status === 419) return new Error('Your session expired. Sign in again, then reload this page.');
    if (response.status === 403) return new Error('You no longer have access to this parcel.');
    let payload;
    try { payload = await response.json(); } catch (ignore) { /* HTML error pages are never rendered. */ }
    const validation = payload && payload.errors && Object.values(payload.errors).flat().find(value => typeof value === 'string');
    const message = validation || (payload && payload.message);
    if (response.status === 429 && !message) return new Error('Too many satellite requests. Please wait before trying again.');
    return new Error(typeof message === 'string' ? message.slice(0, 400) : 'The satellite request failed. Please try again.');
  }

  if (typeof module !== 'undefined' && module.exports) module.exports = { readObservations, createRequestSlot, validDay };
  if (typeof document === 'undefined' || !document.getElementById('satellitePage')) return;

  const byId = id => document.getElementById(id);
  const config = JSON.parse(byId('satelliteConfig').textContent);
  const form = byId('satelliteForm');
  const loadButton = byId('satelliteLoad');
  const daySelect = byId('satelliteDate');
  const layerSelect = byId('satelliteLayer');
  const opacity = byId('satelliteOpacity');
  const retry = byId('satelliteImageRetry');
  const statsRequest = createRequestSlot(window.fetch.bind(window));
  const imageRequest = createRequestSlot(window.fetch.bind(window));
  let rows = [];
  let period = null;
  let map = null;
  let overlay = null;
  let objectUrl = null;
  let mapFailed = false;

  function status(message, error) {
    byId('satelliteStatus').textContent = message;
    byId('satelliteStatus').classList.toggle('is-error', !!error);
  }
  function clearImage() {
    imageRequest.cancel();
    if (overlay) overlay.setMap(null);
    overlay = null;
    byId('satellitePreview').hidden = true;
    byId('satellitePreviewImage').removeAttribute('src');
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
    opacity.disabled = true;
    retry.hidden = true;
    byId('satelliteLegend').hidden = true;
  }
  function mountImage() {
    if (!objectUrl) return;
    if (overlay) overlay.setMap(null);
    overlay = null;
    if (map && !mapFailed) {
      overlay = new google.maps.GroundOverlay(objectUrl, config.bounds, { opacity: Number(opacity.value) / 100, clickable: false });
      overlay.setMap(map);
      byId('satellitePreview').hidden = true;
    } else if (mapFailed) {
      byId('satellitePreviewImage').src = objectUrl;
      byId('satellitePreviewImage').style.opacity = Number(opacity.value) / 100;
      byId('satellitePreview').hidden = false;
    }
  }
  function mapFailure() {
    mapFailed = true;
    if (overlay) overlay.setMap(null);
    overlay = null;
    byId('satelliteMap').hidden = true;
    byId('satelliteMapStatus').textContent = 'The reference map is unavailable. Loaded satellite images will appear as a parcel preview below.';
    byId('satelliteMapStatus').hidden = false;
    byId('satelliteMap').parentNode.insertBefore(byId('satelliteMapStatus'), byId('satelliteMap'));
    mountImage();
  }
  function initMap() {
    if (mapFailed) return;
    try {
      // Keep the status element outside the map before Google replaces its children.
      const mapStatus = byId('satelliteMapStatus');
      byId('satelliteMap').parentNode.insertBefore(mapStatus, byId('satelliteMap'));
      mapStatus.hidden = true;
      map = new google.maps.Map(byId('satelliteMap'), { center: { lat: (config.bounds.north + config.bounds.south) / 2,
        lng: (config.bounds.east + config.bounds.west) / 2 }, zoom: 16, mapTypeId: 'roadmap', streetViewControl: false, mapTypeControl: false });
      new google.maps.Polygon({ map: map, paths: config.geometry.coordinates.map(ring => ring.map(point => ({ lat: point[1], lng: point[0] }))),
        strokeColor: '#164d38', strokeWeight: 3, fillOpacity: 0, clickable: false, zIndex: 10 });
      map.fitBounds(config.bounds, 40);
    } catch (error) { mapFailure(); }
    mountImage();
  }
  if (!config.geometry || !config.bounds) {
    byId('satelliteMapStatus').textContent = 'Save a valid parcel boundary before requesting satellite observations.';
  } else if (!config.googleKey) {
    mapFailure();
  } else if (window.google && google.maps && google.maps.Map) {
    initMap();
  } else {
    const previousAuthFailure = window.gm_authFailure;
    window.gm_authFailure = function () { mapFailure(); if (typeof previousAuthFailure === 'function') previousAuthFailure(); };
    const timer = setTimeout(mapFailure, 15000);
    window.initParcelSatelliteMap = function () { clearTimeout(timer); initMap(); };
    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(config.googleKey) + '&loading=async&callback=initParcelSatelliteMap';
    script.async = true;
    script.onerror = function () { clearTimeout(timer); mapFailure(); };
    document.head.appendChild(script);
  }

  function metrics(row) {
    const available = row && row.status === 'available';
    byId('satelliteMean').textContent = available ? row.mean.toFixed(3) : '—';
    byId('satelliteRange').textContent = available ? row.min.toFixed(3) + ' / ' + row.max.toFixed(3) : '—';
    byId('satellitePixels').textContent = row ? row.valid_pixels.toLocaleString() : '—';
    byId('satelliteDay').textContent = row ? row.date : '—';
    byId('satelliteQuality').textContent = !row ? 'No greenness measurement has been loaded yet.' : !available ?
      'No clear image area was available on this day. Clouds, scene coverage or other filters may have blocked the measurement.' : row.valid_pixels < 25 ?
        'Only a small clear area was available. Edge effects and mixed land cover can affect the score, so confirm conditions in the field.' :
        'This score uses clear pixels only and may cover part of the parcel. The pixel count is not a parcel cloud percentage.';
  }
  async function loadImage() {
    clearImage();
    const row = rows.find(item => item.date === daySelect.value);
    metrics(row);
    layerSelect.disabled = !row || row.status !== 'available';
    if (!row || row.status !== 'available') {
      byId('satelliteImageCaption').textContent = row ? 'No clear image area was available for ' + row.date + ' (UTC).' : 'No satellite observation loaded.';
      return;
    }
    const layer = layerSelect.value;
    const caption = row.date + ' (UTC) · ' + (layer === 'ndvi' ? 'NDVI' : 'True color') + ' · Daily Sentinel-2 mosaic';
    byId('satelliteImageCaption').textContent = 'Loading ' + caption + '…';
    const url = new URL(config.imageUrl, window.location.href);
    url.search = new URLSearchParams({ date: row.date, max_cloud: String(period.max_cloud), layer: layer }).toString();
    try {
      const result = await imageRequest.run(url.href, { headers: { Accept: 'application/json, image/png' } }, async function (response) {
        if (!response.ok) throw await responseError(response);
        if (!(response.headers.get('Content-Type') || '').startsWith('image/png')) throw new Error('The provider returned an invalid image.');
        const blob = await response.blob();
        if (!blob.size || blob.size > 3000000) throw new Error('The provider returned an invalid image size.');
        return blob;
      });
      if (!result) return;
      objectUrl = URL.createObjectURL(result.value);
      mountImage();
      opacity.disabled = false;
      byId('satelliteLegend').hidden = layer !== 'ndvi';
      byId('satelliteImageCaption').textContent = caption + ' · Clipped to saved parcel';
    } catch (error) {
      byId('satelliteImageCaption').textContent = caption + ' · ' + (error.message || 'Image unavailable. Please retry.');
      retry.hidden = false;
    }
  }

  function history() {
    const body = byId('satelliteHistory');
    body.replaceChildren();
    if (!rows.length) {
      const cell = document.createElement('td'); cell.colSpan = 6;
      cell.textContent = 'No observations in this period. Try different dates or a higher scene cloud limit.';
      const tr = document.createElement('tr'); tr.appendChild(cell); body.appendChild(tr);
    }
    rows.forEach(function (row) {
      const tr = document.createElement('tr');
      [row.date, row.mean === null ? '—' : row.mean.toFixed(3), row.min === null ? '—' : row.min.toFixed(3),
        row.max === null ? '—' : row.max.toFixed(3), row.valid_pixels.toLocaleString(), row.status === 'available' ? 'Clear image found' : 'No clear image area'].forEach(function (value) {
        const td = document.createElement('td'); td.textContent = value; tr.appendChild(td);
      });
      body.appendChild(tr);
    });
    const chart = byId('satelliteChart');
    chart.replaceChildren();
    if (!rows.some(row => row.status === 'available')) return;
    function element(tag, attributes, label) {
      const node = document.createElementNS('http://www.w3.org/2000/svg', tag);
      Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
      if (label !== undefined) node.textContent = label;
      return node;
    }
    const svg = element('svg', { viewBox: '0 0 760 180', role: 'img', 'aria-label': 'Average greenness score by UTC day. Individual observations only; see the table for values.' });
    const left = 45, right = 735, top = 16, bottom = 146;
    const start = Date.parse(period.from), span = Math.max(86400000, Date.parse(period.to) - start);
    [-1, 0, 1].forEach(function (value) {
      const y = top + (1 - value) / 2 * (bottom - top);
      svg.appendChild(element('line', { x1: left, x2: right, y1: y, y2: y, stroke: '#dce5df' }));
      svg.appendChild(element('text', { x: 6, y: y + 4, fill: '#52655a', 'font-size': 12 }, String(value)));
    });
    rows.filter(row => row.status === 'available').forEach(function (row) {
      const point = element('circle', { cx: left + (Date.parse(row.date) - start) / span * (right - left),
        cy: top + (1 - row.mean) / 2 * (bottom - top), r: 5, fill: '#236347' });
      point.appendChild(element('title', {}, row.date + ': ' + row.mean.toFixed(3)));
      svg.appendChild(point);
    });
    svg.appendChild(element('text', { x: left, y: 172, fill: '#52655a', 'font-size': 12 }, period.from));
    svg.appendChild(element('text', { x: right, y: 172, fill: '#52655a', 'font-size': 12, 'text-anchor': 'end' }, period.to));
    chart.appendChild(svg);
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (loadButton.disabled || !form.reportValidity()) return;
    const next = { from: byId('satelliteFrom').value, to: byId('satelliteTo').value, max_cloud: Number(byId('satelliteCloud').value) };
    if (!validDay(next.from) || !validDay(next.to) || next.to < next.from || Date.parse(next.to) - Date.parse(next.from) > 30 * 86400000) {
      status('Choose a valid period of up to 31 days.', true); return;
    }
    clearImage(); rows = []; metrics(null);
    byId('satelliteChart').replaceChildren();
    byId('satelliteHistory').replaceChildren();
    daySelect.replaceChildren(new Option('Loading observations…', ''));
    daySelect.disabled = true; layerSelect.disabled = true;
    byId('satelliteImageCaption').textContent = 'No satellite observation loaded.';
    loadButton.disabled = true; loadButton.textContent = 'Loading…';
    form.setAttribute('aria-busy', 'true');
    status('Requesting cloud-masked observations for ' + next.from + ' to ' + next.to + '…');
    try {
      const result = await statsRequest.run(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value }, body: JSON.stringify(next) }, async function (response) {
        if (!response.ok) throw await responseError(response);
        return readObservations(await response.json(), next);
      });
      if (!result) return;
      rows = result.value; period = next;
      history();
      daySelect.replaceChildren();
      rows.forEach(row => daySelect.add(new Option(row.date + (row.status === 'no_data' ? ' · No clear image area' : ''), row.date)));
      if (!rows.length) daySelect.add(new Option('No observations found', ''));
      daySelect.disabled = !rows.length;
      const usable = rows.filter(row => row.status === 'available');
      daySelect.value = (usable[usable.length - 1] || rows[rows.length - 1] || {}).date || '';
      status(usable.length + ' clear day(s) found · ' + period.from + ' to ' + period.to + ' (UTC) · Scene cloud limit ' + period.max_cloud + '%.' +
        (usable.length ? ' Select a day to view its map and score.' : ' Try different dates or a higher scene cloud limit.'));
      loadImage();
    } catch (error) {
      daySelect.replaceChildren(new Option('No observations loaded', ''));
      status(error.message || 'Unable to load observations. Check the connection and retry.', true);
    } finally {
      loadButton.disabled = false; loadButton.textContent = 'Load observations'; form.removeAttribute('aria-busy');
    }
  });
  daySelect.addEventListener('change', loadImage);
  layerSelect.addEventListener('change', loadImage);
  retry.addEventListener('click', loadImage);
  opacity.addEventListener('input', function () {
    if (overlay) overlay.setOpacity(Number(opacity.value) / 100);
    byId('satellitePreviewImage').style.opacity = Number(opacity.value) / 100;
  });
  window.addEventListener('pagehide', function () { statsRequest.cancel(); clearImage(); });
  window.addEventListener('pageshow', function (event) { if (event.persisted && rows.length) loadImage(); });
  byId('satelliteLegend').hidden = true;
}());
