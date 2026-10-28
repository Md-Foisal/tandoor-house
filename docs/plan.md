# Plan

The whole build, phase by phase. `docs/brief.md` is the requirement; this file is how it is met. Choices that are not obvious are explained in `docs/decisions.md`.

## 1. Shape of the product

One Laravel app, three surfaces, one design system.

| Surface | Who | Layout | Entry |
|---|---|---|---|
| Public and customer | Guest, Customer | bottom tab bar under 768px, top navigation above | `/` |
| Table ordering | Guest at a table | same as customer, scoped to one table | `/t/{qr_token}` |
| Staff panel | Waiter, Cashier, Kitchen, Rider, Manager, Owner | collapsible sidebar (tablet and desktop), bottom bar on phone | `/staff` |
| Kitchen display | Kitchen | full-screen, tablet landscape, dark by default | `/staff/kitchen` |

The restaurant's name, cuisine, address and contact details come from `config/restaurant.php` (backed by `.env`). Every string goes through `lang/en_GB/*.php` (served to React through a shared `t()` helper).

## 2. Roles and what each may do

`users.role` holds one of the enum values below. Guest means not signed in. Every controller action calls a policy; tests cover each role.

| Role | Can |
|---|---|
| Guest | browse, build a cart, check out as guest, order at a table, book a table |
| Customer | all of Guest, plus account, addresses, history, reorder, loyalty, reviews |
| Waiter | table map, take dine-in orders, serve, transfer/merge tables, handle calls and bill requests, reservations (seat, mark no-show) |
| Cashier | order board for all channels, accept/reject, edit before cooking, take payment, refund up to a limit, receipts |
| Kitchen | KDS: start, bump, recall; mark a dish sold out |
| Rider | own assigned deliveries, mark picked up/delivered, record cash collected |
| Manager | everything operational: menu, stock, staff and shifts, reservations, refunds without limit, reports, settings except money settings |
| Owner | everything, including VAT, service charge, payments settings, deleting staff |

## 3. Screens

### Customer (Phase 2)
1. Home: hero video with one line, live open/closed status, today's specials, quick reorder (signed in), story teaser, reviews, location and hours.
2. Menu: sticky horizontally scrolling categories, search, collapsible filters (vegetarian, vegan, spice level, exclude allergens, price), compact rows with kcal and allergen badges always visible, sold-out shown disabled.
3. Dish detail: hero photo (shared-element from the card), variants, modifier groups with min/max and price, notes, full allergen list, kcal.
4. Cart: lines, edit, promo code, totals; sticky checkout bar.
5. Checkout: channel (delivery / pickup / dine-in), address and postcode check against zones, pickup slot, contact, tip, payment method; ticket-stub steps.
6. Order tracking: kitchen-ticket progress, estimated time, live updates.
7. Reservations: date, party size, place-card time slots, details, deposit, confirmation.
8. Table ordering (QR): table banner, menu, cart, call waiter, request bill, split bill.
9. Account: overview with loyalty points, order history and reorder, addresses, reviews, promo wallet, settings (starter kit profile, password, 2FA, passkeys, appearance).
10. Story / about, gallery, contact and location.
11. Auth: sign in, register, forgot/reset password, verify email (restyled starter kit).
12. Legal: privacy notice, cookie policy, allergen statement; cookie banner.
13. Styleguide: `/styleguide` (components in every state) and `/styleguide/motion`.

### Staff (Phase 3)
1. Today (dashboard per role).
2. Order board: live queue across channels, filters, accept/reject, edit, refund, receipt.
3. KDS: tickets by station, timers, colour by wait, bump, recall.
4. Table map: floor plan, status, merge/transfer, calls and bill requests.
5. Reservations calendar: day and week views, seat, no-show.
6. Rider: assigned deliveries, map link, picked up, delivered, cash collected.
7. Menu management: categories, items, variants, modifiers, photos, availability schedule, price history.
8. Inventory: ingredients, recipes, stock in/out, waste log, suppliers, purchase orders.
9. Staff: users and roles, shifts rota, attendance (clock in/out).
10. Reports: sales by day/hour/item/channel, top and slow items, prep time, payment mix, VAT, service charge, tips; CSV and PDF export.
11. Settings: hours, bank holidays, delivery zones, VAT, service charge, receipt template, notification channels, hygiene rating, automation switches.
12. Activity log.

## 4. Data model

Money is `unsigned int` pence. Quantities of stock are integers in the ingredient's base unit (g, ml or piece). Times are stored in UTC and shown in Europe/London.

**Menu:** `categories` (name, slug, description, position, service_periods json) · `menu_items` (category_id, name, slug, description, price, kcal, spice_level 0–3, is_vegetarian, is_vegan, allergens json of the 14 FSA codes, vat_rate basis points, station, prep_minutes, image, is_available, is_special, service_periods json nullable, position) · `item_variants` (menu_item_id, name, price, kcal, position) · `modifier_groups` (menu_item_id, name, min, max, position) · `modifiers` (group_id, name, price, allergens json, position) · `price_changes` (menu_item_id, variant_id nullable, old_price, new_price, user_id).

