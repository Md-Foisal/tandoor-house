import { AbsoluteFill, Easing, Img, interpolate, OffthreadVideo, Sequence, spring, staticFile, useCurrentFrame, useVideoConfig } from 'remotion';
import { B, capture, Caption, Device, FPS, Key, Reel, reel, Shot, shots, srcAt, T_CLOSE, T_ENG, TOTAL } from './timeline';

const C = {
    paper: '#F7F1E8', surface: '#FFFBF5', clay: '#EADFD0', ink: '#22180F', muted: '#6B5A4B', line: '#D9CBB8', ember: '#A0421C',
    coal: '#16110D', coalSurface: '#211A14', coalInk: '#F2E8DA', coalMuted: '#B5A595', coalLine: '#3A2F25', emberLit: '#E8784A', turmeric: '#E2B04A',
};
const display = 'Rozha One', ui = 'Hanken Grotesk';
const inOut = Easing.bezier(0.65, 0, 0.35, 1), out = Easing.bezier(0.22, 1, 0.36, 1);
const fontCss = `
@font-face{font-family:'Rozha One';src:url('${staticFile('fonts/rozha-one-latin-400-normal.woff2')}') format('woff2')}
@font-face{font-family:'Hanken Grotesk';font-weight:100 900;src:url('${staticFile('fonts/hanken-grotesk-latin-wght-normal.woff2')}') format('woff2')}`;

const useT = () => useCurrentFrame() / FPS;

