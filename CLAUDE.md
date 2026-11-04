# Tandoor House

A restaurant platform for a UK restaurant: customers order, book and pay; staff take, cook, serve and deliver; the owner runs menu, stock, people and money. The restaurant name and cuisine come from `config/restaurant.php`.

## Stack (do not swap)
- Laravel 13, React starter kit: Inertia 3 + React 19 + TypeScript strict + Tailwind 4 + shadcn/ui (restyled to the brand).
- Pest tests. Reverb for real-time. Queued jobs for every notification and long task. SQLite locally, MySQL in production.
- Money is integer pence, GBP. Timezone Europe/London. Locale en-GB.
- Server is the source of truth: routes, controllers, policies, validation in Laravel; pages get typed props via Inertia. No REST API for the web app.

## Commands
- `composer dev` run the app (or Herd: http://tandoor-house.test)
- `php artisan test` · `npm run build` · `npm run check` · `npm run types:check` · `composer lint`
- `php artisan migrate:fresh --seed` resets demo data

## Working rules
- Mobile first: design at 375px, then 768px, then 1280px+.
- Components use design tokens only (`resources/css/app.css`), never raw hex.
- Every string goes through `lang/en_GB/*.php`; British English, warm and specific.
- Every screen has loading (skeleton), empty, error, offline, success and disabled states.
- Motion follows the tokens in `docs/design-system.md`; respect `prefers-reduced-motion`.
- Every action is guarded by a policy; tests cover each role.
- Unclear requirement: pick the industry-standard behaviour, note it in `docs/decisions.md`.

## Git rules
- GitHub Flow: short-lived branch per phase or feature, merged into `main` by pull request.
- Conventional Commits, imperative, lower case, no full stop, no emoji (`feat: add allergen filter to menu`).
- No AI attribution anywhere: no Co-Authored-By trailer, no "Generated with" line, no AI mention in commits, PRs, comments or docs.
- Stage explicit paths, never `git add .`. Never commit `.env` or keys.
- Comments only where the why is not obvious; no TODOs, no commented-out code.

## Agent rules
- The main session owns the plan, design system, shared components and integration.
- `screen-builder` (sonnet) builds one screen or feature and touches only its own files.
- `reviewer` (sonnet, read-only) checks finished work against the brief, design system, accessibility and originality.
- `media-curator` (sonnet) sources and converts photos and video; credits in `docs/image-credits.md`.
- Two agents never edit the same file. Agents return short summaries, not logs.
- Screenshots go to `docs/screenshots/phase-N/`; agents view them at reduced size.

## Docs (read only when a task needs them)
- `docs/brief.md` the full requirement
- `docs/plan.md` screens, data model, state machines, automations, phases
- `docs/design-system.md` tokens, type, motion
- `docs/decisions.md` why things are the way they are
- `docs/progress.md` phase reports
- `.claude/briefs/` instructions given to subagents (customer screens, staff screens, review fixes)
