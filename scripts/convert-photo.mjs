/*
 * Cuts an uploaded dish photo into 4:5 AVIF and WebP crops plus a tiny blurred
 * placeholder: node scripts/convert-photo.mjs <input> <outDir>
 * Writes <outDir>/<width>.avif and <width>.webp and prints
 * {"width":1600,"height":2000,"placeholder":"data:image/webp;base64,..."}.
 * Used by App\Jobs\ConvertMenuPhoto when PHP's Imagick has no AVIF support.
 */
import sharp from 'sharp';

const WIDTHS = [480, 960, 1600];
const [input, outDir] = process.argv.slice(2);

if (!input || !outDir) {
    console.error('Usage: node scripts/convert-photo.mjs <input> <outDir>');
    process.exit(1);
}

const crop = (width) =>
    sharp(input).rotate().resize(width, Math.round(width * 1.25), {
        fit: 'cover',
        position: 'centre',
    });

for (const width of WIDTHS) {
    await crop(width).avif({ quality: 50 }).toFile(`${outDir}/${width}.avif`);
    await crop(width).webp({ quality: 74 }).toFile(`${outDir}/${width}.webp`);
}

const tiny = await crop(16).blur(1).webp({ quality: 40 }).toBuffer();
const largest = WIDTHS[WIDTHS.length - 1];

console.log(
    JSON.stringify({
        width: largest,
        height: Math.round(largest * 1.25),
        placeholder: `data:image/webp;base64,${tiny.toString('base64')}`,
    }),
);