function Screen({ r, t, w, h }: { r: Reel; t: number; w: number; h: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    let lo = 0, hi = cap.times.length - 1;
    while (lo < hi) { const mid = (lo + hi + 1) >> 1; if (cap.times[mid] <= src) lo = mid; else hi = mid - 1; }
    return <Img src={staticFile(`raw/${r.cam}/${String(lo).padStart(6, '0')}.jpg`)} style={{ position: 'absolute', inset: 0, width: w, height: h, display: 'block' }} />;
}

// Desktop pointer, rebuilt from the logged targets so it glides whatever the capture did.
function Pointer({ r, t }: { r: Reel; t: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    const ev = cap.events.filter((e) => e.type === 'move' || e.type === 'click');
    if (!ev.length) return null;
    const rest = { x: cap.size.width * 0.62, y: cap.size.height * 0.72 };
    const i = ev.reduce((found, e, n) => (e.t <= src ? n : found), -1);
    const to = i >= 0 ? ev[i] : rest;
    const from = i >= 1 ? ev[i - 1] : rest;
    const k = i >= 0 ? interpolate(src, [ev[i].t, ev[i].t + 0.34], [0, 1], { extrapolateRight: 'clamp', easing: inOut }) : 1;
    const x = from.x + (to.x - from.x) * k;
    const y = from.y + (to.y - from.y) * k;
    const click = cap.events.filter((e) => e.type === 'click' && e.t <= src).pop();
    const age = click ? src - click.t : 9;
    const press = age < 0.16 ? 0.9 : 1;
    return (
        <>
            {age < 0.55 && <div style={{ position: 'absolute', left: click!.x - 26, top: click!.y - 26, width: 52, height: 52, borderRadius: 99, border: `3px solid ${C.emberLit}`, opacity: 1 - age / 0.55, transform: `scale(${0.4 + age * 1.8})` }} />}
            <svg width="26" height="30" viewBox="0 0 26 30" style={{ position: 'absolute', left: x - 3, top: y - 2, transform: `scale(${press})`, filter: 'drop-shadow(0 3px 5px rgba(34,24,15,.4))' }}>
                <path d="M3 2 L3 24 L9 18.5 L13 27 L17.5 25 L13.5 16.8 L21.5 16.2 Z" fill={C.ink} stroke="#FFFBF5" strokeWidth="2" strokeLinejoin="round" />
            </svg>
        </>
    );
}

function Touch({ r, t }: { r: Reel; t: number }) {
    const cap = capture(r.cam);
    const src = srcAt(r, t);
    const near = cap.events.filter((e) => e.type === 'click').map((e) => ({ e, d: src - e.t })).find(({ d }) => d > -0.26 && d < 0.34);
    if (!near) return null;
    const { e, d } = near;
    const o = d < 0 ? interpolate(d, [-0.26, -0.06], [0, 0.85], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' }) : interpolate(d, [0, 0.34], [0.85, 0]);
    const s = d < 0 ? interpolate(d, [-0.26, 0], [1.25, 0.9], { extrapolateLeft: 'clamp' }) : interpolate(d, [0, 0.34], [0.9, 1.5]);
    return <div style={{ position: 'absolute', left: e.x - 23, top: e.y - 23, width: 46, height: 46, borderRadius: 99, background: 'rgba(232,120,74,.34)', border: '2px solid rgba(255,248,240,.9)', opacity: o, transform: `scale(${s})` }} />;
}

// Zoom inside a device's screen, so a split layout keeps both devices in frame.
function innerAt(d: Device, t: number, w: number, h: number) {
    if (!d.inner) return undefined;
    let cur = d.inner[0];
    for (let i = 1; i < d.inner.length; i++) {
        const a = d.inner[i - 1], b = d.inner[i];
        const k = interpolate(t, [b.at - 0.85, b.at], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: inOut });
        if (t >= b.at - 0.85) cur = { at: t, z: a.z * Math.pow(b.z / a.z, k), fx: a.fx + (b.fx - a.fx) * k, fy: a.fy + (b.fy - a.fy) * k };
    }
    return `translate(${w / 2 - cur.fx * cur.z}px, ${h / 2 - cur.fy * cur.z}px) scale(${cur.z})`;
}

function DeviceFrame({ d, t, dark }: { d: Device; t: number; dark: boolean }) {
    const { width: w, height: h } = capture(d.reel.cam).size;
    if (d.kind === 'phone') {
        const b = 13;
        return (
            <div style={{ position: 'absolute', left: d.x, top: d.y, width: w + b * 2, height: h + b * 2, transform: `scale(${d.s})`, transformOrigin: '0 0', borderRadius: 62, background: '#0E0A07', boxShadow: dark ? '0 0 0 1.5px #4A3C30, 0 40px 90px rgba(0,0,0,.65)' : '0 0 0 1.5px #3A2F25, 0 36px 70px rgba(34,24,15,.32), 0 8px 18px rgba(34,24,15,.18)' }}>
                <div style={{ position: 'absolute', left: b, top: b, width: w, height: h, borderRadius: 50, overflow: 'hidden', background: '#000' }}>
                    <Screen r={d.reel} t={t} w={w} h={h} />
                    <Touch r={d.reel} t={t} />
                </div>
            </div>
        );
    }
    if (d.kind === 'tablet') {
        const b = 14;
        return (
            <div style={{ position: 'absolute', left: d.x, top: d.y, width: w + b * 2, height: h + b * 2, transform: `scale(${d.s})`, transformOrigin: '0 0', borderRadius: 30, background: '#0E0A07', boxShadow: '0 0 0 1.5px #4A3C30, 0 40px 90px rgba(0,0,0,.65)' }}>
                <div style={{ position: 'absolute', left: b, top: b, width: w, height: h, borderRadius: 18, overflow: 'hidden', background: '#000' }}>
                    <div style={{ position: 'absolute', inset: 0, transformOrigin: '0 0', transform: innerAt(d, t, w, h) }}>
                        <Screen r={d.reel} t={t} w={w} h={h} />
                        <Pointer r={d.reel} t={t} />
                    </div>
                </div>
            </div>
        );
    }
    const bar = 40;
    return (
        <div style={{ position: 'absolute', left: d.x, top: d.y, width: w, height: h + bar, transform: `scale(${d.s})`, transformOrigin: '0 0', borderRadius: 16, overflow: 'hidden', background: C.surface, boxShadow: '0 0 0 1px rgba(34,24,15,.14), 0 30px 70px rgba(34,24,15,.22), 0 6px 16px rgba(34,24,15,.12)' }}>
            <div style={{ height: bar, background: C.clay, display: 'flex', alignItems: 'center', gap: 8, padding: '0 16px' }}>
                {[0, 1, 2].map((i) => <div key={i} style={{ width: 11, height: 11, borderRadius: 9, background: '#C9B8A4' }} />)}
                <div style={{ marginLeft: 16, flex: 1, maxWidth: 560, height: 24, borderRadius: 8, background: C.paper, fontFamily: ui, fontSize: 13, color: C.muted, display: 'flex', alignItems: 'center', padding: '0 12px' }}>Tandoor House{d.path && d.path !== '/' ? ` · ${d.path}` : ''}</div>
            </div>
            <div style={{ position: 'relative', width: w, height: h, overflow: 'hidden' }}>
                <Screen r={d.reel} t={t} w={w} h={h} />
                <Pointer r={d.reel} t={t} />
            </div>
        </div>
    );
}

// A slow camera: zooms arrive on `at`, having eased in over the preceding 0.8 s.
function cameraAt(keys: Key[], devices: Device[], t: number) {
    const pt = (k: Key) => {
        const d = devices[k.dev ?? 0] ?? { x: 0, y: 0, s: 1 };
        const pad = d.kind === 'browser' ? 40 : d.kind ? 13 : 0;
        const px = d.x + k.fx * d.s + (d.kind === 'browser' ? 0 : pad * d.s);
        const py = d.y + (k.fy + pad) * d.s;
        return { z: k.z, px, py, tx: k.z === 1 ? px : k.tx ?? 960, ty: k.z === 1 ? py : k.ty ?? 540 };
    };
    let cur = pt(keys[0]);
    for (let i = 1; i < keys.length; i++) {
        const a = pt(keys[i - 1]), b = pt(keys[i]);
        const dur = 0.85;
        const k = interpolate(t, [keys[i].at - dur, keys[i].at], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: inOut });
        if (t >= keys[i].at - dur) cur = { z: a.z * Math.pow(b.z / a.z, k), px: a.px + (b.px - a.px) * k, py: a.py + (b.py - a.py) * k, tx: a.tx + (b.tx - a.tx) * k, ty: a.ty + (b.ty - a.ty) * k };
    }
    const sx = cur.tx - cur.px * cur.z, sy = cur.ty - cur.py * cur.z;
    return `translate(${sx}px, ${sy}px) scale(${cur.z})`;
}

const EMBER = 'M2 5.2C18 3.1 31 2.6 46 3.6c13 .9 22 2 37 1.4 12-.5 22-1.8 35-2.6';
function EmberLine({ k, color, width = 150 }: { k: number; color: string; width?: number }) {
    return (
        <svg viewBox="0 0 120 8" width={width} height={width / 15} style={{ display: 'block', overflow: 'visible' }}>
            <path d={EMBER} fill="none" stroke={color} strokeWidth="2.6" strokeLinecap="round" pathLength={1} strokeDasharray={1} strokeDashoffset={1 - k} />
        </svg>
    );
}

function CaptionView({ c, dark, t }: { c: Caption; dark: boolean; t: number }) {
    const { fps } = useVideoConfig();
    if (t < c.at - 0.02 || t > c.end + 0.35) return null;
    const enter = spring({ frame: Math.round((t - c.at) * fps), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    const leave = interpolate(t, [c.end, c.end + 0.3], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: inOut });
    const ink = dark ? C.coalInk : C.ink, muted = dark ? C.coalMuted : C.muted, ember = dark ? C.emberLit : C.ember;
    const size = c.size ?? 68;
    return (
        <div style={{ position: 'absolute', left: c.x, top: c.y, width: c.card ? 'fit-content' : c.w, maxWidth: c.w, opacity: enter * leave, transform: `translateY(${(1 - enter) * 26}px)`, ...(c.card ? { background: dark ? C.coalSurface : C.surface, padding: '20px 34px 22px', borderRadius: 16, boxShadow: dark ? '0 0 0 1px #3A2F25, 0 18px 44px rgba(0,0,0,.55)' : '0 0 0 1px rgba(34,24,15,.08), 0 18px 44px rgba(34,24,15,.24)' } : {}) }}>
            {c.label && <div style={{ fontFamily: ui, fontWeight: 650, fontSize: 24, letterSpacing: '0.12em', textTransform: 'uppercase', color: ember, marginBottom: c.card ? 8 : 18 }}>{c.label}</div>}
            {c.title && <div style={{ fontFamily: display, whiteSpace: c.card && size < 50 ? 'nowrap' : undefined, fontSize: size, lineHeight: 1.08, letterSpacing: '-0.01em', color: ink, textWrap: 'balance' }}>{c.title}</div>}
            {size >= 60 && <div style={{ margin: '22px 0 20px' }}><EmberLine k={interpolate(t, [c.at + 0.15, c.at + 0.75], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: out })} color={ember} /></div>}
            {c.body && <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 32, lineHeight: 1.4, color: muted, marginTop: size >= 60 ? 0 : 16, textWrap: 'pretty' }}>{c.body}</div>}
        </div>
    );
}

