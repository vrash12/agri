const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const blade = fs.readFileSync('resources/views/farmers/id-card.blade.php', 'utf8');
const source = blade.slice(blade.indexOf('    function addressText('), blade.indexOf('    const printedAddress'));
const context = vm.createContext({});
vm.runInContext(source, context);

function canvasContext() {
    return {
        font: '',
        drawn: [],
        measureText(text) { return { width: Array.from(text).length * Number(this.font.match(/(\d+)px/)[1]) * 0.55 }; },
        fillText(text, x, y) { this.drawn.push({ text, x, y, width: this.measureText(text).width }); },
    };
}

test('keeps complete slash-separated addresses within the canvas area', () => {
    const ctx = canvasContext();
    const text = 'Field A, Example Town, Example Province / Field B, Example Town, Example Province';
    context.addressText(ctx, text, 52, 288, 440, 100);
    assert.equal(ctx.drawn.map(line => line.text).join(' '), text);
    assert.ok(ctx.drawn.every(line => line.width <= 440 && line.y < 388));
});

test('wraps a long unbroken Unicode address without losing characters', () => {
    const ctx = canvasContext();
    const text = 'Ñ'.repeat(80);
    context.addressText(ctx, text, 52, 288, 440, 100);
    assert.equal(ctx.drawn.map(line => line.text).join(''), text);
    assert.ok(ctx.drawn.every(line => line.width <= 440));
});

test('refuses an incomplete export when addresses exceed the card', () => {
    const ctx = canvasContext();
    assert.throws(() => context.addressText(ctx, 'Parcel address / '.repeat(100), 52, 288, 440, 100), /complete parcel addresses exceed/);
    assert.equal(ctx.drawn.length, 0);
});
