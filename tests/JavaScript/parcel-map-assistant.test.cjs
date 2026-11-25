const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const assistant = require('../../public/js/parcel-map-assistant.js');

const reply = (body, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

test('batches de-duplicate ids, drop non-numeric values and never exceed 200', () => {
  const ids = Array.from({ length: 450 }, (_, i) => i + 1).concat(['1', 'x', null]);
  const groups = assistant.batches(ids);
  assert.deepEqual(groups.map(group => group.length), [200, 200, 50]);
  assert.ok(groups.flat().every(id => /^\d+$/.test(id)));
  assert.deepEqual(assistant.batches([], 200), []);
});

test('highlight styles separate question matches, priority levels and only-matches visibility', () => {
  const h = assistant.createHighlights();
  assert.equal(h.style(1), null);
  assert.equal(h.visible(1), true);
  h.state.source = 'priority'; h.state.status = 'ready';
  h.state.entries = new Map([['1', { level: 'high', reasons: [] }], ['2', { level: 'check', reasons: [] }]]);
  assert.equal(h.style(1).color, assistant.COLORS.high);
  assert.equal(h.style('2').color, assistant.COLORS.check);
  assert.equal(h.styleKey(3), '');
  h.state.onlyMatches = true;
  assert.equal(h.visible(3), false);
  assert.equal(h.visible(1), true);
  h.reset();
  assert.equal(h.visible(3), true);
  assert.equal(h.state.status, 'off');
});

test('reason chips escape text and ignore unknown reason keys', () => {
  const html = assistant.chipsHtml({ reasons: [{ key: 'visit_overdue', date: '2026-10-01' }, { key: '<script>', date: '<b>' }, { key: 'no_harvest', date: 'bad' }] });
  assert.match(html, /Planned visit overdue · 2026-10-01/);
  assert.match(html, /No harvest recorded<\/span>/);
  assert.doesNotMatch(html, /<script>|<b>/);
  assert.equal(assistant.chipsHtml(null), '');
});

test('a no-harvest answer applies the crop view and loads matches only for loaded parcels in 200-id batches', async () => {
  const calls = [];
  const ids = Array.from({ length: 401 }, (_, i) => i + 1);
  const fetch = async (url, init) => {
    calls.push({ url, init });
    if (init && init.method === 'POST') {
      return reply({ status: 'answered', answer: '3 corn parcels…', action: { type: 'highlight',
        match: { filter: 'no_harvest', crop: 'corn', year: 2026, season: 'wet', location: null }, crop_view: { year: 2026, season: 'wet', crop: 'corn' } } });
    }
    const query = new URL(url, 'http://local').searchParams;
    const batch = query.getAll('plot_ids[]');
    assert.ok(batch.length <= 200);
    assert.equal(query.get('filter'), 'no_harvest');
    assert.equal(query.has('location'), false, 'Empty filter values are not sent');
    return reply({ matches: batch.filter(id => Number(id) % 100 === 0).map(Number), checked: batch.length });
  };
  const views = [];
  let changes = 0;
  const controller = assistant.create({ fetch, askUrl: '/ask', matchUrl: '/matches?region_id=1', priorityUrl: '/priority', csrf: 'token' });
  const highlights = controller.connect({ getPlotIds: () => ids, getPeriod: () => ({ year: 2025, season: 'dry' }),
    applyCropView: view => views.push(view), onChange: () => changes++ });
  await controller.ask('corn parcels with no harvest this wet season');
  const post = calls[0];
  assert.equal(post.init.headers['X-CSRF-TOKEN'], 'token');
  assert.deepEqual(JSON.parse(post.init.body), { question: 'corn parcels with no harvest this wet season', year: 2025, season: 'dry' });
  assert.deepEqual(views, [{ year: 2026, season: 'wet', crop: 'corn' }]);
  assert.equal(calls.length, 4, 'One question plus three bounded batches');
  assert.ok(calls[1].url.startsWith('/matches?region_id=1&'), 'Workspace filters in the URL are preserved');
  assert.equal(highlights.state.status, 'ready');
  assert.equal(highlights.state.checked, 401);
  assert.deepEqual([...highlights.state.entries.keys()], ['100', '200', '300', '400']);
  assert.match(assistant.summary(highlights.state), /4 of 401 loaded parcels match/);
  assert.ok(changes > 1);
});

test('clarifications and failures never leave a stale highlight and errors keep the map unchanged', async () => {
  let mode = 'priority';
  const fetch = async (url, init) => {
    if (init && init.method === 'POST') {
      return mode === 'clarify'
        ? reply({ status: 'clarify', answer: 'Ask about one crop at a time.', examples: ['parcels without a boundary'], action: { type: 'none' } })
        : mode === 'throttled' ? reply({ message: 'Map questions are temporarily limited.' }, 429) : reply({}, 419);
    }
    return reply({ checked: 2, flagged: [{ plot_id: 1, level: 'high', reasons: [{ key: 'visit_overdue', date: '2026-10-01' }] }],
      counts: { visit_overdue: 1, satellite_decline: 0, no_harvest: 0 } });
  };
  const controller = assistant.create({ fetch, askUrl: '/ask', matchUrl: '/m', priorityUrl: '/p' });
  const highlights = controller.connect({ getPlotIds: () => [1, 2], getPeriod: () => ({ year: 2026, season: 'wet' }) });
  await controller.setPriority(true);
  assert.equal(highlights.state.source, 'priority');
  assert.equal(highlights.entry(1).reasons[0].key, 'visit_overdue');
  assert.match(assistant.summary(highlights.state), /1 of 2 loaded parcels flagged for a visit · 2026 Wet season/);
  mode = 'clarify';
  await controller.ask('rice and corn parcels');
  assert.equal(controller.answer.status, 'clarify');
  assert.equal(highlights.state.source, 'priority', 'A clarification does not remove the priority layer');
  mode = 'throttled';
  await controller.ask('rice parcels');
  assert.match(controller.answer.error, /temporarily limited/);
  mode = 'session';
  await controller.ask('rice parcels');
  assert.match(controller.answer.error, /session ended/);
  await controller.setPriority(false);
  assert.equal(highlights.state.status, 'off');
});

test('a failed batch shows a retryable error instead of a partial highlight', async () => {
  let fail = true;
  const fetch = async () => fail ? reply({}, 500) : reply({ matches: [5], checked: 1 });
  const controller = assistant.create({ fetch, askUrl: '/ask', matchUrl: '/m', priorityUrl: '/p' });
  const highlights = controller.connect({ getPlotIds: () => [5] });
  await controller.setPriority(true, { year: 2026, season: 'dry' });
  assert.equal(highlights.state.status, 'error');
  assert.equal(highlights.state.entries.size, 0);
  assert.match(highlights.state.error, /unchanged/);
  fail = false;
  await controller.retry();
  assert.equal(highlights.state.status, 'ready');
});

test('the map renderer applies highlights to strokes only and the plotting script stays Blade-free', () => {
  const source = fs.readFileSync(path.join(__dirname, '../../public/js/farmers-maps.js'), 'utf8');
  const own = fs.readFileSync(path.join(__dirname, '../../public/js/parcel-map-assistant.js'), 'utf8');
  for (const text of [source, own]) assert.doesNotMatch(text, /\{\{|@json|@php/);
  assert.match(source, /parcelHighlights\.visible\(overlay\.plotId\)/);
  assert.match(source, /mark \? mark\.color : overlay\.style\.strokeStrong/);
  assert.doesNotMatch(own, /new\s+google\.|Marker3DElement|Polygon3D/, 'Highlights reuse existing overlays');
});
