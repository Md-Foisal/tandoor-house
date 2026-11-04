// Renders a reduced contact sheet of the film at the given step: node scripts/preview.mjs <from> <to> <stepSeconds> <out.jpg> [cols]
import { bundle } from '@remotion/bundler';
import { renderStill, selectComposition, openBrowser } from '@remotion/renderer';
import { execFileSync } from 'node:child_process';
import { mkdirSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import ff from 'ffmpeg-static';
const [,, from, to, step, out, cols = '6'] = process.argv;
const serveUrl = process.env.BUNDLE ?? await bundle({ entryPoint: resolve('src/index.ts'), publicDir: resolve('public') });
const browser = await openBrowser('chrome');
const composition = await selectComposition({ serveUrl, id: 'Film', puppeteerInstance: browser });
const dir = resolve('out/preview'); rmSync(dir, { recursive: true, force: true }); mkdirSync(dir, { recursive: true });
const ts = []; for (let t = +from; t <= +to; t += +step) ts.push(t);
for (let i = 0; i < ts.length; i += 6) await Promise.all(ts.slice(i, i + 6).map((t, j) => renderStill({ serveUrl, composition, frame: Math.round(t * 60), output: `${dir}/${String(i + j).padStart(4, '0')}.jpg`, imageFormat: 'jpeg', jpegQuality: 70, scale: 0.2, puppeteerInstance: browser })));
await browser.close({ silent: true });
const rows = Math.ceil(ts.length / +cols);
execFileSync(ff, ['-v', 'error', '-y', '-framerate', '1', '-i', `${dir}/%04d.jpg`, '-vf', `tile=${cols}x${rows}:padding=4:color=white`, '-frames:v', '1', '-q:v', '5', out]);
console.log(ts.length, 'frames');
