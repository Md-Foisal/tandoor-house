This file is the standing brief. First write a short `CLAUDE.md` (at most 60 lines): stack, working rules, git rules, agent rules, and pointers to `docs/brief.md`, `docs/plan.md`, `docs/design-system.md` and `docs/decisions.md`. Read the long docs only when a task needs them; this keeps every session cheap.

# Brief: a fully automated restaurant platform

## Goal
Build a production-grade platform for a restaurant in the United Kingdom that runs it end to end: customers order, book and pay; staff take, cook, serve and deliver; the owner manages menu, stock, people and money. Anything a person repeats every day, the system does on its own. If no restaurant name is given, use the working name "Tandoor House" from a single config value so it can be changed in one place.

## Stack (do not swap)
- Laravel, latest stable, created with the official React starter kit: Inertia + React + TypeScript (strict) + Tailwind + shadcn/ui. Use the versions the starter kit installs; do not downgrade.
- Pest for tests. Laravel Reverb for real-time. Queues for every notification and long task. MySQL in production.
- Money stored as integers in pence; currency GBP. Timezone Europe/London. Locale en-GB (dates, phone numbers, postcodes).
- Server is the source of truth: routes, controllers, policies and validation live in Laravel; pages receive typed props through Inertia. No separate REST API for the web app.

## Working rules
1. Autonomous run. You decide everything yourself, as a senior product engineer would, and keep going without waiting for approval. Start with a written plan for all phases (screens, data model, state machines, automations) in `docs/plan.md`, then execute it. Record every non-obvious choice and its reason in `docs/decisions.md`. Stop and ask only for: anything that spends real money, real API keys or credentials, deleting anything outside this project, or a blocker you could not solve after three different attempts.
2. Mobile first and frontend first. Every screen is designed at 375px first, then 768px, then 1280px+. Phases 1–3 build the full UI on realistic seeded data from factories before real business logic is wired.
3. One phase at a time, each on its own branch. At the end of each phase: run the reviewer subagent and fix what it finds, run the tests and `npm run build`, save screenshots of every new screen at 375, 768 and 1280 widths in light and dark (Playwright) to `docs/screenshots/phase-N/`, write the phase report in `docs/progress.md` (done, decided, open), commit, merge the branch into `main`, push if a remote exists, then continue with the next phase.
4. Small, readable commits with clear English messages. Code comments in English, timeless, no process notes.
5. Never invent a requirement silently. If something is unclear, choose the industry-standard behaviour, write the choice in `docs/decisions.md`, and continue.

