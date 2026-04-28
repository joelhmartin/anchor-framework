<?php
/**
 * Component: Content Sidebar
 *
 * A sticky sidebar used on blog / events pages. Contains a brand info card
 * with social links and a contact info card with an optional mini form.
 *
 * Data (via anchor_get_template_data):
 *   title        (string) Contact card heading. Default 'Get in Touch'.
 *   subtitle     (string) Contact card subheading.
 *   show_form    (bool)   Show the mini contact form. Default true.
 *   show_contact (bool)   Show contact info items. Default true.
 *   show_social  (bool)   Show social links in brand card. Default true.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$title        = ! empty( $data['title'] )    ? $data['title']    : __( 'Get in Touch', 'anchor-framework' );
$subtitle     = isset( $data['subtitle'] )   ? $data['subtitle'] : '';
$show_form    = isset( $data['show_form'] )  ? (bool) $data['show_form']    : true;
$show_contact = isset( $data['show_contact'] ) ? (bool) $data['show_contact'] : true;
$show_social  = isset( $data['show_social'] )  ? (bool) $data['show_social']  : true;

// Pull site config data.
$description = anchor_get_site( 'description', '' );
$phone       = anchor_get_site( 'phone', '' );
$email       = anchor_get_site( 'email', '' );
$location    = anchor_get_site( 'location', '' );
$hours       = anchor_get_site( 'hours', '' );
$socials     = anchor_get_site( 'social', [] );
$logo_key    = anchor_get_site( 'logo.header_light', '' );
$logo        = $logo_key ? anchor_resolve_media( $logo_key ) : '';
$site_name   = anchor_get_site( 'name', get_bloginfo( 'name' ) );
?>
<aside class="anchor-content-sidebar">

    <!-- Brand Card -->
    <div class="anchor-card">

        <?php if ( $logo ) : ?>
            <img
                src="<?php echo esc_url( $logo ); ?>"
                alt="<?php echo esc_attr( $site_name ); ?>"
                class="anchor-content-sidebar__logo"
            >
        <?php elseif ( $site_name ) : ?>
            <h3 class="anchor-content-sidebar__site-name"><?php echo esc_html( $site_name ); ?></h3>
        <?php endif; ?>

        <?php if ( $description ) : ?>
            <p class="anchor-text-muted anchor-content-sidebar__desc"><?php echo anchor_esc_content( $description ); ?></p>
        <?php endif; ?>

        <?php if ( $show_social && ! empty( $socials ) && is_array( $socials ) ) : ?>
            <div class="anchor-content-sidebar__social">
                <?php foreach ( $socials as $social ) :
                    $platform = isset( $social['platform'] ) ? $social['platform'] : '';
                    $s_url    = isset( $social['url'] )      ? $social['url']      : '#';
                    $s_icon   = isset( $social['icon'] )     ? $social['icon']     : $platform;
                ?>
                    <a
                        href="<?php echo esc_url( $s_url ); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="<?php echo esc_attr( ucfirst( $platform ) ); ?>"
                        class="anchor-content-sidebar__social-link"
                    >
                        <?php echo anchor_icon( $s_icon, 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Contact Card -->
    <div class="anchor-card">

        <h3 class="anchor-content-sidebar__title"><?php echo esc_html( $title ); ?></h3>

        <?php if ( $subtitle ) : ?>
            <p class="anchor-text-muted anchor-content-sidebar__subtitle"><?php echo anchor_esc_content( $subtitle ); ?></p>
        <?php endif; ?>

        <?php if ( $show_contact ) : ?>
            <div class="anchor-contact-info anchor-content-sidebar__contact">

                <?php if ( $phone ) : ?>
                    <div class="anchor-contact-item">
                        <div class="anchor-icon-box anchor-icon-box--sm">
                            <?php echo anchor_icon( 'phone', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>" class="anchor-contact-item__text">
                            <?php echo esc_html( $phone ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ( $email ) : ?>
                    <div class="anchor-contact-item">
                        <div class="anchor-icon-box anchor-icon-box--sm">
                            <?php echo anchor_icon( 'mail', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <a href="mailto:<?php echo esc_attr( $email ); ?>" class="anchor-contact-item__text">
                            <?php echo esc_html( $email ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ( $location ) : ?>
                    <div class="anchor-contact-item">
                        <div class="anchor-icon-box anchor-icon-box--sm">
                            <?php echo anchor_icon( 'map-pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <span class="anchor-contact-item__text"><?php echo esc_html( $location ); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ( $hours ) : ?>
                    <div class="anchor-contact-item">
                        <div class="anchor-icon-box anchor-icon-box--sm">
                            <?php echo anchor_icon( 'clock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <span class="anchor-contact-item__text"><?php echo esc_html( $hours ); ?></span>
                    </div>
                <?php endif; ?>

            </div>
        <?php endif; ?>

        <?php if ( $show_form ) : ?>
            <div class="anchor-divider anchor-divider--mb"></div>

            <form class="anchor-sidebar-form" method="post" action="#">
                <div class="anchor-form-group anchor-form-group--spaced">
                    <input type="text" name="name" class="anchor-input" placeholder=" " required>
                    <label class="anchor-form-label"><?php esc_html_e( 'Your Name', 'anchor-framework' ); ?></label>
                </div>

                <div class="anchor-form-group anchor-form-group--spaced">
                    <input type="email" name="email" class="anchor-input" placeholder=" " required>
                    <label class="anchor-form-label"><?php esc_html_e( 'Email Address', 'anchor-framework' ); ?></label>
                </div>

                <div class="anchor-form-group anchor-form-group--spaced">
                    <input type="tel" name="phone" class="anchor-input" placeholder=" ">
                    <label class="anchor-form-label"><?php esc_html_e( 'Phone (optional)', 'anchor-framework' ); ?></label>
                </div>

                <div class="anchor-form-group anchor-form-group--spaced">
                    <textarea name="message" class="anchor-textarea" rows="3" placeholder=" " required></textarea>
                    <label class="anchor-form-label"><?php esc_html_e( 'Message', 'anchor-framework' ); ?></label>
                </div>

                <button type="submit" class="anchor-btn anchor-btn--primary anchor-btn--full">
                    <span><?php esc_html_e( 'Send Message', 'anchor-framework' ); ?></span>
                    <?php echo anchor_icon( 'send', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                </button>
            </form>
        <?php endif; ?>

    </div>

</aside>
