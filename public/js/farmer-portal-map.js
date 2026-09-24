(function () {
    'use strict';
    var root = document.querySelector('[data-parcel-map]');
    if (!root) return;
    var button = root.querySelector('[data-load-map]'), status = root.querySelector('[data-map-status]');
    var canvas = root.querySelector('[data-map-canvas]'), mapsPromise;
    function mapError(message) {
        var error = new Error(message);
        error.userMessage = message;
        return error;
    }
    function loadMaps() {
        if (window.google && google.maps && google.maps.Map) return Promise.resolve();
        if (mapsPromise) return mapsPromise;
        mapsPromise = new Promise(function (resolve, reject) {
            if (!root.dataset.mapsKey) { reject(mapError('Maps are unavailable. Your agriculture office can help.')); return; }
            var script = document.createElement('script');
            var timer = setTimeout(function () { script.remove(); reject(mapError('The map took too long to load. Please try again.')); }, 20000);
            window.agriFarmerMapReady = function () { clearTimeout(timer); resolve(); };
            script.onerror = function () { clearTimeout(timer); script.remove(); reject(mapError('The map could not load. Check your connection and try again.')); };
            window.gm_authFailure = function () { clearTimeout(timer); canvas.hidden = true; status.textContent = 'Maps are temporarily unavailable. Contact your agriculture office.'; reject(mapError(status.textContent)); };
            var url = new URL('https://maps.googleapis.com/maps/api/js');
            url.searchParams.set('key', root.dataset.mapsKey);
            url.searchParams.set('callback', 'agriFarmerMapReady');
            url.searchParams.set('loading', 'async');
            script.src = url.toString(); script.async = true; document.head.appendChild(script);
        }).catch(function (error) { mapsPromise = null; throw error; });
        return mapsPromise;
    }
    button.addEventListener('click', async function () {
        button.disabled = true; status.textContent = 'Loading your parcel…';
        var controller = new AbortController(), timeout = setTimeout(function () { controller.abort(); }, 20000);
        try {
            var response = await fetch(root.dataset.geometryUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) {
                var messages = {
                    401: 'Your session ended. Sign in again.',
                    404: 'This parcel is no longer available. Contact your agriculture office.',
                    422: 'This parcel needs office review before it can be displayed.',
                    429: 'Too many map requests. Wait a minute and try again.'
                };
                throw mapError(messages[response.status] || 'The parcel could not be loaded. Please try again.');
            }
            var data = await response.json();
            await loadMaps();
            canvas.hidden = false;
            var map = new google.maps.Map(canvas, { center: data.plot.paths[0][0], zoom: 16, mapTypeId: 'hybrid', streetViewControl: false, gestureHandling: 'cooperative' });
            new google.maps.Polygon({ map: map, paths: data.plot.paths, strokeColor: data.plot.color, fillColor: data.plot.color, fillOpacity: 0.25, strokeWeight: 2, clickable: false, editable: false });
            var bounds = new google.maps.LatLngBounds();
            data.plot.paths.forEach(function (path) { path.forEach(function (point) { bounds.extend(point); }); });
            map.fitBounds(bounds, 35);
            status.textContent = 'Showing the boundary recorded by your agriculture office.';
            button.hidden = true;
        } catch (error) {
            canvas.hidden = true;
            status.textContent = error.name === 'AbortError' ? 'The request took too long. Please try again.'
                : (error.userMessage || 'The parcel map could not be loaded. Check your connection and try again.');
            button.textContent = 'Try loading the map again'; button.disabled = false;
        } finally { clearTimeout(timeout); }
    });
})();
