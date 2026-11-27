# Decisions

Non-obvious choices and why. Newest at the bottom of each phase.

## Phase 0

- **Lint and format with the starter kit's Vite+ (`vp check`), not a separate ESLint/Prettier install.** The React starter kit now ships Vite+ with Oxlint (ESLint-compatible rules, type-aware) and Oxfmt (Prettier-compatible). Adding ESLint and Prettier on top would run two linters with conflicting rules. `npm run check` is the lint/format gate; `npm run types:check` runs `tsc` in strict mode.
- **`.claude/` and `CLAUDE.md` are committed.** The starter kit's `.gitignore` excludes them; the brief needs the shared agents and attribution settings in the repo. Only `.claude/settings.local.json` stays ignored.
- **Schema arrives with the seed data that needs it.** Phases 1–3 build UI on realistic seeded data, which needs tables. Migrations, models and factories for each area are written when its UI is built; business logic (state machines, policies, payments, broadcasting, automations) arrives in Phases 4–5 as planned.
- **Roles as a backed enum column on `users`, not a permissions package.** Seven fixed roles (plus Guest, who is simply not signed in) with fixed abilities; policies read the enum. A package would add tables and runtime cost for flexibility nobody asked for.
- **Allergens stored as a JSON array of FSA codes on items and modifiers.** The 14 allergens are a fixed legal list (an enum), always read with the item and never queried on their own beyond filtering, which JSON handles.
- **Pull requests are merged by the main session with `gh pr merge --merge`.** The repository is private with one maintainer; the reviewer subagent is the review gate.
- **SQLite locally and in tests, MySQL in production.** Migrations avoid engine-specific SQL so both work.
- **A variant's price replaces the dish price; the dish price equals its cheapest variant.** Menus list "from £x" and the cart never adds a base and a variant together.
- **Modifier allergens add to the dish's allergens.** Where a choice changes an allergen (whole or oat milk in chai), the dish declares none for it and the required modifier carries it, so the declaration is right after every choice.
- **Alcoholic drinks are flagged (`is_alcoholic`).** Delivery and pickup orders containing them need an age confirmation (Licensing Act 2003, Challenge 25), enforced at checkout.

## Phase 1

- **Brand direction A, "Clay and Ember", chosen.** Scored highest; reasons in `docs/brand-directions.md`.
- **Fonts self-hosted from npm (`@fontsource`) instead of Bunny Fonts.** The starter kit loaded fonts from a third-party CDN; the brief asks for self-hosting, and it removes a third-party request (privacy and LCP).
- **Interface strings reach React as an Inertia "once" prop.** All of `lang/en_GB/*.php` is sent on the first visit and cached by the client; `useT()` resolves `group.key` with Laravel-style `:placeholders`. One source of truth for Blade, mail and React.
- **Content stays visible without JavaScript.** Animated elements carry `data-reveal`; a `<noscript>` rule in the document head resets their opacity and transform.
- **Screenshots are JPEG at quality 70.** Full-page PNGs of long pages were 3–5× larger with no review benefit.
- **The 1080p hero film was re-encoded to stay under 3 MB** (H.264 at 1.65 Mbit/s, VP9 at 1.5 Mbit/s).
- **Models are strict outside production** (`Model::shouldBeStrict`): lazy loading, silently discarded attributes and missing attributes throw, so N+1 queries are caught in development and tests.

## Phase 2

