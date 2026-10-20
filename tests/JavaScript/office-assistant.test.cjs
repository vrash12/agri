const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../public/js/office-assistant.js'), 'utf8');

class Element {
  constructor(tagName = 'div') {
    this.tagName = tagName; this.listeners = {}; this.children = []; this.attributes = {};
    this.hidden = false; this.value = ''; this.textContent = ''; this.disabled = false; this.className = ''; this.dataset = {};
    this.scrollTop = 0; this.scrollHeight = 400; this.clientHeight = 400;
    const classes = () => new Set(this.className.split(/\s+/).filter(Boolean));
    this.classList = {
      add: (...names) => { this.className = [...new Set([...classes(), ...names])].join(' '); },
      remove: (...names) => { this.className = [...classes()].filter(name => !names.includes(name)).join(' '); },
      toggle: (name, enabled) => enabled ? this.classList.add(name) : this.classList.remove(name)
    };
  }
  get disabled() { return this._disabled; }
  set disabled(value) {
    this._disabled = value;
    if (value && this.ownerDocument?.activeElement === this) this.ownerDocument.activeElement = this.ownerDocument.body;
  }
  get childElementCount() { return this.children.length; }
  addEventListener(name, fn) { (this.listeners[name] ||= []).push(fn); }
  setAttribute(name, value) { this.attributes[name] = value; }
  removeAttribute(name) { delete this.attributes[name]; }
  append(...children) { children.forEach(child => { child.parent = this; this.children.push(child); }); }
  replaceChildren(...children) {
    this.children.forEach(child => {
      if (child.contains(this.ownerDocument?.activeElement)) this.ownerDocument.activeElement = this.ownerDocument.body;
      child.parent = null;
    });
    this.children = [];
    this.append(...children);
  }
  remove() { if (this.parent) this.parent.children = this.parent.children.filter(child => child !== this); }
  querySelectorAll(tagName) {
    return this.children.flatMap(child => [
      ...(child.tagName === tagName ? [child] : []), ...child.querySelectorAll(tagName)
    ]);
  }
  contains(node) {
    for (let current = node; current; current = current.parent) if (current === this) return true;
    return false;
  }
  focus() { this.focused = true; if (this.ownerDocument) this.ownerDocument.activeElement = this; }
  showModal() { this.open = true; }
  close() { this.open = false; return this.fire('close'); }
  fire(name, properties = {}) {
    const event = { preventDefault() { this.prevented = true; }, ...properties };
    this.lastEvent = event;
    return Promise.all((this.listeners[name] || []).map(fn => fn(event)));
  }
}

