/*
 * Draws the staff app icons from the brand mark (the tandoor arch with its ember):
 * node scripts/pwa-icons.mjs
 * Writes icon-192.png, icon-512.png, icon-maskable-512.png and apple-touch-icon.png
 * to public/staff/icons/. Colours are the dark paper, ink and ember tokens from
 * docs/design-system.md, matching the kitchen display.
 */
import { mkdir } from 'node:fs/promises';
import sharp from 'sharp';

const PAPER = '#16110D';
const INK = '#F2E8DA';
const EMBER = '#E8784A';
const OUT = new URL('../public/staff/icons/', import.meta.url).pathname;

// `fill` is the share of the canvas the 32-unit mark occupies; maskable icons
// keep it inside the 80% safe circle.
const mark = (size, fill) => {
    const scale = (size * fill) / 32;
    const offset = (size - 32 * scale) / 2;

    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
<rect width="${size}" height="${size}" fill="${PAPER}"/>
<g transform="translate(${offset} ${offset}) scale(${scale})">
<path d="M5 29V15.5C5 9.15 9.92 4 16 4s11 5.15 11 11.5V29" fill="none" stroke="${INK}" stroke-width="2.5" stroke-linecap="round"/>
<path d="M16 27c-3.2 0-5-2.1-5-4.6 0-2.9 2.6-4.1 3.4-7.4.9 1.7 1 2.9.6 4.2 1.6-.6 2.9-2.2 3.2-4 1.8 1.8 2.8 4.3 2.8 6.6 0 3-2 5.2-5 5.2Z" fill="${EMBER}"/>
<path d="M3 29h26" stroke="${INK}" stroke-width="2.5" stroke-linecap="round"/>
</g></svg>`);
};

const icons = [
    ['icon-192.png', 192, 0.62],
    ['icon-512.png', 512, 0.62],
    ['icon-maskable-512.png', 512, 0.5],
    ['apple-touch-icon.png', 180, 0.62],
];

await mkdir(OUT, { recursive: true });

for (const [file, size, fill] of icons) {
    await sharp(mark(size, fill), { density: 300 }).resize(size, size).png().toFile(OUT + file);
    console.log(`wrote ${file}`);
}
