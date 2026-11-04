/*
 * Lighthouse performance audit (mobile preset: Moto G Power, simulated slow 4G):
 * node scripts/perf-audit.mjs [label]  (ONLY=Home,Menu limits the pages)
 * Writes storage/app/review/perf-report.md. Requests under /herd-studio/ come
 * from Laravel Herd's dev toolbar, so they are listed apart and subtracted from
 * the JS totals. Staff pages sign in with the demo manager and send the session
 * cookie as an extra header. BASE_URL defaults to http://tandoor-house.test.
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from '@playwright/test';
import lighthouse from 'lighthouse';

const baseUrl = process.env.BASE_URL ?? 'http://tandoor-house.test';
const label = process.argv[2] ?? 'run';
const port = 9333;
const pages = [
    { name: 'Home', path: '/' },
    { name: 'Menu', path: '/menu' },
    { name: 'Dish', path: '/menu/bhel-puri' },
    { name: 'Book', path: '/book' },
    { name: 'Visit', path: '/visit' },
    {
        name: 'Staff orders (signed in)',
        path: '/staff/orders',
        email: process.env.STAFF_EMAIL ?? 'owner@tandoorhouse.test',
    },
];

const browser = await chromium.launch({
    args: [`--remote-debugging-port=${port}`],
});

async function cookieHeader(email) {
    const context = await browser.newContext();
    const page = await context.newPage();

    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password');
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ]);
    const cookies = await context.cookies();
    await context.close();

    return cookies.map((cookie) => `${cookie.name}=${cookie.value}`).join('; ');
}

const kb = (bytes) => `${(bytes / 1024).toFixed(0)} KB`;
const rows = [];
const details = [];

const only = process.env.ONLY?.split(',');

for (const entry of pages.filter((p) => !only || only.includes(p.name))) {
    const extraHeaders = entry.email
        ? { Cookie: await cookieHeader(entry.email) }
        : undefined;
    const result = await lighthouse(
        baseUrl + entry.path,
        {
            port,
            output: 'json',
            logLevel: 'error',
            onlyCategories: ['performance'],
            extraHeaders,
            blockedUrlPatterns: ['*herd-studio*'],
        },
        undefined,
    );
    const lhr = result.lhr;
    const audits = lhr.audits;
    const requests = audits['network-requests'].details?.items ?? [];
    const herd = (item) => item.url.includes('/herd-studio/');
    const scripts = requests.filter(
        (item) => item.resourceType === 'Script' && !herd(item),
    );
    const herdBytes = requests
        .filter(herd)
        .reduce((sum, item) => sum + (item.transferSize ?? 0), 0);
    const js = scripts.reduce((sum, item) => sum + (item.transferSize ?? 0), 0);
    const lcpNode =
        audits['largest-contentful-paint-element']?.details?.items?.[0]
            ?.items?.[0]?.node;
    const lcpElement = lcpNode
        ? `${lcpNode.nodeLabel ?? ''} (${(lcpNode.selector ?? '').slice(0, 70)})`
        : 'none';
    const opportunities = Object.values(audits)
        .filter(
            (audit) =>
                audit.details?.overallSavingsMs > 0 ||
                audit.details?.overallSavingsBytes > 10240,
        )
        .sort(
            (a, b) =>
                (b.details.overallSavingsMs ?? 0) - (a.details.overallSavingsMs ?? 0),
        )
        .slice(0, 5)
        .map(
            (audit) =>
                `${audit.title} (${Math.round(audit.details.overallSavingsMs ?? 0)} ms, ${kb(audit.details.overallSavingsBytes ?? 0)})`,
        );

    rows.push(
        `| ${entry.name} | ${Math.round(lhr.categories.performance.score * 100)} | ${(audits['largest-contentful-paint'].numericValue / 1000).toFixed(2)} s | ${audits['cumulative-layout-shift'].numericValue.toFixed(3)} | ${Math.round(audits['total-blocking-time'].numericValue)} ms | ${kb(js)} | ${scripts.length} | ${kb(herdBytes)} |`,
    );
    details.push(
        `### ${entry.name} \`${entry.path}\`\n\n- LCP element: ${lcpElement}\n- Opportunities: ${opportunities.join('; ') || 'none'}\n- Largest scripts: ${
            scripts
                .sort((a, b) => b.transferSize - a.transferSize)
                .slice(0, 4)
                .map((item) => `${item.url.split('/').pop()} ${kb(item.transferSize)}`)
                .join(', ') || 'none'
        }\n`,
    );
    console.log(`${entry.name} done`);
}

await browser.close();
await mkdir('storage/app/review', { recursive: true });
await writeFile(
    'storage/app/review/perf-report.md',
    `# Performance report (${label})\n\nLighthouse ${lighthouse.version ?? ''} mobile preset, simulated slow 4G, Moto G Power. Budget: LCP < 2.5 s, CLS < 0.1, INP < 200 ms (TBT is the lab proxy; keep it under 200 ms). JS excludes Herd's dev toolbar (/herd-studio/), shown in the last column.\n\n| Page | Score | LCP | CLS | TBT | JS transferred | Scripts | Herd toolbar |\n| --- | --- | --- | --- | --- | --- | --- | --- |\n${rows.join('\n')}\n\n${details.join('\n')}`,
);
console.log('Wrote storage/app/review/perf-report.md');