function setup(fetcher, { savedGuide = null, storageBlocked = false } = {}) {
  const names = ['dialog', 'open', 'input', 'form', 'send', 'clear', 'welcome',
    'messages', 'body', 'status', 'count', 'close', 'jump', 'cooldown', 'guide-picker', 'launcher-name', 'portrait'];
  const elements = Object.fromEntries(names.map(name => [name, new Element()]));
  elements.jump.hidden = true;
  const guideButtons = [['ka-berto', 'Ka Berto'], ['ka-kaloy', 'Ka Kaloy'], ['aling-maya', 'Aling Maya'], ['kiko', 'Kiko']].map(([id, name]) => {
    const button = new Element('button');
    button.dataset = { assistantGuide: id, name, avatar: '/images/mascots/' + id + '-avatar-v1.webp',
      portrait: '/images/mascots/' + id + '-portrait-v1.webp', alt: name + ' mascot' };
    return button;
  });
  const avatars = [new Element('img'), new Element('img')], guideNames = [new Element('h2'), new Element('span')];
  const root = { dataset: { endpoint: '/assistant/message', module: 'crops', guide: 'ka-berto' },
    querySelector: selector => elements[selector.slice(16, -1)],
    querySelectorAll: selector => selector === '[data-assistant-guide]' ? guideButtons
      : selector === '[data-assistant-avatar]' ? avatars : selector === '[data-assistant-guide-name]' ? guideNames : [] };
  const document = { body: new Element('body'),
    querySelector: selector => selector === '[data-office-assistant]' ? root : { content: 'synthetic-csrf' },
    createElement(tagName) { const element = new Element(tagName); element.ownerDocument = document; return element; } };
  document.activeElement = document.body;
  [...Object.values(elements), ...guideButtons, ...avatars, ...guideNames].forEach(element => { element.ownerDocument = document; });
  const listeners = {};
  let now = Date.UTC(2026, 9, 11, 2), nextTimer = 0;
  const timers = new Map();
  class ClockDate extends Date { static now() { return now; } }
  const stored = new Map(savedGuide === null ? [] : [['eagrilgu.assistant-guide.v1', savedGuide]]), storageWrites = [];
  const window = { localStorage: {
      getItem(key) { if (storageBlocked) throw new Error('Storage blocked'); return stored.get(key) ?? null; },
      setItem(key, value) { if (storageBlocked) throw new Error('Storage blocked'); stored.set(key, value); storageWrites.push([key, value]); }
    },
    setTimeout(fn, delay) { const id = ++nextTimer; timers.set(id, { fn, at: now + delay, delay }); return id; },
    clearTimeout(id) { timers.delete(id); },
    addEventListener(name, fn) { listeners[name] = fn; },
    location: { href: 'https://example.test/farmers', origin: 'https://example.test' } };
  const requests = [];
  const fetch = async (url, options) => { requests.push({ url, options }); return fetcher(url, options); };
  vm.runInNewContext(source, { document, window, fetch, AbortController, URL, Date: ClockDate });
  return { ...elements, document, requests, listeners, root, guideButtons, avatars, guideNames, stored, storageWrites, now: () => now,
    timeout() { const timer = [...timers.entries()].find(([, entry]) => entry.delay === 30000); if (timer) timer[1].fn(); },
    advance(milliseconds) {
      const until = now + milliseconds;
      let next;
      while ((next = [...timers.entries()].sort((a, b) => a[1].at - b[1].at)[0]) && next[1].at <= until) {
        timers.delete(next[0]); now = next[1].at; next[1].fn();
      }
      now = until;
    } };
}
function response(status, data, headers = {}) {
  return { status, ok: status === 200, redirected: false, json: async () => data,
    headers: { get: name => Object.entries(headers).find(([key]) => key.toLowerCase() === name.toLowerCase())?.[1] ?? null } };
}
function reply(answer, options = {}) { return response(200, { answer, source: 'help', context: null, ...options }); }
function payload(ui, index = -1) { return JSON.parse(ui.requests.at(index).options.body); }
function content(ui, index = -1) { return ui.messages.children.at(index).children[1].textContent; }
function controls(ui, index = -1) { return ui.messages.children.at(index).children.at(-1).children; }
function followups(ui, index = -1) {
  return ui.messages.children.at(index).children.find(child => child.className === 'assistant-followups')?.children || [];
}

test('follow-up suggestions stage an editable message with its reply reference and never fetch on selection', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('Crop guide.', { context: 'crop-reference',
    suggestions: [{ label: 'Show the steps', message: 'Show me the steps' }] }) : reply('Crop steps.'));
  await ui.open.fire('click'); ui.input.value = 'How do I draw crop areas?'; await ui.form.fire('submit');
  const suggestion = followups(ui)[0];
  assert.equal(suggestion.disabled, false);
  await suggestion.fire('click');
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.input.value, 'Show me the steps');
  assert.equal(ui.document.activeElement, ui.input);
  assert.match(ui.status.textContent, /Edit it or send/);
  await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Show me the steps', module: 'crops', guide: 'ka-berto', context: 'crop-reference' });
  assert.equal(ui.input.value, '');
});

test('selecting a follow-up cannot overwrite an unsent draft', async () => {
  const ui = setup(async () => reply('Guide.', { context: 'guide-reference',
    suggestions: [{ label: 'Make it shorter', message: 'Make it shorter' }] }));
  ui.input.value = 'How do I use this page?'; await ui.form.fire('submit');
  ui.input.value = 'My unfinished question';
  await followups(ui)[0].fire('click');
  assert.equal(ui.input.value, 'My unfinished question');
  assert.match(ui.status.textContent, /Finish or clear your current draft/);
  assert.equal(ui.requests.length, 1);
});

