<h1 align="center">Tandoor House</h1>

<p align="center"><strong>One platform for a UK restaurant: guests order, book and pay; staff take, cook, serve and deliver; the owner runs the menu, stock, people and money.</strong></p>

<p align="center">
  <a href="https://github.com/Md-Foisal/tandoor-house/actions/workflows/tests.yml"><img alt="CI" src="https://github.com/Md-Foisal/tandoor-house/actions/workflows/tests.yml/badge.svg?branch=main"></a>
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="React 19" src="https://img.shields.io/badge/React-19-149ECA?logo=react&logoColor=white">
  <img alt="Inertia 3" src="https://img.shields.io/badge/Inertia-3-9553E9">
  <img alt="TypeScript strict" src="https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white">
  <img alt="Tailwind CSS 4" src="https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white">
  <img alt="Tested with Pest" src="https://img.shields.io/badge/tests-Pest-F28D1A">
  <img alt="All rights reserved" src="https://img.shields.io/badge/licence-all%20rights%20reserved-lightgrey">
</p>

<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/media/hero-dark.webp">
    <img alt="The Tandoor House customer site on a phone and desktop, beside the kitchen display" src="docs/media/hero-light.webp" width="900">
  </picture>
</p>

> **A sandbox, not a business.** Tandoor House is a fictional restaurant in East London built to show a complete, real-world product. Card payments run in Stripe test mode, or on a fake gateway by default, so no money moves. Text messages go to a log. The 40 dishes use free-licence stock photos, 20 of them stand-ins for similar dishes. There is no live demo; you run it locally in a few minutes (see [Run it](#run-it)).

## Watch the film

One evening of service in 2:22, from a guest's phone to the kitchen display, out with a rider and into the owner's reports. It is all the running app, in 4K with sound.

<p align="center">
  <a href="docs/media/film/tandoor-house-film.mp4"><img alt="A silent preview of the film: the Tandoor House title, a guest ordering on a phone, a ticket landing on the kitchen display, a rider delivering and the owner's stock screen" src="docs/media/film/tandoor-house-teaser.webp" width="800"></a>
</p>

## See it work

**A customer orders on a phone** (about 28 seconds): filter the menu, open a dish, fill the basket, check out for collection, pay with a test card.

<p align="center">
  <a href="docs/media/customer-order.mp4"><img alt="A customer filters the menu, adds two dishes, checks out and pays with a test card" src="docs/media/customer-order.gif" width="300"></a>
</p>

**The same order travels through the restaurant** (about 22 seconds): the cashier accepts it on the order board, the kitchen bumps the ticket on the kitchen display, and the customer's phone, on the left, follows each step as it happens over Reverb.

<p align="center">
  <a href="docs/media/order-through-the-kitchen.mp4"><img alt="Phone tracking page beside the order board and the kitchen display, updating as staff accept and bump the order" src="docs/media/order-through-the-kitchen.gif" width="900"></a>
</p>

**A table for six, with a deposit** (about 16 seconds): pick party, day and time, fill in details, pay the deposit, get the confirmation.

<p align="center">
  <a href="docs/media/book-a-table.mp4"><img alt="A guest books a table for six and pays a deposit with a test card" src="docs/media/book-a-table.gif" width="300"></a>
</p>

<sub>Each GIF links to a full-quality MP4. They were recorded with Playwright against the running app on fresh demo data; how is in [docs/media/README.md](docs/media/README.md).</sub>

## What it does

**Guests and customers**
- A home page with live open or closed status and today's specials; a menu with search, filters (vegetarian, vegan, spice, price and all 14 UK allergens) and sold-out dishes shown but disabled.
- Basket and checkout for delivery (postcode zones, fee, minimum), pickup (time slots) and dine-in, with promo codes, loyalty rewards and an optional tip that passes in full to staff.
- Card payment (Stripe's Payment Element in test mode) or pay at the restaurant. Live order tracking; cancelling before the restaurant accepts.
- Table booking with deposits, a 15-minute hold, free cancellation up to 24 hours before, reminders and calendar files.
- **Table QR ordering:** scan the code on the table, order from your seat, call the waiter, ask for the bill, split it and pay your share.
- An account with order history and reorder, saved addresses, reviews, loyalty points, "Download my data" and account deletion.

**Staff** (at `/staff`, installable on tablets as an app, one screen set per role)

| Role | What they get |
|---|---|
| Cashier | Order board: accept, edit, refund, take payment, print receipts |
| Kitchen | Kitchen display (dark by default), tickets by station, timers, bump and recall, mark dishes sold out |
| Waiter | Floor plan with merge and transfer, table requests, bookings by day and week |
| Rider | Assigned runs, navigation links, picked up, delivered, cash collected |
| Manager | Menu with photos, allergens and price history; stock, recipes, waste and purchase orders; team, rota, attendance and the weekly tip split; reviews |
| Owner | Everything above, plus reports (CSV and PDF), the activity log, and settings with the money switches |

**Automations**, each with its own switch in Settings and each logged as "By the system": auto-accept orders (by opening hours, kitchen load, delivery zone and payment), prep-time estimates from real cooking times, customer email and SMS at every step, booking reminders and no-show release, stock deducted per recipe with automatic sold-out and low-stock alerts, a nightly close and a Monday report emailed to the owner, review requests, loyalty points and abandoned-basket reminders for customers who opted in.

**Compliance built in:** UK GDPR privacy notice, a PECR cookie banner where Reject is as easy as Accept, retention that matches the notice, data export and erasure, security headers, and WCAG 2.2 AA checked with axe.

## Screenshots

Images follow your GitHub theme. Each is a real screen from the seeded demo, not a mock-up.

### Customer

<table>
<tr>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/home-phone-dark.webp"><img alt="Home on a phone" src="docs/media/screens/home-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/menu-phone-dark.webp"><img alt="Menu on a phone" src="docs/media/screens/menu-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/dish-phone-dark.webp"><img alt="A dish page on a phone, with allergens" src="docs/media/screens/dish-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/book-phone-dark.webp"><img alt="Booking a table on a phone" src="docs/media/screens/book-phone-light.webp"></picture></td>
</tr>
</table>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/account-desktop-dark.webp"><img alt="The customer account page with loyalty points on desktop" src="docs/media/screens/account-desktop-light.webp" width="100%"></picture>

### Table QR

<table>
<tr>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/table-qr-phone-dark.webp"><img alt="Ordering from a table: menu with call a waiter and ask for the bill" src="docs/media/screens/table-qr-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/visit-phone-dark.webp"><img alt="The find-us page on a phone" src="docs/media/screens/visit-phone-light.webp"></picture></td>
</tr>
</table>

### Cashier

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/cashier-orders-desktop-dark.webp"><img alt="The order board on desktop" src="docs/media/screens/cashier-orders-desktop-light.webp" width="100%"></picture>

<table>
<tr>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/cashier-orders-phone-dark.webp"><img alt="The order board on a phone" src="docs/media/screens/cashier-orders-phone-light.webp"></picture></td>
</tr>
</table>

### Kitchen

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/kitchen-display-desktop-dark.webp"><img alt="The kitchen display with tickets by station" src="docs/media/screens/kitchen-display-desktop-light.webp" width="100%"></picture>

### Waiter

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/waiter-floor-desktop-dark.webp"><img alt="The floor plan with table status" src="docs/media/screens/waiter-floor-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/waiter-bookings-desktop-dark.webp"><img alt="Bookings timeline by table" src="docs/media/screens/waiter-bookings-desktop-light.webp" width="100%"></picture>

### Rider

<table>
<tr>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/rider-deliveries-phone-dark.webp"><img alt="A rider's delivery run on a phone" src="docs/media/screens/rider-deliveries-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/waiter-floor-phone-dark.webp"><img alt="The floor plan on a phone" src="docs/media/screens/waiter-floor-phone-light.webp"></picture></td>
<td width="25%"><picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/owner-today-phone-dark.webp"><img alt="The owner's Today screen on a phone" src="docs/media/screens/owner-today-phone-light.webp"></picture></td>
</tr>
</table>

<sub>Left to right: rider deliveries, the waiter's floor plan, the owner's Today screen.</sub>

### Manager and owner

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/manager-menu-desktop-dark.webp"><img alt="The menu manager" src="docs/media/screens/manager-menu-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/manager-stock-desktop-dark.webp"><img alt="Stock levels against par" src="docs/media/screens/manager-stock-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/owner-today-desktop-dark.webp"><img alt="The owner's Today screen" src="docs/media/screens/owner-today-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/owner-reports-desktop-dark.webp"><img alt="Sales and VAT reports" src="docs/media/screens/owner-reports-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/manager-team-desktop-dark.webp"><img alt="Team, rota and tips" src="docs/media/screens/manager-team-desktop-light.webp" width="100%"></picture>

<picture><source media="(prefers-color-scheme: dark)" srcset="docs/media/screens/owner-settings-desktop-dark.webp"><img alt="Settings: opening hours, zones, money and automations" src="docs/media/screens/owner-settings-desktop-light.webp" width="100%"></picture>

## Design: Clay and Ember

The identity is written down in [docs/design-system.md](docs/design-system.md) and chosen from three directions in [docs/brand-directions.md](docs/brand-directions.md). The palette is warm clay and paper with an ember accent, in light and dark themes designed on purpose; type is Rozha One for display and Hanken Grotesk for text. Interface copy sounds like the person at the pass: short, specific, British.

- **Tandoor arch:** dish photos are framed with a round top, like the mouth of the oven.
- **Ember line:** a hand-drawn underline, drawn in with an SVG stroke, under active tabs, filters and headings.
- **Allergen icons:** 14 custom line icons, always shown with their name.
- **Signature motion, "served from the pass":** things move the way they do in a kitchen. A dish card sets down like a plate (a spring with one barely visible settle), groups arrive course by course, a finished kitchen step gets a stamp, the receipt total counts up. Every motion is a named token in `resources/js/lib/motion.ts`, and `prefers-reduced-motion` turns the choreography into a short fade.

## Architecture

```mermaid
flowchart TB
    Guest["Guest and customer site (phone first)"]
    Staff["Staff panel (tablet app, kitchen display)"]
    Inertia["Controllers, policies, form requests<br/>Inertia 3 with typed props"]
    Domain["Actions and services<br/>Pricing, OrderStateMachine, Payments, Automation, ActivityRecorder"]
    Queue["Queued jobs and notifications<br/>mail, SMS, reminders, reports"]
    Sched["Scheduler: automations and pruning"]
    DB[("SQLite locally, MySQL in production")]
    Gateway["Payment gateway: fake, venue, Stripe test mode"]
    Reverb["Reverb WebSockets"]

    Guest --> Inertia
    Staff --> Inertia
    Inertia --> Domain
    Sched --> Domain
    Domain --> DB
    Domain --> Gateway
    Gateway -.->|webhooks| Inertia
    Domain --> Queue
    Domain -->|"events: ids and statuses only"| Reverb
    Reverb -.->|live updates| Guest
    Reverb -.->|live updates| Staff
```

The server is the source of truth. There is no REST API for the web app: routes, controllers, policies and validation live in Laravel, and pages receive typed props through Inertia. Broadcast events carry only ids and statuses, so a page reloads its own data and permissions are checked again on every update. When the socket is down (and for guests, who have no private channel) pages poll every 15 seconds instead.

### An order's life

```mermaid
flowchart TB
    Placed(["Placed"]) -->|"staff or auto-accept"| Accepted(["Accepted"])
    Accepted -->|"kitchen starts"| Cooking(["Cooking"])
    Cooking -->|"kitchen bumps the ticket"| Ready(["Ready"])
    Ready -->|delivery| Out(["Out for delivery"])
    Ready -->|pickup| Collected(["Collected"])
    Ready -->|dine-in| Served(["Served"])
    Out --> Delivered(["Delivered"])
    Delivered --> Completed(["Completed"])
    Collected --> Completed
    Served --> Completed
    Placed -.-> Rejected(["Rejected"])
    Placed -.-> Cancelled(["Cancelled"])
    Accepted -.-> Cancelled
```

The transitions live in one place, `app/Services/OrderStateMachine.php`. Every transition runs inside a transaction that locks the order row and re-reads its status first, so two staff pressing Accept on the same order cannot both win.

## Engineering highlights

- **Money is integer pence.** No floats anywhere near a price. Totals are always recomputed on the server (`app/Services/Pricing.php`); menu prices include VAT as UK consumer prices must, VAT is the inclusive portion of each line, and the discretionary service charge sits outside it. Reports and receipts use the same numbers.
- **Concurrency-safe writes.** Anything that changes stock, payments, refunds, seating or order status takes a row lock (`lockForUpdate`, 76 call sites) and re-checks its precondition inside the transaction. Eloquent runs in strict mode outside production, so lazy loading and silent attribute errors fail loudly in development.
- **A policy for every action, a test for every role.** 22 policy classes guard the staff and customer actions across 7 roles, and a test walks every page and throttle as every role.
- **Real time without leaking data.** Laravel Reverb events carry ids and statuses only; the `useLive` hook reloads just the named Inertia props, falls back to polling, and catches up after a reconnect.
- **One payment interface, three drivers.** `PaymentGateway` has a fake driver (local and tests, refuses to run in production), a venue driver (pay at the restaurant) and a Stripe driver in test mode. Starting a payment cancels earlier attempts, money that lands on something already paid or expired is refunded automatically, and live Stripe keys are refused unless `STRIPE_ALLOW_LIVE` is set.
- **Automations that explain themselves.** Every automated action goes through `App\Support\Automation`, which checks the owner's switch and logs it as "By the system" through the same `ActivityRecorder` humans use.
- **Security and privacy.** A nonce-based Content Security Policy, trusted hosts, per-route throttles keyed on session and IP, private photo originals, single-use hashed invite tokens, signed guest links, retention jobs that match the privacy notice, and data export and erasure for customers.
- **Server-rendered metadata.** Titles, Open Graph and schema.org `Restaurant`, `Menu` and `MenuItem` data are printed by the Blade root view, so crawlers and link previews do not need JavaScript.
- **Quality gates.** A Pest suite of **2,508 tests (13,846 assertions)**, PHPStan at level 7, TypeScript in strict mode, Vite+ lint and format, Pint, and an automated axe audit (WCAG 2.2 AA, 26 pages, both themes). Lighthouse is run with `scripts/perf-audit.mjs`; the menu page went from a layout shift of 0.209 to 0.000 by preloading its two fonts.

## Tech stack

| Layer | Choice |
|---|---|
| Backend | PHP 8.3+ (8.4 in CI), Laravel 13, Fortify auth, queued jobs, scheduler |
| Frontend | React 19, Inertia 3, TypeScript (strict), Tailwind CSS 4, shadcn/ui restyled to the brand, `motion` for animation |
| Real time | Laravel Reverb (WebSockets) with polling fallback |
| Payments | Stripe in test mode (Payment Element), or the built-in fake driver |
| Data | SQLite locally, MySQL in production; money in integer pence, timezone Europe/London, locale en-GB |
| Tests and checks | Pest 5, PHPStan, Pint, Vite+ (lint and format), axe, Lighthouse |
| Documents | dompdf for receipts and report PDFs |

## Run it

You need PHP 8.3 or newer with the SQLite extension, Composer, and Node 22. These steps were run in a fresh clone before they were written down.

```bash
git clone https://github.com/Md-Foisal/tandoor-house.git
cd tandoor-house
composer setup                 # installs PHP and JS dependencies, creates .env and the key, migrates, builds assets
php artisan migrate:fresh --seed   # demo restaurant, menu, people, orders and bookings
php artisan storage:link       # public disk, needed for uploaded menu photos
composer dev                   # web server, Vite, queue worker, log viewer and Reverb together
```

Open the address `composer dev` prints (usually <http://localhost:8000>). With [Laravel Herd](https://herd.laravel.com) the site is served at `http://<folder-name>.test` instead; still run `composer dev` for the queue, Reverb and Vite.

Automations run from the scheduler, so keep `php artisan schedule:work` running beside it. Without a queue worker, emails and live updates do not go out; without Reverb, pages fall back to polling.

### Demo logins

Every demo account uses the password `password`.

| Role | Email | Try this |
|---|---|---|
| Owner | `owner@tandoorhouse.test` | Settings → Automations and money; Team → Tips; Reports |
| Manager | `manager@tandoorhouse.test` | Menu, Stock, Team, Bookings |
| Cashier | `cashier@tandoorhouse.test` | Order board: accept, edit, refund, take payment |
| Waiter | `waiter@tandoorhouse.test` | Floor plan, table requests, bookings |
| Kitchen | `kitchen@tandoorhouse.test` | Kitchen display: start, bump, recall, sold out |
| Rider | `rider@tandoorhouse.test` | Assigned runs, picked up, delivered, cash collected |
| Customer | `customer@tandoorhouse.test` | Loyalty points, a basket, an order waiting to be paid |

Test cards on the payment page: "Visa ending 4242" and "Mastercard ending 4444" succeed; "Card that is declined", or any total ending in 51p, is declined. A table's QR link is on its card in the staff floor plan.

### Use real Stripe test keys (optional)

Set `STRIPE_KEY`, `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET` from a Stripe **test-mode** account in `.env` and point a webhook at `/webhooks/payments/stripe`. Without a secret key the fake gateway is used. Mail goes to the log driver by default, and SMS is logged locally (and sends nothing in production) until a provider is configured.

### Run the checks

```bash
composer ci:check              # Vite+ lint and format, TypeScript, then the Pest suite (what CI runs)
php artisan test --compact     # tests only (needs `npm run build` once for the Vite manifest)
vendor/bin/phpstan analyse --memory-limit=1G
composer lint                  # Pint
node scripts/a11y-audit.mjs    # axe, WCAG 2.2 AA
node scripts/perf-audit.mjs    # Lighthouse
```

## Documentation

| Read | For |
|---|---|
| [docs/brief.md](docs/brief.md) | The full requirement the build followed |
| [docs/plan.md](docs/plan.md) | Screens, data model, state machines, automations, phases |
| [docs/decisions.md](docs/decisions.md) | Why things are the way they are, by phase |
| [docs/design-system.md](docs/design-system.md) | Tokens, type, motion and the signature elements |
| [docs/progress.md](docs/progress.md) | A report for each phase |
| [docs/handover.md](docs/handover.md) | Running it, going live, and what a client would still need to supply |
| [docs/image-credits.md](docs/image-credits.md) | Credits for every photo and the hero film |

## Licence

Copyright © 2026 Md. Foisal. **All rights reserved.** The source is public so it can be read; no permission is given to use, copy, modify or distribute it without written permission. Third-party material keeps its own terms: the Laravel + React starter kit and shadcn/ui parts are MIT, dependencies keep their own licences, and photographs and video follow [docs/image-credits.md](docs/image-credits.md). See [LICENSE](LICENSE).
