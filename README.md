# Anchor Framework

A reusable WordPress parent theme. Provides a CSS/JS component library, a small set of PHP helpers, and a catalog of section templates a child theme can compose into pages.

The framework is **client-agnostic** — it contains no client names, no custom post types, and no page content. Everything client-specific lives in a child theme.

## Quickstart for child theme authors

1. Install Anchor Framework by uploading the latest release zip in *Appearance → Themes → Add New → Upload Theme*. Updates after that come through the standard WP update flow.
2. Create a child theme that lists Anchor Framework as `Template:` in its `style.css`.
3. In the child:
   - Register your CPTs and taxonomies in `functions.php`.
   - Drop client-specific markup into `page-content/{slug}.php` — that file is included verbatim when a page with that slug renders.
   - Or fall back to the config-driven path: define a section array in `config/pages/{slug}.php` and let the parent's renderer handle it.
   - Override `header.php`, `footer.php`, or any specific template (`single-{cpt}.php`, etc.) as needed.

## Two ways to render a page

### Direct PHP (recommended for bespoke pages)

Drop a file at `child-theme/page-content/{slug}.php`. The parent's `front-page.php` and `page.php` will include it directly, bypassing the renderer.

Inside the file you have two patterns:

**Pure inline HTML** — write whatever markup you want and apply Anchor classes:

```php
<section class="anchor-section anchor-section--dark">
  <div class="anchor-container">
    <h2>Hello</h2>
  </div>
</section>
```

**Compose framework sections** — set the section data, load the template:

```php
anchor_set_template_data([
  'type'    => 'hero',
  'variant' => 'short',
  'props'   => [ 'heading' => 'About us', 'eyebrow' => '— Company' ],
]);
get_template_part('template-parts/sections/hero');
```

### Config-driven (good when the page is just a list of standard sections)

Skip `page-content/` and define `child-theme/config/pages/{slug}.php`:

```php
return [
  'sections' => [
    [ 'type' => 'hero', 'variant' => 'short', 'props' => [ 'heading' => 'About' ] ],
    [ 'type' => 'split_content', 'props' => [ 'heading' => '…' ] ],
  ],
];
```

The parent's renderer reads the config, validates each section, resolves any media keys, and outputs the HTML.

## What you get

- **Section templates** at `template-parts/sections/`: hero, split-content, text-block, card-grid, team-grid, contact-block, services-tabs, cta-band, icon-list, faq-list, testimonial-band, gallery-band, iso-split, shortcode-block. See [docs/components.md](docs/components.md) for the full catalog with props.
- **CSS classes** with the `anchor-` prefix and BEM-style modifiers. The full public list is in [docs/components.md](docs/components.md).
- **JS behaviors** that auto-attach via classes/data attributes — `.anchor-reveal` for IntersectionObserver entrance animations, the navigation script for the fixed-pill header, the services-tabs interaction, etc.
- **PHP helpers** — see the function reference in [docs/components.md](docs/components.md).
- **Hook points** for child theme extensibility — see [docs/hooks.md](docs/hooks.md).

## Updates

This theme uses [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) to publish updates as standard WordPress theme updates against [the GitHub repo](https://github.com/joelhmartin/anchor-framework). When a new release is tagged, every site running the theme sees an *Update available* notice in *Appearance → Themes* and can update with one click.

For private-repo use, define `ANCHOR_FRAMEWORK_GH_TOKEN` in `wp-config.php` with a GitHub Personal Access Token that has `repo` scope.

## Versioning

Semantic versioning. The version number lives in `style.css` under `Version:`.

- **Major** — breaking change to the public API. Migration notes ship in `CHANGELOG.md` and `docs/migration/`.
- **Minor** — new public API additions, fully backward compatible.
- **Patch** — bug fixes, internal refactors, no public-API change.

The public API is the surface documented in `docs/components.md` and `docs/hooks.md`. Anything not listed there — internal function names, file paths inside `inc/`, the contents of `template-parts/components/` — is implementation detail and may change in any version.

## Sibling: anchor-page-assistant

The [Anchor Page Assistant](https://github.com/joelhmartin/anchor-page-assistant) is a separate plugin that adds an AI-driven page-building assistant. It versions independently but releases are coordinated — major versions of the framework will name the compatible plugin range in `CHANGELOG.md`.

## License

GPL-2.0-or-later, matching WordPress.
