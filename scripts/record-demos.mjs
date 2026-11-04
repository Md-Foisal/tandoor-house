/*
 * Records the README walkthroughs against the running app as WebM files:
 * node scripts/record-demos.mjs <out-dir> [guest|staff|table ...]
 * Run it on fresh demo data (php artisan migrate:fresh --seed) with the queue
 * worker and Reverb running. BASE_URL defaults to http://tandoor-house.test.
 * The files are converted to MP4 and animated WebP with ffmpeg outside the
 * project (see docs/media/README.md).
 */
import { mkdir, readFile, rename, writeFile } from 'node:fs/promises';
import { chromium } from '@playwright/test';

const [outDir, ...wanted] = process.argv.slice(2);
const baseUrl = process.env.BASE_URL ?? 'http://tandoor-house.test';
const phone = { width: 390, height: 844 };
const desktop = { width: 1280, height: 720 };

if (!outDir) {
    console.error(
        'Usage: node scripts/record-demos.mjs <out-dir> [guest|staff]',
    );
    process.exit(1);
}

await mkdir(outDir, { recursive: true });
const browser = await chromium.launch();
const pause = (page, ms = 900) => page.waitForTimeout(ms);

async function context(viewport, options = {}) {
    const mobile = viewport.width < 600;
    const created = await browser.newContext({
        viewport,
        deviceScaleFactor: mobile ? 2 : 1,
        isMobile: mobile,
        hasTouch: mobile,
        ...options,
    });

    // Laravel Herd injects a dev toolbar; this flag stops it mounting.
    await created.addInitScript(() => {
        window.__HERD_STUDIO__ = true;
    });

    return created;
}

async function consented(viewport) {
    const setup = await context(viewport);
    const page = await setup.newPage();

    await page.goto(`${baseUrl}/menu`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Accept all' }).click();
    await pause(page, 400);
    const state = await setup.storageState();

    await setup.close();

    return state;
}

async function signedIn(email, viewport) {
    const setup = await context(viewport);
    const page = await setup.newPage();

    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.getByRole('button', { name: 'Accept all' }).click();
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), {
            waitUntil: 'commit',
        }),
        page.getByRole('button', { name: 'Sign in', exact: true }).click(),
    ]);
    await page.waitForLoadState('networkidle');
    const state = await setup.storageState();

    await setup.close();

    return state;
}

async function recorded(name, viewport, storageState) {
    const created = await context(viewport, {
        storageState,
        recordVideo: {
            dir: `${outDir}/raw-${name}`,
            size: { width: viewport.width * 2, height: viewport.height * 2 },
        },
    });

    return { created, page: await created.newPage() };
}

async function finish(name, page, created) {
    await pause(page, 1200);
    const video = page.video();

    await created.close();
    await rename(await video.path(), `${outDir}/${name}.webm`);
}

/**
 * The demo customer orders for collection on a phone and pays with a test
 * card. Signed in, because only signed-in customers get live updates over
 * Reverb (guests poll every 15 seconds). Empty the customer's saved basket
 * first: php artisan tinker --execute 'App\Models\Cart::query()->delete();'
 */
async function guest() {
    const state = await signedIn('customer@tandoorhouse.test', phone);
    const { created, page } = await recorded('guest', phone, state);

    await page.goto(`${baseUrl}/menu`, { waitUntil: 'networkidle' });
    await pause(page, 1200);

    await page.getByRole('button', { name: 'Filters' }).click();
    await pause(page, 600);
    await page
        .getByRole('dialog')
        .getByText('Vegetarian', { exact: true })
        .click();
    await pause(page, 600);
    await pause(page, 500);
    await page.getByRole('button', { name: /^Show \d+ dishes/ }).click();
    await pause(page, 1800);
    await page.mouse.wheel(0, 500);
    await pause(page, 1200);

    await page.getByRole('link', { name: 'Paneer Shashlik' }).first().click();
    await page.waitForLoadState('networkidle');
    await pause(page, 1200);
    await page
        .getByRole('button', { name: 'One more Paneer Shashlik' })
        .click();
    await pause(page, 600);
    await page.getByRole('button', { name: /^Add to basket/ }).click();
    await pause(page, 1200);

    await page.goto(`${baseUrl}/menu`, { waitUntil: 'networkidle' });
    await page
        .getByRole('button', { name: 'Add Butter Naan to basket' })
        .click();
    await pause(page, 900);
    await page
        .getByRole('link', { name: /Basket, \d+ items?/ })
        .last()
        .click();
    await page.waitForLoadState('networkidle');
    await pause(page, 1500);
    await page.getByRole('link', { name: /^Go to checkout/ }).click();
    await page.waitForLoadState('networkidle');
    await pause(page, 900);

    await page.getByText('Pickup', { exact: true }).click();
    await pause(page, 500);
    await page.getByRole('button', { name: 'Continue' }).click();
    await pause(page, 700);
    await page.getByText('13:00', { exact: true }).click();
    await pause(page, 500);
    await page.getByRole('button', { name: 'Continue' }).click();
    await pause(page, 700);

    await pause(page, 1200);
    await page.getByRole('button', { name: 'Continue' }).click();
    await pause(page, 900);

    await page.getByText('Card', { exact: true }).first().click();
    await pause(page, 500);
    await page.getByRole('button', { name: /^Continue to payment/ }).click();
    await page.waitForURL(/\/pay/);
    await pause(page, 1200);
    await page.getByText('Visa ending 4242').click();
    await pause(page, 500);
    await page
        .getByRole('button', { name: /^Pay £/ })
        .first()
        .click();
    await page.waitForURL((url) => !url.pathname.endsWith('/pay'));
    await pause(page, 2200);

    const order = page.url().match(/orders\/([A-Z0-9-]+)/)?.[1] ?? '';

    await writeFile(
        `${outDir}/guest-order.json`,
        JSON.stringify({
            url: page.url(),
            order,
            state: await created.storageState(),
        }),
    );
    await finish('guest', page, created);
}

