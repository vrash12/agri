(function (root, factory) {
  'use strict';
  const api = factory();
  root.FarmerParcelStats = api;
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';

  // These application-owned categories match ParcelCropSeason and CropAreaBadges.
  const crops = {
    rice: ['Rice / Palay', '#219653'], corn: ['Corn', '#D4A017'],
    vegetables: ['Vegetables', '#8E44AD'], root_crops: ['Root crops', '#A65C32'],
    fruit: ['Fruit', '#DB5B36'], legumes: ['Legumes', '#168C94'],
    mixed: ['Mixed crops', '#4263C7'], other: ['Other crop', '#C44379']
  };
  const colors = { recorded: '#24734B', notRecorded: '#DFE8E1', unknown: '#A9B5BF' };
  const rendered = new WeakMap();
  const landMessages = {
    empty: 'Select a farmer to see their land and saved crops.',
    loading: 'Loading this farmer’s parcels…',
    error: 'The farmer’s parcels could not load. Choose Try again.'
  };
  const cropMessages = {
    loading: 'Loading saved crops for this season…',
    error: 'Crop records could not load. Choose Try again.',
    off: 'Crop records are not available. Reload the page to try again.'
  };
  function id(value) {
    return typeof value === 'string' && value.trim() ? value.trim()
      : typeof value === 'number' && Number.isFinite(value) ? String(value) : null;
  }
  function area(value) {
    if (typeof value !== 'number' && typeof value !== 'string') return null;
    if (typeof value === 'string' && !value.trim()) return null;
    const number = Number(value);
    return Number.isFinite(number) && number > 0 ? number : null;
  }
  function areaLabel(value) {
    if (value === null) return 'Not recorded';
    if (value > 0 && value < 0.001) return '< 0.001 ha';
    return value.toLocaleString('en-PH', { minimumFractionDigits: value < 0.1 ? 3 : 2, maximumFractionDigits: value < 0.1 ? 3 : 2 }) + ' ha';
  }
  function parcelCrops(record) {
    if (!record || !Object.hasOwn(crops, record.crop)) return [];
    const values = Array.isArray(record.area_crops) && record.area_crops.length ? record.area_crops : [record.crop];
    return [...new Set(values.filter(code => typeof code === 'string' && Object.hasOwn(crops, code)))];
  }
  function summarize(input) {
    input = input || {};
    const landStatus = ['ready', 'loading', 'error', 'empty'].includes(input.landStatus) ? input.landStatus : 'empty';
    const cropStatus = ['ready', 'loading', 'error', 'off'].includes(input.cropStatus) ? input.cropStatus : 'off';
    const year = Number(input.year);
    const periodLabel = Number.isInteger(year) && year >= 1990 && year <= 9999 && ['dry', 'wet'].includes(input.season)
      ? year + ' · ' + (input.season === 'dry' ? 'Dry season' : 'Wet season') : 'Selected season';
    const model = {
      landStatus, cropStatus, periodLabel, statusText: landMessages[landStatus] || '',
      ready: false, totalParcels: null, totalAreaHa: null, totalAreaLabel: '—',
      knownAreaParcels: 0, unknownAreaParcels: 0, landRows: [], topLandRows: [],
      cropRows: [], coverage: null, areaNote: '', cropMessage: '',
      cropNote: 'Counts show parcels, not planted hectares. A parcel with several crops appears in each crop’s count.'
    };
    // Never retain a previous farmer’s chart while the next selection is pending.
    if (landStatus !== 'ready') return model;
    const unique = new Map();
    (Array.isArray(input.plots) ? input.plots : []).forEach(plot => {
      const key = id(plot?.id);
      if (key === null || unique.has(key)) return;
      unique.set(key, {
        id: key, label: typeof plot.name === 'string' && plot.name.trim() ? plot.name.trim().slice(0, 240) : 'Parcel ' + (unique.size + 1),
        areaHa: area(plot.area_ha), selected: key === id(input.selectedPlotId)
      });
    });
    model.totalParcels = unique.size;
    if (!unique.size) {
      model.statusText = 'This farmer has no saved parcels yet. Add a parcel to see land and crop charts.';
      return model;
    }
    model.ready = true;
    model.landRows = [...unique.values()].sort((a, b) => (b.areaHa || 0) - (a.areaHa || 0));
    let total = 0;
    model.landRows.forEach(row => {
      if (row.areaHa !== null) { total += row.areaHa; model.knownAreaParcels++; }
      else model.unknownAreaParcels++;
    });
    model.totalAreaHa = model.knownAreaParcels && Number.isFinite(total) ? total : null;
    model.totalAreaLabel = areaLabel(model.totalAreaHa);
    const maxArea = model.landRows[0].areaHa || 0;
    model.landRows.forEach(row => {
      row.areaLabel = areaLabel(row.areaHa);
      row.widthPercent = maxArea && row.areaHa !== null ? row.areaHa / maxArea * 100 : 0;
    });
    model.topLandRows = model.landRows.slice(0, 6);
    model.areaNote = 'Approximate parcel area from saved records; this is not measured planted area.';
    if (model.unknownAreaParcels) model.areaNote += ' ' + model.unknownAreaParcels + ' parcel' + (model.unknownAreaParcels === 1 ? ' has' : 's have') + ' no usable area value.';

    const records = new Map();
    if (cropStatus === 'ready') (Array.isArray(input.records) ? input.records : []).forEach(record => {
      const key = id(record?.plot_id);
      if (key === null || !unique.has(key)) return;
      if (record.year !== undefined && Number(record.year) !== year) return;
      if (record.season !== undefined && record.season !== input.season) return;
      // Ambiguous duplicates are unavailable, rather than whichever row arrived last.
      records.set(key, records.has(key) ? null : record);
    });
    const counts = new Map();
    const coverage = { recorded: 0, notRecorded: 0, unknown: 0, total: unique.size, complete: false, percentage: null };
    unique.forEach((plot, key) => {
      const record = records.get(key);
      const codes = parcelCrops(record);
      if (cropStatus === 'ready' && record?.crop === 'not_recorded') coverage.notRecorded++;
      else if (cropStatus === 'ready' && codes.length) {
        coverage.recorded++;
        codes.forEach(code => counts.set(code, (counts.get(code) || 0) + 1));
      } else coverage.unknown++;
    });
    coverage.complete = coverage.unknown === 0;
    coverage.percentage = coverage.complete ? Math.round(coverage.recorded / coverage.total * 100) : null;
    model.coverage = coverage;
    model.cropRows = [...counts].map(([code, count]) => ({ code, label: crops[code][0], color: crops[code][1], count, widthPercent: count / unique.size * 100 }))
      .sort((a, b) => b.count - a.count || a.label.localeCompare(b.label));
    model.cropMessage = cropMessages[cropStatus] || (coverage.unknown ? 'Some crop records are unavailable. Showing confirmed records only.' : '');
    model.statusText = model.cropMessage;
    return model;
  }

  function render(element, input) {
    const model = summarize(input);
    if (!element || !element.ownerDocument) return model;
    const signature = JSON.stringify(model);
    const previous = rendered.get(element);
    // Layer notifications can repeat unchanged data. Preserve open details and focus.
    if (previous?.signature === signature) return model;
    const landSignature = model.ready ? JSON.stringify(model.landRows) : null;
    const preserveLand = model.ready && previous?.landSignature === landSignature;
    rendered.set(element, { signature, landSignature });
    const document = element.ownerDocument;
    const find = name => element.querySelector('[data-parcel-stats-' + name + ']');
    const node = (tag, className, text) => {
      const child = document.createElement(tag);
      if (className) child.className = className;
      if (text !== undefined) child.textContent = String(text);
      return child;
    };
    const setText = (name, text) => { const target = find(name); if (target) target.textContent = text; };
    ['metrics', 'crop-chart', 'coverage'].forEach(name => find(name)?.replaceChildren());
    if (!preserveLand) ['land-chart', 'land-all'].forEach(name => find(name)?.replaceChildren());
    setText('period', model.periodLabel);
    setText('status', model.statusText);
    if (find('status')) find('status').hidden = !model.statusText;
    if (find('retry')) find('retry').hidden = !(model.landStatus === 'error' || (model.ready && model.cropStatus === 'error'));
    if (find('content')) find('content').hidden = !model.ready;
    setText('area-note', model.areaNote);
    setText('crop-note', model.cropNote);
    if (!model.ready) return model;

    function metric(label, value, detail, legacyId) {
      const card = node('div', 'fps-metric');
      const number = node('strong', 'fps-metric-value', value);
      if (legacyId) number.id = legacyId;
      card.appendChild(node('span', 'fps-metric-label', label));
      card.appendChild(number);
      card.appendChild(node('span', 'fps-metric-detail', detail));
      find('metrics')?.appendChild(card);
    }
    metric('Saved parcels', model.totalParcels, 'This farmer’s land records', 'plotCountPill');
    metric('Recorded land area', model.totalAreaHa === null ? '—' : model.totalAreaLabel,
      model.unknownAreaParcels ? model.knownAreaParcels + ' of ' + model.totalParcels + ' parcels have area values' : 'Approximate hectares', 'plotAreaTotalPill');
    metric('Saved crop types', model.cropStatus !== 'ready' || (!model.cropRows.length && model.coverage.unknown) ? '—'
      : (model.coverage.unknown ? '≥ ' : '') + model.cropRows.length,
      model.coverage.unknown ? 'Some crop records are not available' : 'For the selected season');

    function bar(parent, name, value, width, color, selected) {
      const row = node('div', 'fps-bar-row' + (selected ? ' is-selected' : ''));
      const label = node('span', 'fps-bar-name', name + (selected ? ' · Selected' : ''));
      const track = node('div', 'fps-bar-track');
      const fill = node('span', 'fps-bar-fill');
      track.setAttribute('aria-hidden', 'true');
      fill.style.width = Math.max(0, Math.min(100, width)) + '%';
      fill.style.backgroundColor = color;
      track.appendChild(fill);
      row.appendChild(label); row.appendChild(node('span', 'fps-bar-value', value)); row.appendChild(track);
      parent?.appendChild(row);
    }
    if (!preserveLand) model.topLandRows.forEach(row => bar(find('land-chart'), row.label, row.areaLabel, row.widthPercent, '#43866B', row.selected));

    if (!preserveLand && model.landRows.length > 6 && find('land-all')) {
      const details = node('details', 'fps-all');
      details.appendChild(node('summary', '', 'View all ' + model.totalParcels + ' parcels'));
      const body = node('div');
      details.appendChild(body);
      let page = 0, built = false;
      const pageSize = 50;
      function drawPage(moveFocus) {
        body.replaceChildren();
        const table = node('table');
        const caption = node('caption', '', 'Recorded parcel area in hectares');
        const head = node('thead'), header = node('tr');
        ['Parcel', 'Area'].forEach(text => { const cell = node('th', '', text); cell.setAttribute('scope', 'col'); header.appendChild(cell); });
        head.appendChild(header); table.appendChild(caption); table.appendChild(head);
        const rows = node('tbody');
        model.landRows.slice(page * pageSize, (page + 1) * pageSize).forEach(row => {
          const tr = node('tr', row.selected ? 'is-selected' : '');
          const name = node('th', '', row.label + (row.selected ? ' · Selected' : '')); name.setAttribute('scope', 'row');
          tr.appendChild(name); tr.appendChild(node('td', '', row.areaLabel)); rows.appendChild(tr);
        });
        table.appendChild(rows); body.appendChild(table);
        if (model.totalParcels > pageSize) {
          const controls = node('div', 'fps-pagination');
          const previous = node('button', '', 'Previous parcels'), next = node('button', '', 'Next parcels');
          previous.type = next.type = 'button';
          previous.disabled = page === 0; next.disabled = (page + 1) * pageSize >= model.totalParcels;
          previous.addEventListener('click', () => { if (page > 0) { page--; drawPage(true); } });
          next.addEventListener('click', () => { if ((page + 1) * pageSize < model.totalParcels) { page++; drawPage(true); } });
          controls.appendChild(previous);
          const range = node('span', 'fps-note', (page * pageSize + 1) + '–' + Math.min((page + 1) * pageSize, model.totalParcels) + ' of ' + model.totalParcels);
          range.setAttribute('role', 'status'); controls.appendChild(range); controls.appendChild(next); body.appendChild(controls);
          if (moveFocus) (previous.disabled ? next : previous).focus();
        }
      }
      details.addEventListener('toggle', () => { if (details.open && !built) { built = true; drawPage(); } });
      find('land-all').appendChild(details);
    }

    if (model.cropRows.length) model.cropRows.forEach(row => bar(find('crop-chart'), row.label,
      row.count + (row.count === 1 ? ' parcel' : ' parcels'), row.widthPercent, row.color, false));
    else find('crop-chart')?.appendChild(node('p', 'fps-empty', model.cropMessage || 'No crop has been recorded for these parcels in this season.'));

    const coverage = model.coverage;
    const recordedEnd = coverage.recorded / coverage.total * 100;
    const notRecordedEnd = (coverage.recorded + coverage.notRecorded) / coverage.total * 100;
    const ring = node('div', 'fps-coverage-ring');
    ring.setAttribute('aria-hidden', 'true');
    ring.style.background = 'conic-gradient(' + colors.recorded + ' 0% ' + recordedEnd + '%, ' + colors.notRecorded + ' ' + recordedEnd + '% ' + notRecordedEnd + '%, ' + colors.unknown + ' ' + notRecordedEnd + '% 100%)';
    ring.appendChild(node('strong', 'fps-coverage-value', coverage.percentage === null ? '—' : coverage.percentage + '%'));
    const body = node('div', 'fps-coverage-body');
    body.appendChild(node('p', 'fps-coverage-caption', coverage.recorded + ' of ' + coverage.total + ' parcels have a saved crop record.'));
    const legend = node('ul', 'fps-legend');
    [['recorded', 'With saved crop'], ['notRecorded', 'No crop recorded'], ['unknown', 'Not available']].forEach(([key, label]) => {
      const item = node('li', 'fps-legend-item');
      const dot = node('span', 'fps-legend-dot'); dot.style.backgroundColor = colors[key]; dot.setAttribute('aria-hidden', 'true');
      item.appendChild(dot); item.appendChild(node('span', 'fps-legend-text', label)); item.appendChild(node('strong', 'fps-legend-value', coverage[key]));
      legend.appendChild(item);
    });
    body.appendChild(legend);
    find('coverage')?.appendChild(ring); find('coverage')?.appendChild(body);
    return model;
  }
  function clear(element) { return render(element, { landStatus: 'empty' }); }
  return { summarize, render, clear };
});
