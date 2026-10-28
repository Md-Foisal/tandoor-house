# Design system: Clay and Ember

Chosen in `docs/brand-directions.md`. Tokens live in `resources/css/app.css` (`@theme` + `:root` / `.dark`); motion tokens live in `resources/js/lib/motion.ts`. Components use tokens only, never raw colour values.

## Personality
Warm, confident, generous, exact. Talks like the person at the pass: short, specific, a little dry. Never "Welcome to our restaurant", never "delicious", never exclamation marks in interface copy.

## Colour
Semantic tokens; each has a light and a dark value chosen on purpose. Dark is "banked coals": warm charcoal, not grey, with the ember lifted for contrast.

| Token | Use | Light | Dark |
|---|---|---|---|
| `paper` | page background | `#F7F1E8` | `#16110D` |
| `surface` | raised cards, sheets | `#FFFBF5` | `#211A14` |
| `clay` | wells, inputs, hover fill | `#EADFD0` | `#2B221A` |
| `ink` | main text | `#22180F` | `#F2E8DA` |
| `ink-muted` | secondary text | `#6B5A4B` | `#B5A595` |
| `line` | dividers, decorative borders | `#D9CBB8` | `#3A2F25` |
| `line-strong` | input and control borders (3:1) | `#8F7C68` | `#7D6C5B` |
| `ember` | the accent: primary actions, active state, focus | `#A8461F` | `#E8784A` |
| `ember-ink` | text on ember | `#FFF8F0` | `#1A0F08` |
| `ember-soft` | tinted ember background | `#F4DED2` | `#3A2015` |
| `turmeric` | specials, highlights, waiting tickets | `#8A5D00` | `#E2B04A` |
| `turmeric-soft` | | `#F6E7C4` | `#33270F` |
| `leaf` | vegetarian, vegan, success | `#3F6B34` | `#8DBA74` |
| `leaf-soft` | | `#E4EBD9` | `#1F2A19` |
| `chilli` | spice, errors, destructive, late tickets | `#A3231B` | `#F07A6E` |
| `chilli-soft` | | `#F6DEDA` | `#3A1714` |

Measured contrast (WCAG 2.x): ink on paper 15.9, ink-muted on paper 6.0, ember on paper 5.3, ember-ink on ember 5.6, every `x` on `x-soft` ≥ 4.5, line-strong on paper ≥ 3.4 in both themes. No blue, no purple, no gradients except the photo scrim (ink at 0 → 70%).

shadcn variables (`--primary`, `--muted`, ...) are mapped to these tokens, so shadcn components inherit the brand.

## Type
Self-hosted from npm (`@fontsource`), no third-party font requests.

- **Display:** Rozha One 400. Headlines and dish names on dish pages only; never below 24px. Tracking −0.01em, line height 1.05.
- **Interface:** Hanken Grotesk variable 400–700. Everything else. Prices use `tabular-nums`.

| Token | Size (mobile → desktop) | Face / weight | Line height |
|---|---|---|---|
| `display-2xl` | 44 → 88px | Rozha | 1.0 |
| `display-xl` | 36 → 64px | Rozha | 1.05 |
| `display-lg` | 30 → 44px | Rozha | 1.1 |
| `display-md` | 24 → 32px | Rozha | 1.15 |
| `title` | 18 → 20px | Hanken 650 | 1.3 |
| `body-lg` | 17 → 18px | Hanken 400 | 1.55 |
| `body` | 16px | Hanken 400 | 1.55 |
| `small` | 14px | Hanken 450 | 1.45 |
| `label` | 12px, uppercase, +0.08em | Hanken 650 | 1.3 |

Body copy is never under 16px on mobile (prevents iOS zoom, keeps it readable in a dim room).

## Space, size, radius, shadow
- 4px base (Tailwind default). Section rhythm `--space-section`: 64px → 128px. Page gutter 16px → 24px → 40px. Content max width 1200px; reading width 64ch.
- Touch targets ≥ 44×44px. Primary buttons 48px tall on mobile.
- Radius: `sm` 6px (badges), `md` 10px (inputs, buttons), `lg` 16px (cards, sheets), `arch` (full round top, 16px bottom corners), `full` (pills).
- Shadows are warm (ink-tinted) in light and deep with a 1px top highlight in dark:
  - `shadow-plate`: a plate resting on the table; cards after they land.
  - `shadow-lift`: a lifted ticket or open menu.

