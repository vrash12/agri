const { test } = require('node:test');
const assert = require('node:assert/strict');
const { pageMetric, mount } = require('../../public/js/dashboard-chart-pages.js');

function municipalityMetric(count = 305) {
  return {
    title: 'Municipality comparison',
    labels: Array.from({ length: count }, (_, index) => `Municipality ${String(index + 1).padStart(3, '0')}`),
    series: [
      { name: 'Registered farmers', unit: 'farmers', decimals: 0, values: Array.from({ length: count }, (_, index) => index) },
      { name: 'Production', unit: 'kg', decimals: 3, values: Array.from({ length: count }, (_, index) => index + 0.125) },
    ],
    not_recorded: { label: 'Records with missing information', count: 2 },
  };
}

test('hundreds of municipalities begin with eight aligned rows and report the complete count', () => {
  const source = municipalityMetric();
  const result = pageMetric(source);

  assert.equal(result.page, 0);
  assert.equal(result.pageCount, 39);
  assert.equal(result.total, 305);
  assert.equal(result.start, 1);
  assert.equal(result.end, 8);
  assert.deepEqual(result.metric.labels, source.labels.slice(0, 8));
  assert.deepEqual(result.metric.series[0].values, source.series[0].values.slice(0, 8));
  assert.deepEqual(result.metric.series[1].values, source.series[1].values.slice(0, 8));
});

test('the final municipality remains reachable and pages beyond the available range clamp safely', () => {
  const source = municipalityMetric();
  const last = pageMetric(source, { page: 38 });

  assert.equal(last.page, 38);
  assert.equal(last.pageCount, 39);
  assert.equal(last.start, 305);
  assert.equal(last.end, 305);
  assert.deepEqual(last.metric.labels, ['Municipality 305']);
  assert.deepEqual(last.metric.series.map(series => series.values), [[304], [304.125]]);
  assert.deepEqual(pageMetric(source, { page: 500 }), last);
  assert.deepEqual(pageMetric(source, { page: -10 }), pageMetric(source));
});

test('search ignores case, surrounding whitespace and accents while retaining original labels', () => {
  const source = municipalityMetric(4);
  source.labels = ['Alfonso Castañeda', 'San José', 'San Jose del Monte', 'Anao'];

  const accented = pageMetric(source, { query: '  CASTANEDA  ', page: 0 });
  assert.deepEqual(accented.metric.labels, ['Alfonso Castañeda']);
  assert.deepEqual(accented.metric.series[0].values, [0]);
  assert.equal(accented.total, 1);

  const matching = pageMetric(source, { query: 'sAN JOsÉ', page: 0 });
  assert.deepEqual(matching.metric.labels, ['San José', 'San Jose del Monte']);
  assert.deepEqual(matching.metric.series[1].values, [1.125, 2.125]);
  assert.equal(matching.total, 2);
});

test('a caller can reset to the first matching page when searching and restore all rows when clearing', () => {
  const source = municipalityMetric();
  const selected = pageMetric(source, { query: 'Municipality 30', page: 0 });

  assert.equal(selected.page, 0);
  assert.equal(selected.pageCount, 1);
  assert.equal(selected.total, 6);
  assert.equal(selected.start, 1);
  assert.equal(selected.end, 6);
  assert.deepEqual(selected.metric.labels, source.labels.slice(299, 305));
  assert.deepEqual(pageMetric(source, { query: '', page: 0 }), pageMetric(source));
});

test('no matching municipalities return empty aligned series and an honest zero range', () => {
  const result = pageMetric(municipalityMetric(), { query: 'No matching municipality', page: 20 });

  assert.equal(result.page, 0);
  assert.equal(result.pageCount, 1);
  assert.equal(result.total, 0);
  assert.equal(result.start, 0);
  assert.equal(result.end, 0);
  assert.deepEqual(result.metric.labels, []);
  assert.deepEqual(result.metric.series.map(series => series.values), [[], []]);
});

test('empty source metrics remain empty without inventing chart rows', () => {
  const result = pageMetric(municipalityMetric(0));

  assert.equal(result.page, 0);
  assert.equal(result.pageCount, 1);
  assert.equal(result.total, 0);
  assert.equal(result.start, 0);
  assert.equal(result.end, 0);
  assert.deepEqual(result.metric.labels, []);
});

