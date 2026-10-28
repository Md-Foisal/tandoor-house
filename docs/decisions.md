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
