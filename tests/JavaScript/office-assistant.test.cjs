const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/office-assistant.js'), 'utf8');

class Element {
  constructor(tagName = 'div') {
    this.tagName = tagName; this.listeners = {}; this.children = []; this.attributes = {};
    this.hidden = false; this.value = ''; this.textContent = ''; this.disabled = false; this.className = '';
    this.scrollTop = 0; this.scrollHeight = 400; this.clientHeight = 400;
    const classes = () => new Set(this.className.split(/\s+/).filter(Boolean));
    this.classList = {
      add: (...names) => { this.className = [...new Set([...classes(), ...names])].join(' '); },
      remove: (...names) => { this.className = [...classes()].filter(name => !names.includes(name)).join(' '); },
      toggle: (name, enabled) => enabled ? this.classList.add(name) : this.classList.remove(name)
    };
  }
  get childElementCount() { return this.children.length; }
  addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }
  setAttribute(name, value) { this.attributes[name] = value; }
  removeAttribute(name) { delete this.attributes[name]; }
  append(...children) { children.forEach(child => { child.parent = this; this.children.push(child); }); }
  replaceChildren(...children) {
    this.children.forEach(child => { child.parent = null; });
    this.children = [];
    this.append(...children);
  }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
  querySelectorAll(tagName) {
    return this.children.flatMap(child => [
      ...(child.tagName === tagName ? [child] : []), ...child.querySelectorAll(tagName)
    ]);
  }
  focus() { this.focused = true; }
  showModal() { this.open = true; }
  close() { this.open = false; return this.fire('close'); }
  fire(name, properties = {}) {
    const event = { preventDefault() { this.prevented = true; }, ...properties };
    this.lastEvent = event;
    return Promise.all((this.listeners[name] || []).map(fn => fn(event)));
  }
}

function setup(fetcher) {
  const names = ['dialog', 'open', 'input', 'form', 'send', 'clear', 'welcome',
    'messages', 'body', 'status', 'count', 'close', 'jump'];
  const elements = Object.fromEntries(names.map(name => [name, new Element()]));
  elements.jump.hidden = true;
  const root = { dataset: { endpoint: '/assistant/message', module: 'crops' },
    querySelector: selector => elements[selector.slice(16, -1)], querySelectorAll: () => [] };
  const document = { querySelector: selector => selector === '[data-office-assistant]' ? root : { content: 'synthetic-csrf' },
    createElement: tagName => new Element(tagName) };
  const listeners = {};
  let timer;
  const window = { setTimeout(fn) { timer = fn; return 1; }, clearTimeout() { timer = null; },
    addEventListener(name, fn) { listeners[name] = fn; },
    location: { href: 'https://example.test/farmers', origin: 'https://example.test' } };
  const requests = [];
  const fetch = async (url, options) => { requests.push({ url, options }); return fetcher(url, options); };
  vm.runInNewContext(source, { document, window, fetch, AbortController, URL });
  return { ...elements, requests, listeners, timeout: () => timer?.() };
}
function response(status, data) { return { status, ok: status === 200, redirected: false, json: async () => data }; }
function reply(answer, options = {}) { return response(200, { answer, source: 'help', context: null, ...options }); }
function payload(ui, index = -1) { return JSON.parse(ui.requests.at(index).options.body); }
function content(ui, index = -1) { return ui.messages.children.at(index).children[1].textContent; }
function controls(ui, index = -1) { return ui.messages.children.at(index).children.at(-1).children; }

test('application-owned conversational replies display and retain only an opaque reference', async () => {
  const ui = setup(async () => reply('I am the AgriLGU Assistant.', { source: 'conversation', context: 'identity-reference' }));
  ui.input.value = 'Who are you?'; await ui.form.fire('submit');
  assert.equal(content(ui), 'I am the AgriLGU Assistant.');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'Assistant');
  assert.equal(ui.input.value, '');
  ui.input.value = 'Tell me more'; await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Tell me more', module: 'crops', context: 'identity-reference' });
  assert.doesNotMatch(ui.requests.at(-1).options.body, /history|general_context|I am the/);
});

