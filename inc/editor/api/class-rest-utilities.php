<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Anchor_Editor_REST_Utilities {
	private static $instance = null;
	private $namespace = 'anchor-assistant/v1';
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); self::$instance->init(); }
		return self::$instance;
	}
	private function init() { add_action( 'rest_api_init', [ $this, 'register_routes' ] ); }
	public function register_routes() {
		register_rest_route( $this->namespace, '/editor/utilities', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_utilities' ],
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		] );
	}
	public function get_utilities() {
		return rest_ensure_response( [ 'classes' => Anchor_Editor_Utility_Registry::get_classes() ] );
	}
}
