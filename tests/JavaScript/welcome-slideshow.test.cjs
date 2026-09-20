const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/welcome-slideshow.js'), 'utf8');

class Element {
    constructor(options = {}) {
        Object.assign(this, { hidden: false, disabled: false, textContent: '', attributes: {}, listeners: {}, children: {}, parent: null }, options);
    }

    addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
    removeAttribute(name) { delete this.attributes[name]; }
    querySelector(selector) { const child = this.children[selector]; return Array.isArray(child) ? child[0] : child || null; }
    querySelectorAll(selector) { const child = this.children[selector]; return Array.isArray(child) ? child : child ? [child] : []; }
    contains(element) {
        for (let node = element; node; node = node.parent) {
            if (node === this) return true;
        }
        return false;
    }

    dispatch(name, properties = {}) {
        const event = { target: this, relatedTarget: null, ...properties };
        let node = this;
        do {
            (node.listeners[name] || []).forEach(callback => callback(event));
            node = ['click', 'pointerdown', 'focusin'].includes(name) ? node.parent : null;
        } while (node);
    }
}

function setup({ reducedMotion = false, slideCount = 5, failedImage = -1, noRoot = false, missingControls = false, legacyMotion = false } = {}) {
    const root = new Element();
    const photos = [];
    const slides = Array.from({ length: slideCount }, (_, index) => {
        const group = Array.from({ length: 4 }, (_, photoIndex) => new Element({
            children: {
                img: new Element({
                    complete: index !== 0 || index === failedImage,
                    naturalWidth: index === failedImage || index !== 0 ? 0 : 960,
                    loading: index === 0 ? 'eager' : 'lazy',
                    attributes: { [index === 0 ? 'src' : 'data-src']: `/photo-${index}-${photoIndex}.jpg` },
                }),
                '[data-image-fallback]': new Element({ hidden: true }),
            },
        }));
        photos.push(...group);
        return new Element({
            hidden: index !== 0,
            parent: root,
            children: {
                img: group.map(photo => photo.querySelector('img')),
                '[data-image-fallback]': group[0].querySelector('[data-image-fallback]'),
            },
        });
    });
    const selectors = Array.from({ length: slideCount }, (_, index) => new Element({
        parent: root,
        attributes: { 'aria-pressed': String(index === 0) },
    }));
    const controls = new Element({ hidden: true, parent: root });
    const play = new Element({ textContent: 'Pause slideshow', parent: root });
    root.children = { '[data-gallery-controls]': missingControls ? null : controls, '[data-gallery-play]': play,
        '[data-scene]': slides, '[data-scene-select]': selectors, '[data-collage-photo]': photos };
    const document = new Element({ hidden: false, children: { '[data-welcome-slideshow]': noRoot ? null : root } });
    const motion = new Element({ matches: reducedMotion });
    if (legacyMotion) {
        motion.addListener = callback => Element.prototype.addEventListener.call(motion, 'change', callback);
        motion.addEventListener = undefined;
    }
    const timers = new Map();
    let timerId = 0;
    const window = {
        matchMedia: () => motion,
        clearTimeout: id => timers.delete(id),
        setTimeout: (callback, delay) => {
            assert.equal(delay, 8000, 'photographs advance every eight seconds');
            timers.set(++timerId, callback);
            return timerId;
        },
    };
    vm.runInNewContext(source, { document, window });
    const tick = () => {
        assert.equal(timers.size, 1, 'only one rotation is scheduled');
        const [id, callback] = timers.entries().next().value;
        timers.delete(id);
        callback();
    };
    const visible = () => slides.flatMap((slide, index) => slide.hidden ? [] : [index]);
    const chosen = () => selectors.flatMap((button, index) => button.attributes['aria-pressed'] === 'true' ? [index] : []);
    return { root, slides, photos, selectors, controls, play, document, motion, timers, tick, visible, chosen };
}

