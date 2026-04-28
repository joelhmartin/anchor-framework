<?php
/**
 * Section: ISO Split
 *
 * Two-column layout with a bottom-anchored isolated/cutout image on one side
 * and vertically-centered text content on the other. The image always touches
 * the bottom edge of the section — ideal for transparent-background cutout images
 * preceding a dark section.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)      Eyebrow badge text.
 *   heading        (string)      Section heading.
 *   heading_accent (string|null) Accent portion of heading.
 *   text           (mixed)       String or array of paragraph strings.
 *   image          (string)      Image URL. Should be a cutout/ISO image.
 *   image_alt      (string|null) Alt text for image.
 *   image_position (string)      'left' or 'right'. Default 'left'.
 *   cta            (array|null)  CTA link: ['label' => '...', 'url' => '...'].
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

$eyebrow        = ! empty( $props['eyebrow'] )        ? $props['eyebrow']        : '';
$heading        = ! empty( $props['heading'] )        ? $props['heading']        : '';
$heading_accent = ! empty( $props['heading_accent'] ) ? $props['heading_accent'] : '';
$text           = isset( $props['text'] )             ? $props['text']           : '';
$image          = ! empty( $props['image'] )          ? $props['image']          : '';
$image_alt      = ! empty( $props['image_alt'] )      ? $props['image_alt']      : $heading;
$image_position = ! empty( $props['image_position'] ) ? $props['image_position'] : 'left';
$cta            = ! empty( $props['cta'] )            ? $props['cta']            : null;
$rotating_text  = ! empty( $props['rotating_text'] )  ? $props['rotating_text']  : '';

// Column order: image defaults to left (order 1), content to right (order 2).
$image_order   = ( 'right' === $image_position ) ? 2 : 1;
$content_order = ( 'right' === $image_position ) ? 1 : 2;
?>
<div class="anchor-section-pad">
<div class="anchor-container">
    <div class="anchor-iso-split__grid">

        <!-- Image column: anchored to bottom of section -->
        <div class="anchor-iso-split__image-col" style="order:<?php echo (int) $image_order; ?>;">

            <?php if ( $rotating_text ) : ?>
                <div class="anchor-iso-split__rotating-text">
                    <?php
                    anchor_render_component( 'rotating-text', [
                        'text'  => $rotating_text,
                        'size'  => 200,
                        'color' => 'var(--anchor-accent)',
                        'speed' => 20,
                    ] );
                    ?>
                </div>
            <?php endif; ?>

            <?php if ( $image ) : ?>
                <img
                    src="<?php echo esc_url( $image ); ?>"
                    alt="<?php echo esc_attr( $image_alt ); ?>"
                    class="anchor-iso-split__img"
                >
            <?php endif; ?>
        </div>

        <!-- Content column: vertically centered -->
        <div class="anchor-iso-split__content" style="order:<?php echo (int) $content_order; ?>;">
            <?php
            anchor_render_component( 'heading-group', [
                'eyebrow'        => $eyebrow,
                'heading'        => $heading,
                'heading_accent' => $heading_accent,
                'tag'            => 'h2',
            ] );
            ?>

            <?php if ( ! empty( $text ) ) : ?>
                <?php if ( is_array( $text ) ) : ?>
                    <?php foreach ( $text as $paragraph ) : ?>
                        <p class="anchor-text-muted anchor-split__paragraph"><?php echo anchor_esc_content( $paragraph ); ?></p>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="anchor-text-muted"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
                <div class="anchor-split__cta">
                    <?php
                    anchor_render_component( 'button', array_merge(
                        [ 'variant' => 'ghost', 'icon' => 'arrow-right' ],
                        $cta
                    ) );
                    ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
