<?php
/**
 * Component: Sticky Footer Bar
 *
 * Mobile-only sticky bar at the bottom of the viewport with configurable buttons.
 * Appears after scrolling down 100px. Configured via site.php → sticky_footer.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$site   = function_exists( 'anchor_get_site_config' ) ? anchor_get_site_config() : [];
$config = ! empty( $site['sticky_footer'] ) ? $site['sticky_footer'] : [];

if ( empty( $config['enabled'] ) || empty( $config['buttons'] ) ) {
    return;
}

$buttons = $config['buttons'];
?>

<div class="anchor-sticky-footer" data-sticky-footer aria-label="Quick actions">
    <?php foreach ( $buttons as $btn ) :
        $label = isset( $btn['label'] ) ? $btn['label'] : '';
        $icon  = isset( $btn['icon'] )  ? $btn['icon']  : '';
        $url   = ! empty( $btn['url'] ) ? $btn['url']   : '#';
        $style = ! empty( $btn['style'] ) ? $btn['style'] : 'primary';

        // Allow 'auto' for phone URL — pulls from site config.
        if ( 'auto' === $url && ! empty( $site['phone_href'] ) ) {
            $url = $site['phone_href'];
        }

        $icon_only = ( ! empty( $icon ) && empty( $label ) );
        $classes   = 'anchor-sticky-footer__btn anchor-sticky-footer__btn--' . esc_attr( $style );
        if ( $icon_only ) {
            $classes .= ' anchor-sticky-footer__btn--icon-only';
        }
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>">
            <?php if ( $icon ) : ?>
                <?php echo anchor_icon( $icon, 16 ); ?>
            <?php endif; ?>
            <?php if ( $label ) : ?>
                <span><?php echo esc_html( $label ); ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
