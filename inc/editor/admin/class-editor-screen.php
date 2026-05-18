<?php
/**
 * Anchor Editor screen — the custom admin page that replaces Gutenberg for
 * _anchor_managed pages. Registered as a hidden submenu (no menu item) so
 * the WP admin chrome loads but no entry appears in the left menu.
 *
 * URL: admin.php?page=anchor-editor&post={post_id}
 *
 * Phase 3 fills in the bundle that turns the empty shell into a real editor.
 *
 * @package Anchor_Editor
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Screen {

    private static $instance = null;
    const PAGE_SLUG = 'anchor-editor';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'admin_menu', [ $this, 'register_hidden' ], 30 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_hidden() {
        add_submenu_page(
            null, // null parent = hidden from menu but routable
            'Anchor Editor',
            'Anchor Editor',
            'edit_pages',
            self::PAGE_SLUG,
            [ $this, 'render' ]
        );
    }

    public function enqueue_assets( $hook ) {
        // Phase 3 will populate this. For Phase 2, no assets are enqueued.
        if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) return;
    }

    public function render() {
        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || $post->post_type !== 'page' || ! Anchor_Editor_Page_Sync::is_anchor_managed( $post ) ) {
            echo '<div class="wrap"><h1>Anchor Editor</h1><p>That page isn\'t managed by Anchor.</p></div>';
            return;
        }
        $slug = $post->post_name;
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html( $post->post_title ) . '</h1> ';
        echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" class="page-title-action" target="_blank">View ↗</a>';
        echo '<div id="anchor-editor-app" data-post-id="' . esc_attr( $post_id ) . '" data-slug="' . esc_attr( $slug ) . '" data-loading="1">';
        echo '<p>Loading Anchor Editor…</p>';
        echo '</div>';
        echo '</div>';
    }
}
