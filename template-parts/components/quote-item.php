<?php
/**
 * Component: Quote Item
 *
 * Renders a blockquote with optional author info and photo.
 *
 * Data (via anchor_get_template_data):
 *   quote  (string) Quote text.
 *   author (string) Author name.
 *   role   (string) Author role / title.
 *   photo  (string) Author photo URL.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Defaults.
$quote  = ! empty( $data['quote'] )  ? $data['quote']  : '';
$author = isset( $data['author'] )   ? $data['author'] : null;
$role   = isset( $data['role'] )     ? $data['role']   : null;
$photo  = isset( $data['photo'] )    ? $data['photo']  : null;

if ( empty( $quote ) ) {
    return;
}
?>
<blockquote class="anchor-quote">

    <p class="anchor-quote__text">&ldquo;<?php echo anchor_esc_content( $quote ); ?>&rdquo;</p>

    <?php if ( $author || $role || $photo ) : ?>
        <footer class="anchor-quote__footer">

            <?php if ( $photo ) : ?>
                <img
                    src="<?php echo esc_url( $photo ); ?>"
                    alt="<?php echo esc_attr( $author ); ?>"
                    loading="lazy"
                    class="anchor-quote__avatar"
                >
            <?php endif; ?>

            <div>
                <?php if ( $author ) : ?>
                    <cite class="anchor-quote__author"><?php echo esc_html( $author ); ?></cite>
                <?php endif; ?>
                <?php if ( $role ) : ?>
                    <span class="anchor-quote__role"><?php echo esc_html( $role ); ?></span>
                <?php endif; ?>
            </div>

        </footer>
    <?php endif; ?>

</blockquote>
