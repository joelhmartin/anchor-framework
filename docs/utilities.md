# Anchor Framework — Utility Classes

The parent theme ships a scoped utility-class vocabulary. These are the **primary styling tool** for child-theme `page-content/*.php` files. Bespoke CSS is the exception, not the rule.

All utilities are prefixed `anchor-`. All color/radius/size values reference CSS custom properties, so the theme settings page (or a child's `client-tokens.css`) can override them without modifying utility files.

## Breakpoints

| Name | Max-width | Use |
|------|-----------|-----|
| sm   | 639px     | Mobile |
| md   | 1023px    | Tablet + mobile |
| lg   | 1279px    | Below desktop |
| (default) | —    | Desktop |

## Layout — `_layout.css`

### Grid

- `.anchor-grid` — `display: grid; gap: var(--anchor-gap-md);`
- `.anchor-grid-{1..6}` — N columns at desktop. Mobile collapse baked in:
  - `grid-6` → 3 cols (md) → 1 col (sm)
  - `grid-5` → 3 (md) → 1 (sm)
  - `grid-4` → 2 (md) → 1 (sm)
  - `grid-3` / `grid-2` → unchanged (md) → 1 (sm)
- `.anchor-grid-center` — centers orphan items in the last row. Combine with `.anchor-grid-{n}`.

### Flex

- `.anchor-flex`, `.anchor-flex-row`, `.anchor-flex-col`
- `.anchor-flex-wrap`, `.anchor-flex-nowrap`
- `.anchor-justify-{start,center,end,between,around}`
- `.anchor-items-{start,center,end,stretch,baseline}`
- `.anchor-reverse` — at `sm`, flips a flex row to `column-reverse` (right-side image becomes top-of-stack)

### Stack / cluster

- `.anchor-stack` — vertical flow with `--anchor-gap-md` between children
- `.anchor-cluster` — horizontal wrap, ideal for tag/button groups

## Typography — `_typography.css`

Type ramp (clamp-based, fluid):

| Class | Default value |
|---|---|
| `.anchor-display` | `clamp(3rem, 8vw, 6rem)` |
| `.anchor-h1` | `clamp(2.5rem, 5vw, 4rem)` |
| `.anchor-h2` | `clamp(2rem, 4vw, 3rem)` |
| `.anchor-h3` | `clamp(1.5rem, 3vw, 2rem)` |
| `.anchor-h4` | `clamp(1.25rem, 2vw, 1.5rem)` |
| `.anchor-body` | `clamp(1rem, 1.2vw, 1.125rem)` |
| `.anchor-small` | `clamp(0.875rem, 1vw, 1rem)` |
| `.anchor-eyebrow` | uppercase + tracked-out |
| `.anchor-caption` | `0.75rem` fine-print |

Other: `.anchor-text-{left,center,right}`, `.anchor-text-balance`, `.anchor-text-pretty`, `.anchor-text-nowrap`, `.anchor-uppercase`, `.anchor-lowercase`, `.anchor-capitalize`, `.anchor-tracking-{tight,normal,wide,wider}`, `.anchor-weight-{light,normal,medium,semibold,bold}`, `.anchor-leading-{tight,normal,relaxed}`, `.anchor-font-{heading,body,accent}`.

## Spacing — `_spacing.css`

Scale: `0`, `xs` (4px), `sm` (12px), `md` (24px), `lg` (40px), `xl` (64px), `2xl` (96px).

- `.anchor-p-{scale}`, `.anchor-px-{scale}`, `.anchor-py-{scale}`, `.anchor-pt-{scale}`, `.anchor-pb-{scale}`
- `.anchor-m-{scale}`, `.anchor-mt-{scale}`, `.anchor-mb-{scale}`, `.anchor-m-auto`, `.anchor-mx-auto`
- `.anchor-gap-{scale}`

## Sizing — `_sizing.css`

- Width: `.anchor-w-full`, `.anchor-w-auto`, `.anchor-w-prose` (65ch), `.anchor-w-narrow` (40rem), `.anchor-w-content` (80ch)
- Max-width: `.anchor-max-w-{sm,md,lg,xl,2xl}`
- Height: `.anchor-h-full`, `.anchor-h-screen`, `.anchor-min-h-screen`, `.anchor-min-h-half`
- Aspect: `.anchor-aspect-{video,square,portrait,wide}`

## Colors — `_colors.css`

CSS-var driven. Override via `client-tokens.css` or the settings page.

- BG: `.anchor-bg-{ink,ivory,accent,muted,transparent,current}`
- Text: `.anchor-text-{ink,ivory,accent,muted,inherit}`
- Border color: `.anchor-border-{rule,rule-strong,ink,accent,current}`

## Borders / radius / shadow — `_borders.css`

- `.anchor-border`, `.anchor-border-{t,b,l,r}`, `.anchor-border-0`
- `.anchor-rounded-{none,sm,md,lg,full}` (4px / 8px / 16px / 999px)
- `.anchor-shadow-{none,sm,md,lg}`

## Responsive display — `_responsive.css`

- `.anchor-block`, `.anchor-inline`, `.anchor-inline-block`, `.anchor-hidden`
- `.anchor-hide-{sm,md,lg}` — hides AT that breakpoint and narrower
- `.anchor-show-{sm,md,lg}` — shows ONLY at that breakpoint and narrower

## A11y — `_a11y.css`

- `.anchor-sr-only` / `.anchor-not-sr-only` — screen-reader visibility
- `.anchor-focus-ring` — explicit focus ring (use when reset removes the default)

## Build pipeline

Source files in `assets/css/utilities/_*.css`. Built to `dist/utilities.min.css` via esbuild.

```bash
npm run build   # one-shot
npm run watch   # rebuild on save
```

WordPress enqueues `dist/utilities.min.css`. `dist/` is tracked in git so the theme works post-clone without `npm install`.