function archClip(k: number) {
    // The mouth of the tandoor opening until it clears the frame.
    const w = interpolate(k, [0, 1], [0, 1500]);
    const h = interpolate(k, [0, 1], [0, 2000]);
    return `path('M ${960 - w} 1090 L ${960 - w} ${1090 - h + w} A ${w} ${w} 0 0 1 ${960 + w} ${1090 - h + w} L ${960 + w} 1090 Z')`;
}

function Open({ t }: { t: number }) {
    const grow = interpolate(t, [0.1, 3.2], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: out });
    const w = interpolate(grow, [0, 1], [90, 300]);
    const h = interpolate(grow, [0, 1], [140, 700]);
    const arch = `path('M ${960 - w} ${890} L ${960 - w} ${890 - h + w} A ${w} ${w} 0 0 1 ${960 + w} ${890 - h + w} L ${960 + w} ${890} Z')`;
    const name = interpolate(t, [3.4, 4.2], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: out });
    const dim = interpolate(t, [3.2, 4.4], [1, 0.42], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    const tag = interpolate(t, [4.9, 5.6], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: out });
    return (
        <AbsoluteFill style={{ background: C.coal }}>
            <div style={{ position: 'absolute', inset: 0, clipPath: arch, opacity: interpolate(t, [0, 0.5], [0, 1], { extrapolateRight: 'clamp' }) }}>
                <OffthreadVideo src={staticFile('media/hero.mp4')} muted style={{ width: 1920, height: 1080, opacity: dim, transform: `scale(${1.04 + t * 0.006})` }} />
            </div>
            <svg width="1920" height="1080" style={{ position: 'absolute', inset: 0 }}>
                <path d={`M ${960 - w} 890 L ${960 - w} ${890 - h + w} A ${w} ${w} 0 0 1 ${960 + w} ${890 - h + w} L ${960 + w} 890`} fill="none" stroke={C.emberLit} strokeWidth="3" opacity={interpolate(t, [0.2, 0.7], [0, 0.9], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' })} />
                <path d={`M ${960 - w - 50} 890 H ${960 + w + 50}`} stroke={C.coalInk} strokeWidth="3" opacity={0.75} strokeLinecap="round" />
            </svg>
            <div style={{ position: 'absolute', left: 0, right: 0, top: 450, textAlign: 'center', fontFamily: display, fontSize: 124, color: C.coalInk, letterSpacing: '-0.01em', opacity: name, transform: `translateY(${(1 - name) * 24}px)` }}>Tandoor House</div>
            <div style={{ position: 'absolute', left: 0, right: 0, top: 610, textAlign: 'center', fontFamily: ui, fontWeight: 450, fontSize: 38, color: C.coalInk, opacity: tag, transform: `translateY(${(1 - tag) * 14}px)` }}>Fire first. Then everything else.</div>
        </AbsoluteFill>
    );
}

function Themes({ t, start, end }: { t: number; start: number; end: number }) {
    const light = reel('look/light', start, [['start', 'end', 1]]);
    const dark = reel('look/dark', start, [['start', 'end', 1]]);
    const seam = interpolate(t, [start + 0.3, end - 0.4], [1400, 520], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: inOut });
    const s = 1.14, left = 140, top = 20;
    const W = 1440 * s, H = 900 * s;
    const box = { position: 'absolute' as const, left, top, width: W, height: H, borderRadius: 16, overflow: 'hidden' };
    const lean = 120;
    return (
        <AbsoluteFill>
            <div style={box}><Screen r={dark} t={t} w={W} h={H} /></div>
            <div style={{ ...box, clipPath: `polygon(0 0, ${seam + lean}px 0, ${seam - lean}px 100%, 0 100%)` }}><Screen r={light} t={t} w={W} h={H} /></div>
            <svg width="1920" height="1080" style={{ position: 'absolute', inset: 0 }}><line x1={left + seam + lean} y1={top} x2={left + seam - lean} y2={top + H} stroke={C.emberLit} strokeWidth="3" /></svg>
        </AbsoluteFill>
    );
}

