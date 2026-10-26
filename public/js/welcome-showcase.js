(() => {
    'use strict';

    const dialog = document.getElementById('welcome-preview-dialog');
    if (!dialog || typeof dialog.showModal !== 'function' || typeof dialog.close !== 'function') return;

    const title = document.getElementById('welcome-preview-title');
    let image = document.getElementById('welcome-preview-image');
    const description = dialog.querySelector('[data-preview-description]');
    const status = dialog.querySelector('[data-preview-status]');
    const counter = dialog.querySelector('[data-preview-counter]');
    const close = dialog.querySelector('[data-preview-close]');
    const previous = dialog.querySelector('[data-preview-previous]');
    const next = dialog.querySelector('[data-preview-next]');
    const original = dialog.querySelector('[data-preview-original]');
    if (!title || !image || !description || !status || !counter || !close || !previous || !next) return;

    const previews = [];
    const paths = new Map();
    const entries = [];
    document.querySelectorAll('[data-welcome-preview]').forEach(link => {
        let url;
        try {
            url = new URL(link.getAttribute('href'), window.location.href);
        } catch {
            return;
        }
        if (url.origin !== window.location.origin || url.username || url.password || url.hash
            || !/^\/images\/showcase\/(?:people|maps|climate|programs|operations|connected|administration)-0[1-4]\.svg$/.test(url.pathname)) return;
        const label = (link.getAttribute('data-preview-title') || '').trim();
        if (!label) return;
        if (!paths.has(url.pathname)) {
            paths.set(url.pathname, previews.length);
            previews.push({
                url: url.href,
                title: label,
                description: link.getAttribute('data-preview-description') || '',
            });
        }
        entries.push({ link, index: paths.get(url.pathname) });
    });
    if (!previews.length) return;

    let selected = 0;
    let opener = null;
    let generation = 0;

    const render = () => {
        const preview = previews[selected];
        const version = ++generation;
        title.textContent = preview.title;
        description.textContent = preview.description;
        counter.textContent = `${selected + 1} of ${previews.length}`;
        previous.disabled = selected === 0;
        next.disabled = selected === previews.length - 1;
        status.textContent = `${preview.title}: loading sample preview…`;
        status.setAttribute('data-state', 'loading');
        dialog.setAttribute('aria-busy', 'true');
        if (original) original.setAttribute('href', preview.url);

        // Separate elements keep a late response from an earlier selection from
        // hiding or relabeling the image that is now being viewed.
        const replacement = image.cloneNode(false);
        replacement.removeAttribute('src');
        replacement.removeAttribute('srcset');
        replacement.hidden = true;
        replacement.loading = 'eager';
        replacement.alt = `${preview.title} — illustrated interface with fictional sample data`;
        replacement.addEventListener('load', () => {
            if (version !== generation || image !== replacement || !dialog.open) return;
            replacement.hidden = false;
            status.setAttribute('data-state', 'ready');
            status.textContent = `Sample preview loaded: ${preview.title}, ${selected + 1} of ${previews.length}. All records and figures are fictional.`;
            dialog.setAttribute('aria-busy', 'false');
        });
        replacement.addEventListener('error', () => {
            if (version !== generation || image !== replacement || !dialog.open) return;
            replacement.hidden = true;
            status.setAttribute('data-state', 'error');
            status.textContent = 'This sample image could not load. The feature description is still available; try another preview or open the image directly.';
            dialog.setAttribute('aria-busy', 'false');
        });
        image.replaceWith(replacement);
        image = replacement;
        image.setAttribute('src', preview.url);
    };

    entries.forEach(({ link, index }) => {
        link.addEventListener('click', event => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
                || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            try {
                if (!dialog.open) dialog.showModal();
            } catch {
                // The ordinary image link remains available if modal display fails.
                return;
            }
            event.preventDefault();
            opener = link;
            selected = index;
            render();
            close.focus();
        });
    });

    close.addEventListener('click', () => dialog.close());
    previous.addEventListener('click', () => {
        if (!dialog.open || selected <= 0) return;
        selected -= 1;
        render();
        if (previous.disabled) next.focus();
    });
    next.addEventListener('click', () => {
        if (!dialog.open || selected >= previews.length - 1) return;
        selected += 1;
        render();
        if (next.disabled) previous.focus();
    });
    dialog.addEventListener('click', event => {
        if (event.target !== dialog || !Number.isFinite(event.clientX) || !Number.isFinite(event.clientY)) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => {
        generation += 1;
        dialog.setAttribute('aria-busy', 'false');
        status.textContent = '';
        if (opener?.isConnected) opener.focus({ preventScroll: true });
        opener = null;
    });
})();
