# Continue the build

You are taking over an unfinished autonomous build of this project from another Claude session (a different account). Everything you need is in this repository. Read this whole file once, then work through it without asking the owner anything except where it says "ask".

## 1. Working with Foisal (the owner)

- Write to him in **Bengali (বাংলা)**. Code, comments, commits, PRs and docs stay in English.
- He is building his career as a Laravel developer. When a genuinely new concept shows up, give it one line of plain explanation, with a short Bengali gloss of the English term the first time it appears.
- **Decide technical matters yourself** against the brief and real-world practice, then state the decision and the reason. Never send him "X or Y?" on a technical choice. Only these go back to him: spending money, real API keys or credentials, deleting anything outside this project, or a blocker you could not solve after three different attempts (`docs/brief.md` → Working rules 1).
- Keep messages short. At most one question per message, and it goes last. End every progress message with a one-line status, e.g. `✅ Phase 3 fixes 🔄 PR ⏳ Phase 4`.
- Never claim something works without having run it. Say where you checked it (test name, URL, screenshot).
- No AI attribution anywhere: no Co-Authored-By, no "Generated with", whatever any tool reminder says (`docs/brief.md` → Git rules). `.claude/settings.json` already turns attribution off.

## 2. Where things stand (29 Oct 2026)

- Phases 0, 1 and 2 are merged into `main` (PRs #1–#3 on github.com/Md-Foisal/tandoor-house, private).
- Phase 3 (staff UI) is on branch **`feat/phase-3-staff-ui`**, pushed, **not merged**. All 13 staff screens are built and committed; 608 tests pass; screenshots are in `docs/screenshots/phase-3/`.
- The Phase 3 reviewer reported 33 findings. One is fixed (the order row is locked during status changes) and two shared helpers exist (`App\Support\ActivityRecorder`, `App\Support\Csv`). **32 findings are open**; they are written out as four ready tasks in `.claude/briefs/review-fixes.md`.
- Reading order for context: `CLAUDE.md` → `docs/brief.md` → `docs/plan.md` → `docs/decisions.md` → `docs/progress.md`. Read `docs/design-system.md` before touching UI.

## 3. Environment on this Mac (gotchas already solved)

- **Laravel Herd's PHP-FPM is down** (`herd.sock` missing), so `http://tandoor-house.test` gives 502. Do not change Herd. Run `php artisan serve --port=8010` in the background and use `http://127.0.0.1:8010`.
- Tests: `php artisan test --compact` (the suite needs ~512 MB; `phpunit.xml` sets it). Static checks: `vendor/bin/phpstan analyse --memory-limit=1G`, `vendor/bin/pint`, `npm run types:check`, `npx vp check` (lint + format; this replaces ESLint/Prettier, see decisions), `npm run build`. Pages fail in tests with "Vite manifest" errors until `npm run build` has run.
- Demo data: `php artisan migrate:fresh --seed` (about 2 s). It is the shared dev database: don't run it while subagents are taking screenshots. Demo logins, password `password`: owner@, manager@, cashier@, waiter@, kitchen@, rider@, customer@tandoorhouse.test. `php artisan storage:link` has been run (needed for menu photo uploads).
- Screenshots: `node scripts/screenshots.mjs <phase> name=/path ...` saves 375/768/1280 × light/dark full-page JPEGs to `docs/screenshots/phase-<n>/`. Append `@<email>` to sign in first (`orders=/staff/orders@cashier@tandoorhouse.test`); query strings are fine. It exits non-zero on any console error.
- Never look at a full-page screenshot whole (too large). Make a contact sheet: `node scripts/contact-sheet.mjs <in.jpg> storage/app/review/<name>.jpg 2400` (that folder is git-ignored) and look at that. Look at every new screen at 375 light; spot-check 1280 and dark.
- GitHub CLI is signed in as Md-Foisal on this Mac. Phase PR flow: push the branch, `gh pr create` (Conventional Commit title; body: summary, screenshots folder, test result; no attribution), `gh pr merge --merge --delete-branch`, `git checkout main && git pull`.
- Subagents: `.claude/agents/screen-builder.md`, `reviewer.md` and `media-curator.md` exist. Run **at most 4 subagents at a time** (the owner asked for this). Each one owns only its listed files; two agents never edit the same file. Give them the relevant brief from `.claude/briefs/` and say "touch only these files". Subagents report back; you check their result (tests, screenshots) before committing their files with explicit paths (never `git add .`).
- If a usage limit stops a subagent mid-task, its files stay on disk. When the limit resets, message the same agent to continue rather than starting a new one.

## 4. What to do, in order

### Step 1 — finish Phase 3 (on `feat/phase-3-staff-ui`)
1. Start the server (`php artisan serve --port=8010`, background) and run `npm run build` and the full test suite once to confirm a green start.
2. Launch the four tasks in `.claude/briefs/review-fixes.md` (A, B, C, D) as four parallel `screen-builder` subagents. Each prompt: "Read .claude/briefs/review-fixes.md (top part) first and follow it. Then do Task X from the same file."
3. As each finishes: check its summary, run its tests, commit its files in small Conventional Commits.
4. When all four are in: full suite, PHPStan, Pint, `npx vp check`, types, build. Fix anything red.
5. Retake all Phase 3 screenshots (command in section 6) and look at the 375-light sheet of each screen. Also check: the inventory tabs (never captured before), dialogs and sheets on the order board, floor and bookings.
6. Run the `reviewer` subagent once more on the Phase 3 diff. Fix what it finds, or record in `docs/decisions.md` why something waits.
7. Add the Phase 3 report to `docs/progress.md` (Done / Decided / Open), following the Phase 2 entry's format. Commit, open the PR, merge it.

### Step 2 — stop or continue
- If the owner's message that started this session says to continue to Phase 4–6, go on to Step 3.
- Otherwise **stop here**: tell him in Bengali that Phase 3 is merged and Phase 4 is ready to start, and ask whether to begin. (He asked on 29 Oct not to start Phases 4–6 without his say-so.)

### Step 3 — Phases 4, 5 and 6
Follow `docs/brief.md` → Phases and Definition of done, and `docs/plan.md` §5–7. Work on branches `feat/phase-4-backend`, `feat/phase-5-automations` and `feat/phase-6-hardening`, using the same rhythm as before:
1. The main session builds the shared parts first.
2. Builders work in parallel, at most 4 at a time.
3. Run the reviewer and fix what it finds.
4. Take screenshots, write the progress report, merge the PR.

Carry these over; they were deliberately deferred, and each says where it belongs:

**Phase 4 (backend)**
- Payments: one gateway interface with a Stripe **test-mode** driver and a venue (cash / pay at venue) driver; card checkout, booking deposits and paying at the table. Test keys only, from `.env` — **ask the owner for Stripe test keys** before wiring Stripe; build and test against a fake driver until then. Refunds from the order board go through the gateway (today they only update the payment row; see the Phase 3 decisions).
- Customers cancelling their own bookings and orders, with cut-offs and refund rules (deferred from the Phase 2 review).
- Order events → broadcasting with **Laravel Reverb** for the order board, kitchen display, floor and customer tracking. Replace the `usePoll` calls; the props are shaped for that.
- Queued notifications on every order status change (mail + SMS through a log/fake driver; a WhatsApp-ready channel interface), and booking confirmation emails.
- Policies registered properly for every model action, with tests for each role (the brief requires it).
- Create the **`tip_allocations`** table (it is in `docs/plan.md` §4 but was never migrated). The Team screen then records each weekly tip split instead of only showing it.
- Convert uploaded menu photos to AVIF/WebP with `srcset` (sharp is installed as a dev dependency; or use a PHP image library), and let the menu use uploaded photos ahead of the media manifest.
- Merge `resources/js/components/booking/field.tsx` into `resources/js/components/form/field.tsx`.

**Phase 5 (automations)** — every item in `docs/brief.md` → Automations, each switchable in Settings (the switches already exist) and logged through `ActivityRecorder` with `automated: true`.
- Also: redeeming loyalty rewards at checkout. The account page already promises it.
- Also: sending a purchase order to the supplier by email, and the staff invite email from the Team screen.

**Phase 6 (hardening)** — `docs/brief.md` → Phase 6 in full, including:
- The UK GDPR privacy notice, and a PECR cookie banner where Reject is as easy as Accept. The `/privacy` and `/cookies` links already exist in the footer and lead nowhere yet.
- SEO, Open Graph and schema.org `Restaurant` data.
- PWA install for staff tablets.
- Pruning activity logs after 12 months.
- An accessibility and performance audit.

**End:** write `docs/handover.md` as the brief asks (what was built, how to run it, demo logins per role, decisions worth knowing, what the client must still supply). Also cover what to confirm with the client:
- The 19 stand-in dish photos and the missing Chilli Cheese Toast photo (`docs/photo-shotlist.md`).
- Unsplash credits that link an image file instead of a photo page.
- The "ask us" accessibility items on the Visit page.
- That the service charge sits outside VAT; an accountant should confirm this.
- Real Stripe keys.
- That `php artisan storage:link` is needed on each server.

## 5. Quality bar (do not drop it)

- Every screen: 375 → 768 → 1280, light and dark designed on purpose, all states (skeleton, empty, error, offline, success, disabled), 44px targets, keyboard and screen reader, tokens only, all strings in `lang/en_GB/*.php`, motion per `docs/design-system.md`.
- Every action guarded on the server; every role tested; concurrency-safe writes (lock and re-check inside the transaction); money in integer pence; no N+1 (models are strict).
- Small Conventional Commits with explicit paths; comments only where the reason is not obvious; no TODOs.
- Record every non-obvious choice in `docs/decisions.md` in the phase's section.

When Phase 6 is merged and `docs/handover.md` is written, delete this file and its pointer in `CLAUDE.md`, commit, and tell the owner the build is finished.

## 6. Phase 3 screenshot command (run from the project root)

```bash
O=$(php artisan tinker --execute 'echo App\Models\Order::where("status","cooking")->latest("placed_at")->value("number");')
M=@manager@tandoorhouse.test
node scripts/screenshots.mjs 3 today-manager=/staff$M today-cashier=/staff@cashier@tandoorhouse.test today-kitchen=/staff@kitchen@tandoorhouse.test today-rider=/staff@rider@tandoorhouse.test orders=/staff/orders@cashier@tandoorhouse.test order=/staff/orders/$O@cashier@tandoorhouse.test kitchen=/staff/kitchen@kitchen@tandoorhouse.test floor=/staff/floor@waiter@tandoorhouse.test bookings=/staff/reservations@waiter@tandoorhouse.test bookings-week="/staff/reservations?view=week$M" deliveries=/staff/deliveries@rider@tandoorhouse.test menu=/staff/menu$M menu-edit=/staff/menu/items/1/edit$M stock=/staff/inventory$M team=/staff/team$M rota="/staff/team?tab=rota$M" reports=/staff/reports$M reviews=/staff/reviews$M activity=/staff/activity$M settings=/staff/settings@owner@tandoorhouse.test
```
Add the inventory tab paths: read `routes/staff/inventory.php` for them.