const FACTS: [string, string][] = [
    ['2,508', 'Pest tests passing, over 13,800 assertions'],
    ['6', 'staff roles, every action behind a tested policy'],
    ['1p', 'money is integer pence, recomputed on the server'],
    ['26', 'pages audited with axe against WCAG 2.2 AA, both themes'],
];
const STACK = ['Laravel 13', 'Inertia 3', 'React 19', 'TypeScript strict', 'Tailwind 4', 'Reverb', 'Queued jobs', 'Stripe Payment Element'];

function Eng({ t }: { t: number }) {
    const { fps } = useVideoConfig();
    const rel = t - T_ENG;
    const reveal = (d: number) => spring({ frame: Math.max(0, Math.round((rel - d) * fps)), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    return (
        <AbsoluteFill style={{ background: C.coal, padding: '92px 130px' }}>
            <div style={{ fontFamily: ui, fontWeight: 650, fontSize: 24, letterSpacing: '0.12em', textTransform: 'uppercase', color: C.emberLit, opacity: reveal(0.2) }}>The build</div>
            <div style={{ fontFamily: display, fontSize: 72, lineHeight: 1.08, color: C.coalInk, marginTop: 16, opacity: reveal(0.3), transform: `translateY(${(1 - reveal(0.3)) * 22}px)` }}>The server is the source of truth.</div>
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '36px 70px', marginTop: 60 }}>
                {FACTS.map(([n, l], i) => {
                    const k = reveal(1.1 + i * 0.5);
                    return (
                        <div key={n} style={{ opacity: k, transform: `translateY(${(1 - k) * 22}px)`, borderTop: `1.5px solid ${C.coalLine}`, paddingTop: 20 }}>
                            <div style={{ fontFamily: display, fontSize: 84, lineHeight: 1, color: C.emberLit, fontVariantNumeric: 'tabular-nums' }}>{n}</div>
                            <div style={{ fontFamily: ui, fontSize: 31, lineHeight: 1.35, color: C.coalInk, marginTop: 10 }}>{l}</div>
                        </div>
                    );
                })}
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginTop: 54 }}>
                {STACK.map((s, i) => {
                    const k = reveal(3.3 + i * 0.09);
                    return <div key={s} style={{ opacity: k, transform: `scale(${0.96 + k * 0.04})`, fontFamily: ui, fontWeight: 600, fontSize: 26, color: C.coalInk, border: `1.5px solid ${C.coalLine}`, background: C.coalSurface, borderRadius: 10, padding: '11px 20px' }}>{s}</div>;
                })}
            </div>
        </AbsoluteFill>
    );
}

