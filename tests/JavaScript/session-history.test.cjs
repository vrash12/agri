const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {test} = require('node:test');

function page() {
  const listeners = new Map();
  const style = {};
  let reloads = 0;
  vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/session-history.js'), 'utf8'), {
    document: {documentElement: {style}},
    window: {addEventListener: (name, callback) => listeners.set(name, callback), location: {reload: () => reloads++}},
  });
  return {style, dispatch: (name, persisted) => listeners.get(name)({persisted}), reloads: () => reloads};
}

test('a private history snapshot stays hidden and must revalidate with the server', () => {
  const view = page();
  view.dispatch('pagehide', true);
  assert.equal(view.style.visibility, 'hidden');
  view.dispatch('pageshow', true);
  assert.equal(view.style.visibility, 'hidden');
  assert.equal(view.reloads(), 1);
});

test('normal navigation does not hide the document or enter a reload loop', () => {
  const view = page();
  view.dispatch('pageshow', false);
  view.dispatch('pagehide', false);
  assert.equal(view.style.visibility, undefined);
  assert.equal(view.reloads(), 0);
});
