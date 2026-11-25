const test = require('node:test');
const assert = require('node:assert/strict');
const { renderAdvice, renderState, errorMessage } = require('../../public/js/parcel-crop-advice.js');

function fakeDocument() {
  return { createElement: tag => ({ tag, dataset: {}, className: '', textContent: '' }) };
}
function container() {
  return { dataset: {}, children: [], replaceChildren(...nodes) { this.children = nodes; } };
}

test('advice renders as text only with delivery label and application-owned source titles', () => {
  const box = container();
  renderAdvice(fakeDocument(), box, { answer: 'First <img src=x onerror=alert(1)>\n\nSecond', delivery: 'cache',
    sources: [{ id: 'agri-irrigation', title: 'Crop water and irrigation' }, { bad: true }] });
  assert.equal(box.dataset.state, 'success');
  assert.equal(box.children[0].textContent, 'Saved answer reused (no new AI usage)');
  assert.equal(box.children[1].textContent, 'First <img src=x onerror=alert(1)>');
  assert.equal(box.children[2].textContent, 'Second');
  assert.equal(box.children[3].textContent, 'Reviewed sources: Crop water and irrigation');
  assert.ok(box.children.every(node => !('innerHTML' in node)));
});

test('states and errors are safe and explain limits and retry timing', () => {
  const box = container();
  renderState(fakeDocument(), box, 'loading', 'Preparing crop advice…');
  assert.equal(box.dataset.state, 'loading');
  assert.match(errorMessage(429, { message: 'Too Many Attempts.' }, '30'), /temporarily limited\. Try again in 30 seconds/);
  assert.equal(errorMessage(429, { message: 'The Assistant allowance has been reached.' }, null), 'The Assistant allowance has been reached.');
  assert.match(errorMessage(419, null, null), /session expired/);
  assert.match(errorMessage(500, null, null), /records are unaffected/);
});
