// The edit decision list: pure data, read by the renderer (Film.tsx) and the sound mix (scripts/mix.mjs).
import captures from './data/captures.json';

export type Capture = { size: { width: number; height: number; dsf: number; phone: boolean }; times: number[]; marks: Record<string, number>; events: { t: number; type: string; x: number; y: number; w?: number; h?: number }[] };
const caps = captures as unknown as Record<string, Capture>;
export const capture = (cam: string) => caps[cam];

export const FPS = 60;
export const TOTAL = 142;
// Beat grid measured from the music bed (scripts/beats.mjs): 89.6 BPM, first beat at 0.659 s.
export const B = (n: number) => 0.659 + 0.6696 * n;

export type Seg = { film0: number; film1: number; src0: number; src1: number };
export type Reel = { cam: string; segs: Seg[] };

// 'pay+0.5' reads a mark in the camera's own clock.
const at = (cam: string, s: string | number) => {
    if (typeof s === 'number') return s;
    const m = s.match(/^([a-z0-9-]+?)([+-][\d.]+)?$/i)!;
    const base = caps[cam].marks[m[1]];
    if (base === undefined) throw new Error(`no mark ${s} on ${cam}`);
    return base + Number(m[2] ?? 0);
};

export function reel(cam: string, start: number, parts: [string | number, string | number, number?][]): Reel {
    let film = start;
    const segs = parts.map(([from, to, rate = 1]) => {
        const src0 = at(cam, from);
        const src1 = at(cam, to);
        const seg = { film0: film, film1: film + (src1 - src0) / rate, src0, src1 };
        film = seg.film1;
        return seg;
    });
    return { cam, segs };
}

export const reelEnd = (r: Reel) => r.segs[r.segs.length - 1].film1;

export function srcAt(r: Reel, t: number) {
    const s = r.segs.find((x) => t < x.film1) ?? r.segs[r.segs.length - 1];
    const k = Math.min(1, Math.max(0, (t - s.film0) / (s.film1 - s.film0)));
    return s.src0 + (s.src1 - s.src0) * k;
}

// Film times of a reel's real clicks and key presses: these drive the UI ticks in the mix.
export function eventsInFilm(r: Reel, type: string) {
    const out: number[] = [];
    for (const s of r.segs) for (const e of caps[r.cam].events) {
        if (e.type === type && e.t >= s.src0 && e.t <= s.src1) out.push(s.film0 + ((e.t - s.src0) / (s.src1 - s.src0)) * (s.film1 - s.film0));
    }
    return out;
}

export type Device = { kind: 'phone' | 'browser' | 'tablet'; reel: Reel; x: number; y: number; s: number; path?: string; inner?: { at: number; z: number; fx: number; fy: number }[] };
export type Key = { at: number; z: number; fx: number; fy: number; tx?: number; ty?: number; dev?: number };
export type Caption = { at: number; end: number; label?: string; title?: string; body?: string; x: number; y: number; w: number; size?: number; card?: boolean };
export type Shot = { id: string; start: number; end: number; bg: 'paper' | 'coal'; enter: 'arch' | 'pass' | 'fade' | 'cut'; devices: Device[]; keys?: Key[]; captions?: Caption[]; custom?: string };

const G = 'service/guest', K = 'service/kds', O = 'service/board', R = 'service/rider', T = 'table/guest', L = 'table/floor', W = 'owner/owner', BK = 'booking/guest', H = 'home/desk';
const phoneRight = { x: 1192, y: 62, s: 1.1 };
const browserFull = { x: 140, y: 36, s: 1.14 };

// Ch 1: the guest
const t1 = B(19);
const guest1 = reel(G, t1, [
    ['menu+0.3', 'filters+0.3', 1],
    ['filters+0.3', 'filtered+1.0', 1.15],
    ['dish', 'added-1+1.0', 1.1],
    ['added-2-1.3', 'added-2+0.5', 1],
    ['basket+0.2', 'basket+2.3', 1.2],
    ['how+0.2', 'how+3.0', 1.25],
    ['tip', 'tip+3.0', 1.25],
    ['pay-2.5', 'pay+0.75', 1],
]);
const s1 = guest1.segs;

// Ch 2: the pass
const t2 = B(60);
const lands = t2 + 1.3;
const t2b = B(69);
const t2c = B(75);
const pass1 = reel(G, t2, [['landed-1.3', 'landed+5', 1]]);
const kds1 = reel(K, t2, [['landed-1.3', 'landed+4.2', 1]]);
const board1 = reel(O, t2b, [['landed-0.7', 'landed+4', 1]]);
const pass2 = reel(G, t2c, [['bump-3.9', 'bump+4.4', 1]]);
const kds2 = reel(K, t2c, [['bump-3.9', 'bump+4.4', 1]]);
const bumpFilm = eventsInFilm(kds2, 'click');

