<?php
/**
 * Component: Heading Group
 *
 * The signature heading pattern: optional eyebrow badge, heading with optional
 * italic accent word in brand color, and optional subtitle text.
 *
 * Data (via anchor_get_template_data):
 *   eyebrow        (string|null) Eyebrow badge text.
 *   heading        (string)      Main heading text.
 *   heading_accent (string|null) Word(s) to wrap in .anchor-text-drama span.
 *   text           (string|null) Subtitle / description text.
 *   align          (string)      'left' | 'center'. Default 'left'.
 *   dark           (bool)        Use dark badge variant + light text colors.
 *   tag            (string)      Heading HTML tag. Default 'h2'.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$eyebrow        = isset( $data['eyebrow'] )        ? $data['eyebrow']        : null;
$heading        = ! empty( $data['heading'] )       ? $data['heading']        : '';
$heading_accent = isset( $data['heading_accent'] )  ? $data['heading_accent'] : null;
$text           = isset( $data['text'] )            ? $data['text']           : null;
$align          = ! empty( $data['align'] )         ? $data['align']          : 'left';
$dark           = ! empty( $data['dark'] );
$tag            = ! empty( $data['tag'] )           ? $data['tag']            : 'h2';

if ( empty( $heading ) ) {
    return;
}

// Sanitize heading tag to allowed set.
$allowed_tags = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div' ];
if ( ! in_array( $tag, $allowed_tags, true ) ) {
    $tag = 'h2';
}

// Container classes.
$wrapper_classes = 'anchor-heading-group';
if ( 'center' === $align ) {
    $wrapper_classes .= ' anchor-text-center';
}

// Badge variant.
$badge_class = $dark ? 'anchor-badge anchor-badge--dark' : 'anchor-badge anchor-badge--brand';

// Heading class for dark backgrounds.
$heading_class = 'anchor-heading-group__title';
if ( $dark ) {
    $heading_class .= ' anchor-heading-group__title--dark';
}

// Text class for dark backgrounds.
$text_class = 'anchor-heading-group__text';
if ( $dark ) {
    $text_class .= ' anchor-heading-group__text--dark';
}

// Build heading content with optional accent.
$heading_html = esc_html( $heading );
if ( $heading_accent ) {
    $accent_span = '<span class="anchor-text-drama">' . esc_html( $heading_accent ) . '</span>';
    if ( strpos( $heading, $heading_accent ) !== false ) {
        $heading_html = str_replace( esc_html( $heading_accent ), $accent_span, $heading_html );
    } else {
        $heading_html .= ' ' . $accent_span;
    }
}
?>
<div class="<?php echo esc_attr( $wrapper_classes ); ?>">

    <?php if ( $eyebrow ) : ?>
        <div class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $eyebrow ); ?></div>
    <?php endif; ?>

    <<?php echo $tag; ?> class="<?php echo esc_attr( $heading_class ); ?>">
        <?php echo $heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
    </<?php echo $tag; ?>>

    <?php if ( $text ) : ?>
        <p class="<?php echo esc_attr( $text_class ); ?>"><?php echo anchor_esc_content( $text ); ?></p>
    <?php endif; ?>

</div>
