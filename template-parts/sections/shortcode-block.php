<?php
/**
 * Section: Shortcode Block
 *
 * Wrapper for third-party shortcodes (sliders, forms, galleries, etc.).
 * Provides an optional heading/text intro and constrainable max-width.
 *
 * Data (via anchor_get_template_data):
 *   heading    (string|null) Optional section heading.
 *   text       (string|null) Optional description text.
 *   shortcode  (string)      The shortcode string to render.
 *   max_width  (string|null) CSS max-width value (e.g. '48rem', '800px').
 *   background (string|null) Optional background style.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$eyebrow    = isset( $props['eyebrow'] )     ? $props['eyebrow']    : null;
$heading    = isset( $props['heading'] )     ? $props['heading']    : null;
$heading_accent = isset( $props['heading_accent'] ) ? $props['heading_accent'] : null;
$text       = isset( $props['text'] )        ? $props['text']       : null;
$shortcode  = ! empty( $props['shortcode'] ) ? $props['shortcode']  : '';
$max_width  = isset( $props['max_width'] )   ? $props['max_width']  : null;
$dark       = ! empty( $props['dark'] );

if ( empty( $shortcode ) ) {
    return;
}

// Build container max-width inline style ($max_width is a dynamic PHP value).
$container_style = $max_width ? 'max-width:' . esc_attr( $max_width ) . ';' : '';
?>
<div class="anchor-section-pad">
    <div class="anchor-container"<?php echo $container_style ? ' style="' . esc_attr( $container_style ) . '"' : ''; ?>>

        <?php if ( $heading ) : ?>
            <div class="anchor-text-center anchor-shortcode-block__intro">
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow'        => $eyebrow,
                    'heading'        => $heading,
                    'heading_accent' => $heading_accent,
                    'text'           => $text,
                    'align'          => 'center',
                    'dark'           => $dark,
                ] );
                ?>
            </div>
        <?php endif; ?>

        <div class="anchor-shortcode-block__content">
            <?php echo do_shortcode( $shortcode ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>

    </div>
</div>
