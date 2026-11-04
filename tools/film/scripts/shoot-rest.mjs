// The remaining scenes: table service, a booking, the owner's evening, theme and motion, and the home page.
import { artisan, BASE, Cam, launch, sleep } from './lib.mjs';

const wanted = process.argv.slice(2);
const b = await launch();
const dump = async (cam) => {
    const s = await cam.page.locator('body').ariaSnapshot();
    console.log(`\n#### ${cam.name} ${cam.page.url()}\n${s.split('\n').filter((l) => !/\/url|^\s*- img/.test(l)).slice(0, +(process.env.L ?? 70)).join('\n')}`);
    await cam.page.screenshot({ path: `/tmp/fail-${cam.name}.jpg`, type: 'jpeg', quality: 55 });
};
const phone = { width: 390, height: 844, phone: true };
const desk = { width: 1440, height: 900 };

const scenes = {
    async table(scene, cams) {
        const token = (await artisan(`App\\Models\\DiningTable::where('label','G1')->update(['status'=>'seated']); echo App\\Models\\DiningTable::where('label','G1')->value('qr_token');`)).trim().split('\n').pop();
        const guest = await Cam.open(b, 'guest', { ...phone, theme: 'dark', scene });
        const floor = await Cam.open(b, 'floor', { ...desk, login: 'waiter@tandoorhouse.test', scene });
        cams.push(guest, floor);
        const p = guest.page;
        await floor.goto('/staff/floor');
        await floor.page.getByRole('tab', { name: /Garden/ }).click();
        await sleep(400);
        await guest.goto(`/t/${token}`);
        await floor.record();
        await guest.record();
        guest.mark('open');
        await guest.hold(1500);
        await guest.scrollBy(520, 1100);
        await guest.hold(300);
        await guest.tap(p.getByRole('button', { name: 'Add Samosa Chaat to basket' }));
        guest.mark('add-1');
        await guest.hold(500);
        await guest.tap(p.getByRole('button', { name: 'Add Onion Bhaji to basket' }));
        await guest.hold(600);
        guest.mark('bar');
        await guest.tap(p.getByRole('button', { name: /basket|items?|order/i }).filter({ hasText: /£/ }).last(), { reveal: false });
        await guest.hold(900);
        guest.mark('send');
        floor.mark('send');
        await guest.tap(p.getByRole('dialog').getByRole('button', { name: 'Send to the kitchen' }), { reveal: false });
        await guest.hold(3000);
        guest.mark('sent');
        floor.mark('sent');
        await p.locator('[data-sonner-toast]').first().waitFor({ state: 'detached', timeout: 9000 }).catch(() => {});
        guest.mark('call');
        floor.mark('call');
        await guest.tap(p.getByRole('button', { name: 'Call a waiter' }), { reveal: false });
        await sleep(3200);
        floor.mark('called');
        guest.mark('called');
        await guest.page.goto(`${BASE}/t/${token}/bill`, { waitUntil: 'networkidle' });
        guest.mark('bill');
        await guest.hold(1200);
        await guest.tap(p.getByRole('tab', { name: 'Evenly' }));
        await p.getByText('Number of people').waitFor();
        guest.mark('split');
        await guest.hold(1500);
        const mine = p.getByRole('radio', { name: /Guest 1/ }).first();
        await guest.reveal(mine);
        await guest.tap(mine, { force: true, reveal: false });
        await guest.hold(700);
        const pay = p.getByRole('button', { name: /^Pay £/ }).first();
        await guest.reveal(pay);
        await guest.hold(800);
        await guest.tap(pay);
        await p.waitForURL(/pay\/\d+/);
        guest.mark('share');
        await p.getByRole('button', { name: /^Pay £/ }).waitFor();
        await guest.hold(1100);
        await guest.tap(p.getByRole('button', { name: /^Pay £/ }));
        guest.mark('paid');
        await guest.hold(4000);
        guest.mark('end');
        floor.mark('end');
    },
    async booking(scene, cams) {
        const guest = await Cam.open(b, 'guest', { ...phone, login: 'customer@tandoorhouse.test', scene });
        cams.push(guest);
        const p = guest.page;
        await guest.goto('/book');
        await guest.record();
        guest.mark('open');
        await guest.hold(1100);
        const lab = (l) => l.locator('xpath=ancestor::label[1]');
        await guest.tap(lab(p.getByRole('radio', { name: 'Table for 4' })));
        await guest.hold(350);
        await guest.tap(lab(p.getByRole('radio', { name: 'Friday 6 November' })));
        await p.waitForLoadState('networkidle');
        await guest.hold(900);
        guest.mark('slots');
        const slot = lab(p.getByRole('radio', { name: /19:30/ }).first());
        await slot.waitFor();
        await guest.reveal(slot);
        await guest.hold(900);
        await guest.tap(slot);
        await guest.hold(250);
        guest.mark('chosen');
        await guest.hold(700);
        const submit = p.getByRole('button', { name: /(Book|Confirm|Reserve|deposit)/i }).last();
        await guest.reveal(submit);
        guest.mark('details');
        await guest.hold(900);
        await guest.tap(submit);
        await p.waitForURL(/book\/[A-Z0-9]+/);
        guest.mark('deposit');
        const pay = p.getByRole('button', { name: /^Pay £/ });
        if (await pay.count()) {
            await guest.hold(1100);
            await guest.tap(pay);
            await p.waitForURL(/book\/[A-Z0-9]+$/, { timeout: 15000 });
        }
        await guest.hold(500);
        await p.evaluate(() => window.scrollTo(0, 0));
        guest.mark('confirmed');
        await guest.hold(2800);
        guest.mark('end');
    },
    async owner(scene, cams) {
        const o = await Cam.open(b, 'owner', { ...desk, login: 'owner@tandoorhouse.test', scene });
        cams.push(o);
        const p = o.page;
        await o.goto('/staff/reports');
        await o.record();
        o.mark('reports');
        await o.hold(1500);
        await o.scrollBy(560, 1800);
        await o.hold(1000);
        await o.scrollBy(620, 1800);
        await o.hold(800);
        await o.goto('/staff/inventory');
        o.mark('stock');
        await o.hold(800);
        await o.tap(p.getByRole('switch', { name: /Low stock only/i }).or(p.getByLabel(/Low stock only/i)).first(), { force: true });
        await o.hold(900);
        o.mark('low');
        const lamb = p.getByText('Lamb mince').first();
        await o.reveal(lamb);
        const box = await lamb.boundingBox();
        o.log('move', box.x + box.width / 2, box.y + box.height / 2, { w: box.width, h: box.height });
        await o.hold(1600);
        await o.goto('/staff/inventory/purchase-orders');
        o.mark('pos');
        await o.hold(1000);
        const draft = p.getByRole('link', { name: /Whitechapel Halal Meats/ }).filter({ hasText: /Draft/ }).first().or(p.getByRole('row', { name: /Whitechapel Halal Meats.*Draft/ }).first());
        await o.tap(draft.first());
        await p.waitForURL(/purchase-orders\/.+/);
        o.mark('draft');
        await o.hold(2000);
        await o.goto('/staff/activity');
        o.mark('activity');
        await o.hold(800);
        await o.page.getByRole('searchbox', { name: 'Search' }).fill('Lamb mince');
        await o.page.keyboard.press('Enter');
        await o.hold(1200);
        o.mark('activity-lamb');
        await o.hold(1800);
        await o.goto('/staff/settings');
        await p.getByRole('link', { name: /^Automations/ }).click();
        await sleep(600);
        o.mark('automations');
        await o.hold(1400);
        await o.scrollBy(380, 1400);
        await o.hold(1000);
        o.mark('end');
    },
    async look(scene, cams) {
        const light = await Cam.open(b, 'light', { ...desk, theme: 'light', scene });
        const dark = await Cam.open(b, 'dark', { ...desk, theme: 'dark', scene });
        const motion = await Cam.open(b, 'motion', { ...desk, theme: 'light', scene });
        const toggle = await Cam.open(b, 'toggle', { ...desk, theme: 'light', scene });
        cams.push(light, dark, motion, toggle);
        await light.goto('/menu');
        await dark.goto('/menu');
        await light.record();
        await dark.record();
        light.mark('start');
        dark.mark('start');
        await Promise.all([light.scrollBy(640, 3800), dark.scrollBy(640, 3800)]);
        await sleep(400);
        light.mark('end');
        dark.mark('end');
        await toggle.goto('/story');
        await toggle.record();
        toggle.mark('start');
        await toggle.hold(900);
        await toggle.tap(toggle.page.getByRole('radio', { name: 'Dark' }), { force: true, reveal: false });
        toggle.mark('dark');
        await toggle.hold(1500);
        toggle.mark('end');
        await motion.goto('/styleguide/motion');
        await motion.record();
        motion.mark('start');
        await motion.hold(700);
        const replay = (n) => motion.page.getByRole('button', { name: 'Replay' }).nth(n);
        await motion.tap(replay(0));
        motion.mark('plate');
        await motion.hold(1500);
        await motion.tap(replay(1));
        motion.mark('course');
        await motion.hold(1800);
        await motion.tap(replay(2));
        motion.mark('steam');
        await motion.hold(1600);
        motion.mark('end');
    },
    async home(scene, cams) {
        const h = await Cam.open(b, 'desk', { ...desk, scene });
        cams.push(h);
        await h.goto('/');
        await h.record();
        h.mark('hero');
        await h.hold(3200);
        h.mark('scroll');
        await h.scrollBy(900, 2600);
        await h.hold(1200);
        await h.scrollBy(1000, 2600);
        await h.hold(900);
        h.mark('end');
    },
};

for (const name of wanted.length ? wanted : Object.keys(scenes)) {
    const cams = [];
    try {
        console.log(name);
        await scenes[name](name, cams);
    } catch (e) {
        console.log('FAILED', name, e.message.slice(0, 300));
        for (const c of cams) await dump(c);
        process.exitCode = 1;
    }
    for (const c of cams) await c.stop();
}
await b.close();
