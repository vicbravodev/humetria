// Draws the PNGs the emails load (mail clients do not show SVG) into public/mail, at 2x.
// Run it again after changing a color or an icon: `node scripts/mail-images.mjs`.
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const out = new URL('../public/mail/', import.meta.url).pathname;
const track = '#E3EAE7';

// Lucide icons, as the designs in docs/design-system draw them.
const icons = {
    lock: [
        '#0B7A6F',
        '<rect x="4" y="11" width="16" height="11" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    ],
    info: [
        '#7A5714',
        '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    ],
    monitor: [
        '#56635F',
        '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
    ],
    'hand-heart': [
        '#14211F',
        '<path d="M11 14h2a2 2 0 1 0 0-4h-3c-.6 0-1.1.2-1.4.6L3 16"/><path d="m7 20 1.6-1.4c.3-.4.8-.6 1.4-.6h4c1.1 0 2.1-.4 2.8-1.2l4.6-4.4a2 2 0 0 0-2.75-2.91l-4.2 3.9"/><path d="m2 15 6 6"/><path d="M19.5 8.5c.7-.7 1.5-1.6 1.5-2.7A2.73 2.73 0 0 0 16 4a2.78 2.78 0 0 0-5 1.8c0 1.2.8 2 1.5 2.8L16 12Z"/>',
    ],
    'file-text': [
        '#14211F',
        '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/>',
    ],
};

const icon = ([color, body]) =>
    `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">${body}</svg>`;

// The participation arc: an arch whose filled part is `percent` of its length.
const arc = (percent, color) => {
    const path = 'M10 49V33A22 22 0 0 1 54 33V49';
    const stroke =
        'fill="none" stroke-width="5.49" stroke-linecap="round" pathLength="100"';

    return `<svg xmlns="http://www.w3.org/2000/svg" width="140" height="101" viewBox="0 5 64 46"><path d="${path}" stroke="${track}" ${stroke}/>${
        percent > 0
            ? `<path d="${path}" stroke="${color}" ${stroke} stroke-dasharray="100" stroke-dashoffset="${100 - percent}"/>`
            : ''
    }</svg>`;
};

const browser = await chromium.launch();
const page = await browser.newPage({ deviceScaleFactor: 2 });

async function draw(svg, file) {
    await page.setContent(
        `<body style="margin:0;background:transparent">${svg}</body>`,
    );
    await page
        .locator('svg')
        .screenshot({ path: `${out}${file}`, omitBackground: true });
}

mkdirSync(`${out}arc`, { recursive: true });

for (const [name, spec] of Object.entries(icons)) {
    await draw(icon(spec), `${name}.png`);
}

// Primary while it runs; the deeper chart green once it closed.
for (let percent = 0; percent <= 100; percent++) {
    await draw(arc(percent, '#0B7A6F'), `arc/${percent}.png`);
    await draw(arc(percent, '#1D5C55'), `arc/closed-${percent}.png`);
}

await browser.close();
