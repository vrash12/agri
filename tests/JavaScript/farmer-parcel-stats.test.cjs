const test = require('node:test');
const assert = require('node:assert/strict');
const { summarize, render, clear } = require('../../public/js/farmer-parcel-stats.js');
const badges = require('../../public/js/crop-area-badges.js');

function input(changes = {}) {
  return {
    landStatus: 'ready', cropStatus: 'ready', year: 2026, season: 'dry',
    plots: [{ id: 1, name: 'North parcel', area_ha: 6.62 }, { id: 2, name: 'East parcel', area_ha: 1.04 }, { id: 3, name: 'South parcel', area_ha: 0.91 }],
    records: [{ plot_id: 1, crop: 'mixed', area_crops: ['rice', 'corn', 'rice'] }, { plot_id: 2, crop: 'corn' }, { plot_id: 3, crop: 'not_recorded' }],
    selectedPlotId: 1, ...changes
  };
}

test('land totals and crop counts use only the selected farmer’s unique parcels', () => {
  const data = input();
  data.plots.push({ id: '1', name: 'Duplicate', area_ha: 100 });
  data.records.push({ plot_id: 99, crop: 'rice' });
  const result = summarize(data);
  assert.equal(result.totalParcels, 3);
  assert.ok(Math.abs(result.totalAreaHa - 8.57) < 1e-9);
  assert.equal(result.totalAreaLabel, '8.57 ha');
  assert.deepEqual(result.cropRows.map(row => [row.code, row.count]), [['corn', 2], ['rice', 1]]);
  assert.deepEqual(result.coverage, { recorded: 2, notRecorded: 1, unknown: 0, total: 3, complete: true, percentage: 67 });
  assert.equal(result.landRows[0].selected, true);
  assert.match(result.cropNote, /not planted hectares/);
});

test('one multi-crop parcel is counted once per known crop, matching map badge semantics', () => {
  const records = [
    { plot_id: 1, crop: 'mixed', area_crops: ['rice', 'corn', 'rice', 'mystery'] },
    { plot_id: 2, crop: 'mixed', area_crops: [] },
    { plot_id: 3, crop: 'not_recorded', area_crops: ['rice'] }
  ];
  const result = summarize(input({ records }));
  const counts = new Map();
  records.forEach(record => badges.parcelCrops(record).forEach(code => counts.set(code, (counts.get(code) || 0) + 1)));
  assert.deepEqual(Object.fromEntries(result.cropRows.map(row => [row.code, row.count])), Object.fromEntries([...counts].sort((a, b) => badges.descriptor(a[0])[0].localeCompare(badges.descriptor(b[0])[0]))));
  const only = summarize(input({ plots: [{ id: 1, area_ha: 2 }], records: [records[0]] }));
  assert.equal(only.cropRows.reduce((sum, row) => sum + row.count, 0), 2);
  assert.equal(only.totalParcels, 1);
  assert.equal(only.coverage.recorded, 1);
  assert.ok(only.cropRows.every(row => row.widthPercent === 100));
});

test('missing, unsupported and ambiguous crop records remain unknown rather than no crop', () => {
  const result = summarize(input({ records: [{ plot_id: 1, crop: 'corn' }, { plot_id: 2, crop: 'future_crop', area_crops: ['rice'] }] }));
  assert.equal(result.coverage.recorded, 1);
  assert.equal(result.coverage.notRecorded, 0);
  assert.equal(result.coverage.unknown, 2);
  assert.equal(result.coverage.percentage, null);
  assert.equal(result.cropRows[0].code, 'corn');
  assert.match(result.statusText, /confirmed records only/);
  const duplicate = summarize(input({ records: [{ plot_id: 1, crop: 'rice' }, { plot_id: 1, crop: 'corn' }, { plot_id: 1, crop: 'rice' }] }));
  assert.equal(duplicate.coverage.unknown, 3);
  assert.equal(duplicate.cropRows.length, 0);
});

test('explicitly mismatched period metadata cannot populate the selected season', () => {
  const result = summarize(input({ records: [
    { plot_id: 1, crop: 'rice', year: 2025, season: 'dry' },
    { plot_id: 2, crop: 'corn', year: 2026, season: 'wet' },
    { plot_id: 3, crop: 'not_recorded', year: 2026, season: 'dry' }
  ] }));
  assert.deepEqual(result.coverage, { recorded: 0, notRecorded: 1, unknown: 2, total: 3, complete: false, percentage: null });
  assert.equal(result.periodLabel, '2026 · Dry season');
});