test('retrieved source titles are bounded plain text and never rendered as HTML or navigation', async () => {
  const ui = setup(async () => reply('Use Seasonal crops.', { sources: [
    { id: 'crop-sections', title: '<img src=x onerror=alert(1)>' },
    { id: 'crop-limits', title: 'Crop limits', url: 'https://other.test' },
    { id: '../../private', title: 'Invalid source' },
    { id: 'extra', title: 'Fourth source is ignored' }
  ] }));
  ui.input.value = 'How do I draw corn?'; await ui.form.fire('submit');
  const entry = ui.messages.children.at(-1);
  assert.equal(entry.children[2].textContent, 'Based on: <img src=x onerror=alert(1)> · Crop limits');
  assert.equal(entry.children[2].innerHTML, undefined);
  assert.equal(entry.querySelectorAll('a').length, 0);
  assert.equal(entry.querySelectorAll('img').length, 0);
});

test('one conversation renders untrusted text safely and sends only current message and opaque context', async () => {
  let turn = 0;
  const ui = setup(async () => turn++ === 0
    ? reply('Farmer Fixture has three parcels.', { source: 'records', context: 'opaque-record-reference', year: 2026 })
    : reply('<script>untrusted()</script>', { context: 'opaque-help-reference' }));
  await ui.open.fire('click');
  assert.equal(ui.dialog.open, true);
  assert.equal(ui.input.focused, true);
  ui.input.value = 'Find farmer AGRI-F-000012'; await ui.form.fire('submit');
  ui.input.value = 'How do I draw a crop area?'; await ui.form.fire('submit');
  assert.equal(ui.requests[0].options.headers['X-CSRF-TOKEN'], 'synthetic-csrf');
  assert.equal(ui.requests[0].options.credentials, 'same-origin');
  assert.deepEqual(payload(ui, 0), { message: 'Find farmer AGRI-F-000012', module: 'crops' });
  assert.deepEqual(payload(ui), { message: 'How do I draw a crop area?', module: 'crops', context: 'opaque-record-reference' });
  assert.doesNotMatch(ui.requests[1].options.body, /Farmer Fixture|AGRI-F|history|transcript|mode|year|farmer_id/);
  assert.equal(content(ui), '<script>untrusted()</script>');
  assert.equal(ui.messages.children.at(-1).innerHTML, undefined);
  assert.equal(ui.messages.children[1].children[0].textContent, 'Office records');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'System guide');
  assert.equal(ui.messages.children.length, 4);
});

test('choices use opaque tokens and local visible labels without sending names or numeric IDs', async () => {
  const ui = setup(async () => reply('Which farmer?', { source: 'clarification', context: 'choice-context',
    actions: Array.from({ length: 9 }, (_, index) => ({ choice: 'encrypted-choice-' + index, label: '<script>Fixture ' + index + '</script>' })),
    links: [{ label: 'Allowed register', url: '/farmers' }, { label: 'Wrong origin', url: 'https://other.test/' },
      { label: 'Unsafe', url: 'javascript:alert(1)' }, { label: 'Unsafe credentials', url: 'https://name:secret@example.test/farmers' }] }));
  ui.input.value = 'Find farmer Fixture'; await ui.form.fire('submit');
  const actions = controls(ui);
  assert.equal(actions.length, 7);
  assert.equal(actions[0].textContent, '<script>Fixture 0</script>');
  assert.equal(actions[6].href, 'https://example.test/farmers');
  await actions[0].fire('click');
  assert.deepEqual(payload(ui), { message: 'Continue with my choice.', module: 'crops',
    context: 'choice-context', choice: 'encrypted-choice-0' });
  assert.doesNotMatch(ui.requests[1].options.body, /Fixture|farmer_id|history/);
  assert.equal(content(ui, -2), '<script>Fixture 0</script>');
});

