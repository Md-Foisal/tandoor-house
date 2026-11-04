// Builds the soundtrack: the music bed, ducked on key moments, plus effects synthesised here and timed from the edit.
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { buildSync } from 'esbuild';
import ff from 'ffmpeg-static';

buildSync({ entryPoints: ['src/timeline.ts'], bundle: true, format: 'esm', outfile: 'out/timeline.mjs', loader: { '.json': 'json' }, logLevel: 'error' });
const { cues, TOTAL, shots, B } = await import(resolve('out/timeline.mjs'));

const SR = 48000;
const N = Math.ceil((TOTAL + 0.2) * SR);
const L = new Float32Array(N), R = new Float32Array(N);
const db = (d) => 10 ** (d / 20);
let seed = 7;
const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647) * 2 - 1;

function place(sig, at, gainDb, pan = 0) {
    const g = db(gainDb), i0 = Math.round(at * SR), gl = g * Math.cos((pan + 1) * Math.PI / 4) * Math.SQRT2, gr = g * Math.sin((pan + 1) * Math.PI / 4) * Math.SQRT2;
    for (let i = 0; i < sig.length && i0 + i < N; i++) if (i0 + i >= 0) { L[i0 + i] += sig[i] * gl; R[i0 + i] += sig[i] * gr; }
}
const make = (sec, f) => Float32Array.from({ length: Math.round(sec * SR) }, (_, i) => f(i / SR, i));
function onePole(sig, fc, high = false) {
    const a = 1 - Math.exp(-2 * Math.PI * fc / SR); let y = 0;
    return sig.map((x) => { y += a * (x - y); return high ? x - y : y; });
}

// UI tick: a very short woody click.
const tick = () => onePole(make(0.03, (t) => Math.exp(-t * 260) * (0.7 * Math.sin(2 * Math.PI * 1850 * t) + 0.5 * rnd())), 500, true);
// Soft low key for desktop clicks.
const thock = () => make(0.05, (t) => Math.exp(-t * 130) * (0.8 * Math.sin(2 * Math.PI * 720 * t) + 0.3 * Math.sin(2 * Math.PI * 190 * t)));
// Whoosh: band-limited noise with a rising then falling centre.
function whoosh(sec = 0.7) {
    let lp = 0, bp = 0;
    return make(sec, (t) => {
        const k = t / sec, env = Math.sin(Math.PI * k ** 0.8) ** 2, fc = 260 + 2100 * Math.sin(Math.PI * k) ** 2, f = 2 * Math.sin(Math.PI * fc / SR), q = 0.75;
        const hp = rnd() - lp - q * bp; bp += f * hp; lp += f * bp;
        return bp * env * 0.5;
    });
}
// The bell on the pass: inharmonic partials with a slow decay.
const bell = () => make(2.2, (t) => {
    const a = Math.min(1, t / 0.002);
    return a * (Math.exp(-t * 2.6) * Math.sin(2 * Math.PI * 1318 * t) * 0.6 + Math.exp(-t * 4.2) * Math.sin(2 * Math.PI * 1318 * 2.76 * t) * 0.22 + Math.exp(-t * 7) * Math.sin(2 * Math.PI * 1318 * 5.4 * t) * 0.1 + Math.exp(-t * 1.6) * Math.sin(2 * Math.PI * 659 * t) * 0.14);
});
// Rubber stamp on a ticket: low thud, knock and a paper snap.
const stamp = () => make(0.3, (t) => {
    const f = 55 + 70 * Math.exp(-t * 34);
    return Math.exp(-t * 22) * Math.sin(2 * Math.PI * f * t) * 0.95 + Math.exp(-t * 80) * Math.sin(2 * Math.PI * 410 * t) * 0.4 + Math.exp(-t * 300) * rnd() * 0.5;
});
// Fire in the clay oven under the cold open: brown noise and sparse crackle.
function fire(sec) {
    let b = 0;
    const crackle = new Float32Array(Math.round(sec * SR));
    for (let c = 0; c < sec * 7; c++) {
        const at = Math.floor(Math.abs(rnd()) * (crackle.length - 800)), amp = 0.1 + Math.abs(rnd()) ** 3 * 0.5, fc = 1500 + Math.abs(rnd()) * 3500;
        for (let i = 0; i < 700; i++) crackle[at + i] += amp * Math.exp(-i / 90) * Math.sin(2 * Math.PI * fc * i / SR);
    }
    return make(sec, (t, i) => { b = 0.996 * b + 0.02 * rnd(); return b * (0.85 + 0.15 * Math.sin(t * 5.1) * Math.sin(t * 1.7)) * 1.5 + crackle[i]; });
}

