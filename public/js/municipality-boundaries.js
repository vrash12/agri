/*
 * Municipality geofence workspace.
 *
 * Extracted from resources/views/municipality_boundaries/index.blade.php so this
 * is a real JavaScript file: editors and linters can read it, it is diffable, and
 * the template compiler can no longer swallow part of it.
 *
 * Server values arrive on window.__municipalityBoundarySettings, written by the
 * Blade page. There are no server values here; add new ones to that block. It
 * stays a classic script, matching public/js/farmers-maps.js.
 */

(function () {
  'use strict';

  const settings = window.__municipalityBoundarySettings || {};
  const appearance = window.GeofenceStyle;

  const state = {
    map: null,
    labeledMapType: 'hybrid',
    info: null,
    barangays: null,
    boundaryOverlays: new Map(),
    boundaryFills: new Map(),
    styleDraft: null,
    styleRevision: 0,
    savingStyle: false,
    labels: new Map(),
    parcelOverlays: new Map(),
    boundaries: settings.initialBoundaries.slice(),
    selectedMunicipality: '',
    selectedBoundary: null,
    editorMode: null,
    editorRevision: 0,
    savingBoundary: false,
    editableOverlay: null,
    originalEditorCoordinates: new Map(),
    draftPoints: [],
    draftOverlay: null,
    mapClick: null,
    currentPayload: null,
    loadRevision: 0,
    loadController: null,
    loadingMunicipality: '',
    renderRevision: 0,
    renderFrame: null,
    renderTimer: null,
    searchTimer: null,
    visibleBoundaryIds: new Set(),
    boundaryBounds: new WeakMap(),
  };

  const el = id => document.getElementById(id);
  const filter = el('municipalityFilter');
  const panel = el('panelContent');
  const initialBoundary = settings.initialBoundaries[0];
  const defaultViewport = settings.canChooseMunicipality
    ? {center: {lat: 15.4755, lng: 120.5963}, zoom: 10}
    : initialBoundary
      ? {center: {lat: Number(initialBoundary.centroid_lat), lng: Number(initialBoundary.centroid_lng)}, zoom: 12}
      : {center: {lat: 12.8797, lng: 121.774}, zoom: 5};

  function toast(message, bad) {
    const node = el('geoToast');
    node.textContent = message;
    node.classList.toggle('bad', !!bad);
    node.hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { node.hidden = true; }, 4800);
  }

  function errorMessage(payload, fallback) {
    if (payload && payload.errors) {
      const first = Object.values(payload.errors).flat()[0];
      if (first) return String(first);
    }
    return payload && payload.message ? payload.message : fallback;
  }

  async function request(url, options) {
    const response = await fetch(url, Object.assign({headers: {'Accept': 'application/json'}}, options || {}));
    let payload = null;
    try { payload = await response.json(); } catch (ignore) { /* Report an unexpected HTML/login response below. */ }
    if (response.status === 401 || response.status === 419 || (response.redirected && !payload)) {
      throw new Error('Your sign-in session has expired. Sign in again in another tab, then reload this workspace before saving.');
    }
    if (!response.ok) throw new Error(errorMessage(payload, 'The request failed (' + response.status + '). Please try again.'));
    if (!payload || typeof payload !== 'object') throw new Error('The server did not confirm the request. Reload this workspace before trying again.');
    return payload;
  }

  function endpoint(template, id) { return template.replace('__ID__', encodeURIComponent(String(id))); }
  function formatNumber(value, decimals) { return Number(value || 0).toLocaleString(undefined, {maximumFractionDigits: decimals}); }
  function municipalityName(id) { return (settings.municipalities.find(item => String(item.id) === String(id)) || {}).name || 'Municipality'; }

  function geometryPolygons(geometry) {
    if (!geometry || !geometry.coordinates) return [];
    return geometry.type === 'Polygon' ? [geometry.coordinates] : geometry.coordinates;
  }

  function googlePaths(polygon) {
    return polygon.map(ring => ring.map(point => ({lat: Number(point[1]), lng: Number(point[0])})));
  }

  function boundaryStyle(boundary) {
    return state.styleDraft && String(state.styleDraft.id) === String(boundary.id) ? state.styleDraft : appearance.normalize(boundary);
  }

  function boundaryFillOpacity(id) {
    // The editor replaces the saved fill; stacking both obscures preview colors
    // and makes the actual opacity higher than the slider value.
    const editing = state.editorMode === 'edit' && String(state.selectedBoundary?.id) === String(id);
    const boundary = state.boundaries.find(item => String(item.id) === String(id));
    return editing ? 0 : boundaryStyle(boundary || {}).fill_opacity;
  }

  function applyFillOpacity() {
    state.boundaryFills.forEach((group, id) => group.overlays.forEach(overlay => {
      const style = boundaryStyle(group.boundary);
      overlay.setOptions({fillColor: style.color, strokeColor: style.color, fillOpacity: boundaryFillOpacity(id)});
    }));
    if (state.editableOverlay) {
      state.editableOverlay.setOptions({fillOpacity: appearance.normalize(state.selectedBoundary).fill_opacity});
    }
    if (state.draftOverlay) state.draftOverlay.setOptions({fillOpacity: .2});
  }

  function drawBoundary(boundary) {
    const fills = [];
    const style = boundaryStyle(boundary);
    const detailed = !!state.selectedMunicipality || state.map.getZoom() >= 13;
    const overlays = geometryPolygons(boundary.geojson).flatMap(polygon => {
      const paths = googlePaths(polygon);
      // A pale casing keeps dark saved colors visible over satellite terrain.
      const outline = detailed ? new google.maps.Polygon({
        paths,
        strokeColor: '#FFF8D6',
        strokeOpacity: 1,
        strokeWeight: boundary.status === 'draft' ? 6 : 8,
        fillOpacity: 0,
        clickable: false,
        zIndex: boundary.status === 'active' ? 1.9 : .9,
      }) : null;
      if (outline) outline.setMap(state.map);
      const overlay = new google.maps.Polygon({
        paths,
        strokeColor: style.color,
        strokeOpacity: 1,
        strokeWeight: boundary.status === 'draft' ? 3 : 4,
        fillColor: style.color,
        fillOpacity: boundaryFillOpacity(boundary.id),
        clickable: true,
        zIndex: boundary.status === 'active' ? 2 : 1,
      });
      overlay.setMap(state.map);
      overlay.addListener('click', event => {
        state.info.setContent('<strong>' + escapeHtml(boundary.municipality_name || '') + '</strong><br><span>' + escapeHtml(boundary.name) + ' · ' + escapeHtml(boundary.status) + '<br>' + formatNumber(boundary.area_ha, 2) + ' ha</span>');
        state.info.setPosition(event.latLng);
        state.info.open({map: state.map});
        filter.value = String(boundary.municipality_id);
        loadMunicipality(boundary.municipality_id, boundary.id);
      });
      fills.push(overlay);
      return detailed ? [outline, overlay] : [overlay];
    });
    state.boundaryOverlays.set(String(boundary.id), overlays);
    overlays.detailed = detailed;
    state.boundaryFills.set(String(boundary.id), {overlays: fills, boundary});

    const labelPosition = appearance.labelPosition(boundary);
    if (boundary.status === 'active' && labelPosition) {
      const marker = new google.maps.Marker({
        map: state.selectedMunicipality || state.map.getZoom() >= 10 ? state.map : null,
        position: labelPosition,
        label: {text: String(boundary.municipality_name || ''), color: '#FFFFFF', fontSize: '12px', fontWeight: '500', className: 'geo-boundary-label'},
        icon: {path: google.maps.SymbolPath.CIRCLE, scale: 0},
        clickable: false,
        zIndex: 4,
      });
      state.labels.set(String(boundary.id), marker);
    }
    state.visibleBoundaryIds.add(String(boundary.id));
  }

  function removeBoundary(id) {
    (state.boundaryOverlays.get(String(id)) || []).forEach(item => item.setMap(null));
    state.boundaryOverlays.delete(String(id));
    state.boundaryFills.delete(String(id));
    const label = state.labels.get(String(id));
    if (label) label.setMap(null);
    state.labels.delete(String(id));
    state.visibleBoundaryIds.delete(String(id));
  }

  function boundsForBoundary(boundary) {
    if (state.boundaryBounds.has(boundary)) return state.boundaryBounds.get(boundary);
    const bounds = {south: Infinity, north: -Infinity, west: Infinity, east: -Infinity};
    geometryPolygons(boundary.geojson).forEach(polygon => polygon.forEach(ring => ring.forEach(point => {
      bounds.south = Math.min(bounds.south, Number(point[1]));
      bounds.north = Math.max(bounds.north, Number(point[1]));
      bounds.west = Math.min(bounds.west, Number(point[0]));
      bounds.east = Math.max(bounds.east, Number(point[0]));
    })));
    state.boundaryBounds.set(boundary, bounds);
    return bounds;
  }

  function currentBoundaries() {
    const selected = String(state.selectedMunicipality || '');
    return state.boundaries.filter(boundary => boundary.status !== 'archived'
      && (!selected || String(boundary.municipality_id) === selected));
  }

  function setBoundaryVisible(id, visible) {
    const attached = state.visibleBoundaryIds.has(id);
    if (visible !== attached) {
      (state.boundaryOverlays.get(id) || []).forEach(overlay => overlay.setMap(visible ? state.map : null));
      if (visible) state.visibleBoundaryIds.add(id);
      else state.visibleBoundaryIds.delete(id);
    }
    const label = state.labels.get(id);
    const labelMap = visible && (state.selectedMunicipality || state.map.getZoom() >= 10) ? state.map : null;
    if (label && label.getMap() !== labelMap) label.setMap(labelMap);
  }

  function trimBoundaryCache() {
    // Retain nearby/recent shapes without keeping an unlimited off-screen map cache.
    for (const id of state.boundaryOverlays.keys()) {
      if (state.boundaryOverlays.size <= 160) break;
      if (!state.visibleBoundaryIds.has(id)) removeBoundary(id);
    }
  }

  function renderBoundaries() {
    const revision = ++state.renderRevision;
    if (state.renderFrame !== null) cancelAnimationFrame(state.renderFrame);
    const viewport = state.map.getBounds();
    const northEast = viewport?.getNorthEast();
    const southWest = viewport?.getSouthWest();
    // A margin prevents shapes at the screen edge from flashing while panning.
    const latMargin = viewport ? (northEast.lat() - southWest.lat()) * .15 : 0;
    const lngMargin = viewport ? (northEast.lng() - southWest.lng()) * .15 : 0;
    const visible = currentBoundaries().filter(boundary => {
      if (state.selectedMunicipality) return true;
      if (!viewport) return false; // Maps supplies the first viewport on idle.
      const bounds = boundsForBoundary(boundary);
      const latitudeMatches = bounds.north >= southWest.lat() - latMargin && bounds.south <= northEast.lat() + latMargin;
      // A viewport crossing the date line must not discard Philippine boundaries.
      return latitudeMatches && (southWest.lng() > northEast.lng()
        || (bounds.east >= southWest.lng() - lngMargin && bounds.west <= northEast.lng() + lngMargin));
    });
    const wanted = new Set(visible.map(boundary => String(boundary.id)));
    visible.forEach(boundary => {
      const overlays = state.boundaryOverlays.get(String(boundary.id));
      if (overlays && overlays.detailed !== (!!state.selectedMunicipality || state.map.getZoom() >= 13)) removeBoundary(boundary.id);
    });
    state.boundaryOverlays.forEach((overlays, id) => setBoundaryVisible(id, wanted.has(id)));
    const pending = visible.filter(boundary => !state.boundaryOverlays.has(String(boundary.id)));
    let index = 0;
    function drawBatch() {
      if (revision !== state.renderRevision) return;
      state.renderFrame = null;
      const start = performance.now();
      let count = 0;
      while (index < pending.length && count < 12) {
        drawBoundary(pending[index++]);
        count++;
        if (performance.now() - start >= 8) break;
      }
      trimBoundaryCache();
      if (index < pending.length) state.renderFrame = requestAnimationFrame(drawBatch);
    }
    trimBoundaryCache();
    if (pending.length) state.renderFrame = requestAnimationFrame(drawBatch);
  }

  function scheduleBoundaryRender() {
    clearTimeout(state.renderTimer);
    state.renderTimer = setTimeout(renderBoundaries, 80);
  }

  function mergeBoundaries(municipalityId, boundaries) {
    const previous = state.boundaries.filter(boundary => String(boundary.municipality_id) === String(municipalityId));
    previous.forEach(boundary => {
      const replacement = boundaries.find(item => item.id === boundary.id);
      if (!replacement || JSON.stringify(replacement) !== JSON.stringify(boundary)) removeBoundary(boundary.id);
    });
    state.boundaries = state.boundaries.filter(boundary => String(boundary.municipality_id) !== String(municipalityId)).concat(boundaries);
  }

  function clearParcels() {
    state.parcelOverlays.forEach(overlay => overlay.setMap(null));
    state.parcelOverlays.clear();
  }

  function drawParcels(parcels) {
    clearParcels();
    parcels.forEach(parcel => {
      const points = (parcel.polygon || []).map(point => ({lat: Number(point.lat), lng: Number(point.lng)}));
      if (points.length < 3) return;
      const overlay = new google.maps.Polygon({paths: points, strokeColor: parcel.color || '#2563eb', strokeWeight: 2, strokeOpacity: .9, fillColor: parcel.color || '#2563eb', fillOpacity: .23, zIndex: 6});
      overlay.setMap(state.map);
      overlay.addListener('mouseover', event => {
        state.info.setContent('<strong>' + escapeHtml(parcel.name) + '</strong><br><span>' + escapeHtml(parcel.farmer.name || 'Unknown farmer') + '<br>' + formatNumber(parcel.area_ha, 4) + ' ha</span>');
        state.info.setPosition(event.latLng);
        state.info.open({map: state.map});
      });
      overlay.addListener('mouseout', () => state.info.close());
      state.parcelOverlays.set(String(parcel.id), overlay);
    });
  }

  function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = String(value == null ? '' : value);
    return div.innerHTML;
  }

  function fitVisible() {
    const bounds = new google.maps.LatLngBounds();
    let count = 0;
    // Fit the complete selection even when rendering has culled or not yet drawn it.
    currentBoundaries().forEach(boundary => {
      const box = boundsForBoundary(boundary);
      if (!Number.isFinite(box.south)) return;
      bounds.extend({lat: box.south, lng: box.west});
      bounds.extend({lat: box.north, lng: box.east});
      count++;
    });
    (state.currentPayload?.parcels || []).forEach(parcel => (parcel.polygon || []).forEach(point => {
      bounds.extend({lat: Number(point.lat), lng: Number(point.lng)}); count++;
    }));
    if (count) state.map.fitBounds(bounds, 34);
    else resetDefaultView();
  }

  function resetDefaultView() { state.map.setCenter(defaultViewport.center); state.map.setZoom(defaultViewport.zoom); }

  function syncStyleControls(message) {
    const choices = (state.currentPayload?.boundaries || []).filter(item => item.status !== 'archived');
    const selected = choices.find(item => String(item.id) === String(state.selectedBoundary?.id)) || null;
    const style = boundaryStyle(selected || {});
    el('geofenceStyleBoundary').innerHTML = choices.length
      ? choices.map(item => '<option value="' + item.id + '">' + escapeHtml(item.name + ' · ' + item.status) + '</option>').join('')
      : '<option value="">' + (state.selectedMunicipality ? 'No current boundary' : 'Select a municipality first') + '</option>';
    el('geofenceStyleBoundary').value = selected ? String(selected.id) : '';
    el('geofenceStyleBoundary').disabled = !choices.length || !!state.editorMode || state.savingStyle;
    el('geofenceColor').value = style.color.toLowerCase();
    const percentage = Math.round(style.fill_opacity * 100);
    el('geofenceOpacity').value = String(percentage);
    el('geofenceOpacityValue').value = percentage + '%';
    el('geofenceOpacity').setAttribute('aria-valuetext', percentage + '% color opacity');
    const disabled = !settings.canManage || !selected || !!state.editorMode || state.savingStyle;
    el('geofenceColor').disabled = disabled; el('geofenceOpacity').disabled = disabled;
    if (el('saveGeofenceStyle')) {
      el('saveGeofenceStyle').disabled = disabled || !state.styleDraft;
      el('saveGeofenceStyle').textContent = state.savingStyle ? 'Saving…' : 'Save color & opacity';
    }
    if (el('resetGeofenceStyle')) el('resetGeofenceStyle').disabled = disabled || !state.styleDraft;
    el('geofenceStylePanel').setAttribute('aria-busy', String(state.savingStyle));
    el('geofenceStyleStatus').textContent = message || (state.editorMode ? 'Finish editing the boundary before changing its appearance.'
      : !selected ? 'Select a municipality with an active boundary or draft.'
      : state.styleDraft ? 'Preview only. Save to apply this appearance to both maps.'
      : 'Saved appearance · ' + municipalityName(selected.municipality_id) + ' · ' + percentage + '% opacity');
  }

  function discardStyle() {
    state.styleRevision++;
    state.styleDraft = null;
    el('geofenceStyleError').hidden = true;
    applyFillOpacity();
    syncStyleControls();
  }

  function previewStyle() {
    if (!settings.canManage || !state.selectedBoundary || state.editorMode || state.savingStyle || !state.currentPayload) return;
    const style = appearance.normalize({color: el('geofenceColor').value, fill_opacity: Number(el('geofenceOpacity').value) / 100});
    const saved = appearance.normalize(state.selectedBoundary);
    state.styleDraft = style.color === saved.color && style.fill_opacity === saved.fill_opacity ? null : {id: state.selectedBoundary.id, ...style};
    el('geofenceStyleError').hidden = true;
    applyFillOpacity(); syncStyleControls();
  }

  async function saveStyle() {
    if (!settings.canManage || state.savingStyle || !state.styleDraft || state.editorMode) return;
    const boundary = state.selectedBoundary;
    const revision = state.styleRevision;
    const body = {color: state.styleDraft.color, fill_opacity: state.styleDraft.fill_opacity, _record_version: boundary._record_version};
    state.savingStyle = true; syncStyleControls();
    try {
      const payload = await request(endpoint(settings.styleTemplate, boundary.id), {method: 'PATCH', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf}, body: JSON.stringify(body)});
      if (String(payload.boundary?.id) !== String(boundary.id) || !payload.boundary?._record_version) throw new Error('The server did not confirm the saved appearance. Reload before trying again.');
      // Update this boundary's cached style even if the user switched workspaces in flight.
      const index = state.boundaries.findIndex(item => String(item.id) === String(boundary.id));
      if (index >= 0) { removeBoundary(boundary.id); state.boundaries[index] = payload.boundary; }
      if (revision === state.styleRevision) {
        state.styleDraft = null; state.selectedBoundary = payload.boundary;
        state.currentPayload.boundaries = state.currentPayload.boundaries.map(item => String(item.id) === String(boundary.id) ? payload.boundary : item);
        el('geofenceStyleError').hidden = true;
        toast(payload.message);
      }
      renderBoundaries();
    } catch (error) {
      if (revision === state.styleRevision) {
        el('geofenceStyleError').textContent = error instanceof TypeError ? 'Could not confirm the save. Check your connection and reload before trying again.' : error.message;
        el('geofenceStyleError').hidden = false;
      }
    } finally {
      state.savingStyle = false; syncStyleControls();
    }
  }

  async function loadMunicipality(id, selectedBoundaryId, force) {
    if (!settings.canChooseMunicipality) id = settings.assignedMunicipalityId;
    id = id ? String(id) : '';
    clearTimeout(state.searchTimer);
    if (!force && id && id === state.selectedMunicipality && (state.loadingMunicipality === id || state.currentPayload)) {
      cancelEditor();
      if (selectedBoundaryId && state.currentPayload) {
        state.selectedBoundary = state.currentPayload.boundaries.find(boundary => String(boundary.id) === String(selectedBoundaryId)) || null;
      }
      discardStyle();
      return;
    }
    const revision = ++state.loadRevision;
    state.loadController?.abort();
    state.loadController = null;
    state.loadingMunicipality = id;
    state.selectedMunicipality = id;
    state.selectedBoundary = null;
    cancelEditor();
    state.barangays?.selectMunicipality(id);
    clearParcels();
    state.currentPayload = null;
    discardStyle();
    el('downloadSnapshot').disabled = true;
    renderBoundaries();

    // Only a province-wide account can clear the selection; a municipal
    // account always has its own workspace loaded.
    if (settings.canChooseMunicipality) {
        if (!id) {
          el('panelEyebrow').textContent = 'Province-wide view';
          el('panelTitle').textContent = 'Boundary overview';
          el('panelDescription').textContent = settings.boundaryOnly ? 'Select one municipality to inspect its active administrative boundary reference.' : 'Select one municipality to inspect its active boundary and parcel placement.';
          panel.innerHTML = settings.boundaryOnly
            ? '<div class="geo-empty">The map is showing active municipality geofences. Select one for boundary details.</div>'
            : '<div class="geo-empty">The map is showing all available municipality geofences. Use the municipality selector to load detailed parcel checks.</div>';
          el('mapMessage').textContent = settings.boundaryOnly
            ? 'National view: active administrative boundary references are visible. Operational records are not loaded.'
            : 'Province view: active official boundaries and drafts are visible. Select a municipality for parcel compliance.';
          el('summaryConfigured').textContent = formatNumber(settings.initialSummary.configured, 0);
          if (!settings.boundaryOnly) {
            el('summaryFarmers').textContent = formatNumber(settings.initialSummary.farmers, 0);
            el('summaryParcels').textContent = formatNumber(settings.initialSummary.parcels, 0);
            el('summaryMappedArea').textContent = formatNumber(settings.initialSummary.mapped_area_ha, 2) + ' ha';
          }
          fitVisible();
          return;
        }
    }

    el('mapMessage').textContent = 'Loading ' + municipalityName(id) + (settings.boundaryOnly ? ' boundary…' : ' boundary and parcels…');
    panel.innerHTML = '<div class="geo-empty" role="status">Loading ' + (settings.boundaryOnly ? 'boundary details' : 'boundary and parcel checks') + '…</div>';
    fitVisible();
    const controller = new AbortController();
    state.loadController = controller;
    try {
      const payload = await request(settings.dataUrl + '?municipality_id=' + encodeURIComponent(id), {signal: controller.signal});
      if (revision !== state.loadRevision) return;
      state.currentPayload = payload;
      el('downloadSnapshot').disabled = !payload.snapshot;
      mergeBoundaries(id, payload.boundaries);
      state.selectedBoundary = selectedBoundaryId ? payload.boundaries.find(item => String(item.id) === String(selectedBoundaryId)) : payload.boundaries[0] || null;
      syncStyleControls();
      renderBoundaries();
      drawParcels(payload.parcels || []);
      renderPanel(payload);
      updateSelectedSummary(payload.stats);
      el('mapMessage').textContent = settings.boundaryOnly
        ? (payload.boundaries.some(item => item.status === 'active') ? payload.municipality.name + ': active administrative boundary reference loaded.' : payload.municipality.name + ' has no active boundary reference.')
        : (payload.boundaries.some(item => item.status === 'active')
          ? payload.municipality.name + ': official boundary and ' + payload.stats.parcels + ' parcel(s) loaded.'
          : payload.municipality.name + ' has no active official boundary. Parcels cannot be classified yet.');
      fitVisible();
    } catch (error) {
      if (revision !== state.loadRevision || error.name === 'AbortError') return;
      toast(error.message, true);
      el('mapMessage').textContent = 'The municipality workspace could not be loaded.';
      panel.innerHTML = '<div class="geo-empty">Boundary and parcel checks could not be loaded. <button type="button" class="geo-btn" data-retry-workspace>Try again</button></div>';
    } finally {
      if (revision === state.loadRevision) {
        state.loadingMunicipality = '';
        state.loadController = null;
      }
    }
  }

  function updateSelectedSummary(stats) {
    if (settings.boundaryOnly) return;
    el('summaryFarmers').textContent = formatNumber(stats.farmers, 0);
    el('summaryParcels').textContent = formatNumber(stats.parcels, 0);
    el('summaryMappedArea').textContent = formatNumber(stats.mapped_area_ha, 2) + ' ha';
  }

  function renderPanel(payload) {
    el('panelEyebrow').textContent = 'Municipality workspace';
    el('panelTitle').textContent = payload.municipality.name;
    el('panelDescription').textContent = settings.boundaryOnly ? 'Read-only administrative boundary reference. Operational records are unavailable.' : 'Review the official boundary and parcels needing attention. Older boundaries are available in history.';
    const stats = payload.stats;
    let html = settings.boundaryOnly ? '<div class="geo-empty">This evaluator session does not load farmer, parcel, assistance, account, export, or editing data.</div>' : '<div class="geo-mini-stats">' +
      mini('Farmers', stats.farmers) + mini('Mapped farmers', stats.mapped_farmers) + mini('Parcels', stats.parcels) + mini('Mapped hectares', formatNumber(stats.mapped_area_ha, 2)) +
      mini('Outside boundary', stats.outside) + mini('Crossing / near', Number(stats.partial) + Number(stats.near_boundary)) + '</div>';
    function boundaryCard(boundary) {
      let card = '';
      card += '<article class="geo-boundary-card ' + (boundary.status === 'active' ? 'active' : '') + '"><div class="geo-boundary-top"><strong>' + escapeHtml(boundary.name) + '</strong><span class="geo-badge ' + boundary.status + '">' + escapeHtml(boundary.status) + '</span></div><div class="geo-boundary-meta">' + formatNumber(boundary.area_ha, 2) + ' ha · ' + formatNumber(boundary.vertex_count, 0) + ' vertices</div><div class="geo-card-actions">';
      if (boundary.status !== 'archived') card += '<button class="geo-btn" type="button" data-focus-boundary="' + boundary.id + '">Focus</button>';
      if (settings.canManage) {
        if (boundary.status !== 'archived') card += '<button class="geo-btn" type="button" data-edit-boundary="' + boundary.id + '">Edit</button>';
        if (boundary.status !== 'active') card += '<button class="geo-btn primary" type="button" data-activate-boundary="' + boundary.id + '">Activate</button>';
        if (boundary.status !== 'archived') card += '<button class="geo-btn danger" type="button" data-archive-boundary="' + boundary.id + '">Archive</button>';
      }
      card += '</div></article>';
      return card;
    }
    const active = payload.boundaries.filter(boundary => boundary.status === 'active');
    const history = payload.boundaries.filter(boundary => boundary.status !== 'active');
    html += '<div class="geo-section-title">Official boundary</div>';
    html += active.length ? active.map(boundaryCard).join('') : '<div class="geo-empty">No official boundary is active. ' + (settings.canManage ? 'Review a draft or add a boundary.' : 'Contact the Super Administrator to configure the boundary.') + '</div>';
    if (!settings.boundaryOnly) {
      html += '<details class="module-more"><summary>Drafts and boundary history <span>' + history.length + ' records</span></summary><div class="module-more-content">';
      html += history.length ? history.map(boundaryCard).join('') : '<div class="geo-empty">No drafts or older boundaries.</div>';
      html += '</div></details>';
      html += '<div class="geo-section-title"><span>Needs field review</span><span>' + payload.review.length + '</span></div>';
      if (!payload.review.length) html += '<div class="geo-empty">No outside, crossing, near-boundary, or invalid parcels were found.</div>';
      payload.review.forEach(item => {
        html += '<article class="geo-review-card" tabindex="0" role="button" data-focus-plot="' + item.plot_id + '"><div class="geo-review-top"><strong>' + escapeHtml(item.plot_name) + '</strong><span class="geo-review-status ' + item.status + '">' + escapeHtml(item.status.replace('_', ' ')) + '</span></div><p>' + escapeHtml(item.farmer_name || 'Unknown farmer') + (item.ffrs ? ' · ' + escapeHtml(item.ffrs) : '') + '<br>' + escapeHtml(item.location || 'Location not recorded') + ' · ' + formatNumber(item.area_ha, 4) + ' ha</p></article>';
      });
    }
    panel.innerHTML = html;
    panel.querySelectorAll('[data-focus-plot]').forEach(card => card.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); card.click(); }
    }));
  }

  function mini(label, value) { return '<div class="geo-mini"><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(value) + '</strong></div>'; }

  function worldPoint(lat, lng, zoom) {
    const safeLat = Math.max(-85.05112878, Math.min(85.05112878, Number(lat)));
    const sine = Math.sin(safeLat * Math.PI / 180);
    const factor = Math.pow(2, Number(zoom));
    return {
      x: ((Number(lng) + 180) / 360) * 256 * factor,
      y: (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * 256 * factor,
    };
  }

  function loadSnapshotImage(url) {
    return fetch(url, {headers:{'Accept':'image/png,image/*'}}).then(async response => {
      if (!response.ok) {
        const message = await response.text();
        throw new Error(message || 'The satellite base image could not be generated.');
      }
      const blob = await response.blob();
      return new Promise((resolve, reject) => {
        const image = new Image();
        const objectUrl = URL.createObjectURL(blob);
        image.onload = () => { URL.revokeObjectURL(objectUrl); resolve(image); };
        image.onerror = () => { URL.revokeObjectURL(objectUrl); reject(new Error('The satellite base image could not be read.')); };
        image.src = objectUrl;
      });
    });
  }

  function canvasGeometryPath(geometry, project) {
    const path = new Path2D();
    geometryPolygons(geometry).forEach(polygon => polygon.forEach(ring => {
      ring.forEach((point, index) => {
        const pixel = project(Number(point[1]), Number(point[0]));
        if (index === 0) path.moveTo(pixel.x, pixel.y); else path.lineTo(pixel.x, pixel.y);
      });
      path.closePath();
    }));
    return path;
  }

  function canvasParcelPath(points, project) {
    const path = new Path2D();
    (points || []).forEach((point, index) => {
      const pixel = project(Number(point.lat), Number(point.lng));
      if (index === 0) path.moveTo(pixel.x, pixel.y); else path.lineTo(pixel.x, pixel.y);
    });
    path.closePath();
    return path;
  }

  async function downloadMunicipalitySnapshot() {
    const payload = state.currentPayload;
    if (!payload || !payload.snapshot) {
      toast('Select a municipality with an active official boundary first.', true);
      return;
    }

    const button = el('downloadSnapshot');
    button.disabled = true;
    const originalLabel = button.textContent;
    button.textContent = 'Preparing satellite snapshot…';

    try {
      const image = await loadSnapshotImage(payload.snapshot.base_map_url);
      const canvas = document.createElement('canvas');
      const sourceWidth = Number(image.naturalWidth || payload.snapshot.source_size || 1280);
      const sourceHeight = Number(image.naturalHeight || payload.snapshot.source_size || 1280);
      const mapSize = Math.min(sourceWidth, sourceHeight);
      canvas.width = mapSize;
      canvas.height = mapSize;
      const ctx = canvas.getContext('2d');
      const mapX = 0;
      const mapY = 0;
      const viewportSize = Number(payload.snapshot.viewport_size || 640);
      const sourceScaleX = sourceWidth / viewportSize;
      const sourceScaleY = sourceHeight / viewportSize;
      const zoom = Number(payload.snapshot.zoom);
      const center = worldPoint(payload.snapshot.center_lat, payload.snapshot.center_lng, zoom);
      const project = (lat, lng) => {
        const point = worldPoint(lat, lng, zoom);
        return {
          x: mapX + (((point.x - center.x) * sourceScaleX) + sourceWidth / 2) * (mapSize / sourceWidth),
          y: mapY + (((point.y - center.y) * sourceScaleY) + sourceHeight / 2) * (mapSize / sourceHeight),
        };
      };

      // The export is intentionally map-only. Everything outside the active
      // official geofence remains white, with no report chrome or labels.
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, canvas.width, canvas.height);

      const activeBoundary = payload.boundaries.find(item => item.status === 'active');
      const boundaryPath = canvasGeometryPath(activeBoundary.geojson, project);
      ctx.save();
      ctx.clip(boundaryPath, 'evenodd');
      ctx.drawImage(image, mapX, mapY, mapSize, mapSize);
      ctx.restore();

      (payload.parcels || []).forEach(parcel => {
        const status = parcel.geofence_status || 'inside';
        if (status === 'invalid' || status === 'unconfigured') return;

        const parcelPath = canvasParcelPath(parcel.polygon, project);
        const parcelColor = parcel.color || '#22c55e';

        if (status === 'outside') {
          ctx.fillStyle = '#ffffff';
          ctx.fill(parcelPath);
          ctx.strokeStyle = '#dc2626';
          ctx.lineWidth = 5;
          ctx.stroke(parcelPath);
          return;
        }

        if (status === 'partial') {
          ctx.save();
          ctx.clip(boundaryPath, 'evenodd');
          ctx.fillStyle = hexAlpha(parcelColor, .38);
          ctx.fill(parcelPath);
          ctx.restore();
          ctx.strokeStyle = '#ea580c';
          ctx.lineWidth = 5;
          ctx.stroke(parcelPath);
        } else {
          ctx.fillStyle = hexAlpha(parcelColor, .35);
          ctx.fill(parcelPath);
          ctx.strokeStyle = status === 'near_boundary' ? '#ca8a04' : parcelColor;
          ctx.lineWidth = 3;
          ctx.stroke(parcelPath);
        }
      });

      ctx.strokeStyle = activeBoundary.color || '#15803d';
      ctx.lineWidth = 7;
      ctx.stroke(boundaryPath);

      // Preserve Google's complete attribution/logo strip even though the
      // rest of the satellite image is masked outside the municipal boundary.
      const attributionSourceHeight = Math.min(sourceHeight, Math.max(80, Math.ceil(sourceHeight * .08)));
      const attributionHeight = mapSize * (attributionSourceHeight / sourceHeight);
      ctx.drawImage(image, 0, sourceHeight - attributionSourceHeight, sourceWidth, attributionSourceHeight, mapX, mapY + mapSize - attributionHeight, mapSize, attributionHeight);

      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png', 1));
      if (!blob) throw new Error('The browser could not create the PNG snapshot.');
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = String(payload.municipality.name || 'municipality').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-land-snapshot.png';
      document.body.appendChild(link); link.click(); link.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      recordSnapshotExport(payload.snapshot.audit_url);
      toast('Municipality land snapshot downloaded.');
    } catch (error) {
      console.error(error);
      toast(error.message || 'Snapshot generation failed.', true);
    } finally {
      button.textContent = originalLabel;
      button.disabled = !(state.currentPayload && state.currentPayload.snapshot);
    }
  }

  function recordSnapshotExport(url) {
    if (!url) return;
    fetch(url, {
      method: 'POST',
      headers: {'Accept':'application/json','X-CSRF-TOKEN':settings.csrf},
      keepalive: true,
    }).then(response => {
      if (!response.ok) throw new Error('Audit endpoint returned ' + response.status + '.');
    }).catch(error => console.warn('Snapshot audit could not be recorded.', error));
  }

  function hexAlpha(hex, alpha) {
    const value = String(hex || '#22c55e').replace('#','');
    const normalized = value.length === 3 ? value.split('').map(item => item + item).join('') : value.slice(0,6);
    const number = parseInt(normalized, 16);
    if (!Number.isFinite(number)) return 'rgba(34,197,94,' + alpha + ')';
    return 'rgba(' + ((number >> 16) & 255) + ',' + ((number >> 8) & 255) + ',' + (number & 255) + ',' + alpha + ')';
  }

  function focusOverlay(overlays) {
    if (!overlays.length) return;
    const bounds = new google.maps.LatLngBounds();
    overlays.forEach(overlay => overlay.getPaths().forEach(path => path.forEach(point => bounds.extend(point))));
    state.map.fitBounds(bounds, 48);
  }

  function focusBoundary(id) {
    const boundary = state.boundaries.find(item => String(item.id) === String(id));
    if (!boundary) return;
    const box = boundsForBoundary(boundary);
    if (!Number.isFinite(box.south)) return;
    const bounds = new google.maps.LatLngBounds();
    bounds.extend({lat: box.south, lng: box.west});
    bounds.extend({lat: box.north, lng: box.east});
    state.map.fitBounds(bounds, 48);
  }

  function startDrawing() {
    cancelEditor();
    discardStyle();
    state.editorMode = 'create';
    syncStyleControls();
    state.barangays?.setEditing(true);
    state.draftPoints = [];
    el('boundaryEditor').hidden = false;
    el('editorTitle').textContent = 'Draw a municipality boundary';
    el('editorHelp').textContent = 'Click around the municipality on the map. Place points in order without crossing the boundary line.';
    el('editorMunicipality').disabled = false;
    el('editorMunicipality').value = state.selectedMunicipality || '';
    el('editorName').value = (state.selectedMunicipality ? municipalityName(state.selectedMunicipality) + ' Official Boundary' : '');
    el('editorColor').value = '#15803d';
    el('editorStatusField').hidden = false;
    el('replaceConfirmed').checked = false;
    updateDrawState();
    state.mapClick = state.map.addListener('click', event => {
      state.draftPoints.push(event.latLng);
      refreshDraftOverlay();
    });
    toast('Drawing mode started. Click the map to place boundary points.');
  }

  function editBoundary(boundary) {
    cancelEditor();
    discardStyle();
    if (!boundary || boundary.status === 'archived') return;
    const polygons = geometryPolygons(boundary.geojson);
    if (polygons.length !== 1 || polygons[0].length !== 1) {
      toast('MultiPolygon or holed boundaries must be replaced through file import.', true);
      return;
    }
    state.editorMode = 'edit';
    state.barangays?.setEditing(true);
    state.selectedBoundary = boundary;
    syncStyleControls();
    el('boundaryEditor').hidden = false;
    el('editorTitle').textContent = 'Edit municipality boundary';
    el('editorHelp').textContent = 'Drag the boundary points on the map, then save the revised official geometry.';
    el('editorMunicipality').value = String(boundary.municipality_id);
    el('editorMunicipality').disabled = true;
    el('editorName').value = boundary.name;
    el('editorColor').value = String(boundary.color).toLowerCase();
    el('editorStatusField').hidden = true;
    el('replaceConfirmed').checked = false;
    state.editableOverlay = new google.maps.Polygon({paths: googlePaths(polygons[0]), strokeColor: boundary.color, strokeWeight: 4, fillColor: boundary.color, fillOpacity: appearance.normalize(boundary).fill_opacity, editable: true, zIndex: 20});
    // Keep source coordinates for untouched vertices: map normalization must not move shared borders.
    state.editableOverlay.getPath().getArray().forEach((point, index) => {
      state.originalEditorCoordinates.set(point.lng() + ':' + point.lat(), polygons[0][0][index].slice(0, 2));
    });
    state.editableOverlay.setMap(state.map);
    applyFillOpacity();
    updateDrawState();
    focusOverlay([state.editableOverlay]);
  }

  function refreshDraftOverlay() {
    if (state.draftOverlay) state.draftOverlay.setMap(null);
    state.draftOverlay = new google.maps.Polygon({paths: state.draftPoints, strokeColor: el('editorColor').value, strokeWeight: 3, fillColor: el('editorColor').value, fillOpacity: .2, zIndex: 20});
    state.draftOverlay.setMap(state.map);
    updateDrawState();
  }

  function updateDrawState() {
    let count = state.draftPoints.length;
    if (state.editorMode === 'edit' && state.editableOverlay) count = state.editableOverlay.getPath().getLength();
    el('drawState').textContent = count + ' point' + (count === 1 ? '' : 's') + (count < 3 ? ' · at least 3 required' : ' · ready for validation');
  }

  function cancelEditor() {
    state.barangays?.setEditing(false);
    if (!settings.canManage) return;
    state.editorRevision++;
    if (state.mapClick) google.maps.event.removeListener(state.mapClick);
    state.mapClick = null;
    if (state.draftOverlay) state.draftOverlay.setMap(null);
    if (state.editableOverlay) state.editableOverlay.setMap(null);
    state.draftOverlay = null;
    state.editableOverlay = null;
    state.originalEditorCoordinates.clear();
    state.draftPoints = [];
    state.editorMode = null;
    applyFillOpacity();
    showEditorFeedback('');
    if (el('boundaryEditor')) el('boundaryEditor').hidden = true;
    syncStyleControls();
  }

  function showEditorFeedback(message) {
    const feedback = el('editorFeedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.hidden = !message;
    if (message) feedback.focus?.();
  }

  function pointsToGeoJson(points, originalCoordinates) {
    const coordinates = points.map(point => {
      const original = originalCoordinates?.get(point.lng() + ':' + point.lat());
      return original ? original.slice() : [point.lng(), point.lat()];
    });
    if (coordinates.length) {
      const first = coordinates[0];
      const last = coordinates[coordinates.length - 1];
      if (first[0] !== last[0] || first[1] !== last[1]) coordinates.push(first.slice());
    }
    return {type: 'Polygon', coordinates: [coordinates]};
  }

  async function saveEditor() {
    if (state.savingBoundary || !state.editorMode) return;
    showEditorFeedback('');
    const editing = state.editorMode === 'edit';
    const points = editing && state.editableOverlay ? state.editableOverlay.getPath().getArray() : state.draftPoints;
    if (points.length < 3) return showEditorFeedback('Place at least three boundary points before saving.');
    const municipalityId = el('editorMunicipality').value;
    if (!municipalityId) return showEditorFeedback('Select the municipality first.');
    const name = el('editorName').value.trim();
    if (!name) return showEditorFeedback('Enter a boundary name.');

    const body = {
      name: name,
      color: el('editorColor').value,
      replace_confirmed: el('replaceConfirmed').checked ? 1 : 0,
    };
    const geometry = pointsToGeoJson(points, editing ? state.originalEditorCoordinates : null);
    // GeoJSON object key order is not a shape change. Styling saves must never
    // submit geometry merely because the stored JSON lists coordinates first.
    if (!editing || geometry.type !== state.selectedBoundary.geojson.type
      || JSON.stringify(geometry.coordinates) !== JSON.stringify(state.selectedBoundary.geojson.coordinates)) {
      body.geojson = geometry;
    }
    if (editing && body.geojson && state.selectedBoundary.status === 'active' && !body.replace_confirmed) {
      return showEditorFeedback('The boundary points changed. Check the replacement confirmation before saving the new shape. Name and color changes alone do not need this confirmation.');
    }
    let url = settings.storeUrl;
    let method = 'POST';
    if (editing) {
      url = endpoint(settings.updateTemplate, state.selectedBoundary.id);
      method = 'PUT';
      body._record_version = state.selectedBoundary._record_version;
    } else {
      body.municipality_id = municipalityId;
      body.status = el('editorStatus').value;
    }

    const revision = state.editorRevision;
    const saveButton = el('saveBoundary');
    state.savingBoundary = true;
    saveButton.disabled = true;
    saveButton.textContent = 'Saving…';
    try {
      const payload = await request(url, {method, headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf}, body: JSON.stringify(body)});
      if (!payload.boundary?.id) throw new Error('The server did not confirm the save. Reload this workspace before trying again.');
      toast(payload.message);
      if (revision === state.editorRevision) {
        cancelEditor();
        filter.value = String(municipalityId);
        await loadMunicipality(municipalityId, payload.boundary.id, true);
      }
    } catch (error) {
      const message = error instanceof TypeError
        ? 'Unable to confirm the save. Check your connection and reload this workspace before trying again.'
        : error.message;
      if (revision === state.editorRevision) showEditorFeedback(message);
      toast(message, true);
    } finally {
      state.savingBoundary = false;
      saveButton.disabled = false;
      saveButton.textContent = 'Save boundary';
    }
  }

  async function changeStatus(boundary, action) {
    const verb = action === 'activate' ? 'activate this as the official boundary' : 'archive this boundary';
    if (!window.confirm('Are you sure you want to ' + verb + '?')) return;
    const body = {_record_version: boundary._record_version};
    body[action === 'activate' ? 'replace_confirmed' : 'archive_confirmed'] = 1;
    try {
      const payload = await request(endpoint(action === 'activate' ? settings.activateTemplate : settings.archiveTemplate, boundary.id), {method: 'POST', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': settings.csrf}, body: JSON.stringify(body)});
      toast(payload.message);
      await loadMunicipality(boundary.municipality_id, payload.boundary.id, true);
    } catch (error) { toast(error.message, true); }
  }

  function syncMapLabelsControl() {
    const mapType = state.map.getMapTypeId();
    const labelsVisible = mapType !== 'satellite';
    if (['hybrid', 'roadmap', 'terrain'].includes(mapType)) state.labeledMapType = mapType;
    const button = el('toggleMunicipalityMapLabels');
    button.textContent = 'Map labels: ' + (labelsVisible ? 'On' : 'Off');
    button.setAttribute('aria-pressed', String(labelsVisible));
    button.disabled = false;
  }

  function toggleMapLabels() {
    // Satellite imagery has no Google place or road labels and works with map IDs.
    // Keep AgriGOV overlays and the current camera independent of this choice.
    state.map.setMapTypeId(state.map.getMapTypeId() === 'satellite' ? state.labeledMapType : 'satellite');
  }

  function bindUi() {
    if (settings.canChooseMunicipality) filter.addEventListener('change', () => loadMunicipality(filter.value));
    el('toggleMunicipalityMapLabels').addEventListener('click', toggleMapLabels);
    el('fitVisible').addEventListener('click', fitVisible);
    el('resetMap').addEventListener('click', () => settings.canChooseMunicipality ? resetDefaultView() : fitVisible());
    el('downloadSnapshot').addEventListener('click', downloadMunicipalitySnapshot);
    el('boundarySearch')?.addEventListener('input', event => {
      const value = event.target.value.trim().toLowerCase();
      clearTimeout(state.searchTimer);
      if (!value) return;
      state.searchTimer = setTimeout(() => {
        const match = settings.municipalities.find(item => item.name.toLowerCase().includes(value));
        if (match) { filter.value = String(match.id); loadMunicipality(match.id); }
      }, 250);
    });

    panel.addEventListener('click', event => {
      if (event.target.closest('[data-retry-workspace]')) loadMunicipality(state.selectedMunicipality, null, true);
      const focusBoundaryButton = event.target.closest('[data-focus-boundary]');
      const editButton = event.target.closest('[data-edit-boundary]');
      const activateButton = event.target.closest('[data-activate-boundary]');
      const archiveButton = event.target.closest('[data-archive-boundary]');
      const plotCard = event.target.closest('[data-focus-plot]');
      if (focusBoundaryButton) focusBoundary(focusBoundaryButton.dataset.focusBoundary);
      if (editButton) editBoundary(state.boundaries.find(item => String(item.id) === String(editButton.dataset.editBoundary)));
      if (activateButton) changeStatus(state.boundaries.find(item => String(item.id) === String(activateButton.dataset.activateBoundary)), 'activate');
      if (archiveButton) changeStatus(state.boundaries.find(item => String(item.id) === String(archiveButton.dataset.archiveBoundary)), 'archive');
      if (plotCard) {
        const overlay = state.parcelOverlays.get(String(plotCard.dataset.focusPlot));
        if (overlay) focusOverlay([overlay]);
      }
    });

    if (!settings.canManage) return;
    el('startBoundary').addEventListener('click', startDrawing);
    el('cancelEditor').addEventListener('click', cancelEditor);
    el('saveBoundary').addEventListener('click', saveEditor);
    el('undoPoint').addEventListener('click', () => { if (state.editorMode === 'create') { state.draftPoints.pop(); refreshDraftOverlay(); } });
    el('clearPoints').addEventListener('click', () => { if (state.editorMode === 'create') { state.draftPoints = []; refreshDraftOverlay(); } else if (state.editableOverlay) { state.editableOverlay.getPath().clear(); updateDrawState(); } });
    el('editorColor').addEventListener('input', event => { if (state.draftOverlay) state.draftOverlay.setOptions({strokeColor:event.target.value,fillColor:event.target.value}); if (state.editableOverlay) state.editableOverlay.setOptions({strokeColor:event.target.value,fillColor:event.target.value}); });
    el('openImport').addEventListener('click', () => { el('importMunicipality').value = state.selectedMunicipality || ''; el('importDialog').showModal(); });
    ['closeImport','cancelImport'].forEach(id => el(id).addEventListener('click', () => el('importDialog').close()));
    el('importForm').addEventListener('submit', async event => {
      event.preventDefault();
      const data = new FormData(event.currentTarget);
      try {
        const payload = await request(settings.importUrl, {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':settings.csrf}, body:data});
        toast(payload.message); el('importDialog').close(); event.currentTarget.reset(); el('importColor').value='#15803d'; filter.value=String(payload.boundary.municipality_id); await loadMunicipality(payload.boundary.municipality_id,payload.boundary.id,true);
      } catch (error) { toast(error.message, true); }
    });
  }

  window.initMunicipalityGeofenceMap = function () {
    const options = {center:defaultViewport.center,zoom:defaultViewport.zoom,mapTypeId:'hybrid',streetViewControl:false,fullscreenControl:true,mapTypeControl:true,gestureHandling:'greedy'};
    if (settings.mapId) options.mapId = settings.mapId;
    state.map = new google.maps.Map(el('geofenceMap'), options);
    state.map.addListener('maptypeid_changed', syncMapLabelsControl);
    syncMapLabelsControl();
    state.info = new google.maps.InfoWindow();
    if (window.createBarangayBoundaryLayer) {
      state.barangays = window.createBarangayBoundaryLayer({map: state.map, request, url: settings.barangayUrl, municipalityIds: settings.barangayMunicipalityIds || []});
    }
    bindUi();
    fitVisible();
    state.map.addListener('idle', scheduleBoundaryRender);
    renderBoundaries();
    if (filter.value) loadMunicipality(filter.value);
  };

  el('geofenceOpacity').addEventListener('input', previewStyle);
  el('geofenceColor').addEventListener('input', previewStyle);
  el('geofenceStyleBoundary').addEventListener('change', event => {
    state.selectedBoundary = state.currentPayload?.boundaries.find(item => String(item.id) === event.target.value) || null;
    discardStyle();
  });
  el('saveGeofenceStyle')?.addEventListener('click', saveStyle);
  el('resetGeofenceStyle')?.addEventListener('click', discardStyle);

  if (!settings.key) {
    el('mapMessage').textContent = 'Google Maps is not configured. Add GOOGLE_MAPS_API_KEY and clear Laravel configuration cache.';
    return;
  }
  const script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(settings.key) + '&callback=initMunicipalityGeofenceMap&v=weekly&loading=async';
  script.async = true;
  script.defer = true;
  script.onerror = () => { el('mapMessage').textContent = 'Google Maps could not load. Check the API key, Maps JavaScript API, billing, and website restrictions.'; };
  document.head.appendChild(script);
})();
