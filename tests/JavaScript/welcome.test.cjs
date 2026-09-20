const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/welcome.js'), 'utf8');

class Element {
    constructor(attributes = {}) {
        this.attributes = attributes;
        this.listeners = {};
        this.children = {};
        this.classes = new Set();
        this.classList = {
            add: name => this.classes.add(name),
            remove: name => this.classes.delete(name),
            toggle: (name, active) => active ? this.classes.add(name) : this.classes.delete(name),
        };
    }
    addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
    dispatch(name, properties = {}) { (this.listeners[name] || []).forEach(callback => callback({ target: this, button: 0, ...properties })); }
    getAttribute(name) { return this.attributes[name] ?? null; }
    hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    querySelector(selector) { return this.children[selector] || null; }
    closest(selector) { return selector === 'a' && this.hasAttribute('href') ? this : null; }
    focus(options) { this.focused = true; this.focusOptions = options; }
    getBoundingClientRect() { return { top: this.top || 0 }; }
}

function setup({ reducedMotion = false, observers = true, animationSupport = true, legacyMedia = false, menu = true } = {}) {
    const navigation = new Element();
    const toggle = new Element({ 'aria-expanded': 'false' });
    const sections = Object.fromEntries(['services', 'system', 'initiatives', 'visit', 'top'].map(id => [id, new Element()]));
    const links = Object.fromEntries(Object.keys(sections).map(id => [id, new Element({ href: `#${id}` })]));
    for (const [id, link] of Object.entries(links)) navigation.children[`a[href="#${id}"]`] = link;
    const reveals = [new Element(), new Element()];
    const animated = [];
    if (animationSupport) reveals.forEach(element => {
        element.animate = (keyframes, options) => {
            let reject;
            const animation = {
                keyframes, options, cancelled: false,
                finished: new Promise((resolve, failure) => { reject = failure; }),
                cancel() { this.cancelled = true; reject(new Error('Animation cancelled')); },
            };
            animated.push(animation);
            return animation;
        };
    });
    const document = new Element();
    document.body = new Element();
    document.querySelector = selector => selector === '.welcome-menu' && menu ? toggle : null;
    document.getElementById = id => id === 'welcome-navigation' && menu ? navigation : sections[id] || null;
    document.querySelectorAll = selector => selector === '[data-welcome-reveal]' ? reveals : [];
    const motion = new Element();
    motion.matches = reducedMotion;
    const desktop = new Element();
    if (legacyMedia) [motion, desktop].forEach(media => {
        media.addListener = callback => Element.prototype.addEventListener.call(media, 'change', callback);
        media.addEventListener = undefined;
    });
    const instances = [];
    const window = { matchMedia: query => query.includes('reduced-motion') ? motion : desktop };
    if (observers) window.IntersectionObserver = class {
        constructor(callback) { this.callback = callback; this.observed = new Set(); instances.push(this); }
        observe(element) { this.observed.add(element); }
        unobserve(element) { this.observed.delete(element); }
        emit(entries) { this.callback(entries.filter(entry => this.observed.has(entry.target))); }
    };
    vm.runInNewContext(source, { document, window });
    return { document, toggle, navigation, sections, links, reveals, animated, motion, desktop, instances };
}

test('mobile menu retains toggle, Escape focus return, link close and desktop reset', () => {
    const view = setup();
    assert.equal(view.toggle.hidden, false);
    view.toggle.dispatch('click');
    assert.equal(view.toggle.getAttribute('aria-expanded'), 'true');
    assert.equal(view.navigation.classes.has('is-open'), true);
    view.document.dispatch('keydown', { key: 'Escape' });
    assert.equal(view.toggle.focused, true);
    assert.equal(view.toggle.getAttribute('aria-expanded'), 'false');
    view.toggle.dispatch('click');
    view.navigation.dispatch('click', { target: view.links.services });
    assert.equal(view.navigation.classes.has('is-open'), false);
    view.toggle.dispatch('click');
    view.desktop.dispatch('change');
    assert.equal(view.toggle.getAttribute('aria-expanded'), 'false');
});

