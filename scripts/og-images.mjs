/*
 * Builds the 1200x630 Open Graph JPEGs from the existing photos:
 * node scripts/og-images.mjs
 * Writes public/media/og/{default,home,menu,story,visit}.jpg and one
 * dish-<slug>.jpg per dish photo in public/media/dish.
 */
import { readdirSync, mkdirSync } from 'node:fs';
import sharp from 'sharp';

const NAME = process.env.RESTAURANT_NAME ?? 'Tandoor House';
const media = new URL('../public/media/', import.meta.url).pathname;
const out = `${media}og/`;
mkdirSync(out, { recursive: true });

const escape = (text) =>
    text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

const overlay = (caption) =>
    Buffer.from(`<svg width="1200" height="630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="fade" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0.45" stop-color="#16110d" stop-opacity="0"/>
      <stop offset="1" stop-color="#16110d" stop-opacity="0.86"/>
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#fade)"/>
  <rect x="64" y="476" width="56" height="6" fill="#f7f1e8"/>
  ${caption ? `<text x="64" y="552" font-family="Georgia, 'Times New Roman', serif" font-size="64" fill="#f7f1e8">${escape(caption)}</text>` : ''}
  <text x="64" y="594" font-family="Helvetica, Arial, sans-serif" font-size="26" letter-spacing="3" fill="#f7f1e8">${escape(NAME.toUpperCase())}</text>
</svg>`);

const make = async (source, file, caption) => {
    await sharp(`${media}${source}`)
        .rotate()
        .resize(1200, 630, { fit: 'cover', position: 'attention' })
        .composite([{ input: overlay(caption) }])
        .jpeg({ quality: 82, mozjpeg: true })
        .toFile(`${out}${file}.jpg`);
};

const pages = {
    default: ['hero/poster-1600.webp', null],
    home: ['hero/poster-1600.webp', 'Fire first.'],
    menu: ['story/tandoor-1600.webp', 'The menu'],
    story: ['story/room-1600.webp', 'Our story'],
    visit: ['story/table-1600.webp', 'Visit us'],
};

for (const [file, [source, caption]] of Object.entries(pages)) {
    await make(source, file, caption);
}

const files = readdirSync(`${media}dish`);
const slugs = new Set(
    files
        .filter((name) => name.endsWith('-960.webp'))
        .map((name) => name.replace(/-960\.webp$/, '')),
);

for (const slug of slugs) {
    const largest = files.includes(`${slug}-1600.webp`) ? 1600 : 960;
    const words = slug.replace(/-/g, ' ');
    await make(
        `dish/${slug}-${largest}.webp`,
        `dish-${slug}`,
        words.charAt(0).toUpperCase() + words.slice(1),
    );
}

console.log(`Wrote ${Object.keys(pages).length + slugs.size} images to ${out}`);
