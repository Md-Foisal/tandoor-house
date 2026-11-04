// Capture helpers: every camera records a CDP screencast with real timestamps and logs
// its pointer and marks, so the cursor, zooms and cuts can be rebuilt smoothly in the edit.
import { mkdirSync, readFileSync, rmSync, writeFileSync, createWriteStream } from 'node:fs';
import { writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
export const BASE = 'http://127.0.0.1:8020';
export const RAW = join(ROOT, 'public/raw');
const DRY = process.env.DRY === '1';

const clock = JSON.parse(readFileSync(join(ROOT, 'out/clock.json'), 'utf8'));
const offsetMs = () => {
    // FILM_CLOCK is London wall time; London is on GMT during the capture window in November.
    return new Date(clock.clock.replace(' ', 'T') + ':00Z').getTime() - clock.epoch * 1000;
};

export const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ease = 'function(k){return k<.5?4*k*k*k:1-Math.pow(-2*k+2,3)/2}';

// Screencasts ignore emulated density, so density is set as a browser flag: that yields true device pixels.
// Desktop runs at 3x (4320 wide), phones at 4x (1560 wide), enough for 4K delivery with zooms.
export async function launch() {
    const flags = ['--hide-scrollbars', '--force-color-profile=srgb', '--disable-features=TranslateUI', '--disable-renderer-backgrounding', '--disable-backgrounding-occluded-windows', '--disable-background-timer-throttling'];
    const start = (dsf, w, h) => chromium.launch({ channel: 'chromium', args: [...flags, `--force-device-scale-factor=${dsf}`, `--window-size=${w},${h + 87}`] });
    const [desk, phone] = await Promise.all([start(3, 1440, 900), start(4, 500, 844)]);
    return { desk, phone, close: () => Promise.all([desk.close(), phone.close()]) };
}

export class Cam {
    static async open(browsers, name, { width, height, phone = false, theme = 'light', login = null, scene }) {
        const cam = new Cam();
        cam.name = name;
        cam.scene = scene;
        const dsf = phone ? 4 : 3;
        cam.size = { width, height, dsf, phone };
        cam.ctx = await (phone ? browsers.phone : browsers.desk).newContext({
            viewport: null, hasTouch: phone,
            locale: 'en-GB', timezoneId: 'Europe/London', colorScheme: theme, reducedMotion: 'no-preference',
            userAgent: phone ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1' : undefined,
        });
        const shift = offsetMs();
        await cam.ctx.addInitScript(`(()=>{const R=Date,s=${shift};class D extends R{constructor(...a){a.length?super(...a):super(R.now()+s)}static now(){return R.now()+s}};window.Date=D})();`);
        cam.page = await cam.ctx.newPage();
        cam.cdp = await cam.ctx.newCDPSession(cam.page);
        await cam.cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 0, mobile: phone });
        cam.page.setDefaultTimeout(15000);
        cam.events = [];
        cam.marks = {};
        cam.frames = [];
        cam.pointer = { x: width / 2, y: height * 0.6 };
        cam.pending = [];
        if (login) {
            await cam.page.goto(`${BASE}/login`, { waitUntil: 'networkidle' });
            await cam.rejectCookies();
            await cam.page.fill('input[name="email"]', login);
            await cam.page.fill('input[name="password"]', 'password');
            await Promise.all([cam.page.waitForURL((u) => !u.pathname.startsWith('/login')), cam.page.click('button[type="submit"]')]);
        }
        return cam;
    }

    async rejectCookies() {
        const reject = this.page.getByRole('button', { name: 'Reject non-essential' });
        if (await reject.count()) {
            await reject.first().click();
            await sleep(400);
        }
    }

    async goto(path) {
        await this.page.goto(path.startsWith('http') ? path : BASE + path, { waitUntil: 'networkidle' });
        await this.rejectCookies();
        await this.settleImages();
    }

    async settleImages() {
        await this.page.evaluate(() => Promise.all([...document.images].filter((i) => !i.complete && i.loading !== 'lazy').map((i) => i.decode().catch(() => {}))));
    }

    async record() {
        await this.page.locator('[data-sonner-toast]').first().waitFor({ state: 'detached', timeout: 9000 }).catch(() => {});
        if (DRY) return;
        this.dir = join(RAW, this.scene, this.name);
        rmSync(this.dir, { recursive: true, force: true });
        mkdirSync(this.dir, { recursive: true });
        this.cdp.on('Page.screencastFrame', (f) => {
            const n = String(this.frames.length).padStart(6, '0') + '.jpg';
            this.frames.push([n, f.metadata.timestamp]);
            this.pending.push(writeFile(join(this.dir, n), Buffer.from(f.data, 'base64')));
            this.cdp.send('Page.screencastFrameAck', { sessionId: f.sessionId }).catch(() => {});
        });
        const { width, height, dsf } = this.size;
        await this.cdp.send('Page.startScreencast', { format: 'jpeg', quality: 93, maxWidth: 7000, maxHeight: 7000, everyNthFrame: 1 });
        // Repaint once so an idle page still yields a first frame.
        await this.page.evaluate(() => { const d = document.createElement('div'); d.style.cssText = 'position:fixed;width:1px;height:1px;opacity:.01;top:0;left:0'; document.body.append(d); requestAnimationFrame(() => requestAnimationFrame(() => d.remove())); });
        await sleep(200);
    }

    async stop() {
        if (!DRY && this.dir) {
            await this.cdp.send('Page.stopScreencast');
            await Promise.all(this.pending);
            writeFileSync(join(this.dir, 'index.json'), JSON.stringify({ size: this.size, frames: this.frames, events: this.events, marks: this.marks }));
            console.log(`  ${this.scene}/${this.name}: ${this.frames.length} frames, ${Object.keys(this.marks).length} marks`);
        }
        await this.ctx.close();
    }

    mark(name) {
        this.marks[name] = Date.now() / 1000;
    }

    log(type, x, y, extra = {}) {
        this.events.push({ t: Date.now() / 1000, type, x, y, ...extra });
    }

    async hold(ms) {
        await sleep(DRY ? Math.min(ms, 60) : ms);
    }

    async point(target) {
        const box = await target.boundingBox();
        if (!box) throw new Error(`no box for ${target}`);
        return { x: box.x + box.width / 2, y: box.y + box.height / 2, box };
    }

    // Brings a target into view with an eased scroll rather than Playwright's jump.
    async reveal(target, { margin = 110, dur = 700 } = {}) {
        const box = await target.boundingBox();
        const h = this.size.height;
        if (box && box.y >= margin && box.y + box.height <= h - margin) return;
        if (!box) await target.scrollIntoViewIfNeeded();
        else await this.scrollBy(box.y - h * 0.42 + box.height / 2, dur);
    }

    async tap(target, { before = 380, after = 320, reveal = true, force = false } = {}) {
        await target.waitFor({ state: 'visible' });
        if (reveal) await this.reveal(target);
        const { x, y, box } = await this.point(target);
        this.log('move', x, y, { w: box.width, h: box.height });
        this.pointer = { x, y };
        await this.hold(before);
        this.log('click', x, y, { w: box.width, h: box.height });
        await target.click({ force, timeout: 8000 });
        await this.hold(after);
    }

    async type(target, text, { cps = 11, before = 300, after = 260 } = {}) {
        await this.tap(target, { before, after: 120 });
        for (const ch of text) {
            await this.page.keyboard.type(ch);
            this.log('key', this.pointer.x, this.pointer.y);
            await this.hold(1000 / cps * (0.75 + Math.random() * 0.5));
        }
        await this.hold(after);
    }

    async scrollBy(dy, dur = 1400) {
        if (DRY) return this.page.evaluate((d) => window.scrollBy(0, d), dy);
        this.log('scroll', this.pointer.x, this.pointer.y, { dy, dur });
        await this.page.evaluate(`new Promise(r=>{const e=${ease},a=window.scrollY,s=performance.now(),d=${dy},D=${dur};const f=n=>{const k=Math.min((n-s)/D,1);window.scrollTo(0,a+d*e(k));k<1?requestAnimationFrame(f):r()};requestAnimationFrame(f)})`);
    }

    async scrollInto(target, opts = {}) {
        const box = await target.boundingBox();
        await this.scrollBy(box.y - (opts.top ?? 90), opts.dur ?? 1300);
    }
}

export async function artisan(code) {
    const { execFileSync } = await import('node:child_process');
    return execFileSync('php', ['-d', `auto_prepend_file=${ROOT}/scripts/clock-prepend.php`, 'artisan', 'tinker', `--execute=${code}`], {
        cwd: join(ROOT, '../..'),
        env: { ...process.env, FILM_CLOCK: clock.clock, FILM_CLOCK_EPOCH: String(clock.epoch), FILM_APP_ROOT: join(ROOT, '../..') },
        encoding: 'utf8',
    });
}
