# Progress

## Phase 0: setup

**Done**
- `CLAUDE.md`, `.claude/settings.json` (attribution off, checked with a test commit), agents `screen-builder`, `reviewer`, `media-curator`.
- `docs/plan.md`: screens, data model, state machines, automations, phases.
- `config/restaurant.php`: name, cuisine, contact and company details from one place. Timezone Europe/London, locale en_GB.
- Roles, the 14 FSA allergens, kitchen stations and service periods as enums.
- Menu schema (categories, items, variants, modifier groups, modifiers, price history) with a modern Indian seed menu: 8 categories, 40 dishes, kcal, spice, allergens, variants and modifiers.
- Demo login for every role (password `password`).
- Tests: seed data, demo logins, architecture rules. 47 passing. PHPStan clean, Vite+ lint and format clean, `npm run build` passes.
- Private GitHub repository with the starter kit's CI workflow (`composer ci:check`).

**Decided:** see `docs/decisions.md` → Phase 0 (Vite+ instead of ESLint/Prettier, enum roles, allergens as JSON, variant pricing, alcohol flag).

**Open:** no screens yet, so no screenshots this phase. Dish images are added by the media curator in Phase 1.

## Phase 1: brand and design system

**Done**
- Three brand directions scored; "Clay and Ember" chosen (`docs/brand-directions.md`).
- `docs/design-system.md`: palette for light and dark (all text pairs WCAG AA), Rozha One and Hanken Grotesk self-hosted, type scale, spacing, radius, warm shadows, motion tokens and the "served from the pass" patterns.
- Tokens in `resources/css/app.css`; shadcn variables mapped onto them; base components restyled; tabs, radio group, switch, textarea and popover added.
- Signature pieces: tandoor arch frame, ember line, logo mark, 14 custom allergen icons, empty-plate illustration.
- Shared pieces: dish row and dish card with skeletons, picture with blurred placeholder and "steam clears", hero film with poster-first loading and a pause control, open status, quantity stepper, kitchen-ticket progress, empty/error/offline states, order-slip toasts, theme toggle (no flash on load).
- Helpers: `useT()` translations, `formatMoney()`, motion tokens, media manifest.
- 47 free-licence photos (AVIF/WebP, 3 widths) and a 12 s hero film (720p and 1080p, each under 3 MB). Credits in `docs/image-credits.md`; shot list in `docs/photo-shotlist.md`.
- `/styleguide` and `/styleguide/motion`. Screenshots in `docs/screenshots/phase-1/`.
- Reviewer findings fixed: 44px targets, focus styles, screen-reader step state, reduced-motion count-up, motion limited to transform/opacity (two documented clip-path exceptions), contrast margin on ember.

**Decided:** see `docs/decisions.md` → Phase 1.

**Open**
- 19 dish photos are stand-ins of a similar dish and Chilli Cheese Toast has none; the client's photographer should shoot them (`docs/photo-shotlist.md`).
- Some Unsplash credits link the image file rather than the photo page; check them before launch.
- Laravel Herd's PHP-FPM socket was down on this machine, so pages were checked with `php artisan serve`.

## Phase 2: customer UI

**Done** (each screen built by its own screen-builder, at most four at a time, on shared pieces built first)
- Shared: public layout (top navigation from 768px, bottom tab bar below), account shell, server-side basket with fly-to-basket, VAT-inclusive pricing, opening hours, settings, guest order access, full schema and a busy demo restaurant (355 orders, 14 live, 130 bookings, recipes for all 40 dishes, stock, rota).
- Screens: home, menu with filters, dish detail, basket, checkout (delivery postcode zones, pickup slots, tips, age check), live order tracking, table booking with deposits, QR table ordering with waiter calls and bill splitting, account overview with loyalty and promo wallet, order history and reorder, saved addresses, reviews, story, find us, allergen guide and matrix, sign-in and settings restyled.
- Review fixes: rate limits on public writes, atomic promo limits, VAT outside the discretionary service charge, alcohol and opening-hours checks on table orders, guest basket kept at sign-in, private promo codes, allergens that depend on a chosen option, photos revealed when cached, late-order wording, two-decimal receipts.
- 232 tests, PHPStan level 7, Vite+ lint and format, TypeScript strict and the production build all pass. Screenshots of 20 screens at 375, 768 and 1280 in light and dark: `docs/screenshots/phase-2/` (no console errors).

**Decided:** see `docs/decisions.md` → Phase 2.

**Open**
- Card payments, booking deposits and paying at the table wait for the payment gateway (Phase 4); the buttons explain this.
- Customers cannot yet cancel bookings or orders themselves (Phase 4, with refund rules).
- Loyalty rewards are counted and shown, but redeeming one at checkout arrives with the loyalty automation (Phase 5).
- Booking still has its own form-field component; it should move to `components/form/field.tsx`.
- Visit page lists accessible toilet, high chairs and hearing loop as "ask us" until the restaurant confirms them.
- The service charge VAT treatment should be confirmed with the restaurant's accountant before launch.
