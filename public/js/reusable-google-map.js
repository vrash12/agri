(function (root) {
  'use strict';
  if (root.ReusableGoogleMap) return;
  const slots = new Map();
  function report(map, event) {
    const config = root.__mapUsage;
    if (!config?.url || !config.token || typeof root.fetch !== 'function') return;
    try {
      const url = new URL(config.url, root.location.href);
      if (url.origin !== root.location.origin) return;
      root.fetch(url.href, {method:'POST', credentials:'same-origin', keepalive:true,
        headers:{'Content-Type':'application/json', Accept:'application/json', 'X-CSRF-TOKEN':config.token},
        body:JSON.stringify({map, event})}).catch(() => {});
    } catch (_) { /* Best-effort metrics never block a map or retry. */ }
  }
  function acquire(name, host, options) {
    if (name !== 'crop-editor-2d') throw new Error('Unsupported reusable map purpose.');
    let slot = slots.get(name);
    if (slot?.active) throw new Error('This map is already in use. Close its current view first.');
    const reused = !!slot;
    if (!slot) {
      const canvas = root.document.createElement('div');
      canvas.style.width = '100%'; canvas.style.height = '100%';
      host.replaceChildren(canvas);
      slot = {canvas, map:new root.google.maps.Map(canvas, options), active:false};
      slots.set(name, slot);
    } else {
      host.replaceChildren(slot.canvas);
      slot.map.setOptions(options);
      root.google.maps.event?.trigger(slot.map, 'resize');
    }
    slot.active = true;
    report(name, reused ? 'reused' : 'created');
    let released = false;
    return {map:slot.map, release() {
      if (released) return;
      released = true;
      slot.active = false;
      // Keep one detached canvas per map purpose for this page's lifetime.
      // The caller removes all record overlays and listeners before release.
      slot.canvas.remove();
    }};
  }
  root.ReusableGoogleMap = {acquire, report};
}(window));
