<?php
/**
 * Section: Contact Block
 *
 * Two-column layout: contact info on the left, form on the right.
 * Pulls phone/email/location/hours from site config via anchor_get_site().
 *
 * Data (via anchor_get_template_data):
 *   heading        (string|null) Section heading. Default 'Get in touch.'.
 *   text           (string|null) Description text.
 *   show_form      (bool)        Render built-in contact form. Default true.
 *   show_map       (bool)        Reserved for map embed. Default false.
 *   form_shortcode (string|null) Shortcode override for the form (e.g. Contact Form 7).
 *   fields         (array|null)  Reserved for custom field configuration.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$section = anchor_get_template_data();
$props   = ! empty( $section['props'] ) ? $section['props'] : [];

// Defaults.
$heading        = isset( $props['heading'] )        ? $props['heading']        : null;
$text           = isset( $props['text'] )           ? $props['text']           : null;
$show_form      = isset( $props['show_form'] )      ? (bool) $props['show_form'] : true;
$show_map       = ! empty( $props['show_map'] );
$form_shortcode = isset( $props['form_shortcode'] ) ? $props['form_shortcode'] : null;
$fields         = isset( $props['fields'] )         ? $props['fields']         : null;

// Contact info items from site config.
$contact_items = [];
if ( function_exists( 'anchor_get_site' ) ) {
    $contact_items = [
        [
            'icon'  => 'phone',
            'label' => 'Phone',
            'value' => anchor_get_site( 'phone' ),
            'href'  => anchor_get_site( 'phone_href' ),
        ],
        [
            'icon'  => 'mail',
            'label' => 'Email',
            'value' => anchor_get_site( 'email' ),
            'href'  => anchor_get_site( 'email_href' ),
        ],
        [
            'icon'  => 'map-pin',
            'label' => 'Location',
            'value' => anchor_get_site( 'location' ),
            'href'  => null,
        ],
        [
            'icon'  => 'clock',
            'label' => 'Hours',
            'value' => anchor_get_site( 'hours' ),
            'href'  => null,
        ],
    ];
}

// Social links from site config.
$social = function_exists( 'anchor_get_site' ) ? anchor_get_site( 'social', [] ) : [];
if ( ! is_array( $social ) ) {
    $social = [];
}
?>
<div class="anchor-section-pad">
    <div class="anchor-container anchor-contact__container">
        <div class="anchor-grid anchor-grid--2 anchor-contact__grid anchor-reveal-stagger">

            <!-- LEFT: Contact info -->
            <div>
                <?php if ( $heading ) : ?>
                    <h2 class="anchor-contact__heading">
                        <?php echo esc_html( $heading ); ?>
                    </h2>
                <?php else : ?>
                    <?php $site_name = function_exists( 'anchor_get_site' ) ? anchor_get_site( 'name', 'Us' ) : 'Us'; ?>
                    <h2 class="anchor-contact__heading">
                        Contact <span class="anchor-contact__name-accent"><?php echo esc_html( $site_name ); ?></span> Today
                    </h2>
                <?php endif; ?>

                <?php if ( $text ) : ?>
                    <p class="anchor-text-muted anchor-contact__text">
                        <?php echo anchor_esc_content( $text ); ?>
                    </p>
                <?php endif; ?>

                <!-- Contact items -->
                <?php if ( ! empty( $contact_items ) ) : ?>
                    <div class="anchor-contact__items">
                        <?php foreach ( $contact_items as $ci ) :
                            if ( empty( $ci['value'] ) ) {
                                continue;
                            }
                        ?>
                            <div class="anchor-contact__item">
                                <div class="anchor-icon-box">
                                    <?php echo anchor_icon( $ci['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </div>
                                <div>
                                    <span class="anchor-contact__label">
                                        <?php echo esc_html( $ci['label'] ); ?>
                                    </span>
                                    <?php if ( ! empty( $ci['href'] ) ) : ?>
                                        <a href="<?php echo esc_url( $ci['href'] ); ?>" class="anchor-contact__value">
                                            <?php echo esc_html( $ci['value'] ); ?>
                                        </a>
                                    <?php else : ?>
                                        <p class="anchor-contact__value">
                                            <?php echo esc_html( $ci['value'] ); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Social links -->
                <?php if ( ! empty( $social ) ) : ?>
                    <div class="anchor-contact__social">
                        <span class="anchor-contact__label">
                            <?php esc_html_e( 'Follow Us', 'anchor-framework' ); ?>
                        </span>
                        <div class="anchor-contact__social-links">
                            <?php foreach ( $social as $s ) :
                                $s_url   = isset( $s['url'] )   ? $s['url']   : '';
                                $s_label = isset( $s['label'] ) ? $s['label'] : '';
                                $s_icon  = isset( $s['icon'] )  ? $s['icon']  : '';

                                if ( empty( $s_url ) || empty( $s_icon ) ) {
                                    continue;
                                }
                            ?>
                                <a href="<?php echo esc_url( $s_url ); ?>" target="_blank" rel="noopener noreferrer"
                                   class="anchor-contact__social-link"
                                   aria-label="<?php echo esc_attr( $s_label ); ?>">
                                    <?php echo anchor_icon( $s_icon, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: Form -->
            <div class="anchor-card anchor-contact__form-card">
                <h3 class="anchor-contact__form-heading"><?php esc_html_e( 'Get in touch.', 'anchor-framework' ); ?></h3>
                <p class="anchor-contact__form-subtext"><?php esc_html_e( 'We will reach out within the next 24 to 48 hours.', 'anchor-framework' ); ?></p>

                <?php if ( $form_shortcode ) : ?>
                    <?php echo do_shortcode( $form_shortcode ); ?>
                <?php elseif ( $show_form ) : ?>
                    <!-- Built-in contact form -->
                    <form class="anchor-contact-form" method="post">

                        <!-- Full Name -->
                        <div class="anchor-form-group">
                            <input type="text" name="name" class="anchor-input" placeholder=" " required>
                            <label class="anchor-form-label"><?php esc_html_e( 'Full Name', 'anchor-framework' ); ?></label>
                        </div>

                        <!-- Email + Phone (2-col) -->
                        <div class="anchor-grid anchor-grid--2 anchor-contact-form__grid">
                            <div class="anchor-form-group">
                                <input type="email" name="email" class="anchor-input" placeholder=" " required>
                                <label class="anchor-form-label"><?php esc_html_e( 'Email Address', 'anchor-framework' ); ?></label>
                            </div>
                            <div class="anchor-form-group">
                                <input type="tel" name="phone" class="anchor-input" placeholder=" ">
                                <label class="anchor-form-label"><?php esc_html_e( 'Phone Number', 'anchor-framework' ); ?></label>
                            </div>
                        </div>

                        <!-- Service / inquiry selector -->
                        <div class="anchor-form-group">
                            <select name="inquiry" class="anchor-select">
                                <option value="" disabled selected><?php esc_html_e( "I'm reaching out about...", 'anchor-framework' ); ?></option>
                                <?php
                                if ( function_exists( 'anchor_get_data' ) ) {
                                    $contact_services = anchor_get_data( 'services' );
                                    foreach ( $contact_services as $cs ) {
                                        if ( ! empty( $cs['title'] ) ) {
                                            echo '<option>' . esc_html( $cs['title'] ) . '</option>';
                                        }
                                    }
                                }
                                ?>
                                <option><?php esc_html_e( 'General Inquiry', 'anchor-framework' ); ?></option>
                            </select>
                        </div>

                        <!-- Message -->
                        <div class="anchor-form-group">
                            <textarea name="message" class="anchor-textarea" rows="5" placeholder=" "></textarea>
                            <label class="anchor-form-label"><?php esc_html_e( 'Tell us about your project', 'anchor-framework' ); ?></label>
                        </div>

                        <p class="anchor-contact__disclaimer">
                            <?php esc_html_e( 'All inquiries are confidential. We typically respond within 24 hours.', 'anchor-framework' ); ?>
                        </p>

                        <button type="submit" class="anchor-btn anchor-btn--primary anchor-contact__submit">
                            <span><?php esc_html_e( 'Send Message', 'anchor-framework' ); ?></span>
                            <?php echo anchor_icon( 'send', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
