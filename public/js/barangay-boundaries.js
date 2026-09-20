/* Read-only, municipality-scoped reference overlays. No operational ownership is inferred here. */
(function () {
  'use strict';

  window.createBarangayBoundaryLayer = function ({map, request, url, municipalityIds}) {
    const byId = id => document.getElementById(id);
    const toggle = byId('showBarangays');
    const select = byId('barangaySelect');
    const focus = byId('focusBarangay');
    const status = byId('barangayStatus');
    const retry = byId('retryBarangays');
    const source = byId('barangaySource');
    const controls = byId('barangayControls');
    const supported = new Set(municipalityIds.map(String));
    const info = new google.maps.InfoWindow();
    let municipality = '', revision = 0, controller = null, timer = null;
    let cached = null, items = [], editing = false;

    function stopRequest() {
      ++revision;
      controller?.abort();
      controller = null;
      clearTimeout(timer);
      controls.setAttribute('aria-busy', 'false');
    }

    function clear() {
      info.close();
      items.forEach(item => {
        item.overlays.concat(item.marker).forEach(overlay => {
          google.maps.event.clearInstanceListeners(overlay);
          overlay.setMap(null);
        });
      });
      items = [];
      select.replaceChildren(new Option('All barangays', ''));
      select.disabled = true;
      focus.disabled = true;
      source.hidden = true;
      retry.hidden = true;
    }

    function labels() {
      items.forEach(item => item.marker.setVisible(!editing && (map.getZoom() >= 14 || select.value === item.code)));
    }

    function highlight() {
      items.forEach(item => item.overlays[1].setOptions({
        strokeColor: select.value === item.code ? '#b45309' : '#146c68',
        strokeWeight: select.value === item.code ? 4 : 2,
        fillColor: '#facc15',
        fillOpacity: select.value === item.code ? .2 : 0,
      }));
      labels();
    }

    function showInfo(item) {
      select.value = item.code;
      highlight();
      const content = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = item.name;
      const detail = document.createElement('p');
      detail.textContent = 'Ramos, Tarlac · PSGC ' + item.code + ' · Planning reference';
      content.append(title, detail);
      info.setContent(content);
      info.setPosition(item.position);
      info.open({map});
    }

    function draw(payload) {
      clear();
      if (!payload.available || !payload.features.length) {
        status.textContent = 'No barangay reference is available for this municipality.';
        return;
      }
      payload.features.forEach(feature => {
        const props = feature.properties;
        const paths = feature.geometry.coordinates.map(ring => ring.map(point => ({lng: point[0], lat: point[1]})));
        const bounds = new google.maps.LatLngBounds();
        paths.forEach(ring => ring.forEach(point => bounds.extend(point)));
        const casing = new google.maps.Polygon({map, paths, strokeColor: '#ffffff', strokeWeight: 5, strokeOpacity: .95, fillOpacity: 0, clickable: false, zIndex: 4.8});
        const outline = new google.maps.Polygon({map, paths, strokeColor: '#146c68', strokeWeight: 2, strokeOpacity: 1, fillOpacity: 0, zIndex: 5});
        const marker = new google.maps.Marker({map, position: props.label_position, title: props.name + ' barangay',
          icon: {path: google.maps.SymbolPath.CIRCLE, scale: 0},
          label: {text: props.name, className: 'geo-barangay-label', fontSize: '12px', fontWeight: '700'}, zIndex: 5});
        const item = {name: props.name, code: props.psgc, position: props.label_position, bounds, overlays: [casing, outline], marker};
        outline.addListener('click', () => showInfo(item));
        marker.addListener('click', () => showInfo(item));
        items.push(item);
        select.add(new Option(item.name, item.code));
      });
      select.disabled = editing;
      focus.disabled = editing;
      source.hidden = false;
      byId('barangaySourceLink').textContent = payload.source.label;
      byId('barangaySourceLink').href = payload.source.url;
      byId('barangaySourceNote').textContent = payload.source.note;
      status.textContent = items.length + ' barangay boundaries shown. Select a barangay to highlight it.';
      highlight();
      applyEditing();
    }

    async function load() {
      stopRequest();
      clear();
      if (!supported.has(municipality)) {
        status.textContent = municipality ? 'No barangay reference has been added for this municipality.' : 'Select a municipality to see available barangay boundaries.';
        return;
      }
      if (!toggle.checked) {
        status.textContent = 'Barangay boundaries hidden. Turn on the layer to view them.';
        return;
      }
      if (cached && String(cached.municipality_id) === municipality) {
        draw(cached);
        return;
      }
      const current = revision;
      const currentMunicipality = municipality;
      controller = new AbortController();
      let timedOut = false;
      const pendingController = controller;
      timer = setTimeout(() => { timedOut = true; pendingController.abort(); }, 20000);
      controls.setAttribute('aria-busy', 'true');
      status.textContent = 'Loading barangay boundaries…';
      try {
        const payload = await request(url + '?municipality_id=' + encodeURIComponent(currentMunicipality), {signal: controller.signal});
        if (current !== revision) return;
        if (String(payload.municipality_id) !== currentMunicipality || !Array.isArray(payload.features)) throw new Error('The server returned an unexpected barangay reference. Please reload the workspace.');
        cached = payload;
        draw(payload);
      } catch (error) {
        if (current !== revision) return;
        clear();
        cached = null;
        status.textContent = timedOut ? 'Loading took too long. Please try again.' : error.message;
        retry.hidden = false;
      } finally {
        if (current === revision) {
          clearTimeout(timer);
          controller = null;
          controls.setAttribute('aria-busy', 'false');
        }
      }
    }

    function applyEditing() {
      toggle.disabled = editing || !supported.has(municipality);
      select.disabled = focus.disabled = editing || !items.length;
      items.forEach(item => item.overlays.concat(item.marker).forEach(overlay => overlay.setMap(editing ? null : map)));
      if (editing) info.close();
      byId('barangayEditingNote').hidden = !editing;
      labels();
    }

    toggle.addEventListener('change', load);
    retry.addEventListener('click', load);
    select.addEventListener('change', () => { info.close(); highlight(); });
    focus.addEventListener('click', () => {
      const bounds = new google.maps.LatLngBounds();
      items.filter(item => !select.value || select.value === item.code).forEach(item => bounds.union(item.bounds));
      if (!bounds.isEmpty()) map.fitBounds(bounds, 45);
    });
    map.addListener('zoom_changed', labels);

    return {
      selectMunicipality(id) {
        municipality = String(id || '');
        toggle.disabled = editing || !supported.has(municipality);
        return load();
      },
      setEditing(value) { editing = value; applyEditing(); },
    };
  };
})();
