/**
 * Rebuild public, fictional feature illustrations without reading application data.
 * Run: node scripts/build-homepage-previews.mjs
 * These images explain workflows; they are not screenshots or operational records.
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const output = resolve(dirname(fileURLToPath(import.meta.url)), '../public/images/showcase');
const C = Object.freeze({
    ink: '#20362c', muted: '#607269', green: '#236344', dark: '#173e2d', pale: '#eaf4ed',
    yellow: '#facc15', cream: '#fff8d6', white: '#ffffff', bg: '#f8faf8', line: '#dfe7e1',
    blue: '#346a8a', bluePale: '#edf4f8', purple: '#76598d', purplePale: '#f2edf7',
    amber: '#906019', amberPale: '#fbf2df', red: '#a6504a', redPale: '#faefed', teal: '#267b72',
});
const xml = value => String(value).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&apos;' }[char]));
const rect = (x, y, w, h, fill = C.white, rx = 12, stroke = '') => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}"${stroke ? ` stroke="${stroke}" stroke-width="1.5"` : ''}/>`;
const text = (x, y, value, size = 18, color = C.ink, weight = 500, extra = '') => `<text x="${x}" y="${y}" font-size="${size}" fill="${color}" font-weight="${weight}" ${extra}>${xml(value)}</text>`;
const line = (x1, y1, x2, y2, color = C.line, width = 2, extra = '') => `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${color}" stroke-width="${width}" ${extra}/>`;
const circle = (x, y, r, fill, stroke = '') => `<circle cx="${x}" cy="${y}" r="${r}" fill="${fill}"${stroke ? ` stroke="${stroke}" stroke-width="2"` : ''}/>`;
const path = (d, fill = 'none', stroke = C.green, width = 3, extra = '') => `<path d="${d}" fill="${fill}" stroke="${stroke}" stroke-width="${width}" stroke-linecap="round" stroke-linejoin="round" ${extra}/>`;
const panel = (x, y, w, h, fill = C.white) => rect(x, y, w, h, fill, 12, C.line);
const chip = (x, y, w, value, fill = C.pale, color = C.green, size = 16) => rect(x, y, w, 32, fill, 7) + text(x + 12, y + 22, value, size, color, 650);
const label = (x, y, value) => text(x, y, value, 15, C.muted, 550);
const check = (x, y, color = C.green) => circle(x + 10, y + 10, 11, C.pale) + path(`M ${x + 4} ${y + 10} l 4 4 8 -9`, 'none', color, 2.5);
const row = (y, index, name, detail, status, statusColor = 'green') => {
    const colors = statusColor === 'amber' ? [C.amberPale, C.amber] : statusColor === 'blue' ? [C.bluePale, C.blue] : [C.pale, C.green];
    return line(56, y + 47, 904, y + 47) + circle(80, y + 18, 17, C.pale) + text(80, y + 24, index, 15, C.green, 700, 'text-anchor="middle"') + text(112, y + 23, name, 19, C.ink, 600) + text(435, y + 23, detail, 18, C.muted) + chip(720, y + 1, 160, status, ...colors);
};
const stat = (x, y, w, value, caption, color = C.green, pale = C.pale) => panel(x, y, w, 102) + rect(x, y, 5, 102, color, 0) + text(x + 20, y + 46, value, 34, color, 700) + text(x + 20, y + 77, caption, 16, C.muted) + circle(x + w - 30, y + 31, 12, pale);
const button = (x, y, w, caption, filled = true) => rect(x, y, w, 40, filled ? C.green : C.white, 8, filled ? '' : C.green) + text(x + w / 2, y + 26, caption, 16, filled ? C.white : C.green, 650, 'text-anchor="middle"');
const field = (x, y, w, caption, value) => label(x, y, caption) + rect(x, y + 12, w, 46, C.white, 8, '#7a9182') + text(x + 14, y + 42, value, 19, C.ink, 500);
const note = (x, y, w, caption, color = C.blue, fill = C.bluePale) => rect(x, y, w, 45, fill, 8) + circle(x + 23, y + 22, 11, color) + text(x + 23, y + 28, 'i', 16, C.white, 700, 'text-anchor="middle"') + text(x + 44, y + 28, caption, 16, color, 550);

function frame(title, subtitle, body, description = title) {
    return `<svg xmlns="http://www.w3.org/2000/svg" width="960" height="600" viewBox="0 0 960 600" role="img" aria-labelledby="title desc">
<title id="title">${xml(title)} — fictional sample</title>
<desc id="desc">${xml(description)}. Sample LGU. Illustrated preview · fictional records. All names, numbers, maps and dates are examples.</desc>
<g font-family="Inter, Arial, sans-serif" font-variant-numeric="tabular-nums">
${rect(0, 0, 960, 600, C.bg, 0)}${rect(0, 0, 960, 6, C.green, 0)}${rect(748, 0, 212, 6, C.yellow, 0)}
${rect(0, 6, 960, 64, C.white, 0)}${text(36, 46, 'AgriLGU', 28, C.green, 750)}${line(185, 23, 185, 53)}${text(207, 43, 'Sample LGU', 18, C.muted, 550)}${chip(768, 22, 156, 'SAMPLE DATA', C.cream, C.ink, 17)}
${line(0, 70, 960, 70)}${text(36, 113, title, 29, C.ink, 700)}${text(36, 143, subtitle, 17, C.muted)}
${body}
${line(36, 558, 924, 558)}${text(36, 583, 'Illustrated preview · fictional records', 16, C.muted, 500)}${text(924, 583, 'NOT LIVE DATA', 14, C.green, 650, 'text-anchor="end"')}
</g></svg>\n`;
}

function map(x, y, w, h, variant = 'parcels') {
    // The geometry is deliberately schematic and has no geographic coordinates.
    const sx = w / 600, sy = h / 360;
    const polygons = [
        ['42,40 214,28 237,146 54,171', C.green, 'Parcel 01'],
        ['236,35 407,62 382,180 249,146', C.amber, 'Parcel 02'],
        ['64,190 244,166 263,316 93,321', C.teal, 'Parcel 03'],
        ['270,200 418,189 511,280 299,321', C.purple, 'Parcel 04'],
    ];
    let body = rect(0, 0, 600, 360, '#edf1e7', 0);
    body += path('M -10 275 Q 130 240 222 252 T 620 204', 'none', '#d2e4e8', 26);
    body += path('M 455 -10 Q 418 107 476 170 T 592 370', 'none', '#ffffff', 15);
    body += path('M -10 185 L 594 110', 'none', '#ffffff', 13);
    body += path('M 224 -10 L 287 370', 'none', '#ffffff', 12);
    if (variant === 'boundaries' || variant === 'coverage') {
        const cells = [
            ['54,43 236,27 260,168 80,193', variant === 'coverage' ? 'Sample LGU A' : 'North area', '12 releases', C.green, '#cfe2d1'],
            ['257,28 473,67 456,186 276,161', variant === 'coverage' ? 'Sample LGU B' : 'East area', '8 releases', C.blue, '#d1e3ea'],
            ['83,211 269,187 295,322 103,329', variant === 'coverage' ? 'Sample LGU C' : 'West area', '4 releases', C.amber, '#f5e6af'],
            ['291,208 454,206 527,301 315,328', variant === 'coverage' ? 'Sample LGU D' : 'South area', '0 releases', C.muted, '#e7ece5'],
        ];
        cells.forEach(([points, name, detail, color, fill], i) => {
            body += `<polygon points="${points}" fill="${fill}" stroke="${color}" stroke-width="3"/>`;
            const tx = i % 2 === 0 ? 160 : 365, ty = i < 2 ? 106 : 265;
            body += text(tx, ty, name, 22, C.ink, 650, 'text-anchor="middle"');
            if (variant === 'coverage') body += text(tx, ty + 27, detail, 18, color, 600, 'text-anchor="middle"');
        });
        if (variant === 'boundaries') body += path('M 32 29 L 239 11 L 495 53 L 547 315 L 307 347 L 88 349 Z', 'none', C.dark, 4, 'stroke-dasharray="9 6"');
    } else if (variant === 'satellite') {
        for (let i = 0; i < 7; i++) {
            for (let j = 0; j < 4; j++) {
                const palette = ['#c8dba2', '#9ec382', '#6aa46a', '#357d54'];
                body += rect(30 + i * 73, 33 + j * 72, 70, 69, palette[(i + j * 2) % 4], 0);
            }
        }
        body += `<polygon points="44,46 451,60 514,275 124,314" fill="none" stroke="${C.white}" stroke-width="5"/>`;
        body += chip(170, 140, 234, 'Illustrated index grid', C.white, C.green, 20);
    } else {
        polygons.forEach(([points, color, caption], i) => {
            body += `<polygon points="${points}" fill="${color}" fill-opacity="0.18" stroke="${color}" stroke-width="3"/>`;
            const tx = i % 2 === 0 ? 141 : 327, ty = i < 2 ? 99 : 259;
            body += text(tx, ty, variant === 'crops' ? ['Rice', 'Corn', 'Mungbean', 'Fallow'][i] : caption, 21, C.ink, 650, 'text-anchor="middle"');
        });
    }
    return `<svg x="${x}" y="${y}" width="${w}" height="${h}" viewBox="0 0 600 360" preserveAspectRatio="none">${body}</svg>` + rect(x, y, w, h, 'none', 0, C.line);
}

const horizontalBar = (x, y, w, name, count, total, color) => text(x, y, name, 17, C.ink, 550) + text(x + w, y, `${count} / ${total}`, 16, color, 650, 'text-anchor="end"') + rect(x, y + 13, w, 14, C.bg, 7) + rect(x, y + 13, Math.round(w * count / total), 14, color, 7);
const chart = (x, y, w, h, values, labels, color = C.green) => {
    const max = Math.max(...values) * 1.15;
    let result = [0, 0.5, 1].map(n => line(x, y + h * n, x + w, y + h * n, C.line, 1)).join('');
    const step = w / values.length;
    values.forEach((value, i) => {
        const bh = value / max * h;
        result += rect(x + i * step + step * .16, y + h - bh, step * .68, bh, color, 5);
        result += text(x + (i + .5) * step, y + h - bh - 10, value, 16, color, 650, 'text-anchor="middle"');
        result += text(x + (i + .5) * step, y + h + 25, labels[i], 16, C.muted, 500, 'text-anchor="middle"');
    });
    return result;
};

const previews = [];
const add = (name, title, subtitle, body, description) => previews.push({ name, svg: frame(title, subtitle, body, description) });

add('people-01', 'Farmer registry', 'Find the person. See the records connected to them.',
    stat(36, 170, 278, '48', 'Sample farmer profiles') + stat(341, 170, 278, '36', 'With mapped parcels', C.blue, C.bluePale) + stat(646, 170, 278, '12', 'Awaiting mapping', C.amber, C.amberPale)
    + panel(36, 292, 888, 247) + label(57, 324, 'FARMER') + label(435, 324, 'REGISTRY REFERENCE') + label(732, 324, 'MAPPING')
    + row(347, '01', 'Sample Farmer 001', 'SAMPLE-001', 'Mapped') + row(405, '02', 'Sample Farmer 002', 'SAMPLE-002', 'Mapped') + row(463, '03', 'Sample Farmer 003', 'SAMPLE-003', 'Needs mapping', 'amber'));

add('people-02', 'Digital IDs & QR parcel pages', 'A local registry card, linked to a limited parcel view.',
    panel(36, 176, 565, 355, '#fffbed') + text(64, 213, 'SAMPLE LGU', 17, C.green, 700) + text(64, 245, 'FARMER REGISTRY CARD', 23, C.ink, 700)
    + path('M 38 426 Q 175 344 320 410 T 600 395 L 600 530 L 38 530 Z', '#b5ce90', 'none', 0)
    + path('M 38 458 Q 215 390 385 467 T 600 443 L 600 530 L 38 530 Z', '#6fa475', 'none', 0)
    + rect(64, 270, 128, 161, C.pale, 8, C.green) + circle(128, 318, 26, '#9db9a6') + path('M 89 405 Q 90 354 128 354 Q 166 354 168 405', '#9db9a6', 'none', 0)
    + label(215, 290, 'CARDHOLDER') + text(215, 321, 'Sample Farmer 001', 23, C.ink, 700) + label(215, 355, 'SAMPLE REGISTRY REFERENCE') + text(215, 384, 'SAMPLE-001', 22, C.dark, 700)
    + chip(215, 405, 240, 'SPECIMEN · NOT VALID', C.cream, C.ink) + rect(36, 490, 565, 41, C.dark, 0) + text(64, 518, 'Local registry card · not a national ID', 16, C.white, 550)
    + panel(626, 176, 298, 355) + text(650, 213, 'Parcel verification', 21, C.ink, 650) + map(650, 235, 250, 155)
    + label(650, 422, 'QR DESTINATION PREVIEW') + text(650, 452, 'Read-only parcel map', 19, C.green, 650) + text(650, 485, 'No functional code shown', 16, C.muted));

add('people-03', 'My farmer records', 'A separate, read-only space for each farmer.',
    panel(36, 173, 888, 81) + circle(80, 213, 23, C.pale) + text(80, 220, '01', 17, C.green, 700, 'text-anchor="middle"') + text(119, 207, 'Sample Farmer 001', 23, C.ink, 650) + label(119, 233, 'SAMPLE-001 · SAMPLE LGU') + chip(742, 197, 156, 'Read-only view', C.bluePale, C.blue)
    + panel(36, 274, 509, 266) + text(58, 309, 'My farm', 23, C.ink, 650) + map(58, 327, 465, 192, 'crops')
    + panel(566, 274, 358, 266) + text(590, 309, 'My records', 22, C.ink, 650) + line(590, 327, 900, 327)
    + label(590, 359, '2026 · WET SEASON') + text(590, 388, '4 recorded parcel sections', 20, C.green, 600)
    + label(590, 427, 'ASSISTANCE HISTORY') + text(590, 456, '1 sample release · 20 kg', 20, C.ink, 600)
    + text(590, 502, 'Ask the office for corrections', 16, C.muted));

add('people-04', 'Record quality & duplicate review', 'Keep missing information visible and review it carefully.',
    stat(36, 174, 278, '4', 'Missing registry reference', C.amber, C.amberPale) + stat(341, 174, 278, '2', 'Possible duplicate pairs', C.purple, C.purplePale) + stat(646, 174, 278, '12', 'Farmers without a map', C.blue, C.bluePale)
    + panel(36, 299, 888, 170) + text(60, 333, 'Review before changing a record', 22, C.ink, 650)
    + text(60, 378, 'Sample Farmer 004', 20, C.ink, 650) + text(363, 378, 'Reference not recorded', 19, C.muted) + chip(715, 354, 178, 'Needs office review', C.amberPale, C.amber, 15)
    + line(60, 398, 900, 398) + text(60, 435, 'Sample Farmer 005', 20, C.ink, 650) + text(363, 435, 'Possible import match', 19, C.muted) + chip(715, 412, 178, 'Check source rows', C.bluePale, C.blue, 15)
    + note(36, 488, 888, 'Potential matches require review; no automatic merge.'));

add('maps-01', 'GIS parcel workspace', 'Keep each mapped boundary connected to its farmer.',
    panel(36, 174, 282, 365) + text(58, 211, 'Sample Farmer 001', 21, C.ink, 650) + label(58, 239, '4 ILLUSTRATED PARCELS')
    + chip(58, 261, 238, 'Parcel 01 · 1.20 ha') + text(72, 327, 'Parcel 02 · 0.80 ha', 18, C.muted) + text(72, 379, 'Parcel 03 · 0.60 ha', 18, C.muted) + text(72, 431, 'Parcel 04 · 0.40 ha', 18, C.muted)
    + line(58, 458, 296, 458) + text(58, 493, '3.00 ha mapped total', 21, C.green, 650)
    + map(337, 174, 587, 319) + chip(357, 192, 184, 'Schematic boundaries', C.white, C.ink, 15) + note(337, 505, 587, 'Illustration only · not surveyed land', C.green, C.pale));

add('maps-02', 'Seasonal crops & planted sections', 'See what is recorded for a selected year and season.',
    chip(36, 170, 125, '2026') + chip(173, 170, 165, 'Wet season', C.bluePale, C.blue)
    + map(36, 218, 551, 320, 'crops') + panel(609, 170, 315, 368)
    + text(633, 209, 'Recorded sections', 22, C.ink, 650)
    + horizontalBar(633, 255, 263, 'Rice', 12, 30, C.green) + horizontalBar(633, 323, 263, 'Corn', 8, 30, C.amber) + horizontalBar(633, 391, 263, 'Mungbean', 6, 30, C.teal)
    + text(633, 466, '4 fallow / unplanted', 18, C.muted) + text(633, 505, '30 sections · sample cohort', 16, C.muted));

add('maps-03', 'Municipality & barangay references', 'Put parcel boundaries in their local planning context.',
    map(36, 174, 575, 365, 'boundaries') + panel(633, 174, 291, 365)
    + text(657, 211, 'Reference layers', 22, C.ink, 650) + check(657, 242) + text(689, 259, 'Municipality', 19) + check(657, 291) + text(689, 308, 'Barangay areas', 19)
    + line(657, 335, 900, 335) + chip(657, 355, 219, '3 boundary checks', C.amberPale, C.amber)
    + text(657, 418, 'Crossing or outside', 19, C.ink, 650) + text(657, 449, 'Review parcel geometry', 16, C.muted)
    + text(657, 494, 'Planning references only', 17, C.green, 600) + text(657, 520, 'Schematic; not cadastral', 16, C.muted));

add('maps-04', 'Parcel sheets & land snapshots', 'Bring saved map information into office reports.',
    panel(36, 174, 550, 365) + text(61, 209, 'SAMPLE PARCEL SHEET', 21, C.ink, 650) + label(61, 237, 'SAMPLE-001 · PARCEL 01') + map(61, 255, 500, 223)
    + text(61, 510, 'Recorded area: 1.20 ha', 19, C.green, 600)
    + panel(610, 174, 314, 365) + text(634, 212, 'Export options', 23, C.ink, 650) + button(634, 241, 266, 'Print parcel sheet') + button(634, 298, 266, 'Download map PNG', false)
    + label(634, 387, 'INCLUDED CONTEXT') + text(634, 418, 'Farmer and parcel', 19) + text(634, 451, 'Boundary and area', 19) + text(634, 500, 'Depends on imagery access', 16, C.muted));

add('climate-01', 'Satellite field checks', 'Use dated observations to guide a closer look.',
    chip(36, 170, 101, 'NDVI') + chip(149, 170, 218, 'Sample: 12 Oct 2026', C.bluePale, C.blue)
    + map(36, 216, 507, 277, 'satellite') + text(36, 527, 'Schematic index image · not satellite evidence', 16, C.muted)
    + panel(564, 170, 360, 368) + label(589, 208, 'VEGETATION INDEX · SAMPLE') + text(589, 265, '0.64', 51, C.green, 700) + text(724, 264, 'NDVI', 22, C.muted, 600)
    + label(589, 307, 'CLOUD COVER') + text(589, 340, '18% in sample scene', 21, C.ink, 650)
    + line(589, 367, 900, 367) + text(589, 405, 'Inspect field conditions', 22, C.ink, 650) + text(589, 438, 'An index alone cannot', 18, C.muted) + text(589, 465, 'diagnose crop damage.', 18, C.muted)
    + chip(589, 487, 218, 'Dated evidence required', C.cream, C.ink, 15));

add('climate-02', 'El Niño readiness', 'Bring staff assessments and supporting evidence together.',
    stat(36, 174, 278, '6', 'Sample assessed parcels') + stat(341, 174, 278, '2', 'Marked Watch by staff', C.amber, C.amberPale) + stat(646, 174, 278, '4', 'Not assessed', C.blue, C.bluePale)
    + panel(36, 298, 888, 181) + text(60, 334, 'Parcel 01 · staff-entered assessment', 23, C.ink, 650) + chip(759, 311, 135, 'Watch', C.amberPale, C.amber)
    + label(60, 377, 'EVIDENCE TO REVIEW') + text(60, 410, 'Water-supply note + rainfall comparison', 21, C.ink, 550)
    + text(60, 452, 'Recorded 12 Oct 2026 · review due 19 Oct 2026', 17, C.muted)
    + note(36, 494, 888, 'Sample assessment only; no automatic disaster declaration.', C.amber, C.amberPale));

add('climate-03', 'Crop guidance & field visits', 'Record the visit, compare evidence and plan follow-up.',
    panel(36, 174, 490, 365) + text(60, 211, 'Parcel 01 · field visit', 23, C.ink, 650)
    + field(60, 247, 442, 'VISIT DATE', '12 Oct 2026 · example') + field(60, 327, 442, 'CROP STAGE', 'Rice · vegetative')
    + label(60, 408, 'RECORDED OBSERVATION') + text(60, 440, 'Water supply needs verification', 20, C.ink, 550) + chip(60, 478, 213, 'Follow-up: 19 Oct', C.amberPale, C.amber)
    + panel(549, 174, 375, 365) + text(573, 211, 'Evidence comparison', 23, C.ink, 650)
    + label(573, 257, 'CROP REFERENCE') + text(573, 287, 'Rice / Corn / Mungbean', 20, C.green, 650)
    + line(573, 313, 900, 313) + check(573, 337) + text(607, 354, 'Crop stage recorded', 18) + circle(583, 391, 11, C.amberPale) + text(583, 397, '?', 17, C.amber, 700, 'text-anchor="middle"') + text(607, 397, 'Soil evidence missing', 18)
    + text(573, 466, 'Unknowns stay visible.', 19, C.ink, 650) + text(573, 501, 'Supports review, not prescription.', 16, C.muted));

add('climate-04', 'Weather & rainfall evidence', 'Keep forecast dates and source limitations in view.',
    panel(36, 174, 888, 176) + text(60, 211, 'Sample LGU · illustrative 3-day forecast', 23, C.ink, 650)
    + [0, 1, 2].map((i) => {
        const x = 66 + i * 285;
        return text(x, 250, ['Mon 12 Oct', 'Tue 13 Oct', 'Wed 14 Oct'][i], 18, C.muted) + circle(x + 26, 294, 18, i === 1 ? '#b7d2df' : C.yellow)
            + text(x + 61, 302, ['30°C', '28°C', '29°C'][i], 30, C.ink, 650) + text(x + 160, 301, ['4 mm', '12 mm', '7 mm'][i], 21, C.blue, 600);
    }).join('')
    + panel(36, 370, 541, 168) + text(60, 407, 'Recent rainfall comparison', 22, C.ink, 650) + horizontalBar(60, 450, 491, 'Illustrative 7-day rainfall (mm)', 28, 40, C.blue)
    + text(60, 514, '28 mm recent · 40 mm baseline · sample', 15, C.muted)
    + panel(599, 370, 325, 168, C.cream) + text(623, 408, 'Check the source', 23, C.ink, 650) + text(623, 444, 'Forecast ≠ farm measurement', 17, C.ink) + text(623, 481, 'Official advisories: PAGASA', 17, C.green, 600) + text(623, 512, 'All weather values are samples', 15, C.muted));

add('programs-01', 'Agriculture & fisheries assistance', 'Track the item, beneficiary, quantity and release date.',
    stat(36, 174, 278, '24', 'Sample release records') + stat(341, 174, 278, '18', 'Distinct sample farmers', C.blue, C.bluePale) + stat(646, 174, 278, '2026', 'Reporting year', C.purple, C.purplePale)
    + panel(36, 298, 888, 241) + label(60, 331, 'BENEFICIARY') + label(392, 331, 'ITEM') + label(678, 331, 'QUANTITY')
    + text(60, 378, 'Sample Farmer 001', 20, C.ink, 600) + text(392, 378, 'Rice seed', 20) + chip(678, 354, 200, '20 kg', C.pale, C.green)
    + line(60, 399, 900, 399) + text(60, 435, 'Sample Farmer 002', 20, C.ink, 600) + text(392, 435, 'Fingerlings', 20) + chip(678, 411, 200, '500 pieces', C.bluePale, C.blue)
    + line(60, 456, 900, 456) + text(60, 499, 'Different items retain their own units.', 18, C.muted));

add('programs-02', 'Rice seed distribution sheets', 'Organize a sample batch by season and beneficiary.',
    chip(36, 174, 221, 'SAMPLE-BATCH-001') + chip(271, 174, 191, '2026 · wet season', C.bluePale, C.blue)
    + panel(36, 223, 888, 221) + rect(38, 225, 884, 49, C.pale, 10)
    + text(60, 257, 'Beneficiary', 17, C.green, 650) + text(405, 257, 'Rice area', 17, C.green, 650) + text(590, 257, 'Seed bags', 17, C.green, 650) + text(770, 257, 'Bag weight', 17, C.green, 650)
    + [0, 1, 2].map(i => text(60, 313 + i * 51, `Sample Farmer 00${i + 1}`, 20, C.ink, 600) + text(405, 313 + i * 51, ['1.20 ha', '0.80 ha', '1.00 ha'][i], 19) + text(620, 313 + i * 51, '1', 19) + text(786, 313 + i * 51, '20 kg', 19) + (i < 2 ? line(60, 331 + i * 51, 900, 331 + i * 51) : '')).join('')
    + panel(36, 465, 888, 74, C.cream) + text(61, 510, '3 beneficiaries', 24, C.ink, 650) + text(362, 510, '3 seed bags', 24, C.ink, 650) + text(681, 510, '60 kg total', 24, C.green, 700));

add('programs-03', 'Harvests & production', 'Keep harvest quantities tied to crop, season and unit.',
    panel(36, 174, 516, 365) + text(60, 212, 'Recorded rice harvest · sample', 23, C.ink, 650) + label(60, 240, '2026 WET SEASON · METRIC TONNES') + chart(74, 285, 450, 180, [3.6, 2.4, 3], ['Parcel 01', 'Parcel 02', 'Parcel 03'], C.green)
    + panel(576, 174, 348, 365) + label(600, 212, 'SAMPLE TOTAL') + text(600, 268, '9.0 t', 48, C.green, 700) + text(600, 304, '3 recorded harvests', 22, C.ink, 650)
    + line(600, 331, 900, 331) + label(600, 367, 'CROP CONNECTION') + text(600, 399, 'Saved rice crop sections', 20, C.ink, 550)
    + text(600, 458, 'Missing records stay missing.', 17, C.muted) + text(600, 490, 'They are not counted as zero.', 17, C.muted));

add('programs-04', 'Assistance coverage maps', 'Compare municipality releases within the allowed scope.',
    map(36, 174, 562, 365, 'coverage') + panel(621, 174, 303, 365)
    + text(645, 211, 'Sample coverage', 23, C.ink, 650) + label(645, 239, '2026 · RICE SEED')
    + text(645, 298, '24', 48, C.green, 700) + text(717, 296, 'releases', 22, C.ink, 600)
    + line(645, 327, 900, 327) + text(645, 366, '4 illustrative LGUs', 20, C.ink, 600)
    + text(645, 411, 'Color represents', 18, C.muted) + text(645, 440, 'recorded release counts.', 18, C.muted)
    + text(645, 494, 'Not parcel eligibility', 18, C.green, 650) + text(645, 521, 'Schematic areas only', 16, C.muted));

add('operations-01', 'Animal-health services', 'Keep service records separate by species and care type.',
    stat(36, 174, 278, '12', 'Sample service visits') + stat(341, 174, 278, '30', 'Animals in sample visits', C.blue, C.bluePale) + stat(646, 174, 278, 'Oct 2026', 'Sample reporting month', C.purple, C.purplePale)
    + panel(36, 299, 888, 240) + label(60, 331, 'SERVICE') + label(420, 331, 'SPECIES') + label(704, 331, 'ANIMAL COUNT')
    + text(60, 380, 'Vaccination', 22, C.ink, 650) + text(420, 380, 'Cattle', 20) + text(734, 380, '12', 23, C.green, 700)
    + line(60, 401, 900, 401) + text(60, 436, 'Deworming', 22, C.ink, 650) + text(420, 436, 'Goats', 20) + text(734, 436, '18', 23, C.blue, 700)
    + line(60, 457, 900, 457) + text(60, 505, 'Service visits and animal counts are different measures.', 17, C.muted));

add('operations-02', 'Cooperatives & memberships', 'Keep a group profile connected to eligible farmer members.',
    panel(36, 174, 321, 365, C.pale) + circle(196, 237, 35, C.white) + circle(184, 231, 9, C.green) + circle(210, 231, 9, C.green) + path('M 166 255 Q 184 238 200 255 M 193 255 Q 211 238 226 255', 'none', C.green, 4)
    + text(60, 309, 'Sample Growers', 25, C.ink, 700) + text(60, 341, 'Cooperative', 25, C.ink, 700) + label(60, 381, 'SAMPLE LGU') + text(60, 439, '12 members', 33, C.green, 700) + text(60, 497, 'Fictional organization', 17, C.muted)
    + panel(379, 174, 545, 365) + text(404, 212, 'Member list', 24, C.ink, 650) + label(404, 240, '3 OF 12 SAMPLE MEMBERS')
    + [0, 1, 2].map(i => circle(428, 287 + i * 67, 18, C.pale) + text(428, 293 + i * 67, i + 1, 16, C.green, 700, 'text-anchor="middle"') + text(464, 294 + i * 67, `Sample Farmer 00${i + 1}`, 21, C.ink, 600) + line(404, 319 + i * 67, 900, 319 + i * 67)).join('')
    + button(404, 474, 265, 'Export member workbook', false));

add('operations-03', 'Machinery inventory & maintenance', 'See equipment availability and what needs service.',
    stat(36, 174, 278, '8', 'Sample equipment assets') + stat(341, 174, 278, '6', 'Available for office use', C.blue, C.bluePale) + stat(646, 174, 278, '2', 'Need maintenance', C.amber, C.amberPale)
    + panel(36, 299, 888, 240) + label(60, 332, 'EQUIPMENT') + label(436, 332, 'ASSET REFERENCE') + label(732, 332, 'STATUS')
    + text(60, 382, 'Hand tractor', 22, C.ink, 650) + text(436, 382, 'SAMPLE-EQ-01', 19) + chip(715, 358, 179, 'Available')
    + line(60, 402, 900, 402) + text(60, 439, 'Rice thresher', 22, C.ink, 650) + text(436, 439, 'SAMPLE-EQ-02', 19) + chip(715, 415, 179, 'Maintenance due', C.amberPale, C.amber, 15)
    + text(60, 506, 'Keep service dates and equipment condition visible.', 18, C.muted));

add('operations-04', 'Your office at a glance', 'Sample LGU · current registry and 2026 assistance example',
    stat(36, 174, 208, '48', 'Registered farmers') + stat(262, 174, 208, '36', 'With mapped parcels', C.blue, C.bluePale) + stat(488, 174, 208, '18', 'Assistance recipients', C.purple, C.purplePale) + stat(714, 174, 210, '12', 'Awaiting mapping', C.amber, C.amberPale)
    + panel(36, 300, 429, 239) + text(60, 337, 'Registry mapping coverage', 23, C.ink, 650)
    + horizontalBar(60, 382, 381, 'Mapped farmers', 36, 48, C.green) + horizontalBar(60, 454, 381, 'Awaiting mapping', 12, 48, C.amber)
    + panel(486, 300, 438, 239) + text(510, 337, 'Where to start today', 23, C.ink, 650)
    + circle(529, 382, 17, C.amberPale) + text(529, 389, '12', 15, C.amber, 700, 'text-anchor="middle"') + text(563, 389, 'Farmers need parcel mapping', 18, C.ink, 600)
    + line(510, 413, 900, 413) + circle(529, 449, 17, C.bluePale) + text(529, 456, '4', 17, C.blue, 700, 'text-anchor="middle"') + text(563, 456, 'Registry references to review', 18, C.ink, 600)
    + text(510, 510, 'Sample snapshot · 12 Oct 2026', 16, C.muted));

add('connected-01', 'Agriculture & system Assistant', 'Ask a question, then inspect its scope and source.',
    panel(36, 174, 888, 365) + chip(60, 195, 161, 'Office records') + chip(235, 195, 151, 'Sample LGU', C.bluePale, C.blue)
    + rect(343, 247, 557, 58, C.pale, 10) + text(365, 283, 'How many farmers still need mapping?', 23, C.ink, 600)
    + rect(60, 326, 761, 145, C.bg, 10, C.line) + text(82, 364, '12 of 48 sample farmers have no mapped parcel.', 23, C.green, 650)
    + text(82, 403, '1. Open the farmers awaiting mapping.', 19, C.ink) + text(82, 436, '2. Review their records before adding a parcel.', 19, C.ink)
    + label(60, 506, 'SOURCE: SAMPLE REGISTRY · CURRENT COHORT') + text(900, 506, 'Fixed example reply', 16, C.muted, 500, 'text-anchor="end"'));

add('connected-02', 'LGU profiles & ID issuer settings', 'Use the office identity saved by authorized managers.',
    panel(36, 174, 541, 365) + field(60, 211, 493, 'OFFICE NAME', 'Sample LGU Agriculture Office') + field(60, 299, 493, 'OFFICE ADDRESS', 'Sample municipal hall')
    + field(60, 387, 493, 'PRINTED SIGNATORY · OPTIONAL', 'Sample Office Head') + button(60, 478, 184, 'Save office profile')
    + panel(599, 174, 325, 365, C.cream) + text(623, 212, 'ID issuer preview', 23, C.ink, 650)
    + rect(623, 239, 76, 76, C.white, 12, C.line) + text(661, 283, 'LOGO', 16, C.green, 700, 'text-anchor="middle"')
    + text(623, 355, 'Sample LGU', 28, C.green, 700) + text(623, 388, 'Agriculture Office', 21, C.ink, 650)
    + line(623, 461, 899, 461, C.green) + text(761, 486, 'Sample Office Head', 18, C.ink, 600, 'text-anchor="middle"') + text(761, 514, 'Printed signatory example', 15, C.muted, 500, 'text-anchor="middle"'));

add('connected-03', 'Public LGU websites', 'Share approved office information with the community.',
    panel(36, 174, 888, 365) + rect(38, 176, 884, 53, C.white, 10) + text(61, 210, 'Sample LGU', 23, C.green, 700) + text(520, 209, 'Services', 17) + text(639, 209, 'Announcements', 17) + text(820, 209, 'Contact', 17)
    + rect(38, 231, 884, 180, C.dark, 0) + text(63, 278, 'Your local agriculture office', 32, C.white, 700) + text(63, 315, 'Information and services for our community.', 21, '#dce9df', 500) + rect(63, 345, 187, 40, C.yellow, 7) + text(86, 371, 'View sample services', 16, C.ink, 650)
    + text(61, 453, 'Farmer registration', 21, C.ink, 650) + text(61, 487, 'Bring your registry details', 17, C.muted)
    + text(365, 453, 'Office hours', 21, C.ink, 650) + text(365, 487, 'Mon–Fri · 8 AM–5 PM', 17, C.muted)
    + text(676, 453, 'Visit the office', 21, C.ink, 650) + text(676, 487, 'Sample municipal hall', 17, C.muted)
    + text(61, 521, 'Fictional public page · operational records remain private', 15, C.green, 550));

add('connected-04', 'Protected office files', 'Organize the documents your office is allowed to access.',
    chip(36, 174, 278, 'Sample LGU / Backup Folder') + panel(36, 223, 888, 243)
    + label(60, 256, 'FILE') + label(593, 256, 'TYPE') + label(764, 256, 'ACCESS')
    + [ ['Sample field guide', 'PDF'], ['Sample seed inventory', 'XLSX'], ['Sample office note', 'DOCX'] ].map(([name, type], i) => {
        const y = 298 + i * 58;
        return rect(60, y - 20, 30, 36, C.pale, 4) + line(68, y - 8, 82, y - 8, C.green, 2) + line(68, y, 82, y, C.green, 2) + text(108, y + 7, name, 21, C.ink, 600) + text(593, y + 7, type, 18, C.muted) + chip(751, y - 17, 147, 'Office only', C.bluePale, C.blue) + (i < 2 ? line(60, y + 27, 900, y + 27) : '');
    }).join('') + note(36, 490, 888, 'Reference-file folder; automatic database backups are separate.'));

add('administration-01', 'Accounts & geographic access', 'Each person works within an assigned role and area.',
    panel(36, 174, 888, 274) + label(60, 209, 'SAMPLE ACCOUNT') + label(378, 209, 'ROLE') + label(713, 209, 'GEOGRAPHIC SCOPE')
    + [ ['Sample Head', 'Municipal Head', 'Sample LGU'], ['Sample Staff', 'Municipal Staff', 'Sample LGU'], ['Sample Reviewer', 'Provincial Staff', 'Sample Province'] ].map(([name, role, scope], i) => text(60, 263 + i * 65, name, 21, C.ink, 600) + text(378, 263 + i * 65, role, 20, C.ink) + text(713, 263 + i * 65, scope, 18, C.green, 600) + (i < 2 ? line(60, 287 + i * 65, 900, 287 + i * 65) : '')).join('')
    + note(36, 471, 888, 'Visibility and allowed actions are checked on the server.', C.green, C.pale));

add('administration-02', 'Audit trail & accountability', 'Review recorded actions with their office context.',
    panel(36, 174, 888, 365) + chip(60, 196, 217, '12 Oct 2026 · example', C.bluePale, C.blue) + chip(291, 196, 147, 'Sample LGU')
    + line(84, 278, 84, 470, C.line, 3)
    + [ ['09:10', 'Sample Head', 'Updated LGU office profile'], ['09:35', 'Sample Staff', 'Updated SAMPLE-001 registry'], ['10:05', 'Sample Head', 'Exported a scoped report'] ].map(([time, actor, action], i) => {
        const y = 277 + i * 83;
        return circle(84, y + 9, 9, i === 1 ? C.blue : C.green) + text(112, y + 5, time, 17, C.muted, 550) + text(197, y + 5, actor, 20, C.ink, 650) + text(197, y + 33, action, 19, C.muted);
    }).join('') + text(60, 514, 'Only authorized oversight roles can inspect these events.', 17, C.muted));

add('administration-03', 'Privacy & shared editing', 'Keep a colleague’s newer changes from being overwritten.',
    panel(36, 174, 888, 365) + text(60, 215, 'Sample Farmer 001 · record update', 25, C.ink, 650)
    + rect(60, 242, 840, 99, C.amberPale, 10) + circle(92, 278, 15, C.amber) + text(92, 284, '!', 20, C.white, 700, 'text-anchor="middle"') + text(122, 279, 'This record changed while you were editing.', 24, C.amber, 650) + text(122, 313, 'Review the latest saved version before trying again.', 19, C.ink)
    + field(60, 382, 399, 'YOUR FORM VERSION', 'Version 3 · sample') + field(482, 382, 418, 'LATEST SAVED VERSION', 'Version 4 · sample')
    + button(60, 477, 243, 'Reload latest record') + text(330, 503, 'Your changes have not been saved.', 18, C.muted));

add('administration-04', 'Imports, exports & service usage', 'Review incoming data and monitor recorded service use.',
    panel(36, 174, 532, 365) + text(60, 212, 'Sample workbook review', 23, C.ink, 650) + label(60, 242, 'SAMPLE-REGISTRY.XLSX · 10 ROWS')
    + horizontalBar(60, 289, 484, 'Rows ready for review', 8, 10, C.green) + horizontalBar(60, 361, 484, 'Rows needing correction', 2, 10, C.amber)
    + text(60, 447, 'Validate and match within the LGU.', 19, C.ink, 550) + text(60, 494, 'Export only permitted records.', 18, C.muted)
    + panel(591, 174, 333, 365) + text(615, 212, 'Recorded usage', 23, C.ink, 650) + label(615, 242, 'ILLUSTRATIVE DAILY COUNTERS')
    + text(615, 299, '3', 39, C.green, 700) + text(670, 297, 'satellite requests', 19, C.ink)
    + text(615, 362, '2', 39, C.blue, 700) + text(670, 360, 'map export requests', 18, C.ink)
    + line(615, 392, 900, 392) + text(615, 441, 'Local usage counters', 20, C.ink, 650) + text(615, 477, 'Not complete provider billing', 17, C.muted));

await mkdir(output, { recursive: true });
let totalBytes = 0;
for (const preview of previews) {
    const filename = `${preview.name}.svg`;
    await writeFile(resolve(output, filename), preview.svg, 'utf8');
    totalBytes += Buffer.byteLength(preview.svg);
}
console.log(`Generated ${previews.length} fictional SVG previews (${totalBytes.toLocaleString('en-US')} bytes).`);
