(function () {
  'use strict';

  if (window.MunicipalitySnapshotExport) return;

  function polygons(geometry) {
    if (!geometry || !Array.isArray(geometry.coordinates)) return [];
    if (geometry.type === 'Polygon') return [geometry.coordinates];
    return geometry.type === 'MultiPolygon' ? geometry.coordinates : [];
  }

  function worldPoint(lat, lng, zoom) {
    var safeLat = Math.max(-85.05112878, Math.min(85.05112878, Number(lat)));
    var sine = Math.sin(safeLat * Math.PI / 180);
    var factor = Math.pow(2, Number(zoom));
    return {
      x: ((Number(lng) + 180) / 360) * 256 * factor,
      y: (0.5 - Math.log((1 + sine) / (1 - sine)) / (4 * Math.PI)) * 256 * factor
    };
  }

  async function loadImage(url) {
    var response = await fetch(url, { headers: { Accept: 'image/png,image/*' } });
    if (!response.ok) {
      var message = await response.text();
      throw new Error(message || 'The satellite base image could not be generated.');
    }

    var blob = await response.blob();
    return new Promise(function (resolve, reject) {
      var image = new Image();
      var objectUrl = URL.createObjectURL(blob);
      image.onload = function () { URL.revokeObjectURL(objectUrl); resolve(image); };
      image.onerror = function () {
        URL.revokeObjectURL(objectUrl);
        reject(new Error('The satellite base image could not be read.'));
      };
      image.src = objectUrl;
    });
  }

  function geometryPath(geometry, project) {
    var path = new Path2D();
    polygons(geometry).forEach(function (polygon) {
      polygon.forEach(function (ring) {
        ring.forEach(function (point, index) {
          var pixel = project(Number(point[1]), Number(point[0]));
          if (index === 0) path.moveTo(pixel.x, pixel.y);
          else path.lineTo(pixel.x, pixel.y);
        });
        path.closePath();
      });
    });
    return path;
  }

  function parcelPath(points, project) {
    var path = new Path2D();
    (points || []).forEach(function (point, index) {
      var pixel = project(Number(point.lat), Number(point.lng));
      if (index === 0) path.moveTo(pixel.x, pixel.y);
      else path.lineTo(pixel.x, pixel.y);
    });
    path.closePath();
    return path;
  }

  function alphaColor(hex, alpha) {
    var value = String(hex || '#22c55e').replace('#', '');
    var normalized = value.length === 3
      ? value.split('').map(function (item) { return item + item; }).join('')
      : value.slice(0, 6);
    var number = parseInt(normalized, 16);
    if (!Number.isFinite(number)) return 'rgba(34,197,94,' + alpha + ')';
    return 'rgba(' + ((number >> 16) & 255) + ',' + ((number >> 8) & 255) + ',' + (number & 255) + ',' + alpha + ')';
  }

  function audit(url, csrfToken) {
    if (!url) return;
    fetch(url, {
      method: 'POST',
      headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
      keepalive: true
    }).then(function (response) {
      if (!response.ok) throw new Error('Audit endpoint returned ' + response.status + '.');
    }).catch(function (error) {
      console.warn('Snapshot audit could not be recorded.', error);
    });
  }

  async function download(payload, csrfToken) {
    if (!payload || !payload.snapshot) {
      throw new Error('Select a municipality with an active official boundary first.');
    }

    var activeBoundary = (payload.boundaries || []).find(function (item) {
      return item.status === 'active';
    });
    if (!activeBoundary || !activeBoundary.geojson) {
      throw new Error('The selected municipality has no active official boundary.');
    }

    var image = await loadImage(payload.snapshot.base_map_url);
    var sourceWidth = Number(image.naturalWidth || payload.snapshot.source_size || 1280);
    var sourceHeight = Number(image.naturalHeight || payload.snapshot.source_size || 1280);
    var mapSize = Math.min(sourceWidth, sourceHeight);
    var canvas = document.createElement('canvas');
    canvas.width = mapSize;
    canvas.height = mapSize;
    var context = canvas.getContext('2d');
    var viewportSize = Number(payload.snapshot.viewport_size || 640);
    var sourceScaleX = sourceWidth / viewportSize;
    var sourceScaleY = sourceHeight / viewportSize;
    var zoom = Number(payload.snapshot.zoom);
    var center = worldPoint(payload.snapshot.center_lat, payload.snapshot.center_lng, zoom);
    var project = function (lat, lng) {
      var point = worldPoint(lat, lng, zoom);
      return {
        x: (((point.x - center.x) * sourceScaleX) + sourceWidth / 2) * (mapSize / sourceWidth),
        y: (((point.y - center.y) * sourceScaleY) + sourceHeight / 2) * (mapSize / sourceHeight)
      };
    };

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, mapSize, mapSize);
    var boundaryPath = geometryPath(activeBoundary.geojson, project);
    context.save();
    context.clip(boundaryPath, 'evenodd');
    context.drawImage(image, 0, 0, mapSize, mapSize);
    context.restore();

    (payload.parcels || []).forEach(function (parcel) {
      var status = parcel.geofence_status || 'inside';
      if (status === 'invalid' || status === 'unconfigured') return;
      var path = parcelPath(parcel.polygon, project);
      var color = parcel.color || '#22c55e';

      if (status === 'outside') {
        context.fillStyle = '#ffffff';
        context.fill(path);
        context.strokeStyle = '#dc2626';
        context.lineWidth = 5;
        context.stroke(path);
        return;
      }

      if (status === 'partial') {
        context.save();
        context.clip(boundaryPath, 'evenodd');
        context.fillStyle = alphaColor(color, .38);
        context.fill(path);
        context.restore();
        context.strokeStyle = '#ea580c';
        context.lineWidth = 5;
        context.stroke(path);
        return;
      }

      context.fillStyle = alphaColor(color, .35);
      context.fill(path);
      context.strokeStyle = status === 'near_boundary' ? '#ca8a04' : color;
      context.lineWidth = 3;
      context.stroke(path);
    });

    context.strokeStyle = activeBoundary.color || '#15803d';
    context.lineWidth = 7;
    context.stroke(boundaryPath);

    // Google Maps attribution cannot be removed or obscured. Preserve the
    // complete attribution band supplied with the Static Maps response.
    var attributionSourceHeight = Math.min(sourceHeight, Math.max(80, Math.ceil(sourceHeight * .08)));
    var attributionHeight = mapSize * (attributionSourceHeight / sourceHeight);
    context.drawImage(
      image,
      0,
      sourceHeight - attributionSourceHeight,
      sourceWidth,
      attributionSourceHeight,
      0,
      mapSize - attributionHeight,
      mapSize,
      attributionHeight
    );

    var output = await new Promise(function (resolve) {
      canvas.toBlob(resolve, 'image/png', 1);
    });
    if (!output) throw new Error('The browser could not create the PNG snapshot.');

    var url = URL.createObjectURL(output);
    var link = document.createElement('a');
    link.href = url;
    link.download = String(payload.municipality.name || 'municipality')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-') + '-land-map.png';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    audit(payload.snapshot.audit_url, csrfToken);
  }

  window.MunicipalitySnapshotExport = { download: download };
})();
