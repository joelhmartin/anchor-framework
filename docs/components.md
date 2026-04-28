# Anchor Framework — Component Reference (v1.0.0)

This document describes the public API. Anything not listed here is internal and may change in any release.

## Section templates

Loaded via `get_template_part('template-parts/sections/{slug}')` after calling `anchor_set_template_data()`, or rendered through `anchor_render_sections()`.

### `hero`

A page-opening hero section. Variants: `short`, `full`, `centered`.

```php
anchor_set_template_data([
  'type'    => 'hero',
  'variant' => 'short',
  'props'   => [
    'eyebrow'         => '— Company',
    'heading'         => 'About us',
    'heading_accent'  => 'us',
    'text'            => 'Lede paragraph.',
    'background_image'=> 'home_hero',  // media key, attachment ID, or URL
    'primary_cta'     => [ 'label' => 'Get started', 'url' => '/contact/' ],
    'secondary_cta'   => [ 'label' => 'Learn more', 'url' => '/about/' ],
    'align'           => 'center',     // or 'left'
  ],
]);
get_template_part('template-parts/sections/hero');
```

### `split_content`

Two-column split: text on one side, image on the other.

Props: `eyebrow`, `heading`, `heading_accent`, `text`, `image`, `image_position` (`left`/`right`), `cta`.

### `iso_split`

Isometric image + content split, alternative visual to `split_content`.

Props: same general shape as `split_content`.

### `text_block`

Rich-text block, narrow column.

Props: `heading`, `content` (HTML allowed).

### `card_grid`

Responsive grid of cards. Variants: `icon` (icon + title + text), `services` (image + heading + description + link), default.

Props: `eyebrow`, `heading`, `text`, `cards` (array of card objects with `heading`, `text`, `image`/`icon`, `link`), `cta`.

### `team_grid`

Grid of team members.

Props: `heading`, `members` (array with `name`, `role`, `photo`, `bio`, `socials`).

### `contact_block`

Contact form / map / details composite.

Props: `heading`, `text`, `fields` (array), `show_form` (bool), `show_map` (bool), `form_shortcode` (string).

### `services_tabs`

Horizontal tabs revealing service cards.

Props: `eyebrow`, `heading`, `heading_accent`, `text`, `tabs` (array, each with label and content), `cta`.

### `cta_band`

Banner-style call to action. Variants: `light`, `dark`, `accent` (brand color), `card` (boxed), `expand` (expanding-on-scroll).

Props: `eyebrow`, `heading`, `heading_accent`, `text`, `primary_cta`, `secondary_cta`, `align`, `background_image`.

### `testimonial_band`

Testimonial / quote section.

Props: `quotes` (array of `{ quote, attribution, role }`).

### `faq_list`

Accordion-style FAQ list. Auto-attaches the accordion JS via `.anchor-faq` class.

Props: `heading`, `items` (array of `{ question, answer }`).

### `gallery_band`

Image gallery / lightbox-friendly band.

Props: `images` (array of media keys/URLs/IDs).

### `icon_list`

List of icon + heading + text rows.

Props: `heading`, `items` (array of `{ icon, heading, text }`).

### `shortcode_block`

Renders a WordPress shortcode in the section context.

Props: `shortcode` (string).

## Common section props

Every section honors these wrapper-level props (in addition to its own data props):

- `flush_bottom` (bool) — applies `.anchor-section--flush-bottom` (no bottom padding).
- `dark` (bool) — applies `.anchor-section--dark` (navy bg, light text).

## PHP helpers

### Rendering
- `anchor_render_sections(array $sections): void` — render a list of sections from an array of `{ type, variant?, props }` items.
- `anchor_set_template_data(array $section): void` — set the section data the next `get_template_part('template-parts/sections/{type}')` call will read.
- `anchor_get_template_data(): array` — read the data inside a section template; returns `{ type, variant, props }`.

