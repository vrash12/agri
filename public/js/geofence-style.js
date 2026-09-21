(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.GeofenceStyle = api;
})(typeof window !== 'undefined' ? window : this, function () {
  'use strict';

  function normalize(boundary) {
    const color = /^#[0-9a-f]{6}$/i.test(boundary?.color || '') ? boundary.color.toUpperCase() : '#15803D';
    const raw = boundary?.fill_opacity;
    const opacity = raw === null || raw === undefined || raw === '' ? .2 : Number(raw);
    return {color, fill_opacity: Number.isFinite(opacity) ? Math.min(1, Math.max(0, opacity)) : .2};
  }

  function rgba(boundary) {
    const style = normalize(boundary), hex = style.color.slice(1);
    return 'rgba(' + [0, 2, 4].map(index => parseInt(hex.slice(index, index + 2), 16)).concat(style.fill_opacity).join(',') + ')';
  }

  function labelPosition(boundary) {
    const point = boundary.label_position;
    return point && Number.isFinite(point.lat) && Number.isFinite(point.lng) ? point : null;
  }

  function labelTemplate(document, name) {
    const template = document.createElement('template');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    const width = Math.max(90, Math.min(300, String(name).length * 7 + 16));
    svg.setAttribute('width', width); svg.setAttribute('height', '24');
    svg.setAttribute('viewBox', '0 0 ' + width + ' 24');
    const attrs = {x: width / 2, y: 16, 'text-anchor': 'middle', 'font-family': 'Arial, sans-serif', 'font-size': 12,
      'font-weight': 500, fill: '#FFFFFF', 'fill-opacity': .72, stroke: '#20362C', 'stroke-opacity': .6, 'stroke-width': 2, 'paint-order': 'stroke'};
    Object.entries(attrs).forEach(([key, value]) => text.setAttribute(key, value));
    text.textContent = String(name); svg.appendChild(text); template.content.appendChild(svg);
    return template;
  }

  return {normalize, rgba, labelPosition, labelTemplate};
});