test('zero, missing and invalid areas stay unmeasured while small positive parcels retain useful precision', () => {
  const values = [null, undefined, '', ' ', 0, -4, Infinity, NaN, true, '0.001', 0.00001, '1.23'];
  const result = summarize(input({ plots: values.map((area_ha, index) => ({ id: index + 1, area_ha })), records: [] }));
  assert.equal(result.totalParcels, 12);
  assert.equal(result.knownAreaParcels, 3);
  assert.equal(result.unknownAreaParcels, 9);
  assert.ok(Math.abs(result.totalAreaHa - 1.23101) < 1e-9);
  assert.equal(result.landRows.find(row => row.id === '10').areaLabel, '0.001 ha');
  assert.equal(result.landRows.find(row => row.id === '11').areaLabel, '< 0.001 ha');
  assert.ok(result.landRows.filter(row => row.areaHa === null).every(row => row.areaLabel === 'Not recorded' && row.widthPercent === 0));
  assert.match(result.areaNote, /9 parcels have no usable area/);
  const unknown = summarize(input({ plots: [{ id: 1, area_ha: 0 }], records: [] }));
  assert.equal(unknown.totalAreaHa, null);
  assert.equal(unknown.totalAreaLabel, 'Not recorded');
});

test('a loading or failed farmer selection clears every previous parcel and crop', () => {
  for (const landStatus of ['loading', 'error', 'empty']) {
    const result = summarize(input({ landStatus }));
    assert.equal(result.ready, false);
    assert.equal(result.totalParcels, null);
    assert.equal(result.totalAreaHa, null);
    assert.deepEqual(result.landRows, []);
    assert.deepEqual(result.cropRows, []);
    assert.equal(result.coverage, null);
  }
  const empty = summarize(input({ plots: [] }));
  assert.equal(empty.totalParcels, 0);
  assert.equal(empty.ready, false);
  assert.match(empty.statusText, /no saved parcels/);
});

test('crop loading, errors and disabled display keep land values but never stale crops or zero coverage', () => {
  for (const cropStatus of ['loading', 'error', 'off']) {
    const result = summarize(input({ cropStatus }));
    assert.equal(result.ready, true);
    assert.equal(result.totalParcels, 3);
    assert.equal(result.coverage.unknown, 3);
    assert.equal(result.coverage.notRecorded, 0);
    assert.equal(result.coverage.percentage, null);
    assert.deepEqual(result.cropRows, []);
    assert.ok(result.statusText);
  }
});

test('explicit no-crop results can show zero coverage without implying bare or unplanted land', () => {
  const result = summarize(input({ records: [1, 2, 3].map(plot_id => ({ plot_id, crop: 'not_recorded' })) }));
  assert.equal(result.coverage.percentage, 0);
  assert.equal(result.coverage.notRecorded, 3);
  assert.equal(result.coverage.unknown, 0);
  assert.equal(result.statusText, '');
  assert.equal(result.cropRows.length, 0);
});

test('summaries do not mutate source records or depend on a map, network or browser', () => {
  const data = input();
  data.plots.forEach(Object.freeze); data.records.forEach(row => { if (row.area_crops) Object.freeze(row.area_crops); Object.freeze(row); });
  Object.freeze(data.plots); Object.freeze(data.records); Object.freeze(data);
  assert.equal(summarize(data).ready, true);
  assert.equal(summarize().ready, false);
  assert.equal(summarize({ landStatus: 'ready', plots: [null, {}, { id: '', area_ha: 5 }, { id: 1, area_ha: 2 }] }).totalParcels, 1);
  assert.equal(render(null, data).totalParcels, 3);
});

class Element {
  constructor(tag, document) { this.tagName = tag.toUpperCase(); this.ownerDocument = document; this.children = []; this.attributes = {}; this.style = {}; this.events = {}; this.hidden = false; this.disabled = false; this.className = ''; this.id = ''; this._text = ''; }
  set textContent(value) { this._text = String(value); this.children = []; }
  get textContent() { return this._text + this.children.map(child => child.textContent).join(''); }
  set innerHTML(value) { throw new Error('Unsafe HTML rendering: ' + value); }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  getAttribute(name) { return this.attributes[name] ?? null; }
  appendChild(child) { this.children.push(child); return child; }
  replaceChildren(...children) { this._text = ''; this.children = children; }
  addEventListener(name, callback) { this.events[name] = callback; }
  dispatch(name) { this.events[name]?.({ target: this }); }
  focus() { if (!this.disabled) this.ownerDocument.activeElement = this; }
  matches(selector) {
    if (selector.startsWith('[')) return Object.hasOwn(this.attributes, selector.slice(1, -1));
    if (selector.startsWith('.')) return this.className.split(' ').includes(selector.slice(1));
    if (selector.startsWith('#')) return this.id === selector.slice(1);
    return this.tagName.toLowerCase() === selector;
  }
  querySelectorAll(selector) { return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]); }
  querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
}
function fixture() {
  const document = { createElement: tag => new Element(tag, document), activeElement: null };
  const root = document.createElement('section');
  const elements = {};
  for (const name of ['period', 'status', 'retry', 'content', 'metrics', 'land-chart', 'land-all', 'crop-chart', 'coverage', 'area-note', 'crop-note']) {
    const element = document.createElement(name === 'retry' ? 'button' : 'div');
    element.setAttribute('data-parcel-stats-' + name, '');
    elements[name] = element;
    (['metrics', 'land-chart', 'land-all', 'crop-chart', 'coverage', 'area-note', 'crop-note'].includes(name) ? elements.content : root).appendChild(element);
  }
  return { root, elements, document };
}

