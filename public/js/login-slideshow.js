(() => {
    'use strict';

    const root = document.querySelector('[data-login-slideshow]');
    if (!root) return;
    const slides = Array.from(root.querySelectorAll('[data-slide]'));
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let timer;

    slides.forEach(slide => {
        const image = slide.querySelector('img');
        const fallback = slide.querySelector('[data-image-fallback]');
        if (!image || !fallback) return;
        const showFallback = () => {
            image.hidden = true;
            fallback.hidden = false;
        };
        image.addEventListener('error', showFallback);
        if (image.complete && image.naturalWidth === 0) showFallback();
    });

    if (slides.length < 2) return;
    const schedule = () => {
        window.clearTimeout(timer);
        if (motion.matches || document.hidden) return;
        // Load only the upcoming photo ahead of its turn to avoid a blank transition.
        const upcomingImage = slides[(index + 1) % slides.length].querySelector('img');
        if (upcomingImage) upcomingImage.loading = 'eager';
        timer = window.setTimeout(() => {
            index = (index + 1) % slides.length;
            slides.forEach((slide, slideIndex) => { slide.hidden = slideIndex !== index; });
            schedule();
        }, 7000);
    };

    document.addEventListener('visibilitychange', schedule);
    if (motion.addEventListener) motion.addEventListener('change', schedule);
    else motion.addListener(schedule);
    schedule();
})();