/**
 * The order from guest() travels through the staff panel while the guest's
 * phone watches it: two recordings made side by side, joined afterwards.
 */
async function staff() {
    const {
        url,
        order,
        state: guestState,
    } = JSON.parse(await readFile(`${outDir}/guest-order.json`, 'utf8'));
    const cashier = await signedIn('cashier@tandoorhouse.test', desktop);
    const kitchen = await signedIn('kitchen@tandoorhouse.test', desktop);
    const phoneRec = await recorded('staff-phone', phone, guestState);
    const deskRec = await recorded('staff-desk', desktop, cashier);
    const phonePage = phoneRec.page;
    const desk = deskRec.page;

    await Promise.all([
        phonePage.goto(url, { waitUntil: 'networkidle' }),
        desk.goto(`${baseUrl}/staff/orders`, { waitUntil: 'networkidle' }),
    ]);
    await pause(desk, 2000);

    const card = desk.locator('article').filter({ hasText: order });

    await card.scrollIntoViewIfNeeded();
    await pause(desk, 1000);
    await card.getByRole('button', { name: 'Accept' }).click();
    await pause(desk, 5500);

    await deskRec.created.clearCookies();
    await deskRec.created.addCookies(kitchen.cookies);
    await desk.goto(`${baseUrl}/staff/kitchen`, { waitUntil: 'networkidle' });
    await pause(desk, 2000);

    const ticket = desk.locator('article').filter({ hasText: order });

    await ticket.scrollIntoViewIfNeeded();
    await pause(desk, 1200);
    await ticket.getByRole('button', { name: 'Bump ticket' }).click();
    await pause(desk, 5500);

    await Promise.all([
        finish('staff-phone', phonePage, phoneRec.created),
        finish('staff-desk', desk, deskRec.created),
    ]);
}

/** A guest books a table for six, which asks for a deposit. */
async function booking() {
    const state = await consented(phone);
    const { created, page } = await recorded('booking', phone, state);

    await page.goto(`${baseUrl}/book`, { waitUntil: 'networkidle' });
    await pause(page, 1500);
    await page.locator('label:has(input[aria-label="Table for 6"])').click();
    await pause(page, 900);
    await page
        .locator('label:has(input[aria-label="Friday 6 November"])')
        .click();
    await pause(page, 1200);
    await page.locator('label', { hasText: '19:00' }).first().click();
    await pause(page, 1200);

    await page
        .locator('input[name="name"]')
        .pressSequentially('Priya Shah', { delay: 45 });
    await page
        .locator('input[name="email"]')
        .pressSequentially('priya@example.com', { delay: 30 });
    await page
        .locator('input[name="phone"]')
        .pressSequentially('07700 900123', { delay: 30 });
    await page
        .locator('textarea[name="notes"]')
        .pressSequentially('Birthday dinner, one vegan guest', { delay: 30 });
    await pause(page, 800);
    await page.getByRole('button', { name: /^Hold the table/ }).click();
    await page.waitForURL(/\/pay/);
    await page.evaluate(() => window.scrollTo(0, 0));
    await pause(page, 1800);
    await page.getByText('Visa ending 4242').click();
    await pause(page, 500);
    await page
        .getByRole('button', { name: /^Pay £/ })
        .first()
        .click();
    await page.waitForURL((url) => !url.pathname.endsWith('/pay'));
    await page.evaluate(() => window.scrollTo(0, 0));
    await pause(page, 2500);

    await writeFile(`${outDir}/booking-url.txt`, page.url());
    await finish('booking', page, created);
}

const jobs = { guest, staff, booking };

for (const name of wanted.length ? wanted : Object.keys(jobs)) {
    await jobs[name]();
    console.log(`${name} recorded`);
}

await browser.close();