// Ch 3: the road
const t3 = B(87);
const t3b = t3 + 3.3;
const assign = reel(O, t3, [['order+0.8', 'assigned+0.3', 1]]);
const riderA = reel(R, t3b, [['assigned-0.2', 'picked+1.6', 1], ['road-0.8', 'delivered+2.2', 1.1]]);
const guestA = reel(G, t3b, [['picked-4.0', 'picked+1.6', 1], ['delivered-4.4', 'delivered+2.2', 1.1]]);

// Ch 4: the table, then a booking
const t4 = B(106);
const tableA = reel(T, t4, [['open+0.4', 'bar+0.5', 1.1], ['bar+0.5', 'send+0.5', 1.5], ['send+0.5', 'sent', 1.6]]);
const t4b = reelEnd(tableA);
const tableB = reel(T, t4b, [['call-0.5', 'called-0.4', 1]]);
const floorB = reel(L, t4b, [['call-0.5', 'called-0.4', 1]]);
const t4c = reelEnd(tableB);
const tableC = reel(T, t4c, [['bill+0.3', 'share', 1.8], ['share', 'paid+1.0', 1.2]]);
const t4d = B(137);
const booking = reel(BK, t4d, [['open+0.6', 'chosen+0.6', 1.25], ['details', 'details+1.9', 1], ['confirmed', 'confirmed+2.2', 1]]);

// Ch 5: the owner
const t5 = B(148);
const owner = reel(W, t5, [
    ['reports+0.4', 'stock-0.4', 1.3],
    ['low-0.15', 'low+2.0', 0.85],
    ['pos+1.0', 'draft+1.8', 1],
    ['activity-lamb-0.2', 'activity-lamb+1.9', 0.9],
    ['automations+0.2', 'end', 1.05],
]);
const os = owner.segs;

// Ch 6: the details
const t6 = B(176);
const t6b = B(182);
const motion = reel('look/motion', t6b, [['plate-0.7', 'course+1.7', 1.0]]);

export const T_ENG = B(188);
export const T_CLOSE = B(200);

