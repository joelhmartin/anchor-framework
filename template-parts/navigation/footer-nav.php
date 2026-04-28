<?php
/**
 * Footer Navigation
 * Dark navy footer with rounded top corners, brand info, nav columns, copyright.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$site   = anchor_get_site_config();
$social = $site['social'] ?? [];
$logo_footer = anchor_get_site( 'logo.footer', '' );
?>

<footer class="anchor-footer">
    <div class="anchor-section-pad">
        <!-- Top section -->
        <div class="anchor-footer__top">
            <div class="anchor-grid anchor-grid--5-7">
                <!-- Brand column (5/12) -->
                <div class="anchor-footer__brand">
                    <?php if ( $logo_footer ) : ?>
                        <img src="<?php echo esc_url( anchor_resolve_media( $logo_footer ) ); ?>" alt="<?php echo esc_attr( $site['name'] ?? '' ); ?>" class="anchor-footer__logo">
                    <?php else : ?>
                        <span class="anchor-footer__brand-name">
                            <?php echo esc_html( $site['name'] ?? 'Company' ); ?>
                        </span>
                    <?php endif; ?>

                    <p><?php echo esc_html( $site['description'] ?? '' ); ?></p>

                    <!-- Contact -->
                    <div class="anchor-footer__contact">
                        <?php if ( ! empty( $site['phone'] ) ) : ?>
                            <a href="<?php echo esc_url( $site['phone_href'] ?? '#' ); ?>">
                                <?php echo anchor_icon( 'phone', 13 ); ?>
                                <span><?php echo esc_html( $site['phone'] ); ?></span>
                            </a>
                        <?php endif; ?>
                        <?php if ( ! empty( $site['email'] ) ) : ?>
                            <a href="<?php echo esc_url( $site['email_href'] ?? '#' ); ?>">
                                <?php echo anchor_icon( 'mail', 13 ); ?>
                                <span><?php echo esc_html( $site['email'] ); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Social -->
                    <?php if ( ! empty( $social ) ) : ?>
                    <div class="anchor-footer__social">
                        <?php foreach ( $social as $s ) : ?>
                            <a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $s['label'] ); ?>">
                                <?php echo anchor_icon( $s['icon'], 14 ); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Nav columns (7/12) -->
                <div class="anchor-footer__nav-columns">
                    <?php
                    if ( has_nav_menu( 'footer' ) ) {
                        wp_nav_menu( [
                            'theme_location' => 'footer',
                            'container'      => false,
                            'items_wrap'     => '<div class="anchor-grid anchor-grid--2">%3$s</div>',
                            'walker'         => new Anchor_Footer_Walker(),
                            'depth'          => 2,
                        ] );
                    }
                    ?>
                </div>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="anchor-footer__bottom">
            <p class="anchor-footer__copyright">
                &copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $site['name'] ?? '' ); ?>. All rights reserved.
            </p>
            <div class="anchor-footer__legal">
                <span>Privacy Policy</span>
                <span>Terms of Service</span>
            </div>
        </div>
    </div>
</footer>
