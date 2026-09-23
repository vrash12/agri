/*
  The parcel map's farmer finder.

  This searches on the server. It used to filter a list of every farmer in the
  workspace that had been serialised into the page — around 600 KB for a province,
  sent on every load whether or not anyone opened the map, and carrying personal data
  the map never displayed. Searching in the database instead keeps that off the page
  and scopes the search to what the account may read before any name is matched.

  Configuration arrives on `window`, so this file holds no template syntax and can be
  read, linted and tested on its own:
    window.__farmerLookupUrl    endpoint that takes ?q= and &limit=
    window.__mapFarmerCount     farmers in the workspace, for the idle help text
    window.__mapWorkspaceLabel  what to call the workspace in that text
*/
(function () {
  'use strict';

  var MIN_QUERY = 2;
  var DEBOUNCE_MS = 200;
  var RESULT_LIMIT = 50;

  function clean(value) {
    return String(value === null || value === undefined ? '' : value).trim().replace(/\s+/g, ' ');
  }

  // Keep the canonical ID visible so staff can distinguish farmers with the same name.
  function describe(farmer) {
    var name = clean([
      farmer.first_name,
      farmer.middle_name,
      farmer.last_name,
      farmer.ext_name
    ].filter(Boolean).join(' '));
    var identifier = clean(farmer.agri_gov_id);
    var ffrs = clean(farmer.ffrs);

    return { id: String(farmer.id), name: name, label: [name, identifier, ffrs ? 'FFRS ' + ffrs : ''].filter(Boolean).join(' — ') };
  }

  function matchesFor(entries, value) {
    var query = clean(value).toLowerCase();
    if (!query) return [];

    var exact = entries.filter(function (entry) {
      return entry.label.toLowerCase() === query;
    });
    if (exact.length) return exact;

    return entries.filter(function (entry) {
      return entry.label.toLowerCase().indexOf(query) !== -1;
    });
  }

  /*
    Turn a lookup response into the line shown under the search box.

    A capped result must never read as the complete answer, so when the server says it
    truncated, the message names both numbers rather than just listing what came back.
  */
  function helpTextFor(json, entries, term, workspaceTotal, workspaceLabel) {
    var total = Number(workspaceTotal || 0);

    if (!json || json.needs_more_input) {
      return 'Type at least ' + MIN_QUERY + ' characters to search ' +
        total.toLocaleString() + ' farmers across ' + workspaceLabel + '.';
    }
    if (!entries.length) {
      return 'No farmer matches “' + term + '” in ' + workspaceLabel + '.';
    }
    if (json.truncated) {
      return 'Showing ' + Number(json.returned).toLocaleString() + ' of ' +
        Number(json.total).toLocaleString() + ' matches. Enter more of the AgriGOV ID, name or FFRS.';
    }
    if (entries.length === 1) {
      return 'Ready to locate ' + entries[0].name + '.';
    }

    return entries.length.toLocaleString() + ' matches. Enter more of the AgriGOV ID, name or FFRS.';
  }

  function start() {
    var input = document.getElementById('mapFarmerSearch');
    var locateButton = document.getElementById('mapFarmerLocateBtn');
    var pickerHelp = document.getElementById('mapPickerHelp');
    var optionList = document.getElementById('mapFarmerOptions');
    var selectedName = document.getElementById('selName');

    if (!input || !locateButton) return;

    var lookupUrl = window.__farmerLookupUrl || '';
    var workspaceTotal = Number(window.__mapFarmerCount || 0);
    var workspaceLabel = window.__mapWorkspaceLabel || 'this workspace';

    var entries = [];
    var lastTerm = null;
    var inFlight = null;
    var debounce = null;

    function renderOptions() {
      if (!optionList) return;
      while (optionList.firstChild) optionList.removeChild(optionList.firstChild);
      entries.forEach(function (entry) {
        var option = document.createElement('option');
        option.value = entry.label;
        optionList.appendChild(option);
      });
    }

    function setHelp(message) {
      if (pickerHelp) pickerHelp.textContent = message;
    }

    function syncLocateState() {
      var matches = matchesFor(entries, input.value);
      locateButton.disabled = matches.length !== 1;

      return matches;
    }

    function search(term) {
      if (!lookupUrl) return Promise.resolve();

      var token = {};
      inFlight = token;

      return fetch(lookupUrl + '?q=' + encodeURIComponent(term) + '&limit=' + RESULT_LIMIT, {
        headers: { Accept: 'application/json' }
      }).then(function (res) {
        if (!res.ok) throw new Error('lookup failed');

        return res.json();
      }).then(function (json) {
        // A slow earlier request must not overwrite a newer result.
        if (inFlight !== token) return;

        entries = Array.isArray(json.farmers) ? json.farmers.map(describe) : [];
        renderOptions();
        setHelp(helpTextFor(json, entries, term, workspaceTotal, workspaceLabel));
        syncLocateState();
      }).catch(function () {
        if (inFlight !== token) return;
        entries = [];
        renderOptions();
        setHelp('The farmer search is unavailable. Check your connection and try again.');
        locateButton.disabled = true;
      });
    }

    function onInput() {
      var term = clean(input.value);

      if (term === lastTerm) {
        syncLocateState();

        return;
      }
      lastTerm = term;

      if (debounce) window.clearTimeout(debounce);

      if (term.length < MIN_QUERY) {
        entries = [];
        renderOptions();
        locateButton.disabled = true;
        setHelp(helpTextFor(null, entries, term, workspaceTotal, workspaceLabel));

        return;
      }

      setHelp('Searching…');
      debounce = window.setTimeout(function () { search(term); }, DEBOUNCE_MS);
    }

    function locateFarmer() {
      var matches = matchesFor(entries, input.value);
      if (matches.length !== 1) {
        syncLocateState();

        return;
      }

      if (typeof window.__openFarmer3d !== 'function') {
        if (typeof window.__mapToast === 'function') {
          window.__mapToast('The map is still loading. Try again in a moment.', 'warn');
        }

        return;
      }

      input.value = matches[0].label;
      lastTerm = clean(input.value);
      window.__openFarmer3d(matches[0].id);
      syncLocateState();
    }

    function syncSelectionActions() {
      var value = clean(selectedName ? selectedName.textContent : '');
      var hasSelection = value !== '' && value !== '—' && value !== 'No farmer selected';
      var module = document.getElementById('farmersMapModule');

      if (module) module.classList.toggle('has-farmer-selection', hasSelection);

      document.querySelectorAll('#farmersMapModule .parcel-requires-selection').forEach(function (button) {
        button.disabled = !hasSelection;
        button.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
      });

      document.querySelectorAll('#farmersMapModule .parcel-requires-selection-link').forEach(function (link) {
        link.setAttribute('aria-disabled', hasSelection ? 'false' : 'true');
        link.setAttribute('tabindex', hasSelection ? '0' : '-1');
      });
    }

    input.addEventListener('input', onInput);
    input.addEventListener('change', onInput);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        locateFarmer();
      }
    });
    locateButton.addEventListener('click', locateFarmer);

    if (selectedName) {
      new MutationObserver(syncSelectionActions).observe(selectedName, {
        childList: true,
        characterData: true,
        subtree: true
      });
    }

    setHelp(helpTextFor(null, entries, '', workspaceTotal, workspaceLabel));
    syncSelectionActions();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
}());
