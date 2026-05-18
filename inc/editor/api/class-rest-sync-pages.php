<?php
/**
 * REST: POST /anchor-assistant/v1/editor/sync-pages
 *
 * Runs Anchor_Editor_Page_Sync::backfill() and returns the result.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_SyncPages {
    private static $instance = null;
    private $namespace       = 'anchor-assistant/v1';

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); self::$instance->init(); }
        return self::$instance;
    }
    private function init() { add_action( 'rest_api_init', [ $this, 'register_routes' ] ); }

    public function register_routes() {
        register_rest_route( $this->namespace, '/editor/sync-pages', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'run' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
    }

    public function run() {
        if ( ! class_exists( 'Anchor_Editor_Page_Sync' ) ) {
            return new WP_REST_Response( [ 'error' => 'Page sync service unavailable.' ], 500 );
        }
        return rest_ensure_response( Anchor_Editor_Page_Sync::backfill() );
    }
}
