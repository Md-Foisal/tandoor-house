// Compacts every camera's index.json into one module the edit can import.
import { existsSync, mkdirSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
const out = {};
for (const s of readdirSync('public/raw')) for (const c of readdirSync(`public/raw/${s}`)) {
    const f = `public/raw/${s}/${c}/index.json`;
    if (!existsSync(f)) continue;
    const d = JSON.parse(readFileSync(f));
    const t0 = d.frames[0][1];
    const r = (v) => +(v - t0).toFixed(3);
    out[`${s}/${c}`] = { size: d.size, times: d.frames.map((x) => r(x[1])), marks: Object.fromEntries(Object.entries(d.marks).map(([k, v]) => [k, r(v)])), events: d.events.map((e) => ({ ...e, t: r(e.t) })) };
}
mkdirSync('src/data', { recursive: true });
writeFileSync('src/data/captures.json', JSON.stringify(out));
console.log(Object.keys(out).length, 'cameras');