**Venue:** `dining_tables` (label, seats, area, x, y, width, height, shape, status, qr_token, merged_into_id) · `delivery_zones` (name, postcode_prefixes json, fee, minimum_order, eta_minutes, is_active) · `settings` (key, value json) · `opening_hours` and `bank_holidays` live inside settings.

**Customers:** `users` (+ role, phone, loyalty_points, marketing_opt_in) · `addresses` · `carts` (user_id nullable, session id, lines json, reminded_at) · `promo_codes` · `loyalty_transactions` · `reviews`.

**Orders:** `orders` (number, channel, status, user_id, table_id, rider_id, contact json, address json, postcode, delivery_zone_id, pickup_at, subtotal, discount, delivery_fee, service_charge, tip, vat_total, total, promo_code_id, payment_status, payment_method, estimated_ready_at, accepted_at, ready_at, completed_at, notes, cash_collected) · `order_lines` (order_id, menu_item_id, name, variant_name, unit_price, quantity, modifiers json, notes, station, vat_rate, status, started_at, bumped_at) · `order_status_events` · `payments` (order_id, gateway, reference, method, amount, status, refunded) · `table_requests` (table_id, type call_waiter/bill, status) · `bill_splits`.

**Bookings:** `reservations` (reference, user_id, name, email, phone, party_size, starts_at, ends_at, table_id, status, deposit, deposit_status, notes, reminded_at, seated_at).

**Stock:** `suppliers` · `ingredients` (name, unit, stock, low_threshold, par_level, cost per unit, supplier_id) · `recipe_lines` (menu_item_id, ingredient_id, quantity) · `stock_movements` (ingredient_id, type in/out/sale/waste/adjust, quantity, reason, order_id, user_id) · `purchase_orders` and `purchase_order_lines`.

**People:** `shifts` (user_id, starts_at, ends_at, role) · `attendances` (user_id, clocked_in_at, clocked_out_at) · `tip_allocations` (period, user_id, amount).

**System:** `activity_logs` (event, subject, causer, properties) · `jobs`, `notifications`.

## 5. State machines

**Order:** `placed → accepted → cooking → ready → (out_for_delivery → delivered | served | collected) → completed`. Side exits: `placed → rejected`; `placed|accepted → cancelled`. Each transition is one method on `OrderStateMachine`, checks the allowed source states, writes an `order_status_events` row, fires an event (notifications, broadcasting, stock).

**Order line (kitchen):** `queued → cooking → ready`, `ready → cooking` on recall.

**Payment:** `pending → authorised → paid`, `pending → failed`, `paid → partially_refunded → refunded`. Pay at venue starts `pending` and becomes `paid` when the cashier takes it.

**Reservation:** `pending_deposit → confirmed → seated → completed`; `confirmed → no_show` (auto after the window) ; any open state `→ cancelled`. No deposit means it starts `confirmed`.

**Table:** `free → reserved → seated → bill_requested → needs_cleaning → free`; merge links tables to a lead table.

## 6. Automations (Phase 5)

| Automation | Trigger | Job |
|---|---|---|
| Auto-accept | order placed | `AutoAcceptOrder` checks open hours, kitchen load, zone, then accepts or leaves for a person |
| Station routing | order accepted | lines get the item's station; KDS channels per station |
| Prep estimate | order accepted, line bumped | `PrepTimeEstimator`: median of the last 30 days per item, plus queue load |
| Customer notifications | every order status change | queued notification, mail + SMS channel (log driver), WhatsApp-ready interface |
| Stock deduction | order accepted | recipe quantities out; sold out at zero; low stock alert; draft purchase order |
| Reservation confirm / reminder / release | created, scheduler every 5 min | reminder 2 h before; no-show after 20 min releases table |
| Daily close | 23:30 daily | summary email to owner |
| Weekly report | Monday 07:00 | report email |
| Review request | order completed + 1 h | email |
| Loyalty credit | order completed | 1 point per £1 |
| Abandoned cart | cart idle 2 h, signed in | email once |

Each is a queued job or scheduled command, writes to the activity log and checks its switch in settings.

## 7. Phases

| # | Branch | Outcome |
|---|---|---|
| 0 | `feat/phase-0-setup` | project rules, agents, config, menu seed data, tooling |
| 1 | `feat/phase-1-brand-system` | brand directions, design system, tokens, fonts, base components, `/styleguide`, `/styleguide/motion` |
| 2 | `feat/phase-2-customer-ui` | every customer screen on seeded data |
| 3 | `feat/phase-3-staff-ui` | every staff screen on seeded data |
| 4 | `feat/phase-4-backend` | full models, state machines, policies, payments, Reverb |
| 5 | `feat/phase-5-automations` | all automations with tests |
| 6 | `feat/phase-6-hardening` | role tests, rate limits, security, a11y, performance, SEO, GDPR/PECR, PWA, handover |

Each phase ends with: reviewer subagent, fixes, `php artisan test`, `npm run build`, screenshots in `docs/screenshots/phase-N/`, report in `docs/progress.md`, pull request merged into `main`.