test('a staged follow-up retains its own context after a farmer choice changes the conversation', async () => {
  let turn = 0;
  const ui = setup(async () => {
    if (++turn === 1) return reply('Guide and choice.', { context: 'guide-reference',
      actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }],
      suggestions: [{ label: 'Show the steps', message: 'Show me the steps' }] });
    if (turn === 2) return reply('Farmer summary.', { source: 'records', context: 'farmer-reference' });
    return reply('Original guide steps.');
  });
  ui.input.value = 'Guide'; await ui.form.fire('submit');
  const entry = ui.messages.children.at(-1), choice = entry.querySelectorAll('button')[0];
  await followups(ui)[0].fire('click');
  await choice.fire('click');
  assert.equal(ui.input.value, 'Show me the steps');
  await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Show me the steps', module: 'crops', guide: 'ka-berto', context: 'guide-reference' });
});

test('editing a staged follow-up releases its captured reference and uses the current conversation', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('Guide and choice.', { context: 'guide-reference',
    actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }],
    suggestions: [{ label: 'Show the steps', message: 'Show me the steps' }] })
    : reply('Farmer summary.', { source: 'records', context: 'farmer-reference' }));
  ui.input.value = 'Guide'; await ui.form.fire('submit');
  const choice = ui.messages.children.at(-1).querySelectorAll('button')[0];
  await followups(ui)[0].fire('click'); await choice.fire('click');
  ui.input.value = 'His assistance'; await ui.input.fire('input'); await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'His assistance', module: 'crops', guide: 'ka-berto', context: 'farmer-reference' });
});

test('a staged null-reference follow-up never borrows a newer farmer context', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('Guide and choice.', { context: null,
    actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }],
    suggestions: [{ label: 'Use this page', message: 'How do I use this page?' }] })
    : reply('Farmer summary.', { source: 'records', context: 'farmer-reference' }));
  ui.input.value = 'Guide'; await ui.form.fire('submit');
  const choice = ui.messages.children.at(-1).querySelectorAll('button')[0];
  await followups(ui)[0].fire('click'); await choice.fire('click'); await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'How do I use this page?', module: 'crops', guide: 'ka-berto' });
});

test('old suggestions become inactive on another pending request and stay inactive after recovery', async () => {
  let turn = 0, resolve;
  const ui = setup(() => ++turn === 1 ? Promise.resolve(reply('First guide.', { context: 'first-reference',
    suggestions: [{ label: 'Old steps', message: 'Show me the steps' }] })) : new Promise(done => { resolve = done; }));
  ui.input.value = 'First guide'; await ui.form.fire('submit');
  const old = followups(ui)[0];
  ui.input.value = 'Second guide'; const pending = ui.form.fire('submit');
  assert.equal(old.disabled, true); ui.input.value = ''; await old.fire('click');
  assert.equal(ui.input.value, '');
  resolve(reply('Second guide.', { context: 'second-reference',
    suggestions: [{ label: 'New example', message: 'Give me an example' }] })); await pending;
  assert.equal(old.disabled, true); await old.fire('click');
  assert.equal(ui.input.value, '');
  assert.equal(followups(ui)[0].disabled, false);
  await followups(ui)[0].fire('click');
  assert.equal(ui.input.value, 'Give me an example');
});

test('follow-ups are bounded plain text and malformed suggestions never render', async () => {
  const cases = [
    { label: '', message: 'Show me the steps' }, { label: 'x'.repeat(81), message: 'Steps' },
    { label: 'Steps', message: 'x'.repeat(201) }, { label: 'Steps', message: '' },
    { label: 'Steps\n', message: 'Steps' }, { label: 'Steps', message: 'Steps\u0000' },
    { label: 'Steps', message: 'Steps\u0085' }, { label: 'Steps', message: 2026 }, null
  ];
  for (const suggestion of cases) {
    const ui = setup(async () => reply('Guide.', { suggestions: [suggestion] }));
    ui.input.value = 'Guide'; await ui.form.fire('submit');
    assert.equal(followups(ui).length, 0, JSON.stringify(suggestion));
  }
  const ui = setup(async () => reply('Guide.', { suggestions: Array.from({ length: 5 }, () => ({
    label: '<script>Steps</script>', message: 'Show me the steps'
  })) }));
  ui.input.value = 'Guide'; await ui.form.fire('submit');
  assert.equal(followups(ui).length, 3);
  assert.equal(followups(ui)[0].textContent, '<script>Steps</script>');
  assert.equal(followups(ui)[0].innerHTML, undefined);
  assert.equal(ui.messages.children.at(-1).querySelectorAll('script').length, 0);
});

