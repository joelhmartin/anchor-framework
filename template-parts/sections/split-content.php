<?php
/**
 * Section: Split Content
 *
 * Two-column layout with image on one side and text content on the other.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)  Optional eyebrow text.
 *   heading        (string)  Section heading.
 *   heading_accent (string)  Accent/italic portion of the heading.
 *   text           (mixed)   String or array of paragraph strings.
 *   image          (string)  Image URL (resolved by renderer).
 *   image_alt      (string)  Alt text for the image.
 *   image_position (string)  'left' or 'right'. Default 'left'.
 *   badge          (array)   Optional floating badge: [ 'value' => '...', 'label' => '...' ].
 *   cta            (array)   Optional CTA link data.
 *   aspect         (string)  Aspect ratio for the image (e.g. '4/4', '3/4'). Default '4/4'.
 *   dark           (bool)    Whether this is a dark variant.
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
$text           = isset( $props['text'] )             ? $props['text']           : '';
$image          = ! empty( $props['image'] )          ? $props['image']          : '';
$image_alt      = ! empty( $props['image_alt'] )      ? $props['image_alt']      : '';
$image_position = ! empty( $props['image_position'] ) ? $props['image_position'] : 'left';
$badge          = ! empty( $props['badge'] )          ? $props['badge']          : null;
$cta            = ! empty( $props['cta'] )            ? $props['cta']            : null;
$aspect         = ! empty( $props['aspect'] )         ? $props['aspect']         : '4/4';
$dark           = ! empty( $props['dark'] );

// Determine column order: image on right means image gets order:2 on desktop.
$image_order = ( 'right' === $image_position ) ? 'order:2;' : '';
$text_order  = ( 'right' === $image_position ) ? 'order:1;' : '';
?>
<div class="anchor-section-pad">
<div class="anchor-container">
    <div class="anchor-grid anchor-grid--2 anchor-split__grid">

        <?php // Image column. ?>
        <div class="anchor-split__image-col"<?php echo $image_order ? ' style="' . esc_attr( $image_order ) . '"' : ''; ?>>
            <?php if ( $image ) : ?>
                <?php
                anchor_render_component( 'image-wrapper', [
                    'image'  => $image,
                    'alt'    => $image_alt ? $image_alt : $heading,
                    'aspect' => $aspect,
                ] );
                ?>
            <?php endif; ?>

            <?php // Floating badge. ?>
            <?php if ( $badge && ! empty( $badge['value'] ) ) : ?>
                <div class="anchor-floating-badge">
                    <span class="anchor-floating-badge__value"><?php echo esc_html( $badge['value'] ); ?></span>
                    <?php if ( ! empty( $badge['label'] ) ) : ?>
                        <span class="anchor-floating-badge__label"><?php echo esc_html( $badge['label'] ); ?></span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php // Text column. ?>
        <div<?php echo $text_order ? ' style="' . esc_attr( $text_order ) . '"' : ''; ?>>
            <?php
            // Heading group.
            anchor_render_component( 'heading-group', [
                'eyebrow'        => $eyebrow,
                'heading'        => $heading,
                'heading_accent' => $heading_accent,
                'tag'            => 'h2',
            ] );
            ?>

            <?php // Text content — supports string or array of paragraphs. ?>
            <?php if ( ! empty( $text ) ) : ?>
                <?php if ( is_array( $text ) ) : ?>
                    <?php foreach ( $text as $paragraph ) : ?>
                        <p class="anchor-text-muted anchor-split__paragraph"><?php echo anchor_esc_content( $paragraph ); ?></p>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p class="anchor-text-muted"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php // CTA link. ?>
            <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
                <div class="anchor-split__cta">
                    <?php
                    $cta_data = array_merge( [
                        'variant' => 'ghost',
                        'icon'    => 'arrow-right',
                    ], $cta );
                    anchor_render_component( 'button', $cta_data );
                    ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</div>
