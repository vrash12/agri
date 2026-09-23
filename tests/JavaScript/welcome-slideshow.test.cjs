const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../public/js/welcome-slideshow.js'), 'utf8');

class Element {
    constructor(options = {}) {
        Object.assign(this, { hidden: false, textContent: '', attributes: {}, listeners: {}, children: {} }, options);
    }

    addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
    removeAttribute(name) { delete this.attributes[name]; }
    querySelector(selector) { const child = this.children[selector]; return Array.isArray(child) ? child[0] : child || null; }
    querySelectorAll(selector) { const child = this.children[selector]; return Array.isArray(child) ? child : child ? [child] : []; }

    dispatch(name, properties = {}) {
        const event = { target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...properties };
        (this.listeners[name] || []).forEach(callback => callback(event));
        return event;
    }
}

function setup({ slideCount = 5, failedImage = -1, noRoot = false, missingControls = false, missingButton = '', missingStatus = false } = {}) {
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
            attributes: { 'aria-label': `${index + 1} of ${slideCount}: Scene ${index + 1}` },
            children: { img: group.map(photo => photo.querySelector('img')) },
        });
    });
    const controls = new Element({ hidden: true });
    const previous = new Element();
    const next = new Element();
    const status = new Element();
    root.children = {
        '[data-gallery-controls]': missingControls ? null : controls,
        '[data-gallery-previous]': missingButton === 'previous' ? null : previous,
        '[data-gallery-next]': missingButton === 'next' ? null : next,
        '[data-gallery-status]': missingStatus ? null : status,
        '[data-scene]': slides,
        '[data-collage-photo]': photos,
    };
    const document = new Element({ children: { '[data-welcome-slideshow]': noRoot ? null : root } });
    const window = {
        setTimeout: () => assert.fail('the manual gallery must not schedule automatic navigation'),
        setInterval: () => assert.fail('the manual gallery must not schedule automatic navigation'),
    };
    vm.runInNewContext(source, { document, window });
    const visible = () => slides.flatMap((slide, index) => slide.hidden ? [] : [index]);
    return { root, slides, photos, controls, previous, next, status, document, visible };
}

test('arrow buttons move one collage at a time, wrap both ways, and announce the visible scene', () => {
    const view = setup();
    assert.equal(view.controls.hidden, false);
    assert.deepEqual(view.visible(), [0]);
    assert.equal(view.status.textContent, '', 'initial content must not trigger a navigation announcement');
    view.previous.dispatch('click');
    assert.deepEqual(view.visible(), [4]);
    assert.equal(view.status.textContent, 'Collage 5 of 5: Scene 5');
    for (let i = 0; i < 10; i++) {
        view.next.dispatch('click');
        assert.deepEqual(view.visible(), [i % 5]);
        assert.equal(view.status.textContent, `Collage ${i % 5 + 1} of 5: Scene ${i % 5 + 1}`);
    }
});

test('all twenty distinct images hydrate only when their collage is visited', () => {
    const view = setup();
    assert.equal(view.photos.length, 20);
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 4);
    for (const photo of view.photos.slice(4)) {
        assert.equal(photo.querySelector('img').hasAttribute('src'), false);
        assert.equal(photo.querySelector('[data-image-fallback]').hidden, true, 'an intentionally deferred image is not a failure');
    }
    view.previous.dispatch('click');
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 8);
    assert.deepEqual(view.slides.map(slide => slide.querySelector('img').loading), ['eager', 'lazy', 'lazy', 'lazy', 'eager']);
    for (const image of view.slides[4].querySelectorAll('img')) {
        assert.equal(image.hasAttribute('src'), true);
        assert.equal(image.hasAttribute('data-src'), false);
    }
    view.next.dispatch('click');
    assert.equal(view.photos.filter(photo => photo.querySelector('img').hasAttribute('src')).length, 8, 'returning to a scene does not request unrelated images');
    for (let i = 0; i < 5; i++) view.next.dispatch('click');
    const urls = view.photos.map(photo => photo.querySelector('img').getAttribute('src'));
    assert.equal(new Set(urls).size, 20);
    assert.ok(urls.every(Boolean));
});

