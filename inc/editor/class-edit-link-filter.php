<?php
/**
 * Redirects WP admin "Edit" links for _anchor_managed pages to the
 * Anchor editor screen. A ?anchor_bypass=1 query arg on the page URL
 * restores the default behavior (Gutenberg/classic) — used by the
 * Yoast bypass link in the Phase 3 sidebar.
 *
 * @package Anchor_Editor
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Edit_Link_Filter {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_filter( 'get_edit_post_link', [ $this, 'maybe_redirect' ], 10, 3 );
    }

    public function maybe_redirect( $link, $post_id, $context ) {
        if ( isset( $_GET['anchor_bypass'] ) ) return $link;
        if ( ! Anchor_Editor_Page_Sync::is_anchor_managed( $post_id ) ) return $link;

        $url = admin_url( 'admin.php?page=anchor-editor&post=' . (int) $post_id );
        return ( 'display' === $context ) ? esc_url( $url ) : $url;
    }
}
