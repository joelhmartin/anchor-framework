<?php
/**
 * Section: CTA Band
 *
 * Call-to-action banner section with multiple visual variants.
 *
 * Variants:
 *   'light'  — White/surface background, centered text.
 *   'dark'   — Navy background, centered text, white text.
 *   'accent' — Brand-500 background, white text.
 *   'card'   — Black card with noise overlay and rounded corners.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow          (string)  Optional eyebrow text.
 *   heading          (string)  Section heading.
 *   heading_accent   (string)  Accent/italic portion of the heading.
 *   text             (string)  Description text.
 *   primary_cta      (array)   Primary button data.
 *   secondary_cta    (array)   Secondary button data.
 *   align            (string)  'center' or 'left'. Default 'center'.
 *   background_image (string)  Optional background image URL.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$variant = ! empty( $section['variant'] ) ? $section['variant'] : 'light';
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Extract props with defaults.
$eyebrow          = ! empty( $props['eyebrow'] )          ? $props['eyebrow']          : '';
$heading          = ! empty( $props['heading'] )          ? $props['heading']          : '';
$heading_accent   = ! empty( $props['heading_accent'] )   ? $props['heading_accent']   : '';
$text             = ! empty( $props['text'] )             ? $props['text']             : '';
$primary_cta      = ! empty( $props['primary_cta'] )      ? $props['primary_cta']      : null;
$secondary_cta    = ! empty( $props['secondary_cta'] )    ? $props['secondary_cta']    : null;
$align            = ! empty( $props['align'] )            ? $props['align']            : 'center';
$background_image = ! empty( $props['background_image'] ) ? $props['background_image'] : '';

// Build buttons array.
$buttons = [];
if ( $primary_cta ) {
    $buttons[] = $primary_cta;
}
if ( $secondary_cta ) {
    $buttons[] = $secondary_cta;
}

// ------------------------------------------------------------------
// Variant: card (black card with noise overlay)
// ------------------------------------------------------------------
if ( 'card' === $variant ) : ?>

    <div class="anchor-section-pad">
    <div class="anchor-container">
        <div class="anchor-cta-card">
            <div class="anchor-cta-card__inner">
                <?php if ( $eyebrow ) : ?>
                    <div class="anchor-cta-card__eyebrow">
                        <span class="anchor-badge anchor-badge--glass"><?php echo esc_html( $eyebrow ); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ( $heading ) : ?>
                    <h2 class="anchor-cta-card__heading">
                        <?php echo esc_html( $heading ); ?>
                        <?php if ( $heading_accent ) : ?>
                            <span class="anchor-text-drama anchor-cta-card__accent"><?php echo esc_html( $heading_accent ); ?></span>
                        <?php endif; ?>
                    </h2>
                <?php endif; ?>

                <?php if ( $text ) : ?>
                    <p class="anchor-cta-card__text"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>

                <?php if ( ! empty( $buttons ) ) : ?>
                    <div class="anchor-cta-card__buttons">
                        <?php anchor_render_component( 'button-group', [ 'buttons' => $buttons, 'align' => 'center' ] ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

<?php
// ------------------------------------------------------------------
// Variant: expand (scroll-triggered expanding card + fixed background)
// ------------------------------------------------------------------
elseif ( 'expand' === $variant ) :
    $ease = 'cubic-bezier(0.25, 0.8, 0.25, 1)';
    $uid  = 'anchor-cta-expand-' . substr( md5( $heading ), 0, 6 );
?>
    <div class="anchor-section-pad">
    <div class="anchor-container">
        <div
            class="anchor-cta-expand-card"
            id="<?php echo esc_attr( $uid ); ?>"
            data-expand-image="<?php echo esc_attr( $background_image ); ?>"
            style="transition: transform 0.8s <?php echo $ease; ?>, background-color 0.8s <?php echo $ease; ?>, border-radius 0.8s <?php echo $ease; ?>;"
        >
            <div class="anchor-cta-card__inner">
                <?php if ( $eyebrow ) : ?>
                    <div class="anchor-cta-card__eyebrow--expand">
                        <span class="anchor-badge anchor-badge--glass"><?php echo esc_html( $eyebrow ); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ( $heading ) : ?>
                    <h2 class="anchor-cta-card__heading">
                        <?php echo esc_html( $heading ); ?>
                        <?php if ( $heading_accent ) : ?>
                            <span class="anchor-text-drama anchor-cta-card__accent--expand"><?php echo esc_html( $heading_accent ); ?></span>
                        <?php endif; ?>
                    </h2>
                <?php endif; ?>

                <?php if ( $text ) : ?>
                    <p class="anchor-cta-card__text--expand"><?php echo anchor_esc_content( $text ); ?></p>
                <?php endif; ?>

                <?php if ( ! empty( $buttons ) ) : ?>
                    <div class="anchor-cta-card__buttons--expand">
                        <?php anchor_render_component( 'button-group', [ 'buttons' => $buttons, 'align' => 'center' ] ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

<?php
// ------------------------------------------------------------------
// All other variants: light, dark, accent
// ------------------------------------------------------------------
else : ?>

    <div class="anchor-section-pad">
    <div class="anchor-container--narrow anchor-mx-auto anchor-text-center">

        <?php if ( $eyebrow ) : ?>
            <div class="anchor-cta-card__eyebrow">
                <span class="anchor-badge anchor-badge--brand"><?php echo esc_html( $eyebrow ); ?></span>
            </div>
        <?php endif; ?>

        <?php if ( $heading ) : ?>
            <?php
            anchor_render_component( 'heading-group', [
                'heading'        => $heading,
                'heading_accent' => $heading_accent,
                'tag'            => 'h2',
                'align'          => 'center',
            ] );
            ?>
        <?php endif; ?>

        <?php if ( $text ) : ?>
            <p class="anchor-text-muted anchor-cta-band__text"><?php echo anchor_esc_content( $text ); ?></p>
        <?php endif; ?>

        <?php if ( ! empty( $buttons ) ) : ?>
            <div class="anchor-cta-band__buttons">
                <?php anchor_render_component( 'button-group', [ 'buttons' => $buttons, 'align' => 'center' ] ); ?>
            </div>
        <?php endif; ?>

    </div>
    </div>

<?php endif; ?>
