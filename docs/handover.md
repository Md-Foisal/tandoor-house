# Handover

Tandoor House is a complete restaurant platform for a UK restaurant: guests browse, order, book and pay; staff take, cook, serve and deliver; the owner runs the menu, stock, people and money. Anything the team repeats every day runs on its own and can be switched off in Settings.

This document is for whoever runs, hosts or develops it next. The full requirement is `docs/brief.md`, the plan is `docs/plan.md`, every non-obvious choice is in `docs/decisions.md`, and each phase's report is in `docs/progress.md`.

## What was built

**For guests and customers**
- Home with live open or closed status, today's specials and quick reorder; a hero film with a pause control.
- Menu with sticky categories, search, filters (vegetarian, vegan, spice, allergens, price) and all 14 UK allergens visible on every dish; sold-out dishes shown but disabled. Dish pages with sizes, options, notes and allergens.
- Basket and checkout for delivery (postcode zones, fee and minimum), pickup (time slots) and dine-in; promo codes; loyalty rewards; optional tip passed in full to staff.
- Card payment (Stripe in test mode, with Apple Pay and Google Pay through Stripe's Payment Element) or pay at the restaurant.
- Live order tracking, cancelling before the restaurant accepts, and a signed link in every message so guests can open their order on any device.
- Table booking with deposits, a 15-minute hold, free cancellation up to 24 hours before, reminders and calendar files.
- QR table ordering: order, call the waiter, ask for the bill, split it and pay a share by card.
- Account: order history and reorder, saved addresses, reviews, loyalty points, marketing choice, "Download my data" and account deletion.
- Story, Find us, allergen guide, privacy notice, cookie policy and a cookie banner where Reject is as easy as Accept.

**For staff** (at `/staff`, installable on tablets as an app)
- Today screen per role, order board, kitchen display (dark by default, tickets by station, timers, bump and recall), floor plan with merge and transfer, bookings by day and week, rider runs, menu manager with photos and price history, inventory with recipes, waste, suppliers and purchase orders, team with rota, attendance and the weekly tip split, reports with CSV and PDF, review moderation, activity log, and settings.
- Screens update live over Laravel Reverb and fall back to polling when the socket is down.

**Automations** (each with a switch in Settings → Automations, each logged as "By the system")
- Auto-accept orders by hours, kitchen load, delivery zone, payment and age checks; prep-time estimates from real cooking times.
- Customer email and SMS at each important step; booking confirmations and two-hour reminders; no-show release.
- Stock deducted per recipe, dishes sold out and brought back automatically, low-stock alerts with draft purchase orders.
- Nightly close and Monday report emailed to the owner; review requests; loyalty points; abandoned-basket reminders for customers who opted in.
- Housekeeping that always runs: unpaid card orders closed after 30 minutes, lapsed deposit holds closed, old logs and baskets pruned.

## Running it locally

Requirements: PHP 8.3 or newer (8.4 in CI), Composer, Node 22, SQLite.

```bash
composer setup
php artisan migrate:fresh --seed
php artisan storage:link
composer dev
```

`composer dev` starts the web server, Vite, the queue worker, the log viewer and Reverb together; run `php artisan schedule:work` beside it for the automations. With Laravel Herd the site is http://tandoor-house.test (still run the queue, scheduler and Reverb for jobs, automations and live updates).

Checks: `composer ci:check` (lint and format, TypeScript, PHPStan, Pint and the Pest suite). Extra audits: `node scripts/a11y-audit.mjs` (axe, WCAG 2.2 AA) and `node scripts/perf-audit.mjs` (Lighthouse). Screenshots: `node scripts/screenshots.mjs <folder> name=/path@email`.

## Demo logins

Every demo account uses the password `password`.

| Role | Email | Try this |
|---|---|---|
| Owner | owner@tandoorhouse.test | Settings → Automations and money settings; Team → Tips (mark a week paid); Reports |
| Manager | manager@tandoorhouse.test | Menu, Inventory, Team (Nadia Hussain has a pending invite), Bookings |
| Cashier | cashier@tandoorhouse.test | Order board: accept, edit, refund, take payment, receipts |
| Waiter | waiter@tandoorhouse.test | Floor plan, table requests, bookings |
| Kitchen | kitchen@tandoorhouse.test | Kitchen display: start, bump, recall, sold out |
| Rider | rider@tandoorhouse.test | Assigned runs, picked up, delivered, cash collected |
| Customer | customer@tandoorhouse.test | 220+ loyalty points and a basket ready for checkout; order TH-90001 waiting at `/orders/TH-90001/pay`; booking RDEMO26 with a paid deposit |

Test cards on the fake payment form: Visa 4242 and Mastercard 4444 succeed; "Card that is declined", or any total ending in 51p, is declined. A table's QR link is on its card in the staff floor plan.

## Going live: what the server needs

- PHP 8.3+, MySQL 8, Node only for building assets (`npm ci && npm run build`).
- `php artisan migrate --force`, `php artisan storage:link` (needed on every server for menu photos), `php artisan optimize`.
- A queue worker (`php artisan queue:work`, supervised), the scheduler (`* * * * * php artisan schedule:run`) and Reverb (`php artisan reverb:start`, behind the proxy with TLS).
- `.env`: see the production block at the end of `.env.example`. In particular `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` (hosts and links are built from it), `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES` if behind a load balancer, a real mail service, `REVERB_*` keys, and `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` with the webhook pointed at `/webhooks/payments/stripe`.
- The restaurant's details (`RESTAURANT_*`), including `RESTAURANT_ICO_NUMBER` once registered.

## Decisions worth knowing

- **Money** is integer pence everywhere and always recomputed on the server; VAT is included in menu prices; the discretionary service charge sits outside VAT.
- **Payments** go through one gateway interface. The fake driver runs locally and in tests and refuses to run in production; Stripe is used only when keys are set and refuses live keys until switched deliberately. Starting a payment cancels earlier attempts, and money that lands on something already paid, cancelled or expired is refunded automatically.
- **Unpaid card orders** stay off the staff screens until paid.
- **Roles**: every staff action goes through a policy and is tested for every role; money settings are owner-only.
- **Real time**: events carry ids and statuses only; pages reload their own data, so permissions are always rechecked.
- **Privacy**: retention matches the privacy notice (activity log 12 months, bookings 12 months or six years with a deposit, orders and payments six years). Deleting an account removes the person but keeps anonymised financial records.
- **Security**: CSP with nonces, trusted hosts from `APP_URL`, throttles on every public write, private photo originals.
- **Accessibility**: WCAG 2.2 AA checked with axe on 26 pages in both themes and widths; 44px touch targets; reduced motion respected.

## What the client still has to supply or confirm

1. **Photos**: 20 dish photos are stand-ins of a similar dish from Pexels or Unsplash, including Chilli Cheese Toast. The shot list for the restaurant's photographer is `docs/photo-shotlist.md`; uploading a photo in the menu manager replaces the stand-in.
2. **Hero film**: a stock clip until the restaurant films its own (8–15 s, see the brief).
3. **Image credits**: some Unsplash credits link the image file instead of the photo page; check `docs/image-credits.md` before launch.
4. **Stripe keys**: test keys to try the real card flow, then live keys and the webhook secret for launch. Until then card payments run on the fake gateway locally.
5. **SMS and WhatsApp**: a provider (for example Twilio or Vonage) for real text messages; until then SMS is logged locally and sends nothing in production. WhatsApp has a ready interface but no driver.
6. **Mail**: a transactional mail service and the sending address.
7. **Accessibility details on the Visit page**: accessible toilet, high chairs and hearing loop show "ask us" until the restaurant confirms them.
8. **VAT on the service charge**: the build treats the discretionary service charge as outside VAT; an accountant should confirm this.
9. **ICO registration number** and the company details shown in the footer and privacy notice.
10. **Opening hours, delivery zones, prices, allergens and recipes**: the seeded values are realistic examples and must be replaced with the restaurant's own (all editable in the staff panel).
11. **Servers**: run `php artisan storage:link` on each server.
