<?php
/**
 * Component: Card
 *
 * Renders a flexible card using anchor-card classes. Supports image, icon,
 * category badge, meta line, and link variants.
 *
 * Data (via anchor_get_template_data):
 *   title     (string) Card title.
 *   text      (string) Card description text.
 *   url       (string) Optional link URL (makes card clickable).
 *   image     (string) Optional image URL.
 *   image_alt (string) Image alt text. Default ''.
 *   category  (string) Optional category badge text.
 *   meta      (string) Optional meta line text (e.g. date).
 *   icon      (string) Optional icon name for anchor_icon().
 *   variant   (string) 'default' | 'dark' | 'link'. Default 'default'.
 *   class     (string) Extra CSS classes.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$title     = ! empty( $data['title'] )     ? $data['title']     : '';
$text      = isset( $data['text'] )        ? $data['text']      : null;
$url       = isset( $data['url'] )         ? $data['url']       : null;
$image     = isset( $data['image'] )       ? $data['image']     : null;
$image_alt = isset( $data['image_alt'] )   ? $data['image_alt'] : '';
$category  = isset( $data['category'] )    ? $data['category']  : null;
$meta      = isset( $data['meta'] )        ? $data['meta']      : null;
$icon      = isset( $data['icon'] )        ? $data['icon']      : null;
$variant   = ! empty( $data['variant'] )   ? $data['variant']   : 'default';
$class     = ! empty( $data['class'] )     ? $data['class']     : '';

if ( empty( $title ) ) {
    return;
}

// Build class string.
$card_class = 'anchor-card';
if ( 'dark' === $variant ) {
    $card_class .= ' anchor-card--dark';
} elseif ( 'link' === $variant ) {
    $card_class .= ' anchor-card--link';
}
if ( $class ) {
    $card_class .= ' ' . $class;
}

// Determine wrapper tag.
$is_link  = ! empty( $url );
$open_tag = $is_link
    ? '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $card_class ) . '">'
    : '<div class="' . esc_attr( $card_class ) . '">';
$close_tag = $is_link ? '</a>' : '</div>';

echo $open_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
?>

    <?php if ( $image ) : ?>
        <div class="anchor-card__image">
            <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $image_alt ); ?>" loading="lazy">
            <?php if ( $category ) : ?>
                <div class="anchor-badge anchor-badge--accent"><?php echo esc_html( $category ); ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $meta ) : ?>
        <div class="anchor-card__meta">
            <?php echo esc_html( $meta ); ?>
        </div>
    <?php endif; ?>

    <?php if ( $icon && function_exists( 'anchor_icon' ) ) : ?>
        <div class="anchor-icon-box anchor-card__icon">
            <?php echo anchor_icon( $icon, 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    <?php endif; ?>

    <h3 class="anchor-card__title"><?php echo esc_html( $title ); ?></h3>

    <?php if ( $text ) : ?>
        <p class="anchor-card__text"><?php echo anchor_esc_content( $text ); ?></p>
    <?php endif; ?>

    <?php if ( $is_link && 'link' !== $variant ) : ?>
        <span class="anchor-link-arrow anchor-card__arrow">
            <?php esc_html_e( 'Learn more', 'anchor-framework' ); ?>
            <?php echo anchor_icon( 'arrow-right', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </span>
    <?php endif; ?>

<?php
echo $close_tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