## Signature elements
- **Tandoor arch** (`<ArchFrame>`): photos framed with a round top. Used for dish photos on cards and dish pages, story photos, the reservation confirmation. Not used in staff screens.
- **Ember line** (`<EmberLine>`): a hand-drawn underline, slightly uneven, under the active tab, active filter and section headings. Drawn with SVG stroke so it can "draw in".
- **Allergen icons** (`<AllergenIcon>`): 14 custom line icons on a 24px grid, 1.75px stroke, round caps, always shown with their name in text (icons never carry meaning alone).

## Motion
"Served from the pass": things move the way their real counterpart does in a restaurant. Tokens in `resources/js/lib/motion.ts`.

| Token | Value | Use |
|---|---|---|
| `duration.fast` | 150ms | hover, press, toggle; the reduced-motion fade |
| `duration.base` | 200ms | menus, popovers, tabs |
| `duration.slow` | 250ms | sheets, dialogs, checkout step change (ceiling for functional motion) |
| `ease.out` | `cubic-bezier(0.22, 1, 0.36, 1)` | all interface feedback |
| `ease.inOut` | `cubic-bezier(0.65, 0, 0.35, 1)` | layout shifts |
| `spring.plate` | stiffness 260, damping 24, mass 0.9 | plate set-down: one barely visible settle |
| `spring.ticket` | stiffness 420, damping 32 | ticket lift, stamp |
| `stagger.course` | 90ms | cards in a group, reading order |
| `group.max` | 600ms | a group never takes longer; stagger shrinks to fit |
| `inView.amount` | 0.2 | trigger once when 20% is visible |

Patterns (each shown at `/styleguide/motion`):
- **Plate set-down:** y +32 → 0, scale 0.96 → 1 on `spring.plate`, then the shadow goes from none to `shadow-plate` 120ms later.
- **Course by course:** groups stagger children by `stagger.course`, capped at `group.max`.
- **Steam clears:** dish photos start `blur(6px) brightness(0.9) sepia(0.15)` and clear in 500ms; hot dishes may show one steam wisp (SVG path, opacity 0 → 0.5 → 0, rising 24px) once.
- **New course:** section heading first, then its content.
- **Kitchen ticket:** active step lifts (`spring.ticket`, y −4, `shadow-lift`); finished steps get a stamp (scale 1.4 → 1, rotate −8°, opacity 0 → 1).
- **Photo print:** story photos develop (opacity and saturation up from 0.4, 700ms), then the caption writes in.
- **Place cards:** reservation slots arrive like cards set on a table (small rotation −2°/2° → 0).
- **Guest book:** reviews land at a slight angle (±1.5°) and straighten.
- **Door opening:** gallery frames uncover from `clip-path: inset(0 12% 0 12%)` to `inset(0)`.
- **Receipt total:** numbers count up with tabular figures.
- **Table marker:** map pin drops 16px with `spring.ticket`.
- **Cleared plates:** filtered-out dishes fade and scale to 0.96, the rest close up with layout animation.
- **To the cart:** a small plate travels to the cart icon, the badge nods once (scale 1 → 1.15 → 1).
- **Shared photo:** dish card photo → dish page hero with `layoutId`.
- **Order slip toasts:** slide up from the bottom edge with a 1° tilt that straightens.

Rules: animate transform and opacity (filter only on images, once). Nothing animates before the hero's first paint. Content renders visible without JavaScript (motion is applied only after hydration). Reduced motion: every pattern becomes a 150ms opacity fade. Public pages are the most expressive; ordering and checkout stay under 250ms; staff screens animate only state changes.

## Theme
Follows the device by default; the toggle stores `light`, `dark` or `system` in a cookie and localStorage; an inline script in the document head sets the class before first paint, so there is no flash. The kitchen display is dark unless a cook switches it.

## Voice samples
- Empty cart: "Nothing on the pass yet."
- Error: "Something went wrong in the kitchen. Try again, or call us on 020 7946 0321."
- Offline: "You're offline. We'll keep your basket until you're back."
- Sold out: "Sold out tonight"
- Cookie banner: "We use a couple of cookies to keep your basket and remember your theme. Others only if you say yes."
