const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const root = path.resolve(__dirname, '../..');
// The picker lives in its own file rather than inside the Blade forms, so it can be
// read here without stripping template syntax — and so both forms share one copy.
const source = fs.readFileSync(path.join(root, 'public/js/farmer-picker.js'), 'utf8');

/**
 * The picker's pure helpers, lifted out of the IIFE. These decide what the operator
 * is told and what the server is asked for, which is the part worth pinning.
 */
const helpers = ['clean', 'datasetFields', 'messageFor', 'searchUrl', 'selectionSurvives'].map(name => {
  const match = source.match(new RegExp('^  function ' + name + '\\b[\\s\\S]*?\\n  }', 'm'));
  assert.ok(match, 'Missing picker helper ' + name);
  return match[0];
}).join('\n');

const MIN_QUERY = 2;
const RESULT_LIMIT = 20;

function picker() {
  const context = vm.createContext({ MIN_QUERY, RESULT_LIMIT, console, encodeURIComponent });
  vm.runInContext(helpers, context);

  return context;
}

test('a provincial account is asked for its municipality before anything else', () => {
  const { messageFor } = picker();

  // The write would be refused without one, so searching the whole province first
  // would only offer names that cannot be used.
  assert.equal(
    messageFor({ needsWorkspace: true, term: 'delacruz' }),
    'Choose a municipality first to search its registered beneficiaries.'
  );
});

test('a short term asks for more rather than returning a slice of everyone', () => {
  const { messageFor } = picker();

  assert.match(messageFor({ term: '' }), /at least 2 characters/);
  assert.match(messageFor({ term: 'd' }), /at least 2 characters/);
  assert.doesNotMatch(messageFor({ term: 'de', returned: 3, total: 3 }), /at least 2 characters/);
});

test('no match and too many matches are told apart, because only one is actionable', () => {
  const { messageFor } = picker();

  assert.equal(
    messageFor({ term: 'zzz', returned: 0, total: 0 }),
    'No beneficiary matches “zzz”. Check the spelling or the registry number.'
  );

  // The operator can act on this one: type more.
  assert.equal(
    messageFor({ term: 'cruz', returned: 20, total: 137, truncated: true }),
    'Showing 20 of 137 matches. Type more to narrow it down.'
  );

  assert.equal(messageFor({ term: 'cruz', returned: 1, total: 1 }), '1 match.');
  assert.equal(messageFor({ term: 'cruz', returned: 4, total: 4 }), '4 matches.');
});

test('a failed search says so instead of looking like an empty registry', () => {
  const { messageFor } = picker();

  assert.match(messageFor({ term: 'cruz', failed: true }), /could not be searched/);
  assert.equal(messageFor({ term: 'cruz', loading: true }), 'Searching…');
});

test('the search URL carries the term, the limit, and nothing it was not given', () => {
  const { searchUrl } = picker();

  assert.equal(
    searchUrl('/farmers/picker', 'dela cruz', {}),
    '/farmers/picker?q=dela%20cruz&limit=20'
  );

  // The profile block is opt-in: a form that shows no preview must not ask for a
  // contact number it will never display.
  assert.equal(
    searchUrl('/farmers/picker', 'cruz', { profile: true, municipalityId: '12', limit: 5 }),
    '/farmers/picker?q=cruz&limit=5&profile=1&municipality_id=12'
  );

  // An endpoint that already carries a query string is extended, not corrupted.
  assert.equal(
    searchUrl('/farmers/picker?v=2', 'cruz', {}),
    '/farmers/picker?v=2&q=cruz&limit=20'
  );
});

test('a term with regex- or URL-significant characters is sent intact', () => {
  const { searchUrl } = picker();

  assert.equal(
    searchUrl('/farmers/picker', '100%  &  _cruz', {}),
    '/farmers/picker?q=100%25%20%26%20_cruz&limit=20'
  );
});

test('a beneficiary from the old workspace does not survive a municipality change', () => {
  const { selectionSurvives } = picker();

  // Otherwise the form would submit a beneficiary the chosen office does not hold,
  // and the server would have to be the one to notice.
  assert.equal(selectionSurvives('12', '13'), false);
  assert.equal(selectionSurvives('12', '12'), true);

  // Numbers and strings describe the same municipality.
  assert.equal(selectionSurvives(12, '12'), true);

  // No workspace chosen means nothing to contradict, so the selection stands.
  assert.equal(selectionSurvives('12', ''), true);
});

test('only the listed fields become data attributes on the select', () => {
  const { datasetFields } = picker();

  // The preview reads these by name. A field added to the endpoint should not turn
  // into a data attribute without someone deciding it should.
  assert.deepEqual(Array.from(datasetFields()), [
    'name', 'ffrs', 'municipalityId',
    'location', 'municipality', 'province', 'area', 'contact', 'tags'
  ]);
});

test('whitespace in a typed term is collapsed rather than searched for', () => {
  const { clean } = picker();

  assert.equal(clean('  dela   cruz  '), 'dela cruz');
  assert.equal(clean(null), '');
  assert.equal(clean(undefined), '');
});
