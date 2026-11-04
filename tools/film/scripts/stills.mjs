// Poster and silent teaser, cut from the finished film.
import { execFileSync } from 'node:child_process';
import ff from 'ffmpeg-static';

const film = '../../docs/media/film/tandoor-house-film.mp4';
const dir = '../../docs/media/film';
execFileSync(ff, ['-v', 'error', '-y', '-ss', '6.6', '-i', film, '-frames:v', '1', '-vf', 'scale=1920:1080:flags=lanczos', '-q:v', '2', `${dir}/poster.jpg`]);

const cuts = [[6.2, 1.8], [15.5, 1.8], [41.6, 2.4], [54, 2], [68, 2], [106, 1.8], [137, 2]];
const inputs = cuts.flatMap(([at, d]) => ['-ss', String(at), '-t', String(d), '-i', film]);
const graph = cuts.map((_, i) => `[${i}:v]fps=15,scale=1100:-2:flags=lanczos,setpts=PTS-STARTPTS[v${i}];`).join('') + cuts.map((_, i) => `[v${i}]`).join('') + `concat=n=${cuts.length}:v=1:a=0[o]`;
execFileSync(ff, ['-v', 'error', '-y', ...inputs, '-filter_complex', graph, '-map', '[o]', '-an', '-c:v', 'libwebp_anim', '-quality', process.argv[2] ?? '82', '-compression_level', '6', '-loop', '0', `${dir}/tandoor-house-teaser.webp`]);
execFileSync(ff, ['-v', 'error', '-y', ...inputs, '-filter_complex', graph.replace('[o]', '[m]') + ';[m]select=not(mod(n\\,20)),scale=360:-2,tile=4x2:padding=3[o]', '-map', '[o]', '-frames:v', '1', '/tmp/teaser.jpg']);