test('cooldown and busy states prevent staging suggestions even through a direct event', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('Guide.', { context: 'guide-reference',
    suggestions: [{ label: 'Steps', message: 'Show me the steps' }] })
    : response(429, { message: 'Please wait.' }, { 'Retry-After': '3' }));
  ui.input.value = 'Guide'; await ui.form.fire('submit'); const suggestion = followups(ui)[0];
  ui.input.value = 'Another question'; await ui.form.fire('submit'); ui.input.value = '';
  await suggestion.fire('click');
  assert.equal(ui.input.value, ''); assert.equal(ui.requests.length, 2);
  ui.advance(3000); await suggestion.fire('click');
  assert.equal(ui.input.value, ''); assert.equal(suggestion.disabled, true);
});

test('New chat clears a staged reference and old suggestions cannot recreate it', async () => {
  const ui = setup(async () => reply('Guide.', { context: 'guide-reference',
    suggestions: [{ label: 'Steps', message: 'Show me the steps' }] }));
  ui.input.value = 'Guide'; await ui.form.fire('submit'); const old = followups(ui)[0];
  await old.fire('click'); await ui.clear.fire('click'); await old.fire('click');
  assert.equal(ui.input.value, '');
  ui.input.value = 'Show me the steps'; await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Show me the steps', module: 'crops', guide: 'ka-berto' });
});

test('session expiry erases a staged follow-up and blocks detached suggestion controls', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('Guide and choice.', { context: 'guide-reference',
    actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }],
    suggestions: [{ label: 'Steps', message: 'Show me the steps' }] }) : response(419, {}));
  ui.input.value = 'Guide'; await ui.form.fire('submit');
  const choice = ui.messages.children.at(-1).querySelectorAll('button')[0], old = followups(ui)[0];
  await old.fire('click'); await choice.fire('click'); await old.fire('click');
  assert.equal(ui.input.value, ''); assert.equal(ui.send.disabled, true); assert.equal(ui.requests.length, 2);
  assert.equal(ui.messages.children.length, 1);
});

test('application-owned conversational replies display and retain only an opaque reference', async () => {
  const ui = setup(async () => reply('I am the AgriLGU Assistant.', { source: 'conversation', context: 'identity-reference' }));
  ui.input.value = 'Who are you?'; await ui.form.fire('submit');
  assert.equal(content(ui), 'I am the AgriLGU Assistant.');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'Ka Berto');
  assert.equal(ui.input.value, '');
  ui.input.value = 'Tell me more'; await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'Tell me more', module: 'crops', guide: 'ka-berto', context: 'identity-reference' });
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
  assert.deepEqual(payload(ui, 0), { message: 'Find farmer AGRI-F-000012', module: 'crops', guide: 'ka-berto' });
  assert.deepEqual(payload(ui), { message: 'How do I draw a crop area?', module: 'crops', guide: 'ka-berto', context: 'opaque-record-reference' });
  assert.doesNotMatch(ui.requests[1].options.body, /Farmer Fixture|AGRI-F|history|transcript|mode|year|farmer_id/);
  assert.equal(content(ui), '<script>untrusted()</script>');
  assert.equal(ui.messages.children.at(-1).innerHTML, undefined);
  assert.equal(ui.messages.children[1].children[0].textContent, 'Ka Berto · Office records');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'Ka Berto · System guide');
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
  assert.deepEqual(payload(ui), { message: 'Continue with my choice.', module: 'crops', guide: 'ka-berto',
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
  assert.equal(content(ui), 'Checking…');
  const pendingContent = ui.messages.children.at(-1).children[1];
  assert.match(pendingContent.className, /assistant-thinking/);
  assert.equal(pendingContent.attributes.role, 'status');
  assert.equal(pendingContent.attributes['aria-live'], 'polite');
  assert.equal(pendingContent.attributes['aria-label'], 'Ka Berto is checking your question');
  await ui.close.fire('click');
  assert.equal(ui.requests[0].options.signal.aborted, true);
  resolve(reply('Late answer')); await pending;
  assert.match(content(ui), /Message canceled/);
  assert.equal(controls(ui)[0].textContent, 'Retry');
  assert.equal(ui.input.value, 'Help');
  assert.equal(ui.open.attributes['aria-expanded'], 'false');
  assert.equal(ui.open.focused, true);
});

