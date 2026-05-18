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
        // WP fires 'admin_page_{slug}' for hidden submenu pages.
        if ( strpos( (string) $hook, self::PAGE_SLUG ) === false ) {
            return;
        }

        $tpl_dir = get_template_directory();
        $tpl_url = get_template_directory_uri();

        // ── Styles ──────────────────────────────────────────────────────────
        $css_path = $tpl_dir . '/assets/editor/css/anchor-editor.css';
        wp_enqueue_style(
            'anchor-editor-app',
            $tpl_url . '/assets/editor/css/anchor-editor.css',
            [],
            file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0'
        );

        wp_enqueue_style(
            'anchor-editor-fa',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            [],
            null
        );

        // ── Script ──────────────────────────────────────────────────────────
        $bundle_rel = '/dist/anchor-editor.min.js';
        $bundle_abs = $tpl_dir . $bundle_rel;

        if ( ! file_exists( $bundle_abs ) ) {
            return;
        }

        wp_enqueue_script(
            'anchor-editor-app',
            $tpl_url . $bundle_rel,
            [],
            (string) filemtime( $bundle_abs ),
            true
        );

        // ── Localised config ────────────────────────────────────────────────
        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        $post    = $post_id ? get_post( $post_id ) : null;
        // Use get_page_uri() for hierarchical slug (e.g. services/web-design).
        $slug    = $post ? get_page_uri( $post ) : '';

        wp_enqueue_media(); // for the featured-image picker

        wp_localize_script( 'anchor-editor-app', 'anchorEditor', [
            'restBase'       => rest_url( 'anchor-assistant/v1/' ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'monacoVs'       => 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs',
            'postId'         => $post_id,
            'slug'           => $slug,
            'title'          => $post ? $post->post_title : '',
            'homeUrl'        => home_url( '/' ),
            'editUrlBypass'  => $post_id ? admin_url( 'post.php?post=' . $post_id . '&action=edit&anchor_bypass=1' ) : '',
        ] );
    }

    public function render() {
        $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || $post->post_type !== 'page' || ! Anchor_Editor_Page_Sync::is_anchor_managed( $post ) ) {
            echo '<div class="wrap"><h1>Anchor Editor</h1><p>That page isn\'t managed by Anchor.</p></div>';
            return;
        }
        $slug = get_page_uri( $post );
        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html( $post->post_title ) . '</h1> ';
        echo '<a href="' . esc_url( get_permalink( $post_id ) ) . '" class="page-title-action" target="_blank" rel="noopener noreferrer">View ↗</a>';
        echo '<div id="anchor-editor-app" data-post-id="' . esc_attr( $post_id ) . '" data-slug="' . esc_attr( $slug ) . '" data-loading="1">';
        echo '</div>';
        echo '</div>';
    }
}