### Config / data accessors
- `anchor_load_config(string $name)` — load a config file from the child theme's `config/` directory by name. Returns the file's return value.
- `anchor_get_data(string $key)` — accessor for the child theme's shared `config/data.php`.
- `anchor_get_navigation_config()` — accessor for `config/navigation.php`.
- `anchor_get_media_config()` — accessor for `config/media.php`. **Designed for child override** — child themes commonly redefine this to merge dynamically imported attachment IDs.

### Media
- `anchor_resolve_media($key_or_url_or_id): string` — turn a media reference into a usable URL. Resolution order: empty → '', integer → `wp_get_attachment_url()`, absolute URL → returned as-is, string → looked up in the media config, otherwise treated as a theme-relative path.
- `anchor_resolve_media_in_props(array $props): array` — recursively resolve any media-shaped key in a props array (any key matching `image`, `photo`, `background`, `logo`, `icon_image`, or ending in `_image`/`_photo`/`_bg`/`_media`).

## CSS classes

### Layout primitives
- `.anchor-container` — main content container, max width controlled by `--anchor-container-max`.
- `.anchor-section` — vertical section wrapper.
- `.anchor-section-pad` — section padding helper used inside heroes.
- Modifiers: `.anchor-section--dark`, `.anchor-section--flush-bottom`, `.anchor-section--icon`, `.anchor-section--services`, `.anchor-section--surface`.
- `.anchor-header-spacer` — pushes content below the fixed-pill nav.

### Components
- `.anchor-btn`, with modifiers `.anchor-btn--primary`, `.anchor-btn--secondary`, `.anchor-btn--ghost`. Ghost buttons auto-adapt to dark/light contexts based on parent selectors.
- `.anchor-nav` — the fixed-pill header navigation.
- `.anchor-heading-group` — wrapper that handles eyebrow/heading/lede composition; appends `heading_accent` if it's not already a substring of the heading.

### Behaviors (auto-attach via class)
- `.anchor-reveal` — IntersectionObserver entrance animation. Add to any element you want to fade/slide in on scroll.
- `.anchor-reveal-stagger` — applies staggered entrance to direct children of an element with `.anchor-reveal`.
- `.anchor-responsive` — responsive container utility.

### Tokens (CSS custom properties exposed via design-token classes)
- Brand: `.anchor-brand-{50,100,200,300,400,500,600,700,800,900,950}`. RGB triplets for alpha math: `.anchor-brand-{400,500,600}-rgb`.
- Accent: `.anchor-accent-{400,500,600}`.
- Navy: `.anchor-navy`, `.anchor-navy-dark`, `.anchor-navy-light`. RGB: `.anchor-navy-rgb`.
- Surface: `.anchor-surface-{50,100,200,300,400}`. RGB: `.anchor-surface-{200,300}-rgb`.

### Key CSS variables (the underlying tokens)
- `--anchor-container-max` — main container width.
- `--anchor-container-narrow` — narrow container width for text-heavy sections.
- `--anchor-section-py` / `--anchor-section-py-lg` — section vertical padding (`clamp()` based).

## JS modules

Auto-loaded; you do not need to init them.

- **scroll-reveal** — drives `.anchor-reveal` and `.anchor-reveal-stagger`. Also handles the FAQ accordion behavior, the services-tabs tab switching, and the expanding-CTA scroll behavior.
- **navigation** — drives the fixed-pill header, the mobile menu drawer, and scroll-direction-aware show/hide of the nav.

Both scripts are passive — they search the DOM on load and attach behaviors to any matching elements.

## Section wrapper pattern (canonical HTML)

If you're writing inline HTML rather than calling `get_template_part`, this is the pattern parent CSS expects:

```html
<section class="anchor-section anchor-section--dark anchor-section--flush-bottom">
  <div class="anchor-section-pad">
    <div class="anchor-container">
      <!-- content -->
    </div>
  </div>
</section>
```

## Hero content pattern (inner width constraint)

```html
<div class="anchor-hero__content anchor-section-pad">
  <div class="anchor-container">
    <div class="anchor-hero__inner">  <!-- max-width: 40rem -->
      <!-- heading, text, buttons -->
    </div>
  </div>
</div>
```