test('older reply choices retain their own context rather than borrowing another report reference', async () => {
  let turn = 0;
  const ui = setup(async () => turn++ === 0
    ? reply('Choose a farmer.', { source: 'clarification', context: 'farmer-choice-context',
      actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }] })
    : reply('Assistance total.', { source: 'records', context: 'assistance-context' }));
  ui.input.value = 'Find farmer Synthetic'; await ui.form.fire('submit');
  const choice = controls(ui)[0];
  ui.input.value = 'Our assistance'; await ui.form.fire('submit');
  await choice.fire('click');
  assert.equal(payload(ui).context, 'farmer-choice-context');
  assert.equal(payload(ui).choice, 'farmer-token');
});

test('a pending send cannot duplicate; close cancels and keeps a retryable message while discarding late content', async () => {
  let resolve;
  const ui = setup(() => new Promise(done => { resolve = done; }));
  await ui.open.fire('click'); ui.input.value = 'Help';
  const pending = ui.form.fire('submit');
  await ui.form.fire('submit');
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.send.disabled, true);
  assert.equal(ui.clear.disabled, false);
  assert.equal(content(ui), 'Working on your question…');
  await ui.close.fire('click');
  assert.equal(ui.requests[0].options.signal.aborted, true);
  resolve(reply('Late answer')); await pending;
  assert.match(content(ui), /Message canceled/);
  assert.equal(controls(ui)[0].textContent, 'Retry');
  assert.equal(ui.input.value, 'Help');
  assert.equal(ui.open.attributes['aria-expanded'], 'false');
  assert.equal(ui.open.focused, true);
});

test('New chat cancels work immediately and clears text and opaque context before the next turn', async () => {
  let resolve; let turn = 0;
  const ui = setup(() => turn++ === 0 ? new Promise(done => { resolve = done; }) : Promise.resolve(reply('New answer')));
  ui.input.value = 'Private fixture question'; const pending = ui.form.fire('submit');
  await ui.clear.fire('click');
  assert.equal(ui.requests[0].options.signal.aborted, true);
  resolve(reply('Late private answer', { context: 'stale-context' })); await pending;
  assert.equal(ui.messages.children.length, 0);
  assert.equal(ui.input.value, '');
  assert.equal(ui.welcome.hidden, false);
  ui.input.value = 'New question'; await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'New question', module: 'crops' });
});

test('validation and allowance failures appear in the conversation, preserve input and retry without duplicate user turns', async () => {
  let turn = 0;
  const ui = setup(async () => turn++ === 0
    ? response(429, { message: 'Try again in a moment.' }) : reply('Successful reply'));
  ui.input.value = 'Question'; await ui.form.fire('submit');
  assert.equal(content(ui), 'Try again in a moment.');
  assert.equal(ui.input.value, 'Question');
  assert.equal(ui.messages.children.length, 2);
  await controls(ui)[0].fire('click');
  assert.equal(content(ui), 'Successful reply');
  assert.equal(ui.messages.children.length, 2);
  assert.equal(ui.input.value, '');
  assert.equal(ui.requests.length, 2);
});

test('unexpected failures do not display provider diagnostics', async () => {
  const ui = setup(async () => response(500, { message: 'private provider diagnostic' }));
  ui.input.value = 'Help'; await ui.form.fire('submit');
  assert.doesNotMatch(content(ui), /private provider/);
  assert.match(content(ui), /could not answer/);
  assert.equal(ui.input.value, 'Help');
});

test('expired sessions erase previous protected replies and context then prevent further requests', async () => {
  let turn = 0;
  const ui = setup(async () => turn++ === 0
    ? reply('Private fixture records', { source: 'records', context: 'private-context' })
    : response(419, { answer: 'should not display' }));
  ui.input.value = 'Farmers'; await ui.form.fire('submit');
  ui.input.value = 'Another report'; await ui.form.fire('submit');
  assert.equal(ui.messages.children.length, 1);
  assert.match(content(ui), /session has ended/);
  assert.doesNotMatch(content(ui), /Private fixture|should not display/);
  assert.equal(ui.input.value, '');
  assert.equal(ui.send.disabled, true);
  ui.input.value = 'After expiry'; await ui.form.fire('submit');
  assert.equal(ui.requests.length, 2);
});