test('renderer supplies visible chart values, selected-parcel feedback and text alternatives for the ring', () => {
  const { root, elements } = fixture();
  render(root, input());
  assert.equal(root.querySelector('#plotCountPill').textContent, '3');
  assert.equal(root.querySelector('#plotAreaTotalPill').textContent, '8.57 ha');
  assert.equal(elements.content.hidden, false);
  assert.equal(elements.status.hidden, true);
  assert.equal(elements['land-chart'].querySelector('.is-selected').querySelector('.fps-bar-name').textContent, 'North parcel · Selected');
  assert.equal(elements['land-chart'].querySelector('.fps-bar-fill').style.width, '100%');
  assert.match(elements['crop-chart'].textContent, /Corn2 parcels/);
  assert.equal(elements.coverage.querySelector('.fps-coverage-ring').getAttribute('aria-hidden'), 'true');
  assert.match(elements.coverage.textContent, /With saved crop2No crop recorded1Not available0/);
  assert.equal(elements.coverage.querySelector('.fps-coverage-value').textContent, '67%');
});

test('render transitions remove stale charts, zero substitutes, and old farmer names', () => {
  const { root, elements } = fixture();
  render(root, input());
  render(root, input({ cropStatus: 'loading' }));
  assert.equal(elements.coverage.querySelector('.fps-coverage-value').textContent, '—');
  assert.equal(elements.metrics.querySelectorAll('.fps-metric-value')[2].textContent, '—');
  assert.doesNotMatch(elements['crop-chart'].textContent, /Corn/);
  render(root, input({ landStatus: 'loading' }));
  assert.equal(elements.content.hidden, true);
  assert.doesNotMatch(root.textContent, /North parcel|8.57|Corn/);
  assert.equal(elements['land-chart'].children.length, 0);
  render(root, input({ landStatus: 'error' }));
  assert.equal(elements.retry.hidden, false);
  clear(root);
  assert.equal(elements.retry.hidden, true);
  assert.match(elements.status.textContent, /Select a farmer/);
});

test('names and unsupported crop text remain inert text, and missing crop records show uncertainty', () => {
  const { root, elements } = fixture();
  const unsafe = '<img src=x onerror=alert(1)>';
  render(root, input({ plots: [{ id: 1, name: unsafe, area_ha: 1 }, { id: 2, name: 'Other', area_ha: null }], records: [{ plot_id: 1, crop: 'rice' }] }));
  assert.match(elements['land-chart'].textContent, /<img src=x onerror=alert\(1\)>/);
  assert.equal(root.querySelectorAll('img').length, 0);
  assert.equal(elements.metrics.querySelectorAll('.fps-metric-value')[2].textContent, '≥ 1');
  assert.equal(elements.coverage.querySelector('.fps-coverage-value').textContent, '—');
  assert.match(elements.coverage.textContent, /Not available1/);
  render(root, input({ records: [{ plot_id: 1, crop: '__proto__' }, { plot_id: 2, crop: unsafe }] }));
  assert.equal(elements.metrics.querySelectorAll('.fps-metric-value')[2].textContent, '—');
  assert.doesNotMatch(elements['crop-chart'].textContent, /<img|__proto__/);
});