test('advances every eight seconds, keeps one selected collage, and wraps', () => {
    const view = setup();
    assert.equal(view.controls.hidden, false);
    assert.deepEqual(view.visible(), [0]);
    for (let i = 1; i <= 10; i++) {
        view.tick();
        assert.deepEqual(view.visible(), [i % 5]);
        assert.deepEqual(view.chosen(), [i % 5]);
    }
});

test('preloads the next collage instead of eagerly requesting the whole gallery', () => {
    const view = setup();
    assert.deepEqual(view.slides.map(slide => slide.querySelector('img').loading), ['eager', 'eager', 'lazy', 'lazy', 'lazy']);
    view.tick();
    assert.equal(view.slides[2].querySelector('img').loading, 'eager');
    assert.equal(view.slides[3].querySelector('img').loading, 'lazy');
});

test('twenty distinct images hydrate only for the current, upcoming, or manually selected collage', () => {
    const view = setup();
    assert.equal(view.photos.length, 20);
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 8);
    for (const photo of view.photos.slice(8)) {
        assert.equal(photo.querySelector('img').hasAttribute('src'), false);
        assert.equal(photo.querySelector('[data-image-fallback]').hidden, true, 'an intentionally deferred image is not a failure');
    }
    view.selectors[4].dispatch('click');
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 12);
    assert.ok(view.slides[4].querySelectorAll('img').every(image => image.hasAttribute('src')));
    assert.equal(view.timers.size, 0);
});

test('reduced motion loads just the first four images until a visitor chooses another collage', () => {
    const view = setup({ reducedMotion: true });
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 4);
    view.selectors[3].dispatch('click');
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 8);
    assert.deepEqual(view.visible(), [3]);
});

test('a failed supporting photo shows its own fallback without hiding the other three', () => {
    const view = setup();
    view.photos[2].querySelector('img').dispatch('error');
    assert.equal(view.photos[2].querySelector('[data-image-fallback]').hidden, false);
    assert.equal(view.photos[2].querySelector('img').hidden, true);
    for (const index of [0, 1, 3]) assert.equal(view.photos[index].querySelector('img').hidden, false);
    view.tick();
    assert.deepEqual(view.visible(), [1]);
});

test('pause and resume preserve the current photograph and create only one timer', () => {
    const view = setup();
    view.tick();
    view.play.dispatch('click');
    assert.equal(view.timers.size, 0);
    assert.equal(view.play.textContent, 'Play slideshow');
    assert.deepEqual(view.visible(), [1]);
    view.play.dispatch('click');
    assert.equal(view.play.textContent, 'Pause slideshow');
    view.tick();
    assert.deepEqual(view.visible(), [2]);
});

test('the first pointer click on Pause pauses even when it also moves focus into the gallery', () => {
    const view = setup();
    view.play.dispatch('pointerdown', { pointerType: 'mouse' });
    view.play.dispatch('focusin');
    view.play.dispatch('click', { detail: 1 });
    assert.equal(view.timers.size, 0);
    assert.equal(view.play.textContent, 'Play slideshow');
    assert.deepEqual(view.visible(), [0]);
    view.play.dispatch('pointerdown', { pointerType: 'mouse' });
    view.play.dispatch('click', { detail: 1 });
    view.tick();
    assert.deepEqual(view.visible(), [1]);
});

test('cancelled pointer actions cannot change the later keyboard play action', () => {
    for (const cancellation of ['pointercancel', 'blur']) {
        const view = setup();
        view.play.dispatch('pointerdown', { pointerType: 'touch' });
        view.play.dispatch('focusin');
        view.play.dispatch(cancellation);
        assert.equal(view.timers.size, 0);
        view.play.dispatch('click', { detail: 0 });
        view.tick();
        assert.deepEqual(view.visible(), [1]);
    }
});

