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
