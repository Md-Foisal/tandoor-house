# Film notes

How the presentation film was made, and why. Credits and licences are in `CREDITS.md`; the upload text is in `youtube.md`.

## Research

Pages opened on 4 November 2026.

- **Structure.** [Motion's launch video guide](https://www.motiontheagency.com/blog/product-launch-video-guide) wants "what is it?" answered in the first moments, and warns against explaining every feature. It puts most launch films under 90 seconds and product demos at 60 to 180 seconds. [Vidpros](https://vidpros.com/product-launch-video/) builds on pain, one workflow demonstrated, and one proof point.
- **Pointer and zoom.** [DataStudios on Screen Studio](https://www.datastudios.org/post/screen-studio-auto-zoom-mechanism-mac-only-limits-and-pricing-explained) describes zooms generated from cursor and click data, and warns that automatic zooms can crop out context the viewer needs.
- **Licences.** [Remotion's licence](https://github.com/remotion-dev/remotion/blob/main/LICENSE.md) is free for individuals. [Mixkit's licence](https://mixkit.co/license/) was read in full. [Pixabay's summary](https://pixabay.com/service/license-summary/) was also acceptable, but its music pages refused scripted access, so Mixkit was used.

## Decisions

- **One workflow, not a feature list.** 2:22 is longer than a launch film, so it follows one real order through every screen it touches. It stays a demo length because this is a portfolio piece, for a reader who has already chosen to look.
- **Captions, no voiceover.** No voice available here sounds professional, and a robotic one is worse than none. The story reads with the sound off.
- **Capture: CDP screencast, frame by frame.** Each camera saves JPEG frames with real timestamps and the edit picks the frame for each film time, so there is no video decode or time-base drift, and cameras in a split screen stay in sync by wall clock.
- **True device pixels.** Screencast frames ignore Playwright's emulated `deviceScaleFactor`: at "2x" they were still 1440 wide. Setting `--force-device-scale-factor` on the browser gives real pixels (desktop 4320 wide at 3x, phone 1560 wide at 4x). Phones use a metrics override for a 390px layout, because new headless windows will not go below 500px.
- **Pointer rebuilt in the edit.** The capture logs targets and clicks, and the edit draws the pointer and touch marks. Zooms are placed by hand, not generated, so they never crop what the viewer needs.
- **4K, 60 fps, H.264.** The opening hero clip is the app's own 1080p file, upscaled and dimmed under the title. Everything else is sharper than 4K before zooms.
- **Cuts on the music.** Chapter changes sit on the beat grid measured from the track (89.6 BPM). The music enters properly at about 8 s, where the opening hands over to the product.
- **Two encodes.** The repository copy is 4K60 H.264 at 87 MB, under GitHub's 100 MB limit. The master is 150 MB (CRF 12) for upload.
- **Sound effects synthesised**, so no licence question and full control of level.

## What is real, and what was arranged

The app code is unchanged. Live moments happened live, over Reverb and a queue worker, on this worktree's own SQLite database.

Arranged for the camera, and only these:
- The clock was shifted to 18:40 London time by a `Carbon::setTestNow` prepend and a browser `Date` shift, so the film reads as evening service.
- Seeded data, with a few set-up changes: lamb mince was set just above its reorder level so this order is the one that crosses it; the demo customer got a fixed name and number; other riders' runs were unassigned; table G1 was marked seated.
- Older tickets and new orders were cleared through the real screens before recording, so the new ticket lands in view.
- Waits, typing and some steps are sped up or trimmed in the edit, up to 1.8×.
- The guest is signed in, because signed-in customers get tracking over the socket. Guests who are not signed in get the same updates by polling.

Measured: the ticket reached the kitchen display 1.1 to 1.7 s after Pay was pressed, across takes, hence "under two seconds". The tests shown come from a real run (`php artisan test`, 2,508 passed; assertions vary between 13,815 and 13,893 from run to run, because the seeded data is random, so the film says "over 13,800"); that run needs a hostname `APP_URL`, because `HostsAndUrlsTest` builds subdomains from it.

## Audio

Mixed in `tools/film/scripts/mix.mjs`: the music is ducked 4.5 dB under the bell and the final stamp. Measured with FFmpeg's `ebur128`: about -14 LUFS integrated, peaks at -1.3 dBFS, no clipping.

The mix was checked by measurement and waveform only, not by ear. Listen to it before publishing.

## Rebuilding

From `tools/film` after `npm install`, with the app built in the worktree (`composer install`, `npm ci`, `npm run build`, a `.env` with `APP_URL=http://127.0.0.1:8020`, Reverb on 8021, `CACHE_STORE=file`):

1. Download the track in `CREDITS.md` and convert it to `public/audio/bed.wav`. Copy the fonts to `public/fonts` and the hero to `public/media/hero.mp4`.
2. `scripts/serve.sh --reseed`, then `node scripts/shoot-service.mjs`, `node scripts/shoot-rest.mjs`, `node scripts/index.mjs`.
3. `node scripts/mix.mjs`, then `npm run render`.
