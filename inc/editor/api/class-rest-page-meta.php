<?php
/**
 * REST: POST /anchor-assistant/v1/editor/page-meta
 *
 * Updates title, slug, and featured image for a _anchor_managed page post.
 * Slug change triggers the existing Page_Sync_Hooks pathway (file rename).
 *
 * Body: { post_id: int, title?: string, slug?: string, featured_id?: int|null }
 *
 * @package Anchor_Editor
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Page_Meta {

    private static $instance = null;
    private $namespace       = 'anchor-assistant/v1';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        register_rest_route( $this->namespace, '/editor/page-meta', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'update' ],
            'permission_callback' => function() { return current_user_can( 'edit_pages' ); },
        ] );
    }

    public function update( $request ) {
        $params  = $request->get_json_params();
        $post_id = isset( $params['post_id'] ) ? (int) $params['post_id'] : 0;

        if ( ! $post_id ) {
            return new WP_REST_Response( [ 'error' => 'post_id required' ], 400 );
        }
        if ( ! class_exists( 'Anchor_Editor_Page_Sync' ) || ! Anchor_Editor_Page_Sync::is_anchor_managed( $post_id ) ) {
            return new WP_REST_Response( [ 'error' => 'post is not an Anchor-managed page' ], 400 );
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_REST_Response( [ 'error' => 'forbidden' ], 403 );
        }

        $update = [ 'ID' => $post_id ];
        if ( array_key_exists( 'title', $params ) && is_string( $params['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $params['title'] );
        }
        if ( array_key_exists( 'slug', $params ) && is_string( $params['slug'] ) ) {
            $update['post_name'] = sanitize_title( $params['slug'] );
        }

        if ( count( $update ) > 1 ) {
            $r = wp_update_post( $update, true );
            if ( is_wp_error( $r ) ) {
                return new WP_REST_Response( [ 'error' => $r->get_error_message() ], 422 );
            }
        }

        if ( array_key_exists( 'featured_id', $params ) ) {
            $fid = (int) $params['featured_id'];
            if ( $fid > 0 ) {
                set_post_thumbnail( $post_id, $fid );
            } else {
                delete_post_thumbnail( $post_id );
            }
        }

        if ( array_key_exists( 'meta_description', $params ) && is_string( $params['meta_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field( $params['meta_description'] ) );
        }
        if ( array_key_exists( 'focus_keyphrase', $params ) && is_string( $params['focus_keyphrase'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $params['focus_keyphrase'] ) );
        }

        $post     = get_post( $post_id );
        $page_uri = get_page_uri( $post );
        return rest_ensure_response( [
            'post_id'          => $post_id,
            'title'            => $post->post_title,
            'slug'             => $post->post_name,
            'page_uri'         => $page_uri,
            'edit_url'         => admin_url( 'admin.php?page=anchor-editor&post=' . $post_id ),
            'permalink'        => get_permalink( $post_id ),
            'featured_id'      => (int) get_post_thumbnail_id( $post_id ),
            'meta_description' => (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true ),
            'focus_keyphrase'  => (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ),
        ] );
    }
}
