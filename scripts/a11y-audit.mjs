/*
 * WCAG 2.2 AA audit: axe + target size + focus visibility + reduced motion.
 * node scripts/a11y-audit.mjs [--only=/visit,/checkout]   (BASE_URL defaults to http://tandoor-house.test)
 * Report: storage/app/review/a11y-report.md
 */
import { mkdir, writeFile } from 'node:fs/promises';
import { chromium } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const baseUrl = process.env.BASE_URL ?? 'http://tandoor-house.test';
const CUSTOMER = 'customer@tandoorhouse.test';
const OWNER = 'owner@tandoorhouse.test';
const pages = [
    ['/', null],
    ['/menu', null],
    ['/menu/bhel-puri', null],
    ['/basket', null],
    ['/checkout', CUSTOMER],
    ['/book', null],
    ['/story', null],
    ['/visit', null],
    ['/allergens', null],
    ['/login', null],
    ['/register', null],
    ['/account', CUSTOMER],
    ['/account/orders', CUSTOMER],
    ...[
        '',
        '/orders',
        '/kitchen',
        '/floor',
        '/reservations',
        '/deliveries',
        '/menu',
        '/inventory',
        '/team',
        '/reports',
        '/reviews',
        '/activity',
        '/settings',
    ].map((p) => ['/staff' + p, OWNER]),
];
const only = process.argv
    .find((a) => a.startsWith('--only='))
    ?.slice(7)
    .split(',')
    .filter(Boolean);
if (only?.length)
    pages.splice(0, pages.length, ...pages.filter(([p]) => only.includes(p)));
const widths = [375, 1280];
const schemes = ['light', 'dark'];
const tags = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

const browser = await chromium.launch();
const sessions = new Map();
for (const email of new Set(pages.map((p) => p[1]).filter(Boolean))) {
    const context = await browser.newContext();
    const page = await context.newPage();
    await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
    await page.fill('input[name="email"]', email);
    await page.fill('input[name="password"]', 'password');
    await Promise.all([
        page.waitForURL((u) => !u.pathname.startsWith('/login')),
        page.click('button[type="submit"]'),
    ]);
    sessions.set(email, await context.storageState());
    await context.close();
}

// rule -> { impact, help, pages: Map<path, Map<selector, count>> }
const findings = new Map();
const failures = [];
function add(rule, impact, help, path, selector, variant) {
    if (!findings.has(rule))
        findings.set(rule, { impact, help, pages: new Map(), total: 0 });
    const f = findings.get(rule);
    if (!f.pages.has(path)) f.pages.set(path, new Map());
    const m = f.pages.get(path);
    if (!m.has(selector)) m.set(selector, new Set());
    m.get(selector).add(variant);
    f.total++;
}

const SEL =
    'a[href],button,input:not([type=hidden]),select,textarea,summary,[role=button],[role=link],[role=tab],[role=checkbox],[role=switch],[role=menuitem],[tabindex]:not([tabindex="-1"])';

