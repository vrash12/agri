(() => {
    'use strict';

    const colorFor = count => count >= 200 ? '#155b32' : count >= 50 ? '#39864c' : count >= 10 ? '#85ba70' : count > 0 ? '#cce4b7' : '#e2e5e7';
    const chunks = ids => Array.from({length: Math.ceil(ids.length / 10)}, (_, i) => ids.slice(i * 10, i * 10 + 10));
    if (typeof module !== 'undefined' && module.exports) module.exports = {colorFor, chunks};
    if (typeof document === 'undefined') return;

    const settings = window.assistanceCoverageSettings;
    const loadButton = document.querySelector('[data-load-coverage]');
    const status = document.querySelector('[data-coverage-status]');
    const container = document.getElementById('assistance-coverage-map');
    if (!settings || !loadButton || !status || !container) return;
    const rows = new Map(settings.rows.map(row => [Number(row.id), row]));
    const focusButtons = Array.from(document.querySelectorAll('[data-focus-municipality]'));
    const season = document.getElementById('season');
    const year = document.getElementById('year');
    const updateYear = () => {
        if (!season || !year) return;
        year.disabled = season.value === 'unrecorded';
        year.required = ['dry', 'wet'].includes(season.value);
    };
    if (season) season.addEventListener('change', updateYear);
    updateYear();

    let map;
    let info;
    let loading;
    let loaded = false;
    let mapAuthFailed = false;
    let googlePromise;
    const loadedIds = new Set();
    const say = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', error);
    };
    const loadGoogle = () => {
        if (mapAuthFailed) return Promise.reject(new Error('Google Maps is unavailable for this site. Ask your administrator to check the map configuration.'));
        if (window.google?.maps?.Map) return Promise.resolve();
        if (googlePromise) return googlePromise;
        googlePromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            let timer;
            const fail = () => {
                window.clearTimeout(timer);
                script.remove();
                reject(new Error('Google Maps could not load. Check your connection and try again.'));
            };
            window.initAssistanceCoverageMap = () => { window.clearTimeout(timer); resolve(); };
            window.gm_authFailure = () => {
                mapAuthFailed = true;
                fail();
                loaded = false;
                container.hidden = true;
                loadButton.textContent = 'Retry map';
                say('Google Maps is unavailable for this site. Municipality figures remain available below.', true);
            };
            script.async = true;
            script.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(settings.key) + '&callback=initAssistanceCoverageMap&v=weekly&loading=async';
            script.onerror = fail;
            timer = window.setTimeout(fail, 20000);
            document.head.appendChild(script);
        }).catch(error => { googlePromise = null; throw error; });
        return googlePromise;
    };

    const addLine = (root, text, tag = 'p') => {
        const element = document.createElement(tag);
        element.textContent = text;
        root.appendChild(element);
    };
    const popup = (id, position) => {
        const row = rows.get(Number(id));
        if (!row) return;
        const content = document.createElement('div');
        content.className = 'coverage-popup';
        addLine(content, row.name, 'h3');
        addLine(content, Number(row.releases).toLocaleString() + ' recorded releases');
        addLine(content, Number(row.beneficiaries).toLocaleString() + ' linked beneficiaries');
        row.quantities.forEach(item => addLine(content, Number(item.quantity).toLocaleString(undefined, {maximumFractionDigits:2}) + ' ' + item.unit));
        if (!row.releases) addLine(content, 'No releases match the current filters.');
        info.setContent(content);
        info.setPosition(position);
        info.open({map});
    };
    const boundsFor = feature => {
        const bounds = new google.maps.LatLngBounds();
        feature.getGeometry().forEachLatLng(point => bounds.extend(point));
        return bounds;
    };
    const focus = id => {
        if (!map || !loadedIds.has(Number(id))) {
            say('This municipality has no usable boundary in the map. Its figures are available in the table.', true);
            return;
        }
        const feature = map.data.getFeatureById(Number(id));
        if (!feature) return;
        const bounds = boundsFor(feature);
        map.fitBounds(bounds, 40);
        popup(id, bounds.getCenter());
        container.scrollIntoView({behavior:'auto', block:'center'});
    };
    const fetchBoundaries = async ids => {
        const url = new URL(settings.boundariesUrl, window.location.origin);
        ids.forEach(id => url.searchParams.append('municipality_ids[]', id));
        const abort = new AbortController();
        const timeout = window.setTimeout(() => abort.abort(), 20000);
        try {
            const response = await fetch(url, {headers:{Accept:'application/json'}, signal:abort.signal, credentials:'same-origin'});
            if ([401, 403, 419].includes(response.status)) throw new Error('Your session or access changed. Reload this page and sign in again.');
            if (!response.ok) throw new Error('Municipality boundaries could not load. Try again; the table remains available.');
            const data = await response.json();
            if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) throw new Error('The boundary response was incomplete. Please try again.');
            return data;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Loading boundaries took too long. Check your connection and try again.');
            throw error;
        } finally { window.clearTimeout(timeout); }
    };
    const load = () => {
        if (loaded) return Promise.resolve();
        if (loading) return loading;
        loading = (async () => {
            loadButton.disabled = true;
            loadButton.textContent = 'Loading map…';
            say('Loading the map and municipality boundaries…');
            container.setAttribute('aria-busy', 'true');
            try {
                await loadGoogle();
                container.hidden = false;
                if (!map) {
                    map = new google.maps.Map(container, {center:{lat:15.5,lng:120.5}, zoom:8, mapTypeControl:true, streetViewControl:false, gestureHandling:'cooperative'});
                    info = new google.maps.InfoWindow();
                    map.data.setStyle(feature => ({fillColor:colorFor(rows.get(Number(feature.getId()))?.releases || 0), fillOpacity:0.72, strokeColor:'#40554a', strokeWeight:1.3, clickable:true}));
                    map.data.addListener('click', event => popup(event.feature.getId(), event.latLng));
                }
                const ids = [...rows.values()].filter(row => row.boundary === 'available').map(row => Number(row.id));
                const bounds = new google.maps.LatLngBounds();
                for (const batch of chunks(ids)) {
                    const data = await fetchBoundaries(batch);
                    if (mapAuthFailed) throw new Error('Google Maps is unavailable for this site. Municipality figures remain available below.');
                    const features = data.features.filter(feature => batch.includes(Number(feature.id)) && rows.has(Number(feature.id)));
                    map.data.addGeoJson({type:'FeatureCollection', features}).forEach(feature => {
                        loadedIds.add(Number(feature.getId()));
                        feature.getGeometry().forEachLatLng(point => bounds.extend(point));
                    });
                    say('Loaded ' + loadedIds.size + ' of ' + ids.length + ' available municipality boundaries…');
                }
                if (loadedIds.size) map.fitBounds(bounds, 40);
                else container.hidden = true;
                loaded = true;
                loadButton.textContent = 'Fit visible areas';
                say(loadedIds.size + ' municipality boundaries shown. ' + (rows.size - loadedIds.size) + ' area(s) appear only in the table. Select an area to see its totals.');
            } catch (error) {
                if (map) map.data.forEach(feature => map.data.remove(feature));
                loadedIds.clear();
                if (info) info.close();
                container.hidden = true;
                say(error.message || 'The map could not load. The table remains available.', true);
                loadButton.textContent = 'Retry map';
            } finally {
                loadButton.disabled = false;
                container.setAttribute('aria-busy', 'false');
                loading = null;
            }
        })();
        return loading;
    };

    if (!settings.key) {
        say('The map is not configured. All municipality figures are available in the table.');
        return;
    }
    if (![...rows.values()].some(row => row.boundary === 'available')) {
        say('No single active municipality boundaries are available in this scope. Review the figures below.');
        return;
    }
    loadButton.hidden = false;
    focusButtons.forEach(button => {
        button.hidden = false;
        button.addEventListener('click', async () => {
            await load();
            if (loaded) focus(Number(button.dataset.focusMunicipality));
        });
    });
    loadButton.addEventListener('click', async () => {
        await load();
        if (loaded && loadedIds.size) {
            const bounds = new google.maps.LatLngBounds();
            map.data.forEach(feature => feature.getGeometry().forEachLatLng(point => bounds.extend(point)));
            map.fitBounds(bounds, 40);
        }
    });
})();
