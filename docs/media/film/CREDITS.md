# Film credits

Everything heard or seen in `tandoor-house-film.mp4` that did not come from the running app.

## Music

| Item | Source | Licence |
|---|---|---|
| "Smooth Jazz" by Francisco Alvear (track 640, downtempo, 2:22) | [Mixkit free stock music](https://mixkit.co/free-stock-music/downtempo/), file `https://assets.mixkit.co/music/640/640.mp3` | [Mixkit Stock Music Free License](https://mixkit.co/license/#musicFree), read on 4 November 2026 |

What that licence says, in short: free for commercial and non-commercial projects, including YouTube videos and online marketing; no attribution required. Not allowed: CDs, DVDs, TV or radio broadcast, video games, remixing into a music-only track, or registering the track on a rights management service. If YouTube raises a claim, the licence asks you to send the details to team@mixkit.co.

The track is used unaltered apart from level changes and a fade. The audio file is not stored in this repository, because the licence does not allow redistributing it on its own; `tools/film/scripts/mix.mjs` expects it at `tools/film/public/audio/bed.wav`.

## Sound effects

All synthesised for this film in `tools/film/scripts/mix.mjs`, no third-party samples: UI ticks and clicks, transition whooshes, the bell on the pass, the ticket stamp and the fire under the opening.

## Picture

- All product footage is the real app, recorded from its own screens.
- The opening film is the app's own hero clip; the food photos are the app's stand-ins. Their sources and licences are in `docs/image-credits.md`.
- Type: Rozha One and Hanken Grotesk (SIL Open Font License), from the app's own `@fontsource` packages.

## Tools

- [Remotion](https://github.com/remotion-dev/remotion/blob/main/LICENSE.md) 4 for the edit, under its Free License, which covers individuals, including commercial use.
- Playwright and Chromium for capture, FFmpeg (`ffmpeg-static`) for mixing, measuring and encoding.
