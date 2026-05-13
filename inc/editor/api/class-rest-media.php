<?php
/**
 * REST API — Media endpoints for uploading and browsing the WP media library.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Media {

    private static $instance = null;

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
        $ns = 'anchor-assistant/v1';

        // List media library items.
        register_rest_route( $ns, '/media/library', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_media' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Upload a file.
        register_rest_route( $ns, '/media/upload', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'upload' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Get a single attachment's details.
        register_rest_route( $ns, '/media/library/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_attachment' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );
    }

    public function check_perm() {
        return current_user_can( 'upload_files' );
    }

    /**
     * List media library items with optional search.
     */
    public function list_media( $request ) {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => absint( $request->get_param( 'per_page' ) ?: 40 ),
            'paged'          => absint( $request->get_param( 'page' ) ?: 1 ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Search filter.
        $search = $request->get_param( 'search' );
        if ( $search ) {
            $args['s'] = sanitize_text_field( $search );
        }

        // Type filter (image, video, etc).
        $type = $request->get_param( 'type' );
        if ( 'image' === $type ) {
            $args['post_mime_type'] = 'image';
        }

        $query = new WP_Query( $args );
        $items = [];

        foreach ( $query->posts as $post ) {
            $items[] = $this->format_attachment( $post->ID );
        }

        return rest_ensure_response( [
            'items'       => $items,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
        ] );
    }

    /**
     * Upload a file to the media library.
     */
    public function upload( $request ) {
        $files = $request->get_file_params();

        if ( empty( $files['file'] ) ) {
            return new WP_REST_Response( [ 'error' => 'No file provided.' ], 400 );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        // Handle the upload.
        $attachment_id = media_handle_upload( 'file', 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_REST_Response( [ 'error' => $attachment_id->get_error_message() ], 500 );
        }

        // Optional: register in media.php config.
        $media_key = sanitize_text_field( $request->get_param( 'media_key' ) ?: '' );
        if ( $media_key ) {
            $cm    = Anchor_Editor_Config_Manager::instance();
            $media = $cm->get_media();
            $media[ $media_key ] = wp_get_attachment_url( $attachment_id );
            $cm->save_media( $media );
        }

        return rest_ensure_response( [
            'success'    => true,
            'attachment' => $this->format_attachment( $attachment_id ),
            'media_key'  => $media_key ?: null,
        ] );
    }

    /**
     * Get a single attachment's details.
     */
    public function get_attachment( $request ) {
        $id = absint( $request['id'] );
        if ( ! wp_attachment_is_image( $id ) && 'attachment' !== get_post_type( $id ) ) {
            return new WP_REST_Response( [ 'error' => 'Attachment not found.' ], 404 );
        }
        return rest_ensure_response( $this->format_attachment( $id ) );
    }

    /**
     * Format an attachment for API response.
     */
    private function format_attachment( $id ) {
        $url   = wp_get_attachment_url( $id );
        $meta  = wp_get_attachment_metadata( $id );
        $thumb = wp_get_attachment_image_url( $id, 'thumbnail' );
        $med   = wp_get_attachment_image_url( $id, 'medium' );

        return [
            'id'        => $id,
            'url'       => $url,
            'thumbnail' => $thumb ?: $url,
            'medium'    => $med ?: $url,
            'title'     => get_the_title( $id ),
            'filename'  => basename( get_attached_file( $id ) ),
            'mime_type' => get_post_mime_type( $id ),
            'width'     => $meta['width'] ?? null,
            'height'    => $meta['height'] ?? null,
            'date'      => get_the_date( 'Y-m-d', $id ),
        ];
    }
}
