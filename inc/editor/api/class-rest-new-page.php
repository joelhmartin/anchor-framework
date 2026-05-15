<?php
/**
 * REST controller — POST /editor/new-page
 *
 * Used by the IDE's "+ New Page" modal. Delegates to the same
 * Anchor_Editor_Scaffold_Service::create_from_scaffold() the agent's
 * new_page_from_template tool uses.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_NewPage {

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
		register_rest_route( $this->namespace, '/editor/new-page', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create' ],
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		] );
	}

	public function create( $request ) {
		$params   = $request->get_json_params();
		$template = (string) ( $params['template'] ?? '' );
		$slug     = (string) ( $params['slug']     ?? '' );

		if ( $template === '' || $slug === '' ) {
			return new WP_REST_Response( [ 'error' => 'template and slug are required.' ], 400 );
		}
		if ( ! class_exists( 'Anchor_Editor_Scaffold_Service' ) ) {
			return new WP_REST_Response( [ 'error' => 'Scaffold service unavailable.' ], 500 );
		}

		$r = Anchor_Editor_Scaffold_Service::create_from_scaffold( $template, $slug );
		if ( is_wp_error( $r ) ) {
			$code   = $r->get_error_code();
			$status = ( $code === 'exists' ) ? 409 : 422;
			return new WP_REST_Response( [ 'error' => $r->get_error_message(), 'code' => $code ], $status );
		}
		return rest_ensure_response( $r );
	}
}
