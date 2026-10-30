/*
 * Cuts a tall full-page screenshot into side-by-side columns so it can be
 * looked at in one small image: node scripts/contact-sheet.mjs <in> <out> [segmentHeight]
 */
import sharp from 'sharp';

const [input, output, segment = '2400'] = process.argv.slice(2);

if (!input || !output) {
    console.error(
        'Usage: node scripts/contact-sheet.mjs <in.jpg> <out.jpg> [segmentHeight]',
    );
    process.exit(1);
}

const height = Number(segment);
const { width, height: total } = await sharp(input).metadata();
const columns = Math.ceil(total / height);
const tiles = [];

for (let index = 0; index < columns; index++) {
    const slice = Math.min(height, total - index * height);

    tiles.push({
        input: await sharp(input)
            .extract({ left: 0, top: index * height, width, height: slice })
            .toBuffer(),
        left: index * (width + 10),
        top: 0,
    });
}

await sharp({
    create: {
        width: columns * (width + 10),
        height,
        channels: 3,
        background: '#888888',
    },
})
    .composite(tiles)
    .jpeg({ quality: 70 })
    .toFile(output);

console.log(`${columns} columns → ${output}`);