test('all parcels remain accessible while chart rows and expanded table pages stay bounded', () => {
  const { root, elements, document } = fixture();
  const plots = Array.from({ length: 121 }, (_, i) => ({ id: i + 1, name: 'Parcel ' + (i + 1), area_ha: 121 - i }));
  render(root, input({ plots, records: [], selectedPlotId: 121 }));
  assert.equal(elements['land-chart'].querySelectorAll('.fps-bar-row').length, 6);
  const details = elements['land-all'].querySelector('details');
  assert.equal(details.querySelectorAll('table').length, 0, 'Closed details do not create hundreds of hidden rows');
  details.open = true; details.dispatch('toggle');
  assert.equal(details.querySelector('tbody').children.length, 50);
  assert.equal(details.querySelector('th').getAttribute('scope'), 'col');
  assert.match(details.querySelector('.fps-pagination').textContent, /1–50 of 121/);
  details.querySelectorAll('button')[1].dispatch('click');
  assert.equal(details.querySelector('tbody').children.length, 50);
  assert.match(details.querySelector('.fps-pagination').textContent, /51–100 of 121/);
  assert.equal(document.activeElement.disabled, false);
  details.querySelectorAll('button')[1].dispatch('click');
  assert.equal(details.querySelector('tbody').children.length, 21);
  assert.match(details.querySelector('tbody').textContent, /Parcel 121 · Selected/);
  assert.equal(details.querySelectorAll('button')[1].disabled, true);
  details.querySelectorAll('button')[0].dispatch('click');
  details.querySelectorAll('button')[0].dispatch('click');
  assert.equal(document.activeElement.textContent, 'Next parcels');
});

test('optional missing view hooks are safe and crop errors offer the existing retry action', () => {
  const { root, elements } = fixture();
  render(root, input({ cropStatus: 'error' }));
  assert.equal(elements.retry.hidden, false);
  assert.equal(elements.content.hidden, false);
  root.replaceChildren();
  assert.equal(render(root, input()).ready, true);
});

test('no-op layer notifications preserve the expanded table and keyboard focus', () => {
  const { root, elements, document } = fixture();
  const data = input({ plots: Array.from({ length: 60 }, (_, i) => ({ id: i + 1, name: 'Parcel ' + (i + 1), area_ha: i + 1 })), records: [] });
  render(root, data);
  const details = elements['land-all'].querySelector('details');
  details.open = true; details.dispatch('toggle');
  details.querySelectorAll('button')[1].focus();
  const focused = document.activeElement;
  render(root, { ...data, records: [] });
  assert.equal(elements['land-all'].querySelector('details'), details);
  assert.equal(details.open, true);
  assert.equal(document.activeElement, focused);
  render(root, { ...data, selectedPlotId: 60 });
  assert.notEqual(elements['land-all'].querySelector('details'), details);
  assert.match(elements['land-chart'].querySelector('.is-selected').textContent, /Parcel 60/);
});

test('a crop error does not leave retry visible without loaded farmer parcels', () => {
  const { root, elements } = fixture();
  for (const landStatus of ['empty', 'loading']) {
    render(root, input({ landStatus, cropStatus: 'error' }));
    assert.equal(elements.retry.hidden, true);
  }
  render(root, input({ plots: [], cropStatus: 'error' }));
  assert.equal(elements.retry.hidden, true);
});

test('crop-only transitions preserve expanded land pages and keyboard focus while updating crop charts', () => {
  const { root, elements, document } = fixture();
  const plots = Array.from({ length: 121 }, (_, i) => ({ id: i + 1, name: 'Parcel ' + (i + 1), area_ha: 121 - i }));
  const data = input({ plots, records: [], cropStatus: 'loading' });
  render(root, data);
  const details = elements['land-all'].querySelector('details');
  const chart = elements['land-chart'].querySelector('.fps-bar-row');
  details.open = true; details.dispatch('toggle');
  details.querySelectorAll('button')[1].dispatch('click');
  const focused = document.activeElement;
  const table = details.querySelector('table');
  assert.match(details.querySelector('.fps-pagination').textContent, /51–100 of 121/);

  for (const cropStatus of ['ready', 'error', 'loading', 'off', 'ready']) {
    render(root, { ...data, cropStatus, records: plots.map(plot => ({ plot_id: plot.id, crop: 'corn' })) });
    assert.equal(elements['land-all'].querySelector('details'), details, cropStatus);
    assert.equal(elements['land-chart'].querySelector('.fps-bar-row'), chart, cropStatus);
    assert.equal(details.open, true, cropStatus);
    assert.equal(details.querySelector('table'), table, cropStatus);
    assert.match(details.querySelector('.fps-pagination').textContent, /51–100 of 121/, cropStatus);
    assert.equal(document.activeElement, focused, cropStatus);
    assert.equal(elements.retry.hidden, cropStatus !== 'error');
    if (cropStatus === 'ready') assert.match(elements['crop-chart'].textContent, /Corn121 parcels/);
    else assert.equal(elements['crop-chart'].querySelectorAll('.fps-bar-row').length, 0);
  }

  render(root, { ...data, landStatus: 'loading' });
  assert.equal(elements['land-all'].children.length, 0);
  assert.equal(elements['land-chart'].children.length, 0);
  assert.equal(elements.content.hidden, true);
  assert.doesNotMatch(root.textContent, /Parcel 51/);
});
