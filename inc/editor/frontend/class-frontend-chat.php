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

        wp_enqueue_style(
            'anchor-editor-frontend-chat',
            Anchor_Editor::url() . 'assets/editor/css/frontend-chat.css',
            [],
            Anchor_Editor::VERSION
        );

        wp_enqueue_style(
            'anchor-editor-code-editor',
            Anchor_Editor::url() . 'assets/editor/css/code-editor.css',
            [ 'anchor-editor-frontend-chat' ],
            Anchor_Editor::VERSION
        );

        wp_enqueue_style( 'dashicons' );

        wp_enqueue_media();

        wp_enqueue_script(
            'anchor-editor-frontend-chat',
            Anchor_Editor::url() . 'assets/editor/js/frontend-chat.js',
            [ 'jquery', 'media-views' ],
            Anchor_Editor::VERSION,
            true
        );

        wp_enqueue_script(
            'anchor-editor-code-editor',
            Anchor_Editor::url() . 'assets/editor/js/code-editor.js',
            [ 'anchor-editor-frontend-chat' ],
            Anchor_Editor::VERSION,
            true
        );

        wp_enqueue_script(
            'anchor-editor-inline-edit',
            Anchor_Editor::url() . 'assets/editor/js/inline-edit.js',
            [ 'anchor-editor-frontend-chat' ],
            Anchor_Editor::VERSION,
            true
        );

        // Detect if we're on a single post/event (content-editable via WP REST API)
        $post_context = null;
        if ( is_singular( [ 'post', 'anchor_event' ] ) ) {
            $post = get_queried_object();
            $thumb_id  = get_post_thumbnail_id( $post->ID );
            $thumb_url = $thumb_id ? wp_get_attachment_url( $thumb_id ) : '';

            $post_context = [
                'id'              => $post->ID,
                'type'            => $post->post_type,
                'title'           => $post->post_title,
                'content'         => $post->post_content,
                'excerpt'         => $post->post_excerpt,
                'featured_image'  => $thumb_url,
                'featured_image_id' => $thumb_id ?: null,
                'edit_url'        => get_edit_post_link( $post->ID, 'raw' ),
            ];

            // Yoast SEO meta (if Yoast is active).
            if ( defined( 'WPSEO_VERSION' ) ) {
                $post_context['seo'] = [
                    'title'       => get_post_meta( $post->ID, '_yoast_wpseo_title', true ),
                    'description' => get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ),
                    'focus_kw'    => get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ),
                    'canonical'   => get_post_meta( $post->ID, '_yoast_wpseo_canonical', true ),
                    'og_title'    => get_post_meta( $post->ID, '_yoast_wpseo_opengraph-title', true ),
                    'og_desc'     => get_post_meta( $post->ID, '_yoast_wpseo_opengraph-description', true ),
                ];
            }
        }

        $slug                  = $this->get_current_slug() ?: '';
        $has_page_content_file = false;
        if ( $slug && function_exists( 'anchor_get_page_content_path' ) ) {
            $has_page_content_file = '' !== anchor_get_page_content_path( $slug );
        }

        wp_localize_script( 'anchor-editor-frontend-chat', 'apaFrontend', [
            'restBase'           => rest_url( 'anchor-assistant/v1/' ),
            'wpRestBase'         => rest_url( 'wp/v2/' ),
            'nonce'              => wp_create_nonce( 'wp_rest' ),
            'pageSlug'           => $slug,
            'pageTitle'          => wp_title( '', false ) ?: get_the_title(),
            'adminUrl'           => admin_url( 'admin.php?page=anchor-page-assistant' ),
            'sections'           => array(),
            'postContext'        => $post_context,
            'hasPageContentFile' => $has_page_content_file,
            'pageContentPath'    => $has_page_content_file ? ( 'page-content/' . $slug . '.php' ) : '',
        ] );
    }

    public function maybe_render() {
        if ( ! $this->should_load() ) return;
        // The JS builds the UI — nothing to render server-side.
    }
}