test('thinking indicator is replaced by a verified reply and clears its live status', async () => {
  let resolve;
  const ui = setup(() => new Promise(done => { resolve = done; }));
  ui.input.value = 'Summarize farmers';
  const pending = ui.form.fire('submit');
  const replyEntry = ui.messages.children.at(-1);
  const thinking = replyEntry.children[1];
  assert.match(thinking.className, /assistant-thinking/);
  resolve(reply('Registered farmers: 12')); await pending;
  const completed = replyEntry.children[1];
  assert.equal(completed.textContent, 'Registered farmers: 12');
  assert.doesNotMatch(completed.className, /assistant-thinking/);
  assert.equal(completed.attributes.role, undefined);
  assert.equal(completed.attributes['aria-live'], undefined);
  assert.equal(completed.attributes['aria-label'], undefined);
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
  assert.deepEqual(payload(ui), { message: 'New question', module: 'crops', guide: 'ka-berto' });
});

test('validation and allowance failures appear in the conversation, preserve input and retry without duplicate user turns', async () => {
  let turn = 0;
  const ui = setup(async () => turn++ === 0
    ? response(429, { message: 'Try again in a moment.' }) : reply('Successful reply'));
  ui.input.value = 'Question'; await ui.form.fire('submit');
  assert.equal(content(ui), 'Try again in a moment.');
  assert.equal(ui.input.value, 'Question');
  assert.equal(ui.messages.children.length, 2);
  assert.equal(controls(ui)[0].disabled, true);
  ui.advance(60000);
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

test('Retry-After pauses Send, choices and Retry while keeping the draft and captured opaque reference', async () => {
  let turn = 0;
  const ui = setup(async () => {
    if (++turn === 1) return reply('Choose a farmer.', { source: 'clarification', context: 'choice-context',
      actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }] });
    if (turn === 2) return response(429, { message: 'Too many messages. Please wait.' }, { 'Retry-After': '3' });
    return reply('Completed reply');
  });
  ui.input.value = 'Find farmer Synthetic'; await ui.form.fire('submit');
  const choice = controls(ui)[0];
  ui.input.value = 'Our assistance'; await ui.form.fire('submit');
  const retry = controls(ui)[0];
  assert.equal(ui.send.disabled, true);
  assert.equal(choice.disabled, true);
  assert.equal(retry.disabled, true);
  assert.equal(ui.input.disabled, false);
  assert.equal(ui.cooldown.textContent, 'Try again in 3 seconds.');
  assert.equal(ui.status.textContent, 'Too many messages. Please wait. Try again in 3 seconds.');
  const initialAnnouncement = ui.status.textContent;
  ui.advance(1000);
  assert.equal(ui.status.textContent, initialAnnouncement);
  ui.input.value = 'Next unsent draft';
  await retry.fire('click'); await choice.fire('click'); await ui.form.fire('submit');
  assert.equal(ui.requests.length, 2);
  ui.advance(1000);
  assert.equal(ui.cooldown.textContent, 'Try again in 1 second.');
  assert.equal(ui.send.disabled, true);
  ui.advance(1000);
  assert.equal(ui.cooldown.hidden, true);
  assert.equal(ui.send.disabled, false);
  assert.equal(choice.disabled, false);
  assert.equal(retry.disabled, false);
  assert.equal(ui.status.textContent, 'You can try sending again.');
  await retry.fire('click');
  assert.deepEqual(payload(ui), { message: 'Our assistance', module: 'crops', guide: 'ka-berto', context: 'choice-context' });
  assert.equal(ui.input.value, 'Next unsent draft');
  assert.equal(ui.messages.children.length, 4);
});

test('New chat, Close and page exit clear the conversation without bypassing an active cooldown', async () => {
  const ui = setup(async () => response(429, { message: 'Please wait.' }, { 'Retry-After': '4' }));
  await ui.open.fire('click'); ui.input.value = 'Question'; await ui.form.fire('submit');
  await ui.clear.fire('click');
  assert.equal(ui.messages.children.length, 0);
  assert.equal(ui.send.disabled, true);
  assert.equal(ui.cooldown.hidden, false);
  ui.input.value = 'New question'; await ui.form.fire('submit');
  await ui.close.fire('click'); await ui.open.fire('click');
  await ui.form.fire('submit');
  ui.listeners.pagehide(); await ui.open.fire('click');
  ui.input.value = 'After page restoration'; await ui.form.fire('submit');
  assert.equal(ui.requests.length, 1);
  assert.equal(ui.send.disabled, true);
  assert.equal(ui.input.value, 'After page restoration');
  ui.advance(4000);
  assert.equal(ui.send.disabled, false);
});

