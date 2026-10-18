(function () {
  'use strict';

  function validDay(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) &&
      Number.isFinite(Date.parse(value)) && new Date(value).toISOString().slice(0, 10) === value;
  }

  const defaultIndex = { id: 'ndvi', minimum: -1, maximum: 1, resolution_m: 10, name: 'Plant greenness',
    help: 'Shows how green the plants look from space. Start here for a general look at the field.',
    reading_help: 'Higher numbers usually mean more green plants. A low number can also mean bare soil, water or a recent harvest.', formula: '(B08 − B04) / (B08 + B04)',
    colors: ['#334da6', '#a67a4d', '#f2d44d', '#80b840', '#0f5226'], stops: [-1, 0, 0.2, 0.5, 1],
    legend_low: 'Less green', legend_high: 'More green',
    legend_note: 'Soil, crop stage and standing water affect the reading.',
    next_step: 'Compare the picture with your crop records and conditions in the field.' };

  function readObservations(payload, period, index = defaultIndex) {
    if (!payload || !Array.isArray(payload.observations) || payload.observations.length > 31) {
      throw new Error('The observation response is incomplete. Please reload.');
    }
    // Older NDVI callers may omit metadata; new indices must identify their units/grid.
    if ((payload.index ?? 'ndvi') !== index.id || (period.index ?? 'ndvi') !== index.id ||
        (index.id !== 'ndvi' && payload.resolution_m !== index.resolution_m) ||
        (payload.resolution_m !== undefined && payload.resolution_m !== index.resolution_m)) {
      throw new Error('The observations do not match the selected index. Search again.');
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
        return available ? typeof row[metric] !== 'number' || !Number.isFinite(row[metric]) ||
          row[metric] < index.minimum - 0.001 || row[metric] > index.maximum + 0.001 : row[metric] !== null;
      }) || (available && (row.min > row.mean || row.mean > row.max))) {
        throw new Error('The observation response contains invalid ' + index.id.toUpperCase() + ' values.');
      }
      return { date: row.date, valid_pixels: row.valid_pixels, status: row.status, mean: row.mean, min: row.min, max: row.max };
    }).sort((a, b) => a.date.localeCompare(b.date));
  }

  function indexScale(rows) {
    const values = rows.filter(row => row.status === 'available').flatMap(row => [row.min, row.mean, row.max]);
    return { min: Math.min(-1, ...values), max: Math.max(1, ...values) };
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
    const error = new Error(typeof message === 'string' ? message.slice(0, 400) : response.status === 429
      ? 'Satellite requests are temporarily limited. Please wait before trying again.'
      : 'The satellite request failed. Please try again.');
    if (response.status === 429) {
      const header = response.headers?.get('Retry-After');
      const seconds = /^\d+$/.test(header || '') ? Number(header) : header ? (Date.parse(header) - Date.now()) / 1000 : payload?.retry_after;
      if (Number.isFinite(seconds) && seconds > 0) error.retryAfterSeconds = Math.min(300, Math.max(1, Math.ceil(seconds)));
    }
    return error;
  }

  function createRetryCooldown(onTick, clock = {}) {
    const now = clock.now || Date.now, schedule = clock.setTimeout || setTimeout, cancel = clock.clearTimeout || clearTimeout;
    let deadline = 0, timer = null;
    const remaining = () => Math.max(0, Math.ceil((deadline - now()) / 1000));
    function stop() { if (timer !== null) cancel(timer); timer = null; deadline = 0; }
    function tick() {
      const seconds = remaining();
      timer = null;
      onTick(seconds);
      if (seconds > 0) timer = schedule(tick, Math.min(1000, Math.max(1, deadline - now())));
    }
    return { remaining, stop, start: seconds => {
      if (!Number.isFinite(seconds) || seconds <= 0) return;
      stop(); deadline = now() + Math.min(300, Math.ceil(seconds)) * 1000; tick();
    } };
  }

  if (typeof module !== 'undefined' && module.exports) module.exports = { readObservations, createRequestSlot, validDay, responseError, createRetryCooldown, indexScale };
  if (typeof document === 'undefined' || !document.getElementById('satellitePage')) return;

  const byId = id => document.getElementById(id);
  const config = JSON.parse(byId('satelliteConfig').textContent);
  const form = byId('satelliteForm');
  const indexSelect = byId('satelliteIndex');
  const indexes = config.indexes || { ndvi: defaultIndex };
  const selectedIndex = () => indexes[indexSelect.value] || indexes.ndvi;
  const loadButton = byId('satelliteLoad');
  const canSearch = !loadButton.disabled;
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
  let statsPending = false;
  let searchVersion = 0;
  function searchControl() {
    const seconds = statsCooldown.remaining();
    loadButton.disabled = !canSearch || statsPending || seconds > 0;
    if (!statsPending) loadButton.textContent = seconds > 0 ? 'Try again in ' + seconds + 's' : 'Find pictures →';
  }
  const statsCooldown = createRetryCooldown(seconds => {
    searchControl();
    status(seconds > 0 ? 'Satellite picture searches are paused. Try again in ' + seconds + ' seconds.' : 'You can search for satellite pictures again.', seconds > 0);
  });
  const imageCooldown = createRetryCooldown(seconds => {
    retry.disabled = seconds > 0;
    retry.textContent = seconds > 0 ? 'Retry in ' + seconds + 's' : 'Retry image';
    byId('satelliteImageCaption').textContent = seconds > 0 ? 'Picture loading is paused. Retry in ' + seconds + ' seconds.' : 'You can retry loading the picture now.';
  });

  function status(message, error) {
    byId('satelliteStatus').textContent = message;
    byId('satelliteStatus').classList.toggle('is-error', !!error);
  }
  function indexLabels() {
    const index = selectedIndex(), code = index.id.toUpperCase();
    byId('satelliteIndexHelp').textContent = index.help;
    byId('satelliteScoreHelp').textContent = index.reading_help;
    byId('satelliteIndexLayer').textContent = code + ' colours';
    byId('satelliteMeanLabel').textContent = 'Average ' + code + ' · ' + index.name;
    byId('satelliteHistoryMean').textContent = 'Average ' + code;
    byId('satelliteLegendLow').textContent = index.legend_low;
    byId('satelliteLegendHigh').textContent = index.legend_high;
    byId('satelliteLegendNote').textContent = index.legend_note + ' Blank parts have no reading.';
    byId('satelliteRamp').style.background = 'linear-gradient(to right, ' + index.colors.map((color, i) =>
      color + ' ' + ((index.stops[i] + 1) * 50) + '%').join(', ') + ')';
    byId('satelliteResolution').textContent = 'Each picture square is about ' + index.resolution_m + ' m across. ' +
      'This count is not the percentage of your field that is clear. The natural-colour picture uses about 10 m squares.';
    byId('satelliteFormula').textContent = code + ': ' + index.formula + ' · Harmonized surface reflectance.';
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
      window.ReusableGoogleMap?.report('satellite-2d', 'created');
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

  // The parent keeps only this last authorized iframe. Hiding it cancels
  // pending work; resuming never automatically requests provider processing.
  window.__pauseParcelSatellite = function () {
    searchVersion += 1; statsRequest.cancel(); imageRequest.cancel(); statsPending = false; searchControl();
    form.removeAttribute('aria-busy');
  };
  window.__resumeParcelSatellite = function () {
    if (map) google.maps.event?.trigger(map, 'resize');
    if (map) window.ReusableGoogleMap?.report('satellite-2d', 'reused');
  };

  function metrics(row) {
    const available = row && row.status === 'available';
    const index = selectedIndex(), code = index.id.toUpperCase();
    const scale = indexScale(available ? [row] : []);
    const unusual = available && (row.min < -1 || row.max > 1);
    byId('satelliteMean').textContent = available ? row.mean.toFixed(3) : '—';
    byId('satelliteRange').textContent = available ? row.min.toFixed(3) + ' / ' + row.max.toFixed(3) : '—';
    byId('satellitePixels').textContent = row ? row.valid_pixels.toLocaleString() : '—';
    byId('satelliteDay').textContent = row ? row.date : '—';
    byId('satelliteScore').hidden = !available;
    byId('satelliteResult').dataset.state = !row ? 'empty' : available ? 'available' : 'no-data';
    byId('satelliteResultTitle').textContent = !row ? 'Your result will appear here' : !available ? 'No clear reading for this day' : code + ' result';
    byId('satelliteScoreMeter').min = scale.min;
    byId('satelliteScoreMeter').max = scale.max;
    byId('satelliteScoreMeter').setAttribute('aria-label', 'Average ' + code + ', numeric scale from ' + scale.min + ' to ' + scale.max);
    byId('satelliteScoreMeter').value = available ? row.mean : 0;
    byId('satelliteScaleLow').textContent = scale.min.toLocaleString() + ' · Lower number';
    byId('satelliteScaleHigh').textContent = scale.max.toLocaleString() + ' · Higher number';
    byId('satelliteQuality').textContent = !row ? 'Select Find pictures to see the ' + code + ' result.' : !available ?
      'The picture could not give a clear reading. This does not mean the crops are damaged.' : unusual ?
        'Some numbers fall outside the colour scale. Check the natural-colour picture and try another clear date.' : row.valid_pixels < 25 ?
        'Only a few picture squares could be used. Check the field before acting on this result.' :
        'This result uses the clear parts of the picture. Compare it with what you see in the field.';
    byId('satelliteNextStep').textContent = !row ? 'Choose dates and select Find pictures.' : !available ?
      'Try another date. Clouds may be hiding the field.' : row.valid_pixels < 25 ?
        'Compare another clear date and check the field.' :
        index.next_step;
  }
  async function loadImage() {
    clearImage();
    const row = rows.find(item => item.date === daySelect.value);
    metrics(row);
    layerSelect.disabled = !row || row.status !== 'available';
    if (!row || row.status !== 'available') {
      byId('satelliteImageCaption').textContent = row ? 'No clear picture was available for ' + row.date + ' (UTC).' : 'Find pictures to load a satellite view.';
      return;
    }
    const index = selectedIndex();
    const layer = layerSelect.value === 'true-color' ? 'true-color' : index.id;
    const caption = row.date + ' (UTC) · ' + (layer === 'true-color' ? 'True color · ~10 m' : layer.toUpperCase() + ' · ~' + index.resolution_m + ' m') + ' · Daily Sentinel-2 mosaic';
    if (imageCooldown.remaining() > 0) {
      byId('satelliteImageCaption').textContent = 'Picture loading is paused. Retry in ' + imageCooldown.remaining() + ' seconds.';
      retry.hidden = false; return;
    }
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
      byId('satelliteLegend').hidden = layer === 'true-color';
      byId('satelliteImageCaption').textContent = caption + ' · Clipped to saved parcel';
    } catch (error) {
      byId('satelliteImageCaption').textContent = caption + ' · ' + (error.message || 'Image unavailable. Please retry.');
      retry.hidden = false;
      if (error.retryAfterSeconds) imageCooldown.start(error.retryAfterSeconds);
    }
  }

  function history() {
    const body = byId('satelliteHistory');
    const availableCount = rows.filter(row => row.status === 'available').length;
    byId('satelliteHistorySummary').textContent = rows.length ? availableCount + ' usable picture' + (availableCount === 1 ? '' : 's') + ' in this period' : 'Find pictures to see past readings';
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
    const svg = element('svg', { viewBox: '0 0 760 180', role: 'img', 'aria-label': 'Average ' + selectedIndex().id.toUpperCase() + ' by UTC day. Individual observations only; see the table for values.' });
    const left = 75, right = 735, top = 16, bottom = 146;
    const start = Date.parse(period.from), span = Math.max(86400000, Date.parse(period.to) - start);
    const scale = indexScale(rows);
    const yFor = value => top + (scale.max - value) / (scale.max - scale.min) * (bottom - top);
    [scale.min, 0, scale.max].forEach(function (value) {
      const y = yFor(value);
      svg.appendChild(element('line', { x1: left, x2: right, y1: y, y2: y, stroke: '#dce5df' }));
      svg.appendChild(element('text', { x: 6, y: y + 4, fill: '#52655a', 'font-size': 12 }, Math.abs(value) >= 1000 ? value.toExponential(1) : String(Number(value.toPrecision(3)))));
    });
    rows.filter(row => row.status === 'available').forEach(function (row) {
      const point = element('circle', { cx: left + (Date.parse(row.date) - start) / span * (right - left),
        cy: yFor(row.mean), r: 5, fill: '#236347' });
      point.appendChild(element('title', {}, row.date + ': ' + row.mean.toFixed(3)));
      svg.appendChild(point);
    });
    svg.appendChild(element('text', { x: left, y: 172, fill: '#52655a', 'font-size': 12 }, period.from));
    svg.appendChild(element('text', { x: right, y: 172, fill: '#52655a', 'font-size': 12, 'text-anchor': 'end' }, period.to));
    chart.appendChild(svg);
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (loadButton.disabled || statsPending || statsCooldown.remaining() > 0 || !form.reportValidity()) return;
    const next = { from: byId('satelliteFrom').value, to: byId('satelliteTo').value, max_cloud: Number(byId('satelliteCloud').value), index: selectedIndex().id };
    if (!validDay(next.from) || !validDay(next.to) || next.to < next.from || Date.parse(next.to) - Date.parse(next.from) > 30 * 86400000) {
      status('Choose a valid period of up to 31 days.', true); return;
    }
    const version = ++searchVersion;
    clearImage(); rows = []; period = null; metrics(null);
    byId('satelliteChart').replaceChildren();
    byId('satelliteHistory').replaceChildren();
    daySelect.replaceChildren(new Option('Loading observations…', ''));
    daySelect.disabled = true; layerSelect.disabled = true;
    byId('satelliteImageCaption').textContent = 'No satellite observation loaded.';
    statsPending = true; loadButton.disabled = true; loadButton.textContent = 'Loading…';
    form.setAttribute('aria-busy', 'true');
    status('Requesting cloud-masked ' + next.index.toUpperCase() + ' observations for ' + next.from + ' to ' + next.to + '…');
    try {
      const result = await statsRequest.run(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json',
        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value }, body: JSON.stringify(next) }, async function (response) {
        if (!response.ok) throw await responseError(response);
        return readObservations(await response.json(), next, indexes[next.index]);
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
      status(usable.length + ' usable day(s) found · ' + period.index.toUpperCase() + ' · ' + period.from + ' to ' + period.to + ' (UTC) · Cloud filter ' + period.max_cloud + '%.' +
        (usable.length ? ' Select a picture date to view the map and reading.' : ' Try different dates or use More search options.'));
      loadImage();
    } catch (error) {
      daySelect.replaceChildren(new Option('No observations loaded', ''));
      status(error.message || 'Unable to load observations. Check the connection and retry.', true);
      if (error.retryAfterSeconds) statsCooldown.start(error.retryAfterSeconds);
    } finally {
      if (version === searchVersion) { statsPending = false; searchControl(); form.removeAttribute('aria-busy'); }
    }
  });
  indexSelect.addEventListener('change', function () {
    searchVersion += 1; statsRequest.cancel(); clearImage(); statsPending = false;
    rows = []; period = null; indexLabels(); metrics(null); history();
    daySelect.replaceChildren(new Option('Find pictures for ' + selectedIndex().id.toUpperCase(), ''));
    daySelect.disabled = true; layerSelect.disabled = true;
    form.removeAttribute('aria-busy'); searchControl();
    byId('satelliteImageCaption').textContent = 'No ' + selectedIndex().id.toUpperCase() + ' observation loaded.';
    if (!statsCooldown.remaining()) status('Select Find pictures to load ' + selectedIndex().id.toUpperCase() + '. Changing the index does not start a provider request.');
  });
  daySelect.addEventListener('change', loadImage);
  layerSelect.addEventListener('change', loadImage);
  retry.addEventListener('click', loadImage);
  opacity.addEventListener('input', function () {
    if (overlay) overlay.setOpacity(Number(opacity.value) / 100);
    byId('satellitePreviewImage').style.opacity = Number(opacity.value) / 100;
  });
  window.addEventListener('pagehide', function () { statsRequest.cancel(); clearImage(); statsCooldown.stop(); imageCooldown.stop(); });
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) { searchControl(); retry.disabled = false; retry.textContent = 'Retry image'; if (rows.length) loadImage(); }
  });
  byId('satelliteLegend').hidden = true;
  indexLabels();
}());
