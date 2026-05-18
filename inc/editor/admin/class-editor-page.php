<?php
/**
 * Admin Page — registers the Anchor top-level menu and enqueues editor assets.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Page {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_menu() {
        add_menu_page(
            __( 'Anchor', 'anchor' ),
            __( 'Anchor', 'anchor' ),
            'manage_options',
            'anchor',
            [ $this, 'render_page' ],
            'dashicons-anchor',
            25
        );
        add_submenu_page(
            'anchor',
            __( 'Editor', 'anchor' ),
            __( 'Editor', 'anchor' ),
            'manage_options',
            'anchor',
            [ $this, 'render_page' ]
        );
        // Live Editor submenu removed: route is still registered by Anchor_Editor_IDE_Page::instance()
        // and accessible via ?page=anchor-live-editor for power users. The new Anchor editor screen
        // (Anchor_Editor_Screen) is what regular page editing flows through.
    }

    public function enqueue_assets( $hook ) {
        $is_main = ( 'toplevel_page_anchor' === $hook );

        // Note: the Live Editor screen (anchor_page_anchor-live-editor) has
        // its own enqueue handler in Anchor_Editor_IDE_Page. We deliberately
        // do not enqueue anything for that hook here.
        if ( ! $is_main ) return;

        wp_enqueue_style(
            'anchor-editor-admin',
            Anchor_Editor::url() . 'assets/editor/css/admin.css',
            [],
            Anchor_Editor::VERSION
        );

        wp_enqueue_script(
            'anchor-editor-admin',
            Anchor_Editor::url() . 'assets/editor/js/admin-app.js',
            [],
            Anchor_Editor::VERSION,
            true
        );

        wp_enqueue_style( 'dashicons' );

        wp_localize_script( 'anchor-editor-admin', 'apaData', [
            'restBase' => rest_url( 'anchor-assistant/v1/' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'pages'    => Anchor_Editor_Config_Manager::instance()->list_pages(),
            'sections' => array(),
        ] );
    }

    public function render_page() {
        include Anchor_Editor::path() . 'inc/editor/templates/admin-page.php';
    }

    public function render_live_editor() {
        include Anchor_Editor::path() . 'inc/editor/templates/live-editor.php';
    }
}
