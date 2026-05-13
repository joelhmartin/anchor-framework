<?php
/**
 * REST API — Config endpoints for reading/writing page, site, data, globals, and media configs.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Config {

    private static $instance = null;
    private $namespace = 'anchor-assistant/v1';

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
        // Pages list.
        register_rest_route( $this->namespace, '/pages', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_pages' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ] );

        // Single page config.
        register_rest_route( $this->namespace, '/pages/(?P<slug>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_page' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_page' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );

        // Site config.
        register_rest_route( $this->namespace, '/site', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_site' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_site' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );

        // Data config.
        register_rest_route( $this->namespace, '/data', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_data' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_data' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );

        // Globals config.
        register_rest_route( $this->namespace, '/globals', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_globals' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_globals' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );

        // Media config.
        register_rest_route( $this->namespace, '/media', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_media' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_media' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ],
        ] );
    }

    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    // ----- Callbacks -----

    public function list_pages() {
        return rest_ensure_response( Anchor_Editor_Config_Manager::instance()->list_pages() );
    }

    public function get_page( $request ) {
        $result = Anchor_Editor_Config_Manager::instance()->get_page( $request['slug'] );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 404 );
        }
        return rest_ensure_response( $result );
    }

    public function save_page( $request ) {
        $data   = $request->get_json_params();
        $result = Anchor_Editor_Config_Manager::instance()->save_page( $request['slug'], $data );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        return rest_ensure_response( [ 'success' => true, 'slug' => $request['slug'] ] );
    }

    public function get_site() {
        return rest_ensure_response( Anchor_Editor_Config_Manager::instance()->get_site() );
    }

    public function save_site( $request ) {
        $result = Anchor_Editor_Config_Manager::instance()->save_site( $request->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        return rest_ensure_response( [ 'success' => true ] );
    }

    public function get_data() {
        return rest_ensure_response( Anchor_Editor_Config_Manager::instance()->get_data() );
    }

    public function save_data( $request ) {
        $result = Anchor_Editor_Config_Manager::instance()->save_data( $request->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        return rest_ensure_response( [ 'success' => true ] );
    }

    public function get_globals() {
        return rest_ensure_response( Anchor_Editor_Config_Manager::instance()->get_globals() );
    }

    public function save_globals( $request ) {
        $result = Anchor_Editor_Config_Manager::instance()->save_globals( $request->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        return rest_ensure_response( [ 'success' => true ] );
    }

    public function get_media() {
        return rest_ensure_response( Anchor_Editor_Config_Manager::instance()->get_media() );
    }

    public function save_media( $request ) {
        $result = Anchor_Editor_Config_Manager::instance()->save_media( $request->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }
        return rest_ensure_response( [ 'success' => true ] );
    }
}
