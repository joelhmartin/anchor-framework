<?php
/**
 * Component: Image Wrapper
 *
 * Renders an image container with aspect ratio, border radius, and optional
 * gradient overlay and floating badge.
 *
 * Data (via anchor_get_template_data):
 *   image   (string) Image URL or path.
 *   alt     (string) Alt text. Default ''.
 *   aspect  (string) CSS aspect-ratio value. Default '4/3'.
 *   rounded (string) Border radius token name. Default '3xl'.
 *   overlay (string) Optional gradient overlay: 'bt' (bottom-to-top) | 'lr' (left-to-right).
 *   badge   (array)  Optional floating badge: ['value' => '15+', 'label' => 'Years'].
 *   class   (string) Extra CSS classes.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$image   = ! empty( $data['image'] )   ? $data['image']   : '';
$alt     = isset( $data['alt'] )       ? $data['alt']     : '';
$aspect  = ! empty( $data['aspect'] )  ? $data['aspect']  : '4/3';
$rounded = ! empty( $data['rounded'] ) ? $data['rounded'] : '3xl';
$overlay = isset( $data['overlay'] )   ? $data['overlay'] : null;
$badge   = isset( $data['badge'] )     ? $data['badge']   : null;
$class   = ! empty( $data['class'] )   ? $data['class']   : '';

if ( empty( $image ) ) {
    return;
}

// Build wrapper classes.
$wrapper_class = 'anchor-image-wrapper anchor-image-wrapper--rounded-' . esc_attr( $rounded );
if ( $class ) {
    $wrapper_class .= ' ' . $class;
}

// Aspect ratio must remain inline since it's a dynamic value from PHP.
$style = 'aspect-ratio: ' . esc_attr( $aspect );
?>
<div class="<?php echo esc_attr( $wrapper_class ); ?>" style="<?php echo esc_attr( $style ); ?>">

    <img
        src="<?php echo esc_url( $image ); ?>"
        alt="<?php echo esc_attr( $alt ); ?>"
        loading="lazy"
        class="anchor-image-wrapper__img"
    >

    <?php if ( $overlay ) : ?>
        <?php
        $overlay_class = 'anchor-image-wrapper__overlay';
        if ( 'bt' === $overlay ) {
            $overlay_class .= ' anchor-image-wrapper__overlay--bt';
        } elseif ( 'lr' === $overlay ) {
            $overlay_class .= ' anchor-image-wrapper__overlay--lr';
        }
        ?>
        <div class="<?php echo esc_attr( $overlay_class ); ?>"></div>
    <?php endif; ?>

    <?php if ( $badge && is_array( $badge ) && ! empty( $badge['value'] ) ) : ?>
        <div class="anchor-floating-badge">
            <div class="anchor-floating-badge__value"><?php echo esc_html( $badge['value'] ); ?></div>
            <?php if ( ! empty( $badge['label'] ) ) : ?>
                <div class="anchor-floating-badge__label"><?php echo esc_html( $badge['label'] ); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
