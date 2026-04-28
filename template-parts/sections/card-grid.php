<?php
/**
 * Section: Card Grid
 *
 * Grid of cards with header. Variant controls card rendering style.
 *
 * Variants:
 *   'services'     — Icon + title + text cards.
 *   'benefits'     — Icon box cards (similar to services).
 *   'icon'         — Icon box cards (alias for benefits).
 *   'team-preview' — Photo cards with name/role overlay.
 *   'posts'        — Blog post cards with image, meta, excerpt.
 *   (default)      — Basic cards with optional image.
 *
 * Props (via anchor_get_template_data):
 *   eyebrow        (string)  Optional eyebrow text.
 *   heading        (string)  Section heading.
 *   heading_accent (string)  Accent/italic portion of the heading.
 *   text           (string)  Optional aside/description text.
 *   columns        (int)     Grid columns: 2, 3, or 4. Default 3.
 *   items          (array)   Array of item data (shape depends on variant).
 *   cta            (array)   Optional bottom CTA link data.
 *   align          (string)  'left' or 'center'. Default 'left'.
 *   dark           (bool)    Dark background mode.
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
$text           = ! empty( $props['text'] )           ? $props['text']           : '';
$columns        = ! empty( $props['columns'] )        ? (int) $props['columns']  : 3;
$items          = ! empty( $props['items'] )          ? $props['items']          : [];
$cta            = ! empty( $props['cta'] )            ? $props['cta']            : null;
$align          = ! empty( $props['align'] )          ? $props['align']          : 'left';
$dark           = ! empty( $props['dark'] );

if ( empty( $items ) ) {
    return;
}
?>
<div class="anchor-section-pad">
<div class="anchor-container">

    <?php // Header row. ?>
    <?php if ( $heading || $eyebrow ) : ?>
        <div class="anchor-card-grid__header">
            <div<?php echo ( 'center' === $align ) ? ' class="anchor-card-grid__header-full"' : ''; ?>>
                <?php
                anchor_render_component( 'heading-group', [
                    'eyebrow'        => $eyebrow,
                    'heading'        => $heading,
                    'heading_accent' => $heading_accent,
                    'tag'            => 'h2',
                    'align'          => $align,
                ] );
                ?>
            </div>

            <?php // Aside text (shown on the right when align is left). ?>
            <?php if ( $text && 'left' === $align ) : ?>
                <div class="anchor-card-grid__aside">
                    <p class="anchor-text-muted"><?php echo anchor_esc_content( $text ); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <?php // Centered description text. ?>
        <?php if ( $text && 'center' === $align ) : ?>
            <div class="anchor-card-grid__centered-text">
                <p class="anchor-text-muted"><?php echo anchor_esc_content( $text ); ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php // Grid. ?>
    <div class="anchor-grid anchor-grid--<?php echo esc_attr( $columns ); ?> anchor-reveal-stagger">

        <?php foreach ( $items as $item ) : ?>

            <?php
            // ----------------------------------------------------------
            // Variant: team-preview
            // ----------------------------------------------------------
            if ( 'team-preview' === $variant ) :
                $name  = ! empty( $item['name'] )  ? $item['name']  : '';
                $role  = ! empty( $item['role'] )  ? $item['role']  : '';
                $photo = ! empty( $item['photo'] ) ? $item['photo'] : '';
                $url   = ! empty( $item['url'] )   ? $item['url']   : '';

                $tag_open  = $url ? '<a href="' . esc_url( $url ) . '" class="anchor-team-card">' : '<div class="anchor-team-card">';
                $tag_close = $url ? '</a>' : '</div>';
            ?>
                <?php echo $tag_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <div class="anchor-team-card__image">
                        <?php if ( $photo ) : ?>
                            <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy">
                        <?php endif; ?>
                        <div class="anchor-team-card__overlay"></div>
                        <div class="anchor-team-card__info">
                            <?php if ( $name ) : ?>
                                <h3 class="anchor-team-card__name"><?php echo esc_html( $name ); ?></h3>
                            <?php endif; ?>
                            <?php if ( $role ) : ?>
                                <p class="anchor-team-card__role"><?php echo esc_html( $role ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php echo $tag_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php
            // ----------------------------------------------------------
            // Variant: posts
            // ----------------------------------------------------------
            elseif ( 'posts' === $variant ) :
                $title     = ! empty( $item['title'] )     ? $item['title']     : '';
                $excerpt   = ! empty( $item['excerpt'] )   ? $item['excerpt']   : '';
                $post_image = ! empty( $item['image'] )    ? $item['image']     : '';
                $url       = ! empty( $item['url'] )       ? $item['url']       : '';
                $post_cat  = ! empty( $item['category'] )  ? $item['category']  : '';
                $date      = ! empty( $item['date'] )      ? $item['date']      : '';
                $read_time = ! empty( $item['read_time'] ) ? $item['read_time'] : '';
            ?>
                <article class="anchor-post-card">
                    <?php if ( $post_image ) : ?>
                        <div class="anchor-post-card__image">
                            <a href="<?php echo esc_url( $url ); ?>">
                                <img src="<?php echo esc_url( $post_image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                            </a>
                            <?php if ( $post_cat ) : ?>
                                <span class="anchor-badge anchor-badge--brand anchor-post-card__category-badge"><?php echo esc_html( $post_cat ); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="anchor-post-card__body">
                        <?php // Meta line. ?>
                        <?php if ( $date || $read_time ) : ?>
                            <div class="anchor-post-card__meta anchor-text-muted">
                                <?php if ( $date ) : ?>
                                    <span><?php echo esc_html( $date ); ?></span>
                                <?php endif; ?>
                                <?php if ( $date && $read_time ) : ?>
                                    <span class="anchor-post-card__meta-sep">&bull;</span>
                                <?php endif; ?>
                                <?php if ( $read_time ) : ?>
                                    <span><?php echo esc_html( $read_time ); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $title ) : ?>
                            <h3 class="anchor-post-card__title">
                                <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $title ); ?></a>
                            </h3>
                        <?php endif; ?>

                        <?php if ( $excerpt ) : ?>
                            <p class="anchor-post-card__excerpt anchor-text-muted"><?php echo anchor_esc_content( $excerpt ); ?></p>
                        <?php endif; ?>

                        <?php if ( $url ) : ?>
                            <a href="<?php echo esc_url( $url ); ?>" class="anchor-post-card__link">
                                <span>Read more</span>
                                <?php echo anchor_icon( 'arrow-right', 14 ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </article>

            <?php
            // ----------------------------------------------------------
            // Variant: services / benefits / icon
            // ----------------------------------------------------------
            elseif ( in_array( $variant, [ 'services', 'benefits', 'icon' ], true ) ) :
                $title     = ! empty( $item['title'] ) ? $item['title'] : '';
                $item_text = ! empty( $item['text'] )  ? $item['text']  : '';
                $url       = ! empty( $item['url'] )   ? $item['url']   : '';
                $icon      = ! empty( $item['icon'] )  ? $item['icon']  : '';

                $tag_open  = $url ? '<a href="' . esc_url( $url ) . '" class="anchor-card">' : '<div class="anchor-card">';
                $tag_close = $url ? '</a>' : '</div>';
            ?>
                <?php echo $tag_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ( $icon ) : ?>
                        <div class="anchor-icon-box anchor-card__icon-box">
                            <?php echo anchor_icon( $icon, 24 ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $title ) : ?>
                        <h3 class="anchor-card__title"><?php echo esc_html( $title ); ?></h3>
                    <?php endif; ?>

                    <?php if ( $item_text ) : ?>
                        <p class="anchor-card__text anchor-text-muted"><?php echo anchor_esc_content( $item_text ); ?></p>
                    <?php endif; ?>

                    <?php if ( $url ) : ?>
                        <span class="anchor-card__arrow">
                            <span>Learn more</span>
                            <?php echo anchor_icon( 'arrow-right', 14 ); ?>
                        </span>
                    <?php endif; ?>
                <?php echo $tag_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php
            // ----------------------------------------------------------
            // Default variant: basic cards
            // ----------------------------------------------------------
            else :
                $title      = ! empty( $item['title'] )   ? $item['title']   : '';
                $item_text  = ! empty( $item['text'] )    ? $item['text']    : '';
                $item_image = ! empty( $item['image'] )   ? $item['image']   : '';
                $url        = ! empty( $item['url'] )     ? $item['url']     : '';
                $icon       = ! empty( $item['icon'] )    ? $item['icon']    : '';

                $tag_open  = $url ? '<a href="' . esc_url( $url ) . '" class="anchor-card">' : '<div class="anchor-card">';
                $tag_close = $url ? '</a>' : '</div>';
            ?>
                <?php echo $tag_open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php if ( $item_image ) : ?>
                        <div class="anchor-card__image anchor-card__image-box">
                            <img src="<?php echo esc_url( $item_image ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy">
                        </div>
                    <?php elseif ( $icon ) : ?>
                        <div class="anchor-icon-box anchor-card__icon-box">
                            <?php echo anchor_icon( $icon, 24 ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( $title ) : ?>
                        <h3 class="anchor-card__title"><?php echo esc_html( $title ); ?></h3>
                    <?php endif; ?>

                    <?php if ( $item_text ) : ?>
                        <p class="anchor-card__text anchor-text-muted"><?php echo anchor_esc_content( $item_text ); ?></p>
                    <?php endif; ?>
                <?php echo $tag_close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

            <?php endif; ?>

        <?php endforeach; ?>

    </div>

    <?php // Bottom CTA link. ?>
    <?php if ( $cta && ! empty( $cta['label'] ) ) : ?>
        <div class="anchor-section-cta">
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