for (const [path, email] of pages) {
    for (const width of widths) {
        for (const scheme of schemes) {
            const variant = `${width}/${scheme}`;
            const context = await browser.newContext({
                viewport: { width, height: width < 768 ? 812 : 900 },
                colorScheme: scheme,
                storageState: email ? sessions.get(email) : undefined,
                hasTouch: width < 768,
            });
            await context.addInitScript(() => {
                window.__HERD_STUDIO__ = true;
            });
            const page = await context.newPage();
            try {
                await page.goto(baseUrl + path, { waitUntil: 'networkidle' });
                await page.waitForTimeout(1200);

                const axe = await new AxeBuilder({ page })
                    .withTags(tags)
                    .analyze();
                for (const v of axe.violations) {
                    for (const n of v.nodes) {
                        add(
                            v.id,
                            v.impact ?? 'minor',
                            v.help,
                            path,
                            n.target.join(' '),
                            variant,
                        );
                    }
                }

                const sizes = await page.evaluate((sel) => {
                    const out = [];
                    const path = (el) => {
                        const parts = [];
                        for (
                            let e = el;
                            e && e.nodeType === 1 && parts.length < 4;
                            e = e.parentElement
                        ) {
                            let s = e.tagName.toLowerCase();
                            if (e.id) {
                                parts.unshift(`${s}#${e.id}`);
                                break;
                            }
                            const c = [...e.classList].slice(0, 2).join('.');
                            parts.unshift(c ? `${s}.${c}` : s);
                        }
                        return parts.join(' > ');
                    };
                    for (const el of document.querySelectorAll(sel)) {
                        const r = el.getBoundingClientRect();
                        const cs = getComputedStyle(el);
                        if (
                            r.width === 0 ||
                            r.height === 0 ||
                            cs.visibility === 'hidden' ||
                            cs.display === 'none'
                        )
                            continue;
                        if (
                            el.disabled ||
                            el.closest('[aria-hidden=true],[inert]')
                        )
                            continue;
                        if (r.right < 0 || (r.bottom < 0 && false)) continue;
                        // sr-only / off-screen skip links
                        if (r.width <= 1 || r.height <= 1) continue;
                        const inline =
                            el.tagName === 'A' &&
                            cs.display === 'inline' &&
                            el.closest('p,li,span,div')?.textContent.trim()
                                .length >
                                el.textContent.trim().length + 10;
                        if (inline) continue;
                        if (r.width < 44 || r.height < 44) {
                            out.push({
                                sel: path(el),
                                w: Math.round(r.width),
                                h: Math.round(r.height),
                                text: (
                                    el.getAttribute('aria-label') ||
                                    el.textContent ||
                                    ''
                                )
                                    .trim()
                                    .slice(0, 25),
                            });
                        }
                    }
                    return out;
                }, SEL);
                for (const s of sizes) {
                    const tiny = s.w < 24 || s.h < 24;
                    if (tiny)
                        add(
                            'target-size-24 (WCAG 2.5.8)',
                            'serious',
                            'Interactive target smaller than 24x24 CSS px',
                            path,
                            `${s.sel} [${s.w}x${s.h}] "${s.text}"`,
                            variant,
                        );
                    else if (width < 768)
                        add(
                            'target-size-44 (project rule)',
                            'moderate',
                            'Touch target smaller than 44x44 CSS px',
                            path,
                            `${s.sel} [${s.w}x${s.h}] "${s.text}"`,
                            variant,
                        );
                }

                // focus visibility
                await page.evaluate(() => document.body.focus());
                for (let i = 0; i < 15; i++) {
                    await page.keyboard.press('Tab');
                    const r = await page.evaluate(() => {
                        const el = document.activeElement;
                        if (!el || el === document.body) return null;
                        const cs = getComputedStyle(el);
                        const outline =
                            cs.outlineStyle !== 'none' &&
                            parseFloat(cs.outlineWidth) > 0;
                        const shadow = cs.boxShadow !== 'none';
                        const label = `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className && typeof el.className === 'string' ? '.' + el.className.split(/\s+/).slice(0, 2).join('.') : ''} "${(el.getAttribute('aria-label') || el.textContent || '').trim().slice(0, 25)}"`;
                        return {
                            ok: outline || shadow,
                            label,
                            sameAsBody: false,
                        };
                    });
                    if (r && !r.ok)
                        add(
                            'focus-visible',
                            'serious',
                            'Focused element has no outline or box-shadow',
                            path,
                            r.label,
                            variant,
                        );
                }
            } catch (e) {
                failures.push(
                    `${path} ${variant}: ${e.message.split('\n')[0]}`,
                );
            }
            await context.close();

            // reduced motion (once per path/width, light only)
            if (scheme === 'light') {
                const rc = await browser.newContext({
                    viewport: { width, height: 900 },
                    reducedMotion: 'reduce',
                    storageState: email ? sessions.get(email) : undefined,
                });
                await rc.addInitScript(() => {
                    window.__HERD_STUDIO__ = true;
                });
                const rp = await rc.newPage();
                try {
                    await rp.goto(baseUrl + path, { waitUntil: 'networkidle' });
                    await rp.waitForTimeout(800);
                    const anims = await rp.evaluate(() =>
                        document
                            .getAnimations()
                            .filter(
                                (a) =>
                                    a instanceof CSSAnimation &&
                                    a.playState === 'running',
                            )
                            .map((a) => {
                                const t = a.effect.getComputedTiming();
                                const el = a.effect.target;
                                return {
                                    name: a.animationName,
                                    dur: t.duration,
                                    iter: t.iterations,
                                    sel: el
                                        ? `${el.tagName.toLowerCase()}.${[...el.classList].slice(0, 2).join('.')}`
                                        : '?',
                                };
                            })
                            .filter((a) => a.dur > 200 || a.iter === Infinity),
                    );
                    for (const a of anims)
                        add(
                            'reduced-motion-animation',
                            'moderate',
                            'CSS animation still running under prefers-reduced-motion (>200ms)',
                            path,
                            `${a.sel} animation:${a.name} ${Math.round(a.dur)}ms x${a.iter}`,
                            `${width}/rm`,
                        );
                } catch (e) {
                    failures.push(
                        `${path} rm ${width}: ${e.message.split('\n')[0]}`,
                    );
                }
                await rc.close();
            }
        }
    }
    console.log('done', path);
}
await browser.close();

const order = { critical: 0, serious: 1, moderate: 2, minor: 3 };
const rules = [...findings.entries()].sort(
    (a, b) =>
        order[a[1].impact] - order[b[1].impact] || b[1].total - a[1].total,
);
let md = `# Accessibility audit (WCAG 2.2 AA)\n\nGenerated ${new Date().toISOString()} against ${baseUrl}. ${pages.length} pages x ${widths.join('/')}px x ${schemes.join('/')}.\n\n## Summary\n\n| Rule | Impact | Pages | Selectors | Hits |\n|---|---|---|---|---|\n`;
for (const [rule, f] of rules) {
    const sels = new Set();
    f.pages.forEach((m) => m.forEach((_, s) => sels.add(s)));
    md += `| ${rule} | ${f.impact} | ${f.pages.size} | ${sels.size} | ${f.total} |\n`;
}
for (const [rule, f] of rules) {
    md += `\n## ${rule} (${f.impact})\n\n${f.help}\n\n`;
    for (const [p, m] of f.pages) {
        md += `- \`${p}\` (${m.size} selectors)\n`;
        for (const [s, v] of m) md += `  - \`${s}\` (${[...v].join(', ')})\n`;
    }
}
if (failures.length)
    md += `\n## Run failures\n\n${failures.map((f) => `- ${f}`).join('\n')}\n`;
await mkdir('storage/app/review', { recursive: true });
await writeFile('storage/app/review/a11y-report.md', md);
console.log(
    'Report written; rules:',
    rules.length,
    'failures:',
    failures.length,
);