test('Retry-After accepts a future HTTP date and safely bounds absent, invalid and distant headers', async () => {
  const cases = [
    [null, 60], ['not a date', 60], ['x'.repeat(81), 60], ['9999999999', 300],
    ['0', 1], ['-5', 60], ['Sun, 11 Oct 2026 02:00:02 GMT', 2],
    ['Sat, 10 Oct 2026 02:00:00 GMT', 1], ['Sun, 11 Oct 2099 02:00:00 GMT', 300]
  ];
  for (const [header, seconds] of cases) {
    const ui = setup(async () => response(429, { message: 'Please wait.' }, { 'Retry-After': header }));
    ui.input.value = 'Question'; await ui.form.fire('submit');
    assert.equal(ui.cooldown.textContent, 'Try again in ' + seconds + ' second' + (seconds === 1 ? '.' : 's.'), String(header));
    ui.advance((seconds - 1) * 1000);
    assert.equal(ui.send.disabled, true, String(header));
    ui.advance(1000);
    assert.equal(ui.send.disabled, false, String(header));
  }
});

test('a focused farmer choice restores composer focus and preserves a draft when the response arrives', async () => {
  let turn = 0, resolve;
  const ui = setup(() => ++turn === 1 ? Promise.resolve(reply('Choose a farmer.', {
    source: 'clarification', context: 'choice-context', actions: [{ label: 'Synthetic farmer', choice: 'farmer-token' }]
  })) : new Promise(done => { resolve = done; }));
  await ui.open.fire('click'); ui.input.value = 'Find farmer Synthetic'; await ui.form.fire('submit');
  const choice = controls(ui)[0]; choice.focus();
  const pending = choice.fire('click');
  assert.equal(ui.document.activeElement, ui.document.body);
  ui.input.value = 'Next unsent draft';
  resolve(reply('Permitted farmer summary', { source: 'records' })); await pending;
  assert.equal(ui.document.activeElement, ui.input);
  assert.equal(ui.input.value, 'Next unsent draft');
});

test('submitting from focused Send restores composer focus without replacing the next unsent draft', async () => {
  let resolve;
  const ui = setup(() => new Promise(done => { resolve = done; }));
  await ui.open.fire('click'); ui.input.value = 'Submitted question'; ui.send.focus();
  const pending = ui.form.fire('submit');
  assert.equal(ui.document.activeElement, ui.document.body);
  ui.input.value = 'Next unsent draft';
  resolve(reply('Completed reply')); await pending;
  assert.equal(ui.document.activeElement, ui.input);
  assert.equal(ui.input.value, 'Next unsent draft');
  assert.equal(ui.send.disabled, false);
});

test('a Send completion respects a deliberate focus move and Enter never takes over subsequent focus', async () => {
  for (const fromSend of [true, false]) {
    let resolve;
    const ui = setup(() => new Promise(done => { resolve = done; }));
    await ui.open.fire('click'); ui.input.value = 'Question';
    if (fromSend) ui.send.focus();
    const pending = fromSend ? ui.form.fire('submit') : ui.input.fire('keydown', { key: 'Enter' });
    ui.body.focus();
    resolve(reply('Completed reply')); await pending;
    assert.equal(ui.document.activeElement, ui.body);
  }
});

test('reply completion never steals focus after a deliberate move to another conversation control', async () => {
  let turn = 0, resolve;
  const ui = setup(() => ++turn === 1 ? Promise.resolve(response(503, { message: 'Try again.' }))
    : new Promise(done => { resolve = done; }));
  await ui.open.fire('click'); ui.input.value = 'Question'; await ui.form.fire('submit');
  const retry = controls(ui)[0]; retry.focus();
  const pending = retry.fire('click');
  ui.body.focus();
  resolve(reply('Successful reply')); await pending;
  assert.equal(ui.document.activeElement, ui.body);
  assert.equal(ui.messages.children.length, 2);
});