test('all fragment shortcuts focus their destination without cancelling native hash navigation', () => {
    const view = setup();
    for (const id of ['services', 'visit', 'top']) {
        view.document.dispatch('click', { target: view.links[id], preventDefault: () => assert.fail('Native navigation must remain enabled') });
        assert.equal(view.sections[id].focused, true);
        assert.equal(view.sections[id].focusOptions.preventScroll, true);
        assert.equal(view.sections[id].getAttribute('tabindex'), '-1');
    }
    view.sections.system.setAttribute('tabindex', '0');
    view.document.dispatch('click', { target: view.links.system });
    assert.equal(view.sections.system.getAttribute('tabindex'), '0');
});

test('modified, external, malformed and missing destinations do not steal focus or throw', () => {
    const view = setup();
    for (const event of [{ ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { button: 1 }, { defaultPrevented: true }]) {
        view.document.dispatch('click', { target: view.links.services, ...event });
    }
    for (const attributes of [{ href: '/farmer-portal/login' }, { href: '#' }, { href: '#%zz' }, { href: '#missing' }, { href: '#services', download: '' }]) {
        view.document.dispatch('click', { target: new Element(attributes) });
    }
    const newTab = new Element({ href: '#services' });
    newTab.target = '_blank';
    view.document.dispatch('click', { target: newTab });
    assert.equal(view.sections.services.focused, undefined);
});

test('observed sections expose one current navigation location, then clear it outside those sections', () => {
    const view = setup();
    const observer = view.instances[0];
    assert.equal(observer.observed.size, 3);
    observer.emit([{ target: view.sections.services, isIntersecting: true }]);
    assert.equal(view.links.services.getAttribute('aria-current'), 'location');
    view.sections.services.top = -300;
    view.sections.system.top = 100;
    observer.emit([{ target: view.sections.system, isIntersecting: true }]);
    assert.equal(view.links.services.hasAttribute('aria-current'), false);
    assert.equal(view.links.system.getAttribute('aria-current'), 'location');
    observer.emit([{ target: view.sections.services, isIntersecting: false }, { target: view.sections.system, isIntersecting: false }]);
    assert.equal(Object.values(view.links).some(link => link.hasAttribute('aria-current')), false);
});

test('entry movement runs once and reduced motion changes cancel active movement and skip new entries', () => {
    const view = setup();
    const observer = view.instances[1];
    observer.emit([{ target: view.reveals[0], isIntersecting: false }]);
    assert.equal(view.animated.length, 0);
    observer.emit([{ target: view.reveals[0], isIntersecting: true }]);
    assert.equal(view.animated.length, 1);
    assert.ok(view.animated[0].keyframes.every(frame => frame.opacity > 0));
    assert.ok(view.animated[0].options.duration < 500);
    observer.emit([{ target: view.reveals[0], isIntersecting: true }]);
    assert.equal(view.animated.length, 1);
    view.motion.matches = true;
    view.motion.dispatch('change');
    assert.equal(view.animated[0].cancelled, true);
    observer.emit([{ target: view.reveals[1], isIntersecting: true }]);
    assert.equal(view.animated.length, 1);
});

test('initial reduced motion is respected and legacy preference updates work for later entries', () => {
    const view = setup({ reducedMotion: true, legacyMedia: true });
    const observer = view.instances[1];
    observer.emit([{ target: view.reveals[0], isIntersecting: true }]);
    assert.equal(view.animated.length, 0);
    view.motion.matches = false;
    view.motion.dispatch('change');
    observer.emit([{ target: view.reveals[1], isIntersecting: true }]);
    assert.equal(view.animated.length, 1);
    view.motion.matches = true;
    view.motion.dispatch('change');
    assert.equal(view.animated[0].cancelled, true);
});

test('navigation and content remain usable without observers, animations or a mobile menu', () => {
    const basic = setup({ observers: false, menu: false });
    basic.document.dispatch('click', { target: basic.links.visit });
    assert.equal(basic.sections.visit.focused, true);
    assert.equal(basic.animated.length, 0);
    const staticView = setup({ animationSupport: false });
    staticView.instances[1].emit([{ target: staticView.reveals[0], isIntersecting: true }]);
    assert.equal(staticView.animated.length, 0);
    assert.equal(staticView.reveals[0].hidden, undefined);
});
