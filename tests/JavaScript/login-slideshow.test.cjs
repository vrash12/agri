const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/login-slideshow.js'), 'utf8');

class Element {
    constructor(options = {}) {
        Object.assign(this, { hidden: false, textContent: '', attributes: {}, listeners: {}, children: {} }, options);
    }

    addEventListener(name, callback) {
        (this.listeners[name] ||= []).push(callback);
    }

    dispatch(name, event = {}) {
        (this.listeners[name] || []).forEach(callback => callback(event));
    }

    setAttribute(name, value) { this.attributes[name] = value; }
    querySelector(selector) { return this.children[selector] || null; }
}

function setup({ reducedMotion = false, slideCount = 8, failedImage = -1, noRoot = false } = {}) {
    const slides = Array.from({ length: slideCount }, (_, index) => new Element({
        hidden: index !== 0,
        children: {
            img: new Element({ complete: index === failedImage, naturalWidth: index === failedImage ? 0 : 1200 }),
            '[data-image-fallback]': new Element({ hidden: true }),
        },
    }));
    const root = new Element({ children: {
    } });
    root.querySelectorAll = () => slides;
    const document = new Element({ children: { '[data-login-slideshow]': noRoot ? null : root } });
    const motion = new Element({ matches: reducedMotion });
    const timers = new Map();
    let timerId = 0;
    const window = {
        matchMedia: () => motion,
        clearTimeout: id => timers.delete(id),
        setTimeout: (callback, delay) => {
            assert.equal(delay, 7000);
            timers.set(++timerId, callback);
            return timerId;
        },
    };
    vm.runInNewContext(source, { document, window });
    const tick = () => {
        assert.equal(timers.size, 1, 'one rotation must be scheduled');
        const [id, callback] = timers.entries().next().value;
        timers.delete(id);
        callback();
    };
    const visible = () => slides.flatMap((slide, index) => slide.hidden ? [] : [index]);
    return { slides, root, document, motion, timers, tick, visible };
}

test('cycles through all eight photos and wraps without any controls', () => {
    const view = setup();
    for (let i = 1; i <= 16; i++) {
        view.tick();
        assert.deepEqual(view.visible(), [i % 8]);
    }
});

test('hidden tabs suspend playback and visible tabs resume with one timer', () => {
    const view = setup();
    view.tick();
    view.document.hidden = true;
    view.document.dispatch('visibilitychange');
    assert.equal(view.timers.size, 0);
    view.document.hidden = false;
    view.document.dispatch('visibilitychange');
    view.document.dispatch('visibilitychange');
    view.tick();
    assert.deepEqual(view.visible(), [2]);
});

test('reduced motion keeps the current photo still and reacts to preference changes', () => {
    const view = setup({ reducedMotion: true });
    assert.equal(view.timers.size, 0);
    assert.deepEqual(view.visible(), [0]);
    view.motion.matches = false;
    view.motion.dispatch('change');
    view.tick();
    view.motion.matches = true;
    view.motion.dispatch('change');
    assert.equal(view.timers.size, 0);
    assert.deepEqual(view.visible(), [1]);
});

test('failed images reveal fallback text and do not stop the cycle', () => {
    const view = setup({ failedImage: 0 });
    assert.equal(view.slides[0].querySelector('img').hidden, true);
    assert.equal(view.slides[0].querySelector('[data-image-fallback]').hidden, false);
    view.slides[1].querySelector('img').dispatch('error');
    view.tick();
    assert.deepEqual(view.visible(), [1]);
    assert.equal(view.slides[1].querySelector('[data-image-fallback]').hidden, false);
});

test('missing and single-slide regions do not start timers', () => {
    assert.equal(setup({ noRoot: true }).timers.size, 0);
    assert.equal(setup({ slideCount: 1 }).timers.size, 0);
});
