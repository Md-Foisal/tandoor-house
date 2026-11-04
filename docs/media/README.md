# README media

Everything the root README shows. The videos and screenshots are made against the running app on fresh demo data; none is a mock-up.

| File | What it is | How it was made |
|---|---|---|
| `hero-light.webp`, `hero-dark.webp` | Customer site and kitchen display side by side | `python3 scripts/readme-hero.py` (Pillow), from `screens/` |
| `screens/*.webp` | One shot per role, device and colour scheme | `node scripts/readme-shots.mjs` |
| `customer-order.mp4`, `.gif`, `-poster.jpg` | A signed-in customer orders on a phone and pays with a test card | `node scripts/record-demos.mjs <dir> guest` |
| `order-through-the-kitchen.*` | The same order accepted on the order board and bumped on the kitchen display, with the customer's phone updating live beside it | `record-demos.mjs <dir> staff` (run after `guest`) |
| `book-a-table.*` | A table for six with a deposit paid by test card | `record-demos.mjs <dir> booking` |

The recordings are WebM files from Playwright. They were converted with `ffmpeg-static` installed outside the project:

```bash
# MP4 for the link, then a small GIF for the README (GitHub plays GIFs inline)
ffmpeg -i raw.webm -vf fps=25 -c:v libx264 -crf 26 -pix_fmt yuv420p -movflags +faststart out.mp4
ffmpeg -i out.mp4 -vf "fps=12,scale=380:-2:flags=lanczos,split[a][b];[a]palettegen=max_colors=160:stats_mode=diff[p];[b][p]paletteuse=dither=sierra2_4a:diff_mode=rectangle" -loop 0 out.gif
```

The staff video joins two recordings made at the same time (phone on the left, staff screens on the right) with ffmpeg's `hstack`.
Before recording, run `php artisan migrate:fresh --seed`, empty the customer's saved basket (`php artisan tinker --execute 'App\Models\Cart::query()->delete();'`) and have the queue worker and Reverb running.
