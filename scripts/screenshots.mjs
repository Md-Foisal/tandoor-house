/*
 * Saves full-page screenshots of the given paths at 375, 768 and 1280px in
 * light and dark: node scripts/screenshots.mjs <phase> <name>=<path> ...
 * BASE_URL defaults to http://127.0.0.1:8010. Pages are scrolled to the end
 * first so every in-view animation has played.
 */
import { mkdir } from 'node:fs/promises';
import { chromium } from '@playwright/test';

const [phase, ...pages] = process.argv.slice(2);
const baseUrl = process.env.BASE_URL ?? 'http://127.0.0.1:8010';
const widths = [375, 768, 1280];
const themes = ['light', 'dark'];
const outDir = `docs/screenshots/phase-${phase}`;

if (!phase || pages.length === 0) {
    console.error('Usage: node scripts/screenshots.mjs <phase> <name>=<path> ...');
    process.exit(1);
}

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const errors = [];

for (const theme of themes) {
    for (const width of widths) {
        const context = await browser.newContext({
            viewport: { width, height: width < 768 ? 812 : 900 },
            colorScheme: theme,
            deviceScaleFactor: 1,
        });
        const page = await context.newPage();

        page.on('console', (message) => {
            if (message.type() === 'error') {
                errors.push(`${page.url()} [${width} ${theme}]: ${message.text()}`);
            }
        });

        for (const entry of pages) {
            const [name, path] = entry.split('=');

            await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
            await page.evaluate(async () => {
                for (let y = 0; y < document.body.scrollHeight; y += 400) {
                    window.scrollTo(0, y);
                    await new Promise((resolve) => setTimeout(resolve, 60));
                }
                window.scrollTo(0, 0);
            });
            await page.waitForTimeout(900);
            await page.screenshot({
                path: `${outDir}/${name}-${width}-${theme}.jpg`,
                fullPage: true,
                type: 'jpeg',
                quality: 70,
            });
        }

        await context.close();
    }
}

await browser.close();

if (errors.length > 0) {
    console.error(errors.join('\n'));
    process.exit(1);
}

console.log(`Saved ${pages.length * widths.length * themes.length} screenshots to ${outDir}`);
