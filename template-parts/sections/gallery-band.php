<?php
/**
 * Section: Gallery Band
 *
 * Simple image gallery grid with configurable columns and aspect ratio.
 *
 * Data (via anchor_get_template_data):
 *   heading (string|null) Optional section heading.
 *   images  (array)       Each: image (string URL/path), alt (string), caption (string).
 *   columns (int)         Grid column count. Default 3.
 *   aspect  (string)      CSS aspect-ratio value. Default '4/3'.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$heading = isset( $props['heading'] )  ? $props['heading']       : null;
$images  = ! empty( $props['images'] ) && is_array( $props['images'] ) ? $props['images'] : [];
$columns = ! empty( $props['columns'] ) ? (int) $props['columns'] : 3;
$aspect  = ! empty( $props['aspect'] )  ? $props['aspect']        : '4/3';

if ( empty( $images ) ) {
    return;
}
?>
<div class="anchor-section-pad">
    <div class="anchor-container">

        <?php if ( $heading ) : ?>
            <h2 class="anchor-gallery__heading"><?php echo esc_html( $heading ); ?></h2>
        <?php endif; ?>

        <div class="anchor-grid anchor-grid--<?php echo esc_attr( $columns ); ?>">
            <?php foreach ( $images as $img ) :
                $img_src     = isset( $img['image'] )   ? $img['image']   : '';
                $img_alt     = isset( $img['alt'] )     ? $img['alt']     : '';
                $img_caption = isset( $img['caption'] ) ? $img['caption'] : '';

                if ( empty( $img_src ) ) {
                    continue;
                }

                // Resolve media path if the helper exists.
                $resolved_src = function_exists( 'anchor_resolve_media' )
                    ? anchor_resolve_media( $img_src )
                    : $img_src;
            ?>
                <div class="anchor-gallery__item" style="aspect-ratio:<?php echo esc_attr( $aspect ); ?>;">
                    <img src="<?php echo esc_url( $resolved_src ); ?>"
                         alt="<?php echo esc_attr( $img_alt ); ?>"
                         loading="lazy">
                    <?php if ( ! empty( $img_caption ) ) : ?>
                        <!-- Caption overlay -->
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