- **Online bookings go up to eight guests.** The largest table seats eight; bigger groups are asked to call so the team can join tables. Table merging for bookings arrives with the staff table map.
- **Tips are offered as a share of the bill (none, 10%, 12.5%, 15%) or a custom amount**, and stored in pence on the order; the 2023 tips act note is shown beside them.
- **The basket's channel is chosen at checkout**, where postcode or pickup time is known, so totals shown are never estimates of a different channel.
- **Guests who open someone else's order see a friendly page** with sign-in and call options instead of a bare 403, keeping the URL.
- **The menu is cached for ten minutes under one key** (`menu.v1`); menu management clears it on every change.
- **The test suite runs with a 512 MB memory limit, and the demo restaurant is seeded once in its own test.** One full seed (hundreds of orders) on top of 200 feature tests passed PHP's default 128 MB on Herd; seeding it per check also cost seconds.
- **Guest baskets are keyed by a random token kept in the session**, not by the session id. Sign-in regenerates the id but keeps session data, so the guest basket is found and folded into the customer's basket on their first request after signing in.
- **Public write endpoints are rate-limited per session** (basket 60/min, promo 10/min and 60/hour per IP, checkout 10/min, booking 30/min and 20/hour per IP, table actions 12/min per table and session, order lookups 60/min).
- **Promo codes are private unless marked public**; only public ones are listed in customer accounts.
- **Deferred from the phase 2 review, with reasons:** paying a booking deposit and paying at the table need the payment gateway (Phase 4); customers cancelling bookings and orders needs the state machines and refund rules (Phase 4); a removed service charge on a table bill is recorded by the waiter or cashier when they take payment (Phase 3 order board). The footer keeps the opening hours on every page because guests look for them there, even where a page also shows them.
- **The discretionary service charge sits outside the VAT on food.** VAT is the inclusive portion of the food and drink lines after any discount; the service charge, tip and delivery fee are added after. HMRC treats a genuinely optional service charge as outside the scope of VAT, which is how UK restaurants usually present it.
- **Table orders need the restaurant to be open and, when the basket has alcohol, an age confirmation** (Challenge 25), the same as checkout.
- **Booking occasions are stored as keys** (birthday, anniversary, business, other) and translated when shown.

## Phase 3

- **The order state machine arrives in Phase 3, not 4.** The order board and kitchen display need real status changes to be usable on seeded data; Phase 4 adds events, broadcasting, notifications and payments on top of it.
- **Staff sections are an enum (`StaffArea`) mapping each section to roles**; one gate per section (`staff.<area>`) guards its routes, and the sidebar lists only allowed sections. Per-record rules (a rider sees only their deliveries) live in policies.
- **Each staff section owns its own routes file** (`routes/staff/<area>.php`), so parallel builders never edit the same file.
- **The kitchen display is dark by default on every device** and remembers a cook's switch to light per device.
- **PDF receipts and reports use dompdf**, rendered from Blade on the server so they match the CSV exports exactly.
- **Refunds update the original payment row** (refunded amount, status, history in `meta.refunds`) instead of adding a negative row, because amounts are unsigned and a second row would double-count in reports. Cashiers may refund up to £50 per order in total; managers and owners have no limit; waiters cannot refund.
- **A closing time of midnight is stored as "24:00"** and shown as 00:00, so a period never wraps into the next day.
- **Owner-only settings:** VAT, service charge, tip presets, deposits and delivery zone fees and minimums. Managers see them read-only.
- **Hidden reviews need no extra column:** a review is hidden when it is unpublished and its latest moderation entry in the activity log says so.
- **The food hygiene rating shows the Food Standards Agency wording for its score**, or "awaiting inspection" before a first visit.
- **Kitchen "late" means ready after the estimate** (prep time plus four minutes), or after the slowest dish's prep time when there is no estimate, everywhere: kitchen display, Today, and reports.

## Phase 4

