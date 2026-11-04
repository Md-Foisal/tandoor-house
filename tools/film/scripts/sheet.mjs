// Contact sheet of a camera at given second offsets: node scripts/sheet.mjs scene/cam out.jpg cols height [every|t1,t2]
import { readFileSync } from 'node:fs'; import { execFileSync } from 'node:child_process'; import ff from 'ffmpeg-static';
const [,,cam,out,cols='8',h='300',spec='5']=process.argv; const d=JSON.parse(readFileSync(`public/raw/${cam}/index.json`)); const F=d.frames; const t0=F[0][1]; const dur=F.at(-1)[1]-t0;
const ts=spec.includes(',')?spec.split(',').map(Number):Array.from({length:Math.floor(dur/+spec)+1},(_,i)=>i*+spec);
const picks=ts.map(t=>{let b=F[0];for(const f of F){if(f[1]-t0<=t)b=f;else break}return `public/raw/${cam}/${b[0]}`});
const args=['-v','error','-y'];picks.forEach(p=>args.push('-i',p));const n=picks.length;const rows=Math.ceil(n/+cols);
const fc=picks.map((_,i)=>`[${i}:v]scale=-2:${h}[v${i}]`).join(';')+';'+picks.map((_,i)=>`[v${i}]`).join('')+`xstack=inputs=${n}:layout=${Array.from({length:n},(_,i)=>{const c=i%+cols,r=Math.floor(i/+cols);return `${c?Array.from({length:c},(_,k)=>'w'+k).slice(0,c).join('+'):'0'}_${r?Array.from({length:r},(_,k)=>'h'+(k*+cols)).join('+'):'0'}`}).join('|')}:fill=black[o]`;
execFileSync(ff,[...args,'-filter_complex',fc,'-map','[o]','-q:v','6',out]);
