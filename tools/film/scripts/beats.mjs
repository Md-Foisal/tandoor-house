// Finds the beat grid of the music bed: autocorrelated tempo, then the phase with the strongest onsets.
import { execFileSync } from 'node:child_process'; import ff from 'ffmpeg-static';
const raw=execFileSync(ff,['-v','error','-i',process.argv[2],'-ac','1','-ar','11025','-f','f32le','-'],{maxBuffer:4e9});
const a=new Float32Array(raw.buffer,raw.byteOffset,raw.length/4); const hop=110; const sr=11025/hop; const env=[];
for(let i=0;i+hop<a.length;i+=hop){let s=0;for(let j=i;j<i+hop;j++)s+=a[j]*a[j];env.push(Math.sqrt(s/hop))}
const on=env.map((v,i)=>Math.max(0,v-(env[i-1]??0)));
let best=[0,0];for(let bpm=80;bpm<=160;bpm+=0.1){const lag=sr*60/bpm;let s=0;for(let k=1;k<=4;k++)for(let i=0;i<on.length-lag*k;i+=1)s+=on[i]*on[Math.round(i+lag*k)];if(s>best[0])best=[s,bpm]}
const bpm=best[1],p=sr*60/bpm;let ph=[0,0];for(let o=0;o<p;o+=0.5){let s=0;for(let t=o;t<on.length;t+=p)s+=on[Math.round(t)]||0;if(s>ph[0])ph=[s,o]}
console.log(JSON.stringify({bpm:+bpm.toFixed(1),beat:+(60/bpm).toFixed(4),first:+(ph[1]/sr).toFixed(3)}));
let e=[];for(let i=0;i<env.length;i+=Math.round(sr*2)){const b=env.slice(i,i+Math.round(sr*2));e.push(Math.round(20*Math.log10(Math.sqrt(b.reduce((x,y)=>x+y*y,0)/b.length)+1e-6)))}console.log(e.slice(0,10).join(' '),'...',e.slice(-8).join(' '));
