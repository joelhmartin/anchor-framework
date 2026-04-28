# Anchor Framework — Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] — 2026-04-28

First public release. Initial extraction from the `wp-ai-assistant` site repository into a standalone, distributable theme.

### Added
- Public API documented in `docs/components.md` (CSS classes, section templates, PHP helpers) and `docs/hooks.md` (filter/action extension points).
- GitHub-based update flow via bundled `plugin-update-checker`. New releases on `joelhmartin/anchor-framework` automatically surface in WordPress *Appearance → Themes → Update*.
- `ANCHOR_FRAMEWORK_GH_TOKEN` constant — define in `wp-config.php` to authenticate against a private repository.
- `Theme URI` and `GitHub Theme URI` headers in `style.css`.

### Removed (vs. pre-extraction state)
- All client-specific code that previously leaked into the parent theme:
  - `template-parts/sections/deka-*.php` (10 files of editorial sections specific to the Deka site).
  - `archive-laser_product.php` and `single-laser_product.php` (now owned by the child theme that registers the `laser_product` CPT).
  - `deka_*` entries from the section validator in `inc/validation.php`.

### Section catalog
The public section types shipped in 1.0.0:
- `hero` (variants: `short`, `full`, `centered`)
- `split_content`
- `iso_split`
- `text_block`
- `card_grid` (variants: `icon`, `services`, default)
- `team_grid`
- `contact_block`
- `services_tabs`
- `cta_band` (variants: `light`, `dark`, `accent`, `card`, `expand`)
- `testimonial_band`
- `faq_list`
- `gallery_band`
- `icon_list`
- `shortcode_block`

### Compatibility
- WordPress: requires 6.4 or later, tested up to 6.8.
- PHP: requires 7.4 or later.
- Sibling plugin: works with `anchor-page-assistant` 1.0.x.