## Design standard: enterprise quality, must not look AI-made
Build a real brand, not a template.
- Original identity first: before any UI, write `docs/brand-directions.md` with three clearly different brand directions for this restaurant. Each has a one-line idea, mood, palette (light and dark), type pairing, photo treatment, and one signature element that belongs to this brand only (for example a custom illustrated motif, its own icon set, a distinctive menu interaction, a pattern drawn from the cuisine's craft). Score each against this brief (originality, appetite appeal, legibility, fit for a premium UK restaurant, ease of building well) and choose the strongest yourself; write why. The chosen direction becomes the design system.
- Originality check on every screen: it must not be mistaken for any existing restaurant site, template or UI kit. Reference sites teach principles only.
- Light and dark themes, both first-class: follow the device setting by default, with a toggle that remembers the choice and no flash of the wrong theme on load. Every token has a light and a dark value designed on purpose (not inverted colours); both pass WCAG AA; photos and video sit well on both backgrounds. The kitchen display defaults to dark.
- Write `docs/design-system.md` first: brand personality, palette, type scale, spacing, radius, shadows, motion. Implement it as Tailwind theme tokens; components use tokens only, never raw hex values.
- Typography: one characterful display face for headings and one highly legible sans for UI, self-hosted, with a defined scale. Not a single default font everywhere.
- Colour: a warm, food-led palette drawn from the cuisine, one strong accent, neutrals with a slight warm tint. Check every text/background pair for WCAG AA contrast.
- Avoid the generic AI look: no purple/blue gradients, no glassmorphism, no emoji as icons, no hero-plus-three-cards layouts, no stock "Welcome to our restaurant" copy, no identical card grids on every page. shadcn/ui is the base, restyled to the brand, so it does not look like default shadcn.
- Food photography leads: fixed aspect ratios, `srcset`/lazy loading, blurred placeholders, no layout shift.
- Photography plan: the client's own photos are final. Until then use free-licence stock (Unsplash or Pexels, both allow commercial use), downloaded into the repo, never hotlinked, converted to AVIF/WebP with `srcset`. No AI-generated food images; they read as fake. Mix documentary shots (people, hands, steam, the room) with plated food, not only dishes on white. Keep `docs/photo-shotlist.md` (every shot the client's photographer must take) and `docs/image-credits.md`.
- Hero: a short ambient video (8–15 s, muted, looping, `autoplay muted playsinline`) behind one strong line of copy. The poster image paints first and is the LCP element; WebM + MP4 under about 3 MB, 720p on mobile and 1080p on desktop; paused when off-screen; a visible pause control (WCAG 2.2.2); poster only for `prefers-reduced-motion` and Save-Data. Source from Pexels/Unsplash video until the client supplies footage.
- Copy sounds like a real restaurant: short, warm, specific, British English. Brand voice reaches the small places too (empty states, errors, the cookie banner). All strings go through Laravel's translation files.
- Every screen has all its states: loading (skeletons, not spinners), empty, error, offline, success, and disabled.
- Motion: interface feedback (buttons, toggles, menus) 150–250 ms, ease-out. Scroll storytelling follows the signature motion below. Always respect `prefers-reduced-motion`.
- Signature motion, "served from the pass" (the pass is the counter where chefs set finished plates for service): as the guest scrolls, food content should feel served to the table, not slid in from a template. Build it with Motion for React (`motion`), triggered once when about 20% of an element is in view:
  - Plate set-down: a dish card rises 24–40 px while scaling 0.96 → 1, then its shadow grows last, as if the plate has just touched the table; a soft spring with a barely visible settle, never a cartoon bounce.
  - Course by course: cards in a group arrive one after another, 80–120 ms apart, in reading order; a whole group finishes within about 600 ms so nobody waits for content.
  - Steam clears: dish photos start slightly soft and warm-dimmed and clear to sharp as they land; hot dishes may show one faint steam wisp rising once.
  - Sections open like a new course: heading first, then its dishes.
  - Order tracking moves like a kitchen ticket: the active step lifts, the finished step is stamped.
  - The same idea runs through everything, not only food: each element moves the way its real counterpart behaves in a restaurant, never a generic fade-up. For example:
    - Story/about: photos develop like prints, captions write in after them; chapters turn like pages of a menu book.
    - Reservations: time slots appear like place cards set on a table; the confirmation is "laid" like a place setting (lines draw in, then the card settles).
    - Reviews: they land like notes in a guest book, a little askew, then straighten.
    - Gallery/the room: frames uncover from a soft crop, like a door opening onto the dining room.
    - Numbers (years open, rating, dishes served): count up like a receipt total.
    - Location: the pin drops like a table marker; opening hours show today's line lit.
    - Menu filters: dishes that do not match are cleared like plates from a table; the rest close up smoothly (layout animation).
    - Add to cart: a small plate travels to the cart and the badge gives one nod; checkout steps advance like ticket stubs.
    - Dish card → dish page: the photo expands into the detail page's hero (shared-element transition with Motion's `layoutId` or the View Transitions API, across Inertia visits).
    - Toasts slide in like an order slip; skeletons take the shape of the card that is coming.
  - Motion hierarchy: public pages are the most expressive; ordering and checkout stay quick and functional (under 250 ms, never in the way); staff screens use motion only to signal state (a new order highlights once, a KDS ticket shifts colour as it waits), nothing decorative.
  - Durations, easings and springs are design tokens in `docs/design-system.md`, used everywhere, so the whole product moves with one character.
  - Rules: animate transform and opacity (filter on images only, once); nothing animates before the hero's first paint (LCP); content is fully visible and usable without JavaScript and for screen readers; with reduced motion everything becomes a plain 150 ms fade; 60 fps on a mid-range phone. Add a `/motion` page in the styleguide showing each pattern.
