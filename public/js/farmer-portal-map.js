(function () {
    'use strict';
    var root = document.querySelector('[data-parcel-map]');
    if (!root) return;
    var status = root.querySelector('[data-map-status]');
    var canvas = root.querySelector('[data-map-canvas]');
    var button = root.querySelector('[data-load-map]');
    var retry = root.querySelector('[data-map-retry]');
    var fitButton = root.querySelector('[data-fit-map]');
    var select = root.querySelector('[data-map-parcel-select]');
    var detail = root.querySelector('[data-map-detail]');
    var detailName = root.querySelector('[data-map-detail-name]');
    var detailArea = root.querySelector('[data-map-detail-area]');
    var detailWarning = root.querySelector('[data-map-detail-warning]');
    var crops = root.querySelector('[data-map-crops]');
    var warning = root.querySelector('[data-map-warning]');
    var placeholder = root.querySelector('[data-map-placeholder]');
    var placeholderText = root.querySelector('[data-map-placeholder-text]');
    var records = document.querySelector('[data-map-records]');
    var plots = new Map(), polygons = new Map();
    var map, bounds, mapsPromise, mapsUnavailable = false;
    var afterId = 0, total = 0, complete = false, loading = false, selectedId = null;

    function mapError(message) {
        var error = new Error(message);
        error.userMessage = message;
        return error;
    }

    function showPlaceholder(message) {
        canvas.hidden = true;
        if (placeholder) placeholder.hidden = false;
        if (placeholderText) placeholderText.textContent = message;
        if (fitButton) fitButton.disabled = true;
    }

    function loadMaps() {
        if (mapsUnavailable) return Promise.reject(mapError('Maps are temporarily unavailable. Contact your agriculture office.'));
        if (window.google && google.maps && google.maps.Map) return Promise.resolve();
        if (mapsPromise) return mapsPromise;
        mapsPromise = new Promise(function (resolve, reject) {
            if (!root.dataset.mapsKey) {
                reject(mapError('Maps are unavailable. Your agriculture office can help.'));
                return;
            }
            var script = document.createElement('script');
            var timer = setTimeout(function () {
                script.remove();
                reject(mapError('The map took too long to load. Please try again.'));
            }, 20000);
            window.agriFarmerMapReady = function () { clearTimeout(timer); resolve(); };
            script.onerror = function () {
                clearTimeout(timer); script.remove();
                reject(mapError('The map could not load. Check your connection and try again.'));
            };
            window.gm_authFailure = function () {
                clearTimeout(timer);
                mapsUnavailable = true;
                var message = 'Maps are temporarily unavailable. Contact your agriculture office.';
                showPlaceholder(message);
                status.textContent = message;
                var retryControl = retry || button;
                if (retryControl) { retryControl.hidden = false; retryControl.disabled = false; }
                reject(mapError(message));
            };
            var url = new URL('https://maps.googleapis.com/maps/api/js');
            url.searchParams.set('key', root.dataset.mapsKey);
            url.searchParams.set('callback', 'agriFarmerMapReady');
            url.searchParams.set('loading', 'async');
            script.src = url.toString(); script.async = true; document.head.appendChild(script);
        }).catch(function (error) { mapsPromise = null; throw error; });
        return mapsPromise;
    }

    async function request(url) {
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 20000);
        try {
            var response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                var messages = {
                    401: 'Your session ended. Sign in again.',
                    404: 'This parcel is no longer available. Contact your agriculture office.',
                    422: 'This parcel needs office review before it can be displayed.',
                    429: 'Too many map requests. Wait a minute and try again.'
                };
                var error = mapError(messages[response.status] || 'The parcel map could not be loaded. Please try again.');
                error.sessionEnded = response.status === 401;
                throw error;
            }
            return await response.json();
        } finally { clearTimeout(timer); }
    }

    function validPlot(plot) {
        return Array.isArray(plot.paths) && plot.paths.length > 0 && plot.paths.every(function (path) {
            return Array.isArray(path) && path.length >= 3 && path.length <= 10000 && path.every(function (point) {
                return point && Number.isFinite(point.lat) && Number.isFinite(point.lng) && Math.abs(point.lat) <= 90 && Math.abs(point.lng) <= 180;
            });
        });
    }

    function extendBounds(target, plot) {
        plot.paths.forEach(function (path) { path.forEach(function (point) { target.extend(point); }); });
    }

    function fitAll() {
        if (map && bounds && polygons.size) map.fitBounds(bounds, 40);
    }

    function selectParcel(id, zoom) {
        var plot = plots.get(Number(id));
        if (!plot) return;
        var previous = polygons.get(selectedId);
        if (previous) previous.setOptions({ strokeWeight: 2, fillOpacity: 0.25, zIndex: 1 });
        selectedId = plot.id;
        select.value = String(plot.id);
        detail.hidden = false;
        detailName.textContent = plot.name || 'Unnamed parcel';
        detailArea.textContent = plot.area_ha === null ? 'Not recorded' : Number(plot.area_ha).toFixed(4) + ' ha';
        if (detailWarning) detailWarning.hidden = validPlot(plot);
        crops.textContent = '';
        if (!plot.crops.length) {
            var empty = document.createElement('li');
            empty.className = 'fp-muted';
            empty.textContent = 'No seasonal crops recorded for this year.';
            crops.appendChild(empty);
        } else plot.crops.forEach(function (crop) {
            var item = document.createElement('li'), season = document.createElement('span'), name = document.createElement('strong');
            season.textContent = crop.season;
            name.textContent = crop.crop;
            item.appendChild(season); item.appendChild(name); crops.appendChild(item);
        });
        var polygon = polygons.get(plot.id);
        if (polygon) {
            polygon.setOptions({ strokeWeight: 4, fillOpacity: 0.35, zIndex: 2 });
            if (zoom) {
                var selectedBounds = new google.maps.LatLngBounds();
                extendBounds(selectedBounds, plot); map.fitBounds(selectedBounds, 55);
            }
        }
    }

    function acceptPage(page) {
        if (!page || !Array.isArray(page.plots) || page.plots.length > 20 || !Number.isInteger(page.total) || page.total < 0) throw new Error('Invalid map page');
        var previous = afterId;
        page.plots.forEach(function (plot) {
            if (!Number.isSafeInteger(plot.id) || plot.id <= previous || !Array.isArray(plot.crops)
                || (plot.paths !== null && !validPlot(plot))) throw new Error('Invalid parcel');
            previous = plot.id;
        });
        if (page.next_after_id !== null && (!page.plots.length || page.next_after_id !== previous)) throw new Error('Invalid map cursor');
        if (!plots.size) select.textContent = '';
        page.plots.forEach(function (plot) {
            plots.set(plot.id, plot);
            var option = document.createElement('option');
            option.value = String(plot.id); option.textContent = plot.name || 'Unnamed parcel';
            select.appendChild(option);
        });
        total = page.total; complete = page.next_after_id === null; afterId = page.next_after_id || previous;
        if (selectedId === null && plots.size) selectParcel(plots.keys().next().value, false);
    }

    async function drawPending() {
        var pending = Array.from(plots.values()).filter(function (plot) { return validPlot(plot) && !polygons.has(plot.id); });
        if (!pending.length) return;
        await loadMaps();
        if (mapsUnavailable) throw mapError('Maps are temporarily unavailable. Contact your agriculture office.');
        canvas.hidden = false;
        if (placeholder) placeholder.hidden = true;
        if (!map) {
            map = new google.maps.Map(canvas, { center: pending[0].paths[0][0], zoom: 16, maxZoom: 20, mapTypeId: 'hybrid', streetViewControl: false, gestureHandling: 'cooperative' });
            bounds = new google.maps.LatLngBounds();
        }
        pending.forEach(function (plot) {
            var selected = plot.id === selectedId;
            var polygon = new google.maps.Polygon({
                map: map, paths: plot.paths, strokeColor: plot.color, fillColor: plot.color,
                fillOpacity: selected ? 0.35 : 0.25, strokeWeight: selected ? 4 : 2,
                zIndex: selected ? 2 : 1, clickable: true, editable: false, draggable: false
            });
            polygon.addListener('click', function () { selectParcel(plot.id, true); });
            polygons.set(plot.id, polygon); extendBounds(bounds, plot);
        });
        fitAll();
        fitButton.disabled = false;
    }

    function collectionStatus() {
        var invalid = Array.from(plots.values()).filter(function (plot) { return !validPlot(plot); }).length;
        warning.hidden = invalid === 0;
        warning.textContent = invalid + (invalid === 1 ? ' parcel needs' : ' parcels need') + ' office review. You can still choose these parcels to read their records.';
        if (!plots.size) {
            showPlaceholder('No farm parcels recorded yet.');
            select.textContent = '';
            var empty = document.createElement('option'); empty.textContent = 'No parcels available'; select.appendChild(empty);
            status.textContent = 'No farm parcels are currently linked to your record.';
        } else {
            if (!polygons.size) showPlaceholder('Your parcel boundaries need office review.');
            status.textContent = polygons.size + ' of ' + total + ' recorded parcels on the map.'
                + (invalid ? ' ' + invalid + ' need office review.' : '')
                + (!complete ? ' More parcels remain to be loaded.' : '')
                + (complete && total !== plots.size ? ' Records changed while loading. Refresh to see the latest list.' : '');
        }
    }

    async function loadCollection() {
        await drawPending();
        // Pause unusually large collections before reaching the route's request throttle.
        // The next action resumes at the same cursor and preserves the visible parcels.
        for (var pageNumber = 0; !complete && pageNumber < 20; pageNumber++) {
            var url = new URL(root.dataset.collectionUrl, window.location.href);
            url.searchParams.set('year', root.dataset.cropYear);
            if (afterId) url.searchParams.set('after_id', afterId);
            acceptPage(await request(url.toString()));
            await drawPending();
            collectionStatus();
        }
        collectionStatus();
        retry.hidden = complete;
        retry.textContent = 'Load remaining parcels';
    }

    async function loadSingle() {
        var data = await request(root.dataset.geometryUrl);
        await loadMaps(); canvas.hidden = false;
        map = new google.maps.Map(canvas, { center: data.plot.paths[0][0], zoom: 16, mapTypeId: 'hybrid', streetViewControl: false, gestureHandling: 'cooperative' });
        new google.maps.Polygon({ map: map, paths: data.plot.paths, strokeColor: data.plot.color, fillColor: data.plot.color, fillOpacity: 0.25, strokeWeight: 2, clickable: false, editable: false });
        var parcelBounds = new google.maps.LatLngBounds(); extendBounds(parcelBounds, data.plot); map.fitBounds(parcelBounds, 35);
        status.textContent = 'Showing the boundary recorded by your agriculture office.'; button.hidden = true;
    }

    async function run(loader, retryControl) {
        if (loading) return;
        loading = true; retryControl.disabled = true;
        if (select) select.disabled = true;
        status.textContent = 'Loading your parcel map…';
        try { await loader(); }
        catch (error) {
            var message = error.name === 'AbortError' ? 'The request took too long. Please try again.'
                : (error.userMessage || 'The parcel map could not be loaded. Check your connection and try again.');
            if (error.sessionEnded) {
                // An expired request must remove already loaded private map and detail content.
                polygons.forEach(function (polygon) { polygon.setMap(null); });
                polygons.clear(); plots.clear(); selectedId = null; afterId = 0; complete = false;
                map = null; bounds = null;
                if (select) select.textContent = '';
                if (detail) detail.hidden = true;
                if (crops) crops.textContent = '';
                if (warning) warning.hidden = true;
            }
            if (records && !error.sessionEnded) records.open = true;
            if (!polygons.size || mapsUnavailable) showPlaceholder(message);
            status.textContent = (polygons.size && !mapsUnavailable ? 'Only ' + polygons.size + ' of ' + total + ' parcels are shown. ' : '') + message;
            retryControl.textContent = 'Try loading the map again'; retryControl.hidden = false;
            if (select && !plots.size) {
                select.textContent = '';
                var option = document.createElement('option'); option.textContent = 'Parcel records unavailable'; select.appendChild(option);
            }
        } finally {
            loading = false; retryControl.disabled = false;
            if (select) select.disabled = plots.size === 0;
        }
    }

    if (root.dataset.collectionUrl) {
        select.addEventListener('change', function () { selectParcel(select.value, true); });
        fitButton.addEventListener('click', fitAll);
        retry.addEventListener('click', function () { return run(loadCollection, retry); });
        run(loadCollection, retry);
    } else if (button) {
        button.addEventListener('click', function () { return run(loadSingle, button); });
    }
})();
