(() => {
  'use strict';
  const DELIVERY = { ai: 'Generated now', cache: 'Saved answer reused (no new AI usage)', guide: 'Reviewed guidance (AI answer unavailable)' };

  /** Builds the result nodes with textContent only; model text never becomes HTML. */
  function renderAdvice(doc, container, data) {
    const nodes = [];
    const badge = doc.createElement('p');
    badge.className = 'ag-advice-delivery';
    badge.textContent = DELIVERY[data.delivery] || 'Reviewed guidance';
    nodes.push(badge);
    String(data.answer || '').split(/\n{2,}/).map(part => part.trim()).filter(Boolean).forEach(part => {
      const p = doc.createElement('p');
      p.className = 'ag-advice-text';
      p.textContent = part;
      nodes.push(p);
    });
    const sources = Array.isArray(data.sources) ? data.sources.filter(s => s && typeof s.title === 'string') : [];
    if (sources.length) {
      const label = doc.createElement('p');
      label.className = 'ag-meta';
      label.textContent = 'Reviewed sources: ' + sources.map(s => s.title).join('; ');
      nodes.push(label);
    }
    container.replaceChildren(...nodes);
    container.dataset.state = 'success';
  }

  function renderState(doc, container, state, message) {
    const p = doc.createElement('p');
    p.className = 'ag-advice-state';
    p.dataset.state = state;
    p.textContent = message;
    container.replaceChildren(p);
    container.dataset.state = state;
  }

  function errorMessage(status, data, retryAfter) {
    if (status === 429) {
      const wait = Number(retryAfter) > 0 ? ' Try again in ' + Number(retryAfter) + ' seconds.' : '';
      return (data && typeof data.message === 'string' && data.message !== 'Too Many Attempts.' ? data.message : 'AI crop advice is temporarily limited.') + wait;
    }
    if (status === 419) return 'Your session expired. Reload the page and try again.';
    if (data && typeof data.message === 'string' && data.message.length < 300) return data.message;
    return 'AI crop advice could not be loaded. Your records are unaffected. Try again shortly.';
  }

  function init(doc) {
    const root = doc.querySelector('[data-crop-advice]');
    const button = root?.querySelector('[data-crop-advice-button]');
    const result = root?.querySelector('[data-crop-advice-result]');
    if (!root || !button || !result) return;
    let busy = false;
    button.addEventListener('click', async () => {
      if (busy) return;
      busy = true;
      button.disabled = true;
      button.setAttribute('aria-disabled', 'true');
      result.hidden = false;
      result.setAttribute('aria-busy', 'true');
      renderState(doc, result, 'loading', 'Preparing crop advice…');
      try {
        const body = new URLSearchParams({ year: root.dataset.year || '', season: root.dataset.season || '' });
        const response = await fetch(root.dataset.url, {
          method: 'POST', credentials: 'same-origin', body,
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': doc.querySelector('meta[name="csrf-token"]')?.content || '' },
        });
        let data = null;
        try { data = await response.json(); } catch (_) { data = null; }
        if (response.ok && data && typeof data.answer === 'string') {
          renderAdvice(doc, result, data);
        } else {
          renderState(doc, result, response.status === 503 || response.status === 429 ? 'unavailable' : 'error',
            errorMessage(response.status, data, response.headers.get('Retry-After')));
        }
      } catch (_) {
        renderState(doc, result, 'error', 'AI crop advice could not be reached. Check your connection and try again.');
      } finally {
        result.setAttribute('aria-busy', 'false');
        busy = false;
        button.disabled = false;
        button.removeAttribute('aria-disabled');
        button.textContent = 'Ask again';
      }
    });
  }

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = { renderAdvice, renderState, errorMessage };
  } else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => init(document));
  } else {
    init(document);
  }
})();
