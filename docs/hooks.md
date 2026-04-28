# Anchor Framework — Hooks Reference (v1.0.0)

Filter and action hooks the parent theme exposes for child theme extension. Anything not listed here is internal and may change in any release.

## Naming convention

- Filters and actions both use the `anchor_framework_` prefix.
- Use `add_filter()` / `add_action()` from your child theme's `functions.php`.

## Actions

### `anchor_framework_enqueue_assets`

Fires after the parent enqueues its own CSS and JS.

```php
add_action('anchor_framework_enqueue_assets', function () {
    wp_enqueue_style('my-child-extras', get_stylesheet_directory_uri() . '/assets/css/extras.css');
});
```

## Filters

### `anchor_framework_section_classes`

Filters the array of CSS classes applied to the outer `<section>` element of a section template.

```php
add_filter('anchor_framework_section_classes', function (array $classes, array $section) {
    if ($section['type'] === 'hero' && ! empty($section['props']['has_video'])) {
        $classes[] = 'anchor-section--has-video';
    }
    return $classes;
}, 10, 2);
```

Arguments:
- `array $classes` — current class list.
- `array $section` — the full section definition (`{ type, variant, props }`).

### `anchor_framework_button_args`

Filters the args array used to render any anchor button before the HTML is built.

```php
add_filter('anchor_framework_button_args', function (array $args) {
    if (! empty($args['url']) && strpos($args['url'], 'tel:') === 0) {
        $args['data-track'] = 'phone-click';
    }
    return $args;
});
```

## Conventions

- **Always check for the function/filter existence** before calling, in case the parent theme is temporarily inactive (e.g. during a theme switch).
- **Hook priority** defaults to 10 unless otherwise noted. Use higher numbers to run after the parent's own callbacks, lower to run before.
- **Filter return values** must match the input shape — return the same array structure, the same scalar type, etc.

## Adding new hooks

If you need an extension point that does not exist, open an issue at <https://github.com/joelhmartin/anchor-framework/issues>. New hooks ship in minor releases; the contract is that once a hook is documented here, its name and arguments stay stable until the next major release.
