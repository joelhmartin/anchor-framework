<?php
/**
 * Section: Text Block
 *
 * Centered (or left-aligned) text-only section for mission statements,
 * blockquotes, and standalone content.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)  Optional eyebrow text.
 *   heading        (string)  Section heading.
 *   heading_accent (string)  Accent/italic portion of the heading.
 *   content        (string)  Body content (may include basic HTML).
 *   align          (string)  'center' or 'left'. Default 'center'.
 *   max_width      (string)  Max-width token: 'sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'. Default '4xl'.
 *   divider        (string)  Optional divider text (e.g. "Lorem · Ipsum · Dolor").
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$variant = ! empty( $section['variant'] ) ? $section['variant'] : '';
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Extract props with defaults.
$eyebrow        = ! empty( $props['eyebrow'] )        ? $props['eyebrow']        : '';
$heading        = ! empty( $props['heading'] )        ? $props['heading']        : '';
$heading_accent = ! empty( $props['heading_accent'] ) ? $props['heading_accent'] : '';
$content        = ! empty( $props['content'] ) ? $props['content'] : ( ! empty( $props['text'] ) ? $props['text'] : '' );
$align          = ! empty( $props['align'] )          ? $props['align']          : 'center';
$max_width      = ! empty( $props['max_width'] )      ? $props['max_width']      : '4xl';
$divider        = isset( $props['divider'] )          ? $props['divider']        : '';

// Map max_width token to CSS value.
$width_map = [
    'sm'  => '32rem',
    'md'  => '36rem',
    'lg'  => '42rem',
    'xl'  => '48rem',
    '2xl' => '52rem',
    '3xl' => '56rem',
    '4xl' => '60rem',
];
$max_width_css = isset( $width_map[ $max_width ] ) ? $width_map[ $max_width ] : $max_width;

// Build wrapper classes.
$wrapper_classes = 'anchor-container--narrow anchor-mx-auto';
if ( 'center' === $align ) {
    $wrapper_classes .= ' anchor-text-center';
}
?>
<div class="anchor-section-pad">
<div class="<?php echo esc_attr( $wrapper_classes ); ?>" style="max-width:<?php echo esc_attr( $max_width_css ); ?>;">

    <?php // Heading group. ?>
    <?php if ( $heading ) : ?>
        <?php
        anchor_render_component( 'heading-group', [
            'eyebrow'        => $eyebrow,
            'heading'        => $heading,
            'heading_accent' => $heading_accent,
            'tag'            => 'h2',
            'align'          => $align,
        ] );
        ?>
    <?php elseif ( $eyebrow ) : ?>
        <?php
        anchor_render_component( 'heading-group', [
            'eyebrow' => $eyebrow,
            'align'   => $align,
        ] );
        ?>
    <?php endif; ?>

    <?php // Content. ?>
    <?php if ( $content ) : ?>
        <div class="anchor-text-block__content">
            <?php echo anchor_esc_content( $content ); ?>
        </div>
    <?php endif; ?>

    <?php // Divider. ?>
    <?php if ( $divider ) : ?>
        <div class="anchor-text-block__divider">
            <span class="anchor-divider anchor-text-block__divider-line"></span>
            <span class="anchor-text-mono anchor-text-block__divider-text"><?php echo esc_html( $divider ); ?></span>
            <span class="anchor-divider anchor-text-block__divider-line"></span>
        </div>
    <?php endif; ?>

</div>
</div>
