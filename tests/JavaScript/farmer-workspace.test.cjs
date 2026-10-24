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

test('workbook sections keep their selected tab instead of marking the registry active', () => {
  const source = fs.readFileSync(path.resolve(__dirname, '../../public/js/farmer-workspace.js'), 'utf8');
  const registryLink = { dataset: { workspaceTarget: 'registry' }, classList: { toggle() { assert.fail('Must not change workbook navigation'); } } };
  const control = { querySelectorAll: selector => selector === '[data-workspace-target]' ? [registryLink] : [] };
  vm.runInNewContext(source, {
    document: { querySelector: () => control, getElementById: () => null },
    window: { location: { hash: '' }, addEventListener() {} },
  });
});
