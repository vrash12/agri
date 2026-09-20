(function (root) {
  'use strict';
  function create(options) {
    var revision = 0, abort = null, key = '', records = new Map();
    var state = { enabled: false, status: 'off', year: null, season: null, filter: 'all', legend: [], count: 0, error: '' };
    function notify() { options.onChange(state); }
    function record(id) { return state.status === 'ready' ? records.get(String(id)) || null : null; }
    function visible(id) { return !state.enabled || state.status !== 'ready' || state.filter === 'all' || record(id)?.crop === state.filter; }
    function color(id, savedColor) { return !state.enabled ? savedColor : record(id)?.color || '#64748B'; }
    async function load(settings, ids, force) {
      state.enabled = !!settings.enabled;
      state.filter = settings.filter || 'all';
      var nextIds = Array.from(new Set(ids.map(String))).sort();
      var nextKey = settings.year + '/' + settings.season + '/' + nextIds.join(',');
      if (!state.enabled) {
        revision++; if (abort) abort.abort(); key = ''; records.clear(); state.status = 'off'; notify(); return;
      }
      if (!force && key === nextKey && (state.status === 'ready' || state.status === 'loading')) { notify(); return; }
      var current = ++revision;
      if (abort) abort.abort();
      abort = new AbortController();
      key = nextKey; records.clear(); state.legend = []; state.error = '';
      state.year = Number(settings.year); state.season = settings.season; state.count = nextIds.length;
      state.status = 'loading'; notify();
      try {
        var nextRecords = new Map(), legend = [];
        for (var start = 0; start < nextIds.length; start += 200) {
          var query = new URLSearchParams({ year: String(state.year), season: state.season });
          nextIds.slice(start, start + 200).forEach(function (id) { query.append('plot_ids[]', id); });
          var response = await options.fetch(options.url + '?' + query, { headers: { Accept: 'application/json' }, signal: abort.signal });
          if (current !== revision) return;
          if (!response.ok) throw new Error(response.status === 401 || response.status === 419 ? 'Your session ended. Sign in again.' : 'Seasonal crop records could not load. Retry or use saved parcel colors.');
          var data = await response.json();
          if (current !== revision) return;
          if (Number(data.year) !== state.year || data.season !== state.season || !Array.isArray(data.records)) throw new Error('The crop response did not match the selected period. Retry.');
          data.records.forEach(function (item) {
            if (/^#[a-f0-9]{6}$/i.test(item.color)) nextRecords.set(String(item.plot_id), item);
          });
          legend = data.legend || [];
        }
        if (current !== revision) return;
        if (nextRecords.size !== nextIds.length) throw new Error('Some parcels are no longer available. Reload the map to refresh your workspace.');
        records = nextRecords; state.legend = legend; state.status = 'ready'; notify();
      } catch (error) {
        if (current !== revision || error.name === 'AbortError') return;
        state.status = 'error'; state.error = error.message; records.clear(); notify();
      }
    }
    return { load: load, record: record, visible: visible, color: color, state: state };
  }
  root.ParcelCropLayer = { create: create };
  if (typeof module !== 'undefined' && module.exports) module.exports = root.ParcelCropLayer;
})(typeof window !== 'undefined' ? window : globalThis);