- SMS goes only to UK mobiles or full international E.164 numbers; others are skipped. Text over three segments is cut with an ellipsis. Review requests and abandoned-cart reminders are email only (`config/notify.php` kinds).
- WhatsApp is a ready interface with a null driver; it is offered only once a real driver is configured.
- Tip allocations are recorded per week (period = Monday). Re-recording replaces the week until any row is marked paid; only people with clocked hours are included.
- Uploaded menu photos are converted in a queued job to 4:5 AVIF and WebP at 480/960/1600 with a blurred placeholder. Imagick is used when it can write AVIF, then sharp through `scripts/convert-photo.mjs`, then GD (WebP only). The photo column stores disk paths; URLs are built when serialised. The manifest photo shows until conversion finishes, and a job skips its work if the photo was replaced meanwhile.
- A week's tip split can be recorded only after the week ends. Only the owner marks it paid; doing so twice keeps the first date. If tips change after recording, the Team screen warns, and the recorded amounts stand.
- One `PaymentGateway` interface with three drivers: a fake one for local work and tests (card amounts ending in 51p are declined, it refuses to run in production), a venue driver for cash and pay at the venue, and Stripe in test mode, called through Laravel's HTTP client rather than the Stripe SDK so tests fake it the same way. Stripe is used only when `STRIPE_SECRET` is set and refuses live keys.
- The fake gateway behaves like Stripe: starting a card payment needs a confirmation step, so the browser flow is the same for both.
- Webhooks are idempotent on the gateway reference. Paid is final for webhooks, and an amount that does not match is logged, not applied.
- The amount to pay always comes from the order or booking on the server (`Payable::amountOwed()`), never from the browser.
- Real-time updates use Laravel Reverb on private channels (`staff.orders`, `staff.kitchen`, `staff.floor`, `staff.reservations`, `staff.deliveries.{rider}`, `orders.{number}`), authorised with the same staff-area gates as the pages. Events carry ids and statuses only, never personal data; the page then reloads its own props through Inertia, so permissions are checked again on the server.
- Echo connects only when a page subscribes, so guests never open a socket. Without a connection (no Reverb, offline) pages fall back to polling, and after a dropped connection they reload once to catch up.
- Channel auth sends the XSRF cookie value, because the layout has no CSRF meta tag.
- Laravel Reverb needs Guzzle 7, so Guzzle moved from 8 to 7.
- Staff refunds go through the gateway. If a refund spans several payments and a later one fails, the earlier refunds stand and the error says how much went back.
- Card on the venue's own terminal is recorded as a card payment on the venue gateway (`meta.tender = terminal`), and refunding it is recorded for staff to reverse on the terminal. Cashiers, managers and owners take payment on any order; waiters only on dine-in.
- Staff screens subscribe to the channel that matches their job and keep their old polling interval as the fallback. Reviews and manual stock changes broadcast nothing, so those two screens still poll. The staff header shows a quiet Live / Reconnecting marker.
- Customers hear about the steps that change what they do: placed, accepted (with the time), ready (pickup only), out for delivery (delivery only), delivered, rejected and cancelled. Dine-in guests are at the table, so they only hear about a rejection or cancellation. Bookings send a confirmation (with an .ics file) and a cancellation notice.
- Each order or booking status is notified once, even when a job retries or an event repeats.
- The mail theme uses fixed colour values, because email clients ignore CSS variables.
- A card order stays `placed` and is hidden from staff until it is paid; only then does `OrderPlaced` fire. No stock is deducted when an order is placed, so an abandoned payment costs nothing. Reloading the pay page reuses the pending payment. Pickup and dine-in guests may switch to paying at the restaurant; delivery guests may not.
- Apple Pay and Google Pay are not separate checkout choices: Stripe's Payment Element offers them on the pay page when the device supports them. `@stripe/stripe-js` loads only when the Stripe driver is active.
- Customers may cancel an order only while it is still `placed` (before the restaurant accepts it); a card payment is refunded in full in the same transaction, so a failed refund leaves the order as it was.
- Bookings can be cancelled free up to 24 hours before (setting `reservation.free_cancel_hours`), with the deposit refunded. Later than that the deposit is kept, but cancelling still frees the table. The rule is shown before the guest confirms.
- A booking waiting for its deposit holds the table for 15 minutes (setting `reservation.hold_minutes`), counted from when it was made; the pay page then shows that the hold has lapsed. Nothing extra is stored.
- Emails link to a temporary signed URL, valid until a week after the booking. Opening it remembers the booking in that browser's session, so a guest can pay or cancel from any device.
- Order links in messages are signed and last seven days from the later of the order time and the send time.
- A booking is confirmed by whichever arrives first, the guest's return or the payment webhook; one action does it under a lock, so the confirmation goes out once. A deposit that lands after the booking was cancelled does not bring it back (Phase 5 refunds it).
- Each guest payment at the table is one `TableBillPayment` (a payable of its own), allocated to the table's orders oldest first. An order turns paid once it is covered. The browser sends only "whole bill" or an amount; the server checks it under a lock and refuses overpayment. A started payment holds its share for 15 minutes.
- A guest may leave off the discretionary service charge; the waived part is recorded pro rata. A tip paid at the table goes on the first order the payment covers, so the weekly tip split counts it.
- When the bill is fully paid, open bill requests are closed, the table moves to "bill requested" for clearing, and the floor updates live.
- Every staff write goes through a model policy (or a named gate ability for orders and bookings, whose model policies serve customers), with the staff-area gate kept as the outer layer. Tests check all eight roles against every write route over HTTP, and the policies directly.
- "Money settings" are owner-only: VAT, service charge, tip presets, deposits, adding, removing or pausing a delivery zone and its fee or minimum, a dish's VAT rate, and paying out tips.
- Where the running restaurant needs more than the plan's role table, the code wins: cashiers can see the floor and bookings, waiters can accept and edit orders and close bookings, kitchen staff can take in stock, count it and log waste.
- The shared real-time hook (`resources/js/lib/realtime.ts`) has no browser test yet; the project has no JavaScript test runner, so its fallback and catch-up logic were checked by hand and through the server-side event and channel tests.
- Starting a payment cancels every earlier open attempt for the same order, booking or table share, including declined ones, because a declined Stripe attempt can still be paid from an old tab.
- Money that lands on something already paid, cancelled, rejected or past its hold is refunded automatically and logged as automated. If the gateway refuses that refund, the payment stays paid with an `auto_refund_failed` marker for staff to refund by hand, and the order is not announced to the kitchen twice.
- A table share that lands after the bill is covered is refunded in proportion to what was paid; its tip goes back only if none of the share was used.
- A card payment that lands after an order switched to pay at the venue is kept, and the venue payment is cancelled.
- Payment webhooks in another currency, or without one, are ignored.
- Uploaded photos are limited to 6000 px a side and read with an explicit format, so a crafted file cannot exhaust memory.
- SMS in local work go to a log with the number masked and link query strings removed; in production the default SMS driver sends nothing until a real provider is configured.

