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