test('a focused Retry restores composer focus after another safe failure without adding a user turn', async () => {
  const ui = setup(async () => response(503, { message: 'Try again shortly.' }));
  await ui.open.fire('click'); ui.input.value = 'Question'; await ui.form.fire('submit');
  const retry = controls(ui)[0]; retry.focus();
  await retry.fire('click');
  assert.equal(ui.document.activeElement, ui.input);
  assert.equal(content(ui), 'Try again shortly.');
  assert.equal(controls(ui)[0].disabled, false);
  assert.equal(ui.messages.children.length, 2);
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
  assert.deepEqual(payload(ui), { message: 'Animal Health again', module: 'crops', guide: 'ka-berto' });
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
  assert.equal(ui.storageWrites.length, 0);
  assert.doesNotMatch(source, /sessionStorage|indexedDB/);
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

test('switching guides keeps the conversation, draft and reference and stores only an appearance key', async () => {
  const ui = setup(async () => reply('Local office summary.', { source: 'records', context: 'protected-reference' }));
  await ui.open.fire('click');
  ui.input.value = 'Show our farmers'; await ui.form.fire('submit');
  const oldReply = ui.messages.children.at(-1);
  ui.input.value = 'My unfinished follow-up';
  for (const button of ui.guideButtons) {
    await button.fire('click');
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.input.value, 'My unfinished follow-up');
    assert.equal(ui.messages.children.length, 2);
    assert.equal(ui.dialog.open, true);
    assert.equal(ui.guideNames[0].textContent, button.dataset.name);
    assert.equal(ui.avatars[0].src, button.dataset.avatar);
    assert.equal(ui.portrait.src, button.dataset.portrait);
    assert.equal(button.attributes['aria-pressed'], 'true');
    assert.equal(ui.guideButtons.filter(item => item.attributes['aria-pressed'] === 'true').length, 1);
  }
  await ui.form.fire('submit');
  assert.deepEqual(payload(ui), { message: 'My unfinished follow-up', module: 'crops', guide: 'kiko', context: 'protected-reference' });
  assert.equal(oldReply.children[0].textContent, 'Ka Berto · Office records');
  assert.equal(oldReply.attributes['data-guide'], 'ka-berto');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'Kiko · Office records');
  assert.equal(ui.messages.children.at(-1).attributes['data-guide'], 'kiko');
  assert.deepEqual(ui.storageWrites, ui.guideButtons.map(button => ['eagrilgu.assistant-guide.v1', button.dataset.assistantGuide]));
  assert.equal(ui.stored.size, 1);
  await ui.clear.fire('click');
  assert.equal(ui.messages.children.length, 0);
  assert.equal(ui.guideNames[0].textContent, 'Kiko');
  assert.equal(ui.stored.get('eagrilgu.assistant-guide.v1'), 'kiko');
});

test('saved guide preferences are allowlisted and unavailable browser storage never breaks chat', async () => {
  for (const savedGuide of ['ka-kaloy', 'aling-maya', 'kiko', '../../private', 'https://other.test/image.png', '<script>']) {
    const ui = setup(async () => reply('Ready.'), { savedGuide });
    const expected = ui.guideButtons.some(button => button.dataset.assistantGuide === savedGuide) ? savedGuide : 'ka-berto';
    assert.equal(ui.root.dataset.guide, expected);
    assert.equal(ui.requests.length, 0);
    ui.input.value = 'Who are you?'; await ui.form.fire('submit');
    assert.equal(payload(ui).guide, expected);
    assert.equal(ui.storageWrites.length, 0);
    assert.doesNotMatch(ui.avatars[0].src, /other\.test|private|script/);
  }
  const ui = setup(async () => reply('Ready.'), { storageBlocked: true });
  await ui.guideButtons[2].fire('click');
  assert.equal(ui.guideNames[0].textContent, 'Aling Maya');
  ui.input.value = 'Hello'; await ui.form.fire('submit');
  assert.equal(payload(ui).guide, 'aling-maya');
  assert.equal(content(ui), 'Ready.');
});

