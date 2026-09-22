(function () {
  'use strict';
  const control = document.querySelector('[data-farmer-workspace-control]');
  if (!control) return;
  const links = Array.from(control.querySelectorAll('[data-workspace-target]'));
  const hasDirectory = !!document.getElementById('farmerDirectory');
  if (hasDirectory) links.forEach(link => { link.href = link.dataset.workspaceTarget === 'map' ? '#farmersMapModule' : '#farmerDirectory'; });
  function syncHash() {
    links.forEach(link => {
      const active = (window.location.hash === '#farmersMapModule' ? 'map' : 'registry') === link.dataset.workspaceTarget;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }
  syncHash();
  window.addEventListener('hashchange', syncHash);
  control.querySelectorAll('[data-workspace-scope-form]').forEach(form => {
    form.querySelector('[data-workspace-select]')?.addEventListener('change', event => {
      if (event.target.value) form.requestSubmit();
    });
    form.addEventListener('submit', () => {
      if (form.hasAttribute('data-workspace-open')) {
        const action = new URL(form.action, window.location.href);
        action.hash = window.location.hash === '#farmersMapModule' ? 'farmersMapModule' : 'farmerDirectory';
        form.action = action.toString();
      }
    });
  });
})();
