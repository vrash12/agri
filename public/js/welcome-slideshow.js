(() => {
    'use strict';

    const root = document.querySelector('[data-welcome-slideshow]');
    if (!root) return;
    const slides = Array.from(root.querySelectorAll('[data-scene]'));
    const selectors = Array.from(root.querySelectorAll('[data-scene-select]'));
    const controls = root.querySelector('[data-gallery-controls]');
    const play = root.querySelector('[data-gallery-play]');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let paused = false;
    let hovering = false;
    let timer;
    let pointerPauseIntent = null;

    root.querySelectorAll('[data-collage-photo]').forEach(photo => {
        const image = photo.querySelector('img');
        const fallback = photo.querySelector('[data-image-fallback]');
        if (!image || !fallback) return;
        const fail = () => { image.hidden = true; fallback.hidden = false; };
        image.addEventListener('error', fail);
        if (image.hasAttribute('src') && image.complete && image.naturalWidth === 0) fail();
    });
    if (slides.length < 2 || !controls || !play) return;
    controls.hidden = false;

    const loadScene = index => {
        slides[index].querySelectorAll('img').forEach(image => {
            image.loading = 'eager';
            const source = image.getAttribute('data-src');
            if (source) {
                image.setAttribute('src', source);
                image.removeAttribute('data-src');
            }
        });
    };

    const schedule = () => {
        window.clearTimeout(timer);
        play.disabled = motion.matches;
        play.textContent = motion.matches ? 'Motion reduced' : paused ? 'Play slideshow' : 'Pause slideshow';
        if (motion.matches || document.hidden || paused || hovering) return;
        // Only the current and upcoming collage get image URLs; the rest stay deferred.
        loadScene((current + 1) % slides.length);
        timer = window.setTimeout(() => show((current + 1) % slides.length), 8000);
    };
    const show = index => {
        current = index;
        loadScene(current);
        slides.forEach((slide, i) => { slide.hidden = i !== current; });
        selectors.forEach((button, i) => button.setAttribute('aria-pressed', String(i === current)));
        schedule();
    };
    selectors.forEach((button, index) => button.addEventListener('click', () => {
        paused = true;
        show(index);
    }));
    // Pointer focus arrives before click; retain the action the visitor clicked.
    play.addEventListener('pointerdown', () => { pointerPauseIntent = !paused; });
    play.addEventListener('pointercancel', () => { pointerPauseIntent = null; });
    play.addEventListener('blur', () => { pointerPauseIntent = null; });
    play.addEventListener('click', () => {
        paused = pointerPauseIntent === null ? !paused : pointerPauseIntent;
        pointerPauseIntent = null;
        schedule();
    });
    // Keyboard users retain the chosen collage until they explicitly resume playback.
    root.addEventListener('focusin', event => {
        if (!root.contains(event.relatedTarget)) { paused = true; schedule(); }
    });
    root.addEventListener('mouseenter', () => { hovering = true; schedule(); });
    root.addEventListener('mouseleave', () => { hovering = false; schedule(); });
    document.addEventListener('visibilitychange', schedule);
    if (motion.addEventListener) motion.addEventListener('change', schedule);
    else motion.addListener(schedule);
    loadScene(current);
    schedule();
})();
