/*
 * "Ask the map" and the field-visit priority layer for the Farmers parcel map.
 *
 * Plain script, no server values: URLs arrive on window.__parcelMapAssistant
 * from the maps-scripts partial. The server interprets questions with a fixed
 * vocabulary and returns counts plus an allowlisted filter; this file asks for
 * matches only among parcels already loaded on the map, in 200-ID batches, and
 * paints them by changing the stroke of existing overlays (no new GPU objects).
 */
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.ParcelMapAssistant = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';

  var BATCH = 200;
  var COLORS = { question: '#FACC15', high: '#F97316', check: '#FBBF24' };
  var REASONS = {
    visit_overdue: 'Planned visit overdue',
    satellite_decline: 'Greenness/moisture fell',
    no_harvest: 'No harvest recorded'
  };

  function batches(ids, size) {
    size = Math.max(1, Math.floor(Number(size) || BATCH));
    var unique = Array.from(new Set((ids || []).map(String).filter(function (id) { return /^\d+$/.test(id); })));
    var out = [];
    for (var i = 0; i < unique.length; i += size) out.push(unique.slice(i, i + size));
    return out;
  }

  function seasonLabel(season) { return season === 'wet' ? 'Wet season' : 'Dry season'; }

  function errorMessage(status, data) {
    if (status === 401 || status === 419) return 'Your session ended. Sign in again, then retry.';
    if (status === 403) return 'Your account cannot use map questions.';
    if (status === 429) return (data && data.message) || 'Too many map requests. Wait a moment, then retry.';
    if (status === 422) return (data && (data.message || (data.errors && Object.values(data.errors).flat()[0]))) || 'Check the question and try again.';
    return 'The map could not answer right now. Your map and filters are unchanged. Retry in a moment.';
  }

  /** Highlight state shared with the renderer; style lookups are O(1) per parcel. */
  function createHighlights() {
    var state = { source: null, status: 'off', entries: new Map(), onlyMatches: false, checked: 0, counts: {}, error: '', period: null, title: '' };
    function entry(id) { return state.status === 'ready' || state.status === 'loading' ? state.entries.get(String(id)) || null : null; }
    function style(id) {
      var item = entry(id);
      if (!item) return null;
      var color = state.source === 'priority' ? (item.level === 'high' ? COLORS.high : COLORS.check) : COLORS.question;
      return { color: color, width: state.source === 'priority' && item.level === 'high' ? 3 : 2.4 };
    }
    function styleKey(id) { var s = style(id); return s ? s.color + '/' + s.width : ''; }
    function visible(id) { return !state.onlyMatches || state.status !== 'ready' || state.entries.has(String(id)); }
    function reset() {
      state.source = null; state.status = 'off'; state.entries = new Map(); state.checked = 0; state.counts = {};
      state.error = ''; state.period = null; state.title = ''; state.onlyMatches = false;
    }
    return { state: state, entry: entry, style: style, styleKey: styleKey, visible: visible, reset: reset };
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** Reason chips for the hover card and parcel list; reason keys are allowlisted. */
  function chipsHtml(item) {
    if (!item || !Array.isArray(item.reasons) || !item.reasons.length) return '';
    return '<span class="parcel-flag-chips" aria-label="Field-visit reasons">' + item.reasons.map(function (reason) {
      var label = REASONS[reason.key];
      if (!label) return '';
      var date = /^\d{4}-\d{2}-\d{2}$/.test(reason.date || '') ? ' · ' + reason.date : '';
      return '<span class="parcel-flag-chip is-' + reason.key.replace(/_/g, '-') + '">' + escapeHtml(label + date) + '</span>';
    }).join('') + '</span>';
  }

  function questionChip(item) {
    return item && item.question ? '<span class="parcel-flag-chips"><span class="parcel-flag-chip is-question">Matches your map question</span></span>' : '';
  }

  /** Summary text for the legend after a layer finishes. */
  function summary(state) {
    if (state.status === 'loading') return 'Checking loaded parcels…';
    if (state.status === 'error') return state.error;
    if (state.status !== 'ready') return '';
    var n = state.entries.size;
    if (state.source === 'priority') {
      return n + ' of ' + state.checked + ' loaded ' + (state.checked === 1 ? 'parcel' : 'parcels') + ' flagged for a visit · '
        + state.period.year + ' ' + seasonLabel(state.period.season) + '.';
    }
    return n + ' of ' + state.checked + ' loaded ' + (state.checked === 1 ? 'parcel matches' : 'parcels match') + ' your question.';
  }

  function create(options) {
    var highlights = createHighlights();
    var hooks = null;
    var revision = 0, abort = null, lastLayer = null, reloadTimer = null;
    var answer = { status: 'idle', data: null, error: '' };
    function notify() {
      if (hooks && hooks.onChange) hooks.onChange(highlights);
      if (options.onRender) options.onRender({ highlights: highlights, answer: answer, connected: !!hooks });
    }
    function period() {
      var p = hooks && hooks.getPeriod ? hooks.getPeriod() : null;
      return p && p.year ? p : { year: new Date().getFullYear(), season: 'dry' };
    }
    async function json(response) { try { return await response.json(); } catch (e) { return {}; } }

    async function loadLayer(layer) {
      lastLayer = layer;
      var current = ++revision;
      if (abort) abort.abort();
      abort = typeof AbortController !== 'undefined' ? new AbortController() : null;
      var ids = hooks && hooks.getPlotIds ? hooks.getPlotIds() : [];
      highlights.state.source = layer.source; highlights.state.status = 'loading';
      highlights.state.entries = new Map(); highlights.state.checked = 0; highlights.state.counts = {};
      highlights.state.error = ''; highlights.state.period = { year: layer.params.year, season: layer.params.season };
      notify();
      try {
        var entries = new Map(), checked = 0, counts = {};
        var groups = batches(ids, BATCH);
        for (var g = 0; g < groups.length; g++) {
          var query = new URLSearchParams();
          Object.keys(layer.params).forEach(function (key) {
            var value = layer.params[key];
            if (value !== null && value !== undefined && value !== '') query.set(key, String(value));
          });
          groups[g].forEach(function (id) { query.append('plot_ids[]', id); });
          var url = layer.url + (layer.url.indexOf('?') === -1 ? '?' : '&') + query.toString();
          var response = await options.fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: abort ? abort.signal : undefined });
          if (current !== revision) return;
          var data = await json(response);
          if (current !== revision) return;
          if (!response.ok) throw new Error(errorMessage(response.status, data));
          checked += Number(data.checked) || 0;
          if (layer.source === 'priority') {
            (data.flagged || []).forEach(function (item) {
              entries.set(String(item.plot_id), { level: item.level === 'high' ? 'high' : 'check', reasons: item.reasons || [] });
            });
            Object.keys(data.counts || {}).forEach(function (key) { counts[key] = (counts[key] || 0) + Number(data.counts[key] || 0); });
          } else {
            (data.matches || []).forEach(function (id) { entries.set(String(id), { question: true, reasons: [] }); });
          }
          highlights.state.entries = new Map(entries);
          highlights.state.checked = checked;
          if (g < groups.length - 1) notify();
        }
        if (current !== revision) return;
        highlights.state.entries = entries; highlights.state.checked = checked; highlights.state.counts = counts;
        highlights.state.status = 'ready';
        notify();
      } catch (error) {
        if (current !== revision || (error && error.name === 'AbortError')) return;
        highlights.state.status = 'error';
        highlights.state.entries = new Map();
        highlights.state.error = (error && error.message) || 'Highlights could not load. Retry.';
        notify();
      }
    }

    function clear() {
      revision++;
      if (abort) abort.abort();
      lastLayer = null;
      highlights.reset();
      notify();
    }

    function setPriority(on, at) {
      if (!on) { if (highlights.state.source === 'priority') clear(); return Promise.resolve(); }
      var p = at || period();
      return loadLayer({ source: 'priority', url: options.priorityUrl, params: { year: p.year, season: p.season } });
    }

    async function ask(question) {
      question = String(question || '').trim();
      if (!question) { answer = { status: 'error', data: null, error: 'Type a question first, for example “parcels without a boundary”.' }; notify(); return; }
      var p = period();
      answer = { status: 'loading', data: null, error: '' };
      notify();
      var response, data;
      try {
        response = await options.fetch(options.askUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': options.csrf || '', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ question: question, year: p.year, season: p.season })
        });
        data = await json(response);
      } catch (error) {
        answer = { status: 'error', data: null, error: errorMessage(0) }; notify(); return;
      }
      if (!response.ok) { answer = { status: 'error', data: null, error: errorMessage(response.status, data) }; notify(); return; }
      answer = { status: data.status === 'clarify' ? 'clarify' : 'answered', data: data, error: '' };
      var action = data.action || { type: 'none' };
      if (action.type === 'crop_filter' || action.type === 'highlight') {
        var view = action.type === 'crop_filter' ? action : (action.crop_view || {});
        if (hooks && hooks.applyCropView) hooks.applyCropView({ year: view.year, season: view.season, crop: view.crop || null });
      }
      if (action.type === 'highlight' && action.match) {
        notify();
        return loadLayer({ source: 'question', url: options.matchUrl, params: action.match });
      }
      if (action.type === 'priority') { notify(); return setPriority(true, { year: action.year, season: action.season }); }
      if (highlights.state.source === 'question' || action.type === 'crop_filter') clear();
      else notify();
    }

    function plotsChanged() {
      if (!lastLayer) return;
      if (reloadTimer) clearTimeout(reloadTimer);
      reloadTimer = setTimeout(function () { reloadTimer = null; if (lastLayer) loadLayer(lastLayer); }, 400);
    }

    function connect(nextHooks) {
      hooks = nextHooks || null;
      notify();
      return highlights;
    }

    function setOnlyMatches(on) { highlights.state.onlyMatches = !!on; notify(); }
    function retry() { if (lastLayer) return loadLayer(lastLayer); return Promise.resolve(); }

    return { ask: ask, setPriority: setPriority, clear: clear, retry: retry, plotsChanged: plotsChanged, connect: connect,
      setOnlyMatches: setOnlyMatches, highlights: highlights, get answer() { return answer; } };
  }

  /** DOM wiring for the partial in farmers/partials/map-ask.blade.php. */
  function mount(doc, config) {
    var form = doc.getElementById('mapAskForm');
    if (!form || !config || !config.askUrl) return null;
    var input = doc.getElementById('mapAskInput');
    var submit = doc.getElementById('mapAskSubmit');
    var answerEl = doc.getElementById('mapAskAnswer');
    var layerStatus = doc.getElementById('mapHighlightStatus');
    var legend = doc.getElementById('mapHighlightLegend');
    var priority = doc.getElementById('mapPriorityToggle');
    var only = doc.getElementById('mapHighlightOnly');
    var clearBtn = doc.getElementById('mapHighlightClear');
    var retryBtn = doc.getElementById('mapHighlightRetry');
    var controller = create({
      fetch: config.fetch, askUrl: config.askUrl, matchUrl: config.matchUrl, priorityUrl: config.priorityUrl, csrf: config.csrf,
      onRender: function (view) { render(view); }
    });

    function render(view) {
      var a = view.answer, h = view.highlights.state;
      if (submit) { submit.disabled = a.status === 'loading'; submit.textContent = a.status === 'loading' ? 'Checking…' : 'Ask'; }
      if (answerEl) {
        answerEl.setAttribute('aria-busy', a.status === 'loading' ? 'true' : 'false');
        answerEl.hidden = a.status === 'idle';
        answerEl.className = 'map-ask-answer' + (a.status === 'error' ? ' is-error' : a.status === 'clarify' ? ' is-clarify' : '');
        answerEl.replaceChildren();
        if (a.status === 'loading') answerEl.append(doc.createTextNode('Reading saved records…'));
        else if (a.status === 'error') answerEl.append(doc.createTextNode(a.error));
        else if (a.data) {
          var lead = doc.createElement('strong'); lead.textContent = a.data.answer || ''; answerEl.append(lead);
          (a.data.notes || []).forEach(function (note) { var p = doc.createElement('small'); p.textContent = note; answerEl.append(p); });
          if (a.status === 'clarify' && Array.isArray(a.data.examples)) {
            var list = doc.createElement('div'); list.className = 'map-ask-examples';
            var label = doc.createElement('small'); label.textContent = 'Try one of these:'; list.append(label);
            a.data.examples.forEach(function (example) {
              var b = doc.createElement('button'); b.type = 'button'; b.className = 'btn btn-soft btn-sm'; b.textContent = example;
              b.addEventListener('click', function () { if (input) input.value = example; controller.ask(example); });
              list.append(b);
            });
            answerEl.append(list);
          }
          (a.data.links || []).forEach(function (link) {
            if (!/^(https?:\/\/[^/]+)?\//.test(link.url || '')) return;
            var l = doc.createElement('a'); l.className = 'map-ask-link'; l.href = link.url; l.textContent = link.label; answerEl.append(l);
          });
          if (!view.connected && a.data.action && a.data.action.type !== 'none') {
            var wait = doc.createElement('small'); wait.textContent = 'Open the parcel map to see the highlighted parcels.'; answerEl.append(wait);
          }
        }
      }
      if (priority) { priority.checked = h.source === 'priority' && h.status !== 'off'; priority.disabled = !view.connected; }
      if (only) { only.checked = h.onlyMatches; only.disabled = h.status !== 'ready'; }
      if (clearBtn) clearBtn.hidden = h.status === 'off';
      if (retryBtn) retryBtn.hidden = h.status !== 'error';
      if (layerStatus) {
        layerStatus.textContent = h.status === 'off'
          ? (view.connected ? 'No highlight. Ask a question or turn on field-visit priority.' : 'Highlights become available when the parcel map loads.')
          : summary(h);
        layerStatus.className = 'map-highlight-status' + (h.status === 'error' ? ' is-error' : '');
      }
      if (legend) {
        legend.replaceChildren();
        legend.hidden = h.status === 'off';
        var rows = h.source === 'priority'
          ? [[COLORS.high, 'Visit first: overdue visit or 2+ reasons'], [COLORS.check, 'Check: one reason']]
          : [[COLORS.question, 'Matches your question']];
        rows.forEach(function (row) {
          var item = doc.createElement('span'), swatch = doc.createElement('i');
          swatch.style.borderColor = row[0]; item.append(swatch, doc.createTextNode(row[1])); legend.append(item);
        });
        if (h.source === 'priority' && h.status === 'ready') {
          Object.keys(REASONS).forEach(function (key) {
            var chip = doc.createElement('span'); chip.className = 'parcel-flag-chip is-' + key.replace(/_/g, '-');
            chip.textContent = REASONS[key] + ' (' + (h.counts[key] || 0) + ')'; legend.append(chip);
          });
          var note = doc.createElement('small'); note.textContent = 'Visit-planning aid from saved records only. It does not diagnose crop health.';
          legend.append(note);
        }
      }
    }

    form.addEventListener('submit', function (event) { event.preventDefault(); controller.ask(input ? input.value : ''); });
    if (priority) priority.addEventListener('change', function () { controller.setPriority(priority.checked); });
    if (only) only.addEventListener('change', function () { controller.setOnlyMatches(only.checked); });
    if (clearBtn) clearBtn.addEventListener('click', function () { controller.clear(); });
    if (retryBtn) retryBtn.addEventListener('click', function () { controller.retry(); });
    render({ highlights: controller.highlights, answer: controller.answer, connected: false });
    return controller;
  }

  return { BATCH: BATCH, COLORS: COLORS, REASONS: REASONS, batches: batches, createHighlights: createHighlights,
    chipsHtml: chipsHtml, questionChip: questionChip, summary: summary, errorMessage: errorMessage, create: create, mount: mount };
});

if (typeof window !== 'undefined' && typeof document !== 'undefined') {
  document.addEventListener('DOMContentLoaded', function () {
    var config = window.__parcelMapAssistant || null;
    if (!config || !window.ParcelMapAssistant) return;
    window.__parcelMapAssistantController = window.ParcelMapAssistant.mount(document, {
      askUrl: config.askUrl, matchUrl: config.matchUrl, priorityUrl: config.priorityUrl,
      csrf: window.__csrfToken || '', fetch: window.fetch.bind(window)
    });
  });
}
