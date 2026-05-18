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
     * Only load for admins who can manage options.
     */
    private function should_load() {
        return is_user_logged_in()
            && current_user_can( 'manage_options' )
            && ! is_admin()
            && Anchor_AI_Handler::instance()->is_configured();
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

        // Framework-owned editor assets live in the parent theme, so resolve
        // them against the template (parent) dir rather than the stylesheet
        // (child) dir. Per-page lookups below stay on get_stylesheet_directory().
        $tpl_dir = get_template_directory();
        $tpl_url = get_template_directory_uri();

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

            // Compute files-for-this-page.
            $files = [];
            $slug  = '';
            if ( function_exists( 'anchor_determine_page_slug' ) ) {
                $slug = (string) anchor_determine_page_slug();
            } elseif ( is_singular() ) {
                $slug = sanitize_title( get_post_field( 'post_name', get_queried_object_id() ) );
            }
            if ( $slug ) {
                $page_php = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
                if ( file_exists( $page_php ) ) {
                    $files[] = [
                        'label' => $slug . '.php',
                        'path'  => 'child-theme/page-content/' . $slug . '.php',
                    ];
                }
                $page_css = trailingslashit( get_stylesheet_directory() ) . 'assets/css/pages/' . $slug . '.css';
                if ( file_exists( $page_css ) ) {
                    $files[] = [
                        'label' => $slug . '.css',
                        'path'  => 'child-theme/assets/css/pages/' . $slug . '.css',
                    ];
                }
            }

            wp_localize_script( 'anchor-editor-pencil', 'anchorPencil', [
                'restBase'        => rest_url( 'anchor-assistant/v1/' ),
                'nonce'           => wp_create_nonce( 'wp_rest' ),
                'files'           => $files,
                'currentPageSlug' => $slug,
            ] );
        }
    }

    public function maybe_render() {
        if ( ! $this->should_load() ) return;
        // The JS builds the UI — nothing to render server-side.
    }
}
