// One real order, recorded from four screens at once: guest phone, order board, kitchen display, rider phone.
import { artisan, Cam, launch, sleep } from './lib.mjs';

const scene = 'service';
const b = await launch();
const dump = async (cam) => {
    const s = await cam.page.locator('body').ariaSnapshot();
    console.log(`\n#### ${cam.name} ${cam.page.url()}\n${s.split('\n').filter((l) => !/\/url|^\s*- img/.test(l)).slice(0, 80).join('\n')}`);
    await cam.page.screenshot({ path: `/tmp/fail-${cam.name}.jpg`, type: 'jpeg', quality: 55 });
};

// Lamb mince sits just above its reorder level, so this order is the one that crosses it.
await artisan(`App\\Models\\Ingredient::where('name','Lamb mince')->update(['stock'=>1700]); App\\Models\\MenuItem::query()->update(['is_available'=>true]); App\\Models\\Order::whereIn('status',['ready','out_for_delivery'])->update(['rider_id'=>null]); App\\Models\\User::where('email','customer@tandoorhouse.test')->update(['name'=>'Priya Sen','phone'=>'07700 900123']);`);

const guest = await Cam.open(b, 'guest', { width: 390, height: 844, phone: true, login: 'customer@tandoorhouse.test', scene });
const kds = await Cam.open(b, 'kds', { width: 1366, height: 900, theme: 'dark', login: 'kitchen@tandoorhouse.test', scene });
const board = await Cam.open(b, 'board', { width: 1440, height: 900, login: 'manager@tandoorhouse.test', scene });
const rider = await Cam.open(b, 'rider', { width: 390, height: 844, phone: true, theme: 'dark', login: 'rider@tandoorhouse.test', scene });
const cams = [guest, kds, board, rider];