test('repeated municipality labels preserve their own source indices across pages and series', () => {
  const source = municipalityMetric(4);
  source.labels = ['San Jose', 'Anao', 'San Jose', 'San Jose'];

  const first = pageMetric(source, { query: 'san jose', pageSize: 2 });
  const last = pageMetric(source, { query: 'san jose', pageSize: 2, page: 1 });

  assert.deepEqual(first.metric.labels, ['San Jose', 'San Jose']);
  assert.deepEqual(first.metric.series.map(series => series.values), [[0, 2], [0.125, 2.125]]);
  assert.deepEqual(last.metric.labels, ['San Jose']);
  assert.deepEqual(last.metric.series.map(series => series.values), [[3], [3.125]]);
  assert.equal(last.start, 3);
  assert.equal(last.end, 3);
  assert.equal(last.total, 3);
});

test('zero and missing records stay distinct in every series after filtering and paging', () => {
  const source = municipalityMetric(4);
  source.series[0].values = [20, 0, null, 30];
  source.series[1].values = [0, null, 0, 1.234];
  const second = pageMetric(source, { page: 1, pageSize: 2 });

  assert.deepEqual(second.metric.series[0].values, [null, 30]);
  assert.deepEqual(second.metric.series[1].values, [0, 1.234]);
  assert.equal(second.metric.series[1].unit, 'kg');
  assert.equal(second.metric.series[1].decimals, 3);
  const filtered = pageMetric(source, { query: '002' });
  assert.deepEqual(filtered.metric.series.map(series => series.values), [[0], [null]]);
});

test('pagination copies chart arrays without modifying the shared metric or dropping report metadata', () => {
  const source = municipalityMetric();
  const before = structuredClone(source);
  source.series.forEach(series => {
    Object.freeze(series.values);
    Object.freeze(series);
  });
  Object.freeze(source.series);
  Object.freeze(source.labels);
  Object.freeze(source.not_recorded);
  Object.freeze(source);

  const result = pageMetric(source, { query: 'Municipality 0', page: 1 });
  assert.deepEqual(source, before);
  assert.notEqual(result.metric, source);
  assert.notEqual(result.metric.labels, source.labels);
  assert.notEqual(result.metric.series, source.series);
  result.metric.series.forEach((series, index) => {
    assert.notEqual(series, source.series[index]);
    assert.notEqual(series.values, source.series[index].values);
  });
  assert.equal(result.metric.title, source.title);
  assert.deepEqual(result.metric.not_recorded, source.not_recorded);

  result.metric.labels[0] = 'Changed label';
  result.metric.series[0].values[0] = 9999;
  assert.deepEqual(source, before);
});

function mountHarness(metric) {
  const nodes = {};
  for (const name of ['controls', 'search', 'previous', 'next', 'range', 'canvas']) {
    const handlers = {};
    nodes[name] = {
      hidden: true,
      disabled: false,
      value: '',
      textContent: '',
      addEventListener: (event, handler) => { handlers[event] = handler; },
      dispatch: event => { if (event !== 'click' || !nodes[name].disabled) handlers[event]?.(); },
    };
  }
  const root = { querySelector: selector => nodes[selector.match(/^\[data-chart-(.+)\]$/)[1]] };
  const renders = [];
  const refresh = mount(root, metric, visible => { renders.push(visible); });
  return {
    nodes, renders, refresh,
    latest: () => renders.at(-1),
    search: value => { nodes.search.value = value; nodes.search.dispatch('input'); },
  };
}

test('mounted pagination changes the chart data, range and navigation buttons together', () => {
  const source = municipalityMetric(17);
  const h = mountHarness(source);

  assert.equal(h.nodes.controls.hidden, false);
  assert.equal(h.nodes.canvas.hidden, false);
  assert.equal(h.nodes.previous.disabled, true);
  assert.equal(h.nodes.next.disabled, false);
  assert.equal(h.nodes.range.textContent, '1–8 of 17 municipalities');
  assert.deepEqual(h.latest().labels, source.labels.slice(0, 8));

  h.nodes.next.dispatch('click');
  assert.equal(h.nodes.previous.disabled, false);
  assert.equal(h.nodes.next.disabled, false);
  assert.equal(h.nodes.range.textContent, '9–16 of 17 municipalities');
  assert.deepEqual(h.latest().series[0].values, source.series[0].values.slice(8, 16));

  h.nodes.next.dispatch('click');
  assert.equal(h.nodes.next.disabled, true);
  assert.equal(h.nodes.range.textContent, '17–17 of 17 municipalities');
  assert.deepEqual(h.latest().labels, ['Municipality 017']);
  h.nodes.previous.dispatch('click');
  assert.equal(h.nodes.range.textContent, '9–16 of 17 municipalities');
  assert.deepEqual(h.latest().labels, source.labels.slice(8, 16));
});

