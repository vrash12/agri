(() => {
    'use strict';

    const toggle = document.querySelector('.welcome-menu');
    const navigation = document.getElementById('welcome-navigation');
    const onMediaChange = (query, callback) => {
        if (query.addEventListener) query.addEventListener('change', callback);
        else if (query.addListener) query.addListener(callback);
    };

    const closeMenu = () => {
        if (!toggle || !navigation) return;
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
    };

    if (toggle && navigation) {
        toggle.hidden = false;
        document.body.classList.add('menu-ready');

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            navigation.classList.toggle('is-open', !expanded);
        });

        navigation.addEventListener('click', event => {
            if (event.target.closest('a')) closeMenu();
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                closeMenu();
                toggle.focus();
            }
        });

        if (window.matchMedia) onMediaChange(window.matchMedia('(min-width: 761px)'), closeMenu);
    }

    document.addEventListener('click', event => {
        if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a');
        const href = link?.getAttribute('href');
        if (!href?.startsWith('#') || href.length < 2 || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        let destination;
        try {
            destination = document.getElementById(decodeURIComponent(href.slice(1)));
        } catch {
            return;
        }
        if (!destination) return;
        if (!destination.hasAttribute('tabindex')) destination.setAttribute('tabindex', '-1');
        // Focus follows every in-page shortcut; the browser still owns scrolling and history.
        destination.focus({ preventScroll: true });
    });

    if (!window.IntersectionObserver) return;

    if (navigation) {
        const sections = ['services', 'system', 'initiatives'].map(id => ({
            section: document.getElementById(id),
            link: navigation.querySelector(`a[href="#${id}"]`),
        })).filter(item => item.section && item.link);
        const visible = new Set();
        const navigationObserver = new window.IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) visible.add(entry.target);
                else visible.delete(entry.target);
            });
            const current = sections.filter(item => visible.has(item.section))
                .sort((a, b) => Math.abs(a.section.getBoundingClientRect().top) - Math.abs(b.section.getBoundingClientRect().top))[0];
            sections.forEach(item => {
                if (item === current) item.link.setAttribute('aria-current', 'location');
                else item.link.removeAttribute('aria-current');
            });
        }, { rootMargin: '-80px 0px -120px 0px' });
        sections.forEach(item => navigationObserver.observe(item.section));
    }

    const motion = window.matchMedia?.('(prefers-reduced-motion: reduce)');
    const animations = new Set();
    if (motion) onMediaChange(motion, () => {
        if (!motion.matches) return;
        animations.forEach(animation => animation.cancel());
        animations.clear();
    });

    const entryObserver = new window.IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            entryObserver.unobserve(entry.target);
            if (motion?.matches || typeof entry.target.animate !== 'function') return;
            // Content remains visible before enhancement and throughout the short settling motion.
            const animation = entry.target.animate([
                { transform: 'translateY(10px)', opacity: 0.82 },
                { transform: 'translateY(0)', opacity: 1 },
            ], { duration: 360, easing: 'cubic-bezier(.2,.7,.3,1)' });
            animations.add(animation);
            animation.finished.then(() => animations.delete(animation), () => animations.delete(animation));
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -32px 0px' });
    document.querySelectorAll('[data-welcome-reveal]').forEach(element => entryObserver.observe(element));
})();