try {
    const p = guest.page;

    // A quieter pass, cleared through the real screens: new orders accepted, older tickets bumped, one left cooking.
    await board.goto('/staff/orders');
    for (let i = 0; i < 8; i++) {
        const accept = board.page.getByRole('button', { name: /^Accept/ });
        if (!(await accept.count())) break;
        await accept.first().click();
        await sleep(600);
    }
    await kds.goto('/staff/kitchen');
    for (let i = 0; i < 12; i++) {
        const tickets = kds.page.locator('main article');
        if ((await tickets.count()) <= 1) break;
        await tickets.first().getByRole('button', { name: 'Bump ticket' }).click();
        await sleep(800);
    }

    await guest.goto('/basket');
    for (let r = p.getByRole('button', { name: /^Remove / }); await r.count(); ) { await r.first().click(); await sleep(450); }
    await guest.goto('/menu');
    await guest.record();
    guest.mark('menu');
    await guest.hold(900);
    await guest.scrollBy(760, 1700);
    await guest.hold(500);
    await guest.scrollBy(-760, 900);
    await guest.hold(200);

    guest.mark('filters');
    await guest.tap(p.getByRole('button', { name: /^Filters/ }));
    await guest.hold(500);
    const sheet = p.getByRole('dialog');
    await guest.tap(sheet.getByRole('checkbox', { name: 'Milk' }).or(sheet.getByRole('switch', { name: 'Milk' })).or(sheet.getByRole('button', { name: 'Milk' })).first());
    guest.mark('milk-off');
    await guest.hold(700);
    await guest.tap(sheet.getByRole('button', { name: /^(Show|Done|See|Close)/ }).first());
    await guest.hold(1100);
    guest.mark('filtered');
    await guest.scrollBy(420, 1200);
    await guest.hold(500);

    guest.mark('dish');
    await guest.tap(p.getByRole('link', { name: 'Onion Bhaji' }).first());
    await p.waitForURL(/onion-bhaji/);
    await guest.settleImages();
    await guest.hold(1200);
    await guest.scrollBy(340, 900);
    guest.mark('allergens');
    await guest.hold(1300);
    await guest.tap(p.getByRole('button', { name: /^Add to basket/ }));
    guest.mark('added-1');
    await guest.hold(1100);

    await guest.goto('/menu/lamb-seekh-kebab');
    await guest.hold(300);
    guest.mark('kebab');
    await guest.hold(900);
    await guest.tap(p.getByRole('button', { name: /^Add to basket/ }));
    guest.mark('added-2');
    await guest.hold(900);

    await guest.goto('/basket');
    guest.mark('basket');
    await guest.hold(1300);
    await guest.tap(p.getByRole('link', { name: /^Go to checkout/ }));
    await p.waitForURL(/checkout/);
    await guest.hold(500);

    guest.mark('how');
    await guest.hold(1300);
    await guest.tap(p.getByRole('button', { name: 'Continue' }));
    await guest.hold(900);
    guest.mark('details');
    await guest.hold(500);
    await guest.tap(p.getByRole('button', { name: 'Continue' }));
    await guest.hold(500);

    guest.mark('tip');
    await guest.tap(p.getByRole('radio', { name: /^10%/ }).locator('xpath=ancestor::label | self::*').last(), { force: true });
    await guest.hold(800);
    await guest.tap(p.getByRole('button', { name: /^Continue to payment/ }));
    await p.waitForURL(/\/pay/);
    await p.getByRole('button', { name: /^Pay £/ }).waitFor();
    const number = p.url().match(/TH-[A-Z0-9]+/)[0];
    console.log('  order', number);
    await guest.hold(900);

    // Staff screens are already open and listening when the money lands.
    await board.goto('/staff/orders');
    await kds.goto('/staff/kitchen');
    await board.record();
    await kds.record();
    await sleep(1500);

    guest.mark('pay');
    const paid = Date.now();
    await guest.tap(p.getByRole('button', { name: /^Pay £/ }));
    await p.waitForURL(new RegExp(`orders/${number}$`), { timeout: 15000 });
    guest.mark('tracking');

    const ticket = kds.page.getByRole('article', { name: number });
    await ticket.waitFor({ timeout: 20000 });
    kds.mark('landed');
    board.mark('landed');
    await sleep(700);
    for (const [cam, loc] of [[kds, ticket], [board, board.page.locator('article', { hasText: number }).first()]]) {
        const box = await loc.boundingBox();
        if (box) cam.log('focus', box.x + box.width / 2, box.y + box.height / 2, { w: box.width, h: box.height });
    }
    guest.mark('landed');
    console.log(`  on the kitchen display ${(Date.now() - paid) / 1000}s after Pay`);
    await sleep(3000);

    kds.mark('plate');
    await kds.tap(ticket.getByRole('button', { name: /Onion Bhaji/ }));
    await sleep(1600);
    await kds.tap(ticket.getByRole('button', { name: /Lamb Seekh Kebab/ }));
    await sleep(1200);
    kds.mark('bump');
    guest.mark('bump');
    board.mark('bump');
    const bump = ticket.getByRole('button', { name: 'Bump ticket' });
    if (await bump.count()) await kds.tap(bump);
    await sleep(3500);
    guest.mark('ready');
    board.mark('ready');
    kds.mark('ready');

    // The manager gives the run to a rider; the rider's phone hears about it.
    await rider.goto('/staff/deliveries');
    await rider.record();
    await board.page.goto(`http://127.0.0.1:8020/staff/orders/${number}`, { waitUntil: 'networkidle' });
    board.mark('order');
    await sleep(800);
    await board.tap(board.page.getByRole('combobox').filter({ hasText: /rider/i }).first().or(board.page.getByRole('combobox').first()));
    await sleep(400);
    await board.tap(board.page.getByRole('option', { name: /Dawit/ }).first());
    board.mark('assigned');
    rider.mark('assigned');
    const run = rider.page.getByRole('article', { name: number });
    await run.waitFor({ timeout: 25000 });
    rider.mark('run');
    await sleep(1500);
    await rider.reveal(run);
    await rider.tap(run.getByRole('button', { name: 'Picked up' }));
    rider.mark('picked');
    guest.mark('picked');
    await sleep(4000);
    await rider.page.getByRole('tab', { name: /On the road/ }).click();
    await sleep(800);
    const road = rider.page.getByRole('article', { name: number });
    rider.mark('road');
    await sleep(1000);
    await rider.tap(road.getByRole('button', { name: 'Delivered' }));
    await sleep(600);
    await rider.tap(rider.page.getByRole('button', { name: 'Confirm delivered' }), { reveal: false });
    rider.mark('delivered');
    guest.mark('delivered');
    await sleep(4000);
    guest.mark('end');
    if (process.env.DRY) { await dump(rider); await dump(guest); }
    console.log((await artisan(`echo App\\Models\\PurchaseOrder::latest('id')->first()?->status?->value, ' ', App\\Models\\Ingredient::where('name','Lamb mince')->value('stock');`)).trim());
} catch (e) {
    console.log('FAILED', e.message.slice(0, 400));
    for (const c of cams) await dump(c);
    process.exitCode = 1;
}
for (const c of cams) await c.stop();
await b.close();
