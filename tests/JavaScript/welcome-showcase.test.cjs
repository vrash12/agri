const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/welcome-showcase.js'), 'utf8');

class Element {
    constructor(attributes = {}) {
        this.attributes = { ...attributes };
        this.listeners = {};
        this.children = {};
        this.textContent = '';
        this.isConnected = true;
        this.hidden = false;
        this.disabled = false;
    }
    addEventListener(type, listener) { (this.listeners[type] ||= []).push(listener); }
    dispatch(type, properties = {}) {
        const event = { target: this, button: 0, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...properties };
        (this.listeners[type] || []).forEach(listener => listener(event));
        return event;
    }
    getAttribute(name) { return this.attributes[name] ?? null; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
    removeAttribute(name) { delete this.attributes[name]; }
    querySelector(selector) { return this.children[selector] || null; }
    focus(options) { this.focused = true; this.focusOptions = options; }
    cloneNode() { const clone = new Element(this.attributes); clone.replace = this.replace; return clone; }
    replaceWith(replacement) { this.isConnected = false; this.replace(replacement); }
    getBoundingClientRect() { return { left: 100, top: 100, right: 1000, bottom: 700 }; }
}

function setup({ supported = true, noDialog = false, missing = '', failShow = false, hrefs, duplicate = false, caption = null } = {}) {
    const nodes = {
        title: new Element(), description: new Element(), status: new Element(), counter: new Element(),
        close: new Element(), previous: new Element(), next: new Element(), original: new Element(),
    };
    const dialog = new Element();
    dialog.open = false;
    dialog.showModal = supported ? function () { if (failShow) throw new Error('Unsupported current state'); this.open = true; } : undefined;
    dialog.close = function () { this.open = false; this.dispatch('close'); };
    for (const key of ['description', 'status', 'counter', 'close', 'previous', 'next', 'original']) {
        dialog.children[`[data-preview-${key}]`] = missing === key ? null : nodes[key];
    }
    const images = [];
    const image = new Element({ id: 'welcome-preview-image', class: 'showcase-full-image', width: '960', height: '600' });
    image.replace = replacement => { view.image = replacement; images.push(replacement); };
    const links = (hrefs || ['/images/showcase/people-01.svg?v=1', '/images/showcase/maps-02.svg', '/images/showcase/operations-04.svg']).map((href, index) => new Element({
        href, 'data-preview-title': caption || `Feature ${index + 1}`, 'data-preview-description': `Fictional example ${index + 1}`,
    }));
    if (duplicate) links.push(new Element({ href: '/images/showcase/people-01.svg?v=2', 'data-preview-title': 'Featured duplicate' }));
    const document = {
        getElementById: id => ({ 'welcome-preview-dialog': noDialog ? null : dialog, 'welcome-preview-title': missing === 'title' ? null : nodes.title, 'welcome-preview-image': missing === 'image' ? null : image })[id],
        querySelectorAll: selector => selector === '[data-welcome-preview]' ? links : [],
    };
    const view = { ...nodes, dialog, image, images, links };
    const window = {
        location: { origin: 'https://agri-lgu.online', href: 'https://agri-lgu.online/' },
        setTimeout: () => assert.fail('previews must not schedule automatic navigation'),
        setInterval: () => assert.fail('previews must not automatically advance'),
        fetch: () => assert.fail('the viewer must not fetch operational data or call providers'),
    };
    vm.runInNewContext(source, { document, window, URL });
    return view;
}

test('sample opens as a native modal with text labels, sample announcement and authored image dimensions', () => {
    const view = setup();
    const event = view.links[0].dispatch('click');
    assert.equal(event.defaultPrevented, true);
    assert.equal(view.dialog.open, true);
    assert.equal(view.close.focused, true);
    assert.equal(view.title.textContent, 'Feature 1');
    assert.equal(view.description.textContent, 'Fictional example 1');
    assert.equal(view.counter.textContent, '1 of 3');
    assert.equal(view.previous.disabled, true);
    assert.equal(view.next.disabled, false);
    assert.equal(view.image.getAttribute('class'), 'showcase-full-image');
    assert.equal(view.image.getAttribute('width'), '960');
    assert.equal(view.image.getAttribute('height'), '600');
    assert.equal(view.image.getAttribute('src'), 'https://agri-lgu.online/images/showcase/people-01.svg?v=1');
    assert.match(view.image.alt, /fictional sample data/);
    assert.equal(view.image.hidden, true);
    assert.equal(view.dialog.getAttribute('aria-busy'), 'true');
    view.image.dispatch('load');
    assert.equal(view.image.hidden, false);
    assert.equal(view.dialog.getAttribute('aria-busy'), 'false');
    assert.match(view.status.textContent, /All records and figures are fictional/);
    assert.match(view.status.textContent, /Feature 1, 1 of 3/);
});

test('manual navigation updates description, full-image link and bounded counter without leaving focus on a disabled button', () => {
    const view = setup();
    view.links[0].dispatch('click');
    view.previous.dispatch('click');
    assert.equal(view.counter.textContent, '1 of 3');
    view.next.dispatch('click');
    assert.equal(view.counter.textContent, '2 of 3');
    assert.equal(view.title.textContent, 'Feature 2');
    assert.equal(view.description.textContent, 'Fictional example 2');
    view.next.dispatch('click');
    assert.equal(view.counter.textContent, '3 of 3');
    assert.equal(view.next.disabled, true);
    assert.equal(view.previous.focused, true);
    assert.equal(view.original.getAttribute('href'), 'https://agri-lgu.online/images/showcase/operations-04.svg');
    view.next.dispatch('click');
    assert.equal(view.counter.textContent, '3 of 3');
    view.previous.dispatch('click');
    view.previous.dispatch('click');
    assert.equal(view.counter.textContent, '1 of 3');
    assert.equal(view.previous.disabled, true);
    assert.equal(view.next.focused, true);
});

test('failed images keep the description and recovery link available while stale loads cannot replace the current result', () => {
    const view = setup();
    view.links[0].dispatch('click');
    const first = view.image;
    view.next.dispatch('click');
    const second = view.image;
    second.dispatch('error');
    assert.equal(second.hidden, true);
    assert.match(view.status.textContent, /could not load/);
    assert.equal(view.description.textContent, 'Fictional example 2');
    assert.equal(view.dialog.getAttribute('aria-busy'), 'false');
    first.dispatch('load');
    assert.equal(second.hidden, true);
    assert.match(view.status.textContent, /could not load/);
    view.next.dispatch('click');
    const third = view.image;
    third.dispatch('load');
    second.dispatch('error');
    assert.equal(third.hidden, false);
    assert.match(view.status.textContent, /preview loaded/);
});

test('close button and native Escape closure return focus to the exact initiating link, including duplicated featured previews', () => {
    const view = setup({ duplicate: true });
    view.links[3].dispatch('click');
    assert.equal(view.counter.textContent, '1 of 3', 'duplicate images do not inflate the unique sample count');
    view.next.dispatch('click');
    view.close.dispatch('click');
    assert.equal(view.dialog.open, false);
    assert.equal(view.links[3].focused, true);
    assert.equal(view.links[3].focusOptions.preventScroll, true);
    assert.equal(view.links[0].focused, undefined);
    view.links[1].dispatch('click');
    const lateImage = view.image;
    view.dialog.close(); // Native Escape dispatches the same close event.
    lateImage.dispatch('load');
    assert.equal(view.status.textContent, '');
    assert.equal(view.links[1].focused, true);
    const count = view.images.length;
    view.next.dispatch('click');
    assert.equal(view.images.length, count, 'closed previews do not start new loads');
});

test('backdrop click closes only outside dialog bounds; content, padding and keyboard events do not dismiss it', () => {
    const view = setup();
    view.links[0].dispatch('click');
    view.dialog.dispatch('click', { target: view.title, clientX: 500, clientY: 400 });
    view.dialog.dispatch('click', { clientX: 110, clientY: 110 });
    view.dialog.dispatch('click');
    assert.equal(view.dialog.open, true);
    view.dialog.dispatch('click', { clientX: 50, clientY: 300 });
    assert.equal(view.dialog.open, false);
    assert.equal(view.links[0].focused, true);
});

test('modified, middle, already-handled, target and download clicks retain their ordinary browser behavior', () => {
    const view = setup();
    for (const properties of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
        const event = view.links[0].dispatch('click', properties);
        assert.equal(view.dialog.open, false);
        assert.equal(event.defaultPrevented, Boolean(properties.defaultPrevented));
    }
    view.links[0].target = '_blank';
    assert.equal(view.links[0].dispatch('click').defaultPrevented, false);
    view.links[1].setAttribute('download', 'sample.svg');
    assert.equal(view.links[1].dispatch('click').defaultPrevented, false);
    assert.equal(view.images.length, 0);
});

test('unsupported or incomplete dialogs and modal failures preserve working image-link fallbacks', () => {
    for (const options of [{ supported: false }, { noDialog: true }, { failShow: true }, ...['title', 'image', 'description', 'status', 'counter', 'close', 'previous', 'next'].map(missing => ({ missing }))]) {
        const view = setup(options);
        assert.equal(view.links[0].dispatch('click').defaultPrevented, false);
        assert.equal(view.dialog.open, false);
        assert.equal(view.images.length, 0);
    }
    const optional = setup({ missing: 'original' });
    assert.equal(optional.links[0].dispatch('click').defaultPrevented, true);
});

test('only fixed same-origin sample assets are enhanced; private, external, executable and malformed URLs remain untouched', () => {
    const hrefs = [
        'https://example.test/images/showcase/people-01.svg', 'javascript:alert(1)', '//other.example/images/showcase/people-01.svg',
        '/farmers/1', '/images/showcase/people-05.svg', '/images/showcase/people-01.svg#fragment',
        '/images/showcase/people-01.svg/extra', '/images/showcase/people-01.svg%2Fextra',
        'https://user:password@agri-lgu.online/images/showcase/people-01.svg', 'http://[invalid',
    ];
    const view = setup({ hrefs });
    for (const link of view.links) assert.equal(link.dispatch('click').defaultPrevented, false);
    assert.equal(view.images.length, 0);
    assert.equal(view.dialog.open, false);
});

test('text-only captions are not interpreted as HTML and removed opening links are not focused on close', () => {
    const caption = '<img src=x onerror="alert(1)">';
    const view = setup({ caption });
    view.links[0].isConnected = false;
    view.links[0].dispatch('click');
    assert.equal(view.title.textContent, caption);
    view.close.dispatch('click');
    assert.equal(view.links[0].focused, undefined);
    assert.equal(Object.hasOwn(view.title, 'innerHTML'), false);
    assert.equal(Object.hasOwn(view.description, 'innerHTML'), false);
});