export const shots: Shot[] = [
    { id: 'open', start: 0, end: B(11), bg: 'coal', enter: 'cut', devices: [], custom: 'open' },
    {
        id: 'home', start: B(11), end: t1, bg: 'paper', enter: 'arch',
        devices: [{ kind: 'browser', reel: reel(H, B(11), [['hero+0.3', 'end-0.4', 1.1]]), ...browserFull, path: '/' }],
        keys: [{ at: B(11), z: 1, fx: 720, fy: 450 }, { at: B(15), z: 1.1, fx: 720, fy: 400 }],
        captions: [{ at: B(11) + 1.0, end: t1, title: 'A restaurant platform, built end to end.', body: 'One evening of service, followed from the first order.', x: 70, y: 860, w: 1100, size: 48, card: true }],
    },
    {
        id: 'guest', start: t1, end: t2, bg: 'paper', enter: 'pass',
        devices: [{ kind: 'phone', reel: guest1, ...phoneRight }],
        keys: [
            { at: t1, z: 1, fx: 195, fy: 422 },
            { at: s1[1].film0 + 3.0, z: 1.55, fx: 195, fy: 520, tx: 1300, ty: 560 },
            { at: s1[1].film1 - 0.4, z: 1, fx: 195, fy: 422 },
            { at: s1[2].film0 + 3.2, z: 1.5, fx: 195, fy: 400, tx: 1320, ty: 500 },
            { at: s1[3].film0 + 0.5, z: 1, fx: 195, fy: 422 },
            { at: s1[5].film0 + 0.6, z: 1.4, fx: 195, fy: 420, tx: 1330, ty: 540 },
            { at: s1[6].film0 + 0.4, z: 1, fx: 195, fy: 422 },
            { at: s1[7].film0 + 0.7, z: 1.5, fx: 195, fy: 330, tx: 1420, ty: 560 },
        ],
        captions: [
            { at: t1 + 0.3, end: s1[1].film0 - 0.1, label: 'The guest', title: 'A regular opens the menu.', body: 'Allergens and calories sit under every dish.', x: 130, y: 360, w: 860 },
            { at: s1[1].film0 + 0.1, end: s1[2].film0 - 0.1, title: 'No milk, please. So milk leaves the menu.', body: 'All 14 UK allergens, filterable. Sold-out dishes stay visible, disabled.', x: 130, y: 360, w: 860 },
            { at: s1[2].film0 + 0.2, end: s1[4].film0 - 0.1, title: 'Every dish says so in plain words.', body: '“None of the 14 major allergens.”', x: 130, y: 400, w: 860 },
            { at: s1[4].film0 + 0.1, end: s1[6].film0 + 1.0, title: 'Two dishes. Saved address. Zone checked.', body: 'Delivery, pickup or dine-in, all from one basket.', x: 130, y: 400, w: 860 },
            { at: s1[6].film0 + 1.1, end: t2 + 0.1, title: 'Pay.', body: 'Stripe’s Payment Element when keys are set. Here, the built-in test gateway: no real money moves.', x: 130, y: 400, w: 800 },
        ],
    },
    {
        id: 'pass1', start: t2, end: t2b, bg: 'coal', enter: 'arch',
        devices: [
            { kind: 'phone', reel: pass1, x: 90, y: 150, s: 0.86 },
            { kind: 'tablet', reel: kds1, x: 500, y: 120, s: 0.97, inner: [{ at: t2, z: 1, fx: 683, fy: 450 }, { at: lands + 0.9, z: 1.5, fx: 600, fy: 300 }] },
        ],
        captions: [{ at: t2 + 0.2, end: t2b + 0.1, label: 'The pass', title: 'Paid. On the kitchen display in under two seconds.', x: 500, y: 34, w: 1300, size: 44, card: true }],
    },
    {
        id: 'board', start: t2b, end: t2c, bg: 'paper', enter: 'pass',
        devices: [{ kind: 'browser', reel: board1, ...browserFull, path: '/staff/orders' }],
        keys: [{ at: t2b, z: 1, fx: 720, fy: 450 }, { at: t2b + 1.3, z: 1.7, fx: 700, fy: 640, tx: 1240, ty: 560 }],
        captions: [{ at: t2b + 0.9, end: t2c, title: 'Nobody pressed Accept.', body: 'Hours, kitchen load, zone and payment were checked by a queued job.', x: 70, y: 330, w: 620, size: 56, card: true }],
    },
    {
        id: 'pass2', start: t2c, end: t3, bg: 'coal', enter: 'fade',
        devices: [
            { kind: 'phone', reel: pass2, x: 90, y: 150, s: 0.86 },
            { kind: 'tablet', reel: kds2, x: 500, y: 120, s: 0.97, inner: [{ at: t2c, z: 1.5, fx: 600, fy: 300 }, { at: t2c + 4.6, z: 1.5, fx: 600, fy: 300 }, { at: t2c + 5.6, z: 1, fx: 683, fy: 450 }] },
        ],
        captions: [{ at: t2c + 0.3, end: t3, title: 'Plated. Bumped. The guest’s phone heard it over Reverb.', x: 500, y: 34, w: 1300, size: 44, card: true }],
    },
    {
        id: 'assign', start: t3, end: t3b, bg: 'paper', enter: 'pass',
        devices: [{ kind: 'browser', reel: assign, ...browserFull, path: '/staff/orders' }],
        keys: [{ at: t3 + 0.1, z: 1.5, fx: 1229, fy: 590, tx: 1200, ty: 560 }],
        captions: [{ at: t3 + 0.2, end: t3b + 0.1, label: 'The road', title: 'One tap gives the run to a rider.', x: 70, y: 330, w: 620, size: 52, card: true }],
    },
    {
        id: 'road', start: t3b, end: t4, bg: 'coal', enter: 'pass',
        devices: [
            { kind: 'phone', reel: guestA, x: 880, y: 62, s: 1.1 },
            { kind: 'phone', reel: riderA, x: 1400, y: 62, s: 1.1 },
        ],
        captions: [
            { at: t3b + 0.3, end: t3b + 5.8, title: 'Two phones. One order.', body: 'Picked up on the rider’s. On its way on the guest’s.', x: 110, y: 400, w: 700 },
            { at: t3b + 6.0, end: t4 + 0.1, title: 'Delivered.', body: 'Every step also goes out by email. Text messages use a log driver until a provider is connected.', x: 110, y: 400, w: 700 },
        ],
    },
    {
        id: 'table', start: t4, end: t4b, bg: 'coal', enter: 'arch',
        devices: [{ kind: 'phone', reel: tableA, ...phoneRight }],
        captions: [{ at: t4 + 0.3, end: t4b + 0.1, label: 'The table', title: 'Table G1. Open the QR link, order from your seat.', body: 'No app. No account. It goes straight to the kitchen.', x: 130, y: 380, w: 860 }],
    },
    {
        id: 'floor', start: t4b, end: t4c, bg: 'paper', enter: 'pass',
        devices: [
            { kind: 'browser', reel: floorB, x: 40, y: 150, s: 0.98 },
            { kind: 'phone', reel: tableB, x: 1490, y: 150, s: 0.86 },
        ],
        keys: [{ at: t4b, z: 1, fx: 720, fy: 450 }, { at: t4b + 2.0, z: 1.45, fx: 380, fy: 330, tx: 620, ty: 520 }],
        captions: [{ at: t4b + 0.2, end: t4c + 0.1, title: 'Call a waiter, and the floor plan says so.', x: 40, y: 34, w: 1400, size: 44, card: true }],
    },
    {
        id: 'bill', start: t4c, end: t4d, bg: 'coal', enter: 'pass',
        devices: [{ kind: 'phone', reel: tableC, ...phoneRight }],
        captions: [{ at: t4c + 0.3, end: t4d + 0.1, title: 'Split it evenly. Pay your half.', body: 'Shares add up to the bill to the penny.', x: 130, y: 400, w: 860 }],
    },
    {
        id: 'booking', start: t4d, end: t5, bg: 'paper', enter: 'pass',
        devices: [{ kind: 'phone', reel: booking, ...phoneRight }],
        captions: [{ at: t4d + 0.3, end: t5 + 0.1, title: 'Friday, four people, half past seven.', body: 'Time slots, a deposit from parties of six, a reminder two hours before.', x: 130, y: 400, w: 860 }],
    },
    {
        id: 'owner', start: t5, end: t6, bg: 'paper', enter: 'arch',
        devices: [{ kind: 'browser', reel: owner, ...browserFull, path: '/staff' }],
        keys: [
            { at: t5, z: 1, fx: 720, fy: 450 },
            { at: os[1].film0 + 2.3, z: 1.45, fx: 800, fy: 560, tx: 1120, ty: 560 },
            { at: os[2].film0 + 0.3, z: 1, fx: 720, fy: 450 },
            { at: os[2].film1 + 0.1, z: 1.45, fx: 800, fy: 300, tx: 1130, ty: 400 },
            { at: os[4].film0 + 0.2, z: 1, fx: 720, fy: 450 },
        ],
        captions: [
            { at: t5 + 0.3, end: os[1].film0, label: 'The owner', title: 'The numbers, from the same orders.', x: 70, y: 930, w: 1500, size: 44, card: true },
            { at: os[1].film0 + 0.2, end: os[2].film0, title: 'That kebab took lamb mince under its reorder level.', x: 70, y: 930, w: 1500, size: 44, card: true },
            { at: os[2].film0 + 0.2, end: os[3].film0, title: 'A draft purchase order was already waiting.', x: 70, y: 930, w: 1500, size: 44, card: true },
            { at: os[3].film0 + 0.1, end: os[4].film0, title: 'Logged as “By the system”, every time.', x: 70, y: 930, w: 1500, size: 44, card: true },
            { at: os[4].film0 + 0.1, end: t6 + 0.1, title: 'And every automation has a switch.', x: 70, y: 930, w: 1500, size: 44, card: true },
        ],
    },
    {
        id: 'themes', start: t6, end: t6b, bg: 'coal', enter: 'pass', devices: [], custom: 'themes',
        captions: [{ at: t6 + 0.3, end: t6b + 0.1, label: 'The details', title: 'Light, and banked coals.', x: 70, y: 950, w: 1500, size: 40, card: true }],
    },
    {
        id: 'motion', start: t6b, end: T_ENG, bg: 'paper', enter: 'fade',
        devices: [{ kind: 'browser', reel: motion, ...browserFull, path: '/styleguide/motion' }],
        captions: [{ at: t6b + 0.2, end: T_ENG + 0.1, title: 'Motion, served from the pass.', x: 70, y: 950, w: 1500, size: 40, card: true }],
    },
    { id: 'eng', start: T_ENG, end: T_CLOSE, bg: 'coal', enter: 'arch', devices: [], custom: 'eng' },
    { id: 'close', start: T_CLOSE, end: TOTAL, bg: 'paper', enter: 'pass', devices: [], custom: 'close' },
];

export const cues = {
    lands, bumpFilm,
    phoneTicks: [guest1, pass1, tableA, tableB, tableC, booking, riderA].flatMap((r) => eventsInFilm(r, 'click')),
    deskTicks: [kds2, assign, owner, motion].flatMap((r) => eventsInFilm(r, 'click')),
    transitions: shots.filter((s) => s.enter === 'arch' || s.enter === 'pass').map((s) => s.start),
    picked: eventsInFilm(riderA, 'click'),
};
