/*
 * Saves full-page screenshots of the given paths at 375, 768 and 1280px in
 * light and dark: node scripts/screenshots.mjs <phase> <name>=<path> ...
 * BASE_URL defaults to http://127.0.0.1:8010. Pages are scrolled to the end
 * first so every in-view animation has played. Append @<email> to a path to
 * sign in as that demo user first (password "password"):
 * account=/account@customer@tandoorhouse.test
 */
import { mkdir } from 'node:fs/promises';
import { chromium } from '@playwright/test';

const [phase, ...pages] = process.argv.slice(2);
const baseUrl = process.env.BASE_URL ?? 'http://127.0.0.1:8010';
const widths = [375, 768, 1280];
const themes = ['light', 'dark'];
const outDir = `docs/screenshots/phase-${phase}`;

if (!phase || pages.length === 0) {
    console.error(
        'Usage: node scripts/screenshots.mjs <phase> <name>=<path> ...',
    );
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
                errors.push(
                    `${page.url()} [${width} ${theme}]: ${message.text()}`,
                );
            }
        });

        let signedInAs = null;

        for (const entry of pages) {
            const [name, target] = entry.split('=');
            const at = target.indexOf('@');
            const path = at === -1 ? target : target.slice(0, at);
            const email = at === -1 ? null : target.slice(at + 1);

            if (email !== signedInAs) {
                await context.clearCookies();
                signedInAs = null;

                if (email !== null) {
                    await page.goto(`${baseUrl}/login`, {
                        waitUntil: 'networkidle',
                    });
                    await page.fill('input[name="email"]', email);
                    await page.fill('input[name="password"]', 'password');
                    await Promise.all([
                        page.waitForURL(
                            (url) => !url.pathname.startsWith('/login'),
                        ),
                        page.click('button[type="submit"]'),
                    ]);
                    signedInAs = email;
                }
            }

            await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
            await page.evaluate(async () => {
                for (let y = 0; y < document.body.scrollHeight; y += 400) {
                    window.scrollTo(0, y);
                    await new Promise((resolve) => setTimeout(resolve, 60));
                }
                window.scrollTo(0, 0);
            });
            await page.waitForFunction(
                () =>
                    Array.from(document.images).every(
                        (image) => image.complete,
                    ),
                null,
                { timeout: 15000 },
            );
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

console.log(
    `Saved ${pages.length * widths.length * themes.length} screenshots to ${outDir}`,
);
