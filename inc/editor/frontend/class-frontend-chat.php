<?php
/**
 * Frontend Chat — loads the AI chat widget on the frontend for logged-in admins.
 *
 * Detects the current page config slug so the AI has full context
 * about the page being viewed.
 *
 * @package Anchor_Editor
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Frontend_Chat {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue' ] );
        add_action( 'wp_footer', [ $this, 'maybe_render' ] );
    }

    /**
     * Only load for logged-in admins viewing a singular Anchor-managed page.
     */
    private function should_load() {
        if ( ! is_user_logged_in() ) return false;
        if ( ! current_user_can( 'manage_options' ) ) return false;
        if ( is_admin() ) return false;
        if ( ! Anchor_AI_Handler::instance()->is_configured() ) return false;
        if ( ! is_singular() ) return false;
        $post_id = get_queried_object_id();
        if ( ! $post_id ) return false;
        if ( ! class_exists( 'Anchor_Editor_Page_Sync' ) ) return false;
        return Anchor_Editor_Page_Sync::is_anchor_managed( $post_id );
    }

    /**
     * Detect the current page's config slug.
     */
    private function get_current_slug() {
        if ( function_exists( 'anchor_determine_page_slug' ) ) {
            return anchor_determine_page_slug();
        }
        return '';
    }

    public function maybe_enqueue() {
        if ( ! $this->should_load() ) return;

        $tpl_dir = get_stylesheet_directory();
        $tpl_url = get_stylesheet_directory_uri();

        wp_enqueue_style(
            'anchor-editor-pencil',
            $tpl_url . '/assets/editor/css/pencil.css',
            [],
            file_exists( $tpl_dir . '/assets/editor/css/pencil.css' ) ? filemtime( $tpl_dir . '/assets/editor/css/pencil.css' ) : '1.0.0'
        );

        $bundle_rel = '/dist/pencil.min.js';
        if ( file_exists( $tpl_dir . $bundle_rel ) ) {
            wp_enqueue_script(
                'anchor-editor-pencil',
                $tpl_url . $bundle_rel,
                [],
                filemtime( $tpl_dir . $bundle_rel ),
                true
            );

            $post_id  = get_queried_object_id();
            $post     = get_post( $post_id );
            $slug     = $post ? get_page_uri( $post ) : '';

            // Read current page flags (no-header / no-footer).
            // Anchor_Editor_Page_Flags::get_flags( $slug ) returns
            // [ 'no_header' => bool, 'no_footer' => bool ].
            $flags = [ 'no_header' => false, 'no_footer' => false ];
            if ( $slug && class_exists( 'Anchor_Editor_Page_Flags' ) ) {
                $read = Anchor_Editor_Page_Flags::get_flags( $slug );
                if ( is_array( $read ) ) {
                    $flags['no_header'] = ! empty( $read['no_header'] );
                    $flags['no_footer'] = ! empty( $read['no_footer'] );
                }
            }

            wp_localize_script( 'anchor-editor-pencil', 'anchorPencil', [
                'restBase' => rest_url( 'anchor-assistant/v1/' ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'postId'   => $post_id,
                'slug'     => $slug,
                'title'    => $post ? $post->post_title : '',
                'flags'    => $flags,
                'editUrl'  => admin_url( 'admin.php?page=anchor-editor&post=' . $post_id ),
            ] );
        }
    }

    public function maybe_render() {
        if ( ! $this->should_load() ) return;
        // The JS builds the UI — nothing to render server-side.
    }
}
