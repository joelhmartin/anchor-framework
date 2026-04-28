<?php
/**
 * Component: Button
 *
 * Renders an anchor-btn link element with optional icon.
 *
 * Data (via anchor_get_template_data):
 *   label    (string)  Button text.
 *   url      (string)  Destination URL.
 *   variant  (string)  'primary' | 'ghost' | 'outline' | 'white'. Default 'primary'.
 *   size     (string)  'sm' | 'md' | 'lg'. Default 'md'.
 *   icon     (string)  Optional icon name for anchor_icon().
 *   new_tab  (bool)    Open in a new tab. Default false.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$label   = ! empty( $data['label'] )   ? $data['label']   : '';
$url     = ! empty( $data['url'] )     ? $data['url']     : '#';
$variant = ! empty( $data['variant'] ) ? $data['variant'] : 'primary';
$size    = ! empty( $data['size'] )    ? $data['size']    : 'md';
$icon    = isset( $data['icon'] )      ? $data['icon']    : null;
$new_tab = ! empty( $data['new_tab'] );

if ( empty( $label ) ) {
    return;
}

// Build class string.
$classes = 'anchor-btn anchor-btn--' . esc_attr( $variant );
if ( 'md' !== $size ) {
    $classes .= ' anchor-btn--' . esc_attr( $size );
}

// Target attribute.
$target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
?>
<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"<?php echo $target; ?>>
    <span><?php echo esc_html( $label ); ?></span>
    <?php if ( $icon && function_exists( 'anchor_icon' ) ) : ?>
        <?php echo anchor_icon( $icon, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
</a>