function Close({ t }: { t: number }) {
    const { fps } = useVideoConfig();
    const rel = t - T_CLOSE;
    const r = (d: number) => spring({ frame: Math.max(0, Math.round((rel - d) * fps)), fps, config: { stiffness: 170, damping: 26, mass: 0.9 } });
    const fadeOut = interpolate(t, [TOTAL - 1.3, TOTAL - 0.15], [1, 0], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp' });
    return (
        <AbsoluteFill style={{ background: C.paper, alignItems: 'center', justifyContent: 'center', textAlign: 'center' }}>
            <svg viewBox="0 0 32 32" width="130" height="130" style={{ opacity: r(0.2), transform: `translateY(${(1 - r(0.2)) * 20}px)` }}>
                <path d="M5 29V15.5C5 9.15 9.92 4 16 4s11 5.15 11 11.5V29" fill="none" stroke={C.ink} strokeWidth="2.5" strokeLinecap="round" />
                <path d="M16 27c-3.2 0-5-2.1-5-4.6 0-2.9 2.6-4.1 3.4-7.4.9 1.7 1 2.9.6 4.2 1.6-.6 2.9-2.2 3.2-4 1.8 1.8 2.8 4.3 2.8 6.6 0 3-2 5.2-5 5.2Z" fill={C.ember} />
                <path d="M3 29h26" stroke={C.ink} strokeWidth="2.5" strokeLinecap="round" />
            </svg>
            <div style={{ fontFamily: display, fontSize: 112, lineHeight: 1.05, color: C.ink, marginTop: 20, opacity: r(0.4) }}>Tandoor House</div>
            <div style={{ margin: '26px 0 28px', display: 'flex', justifyContent: 'center' }}><EmberLine k={interpolate(rel, [0.7, 1.4], [0, 1], { extrapolateLeft: 'clamp', extrapolateRight: 'clamp', easing: out })} color={C.ember} width={240} /></div>
            <div style={{ fontFamily: ui, fontWeight: 600, fontSize: 40, color: C.ember, opacity: r(1.0) }}>github.com/Md-Foisal/tandoor-house</div>
            <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 30, color: C.ink, marginTop: 18, opacity: r(1.3) }}>Designed and built by Md. Foisal</div>
            <div style={{ fontFamily: ui, fontWeight: 450, fontSize: 22, color: C.muted, marginTop: 46, opacity: r(1.8) }}>A fictional restaurant. Food photos are free-licence stock. Payments run in test mode.</div>
            <AbsoluteFill style={{ background: C.coal, opacity: 1 - fadeOut }} />
        </AbsoluteFill>
    );
}

