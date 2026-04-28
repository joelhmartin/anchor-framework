<?php
/**
 * Section: Icon List
 *
 * Grid of icon + title + description items. Maps to "Who We Serve" and
 * "Why Us" sections. Supports both light and dark (navy) backgrounds.
 *
 * Data (via anchor_get_template_data):
 *   eyebrow          (string|null) Eyebrow badge text.
 *   heading          (string)      Section heading.
 *   heading_accent   (string|null) Word(s) to italicize in brand color.
 *   text             (string|null) Subtitle / description text.
 *   columns          (int)         Grid column count. Default 3.
 *   items            (array)       Each: icon, title, text.
 *   dark             (bool)        Navy background variant. Default false.
 *   background_image (string|null) Optional atmospheric background image URL.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$eyebrow          = isset( $props['eyebrow'] )          ? $props['eyebrow']          : null;
$heading          = ! empty( $props['heading'] )         ? $props['heading']          : '';
$heading_accent   = isset( $props['heading_accent'] )    ? $props['heading_accent']   : null;
$text             = isset( $props['text'] )              ? $props['text']             : null;
$columns          = ! empty( $props['columns'] )         ? (int) $props['columns']    : 3;
$items            = ! empty( $props['items'] ) && is_array( $props['items'] ) ? $props['items'] : [];
$dark             = ! empty( $props['dark'] );
$background_image = isset( $props['background_image'] )  ? $props['background_image'] : null;

if ( empty( $items ) ) {
    return;
}

// Card and icon-box modifier classes for dark mode.
$card_class     = $dark ? 'anchor-card anchor-card--dark' : 'anchor-card';
$icon_box_class = $dark ? 'anchor-icon-box anchor-icon-box--dark' : 'anchor-icon-box';
$heading_class  = $dark ? 'anchor-icon-list__item-heading anchor-icon-list__item-heading--dark' : 'anchor-icon-list__item-heading';
?>

<?php if ( $dark && $background_image ) : ?>
    <div class="anchor-section__bg-image">
        <img src="<?php echo esc_url( $background_image ); ?>" alt="" aria-hidden="true">
    </div>
<?php endif; ?>

<div class="anchor-section-pad">
    <div class="anchor-container">

        <?php if ( $heading ) : ?>
            <div class="anchor-text-center anchor-max-w-2xl anchor-mx-auto anchor-section-intro">
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

        <div class="anchor-grid anchor-grid--<?php echo esc_attr( $columns ); ?> anchor-reveal-stagger">
            <?php foreach ( $items as $item ) : ?>
                <?php
                $item_icon  = isset( $item['icon'] )  ? $item['icon']  : '';
                $item_image = isset( $item['image'] ) ? $item['image'] : '';
                $item_title = isset( $item['title'] ) ? $item['title'] : '';
                $item_text  = isset( $item['text'] )  ? $item['text']  : '';

                if ( empty( $item_title ) ) {
                    continue;
                }
                ?>
                <div class="<?php echo esc_attr( $card_class ); ?>">
                    <div class="<?php echo esc_attr( $icon_box_class ); ?>">
                        <?php if ( $item_icon ) : ?>
                            <?php echo anchor_icon( $item_icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php elseif ( $item_image ) : ?>
                            <img src="<?php echo esc_url( $item_image ); ?>" alt="" aria-hidden="true" class="anchor-icon-list__item-image">
                        <?php endif; ?>
                    </div>
                    <h3 class="<?php echo esc_attr( $heading_class ); ?>">
                        <?php echo esc_html( $item_title ); ?>
                    </h3>
                    <?php if ( $item_text ) : ?>
                        <p class="<?php echo $dark ? 'anchor-text-white-muted' : 'anchor-text-muted'; ?> anchor-icon-list__item-text">
                            <?php echo anchor_esc_content( $item_text ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
