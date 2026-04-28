<?php
/**
 * Component: Hero Carousel
 *
 * Full-width hero carousel for featured/sticky posts or any array of slides.
 * Shows navigation dots and arrows when there are multiple slides.
 * Falls back to a single static hero when there's only one slide.
 *
 * Data (via anchor_get_template_data):
 *   slides (array) Each slide: [
 *     'title'    => (string) Slide heading,
 *     'excerpt'  => (string) Optional subtitle text,
 *     'image'    => (string) Background image URL,
 *     'url'      => (string) Link URL,
 *     'badges'   => (array)  Optional array of badge strings,
 *     'meta'     => (string) Optional meta line (date, etc.),
 *     'link_text'=> (string) CTA text. Default 'Read Article',
 *   ]
 *   min_height (string) CSS min-height. Default '70dvh'.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data       = anchor_get_template_data();
$slides     = ! empty( $data['slides'] ) ? $data['slides'] : [];
$min_height = ! empty( $data['min_height'] ) ? $data['min_height'] : '70dvh';
$count      = count( $slides );

if ( empty( $slides ) ) {
    return;
}
?>

<div class="anchor-carousel" data-carousel data-carousel-count="<?php echo esc_attr( $count ); ?>">

    <?php foreach ( $slides as $i => $slide ) :
        $title        = ! empty( $slide['title'] )        ? $slide['title']        : '';
        $excerpt      = ! empty( $slide['excerpt'] )      ? $slide['excerpt']      : '';
        $image        = ! empty( $slide['image'] )         ? $slide['image']        : '';
        $image_mobile = ! empty( $slide['image_mobile'] )  ? $slide['image_mobile'] : '';
        $url          = ! empty( $slide['url'] )           ? $slide['url']          : '';
        $badges       = ! empty( $slide['badges'] )        ? $slide['badges']       : [];
        $meta         = ! empty( $slide['meta'] )          ? $slide['meta']         : '';
        $link_text    = ! empty( $slide['link_text'] )     ? $slide['link_text']    : 'Read Article';
        $active       = ( 0 === $i ) ? ' anchor-carousel__slide--active' : '';
    ?>
        <div class="anchor-carousel__slide<?php echo esc_attr( $active ); ?>" data-carousel-slide="<?php echo esc_attr( $i ); ?>">

            <div class="anchor-hero__bg">
                <?php if ( $image && $image_mobile ) : ?>
                    <picture>
                        <source media="(max-width: 768px)" srcset="<?php echo esc_url( $image_mobile ); ?>">
                        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                    </picture>
                <?php elseif ( $image ) : ?>
                    <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>">
                <?php endif; ?>
                <div class="anchor-hero__overlay anchor-hero__overlay--gradient-top-fade"></div>
            </div>

            <?php if ( $url ) : ?>
                <a href="<?php echo esc_url( $url ); ?>" class="anchor-hero__stretched-link" aria-label="<?php echo esc_attr( $title ); ?>"></a>
            <?php endif; ?>

            <div class="anchor-carousel__content anchor-section-pad">
            <div class="anchor-container">
            <div class="anchor-hero__inner">

                <?php if ( ! empty( $badges ) ) : ?>
                    <div class="anchor-hero__meta-row">
                        <?php foreach ( $badges as $j => $badge_text ) : ?>
                            <span class="anchor-badge <?php echo ( 0 === $j ) ? 'anchor-badge--accent' : 'anchor-badge--glass'; ?>">
                                <?php echo esc_html( $badge_text ); ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <h2 class="anchor-hero__heading anchor-hero__heading--archive">
                    <?php echo esc_html( $title ); ?>
                </h2>

                <?php if ( $excerpt ) : ?>
                    <p class="anchor-hero__excerpt"><?php echo esc_html( $excerpt ); ?></p>
                <?php endif; ?>

                <?php if ( $meta ) : ?>
                    <div class="anchor-hero__date-row">
                        <span class="anchor-hero__date-item"><?php echo anchor_icon( 'calendar', 14 ); ?> <?php echo esc_html( $meta ); ?></span>
                    </div>
                <?php endif; ?>

                <span class="anchor-link-arrow anchor-hero__read-link">
                    <?php echo esc_html( $link_text ); ?> <?php echo anchor_icon( 'arrow-right', 16 ); ?>
                </span>

            </div>
            </div>
            </div>

        </div>
    <?php endforeach; ?>

    <?php // Navigation — absolutely positioned wrapper with inner container to match content alignment. ?>
    <?php if ( $count > 1 ) : ?>
        <div class="anchor-carousel__nav-wrap anchor-section-pad">
            <div class="anchor-container">
                <div class="anchor-hero__inner">
                    <div class="anchor-carousel__nav">
                        <button class="anchor-carousel__arrow anchor-carousel__arrow--prev" data-carousel-prev aria-label="Previous slide">
                            <?php echo anchor_icon( 'chevron-left', 20 ); ?>
                        </button>
                        <div class="anchor-carousel__dots">
                            <?php for ( $d = 0; $d < $count; $d++ ) : ?>
                                <button class="anchor-carousel__dot<?php echo ( 0 === $d ) ? ' anchor-carousel__dot--active' : ''; ?>"
                                        data-carousel-dot="<?php echo esc_attr( $d ); ?>"
                                        aria-label="Go to slide <?php echo esc_attr( $d + 1 ); ?>"></button>
                            <?php endfor; ?>
                        </div>
                        <button class="anchor-carousel__arrow anchor-carousel__arrow--next" data-carousel-next aria-label="Next slide">
                            <?php echo anchor_icon( 'chevron-right', 20 ); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
