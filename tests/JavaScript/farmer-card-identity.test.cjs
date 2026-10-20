const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const blade = fs.readFileSync('resources/views/farmers/id-card.blade.php', 'utf8');
const context = vm.createContext({});
vm.runInContext(blade.slice(blade.indexOf('    function identityText('), blade.indexOf('    function addressText(')), context);
vm.runInContext(blade.slice(blade.indexOf('    function fittedText('), blade.indexOf('    function drawIcon(')), context);
vm.runInContext(blade.slice(blade.indexOf('    function wrapText('), blade.indexOf('    function signatureLine(')), context);

function canvasContext() {
    return {
        font: '', drawn: [],
        measureText(text) { return { width: Array.from(text).length * Number(this.font.match(/(\d+)px/)[1]) * .55 }; },
        fillText(text, x, y, squeeze) { this.drawn.push({ text, x, y, squeeze, width: this.measureText(text).width }); },
    };
}

test('long farmer names wrap to two lines without losing accents or letters', () => {
    const ctx = canvasContext();
    const name = 'MARÍA ISABEL DELA CRUZ PEÑA VILLANUEVA';
    context.identityText(ctx, name, 340, 221, 610, 77);
    assert.equal(ctx.drawn.map(line => line.text).join(' '), name);
    assert.equal(ctx.drawn.length, 2);
    assert.ok(ctx.drawn.every(line => line.width <= 610 && line.y <= 298 && line.squeeze === undefined));
});

test('unbroken Unicode names wrap inside the identity panel', () => {
    const ctx = canvasContext();
    const name = 'Ñ'.repeat(70);
    context.identityText(ctx, name, 340, 221, 610, 77);
    assert.equal(ctx.drawn.map(line => line.text).join(''), name);
    assert.ok(ctx.drawn.every(line => line.width <= 610));
});

test('oversized names refuse an incomplete export before drawing identity text', () => {
    const ctx = canvasContext();
    assert.throws(() => context.identityText(ctx, 'LONG FARMER NAME '.repeat(100), 340, 221, 610, 77), /complete farmer name exceeds/);
    assert.equal(ctx.drawn.length, 0);
});

test('reference numbers fit without horizontal compression and refuse impossible lengths', () => {
    const ctx = canvasContext();
    context.fittedText(ctx, '01-00-00-000-000001', 340, 445, 285, 25, 700);
    assert.equal(ctx.drawn[0].text, '01-00-00-000-000001');
    assert.ok(ctx.drawn[0].width <= 285);
    assert.equal(ctx.drawn[0].squeeze, undefined);
    assert.throws(() => context.fittedText(ctx, 'X'.repeat(100), 340, 445, 285, 25, 700), /exceeds its space/);
    assert.equal(ctx.drawn.length, 1);
});

test('the complete cardholder name wraps under its ink signature line', () => {
    const ctx = canvasContext();
    const name = 'MARÍA CONCEPCIÓN DELA CRUZ PEÑA VILLANUEVA';
    context.signatureName(ctx, name, 181, 270);
    assert.equal(ctx.drawn.map(line => line.text).join(' '), name);
    assert.equal(ctx.drawn.length, 2);
    assert.ok(ctx.drawn.every(line => line.width <= 270 && line.y < 623));
});

test('an oversized signature name blocks export without cutting off the name', () => {
    const ctx = canvasContext();
    assert.throws(() => context.signatureName(ctx, 'UNREASONABLY LONG NAME '.repeat(20), 181, 270), /complete signature name exceeds/);
    assert.equal(ctx.drawn.length, 0);
});

test('card heading is centered between the brand and seal and restores alignment', () => {
    context.cardData = { issuer: { configured: true, office_name: 'Municipal Agriculture Office' }, municipality: 'Sample Municipality', province: 'Sample Province' };
    const ctx = canvasContext();
    ctx.textAlign = 'left';
    ctx.save = function () { this.savedAlignment = this.textAlign; };
    ctx.restore = function () { this.textAlign = this.savedAlignment; };
    const drawText = ctx.fillText;
    ctx.fillText = function (...args) {
        assert.equal(this.textAlign, 'center');
        drawText.apply(this, args);
    };
    context.drawCardHeading(ctx);
    assert.equal(ctx.textAlign, 'left');
    assert.equal(ctx.drawn.length, 3);
    for (const line of ctx.drawn) {
        assert.ok(line.x - line.width / 2 >= 326, 'heading stays clear of the left brand');
        assert.ok(line.x + line.width / 2 <= 831, 'heading stays clear of the right seal');
    }
});