test('manual selection pauses until explicitly resumed and marks the selected photograph', () => {
    const view = setup();
    view.selectors[4].dispatch('click');
    assert.deepEqual(view.visible(), [4]);
    assert.deepEqual(view.chosen(), [4]);
    assert.equal(view.timers.size, 0);
    view.root.dispatch('mouseleave');
    view.document.dispatch('visibilitychange');
    assert.equal(view.timers.size, 0, 'background events must not undo a manual choice');
    view.play.dispatch('click');
    view.tick();
    assert.deepEqual(view.visible(), [0]);
});

test('keyboard entry pauses, and moving focus inside does not undo explicit playback', () => {
    const view = setup();
    view.selectors[0].dispatch('focusin');
    assert.equal(view.timers.size, 0);
    assert.equal(view.play.textContent, 'Play slideshow');
    view.play.dispatch('focusin', { relatedTarget: view.selectors[0] });
    view.play.dispatch('click', { detail: 0 });
    assert.equal(view.timers.size, 1);
    view.selectors[1].dispatch('focusin', { relatedTarget: view.play });
    view.tick();
    assert.deepEqual(view.visible(), [1]);
});

test('hover suspension resumes on departure while a deliberate pause persists', () => {
    const view = setup();
    view.root.dispatch('mouseenter');
    assert.equal(view.timers.size, 0);
    view.root.dispatch('mouseleave');
    view.tick();
    view.play.dispatch('click');
    view.root.dispatch('mouseenter');
    view.root.dispatch('mouseleave');
    assert.equal(view.timers.size, 0);
});

test('hidden tabs suspend playback and repeated visibility events leave only one timer', () => {
    const view = setup();
    view.document.hidden = true;
    view.document.dispatch('visibilitychange');
    assert.equal(view.timers.size, 0);
    assert.deepEqual(view.visible(), [0]);
    view.document.hidden = false;
    view.document.dispatch('visibilitychange');
    view.document.dispatch('visibilitychange');
    view.tick();
    assert.deepEqual(view.visible(), [1]);
});

test('reduced motion prevents autoplay, permits choosing a photo, and honors later preference changes', () => {
    const view = setup({ reducedMotion: true });
    assert.equal(view.timers.size, 0);
    assert.equal(view.play.disabled, true);
    assert.equal(view.play.textContent, 'Motion reduced');
    view.motion.matches = false;
    view.motion.dispatch('change');
    assert.equal(view.play.disabled, false);
    view.tick();
    view.motion.matches = true;
    view.motion.dispatch('change');
    assert.equal(view.timers.size, 0);
    assert.deepEqual(view.visible(), [1]);
    view.selectors[3].dispatch('click');
    assert.deepEqual(view.visible(), [3]);
    view.motion.matches = false;
    view.motion.dispatch('change');
    assert.equal(view.timers.size, 0, 'a manual choice remains paused after preferences change');
    assert.equal(view.play.textContent, 'Play slideshow');
});

test('older matchMedia listeners also respond to reduced-motion changes', () => {
    const view = setup({ legacyMotion: true });
    view.motion.matches = true;
    view.motion.dispatch('change');
    assert.equal(view.timers.size, 0);
    assert.equal(view.play.disabled, true);
});

test('failed images expose descriptive fallbacks without stopping the gallery', () => {
    const view = setup({ failedImage: 0 });
    assert.equal(view.slides[0].querySelector('img').hidden, true);
    assert.equal(view.slides[0].querySelector('[data-image-fallback]').hidden, false);
    view.slides[1].querySelector('img').dispatch('error');
    view.tick();
    assert.deepEqual(view.visible(), [1]);
    assert.equal(view.slides[1].querySelector('img').hidden, true);
    assert.equal(view.slides[1].querySelector('[data-image-fallback]').hidden, false);
});

test('missing, empty, single-photo, and incomplete galleries do not start timers', () => {
    for (const options of [{ noRoot: true }, { slideCount: 0 }, { slideCount: 1 }, { missingControls: true }]) {
        const view = setup(options);
        assert.equal(view.timers.size, 0);
        assert.equal(view.controls.hidden, true);
    }
});
