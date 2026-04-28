<?php
/**
 * Header Navigation
 * Fixed pill navbar with scroll state transition, desktop dropdown, mobile overlay.
 * Uses WordPress registered menus with custom walkers.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$site   = anchor_get_site_config();
$cta    = anchor_get_site( 'default_cta', [ 'label' => 'Get Started', 'url' => '/contact/' ] );
$social = $site['social'] ?? [];
?>

<!-- Social bar (top-right, fades on scroll) -->
<?php if ( ! empty( $social ) ) : ?>
<div class="anchor-social-bar" id="anchor-social-bar">
    <?php foreach ( $social as $s ) : ?>
        <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $s['label'] ); ?>">
            <?php echo anchor_icon( $s['icon'], 14 ); ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Mobile overlay -->
<div class="anchor-mobile-overlay" id="anchor-mobile-overlay">
    <div class="anchor-mobile-overlay__spacer"></div>
    <div class="anchor-mobile-overlay__links">
        <?php
        if ( has_nav_menu( 'primary' ) ) {
            wp_nav_menu( [
                'theme_location' => 'primary',
                'container'      => false,
                'items_wrap'     => '%3$s',
                'walker'         => new Anchor_Mobile_Walker(),
                'depth'          => 0,
            ] );
        }
        ?>
    </div>
    <div class="anchor-mobile-overlay__bottom">
        <a href="<?php echo esc_url( $cta['url'] ); ?>" class="anchor-btn anchor-btn--primary anchor-mobile-overlay__cta">
            <?php echo esc_html( $cta['label'] ); ?>
        </a>
        <?php if ( ! empty( $social ) ) : ?>
        <div class="anchor-mobile-overlay__social">
            <?php foreach ( $social as $s ) : ?>
                <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer"
                   class="anchor-mobile-overlay__social-link"
                   aria-label="<?php echo esc_attr( $s['label'] ); ?>">
                    <?php echo anchor_icon( $s['icon'], 16 ); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main navbar -->
<nav class="anchor-nav" id="anchor-nav" role="navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'anchor-framework' ); ?>">
    <div class="anchor-nav__inner">
        <!-- Logo -->
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="anchor-nav__logo anchor-hover-lift">
            <?php
            $logo_light = anchor_get_site( 'logo.header_dark', '' );
            $logo_dark  = anchor_get_site( 'logo.header_light', '' );
            if ( $logo_light || $logo_dark ) :
                if ( $logo_light ) : ?>
                    <img src="<?php echo esc_url( anchor_resolve_media( $logo_light ) ); ?>" alt="<?php echo esc_attr( anchor_get_site( 'name' ) ); ?>" class="anchor-nav__logo-light anchor-nav__logo-img">
                <?php endif;
                if ( $logo_dark ) : ?>
                    <img src="<?php echo esc_url( anchor_resolve_media( $logo_dark ) ); ?>" alt="<?php echo esc_attr( anchor_get_site( 'name' ) ); ?>" class="anchor-nav__logo-dark anchor-nav__logo-img">
                <?php endif;
            else : ?>
                <span class="anchor-nav__logo-text">
                    <?php echo esc_html( anchor_get_site( 'name', 'Site' ) ); ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Desktop links (from WordPress menu) -->
        <div class="anchor-nav__links">
            <?php
            if ( has_nav_menu( 'primary' ) ) {
                wp_nav_menu( [
                    'theme_location' => 'primary',
                    'container'      => false,
                    'items_wrap'     => '%3$s',
                    'walker'         => new Anchor_Nav_Walker(),
                    'depth'          => 0,
                ] );
            }
            ?>
        </div>

        <!-- Right side: CTA + hamburger -->
        <div class="anchor-nav__actions">
            <a href="<?php echo esc_url( $cta['url'] ); ?>" class="anchor-nav__cta anchor-hide-mobile">
                <?php echo esc_html( $cta['label'] ); ?>
            </a>
            <button class="anchor-hamburger" id="anchor-hamburger" type="button" aria-label="<?php esc_attr_e( 'Toggle menu', 'anchor-framework' ); ?>">
                <span class="anchor-hamburger__bar"></span>
                <span class="anchor-hamburger__bar"></span>
                <span class="anchor-hamburger__bar"></span>
            </button>
        </div>
    </div>
</nav>
