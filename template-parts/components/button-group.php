<?php
/**
 * Component: Button Group
 *
 * Renders a flex wrapper containing multiple button components.
 *
 * Data (via anchor_get_template_data):
 *   buttons  (array)  Array of button data arrays.
 *   align    (string) 'left' | 'center' | 'right'. Default 'left'.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

$buttons = ! empty( $data['buttons'] ) && is_array( $data['buttons'] ) ? $data['buttons'] : [];
$align   = ! empty( $data['align'] ) ? $data['align'] : 'left';

if ( empty( $buttons ) ) {
    return;
}

// Build class string for alignment.
$group_class = 'anchor-btn-group';
if ( 'center' === $align ) {
    $group_class .= ' anchor-btn-group--center';
} elseif ( 'right' === $align ) {
    $group_class .= ' anchor-btn-group--right';
}
?>
<div class="<?php echo esc_attr( $group_class ); ?>">
    <?php foreach ( $buttons as $button ) : ?>
        <?php anchor_render_component( 'button', $button ); ?>
    <?php endforeach; ?>
</div>