- Touch targets at least 44×44 px; thumb-reachable primary actions on mobile (bottom bar / sticky CTA); keyboard and screen-reader usable everywhere (WCAG 2.2 AA).
- Layout per device: customer side uses a bottom tab bar on mobile and a top navigation on desktop; staff panel uses a collapsible sidebar on desktop and tablet; the kitchen display is tablet-landscape first.
- Reference study (patterns only; never copy a brand, its copy or a layout one to one):
  - Dishoom: storytelling and editorial layout, warm cream background, documentary photos with small captions, asymmetric image/text rhythm, a quote over ambient video as the hero, brand voice even in the cookie banner.
  - Hawksmoor: full-bleed close-up food photo hero on mobile, large letter-spaced serif headline, two thumb-sized CTAs (Book a table filled, View menus outlined), a restrained three-colour palette, a human touch (the founders' handwritten signature).
  - wagamama menu: sticky, horizontally scrollable category tabs, collapsible dietary filters, compact rows (name, short description, kcal, diet badges, small image on the right) that scan fast on a phone.
- Performance budget on a mid-range phone over 4G: LCP under 2.5 s, INP under 200 ms, CLS under 0.1. Code-split per page, prefetch likely next pages with Inertia, cache menu data.

## Git rules
- GitHub Flow: `main` is always working; every phase or feature gets a short-lived branch (`feat/phase-1-brand-system`, `feat/customer-menu`, `fix/cart-total-rounding`), merged by pull request.
- Conventional Commits, small and focused: `feat:`, `fix:`, `refactor:`, `style:`, `perf:`, `test:`, `docs:`, `chore:`, `build:`, `ci:`; imperative, lower case, no trailing full stop, no emoji (e.g. `feat: add allergen filter to menu`).
- No AI attribution anywhere: no Co-Authored-By trailer, no "Generated with" line, no mention of AI in commits, PRs, code comments or docs. In Phase 0 create `.claude/settings.json` with `{"attribution": {"commit": "", "pr": ""}}` and check it with a test commit.
- Code reads as written by a careful senior: comments only where the why is not obvious, no narration comments, no leftover TODOs or commented-out code.
- Never stage with `git add .`; add explicit paths. Never commit `.env` or keys.

## Agent rules (speed without losing quality)
- The main session plans, owns the design system and architecture, integrates and reviews.
- Once the design system is approved, build independent screens in parallel with subagents. In Phase 0 create `.claude/agents/screen-builder.md` (model: sonnet; builds one screen or feature from the design system and the plan, owns only its own files) and `.claude/agents/reviewer.md` (model: sonnet, read-only tools; checks a finished screen against the brief, design system, accessibility and the originality check).
- Never let two subagents edit the same file; shared components are built first by the main session.
- Mechanical work (seed data, renames, fixtures) may go to a subagent on haiku.
- The main session is the orchestrator: it keeps its own context small and hands each phase's building to subagents, then reviews and integrates. Long logs stay inside subagents.
- In Phase 0 also create `.claude/agents/media-curator.md` (model: sonnet). It sources every photo and the hero video: free-licence material from Pexels or Unsplash, downloaded into the repo (never hotlinked, never AI-generated), cropped to the needed aspect ratios, converted to AVIF/WebP (and WebM/MP4 for video) with whatever tools are available (sharp via npm, ffmpeg), each credited in `docs/image-credits.md`. If downloading is blocked, it makes tasteful branded placeholders, lists the missing shots in `docs/photo-shotlist.md`, and the build continues.
- Keep the main context small: subagents return short summaries, not full logs.
- Token budget: screenshots are saved to disk for the owner; agents look at them only at reduced size, every screen at 375 px light, and spot-check the other sizes and dark. Use Sonnet subagents for building; keep Opus for brand, architecture, security review and hard bugs.

## Roles
Guest, Customer, Waiter, Cashier, Kitchen, Rider, Manager, Owner. Every action is guarded by a Laravel policy; tests cover each role.

## Product scope (complete list; phase it, do not drop items)
Customer side
- Home with live open/closed status, today's specials, quick reorder.
- Menu: categories, search, filters (vegetarian, vegan, spicy level, allergens, price), sold-out items shown but disabled. Every item shows kcal and the UK's 14 major allergens (FSA list); allergen info is never hidden behind a tap only.
- Dish detail: variants (size), add-ons and modifiers with price rules, notes, allergens.
- Cart and checkout: delivery (postcode-based zones, fee and minimum order), pickup (time slot), dine-in (table via QR).
- QR table ordering: scan, order, call waiter, request bill, split bill.
- Payments: Stripe (cards, Apple Pay, Google Pay) and pay at the venue, behind one gateway interface; Stripe test mode only, no real keys in code. Optional tip at checkout; tips are passed in full to staff and reported (Employment (Allocation of Tips) Act 2023).
- Live order tracking: placed → accepted → cooking → ready → out for delivery / served → completed, with estimated time.
- Table reservations with deposit option, reminders and no-show handling.
- Account: order history, saved addresses, reorder, loyalty points, promo codes, reviews after completion.

Staff side
- Order board (POS-lite): all channels in one live queue, accept/reject, edit, refund, print/download receipt.
- Kitchen display (KDS): tickets by station, timers, colour by waiting time, bump when ready, recall.
- Table map: floor plan, status per table, merge/transfer tables.
- Reservations calendar.
- Rider view: assigned deliveries, navigation link, mark delivered, cash collected.
- Menu management: categories, items, variants, modifiers, photos, availability schedules (breakfast/lunch/dinner), price changes with history.
- Inventory: ingredients, recipes per dish, stock in/out, waste log, suppliers.
- Staff: users, roles, shifts, attendance.
- Reports: sales by day/hour/item/channel, top and slow items, average prep time, payment mix, VAT (20% standard rate, configurable per item) and discretionary service charge, tips; export CSV/PDF.
- Settings: hours, bank holidays, delivery zones, VAT, service charge, receipt template, notification channels, food hygiene rating to display.

## Automations (the "fully automated" part)
- Orders: auto-accept by rules (hours, capacity, zone), auto-route lines to kitchen stations, prep-time estimate from history and current load.
- Customer notifications at every status change (SMS/email, queued; WhatsApp-ready interface).
- Inventory: each sale deducts recipe ingredients; item auto-marked sold out when stock runs out; low-stock alert and a draft purchase order to the supplier.
- Reservations: confirmation, reminder 2 hours before, auto-release table after a no-show window.
- Daily close: sales summary emailed to the owner each night; weekly report every Monday.
- After completion: review request; loyalty points credited; abandoned-cart reminder for logged-in customers.
- Every automation runs through queued jobs or the scheduler, is logged in an activity log, and can be switched on or off in settings.

## Phases
0. Setup: the project already exists from `laravel new` with the React starter kit; add `.claude/settings.json` (attribution off), the two agents, a short README, TypeScript strict, ESLint/Prettier, Pest, CI-ready scripts, seeders with a realistic menu (about 40 dishes, 8 categories; modern Indian by default, cuisine set in one config value).
1. Brand and design system: three brand directions → owner's choice → tokens (light and dark), fonts, signature element, base components, and a `/styleguide` page showing every component in every state, in both themes.
2. Customer UI on seeded data, mobile first: every customer screen above.
3. Staff UI on seeded data: order board, KDS, table map, reservations, rider, admin screens.
4. Backend: real models and migrations, order state machine, policies, payments interface with sandbox drivers, Reverb channels for order board, KDS and tracking.
5. Automations: every item in the Automations list, with tests.
6. Hardening: full test suite per role, rate limits, security review (authorization, validation, mass assignment, file uploads), accessibility audit, performance budget check, SEO, Open Graph and restaurant structured data (schema.org) for public pages, UK GDPR privacy notice and a PECR cookie banner with Reject as easy as Accept, PWA install for staff tablets.

## Definition of done (every phase)
Tests green; `npm run build` passes with no TypeScript errors; reviewer subagent has no open findings; screenshots at 375/768/1280 in light and dark saved; no console errors; all states present; `docs/decisions.md` and `docs/progress.md` updated. Then merge and continue.

Start now: read this brief, write `docs/plan.md`, then carry out Phase 0 to Phase 6 in order without waiting. When everything is done, write `docs/handover.md`: what was built, how to run it, the demo logins per role, decisions worth knowing, and what the client must still supply (photos, footage, real payment keys).