test('permission denials clear private context but keep a veterinary account able to ask an allowed question', async () => {
  let turn = 0;
  const ui = setup(async () => {
    turn++;
    if (turn === 1) return reply('Private service total', { source: 'records', context: 'service-context' });
    if (turn === 2) return response(403, { message: 'Your account can use Animal Health only.' });
    return reply('Permitted Animal Health total', { source: 'records', context: 'new-service-context' });
  });
  ui.input.value = 'Animal Health'; await ui.form.fire('submit');
  ui.input.value = 'Farmers'; await ui.form.fire('submit');
  assert.equal(ui.messages.children.length, 1);
  assert.equal(content(ui), 'Your account can use Animal Health only.');
  assert.equal(ui.input.value, 'Farmers');
  assert.equal(ui.send.disabled, false);
  ui.input.value = 'Animal Health again'; await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Animal Health again', module: 'crops' });
});

test('page exit clears the conversation and cancels pending work before browser restoration', async () => {
  let resolve;
  const ui = setup(() => new Promise(done => { resolve = done; }));
  ui.input.value = 'Private sample'; const pending = ui.form.fire('submit');
  ui.listeners.pagehide();
  assert.equal(ui.requests[0].options.signal.aborted, true);
  assert.equal(ui.messages.children.length, 0);
  assert.equal(ui.input.value, '');
  resolve(reply('Late private answer')); await pending;
  assert.equal(ui.messages.children.length, 0);
});

test('Enter sends, Shift+Enter allows a newline, and IME composition never submits', async () => {
  const ui = setup(async () => reply('Reply'));
  ui.input.value = 'First question';
  await ui.input.fire('keydown', { key: 'Enter', shiftKey: true });
  assert.equal(ui.requests.length, 0);
  assert.equal(ui.input.lastEvent.prevented, undefined);
  await ui.input.fire('compositionstart');
  await ui.input.fire('keydown', { key: 'Enter' });
  await ui.input.fire('compositionend');
  await ui.input.fire('keydown', { key: 'Enter', isComposing: true });
  await ui.input.fire('keydown', { key: 'Enter', keyCode: 229 });
  assert.equal(ui.requests.length, 0);
  await ui.input.fire('keydown', { key: 'Enter' });
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.input.lastEvent.prevented, true);
});

test('new replies preserve an earlier reading position and offer a keyboard-accessible jump', async () => {
  const ui = setup(async () => reply('New reply'));
  ui.body.scrollHeight = 1000; ui.body.clientHeight = 300; ui.body.scrollTop = 80;
  ui.input.value = 'Report'; await ui.form.fire('submit');
  assert.equal(ui.body.scrollTop, 80);
  assert.equal(ui.jump.hidden, false);
  await ui.jump.fire('click');
  assert.equal(ui.body.scrollTop, 1000);
  assert.equal(ui.jump.hidden, true);
  assert.equal(ui.body.focused, true);
});

test('malformed replies, unknown sources, and oversized context never become trusted conversation state', async () => {
  for (const data of [
    { answer: 'Unverified source', source: 'provider-records', context: null },
    { answer: 'Missing context', source: 'help' },
    { answer: 'Unsafe context', source: 'help', context: 'x'.repeat(4097) },
    { answer: 'Bad context', source: 'help', context: 'token with spaces' },
    { answer: 'x'.repeat(6001), source: 'records', context: null }
  ]) {
    const ui = setup(async () => response(200, data));
    ui.input.value = 'Question'; await ui.form.fire('submit');
    assert.match(content(ui), /could not be verified/);
    assert.equal(ui.input.value, 'Question');
  }
});

