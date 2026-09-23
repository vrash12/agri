const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const root = path.resolve(__dirname, '../..');
// The finder lives in its own file rather than inside the Blade page, so it can be
// read here without stripping template syntax.
const source = fs.readFileSync(path.join(root, 'public/js/farmer-finder.js'), 'utf8');

/**
 * The finder's pure helpers, lifted out of the IIFE so they can be exercised without
 * a DOM. These are the parts that decide what the user is told.
 */
const helpers = ['clean', 'describe', 'matchesFor', 'helpTextFor'].map(name => {
  const match = source.match(new RegExp('^  function ' + name + '\\b[\\s\\S]*?\\n  }', 'm'));
  assert.ok(match, 'Missing finder helper ' + name);
  return match[0];
}).join('\n');

const MIN_QUERY = 2;

function finder() {
  const context = vm.createContext({ MIN_QUERY, console });
  vm.runInContext(helpers, context);

  return context;
}

test('the AgriGOV ID identifies a farmer even when no external registry number exists', () => {
  const { describe } = finder();

  assert.equal(
    describe({ id: 7, agri_gov_id: 'AGRI-F-000007', first_name: 'Rosemarie', middle_name: 'Gabuya', last_name: 'Abad', ffrs: '03-69-11-001-000002' }).label,
    'Rosemarie Gabuya Abad — AGRI-F-000007 — FFRS 03-69-11-001-000002'
  );

  // A missing FFRS does not hide the farmer's main ID.
  assert.equal(
    describe({ id: 8, agri_gov_id: 'AGRI-F-000008', first_name: 'Juan', last_name: 'Cruz', ffrs: null }).label,
    'Juan Cruz — AGRI-F-000008'
  );
});

test('a truncated result never reads as the complete answer', () => {
  const { helpTextFor, describe } = finder();
  const entries = [{ id: '1' }, { id: '2' }].map((_, i) => describe({ id: i, first_name: 'A', last_name: 'B', ffrs: String(i) }));

  const message = helpTextFor(
    { truncated: true, returned: 50, total: 812, needs_more_input: false },
    entries,
    'cruz',
    1665,
    'Tarlac'
  );

  assert.match(message, /50/, 'The message must say how many were shown');
  assert.match(message, /812/, 'The message must say how many exist');
});

test('an empty result names the term and the workspace instead of going silent', () => {
  const { helpTextFor } = finder();

  const message = helpTextFor({ truncated: false, returned: 0, total: 0, needs_more_input: false }, [], 'zzz', 1665, 'Anao');

  assert.match(message, /zzz/);
  assert.match(message, /Anao/);
});

test('a query below the minimum asks for more input rather than reporting no matches', () => {
  const { helpTextFor } = finder();

  const message = helpTextFor(null, [], 'a', 1665, 'Tarlac');

  assert.match(message, /at least 2 characters/);
  assert.match(message, /1,665/, 'The idle message states the size of the workspace being searched');
  assert.doesNotMatch(message, /No farmer matches/, 'A short query is not the same as no result');
});

test('an exact label match wins over the substring matches it is contained in', () => {
  const { matchesFor, describe } = finder();
  const entries = [
    describe({ id: 1, agri_gov_id: 'AGRI-F-000001', first_name: 'Juan', last_name: 'Cruz', ffrs: 'A1' }),
    describe({ id: 2, agri_gov_id: 'AGRI-F-000002', first_name: 'Juana', last_name: 'Cruz', ffrs: 'A2' }),
  ];

  // Typing the full label of one farmer must select that one, not both, or the
  // Locate button would stay disabled after a valid pick from the list.
  const matches = matchesFor(entries, 'Juan Cruz — AGRI-F-000001 — FFRS A1');

  assert.equal(matches.length, 1);
  assert.equal(matches[0].id, '1');
});

test('searching a canonical ID locates its numeric record among farmers with the same name', () => {
  const { matchesFor, describe } = finder();
  const entries = [
    describe({ id: 123, agri_gov_id: 'AGRI-F-000123', first_name: 'Juan', last_name: 'Cruz', ffrs: null }),
    describe({ id: 124, agri_gov_id: 'AGRI-F-000124', first_name: 'Juan', last_name: 'Cruz', ffrs: null }),
  ];

  const matches = matchesFor(entries, '  agri-f-000123  ');

  assert.equal(matches.length, 1);
  assert.equal(matches[0].id, '123', 'Map routes still receive the numeric record ID');
  assert.equal(matchesFor(entries, 'Juan Cruz').length, 2, 'Namesakes must not be silently merged');
});

test('a partial name still offers every farmer it could mean', () => {
  const { matchesFor, describe } = finder();
  const entries = [
    describe({ id: 1, agri_gov_id: 'AGRI-F-000001', first_name: 'Juan', last_name: 'Cruz', ffrs: 'A1' }),
    describe({ id: 2, agri_gov_id: 'AGRI-F-000002', first_name: 'Juana', last_name: 'Cruz', ffrs: 'A2' }),
    describe({ id: 3, agri_gov_id: 'AGRI-F-000003', first_name: 'Maria', last_name: 'Santos', ffrs: 'B1' }),
  ];

  assert.equal(matchesFor(entries, 'cruz').length, 2);
  assert.equal(matchesFor(entries, '').length, 0, 'An empty box must not select everyone');
});

test('the finder holds no Blade syntax, so it can be cached and linted as a plain script', () => {
  assert.doesNotMatch(source, /@json|\{\{|@php|@endphp/, 'Template syntax leaked into the script file');
  assert.match(source, /window\.__farmerLookupUrl/, 'Configuration must arrive through window');
});