test('a pending turn keeps its guide and Retry retains that guide after an appearance switch', async () => {
  let resolve, turn = 0;
  const ui = setup(() => ++turn === 1 ? new Promise(done => { resolve = done; }) : Promise.resolve(reply('Verified reply.')));
  await ui.guideButtons[1].fire('click');
  ui.input.value = 'How do I use this page?';
  const pending = ui.form.fire('submit');
  assert.ok(ui.guideButtons.every(button => button.disabled));
  await ui.guideButtons[3].fire('click');
  assert.equal(ui.root.dataset.guide, 'ka-kaloy');
  assert.equal(ui.messages.children.at(-1).children[1].attributes['aria-label'], 'Ka Kaloy is checking your question');
  resolve(response(503, { message: 'Try again shortly.' })); await pending;
  await ui.guideButtons[2].fire('click');
  assert.equal(ui.root.dataset.guide, 'aling-maya');
  await controls(ui)[0].fire('click');
  assert.equal(payload(ui).guide, 'ka-kaloy');
  assert.equal(ui.messages.children.at(-1).children[0].textContent, 'Ka Kaloy · System guide');
  assert.equal(ui.messages.children.length, 2);
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
  assert.match(blade, /officeAssistantPrivacy officeAssistantCount officeAssistantCooldown/);
  assert.match(blade, /data-assistant-cooldown hidden/);
  assert.match(blade, /data-assistant-guide-picker/);
  assert.doesNotMatch(blade, /assistant-cooldown[^>]*aria-live/);
  assert.match(css, /var\(--ui-primary\)/);
  assert.match(css, /prefers-reduced-motion/);
  assert.match(css, /assistant-thinking-dots/);
  assert.match(css, /assistant-message\.is-pending \.assistant-thinking/);
  assert.match(css, /min-height: 44px/);
  assert.doesNotMatch(css, /100vw/);
  assert.match(css, /width: calc\(100% - 16px\)/);
});


test('numbered steps render as semantic lists, including legacy inline answers', async () => {
  for (const answer of [
    '1. Inspect the soil.\n2. Plan irrigation.\n3. Monitor the crop.',
    '1) Inspect the soil. 2) Plan irrigation. 3) Monitor the crop.',
    '1. Inspect the soil.\n\n2. Plan irrigation.\n\n3. Monitor the crop.'
  ]) {
    const ui = setup(async () => reply(answer));
    ui.input.value = 'Explain irrigation step by step'; await ui.form.fire('submit');
    const lists = ui.messages.children.at(-1).querySelectorAll('ol');
    assert.equal(lists.length, 1);
    assert.equal(lists[0].attributes.start, '1');
    assert.deepEqual(lists[0].children.map(item => item.textContent),
      ['Inspect the soil.', 'Plan irrigation.', 'Monitor the crop.']);
    assert.ok(lists[0].children.every(item => item.tagName === 'li'));
  }
});

test('lists preserve introductions, conclusions, indented continuation and malicious text as text', async () => {
  const ui = setup(async () => reply('Start here:\n\n1. Inspect <img src=x onerror=alert(1)>\n  Look at the roots.\n2. Ask an officer.\n\nThis is general guidance.'));
  ui.input.value = 'Show the steps'; await ui.form.fire('submit');
  const answer = ui.messages.children.at(-1).children[1];
  assert.equal(answer.children[0].textContent, 'Start here:');
  assert.equal(answer.children.at(-1).textContent, 'This is general guidance.');
  assert.equal(answer.querySelectorAll('li')[0].textContent, 'Inspect <img src=x onerror=alert(1)>\nLook at the roots.');
  assert.equal(answer.querySelectorAll('img').length, 0);
  assert.equal(answer.querySelectorAll('script').length, 0);
});

test('decimals, years and isolated inline numbers stay ordinary text', async () => {
  const answer = 'Area: 1.25 hectares. Yield: 2.5 tonnes. Year 2026. Version 1. Review the record.';
  const ui = setup(async () => reply(answer));
  ui.input.value = 'Show harvests'; await ui.form.fire('submit');
  assert.equal(content(ui), answer);
  assert.equal(ui.messages.children.at(-1).querySelectorAll('ol').length, 0);
});

test('a numbered reply can be followed by a new request and a clean retry', async () => {
  let turn = 0;
  const ui = setup(async () => ++turn === 1 ? reply('1. Inspect.\n2. Review.') : response(503, {}));
  ui.input.value = 'Explain soil health'; await ui.form.fire('submit');
  ui.input.value = 'More'; await ui.form.fire('submit');
  await controls(ui)[0].fire('click');
  assert.equal(ui.requests.length, 3);
  assert.equal(ui.messages.children.length, 4);
  assert.equal(ui.messages.children.at(-1).querySelectorAll('ol').length, 0);
});