test('null years are omitted and application source/scope labels are escaped text', async () => {
  const ui = setup(async () => reply('Reply', { source: 'records', year: null, scope: '<script>Scope</script>' }));
  ui.input.value = 'Farmer totals'; await ui.form.fire('submit');
  const metadata = ui.messages.children.at(-1).children[2];
  assert.equal(metadata.textContent, '<script>Scope</script>');
  assert.doesNotMatch(metadata.textContent, /null|Year/);
  assert.equal(metadata.innerHTML, undefined);
});

test('empty and oversized messages are explained without making a request', async () => {
  const ui = setup(async () => reply('Never requested'));
  for (const value of ['', 'x'.repeat(801)]) {
    ui.input.value = value; await ui.form.fire('submit');
    assert.match(content(ui), /within 800 characters/);
    assert.equal(ui.input.value, value);
    assert.equal(ui.input.attributes['aria-invalid'], 'true');
  }
  assert.equal(ui.requests.length, 0);
});

test('composer validation clears on typing, New chat and successful replies', async () => {
  const ui = setup(async () => reply('Successful answer'));
  await ui.form.fire('submit');
  assert.equal(ui.input.attributes['aria-invalid'], 'true');
  ui.input.value = 'Corrected question'; await ui.input.fire('input');
  assert.equal(ui.input.attributes['aria-invalid'], undefined);
  ui.input.value = ''; await ui.form.fire('submit');
  await ui.clear.fire('click');
  assert.equal(ui.input.attributes['aria-invalid'], undefined);
  ui.input.setAttribute('aria-invalid', 'true');
  ui.input.value = 'Programmatically corrected question'; await ui.form.fire('submit');
  assert.equal(ui.input.attributes['aria-invalid'], undefined);
  assert.equal(content(ui), 'Successful answer');
});

test('a typed draft during a request survives its response, and New chat leaves no persisted storage', async () => {
  let resolve;
  const ui = setup(() => new Promise(done => { resolve = done; }));
  ui.input.value = 'Submitted'; const pending = ui.form.fire('submit');
  ui.input.value = 'Next unsent question';
  resolve(reply('Reply', { context: 'short-lived-context' })); await pending;
  assert.equal(ui.input.value, 'Next unsent question');
  assert.doesNotMatch(source, /localStorage|sessionStorage|indexedDB/);
});

test('provider timeout restores send and leaves a retryable question', async () => {
  const ui = setup((url, options) => new Promise((resolve, reject) => {
    options.signal.addEventListener('abort', () => reject(Object.assign(new Error('Canceled'), { name: 'AbortError' })));
  }));
  ui.input.value = 'Question'; const pending = ui.form.fire('submit');
  ui.timeout(); await pending;
  assert.equal(ui.send.disabled, false);
  assert.equal(ui.input.value, 'Question');
  assert.match(content(ui), /took too long/);
  assert.equal(controls(ui)[0].textContent, 'Retry');
});

test('unified dialog has no mode or year selectors and uses the shared visual system', () => {
  const blade = fs.readFileSync(path.join(__dirname, '../../resources/views/partials/office-assistant.blade.php'), 'utf8');
  const css = fs.readFileSync(path.join(__dirname, '../../public/css/office-assistant.css'), 'utf8');
  assert.doesNotMatch(blade, /data-assistant-mode|data-assistant-year|<select|type="number"|Records &amp; reports/);
  assert.match(blade, /New chat/);
  assert.match(blade, /data-assistant-form novalidate/);
  assert.match(blade, /rows="2" required/);
  assert.match(blade, /aria-relevant="additions text"/);
  assert.match(blade, /Office records stay in AgriLGU/);
  assert.match(css, /var\(--ui-primary\)/);
  assert.match(css, /prefers-reduced-motion/);
  assert.match(css, /min-height: 44px/);
  assert.doesNotMatch(css, /100vw/);
  assert.match(css, /width: calc\(100% - 16px\)/);
});