function ShotView({ shot, next }: { shot: Shot; next?: Shot }) {
    const frame = useCurrentFrame();
    const { fps } = useVideoConfig();
    const t = frame / fps + shot.start;
    const dark = shot.bg === 'coal';
    const rel = t - shot.start;
    const enterK = shot.enter === 'arch' ? interpolate(rel, [0, 0.95], [0, 1], { extrapolateRight: 'clamp', easing: inOut })
        : shot.enter === 'pass' ? spring({ frame, fps, config: { stiffness: 150, damping: 24, mass: 0.9 } })
        : shot.enter === 'fade' ? interpolate(rel, [0, 0.45], [0, 1], { extrapolateRight: 'clamp' }) : 1;
    const style: React.CSSProperties = {};
    if (shot.enter === 'arch') style.clipPath = enterK < 1 ? archClip(enterK) : undefined;
    if (shot.enter === 'pass') { style.transform = `translateY(${(1 - enterK) * 1100}px)`; style.boxShadow = '0 -30px 80px rgba(0,0,0,.28)'; }
    if (shot.enter === 'fade') style.opacity = enterK;
    const cam = shot.keys?.length ? cameraAt(shot.keys, shot.devices, t) : undefined;
    return (
        <AbsoluteFill style={{ background: dark ? C.coal : C.paper, ...style }}>
            {shot.custom === 'open' && <Open t={t} />}
            {shot.custom === 'themes' && <Themes t={t} start={shot.start} end={shot.end} />}
            {shot.custom === 'eng' && <Eng t={t} />}
            {shot.custom === 'close' && <Close t={t} />}
            <AbsoluteFill style={{ transform: cam, transformOrigin: '0 0' }}>
                {shot.devices.map((d, i) => <DeviceFrame key={i} d={d} t={t} dark={dark} />)}
            </AbsoluteFill>
            {shot.captions?.map((c, i) => <CaptionView key={i} c={c} dark={dark} t={t} />)}
        </AbsoluteFill>
    );
}

export function Film() {
    return (
        <AbsoluteFill style={{ background: C.coal }}>
            <style>{fontCss}</style>
            {shots.map((s, i) => {
                const next = shots[i + 1];
                const stop = next ? next.start + (next.enter === 'cut' ? 0 : 1.1) : TOTAL;
                return (
                    <Sequence key={s.id} from={Math.round(s.start * FPS)} durationInFrames={Math.round((stop - s.start) * FPS)} layout="none">
                        <ShotView shot={s} next={next} />
                    </Sequence>
                );
            })}
        </AbsoluteFill>
    );
}

export const _beats = B;
