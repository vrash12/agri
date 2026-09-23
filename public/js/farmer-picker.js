/*
  The beneficiary picker shared by the assistance and harvest forms.

  Both forms used to serialise every farmer the account can see into a <select>. For
  a municipality of 1,546 that is most of the page weight, sent on every load whether
  or not anyone opens the list. This searches the registry on the server instead, so
  the page carries only the farmer already chosen.

  The element contract, so this file holds no template syntax and can be read, linted
  and tested on its own. Everything arrives as data attributes on the <select>:

    data-farmer-picker            marks the select as a picker
    data-endpoint                 search URL, taking ?q=, &limit=, &municipality_id=, &profile=
    data-profile="1"              ask the endpoint for the preview fields
    data-municipality-select      id of a municipality <select> that narrows the search
    data-placeholder              what the empty control says

  Two things other page code can rely on:

    * The select's own dataset always describes the current selection — data-name,
      data-ffrs and, with a profile, data-location, data-municipality, data-province,
      data-area, data-contact, data-tags. The assistance form's preview reads exactly
      these, so it does not need to know where an option came from.
    * A normal `change` event fires on the select whenever the selection changes.

  Without TomSelect, or without JavaScript, the select still works: it holds the
  already-selected farmer, and the form offers a link that renders the whole registry
  the old way.
*/
(function () {
  'use strict';

  var MIN_QUERY = 2;
  var DEBOUNCE_MS = 200;
  var RESULT_LIMIT = 20;

  function clean(value) {
    return String(value === null || value === undefined ? '' : value).trim().replace(/\s+/g, ' ');
  }

  // The fields a selection writes onto the select. Listed rather than copied
  // wholesale so a future endpoint field cannot silently become a data attribute.
  function datasetFields() {
    return ['name', 'agriGovId', 'ffrs', 'municipalityId', 'location', 'municipality', 'province', 'area', 'contact', 'tags'];
  }

  // What the control says when nobody has typed enough, or when nothing matched.
  // The counts matter: "no results" and "too many to list" are different problems
  // and the operator can only act on the second one.
  function messageFor(state) {
    var term = clean(state && state.term);

    // A provincial account has to say which office it is recording for before any
    // beneficiary makes sense: the write would be refused anyway, and searching the
    // whole province first would only offer names that cannot be used.
    if (state && state.needsWorkspace) {
      return 'Choose a municipality first to search its registered beneficiaries.';
    }

    if (term.length < MIN_QUERY) {
      return 'Type at least ' + MIN_QUERY + ' characters of an AgriGOV ID, name, FFRS or RSBSA number.';
    }

    if (state.loading) {
      return 'Searching…';
    }

    if (state.failed) {
      return 'The registry could not be searched. Check the connection and try again.';
    }

    if (!state.returned) {
      return 'No beneficiary matches “' + term + '”. Check the spelling or the registry number.';
    }

    if (state.truncated) {
      return 'Showing ' + state.returned + ' of ' + state.total + ' matches. Type more to narrow it down.';
    }

    return state.returned === 1
      ? '1 match.'
      : state.returned + ' matches.';
  }

  // The endpoint URL for a term, built from the element's configuration. Kept pure
  // so the query it sends can be asserted without a network.
  function searchUrl(endpoint, term, options) {
    var settings = options || {};
    var query = [
      'q=' + encodeURIComponent(clean(term)),
      'limit=' + encodeURIComponent(settings.limit || RESULT_LIMIT)
    ];

    if (settings.profile) {
      query.push('profile=1');
    }

    if (settings.municipalityId) {
      query.push('municipality_id=' + encodeURIComponent(settings.municipalityId));
    }

    return endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + query.join('&');
  }

  // Whether a selection survives a change of municipality workspace. A farmer from
  // the municipality being switched away from must not stay selected, or the form
  // would submit a beneficiary the chosen office does not hold.
  function selectionSurvives(selectedMunicipalityId, workspaceId) {
    if (!workspaceId) {
      return true;
    }

    return String(selectedMunicipalityId || '') === String(workspaceId);
  }

  function applyDataset(select, dataset) {
    datasetFields().forEach(function (field) {
      if (dataset && dataset[field] !== undefined && dataset[field] !== null) {
        select.dataset[field] = dataset[field];
      } else {
        delete select.dataset[field];
      }
    });
  }

  function start() {
    var selects = Array.prototype.slice.call(
      document.querySelectorAll('select[data-farmer-picker]')
    );

    selects.forEach(setUp);
  }

  function setUp(select) {
    var endpoint = select.dataset.endpoint;

    if (!endpoint) {
      return;
    }

    var wantsProfile = select.dataset.profile === '1';
    var workspace = select.dataset.municipalitySelect
      ? document.getElementById(select.dataset.municipalitySelect)
      : null;
    var help = document.getElementById(select.id + '_picker_help');
    var known = Object.create(null);
    var timer = null;

    // Options rendered on page load describe the farmer already chosen. Remembering
    // them here means re-selecting that farmer after a search still restores the
    // full profile, not just the name in the list.
    Array.prototype.slice.call(select.options).forEach(function (option) {
      if (option.value) {
        known[option.value] = Object.assign({}, option.dataset);
      }
    });

    function needsWorkspace() {
      return Boolean(workspace) && !workspace.value;
    }

    function say(state) {
      if (help) {
        help.textContent = messageFor(Object.assign({ needsWorkspace: needsWorkspace() }, state));
      }
    }

    function syncSelection() {
      applyDataset(select, known[select.value] || null);
    }

    function workspaceId() {
      return workspace ? workspace.value : '';
    }

    if (typeof TomSelect === 'undefined') {
      // No TomSelect: the select still holds the chosen farmer and the browse link
      // still works, so the form is usable rather than broken.
      syncSelection();
      say({ term: '', returned: 0 });

      return;
    }

    var control = new TomSelect(select, {
      valueField: 'value',
      labelField: 'label',
      searchField: [],
      create: false,
      maxOptions: RESULT_LIMIT,
      allowEmptyOption: true,
      placeholder: select.dataset.placeholder || 'Type an AgriGOV ID, name, FFRS or RSBSA number',
      // Searching happens in the database, so the widget must not also filter what
      // comes back — a server match on a middle name would otherwise be hidden
      // because the typed text is not in the visible label.
      shouldLoad: function (term) {
        return !needsWorkspace() && clean(term).length >= MIN_QUERY;
      },
      load: function (term, callback) {
        if (timer) {
          window.clearTimeout(timer);
        }

        say({ term: term, loading: true });

        timer = window.setTimeout(function () {
          window.fetch(searchUrl(endpoint, term, {
            profile: wantsProfile,
            municipalityId: workspaceId(),
            limit: RESULT_LIMIT
          }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then(function (response) {
              if (!response.ok) {
                throw new Error('Search failed with status ' + response.status);
              }

              return response.json();
            })
            .then(function (payload) {
              var farmers = payload.farmers || [];

              farmers.forEach(function (farmer) {
                known[farmer.value] = farmer.dataset || {};
              });

              say({
                term: term,
                returned: payload.returned || 0,
                total: payload.total || 0,
                truncated: Boolean(payload.truncated)
              });

              callback(farmers);
            })
            .catch(function () {
              say({ term: term, failed: true });
              callback();
            });
        }, DEBOUNCE_MS);
      },
      render: {
        no_results: function () {
          return '<div class="no-results">No matching beneficiary.</div>';
        }
      }
    });

    control.on('change', syncSelection);

    if (workspace) {
      workspace.addEventListener('change', function () {
        var current = select.value;

        if (current && !selectionSurvives(known[current] && known[current].municipalityId, workspaceId())) {
          control.clear(true);
          control.removeItem(current, true);
        }

        // Everything cached was found under the previous workspace, so the next
        // search starts clean rather than offering stale names.
        control.clearOptions();
        syncSelection();
        select.dispatchEvent(new Event('change', { bubbles: true }));

        if (needsWorkspace()) {
          control.disable();
        } else {
          control.enable();
        }

        say({ term: '', returned: 0 });
      });

      if (needsWorkspace()) {
        control.disable();
      }
    }

    var describedBy = select.getAttribute('aria-describedby');
    if (describedBy && control.control_input) {
      control.control_input.setAttribute('aria-describedby', describedBy);
    }

    syncSelection();
    say({ term: '', returned: 0 });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
}());