// Music bed.
const raw = execFileSync(ff, ['-v', 'error', '-i', 'public/audio/bed.wav', '-ar', String(SR), '-ac', '2', '-f', 'f32le', '-'], { maxBuffer: 2e9 });
const bed = new Float32Array(raw.buffer, raw.byteOffset, raw.length / 4);
const lifted = [cues.lands, cues.bumpFilm[cues.bumpFilm.length - 1]];
const duckAt = (t) => lifted.reduce((g, c) => {
    const d = t < c ? (c - t) / 0.25 : (t - c) / 1.5;
    return Math.min(g, d >= 1 ? 1 : 1 - (1 - db(-4.5)) * (1 - d) ** 0.7);
}, 1);
for (let i = 0; i < N; i++) {
    const t = i / SR, k = i * 2;
    if (k + 1 >= bed.length) break;
    const fadeOut = Math.min(1, Math.max(0, (TOTAL - 0.2 - t) / 2.2));
    const g = db(-2) * duckAt(t) * fadeOut;
    L[i] += bed[k] * g;
    R[i] += bed[k + 1] * g;
}

// Effects.
const fireSig = fire(9);
for (let i = 0; i < fireSig.length; i++) fireSig[i] *= Math.min(1, i / SR / 0.8) * Math.min(1, (fireSig.length - i) / SR / 3);
place(fireSig, 0, -21);
place(bell(), 3.4, -23);
for (const t of cues.transitions) place(whoosh(), t - 0.12, -22, 0);
for (const t of cues.phoneTicks) place(tick(), t, -30, 0.1);
for (const t of cues.deskTicks) place(thock(), t, -30, -0.1);
place(bell(), cues.lands, -17);
cues.bumpFilm.forEach((t, i, all) => place(stamp(), t, i === all.length - 1 ? -13 : -20));
place(stamp(), B(201), -20);

const out = new Float32Array(N * 2);
for (let i = 0; i < N; i++) { out[i * 2] = L[i]; out[i * 2 + 1] = R[i]; }
writeFileSync('out/mix.f32', Buffer.from(out.buffer));
const input = ['-v', 'error', '-f', 'f32le', '-ar', String(SR), '-ac', '2', '-i', 'out/mix.f32'];
const run = (args) => {
    const { spawnSync } = process.getBuiltinModule('node:child_process');
    return spawnSync(ff, args, { maxBuffer: 2e9 }).stderr.toString();
};
const stats = (file) => {
    const s = run(['-hide_banner', '-i', file, '-af', 'ebur128=peak=true', '-f', 'null', '-']);
    const tail = s.slice(s.lastIndexOf('Summary:'));
    return { I: +tail.match(/I:\s+(-?[\d.]+) LUFS/)[1], LRA: +tail.match(/LRA:\s+([\d.]+) LU/)[1], peak: +tail.match(/Peak:\s+(-?[\d.]+) dBFS/)[1] };
};
execFileSync(ff, [...input, '-y', '-c:a', 'pcm_f32le', 'out/mix-raw.wav']);
let s0 = stats('out/mix-raw.wav');
console.log('before', s0);
let gain = -14 - s0.I, final;
for (let pass = 0; pass < 3; pass++) {
    execFileSync(ff, ['-v', 'error', '-y', '-i', 'out/mix-raw.wav', '-af', `volume=${gain.toFixed(2)}dB,alimiter=limit=0.87:attack=4:release=60:level=disabled`, '-ar', String(SR), '-c:a', 'pcm_s24le', 'out/mix.wav']);
    final = stats('out/mix.wav');
    if (Math.abs(final.I + 14) < 0.15) break;
    gain += -14 - final.I;
}
console.log('final', final, 'gain', gain.toFixed(2));
writeFileSync('out/mix.json', JSON.stringify(final));