test('typing a search resets the mounted page and clearing restores the first complete page', () => {
  const source = municipalityMetric(305);
  const h = mountHarness(source);
  h.nodes.next.dispatch('click');
  h.nodes.next.dispatch('click');
  h.search('Municipality 30');

  assert.equal(h.nodes.range.textContent, '1–6 of 6 municipalities matching your search');
  assert.equal(h.nodes.previous.disabled, true);
  assert.equal(h.nodes.next.disabled, true);
  assert.equal(h.nodes.controls.hidden, false);
  assert.deepEqual(h.latest().labels, source.labels.slice(299));

  h.search('');
  assert.equal(h.nodes.range.textContent, '1–8 of 305 municipalities');
  assert.equal(h.nodes.previous.disabled, true);
  assert.equal(h.nodes.next.disabled, false);
  assert.deepEqual(h.latest().labels, source.labels.slice(0, 8));
});

test('no search results hide the canvas and disable pagination until the search is cleared', () => {
  const source = municipalityMetric(17);
  const h = mountHarness(source);
  h.nodes.next.dispatch('click');
  h.search('Absent municipality');

  assert.equal(h.nodes.canvas.hidden, true);
  assert.equal(h.nodes.previous.disabled, true);
  assert.equal(h.nodes.next.disabled, true);
  assert.equal(h.nodes.controls.hidden, false);
  assert.equal(h.nodes.range.textContent, 'No municipalities match. Clear the search to see all municipalities.');
  assert.deepEqual(h.latest().labels, []);
  assert.deepEqual(h.latest().series.map(series => series.values), [[], []]);

  h.search('');
  assert.equal(h.nodes.canvas.hidden, false);
  assert.equal(h.nodes.range.textContent, '1–8 of 17 municipalities');
  assert.equal(h.nodes.next.disabled, false);
  assert.deepEqual(h.latest().labels, source.labels.slice(0, 8));
});

test('changing the indicator through refresh keeps the current search and page with the new series', () => {
  const source = municipalityMetric(305);
  const h = mountHarness({ ...source, series: [source.series[0]] });
  h.search('Municipality 0');
  h.nodes.next.dispatch('click');
  h.nodes.next.dispatch('click');

  h.refresh({ ...source, series: [source.series[1]] });
  assert.equal(h.nodes.search.value, 'Municipality 0');
  assert.equal(h.nodes.range.textContent, '17–24 of 99 municipalities matching your search');
  assert.deepEqual(h.latest().labels, source.labels.slice(16, 24));
  assert.equal(h.latest().series.length, 1);
  assert.equal(h.latest().series[0].name, 'Production');
  assert.equal(h.latest().series[0].unit, 'kg');
  assert.deepEqual(h.latest().series[0].values, source.series[1].values.slice(16, 24));
});

test('refresh clamps the current page when the available municipality set becomes smaller', () => {
  const source = municipalityMetric(25);
  const h = mountHarness(source);
  h.nodes.next.dispatch('click');
  h.nodes.next.dispatch('click');
  h.nodes.next.dispatch('click');

  const narrowed = municipalityMetric(10);
  h.refresh(narrowed);
  assert.equal(h.nodes.range.textContent, '9–10 of 10 municipalities');
  assert.equal(h.nodes.previous.disabled, false);
  assert.equal(h.nodes.next.disabled, true);
  assert.deepEqual(h.latest().labels, narrowed.labels.slice(8));
});

test('small municipality lists do not display unused search and paging controls', () => {
  for (const count of [1, 8]) {
    const source = municipalityMetric(count);
    const h = mountHarness(source);
    assert.equal(h.nodes.controls.hidden, true);
    assert.equal(h.nodes.previous.disabled, true);
    assert.equal(h.nodes.next.disabled, true);
    assert.equal(h.nodes.canvas.hidden, false);
    assert.deepEqual(h.latest().labels, source.labels);
  }
});
