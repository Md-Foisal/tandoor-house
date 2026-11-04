/*
 * Curated README screenshots: one viewport-sized WebP per role, device and
 * colour scheme in docs/media/screens/.
 * node scripts/readme-shots.mjs [name ...]
 * Run on fresh demo data. BASE_URL defaults to http://tandoor-house.test.
 */
import { execFileSync } from 'node:child_process';
import { mkdir } from 'node:fs/promises';
import { chromium } from '@playwright/test';
import sharp from 'sharp';

const baseUrl = process.env.BASE_URL ?? 'http://tandoor-house.test';
const outDir = 'docs/media/screens';
const devices = {
    phone: { viewport: { width: 390, height: 844 }, scale: 2, width: 560 },
    desktop: { viewport: { width: 1280, height: 800 }, scale: 1, width: 1280 },
};
const schemes = ['light', 'dark'];

const table = execFileSync(
    'php',
    [
        'artisan',
        'tinker',
        '--execute',
        'echo App\\Models\\DiningTable::first()->qr_token;',
    ],
    { encoding: 'utf8' },
)
    .trim()
    .split('\n')
    .pop();

// [name, path, signed-in demo user or null, devices]
const shots = [
    ['home', '/', null, ['phone', 'desktop']],
    ['menu', '/menu', null, ['phone', 'desktop']],
    ['dish', '/menu/bhel-puri', null, ['phone', 'desktop']],
    ['book', '/book', null, ['phone', 'desktop']],
    ['visit', '/visit', null, ['phone']],
    ['account', '/account', 'customer', ['phone', 'desktop']],
    ['table-qr', `/t/${table}`, null, ['phone']],
    ['cashier-orders', '/staff/orders', 'cashier', ['desktop', 'phone']],
    ['kitchen-display', '/staff/kitchen', 'kitchen', ['desktop']],
    ['waiter-floor', '/staff/floor', 'waiter', ['desktop', 'phone']],
    ['waiter-bookings', '/staff/reservations', 'waiter', ['desktop']],
    ['rider-deliveries', '/staff/deliveries', 'rider', ['phone']],
    ['manager-menu', '/staff/menu', 'manager', ['desktop']],
    ['manager-stock', '/staff/inventory', 'manager', ['desktop']],
    ['manager-team', '/staff/team', 'manager', ['desktop']],
    ['owner-today', '/staff', 'owner', ['desktop', 'phone']],
    ['owner-reports', '/staff/reports', 'owner', ['desktop']],
    ['owner-settings', '/staff/settings', 'owner', ['desktop']],
];

const only = process.argv.slice(2);
const browser = await chromium.launch();
const states = new Map();

async function stateFor(user) {
    if (states.has(user)) {
        return states.get(user);
    }

    const context = await browser.newContext({
        viewport: devices.desktop.viewport,
    });
    const page = await context.newPage();

    if (user) {
        await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Accept all' }).click();
        await page.fill('input[name="email"]', `${user}@tandoorhouse.test`);
        await page.fill('input[name="password"]', 'password');
        await Promise.all([
            page.waitForURL((url) => !url.pathname.startsWith('/login'), {
                waitUntil: 'commit',
            }),
            page.getByRole('button', { name: 'Sign in', exact: true }).click(),
        ]);
        await page.waitForLoadState('networkidle');
    } else {
        await page.goto(`${baseUrl}/menu`, { waitUntil: 'networkidle' });
        await page.getByRole('button', { name: 'Accept all' }).click();
    }

    const state = await context.storageState();

    await context.close();
    states.set(user, state);

    return state;
}

await mkdir(outDir, { recursive: true });

for (const [name, path, user, list] of shots) {
    if (only.length && !only.includes(name)) {
        continue;
    }

    const state = await stateFor(user);

    for (const device of list) {
        for (const scheme of schemes) {
            const { viewport, scale, width } = devices[device];
            const context = await browser.newContext({
                viewport,
                deviceScaleFactor: scale,
                isMobile: device === 'phone',
                hasTouch: device === 'phone',
                colorScheme: scheme,
                storageState: state,
            });

            // Laravel Herd injects a dev toolbar; this flag stops it mounting.
            await context.addInitScript(() => {
                window.__HERD_STUDIO__ = true;
            });
            const page = await context.newPage();

            await page.goto(`${baseUrl}${String(path)}`, {
                waitUntil: 'networkidle',
            });
            await page.evaluate(() =>
                window.scrollTo(0, document.body.scrollHeight),
            );
            await page.waitForTimeout(400);
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.waitForTimeout(1200);

            const png = await page.screenshot();

            await sharp(png)
                .resize({ width })
                .webp({ quality: 78 })
                .toFile(`${outDir}/${String(name)}-${device}-${scheme}.webp`);
            await context.close();
        }
    }

    console.log(name);
}

await browser.close();