## Phase 5

- Every automation goes through `App\Support\Automation`: it checks the owner's switch and logs what happened as automated. Housekeeping (closing lapsed deposit holds, expiring unpaid card orders) runs whatever the switches say.
- Booking reminders go to confirmed bookings starting within the next two hours; a booking made inside that window gets none, because its confirmation has just gone out. No-shows are released 20 minutes after the start time and keep their deposit. The reason a lapsed hold was cancelled lives in the activity log, as bookings have no reason column.
- Customer notifications respect the notifications switch and log the kind and channels sent, never the address or number.
- Auto-accept takes an order only when the restaurant is open, the kitchen has fewer than 25 open lines, a delivery postcode is inside an active zone, an online payment has gone through, and the total is under £150. Alcohol for pickup or delivery always goes to a person for the ID check; dine-in alcohol is accepted once the guest has confirmed their age. Anything else is left for staff, with the reasons logged.
- The prep estimate is the median start-to-ready time per dish over the last 30 days (at least three samples, otherwise the dish's set prep time), plus a minute for every two lines already cooking at that station (at most 30), taking the slowest dish and adding the zone's delivery time.
- Card orders still unpaid after 30 minutes are cancelled, after their open payment attempts are cancelled and the payment is checked once more.
- New staff get an email to set their own password, valid for seven days; nobody else ever sees or types it. Invites live in their own table (`staff_invites`) holding only a hash of a single-use token, so an ordinary password-reset link never works as an invite and clearing reset tokens never wipes an invite. An invite can be resent up to three times an hour until it is accepted.
- The nightly close and the Monday report go to owners only, are built from the same report sections as the Reports screen so the numbers always agree, and are sent once per day or week even if the command runs twice. The weekly email attaches each report section as CSV.
- Stock comes off when an order is accepted (recipe quantity times portions; variants and options have no recipes of their own) and goes back exactly if the order is later rejected or cancelled. A sale never takes stock below zero; a shortfall is logged.
- A dish is hidden as sold out as soon as one ingredient cannot cover a portion, and comes back after a restock only if nobody has edited it since the system hid it (`menu_items.sold_out_at`).
- Crossing the low-stock line sends one alert to managers and the owner and tops up the supplier's newest draft purchase order to the par level (or starts one).
- Sending a purchase order emails the supplier with the PDF attached, whatever the automation switches say, because a person chose to send it.
- Points are earned on food after discounts; tips, delivery and the service charge earn nothing. A full refund takes the points back, a partial refund does not. Redeemed points come back if the order is cancelled or rejected, whatever the switch says.
- A loyalty reward replaces a promo code (one discount per order) and applies only when the food is worth at least the reward, so no points are wasted.
- Only signed-in customers are asked for a review, an hour after completion, because reviews belong to an account.
- An abandoned basket is reminded once, when it has sat untouched for between two and 48 hours.
- Deducting and restoring stock both lock the order first, so a quick accept-then-cancel ends with the right stock whichever runs first.
- An abandoned-basket email counts as direct marketing under PECR, so it goes only to customers who opted in to marketing.
- No-shows are released only for bookings that started within the last six hours, so switching the automation on never sweeps up old bookings.
- Closing a lapsed hold cancels the open payment first and checks the booking again under a lock; a deposit that turned out to be paid is confirmed instead.
- Scheduled commands walk rows by id, so changing a row while looping never skips the next ones.
- Loyalty entries are kept unique by order and kind (earned, reversed, redeemed, returned), never by their wording. A full refund takes back earned points and returns any points spent on the order.
- Prep estimates have their own switch; with it off, an accepted order gets the slowest dish's set prep time, as the board did before.
- Kitchen stations are set on every line as the order is placed, from the dish's station, so routing needs no separate job or switch.
- Pages that subscribe to no live channel open no socket and show no Live marker.
- In tests, auto-accept is off unless a test turns it on, because it depends on the clock (opening hours).

## Phase 6

- **Staff PWA**: the manifest, icons and an offline page are served from `/staff`, and the service worker (scope `/staff/`) caches only the offline page and icons. No staff page, Inertia response or live data is ever cached, so a tablet can never show stale orders or another person's data after a shift change. Going offline shows a plain "you are offline" page; Reverb sockets are not fetches and are untouched. The customer site is not a PWA: guests have no need to install it.
- **SEO through Blade, not a JS head**: titles, descriptions, canonical URLs, Open Graph, robots and schema.org JSON-LD are built by `App\Support\Seo` and `StructuredData` and printed by the root Blade view, so crawlers and link previews get them without running JavaScript. Private paths (staff, account, basket, checkout, order links, auth) are `noindex` and left out of `sitemap.xml`. Structured data is `Restaurant` (address, opening hours, cuisine, price range, map position), `Menu` and `MenuItem` for dishes; nothing is invented, and fields without data are omitted. Social images are generated 1200 × 630 JPEGs under `public/media/og`.
- **Cookie consent (PECR)**: the app only sets strictly necessary cookies (session, CSRF, remember-me, the consent choice) and one optional preferences key (the saved theme). Necessary cookies need no consent, so the banner asks only about preferences, and Reject sits next to Accept with the same size and weight. Until the visitor accepts, the theme is not stored and the page follows the system setting. The list of everything stored lives in `config/cookies.php` and builds the cookie policy page, so the page cannot drift from the code. Bump `consent.version` to ask everyone again.
- **Privacy notice**: written for UK GDPR (lawful basis per purpose, retention, rights, ICO complaints). The ICO registration number is read from `RESTAURANT_ICO_NUMBER` and shown only when set, instead of printing a made-up number.
- **Retention**: activity logs are pruned after 12 months; bookings after 12 months, or six years when a deposit was taken (HMRC record keeping); orders and payments are kept six years. Abandoned baskets and expired staff invites are pruned too. The pruning runs daily from the scheduler and always runs, with no switch, because the privacy notice promises it.
- **Data rights**: "Download my data" produces a JSON file of the account, orders, bookings, reviews, addresses and loyalty entries, for the signed-in person only (there is no id to tamper with). "Delete my account" removes the person but keeps anonymised financial records, because tax law requires the sales to be kept; the name, email, phone and addresses are cleared and the orders no longer point at a person.
- **Security headers**: every response carries a CSP with a per-request nonce, `frame-ancestors 'none'`, nosniff, a strict referrer policy and a Permissions-Policy; HSTS is sent in production only. Scripts and frames are allowed for Stripe and nothing else; Reverb is allowed for the WebSocket. Local development adds the Vite and Herd origins, never production.
- **Trusted hosts**: in production only `APP_URL`'s host (and its `www.` form) is accepted, so a forged `Host` header cannot poison signed links, password-reset emails or canonical URLs.
- **Throttles**: every public write has a limiter keyed on the session or user and on the IP, and login, registration, password reset and two-factor use their own limits. Refunds, order edits and new staff sit behind a stricter limiter. Throttle responses are plain 429s with a retry time. A test walks every route as every role to check both the page access and these limits.
- **Private originals**: uploaded menu photos are kept on the private disk; only the converted AVIF and WebP sizes are public, so an uploaded original (and its metadata) is never served.
- **Focus rings**: the starter kit's shadcn components removed the browser outline and relied on utilities that the restyle had overridden, so keyboard focus was invisible. Outlines are now drawn from one token on every interactive element, never removed without a replacement, and the definition lists and small public tap targets found by the audit were fixed at the same time.
- **Accessibility audit**: axe (WCAG 2.2 AA) runs through `scripts/a11y-audit.mjs` on 26 pages at phone and desktop widths in both themes. Staff meters and tables are named, targets are at least 44 px, and the cookie banner renders inside the page layouts so it can be reached by keyboard and does not cover content.
- **Performance**: measured with `scripts/perf-audit.mjs` (Lighthouse, mobile preset, simulated slow 4G). The one clear miss was layout shift on the menu (CLS 0.209), caused by the two web fonts loading after the first paint; both are now preloaded in the root view, which brought CLS to 0.000 on every page. Largest Contentful Paint is already driven by one hero image or heading on each page, and the menu cache from Phase 2 keeps the server response near 100 ms. Lab LCP on the home, dish and visit pages stays above 2.5 s only under the simulated throttle (about 0.3 to 0.8 s unthrottled); the remaining cost is the shared 300 KB script bundle and 134 KB stylesheet, which is a deliberate trade for one-page-app navigation. See the numbers in `docs/progress.md`.
- **`STRIPE_ALLOW_LIVE`**: the Stripe driver refuses a live secret key unless `STRIPE_ALLOW_LIVE=true`, so a live key pasted into a staging `.env` by mistake cannot take real money. Launch day is one deliberate change.
- **Staff app files live in `public/staff-app/`**: a `public/staff/` folder shadowed the `/staff` route, which gave a 404 under `php artisan serve` and `composer dev`, because PHP's built-in server hands any existing path back to itself. The `.htaccess` rewrite skips existing folders in the same way. A test now fails if any public folder shares its name with a route.
- **Demo phone numbers**: seeders and factories draw from Ofcom's reserved `07700 900xxx` range, so every number passes the booking and checkout rules and none can reach a real person.
- **Hamburger menu alongside the tab bar**: the five phone tabs keep the actions guests repeat, and a menu in the header holds what would otherwise have no route on a phone (Our story, Find us, Allergens, theme). The same menu appears up to 1023px, because the tablet header has no room for the theme switch.
- **Icon file names**: pages link `/icon.svg` and `/icon.ico`. Herd's nginx answers `/favicon.ico` with a 404 status, so browsers ignored it and kept their cached Laravel icon. `/favicon.ico` stays for clients that ask for it by default.
- **Vite binds to `127.0.0.1`**: on macOS `localhost` resolves to `[::1]`, and an IPv6 address is not a valid Content Security Policy source, so the browser blocked every dev asset and the site lost its styling while `npm run dev` ran.
- **Public demo on one free container**: the live demo runs from `Dockerfile` on Render's free plan with `DEMO_MODE=true`. Web server, PHP, queue worker, scheduler and Reverb share one container and SQLite, because the plan gives one service and a temporary disk. Each start builds a fresh database, so visitors' changes vanish and dates stay current. This is for showing the work: a real restaurant needs MySQL, durable storage and processes that never sleep (see `docs/handover.md`).
- **`DEMO_MODE`**: production hardening stays on, while the fake gateway is allowed, Stripe, email and text messages cannot be reached whatever keys are set, and shared accounts' credentials and staff records are read-only.
