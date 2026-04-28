<?php
/**
 * Section: Hero
 *
 * Full-viewport hero, short inner-page hero, or centered text hero.
 *
 * Variants:
 *   'full'     — Homepage hero with background image, ticker, multi-line heading, CTAs, values strip.
 *   'short'    — Inner-page hero with background image, optional back-link and category badge.
 *   'centered' — Simple centered text hero with no background image.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow       (string)  Optional eyebrow text.
 *   heading        (string)  Simple heading fallback.
 *   heading_lines  (array)   Array of [ 'text' => '...', 'style' => 'bold|italic|accent' ].
 *   text           (string)  Subtitle / description.
 *   image          (string)  Background image URL (resolved by renderer).
 *   ticker_items   (array)   Array of strings for the scrolling ticker.
 *   values         (array)   Array of strings for the values strip.
 *   primary_cta    (array)   Primary button data.
 *   secondary_cta  (array)   Secondary button data.
 *   back_link      (array)   [ 'label' => '...', 'url' => '...' ] for inner-page back nav.
 *   category       (string)  Category badge text.
 *   min_height     (string)  CSS min-height value (e.g. '45dvh').
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$variant = ! empty( $section['variant'] ) ? $section['variant'] : 'full';
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Extract props with defaults.
$eyebrow       = ! empty( $props['eyebrow'] )       ? $props['eyebrow']       : '';
$heading        = ! empty( $props['heading'] )        ? $props['heading']        : '';
$heading_lines  = ! empty( $props['heading_lines'] )  ? $props['heading_lines']  : [];
$text           = ! empty( $props['text'] )           ? $props['text']           : '';
$image          = ! empty( $props['image'] )          ? $props['image']          : '';
$ticker_items   = ! empty( $props['ticker_items'] )   ? $props['ticker_items']   : [];
$values         = ! empty( $props['values'] )         ? $props['values']         : [];
$primary_cta    = ! empty( $props['primary_cta'] )    ? $props['primary_cta']    : null;
$secondary_cta  = ! empty( $props['secondary_cta'] )  ? $props['secondary_cta']  : null;
$back_link      = ! empty( $props['back_link'] )      ? $props['back_link']      : null;
$category       = ! empty( $props['category'] )       ? $props['category']       : '';
$image_mobile   = ! empty( $props['image_mobile'] )   ? $props['image_mobile']   : '';
$min_height     = ! empty( $props['min_height'] )     ? $props['min_height']     : '';

// ------------------------------------------------------------------
// Variant: full (homepage hero — 100dvh)
// ------------------------------------------------------------------
if ( 'full' === $variant ) :

    $hero_style = '';
    if ( $min_height ) {
        $hero_style = ' style="min-height: ' . esc_attr( $min_height ) . ';"';
    }
?>
    <div class="anchor-hero anchor-hero--full"<?php echo $hero_style; ?>>

        <?php // Background image + overlays. ?>
        <?php if ( $image ) : ?>
            <div class="anchor-hero__bg">
                <?php if ( $image_mobile ) : ?>
                    <picture>
                        <source media="(max-width: 768px)" srcset="<?php echo esc_url( $image_mobile ); ?>">
                        <img src="<?php echo esc_url( $image ); ?>" alt="" loading="eager">
                    </picture>
                <?php else : ?>
                    <img src="<?php echo esc_url( $image ); ?>" alt="" loading="eager">
                <?php endif; ?>
                <div class="anchor-hero__overlay anchor-hero__overlay--lr"></div>
                <div class="anchor-hero__overlay anchor-hero__overlay--bt"></div>
            </div>
        <?php endif; ?>

        <?php // Content area. ?>
        <div class="anchor-hero__content anchor-hero__content--full anchor-section-pad">
        <div class="anchor-container">
        <div class="anchor-hero__inner">

            <?php // Ticker. ?>
            <?php if ( ! empty( $ticker_items ) ) : ?>
                <?php $ticker_text = implode( ' &bull; ', array_map( 'esc_html', $ticker_items ) ) . ' &bull;&nbsp;'; ?>
                <div class="anchor-ticker">
                    <span class="anchor-ticker__dot"></span>
                    <div class="anchor-ticker__track">
                        <div class="anchor-ticker__scroll">
                            <span class="anchor-ticker__text"><?php echo $ticker_text; ?></span>
                            <span class="anchor-ticker__text"><?php echo $ticker_text; ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php // Heading. ?>
            <?php if ( ! empty( $heading_lines ) ) : ?>
                <h1 class="anchor-hero__heading">
                    <?php foreach ( $heading_lines as $line ) :
                        $line_text  = ! empty( $line['text'] )  ? $line['text']  : '';
                        $line_style = ! empty( $line['style'] ) ? $line['style'] : 'bold';

                        if ( 'italic' === $line_style || 'accent' === $line_style ) :
                    ?>
                        <span class="anchor-hero__heading-line--accent anchor-text-drama"><?php echo esc_html( $line_text ); ?></span>
                    <?php else : ?>
                        <span class="anchor-hero__heading-line--bold"><?php echo esc_html( $line_text ); ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </h1>
            <?php elseif ( $heading ) : ?>
                <h1 class="anchor-hero__heading"><?php echo esc_html( $heading ); ?></h1>
            <?php endif; ?>

            <?php // Subtitle text. ?>
            <?php if ( $text ) : ?>
                <p class="anchor-hero__text"><?php echo anchor_esc_content( $text ); ?></p>
            <?php endif; ?>

            <?php // CTA buttons. ?>
            <?php
            $buttons = [];
            if ( $primary_cta ) {
                $buttons[] = $primary_cta;
            }
            if ( $secondary_cta ) {
                $buttons[] = $secondary_cta;
            }
            if ( ! empty( $buttons ) ) {
                anchor_render_component( 'button-group', [ 'buttons' => $buttons ] );
            }
            ?>

            <?php // Values strip. ?>
            <?php if ( ! empty( $values ) ) : ?>
                <div class="anchor-values-strip">
                    <?php foreach ( $values as $index => $value ) : ?>
                        <?php if ( $index > 0 ) : ?>
                            <span class="anchor-values-strip__dot" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span class="anchor-values-strip__item"><?php echo esc_html( $value ); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
        </div>
        </div>
    </div>

<?php
// ------------------------------------------------------------------
// Variant: short (inner-page hero)
// ------------------------------------------------------------------
elseif ( 'short' === $variant ) :

    $hero_style = '';
    if ( $min_height ) {
        $hero_style = ' style="min-height: ' . esc_attr( $min_height ) . ';"';
    }
?>
    <div class="anchor-hero anchor-hero--short"<?php echo $hero_style; ?>>

        <?php // Background image + overlays. ?>
        <?php if ( $image ) : ?>
            <div class="anchor-hero__bg">
                <?php if ( $image_mobile ) : ?>
                    <picture>
                        <source media="(max-width: 768px)" srcset="<?php echo esc_url( $image_mobile ); ?>">
                        <img src="<?php echo esc_url( $image ); ?>" alt="" loading="eager">
                    </picture>
                <?php else : ?>
                    <img src="<?php echo esc_url( $image ); ?>" alt="" loading="eager">
                <?php endif; ?>
                <div class="anchor-hero__overlay anchor-hero__overlay--lr"></div>
                <div class="anchor-hero__overlay anchor-hero__overlay--bt"></div>
            </div>
        <?php endif; ?>

        <?php // Content. ?>
        <div class="anchor-hero__content anchor-hero__content--short-inner anchor-section-pad">
        <div class="anchor-container">
        <div class="anchor-hero__inner">

            <?php // Back link. ?>
            <?php if ( $back_link && ! empty( $back_link['url'] ) ) : ?>
                <a href="<?php echo esc_url( $back_link['url'] ); ?>" class="anchor-hero__back-link">
                    <?php echo anchor_icon( 'arrow-left', 14 ); ?>
                    <span><?php echo esc_html( ! empty( $back_link['label'] ) ? $back_link['label'] : 'Back' ); ?></span>
                </a>
            <?php endif; ?>

            <?php // Category badge. ?>
            <?php if ( $category ) : ?>
                <div class="anchor-hero__badge-wrap">
                    <span class="anchor-badge anchor-badge--brand"><?php echo esc_html( $category ); ?></span>
                </div>
            <?php endif; ?>

            <?php // Eyebrow as glass badge. ?>
            <?php if ( $eyebrow ) : ?>
                <div class="anchor-hero__badge-wrap">
                    <span class="anchor-badge anchor-badge--glass"><?php echo esc_html( $eyebrow ); ?></span>
                </div>
            <?php endif; ?>

            <?php // Heading. ?>
            <?php if ( ! empty( $heading_lines ) ) : ?>
                <h1 class="anchor-hero__heading">
                    <?php foreach ( $heading_lines as $line ) :
                        $line_text  = ! empty( $line['text'] )  ? $line['text']  : '';
                        $line_style = ! empty( $line['style'] ) ? $line['style'] : 'bold';

                        if ( 'italic' === $line_style || 'accent' === $line_style ) :
                    ?>
                        <span class="block anchor-hero__heading-line--accent anchor-text-drama"><?php echo esc_html( $line_text ); ?></span>
                    <?php else : ?>
                        <span class="block anchor-hero__heading-line--bold"><?php echo esc_html( $line_text ); ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </h1>
            <?php elseif ( $heading ) : ?>
                <h1 class="anchor-hero__heading"><?php echo esc_html( $heading ); ?></h1>
            <?php endif; ?>

            <?php // Subtitle text. ?>
            <?php if ( $text ) : ?>
                <p class="anchor-hero__text"><?php echo anchor_esc_content( $text ); ?></p>
            <?php endif; ?>

        </div>
        </div>
        </div>
    </div>

<?php
// ------------------------------------------------------------------
// Variant: centered (simple text hero, no background)
// ------------------------------------------------------------------
else : ?>
    <div class="anchor-hero anchor-hero--centered anchor-section-pad">
        <div class="anchor-container--narrow anchor-mx-auto">

            <?php if ( $eyebrow ) : ?>
                <div class="anchor-hero__badge-wrap">
                    <span class="anchor-badge anchor-badge--brand"><?php echo esc_html( $eyebrow ); ?></span>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $heading_lines ) ) : ?>
                <h1 class="anchor-hero__heading">
                    <?php foreach ( $heading_lines as $line ) :
                        $line_text  = ! empty( $line['text'] )  ? $line['text']  : '';
                        $line_style = ! empty( $line['style'] ) ? $line['style'] : 'bold';

                        if ( 'italic' === $line_style || 'accent' === $line_style ) :
                    ?>
                        <span class="block anchor-hero__heading-line--accent anchor-text-drama"><?php echo esc_html( $line_text ); ?></span>
                    <?php else : ?>
                        <span class="block anchor-hero__heading-line--bold"><?php echo esc_html( $line_text ); ?></span>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </h1>
            <?php elseif ( $heading ) : ?>
                <h1 class="anchor-hero__heading"><?php echo esc_html( $heading ); ?></h1>
            <?php endif; ?>

            <?php if ( $text ) : ?>
                <p class="anchor-hero__text"><?php echo anchor_esc_content( $text ); ?></p>
            <?php endif; ?>

            <?php
            $buttons = [];
            if ( $primary_cta ) {
                $buttons[] = $primary_cta;
            }
            if ( $secondary_cta ) {
                $buttons[] = $secondary_cta;
            }
            if ( ! empty( $buttons ) ) {
                anchor_render_component( 'button-group', [ 'buttons' => $buttons, 'align' => 'center' ] );
            }
            ?>

        </div>
    </div>

<?php endif; ?>
