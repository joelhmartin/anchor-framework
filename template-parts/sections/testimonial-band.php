<?php
/**
 * Section: Testimonial Band
 *
 * Grid of testimonial quotes. Each item is rendered via the quote-item component.
 *
 * Data (via anchor_get_template_data):
 *   eyebrow (string|null) Eyebrow badge text.
 *   heading (string|null) Section heading.
 *   items   (array)       Array of quote-item data (name, role, text, photo, rating).
 *   layout  (string)      'grid' (default) or other layout option.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$eyebrow = isset( $props['eyebrow'] ) ? $props['eyebrow'] : null;
$heading = isset( $props['heading'] ) ? $props['heading'] : null;
$items   = ! empty( $props['items'] ) && is_array( $props['items'] ) ? $props['items'] : [];
$layout  = ! empty( $props['layout'] ) ? $props['layout'] : 'grid';

if ( empty( $items ) ) {
    return;
}

// Determine column count based on item count and layout.
$col_count = count( $items ) >= 3 ? 3 : count( $items );
?>
<div class="anchor-section-pad">
    <div class="anchor-container">

        <?php if ( $heading ) : ?>
            <div class="anchor-text-center anchor-max-w-2xl anchor-mx-auto anchor-section-intro">
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow' => $eyebrow,
                    'heading' => $heading,
                    'align'   => 'center',
                ] );
                ?>
            </div>
        <?php endif; ?>

        <div class="anchor-grid anchor-grid--<?php echo esc_attr( $col_count ); ?> anchor-reveal-stagger">
            <?php foreach ( $items as $item ) : ?>
                <?php anchor_render_component( 'quote-item', $item ); ?>
            <?php endforeach; ?>
        </div>

    </div>
</div>
