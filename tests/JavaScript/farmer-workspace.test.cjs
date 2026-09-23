const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

test('choosing All submits a cleared geography filter instead of leaving the previous area active', () => {
  const source = fs.readFileSync(path.resolve(__dirname, '../../public/js/farmer-workspace.js'), 'utf8');
  let changed;
  let submits = 0;
  const form = {
    querySelector: () => ({ addEventListener: (_, handler) => { changed = handler; } }),
    requestSubmit: () => submits++, addEventListener() {},
  };
  const control = { querySelectorAll: selector => selector === '[data-workspace-target]' ? [] : [form] };
  vm.runInNewContext(source, {
    document: { querySelector: () => control, getElementById: () => ({}) },
    window: { location: { hash: '' }, addEventListener() {} },
  });
  changed({ target: { value: '' } });
  changed({ target: { value: '1' } });
  assert.equal(submits, 2);
});
