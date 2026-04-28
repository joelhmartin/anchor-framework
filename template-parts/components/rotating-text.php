<?php
/**
 * Component: Rotating Text
 *
 * Renders text arranged in a circle that slowly rotates via CSS animation.
 * Uses inline SVG with <textPath> for precise circular layout.
 *
 * Data (via anchor_render_component):
 *   text    (string) Text content to render around the circle.
 *                    Include separators in the string (e.g. "CARE • TRUST • ").
 *   size    (int)    Diameter in pixels. Default 200.
 *   color   (string) CSS color value. Default 'currentColor'.
 *   speed   (int)    Rotation duration in seconds. Default 18.
 *   class   (string) Extra CSS classes for the wrapper span.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data  = anchor_get_template_data();

$text  = ! empty( $data['text'] )  ? $data['text']  : '';
$size  = ! empty( $data['size'] )  ? (int) $data['size']  : 200;
$color = ! empty( $data['color'] ) ? $data['color'] : 'currentColor';
$speed = ! empty( $data['speed'] ) ? (int) $data['speed'] : 18;
$class = ! empty( $data['class'] ) ? ' ' . $data['class'] : '';

if ( empty( $text ) ) {
    return;
}

// Unique ID so multiple instances on the same page don't share a path reference.
$uid        = 'rtp-' . substr( md5( $text . $size ), 0, 8 );
$cx         = $size / 2;
$cy         = $size / 2;
$radius     = ( $size / 2 ) * 0.82; // Leave a little breathing room inside the viewbox.
$font_size  = max( 9, round( $size * 0.062 ) ); // ~11px at 180px diameter.
?>
<span
    class="anchor-rotating-text<?php echo esc_attr( $class ); ?>"
    aria-hidden="true"
    style="width:<?php echo (int) $size; ?>px;height:<?php echo (int) $size; ?>px;--rtp-speed:<?php echo (int) $speed; ?>s;"
>
    <svg
        viewBox="0 0 <?php echo (int) $size; ?> <?php echo (int) $size; ?>"
        xmlns="http://www.w3.org/2000/svg"
        width="<?php echo (int) $size; ?>"
        height="<?php echo (int) $size; ?>"
    >
        <defs>
            <path
                id="<?php echo esc_attr( $uid ); ?>"
                d="M <?php echo $cx; ?>,<?php echo $cy; ?> m -<?php echo $radius; ?>,0 a <?php echo $radius; ?>,<?php echo $radius; ?> 0 1,1 <?php echo $radius * 2; ?>,0 a <?php echo $radius; ?>,<?php echo $radius; ?> 0 1,1 -<?php echo $radius * 2; ?>,0"
            />
        </defs>
        <text
            fill="<?php echo esc_attr( $color ); ?>"
            font-size="<?php echo (int) $font_size; ?>"
            letter-spacing="2"
            font-family="inherit"
            text-transform="uppercase"
        >
            <textPath href="#<?php echo esc_attr( $uid ); ?>" startOffset="0%">
                <?php echo esc_html( $text ); ?>
            </textPath>
        </text>
    </svg>
</span>