test('left and right keys on either arrow navigate without moving keyboard focus', () => {
    const view = setup();
    const left = view.next.dispatch('keydown', { key: 'ArrowLeft' });
    assert.equal(left.defaultPrevented, true);
    assert.deepEqual(view.visible(), [4]);
    const right = view.previous.dispatch('keydown', { key: 'ArrowRight' });
    assert.equal(right.defaultPrevented, true);
    assert.deepEqual(view.visible(), [0]);
    for (const modifier of ['altKey', 'ctrlKey', 'metaKey', 'shiftKey']) {
        const event = view.next.dispatch('keydown', { key: 'ArrowRight', [modifier]: true });
        assert.equal(event.defaultPrevented, false);
        assert.deepEqual(view.visible(), [0]);
    }
    for (const key of ['Tab', 'Enter', ' ', 'Home', 'End', 'ArrowDown', 'ArrowUp']) {
        const event = view.next.dispatch('keydown', { key });
        assert.equal(event.defaultPrevented, false, 'native button and page keyboard behavior remains available');
    }
    view.root.dispatch('keydown', { key: 'ArrowRight' });
    assert.deepEqual(view.visible(), [0], 'other gallery content must not intercept page arrow keys');
});

test('a failed supporting photo shows its own fallback without hiding the other three', () => {
    const view = setup();
    view.photos[2].querySelector('img').dispatch('error');
    assert.equal(view.photos[2].querySelector('[data-image-fallback]').hidden, false);
    assert.equal(view.photos[2].querySelector('img').hidden, true);
    for (const index of [0, 1, 3]) assert.equal(view.photos[index].querySelector('img').hidden, false);
    view.next.dispatch('click');
    assert.deepEqual(view.visible(), [1]);
    view.photos[4].querySelector('img').dispatch('error');
    assert.equal(view.photos[4].querySelector('[data-image-fallback]').hidden, false);
    assert.equal(view.photos[4].querySelector('img').hidden, true);
});

test('already failed first-scene images show fallbacks without stopping navigation', () => {
    const view = setup({ failedImage: 0 });
    assert.ok(view.photos.slice(0, 4).every(photo => photo.querySelector('img').hidden));
    assert.ok(view.photos.slice(0, 4).every(photo => !photo.querySelector('[data-image-fallback]').hidden));
    view.next.dispatch('click');
    assert.deepEqual(view.visible(), [1]);
});

test('gallery remains still after hover, focus, or document visibility changes', () => {
    const view = setup();
    view.next.dispatch('click');
    for (const name of ['mouseenter', 'mouseleave', 'focusin']) view.root.dispatch(name);
    view.document.dispatch('visibilitychange');
    assert.deepEqual(view.visible(), [1]);
    assert.equal(view.status.textContent, 'Collage 2 of 5: Scene 2');
});

test('missing, empty, single-scene and incomplete galleries leave controls hidden', () => {
    for (const options of [{ noRoot: true }, { slideCount: 0 }, { slideCount: 1 }, { missingButton: 'previous' }, { missingButton: 'next' }]) {
        const view = setup(options);
        assert.equal(view.controls.hidden, true);
        view.previous.dispatch('click');
        view.next.dispatch('click');
        assert.deepEqual(view.visible(), options.slideCount === 0 ? [] : [0]);
        assert.equal(view.status.textContent, '');
    }
});

test('navigation still works without the optional wrapper or live status', () => {
    for (const options of [{ missingControls: true }, { missingStatus: true }]) {
        const view = setup(options);
        view.next.dispatch('click');
        assert.deepEqual(view.visible(), [1]);
    }
    const view = setup();
    view.slides[1].removeAttribute('aria-label');
    view.next.dispatch('click');
    assert.equal(view.status.textContent, 'Collage 2 of 5');
});
