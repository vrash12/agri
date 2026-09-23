(() => {
    'use strict';

    const root = document.querySelector('[data-welcome-slideshow]');
    if (!root) return;
    const slides = Array.from(root.querySelectorAll('[data-scene]'));
    const controls = root.querySelector('[data-gallery-controls]');
    const previous = root.querySelector('[data-gallery-previous]');
    const next = root.querySelector('[data-gallery-next]');
    const status = root.querySelector('[data-gallery-status]');
    let current = 0;

    root.querySelectorAll('[data-collage-photo]').forEach(photo => {
        const image = photo.querySelector('img');
        const fallback = photo.querySelector('[data-image-fallback]');
        if (!image || !fallback) return;
        const fail = () => { image.hidden = true; fallback.hidden = false; };
        image.addEventListener('error', fail);
        if (image.hasAttribute('src') && image.complete && image.naturalWidth === 0) fail();
    });
    if (!slides.length) return;

    const show = (index, announce = true) => {
        current = (index + slides.length) % slides.length;
        // Unvisited collages keep deferred URLs until the visitor requests them.
        slides[current].querySelectorAll('img').forEach(image => {
            image.loading = 'eager';
            const source = image.getAttribute('data-src');
            if (source) {
                image.setAttribute('src', source);
                image.removeAttribute('data-src');
            }
        });
        slides.forEach((slide, i) => { slide.hidden = i !== current; });
        if (announce && status) {
            status.textContent = `Collage ${slides[current].getAttribute('aria-label') || `${current + 1} of ${slides.length}`}`;
        }
    };

    show(current, false);
    if (slides.length < 2 || !previous || !next) return;

    previous.addEventListener('click', () => show(current - 1));
    next.addEventListener('click', () => show(current + 1));
    [previous, next].forEach(button => button.addEventListener('keydown', event => {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) return;
        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
        event.preventDefault();
        show(current + (event.key === 'ArrowRight' ? 1 : -1));
    }));
    if (controls) controls.hidden = false;
})();
