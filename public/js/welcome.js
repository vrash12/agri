(() => {
    'use strict';

    const toggle = document.querySelector('.welcome-menu');
    const navigation = document.getElementById('welcome-navigation');
    if (!toggle || !navigation) return;

    toggle.hidden = false;
    document.body.classList.add('menu-ready');

    const closeMenu = () => {
        toggle.setAttribute('aria-expanded', 'false');
        navigation.classList.remove('is-open');
    };

    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        navigation.classList.toggle('is-open', !expanded);
    });

    navigation.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link) return;
        closeMenu();
        const destination = link.getAttribute('href');
        if (destination.startsWith('#')) {
            const section = document.querySelector(destination);
            if (section) {
                section.setAttribute('tabindex', '-1');
                section.focus({preventScroll: true});
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
            closeMenu();
            toggle.focus();
        }
    });

    window.matchMedia('(min-width: 761px)').addEventListener('change', closeMenu);
})();
