<?php
/**
 * Component: Icon Item
 *
 * Icon box + title + description. Used inside icon_list sections.
 *
 * Data (via anchor_get_template_data):
 *   icon  (string) Icon name (for anchor_icon), raw SVG/HTML (starts with '<'), or image URL.
 *   title (string) Item title.
 *   text  (string) Item description.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$icon  = ! empty( $data['icon'] )  ? $data['icon']  : '';
$title = ! empty( $data['title'] ) ? $data['title'] : '';
$text  = ! empty( $data['text'] )  ? $data['text']  : '';

if ( empty( $title ) ) {
    return;
}

// Determine how to render the icon.
$icon_html = '';
if ( ! empty( $icon ) ) {
    if ( strpos( $icon, '<' ) === 0 ) {
        // Raw SVG or HTML — output directly.
        $icon_html = $icon;
    } elseif ( function_exists( 'anchor_icon' ) && anchor_icon( $icon, 18 ) ) {
        // Known icon name.
        $icon_html = anchor_icon( $icon, 18 );
    } else {
        // Treat as image URL.
        $icon_html = '<img src="' . esc_url( $icon ) . '" alt="" width="18" height="18">';
    }
}
?>
<div class="anchor-icon-item">

    <?php if ( $icon_html ) : ?>
        <div class="anchor-icon-box">
            <?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>

    <h3 class="anchor-icon-item__title"><?php echo esc_html( $title ); ?></h3>

    <?php if ( $text ) : ?>
        <p class="anchor-icon-item__text"><?php echo anchor_esc_content( $text ); ?></p>
    <?php endif; ?>

</div>
