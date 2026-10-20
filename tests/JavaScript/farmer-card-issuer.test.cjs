const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const blade = fs.readFileSync('resources/views/farmers/id-card.blade.php', 'utf8');
const context = vm.createContext({});
vm.runInContext(blade.slice(blade.indexOf('    function issuerText('), blade.indexOf('    // The full issuer details')), context);
function canvasContext() {
    return { font: '', drawn: [], measureText(text) { return { width: Array.from(text).length * Number(this.font.match(/(\d+)px/)[1]) * .55 }; }, fillText(text, x, y) { this.drawn.push({ text, y, width: this.measureText(text).width }); } };
}
test('complete LGU office information wraps within its reserved header area', () => {
    const ctx = canvasContext();
    const text = 'Municipal Agriculture Office · https://example.test/office';
    context.issuerText(ctx, text, 52, 25, 290, 76);
    assert.equal(ctx.drawn.map(line => line.text).join(''), text);
    assert.ok(ctx.drawn.every(line => line.width <= 290 && line.y <= 101));
});
test('unbroken URLs and Unicode names retain every character', () => {
    const ctx = canvasContext();
    const text = 'Ñ'.repeat(100);
    context.issuerText(ctx, text, 52, 25, 290, 76);
    assert.equal(ctx.drawn.map(line => line.text).join(''), text);
});
test('oversized LGU details refuse an incomplete card export', () => {
    const ctx = canvasContext();
    assert.throws(() => context.issuerText(ctx, 'Office details '.repeat(200), 52, 25, 290, 76), /Shorten the details in Settings/);
    assert.equal(ctx.drawn.length, 0);
});
