<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Anchor_Editor_REST_PageFlags {
	private static $instance = null;
	private $namespace = 'anchor-assistant/v1';
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); self::$instance->init(); }
		return self::$instance;
	}
	private function init() { add_action( 'rest_api_init', [ $this, 'register_routes' ] ); }
	public function register_routes() {
		$auth = function() { return current_user_can( 'manage_options' ); };
		register_rest_route( $this->namespace, '/editor/page-flags/(?P<slug>[A-Za-z0-9_\-/]+)', [
			[ 'methods' => 'GET',  'callback' => [ $this, 'get_flags' ],  'permission_callback' => $auth ],
			[ 'methods' => 'POST', 'callback' => [ $this, 'post_flags' ], 'permission_callback' => $auth ],
		] );
	}
	public function get_flags( $request ) {
		$slug = $request['slug'];
		return rest_ensure_response( Anchor_Editor_Page_Flags::get_flags( $slug ) );
	}
	public function post_flags( $request ) {
		$slug   = $request['slug'];
		$params = $request->get_json_params();
		$flags  = [
			'no_header' => ! empty( $params['no_header'] ),
			'no_footer' => ! empty( $params['no_footer'] ),
		];
		$r = Anchor_Editor_Page_Flags::set_flags( $slug, $flags );
		if ( is_wp_error( $r ) ) {
			return new WP_REST_Response( [ 'error' => $r->get_error_message() ], 422 );
		}
		return rest_ensure_response( $r );
	}
}
