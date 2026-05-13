<?php
/**
 * REST API — Direct-PHP page file read/write endpoints.
 *
 * Exposes:
 *   GET  /files/page/{slug}   → raw contents of page-content/{slug}.php
 *   POST /files/page/{slug}   → write raw contents (validated by Anchor_Editor_File_Writer)
 *   GET  /files/partials      → list of available partials for AI prompts
 *
 * Authentication: manage_options (same as the rest of the editor).
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anchor_Editor_REST_Files {

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
		// Page file: GET + POST at a nested slug (allow letters, digits, dash,
		// underscore, and forward slash for nested pages).
		register_rest_route(
			$this->namespace,
			'/files/page/(?P<slug>[A-Za-z0-9_\-/]+)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_page_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_page_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// List partials the AI can reference.
		register_rest_route(
			$this->namespace,
			'/files/partials',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_partials' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		// CSS file: GET + POST (child-theme allowlist).
		register_rest_route(
			$this->namespace,
			'/files/css/(?P<filename>[a-z0-9_-]+\.css)',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_css_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'save_css_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
		);

		// Per-page CSS files.
		register_rest_route(
			$this->namespace,
			'/files/page-css/(?P<slug>[a-z0-9_-]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_page_css_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [ 'slug' => [ 'required' => true, 'sanitize_callback' => 'sanitize_file_name' ] ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_page_css_file' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'slug'     => [ 'required' => true, 'sanitize_callback' => 'sanitize_file_name' ],
						'contents' => [ 'required' => true, 'type' => 'string' ],
					],
				],
			]
		);
	}

	public function check_permission() {
		return current_user_can( 'manage_options' );
	}

	public function get_page_file( $request ) {
		$slug   = (string) $request['slug'];
		$result = Anchor_Editor_File_Writer::read_page( $slug );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				[ 'error' => $result->get_error_message() ],
				400
			);
		}

		return rest_ensure_response( $result );
	}

	public function save_page_file( $request ) {
		$slug   = (string) $request['slug'];
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = [];
		}

		$contents = isset( $params['contents'] ) ? (string) $params['contents'] : null;
		if ( null === $contents ) {
			return new WP_REST_Response( [ 'error' => 'Missing "contents".' ], 400 );
		}

		$result = Anchor_Editor_File_Writer::write_page( $slug, $contents );
		if ( is_wp_error( $result ) ) {
			$code   = $result->get_error_code();
			$status = 'syntax_error' === $code ? 422 : 400;
			return new WP_REST_Response(
				[
					'error' => $result->get_error_message(),
					'code'  => $code,
				],
				$status
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'slug'    => $slug,
				'path'    => $result['path'],
			]
		);
	}

	public function list_partials() {
		return rest_ensure_response( Anchor_Editor_File_Writer::list_partials() );
	}

	// ─── CSS file ─────────────────────────────────────────────────────

	public static function get_css_allowlist() {
		return [ 'client-overrides.css', 'client-tokens.css' ];
	}

	public function get_css_file( $request ) {
		$filename = (string) $request['filename'];
		if ( ! in_array( $filename, self::get_css_allowlist(), true ) ) {
			return new WP_REST_Response( [ 'error' => 'File not in allowlist.' ], 403 );
		}

		$path = trailingslashit( get_stylesheet_directory() ) . 'assets/css/' . $filename;

		if ( ! file_exists( $path ) ) {
			return new WP_REST_Response( [ 'exists' => false, 'contents' => '', 'path' => str_replace( ABSPATH, '', $path ) ], 200 );
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_REST_Response( [ 'error' => 'Could not read file.' ], 500 );
		}

		return rest_ensure_response( [
			'exists'   => true,
			'contents' => $contents,
			'path'     => str_replace( ABSPATH, '', $path ),
		] );
	}

	public function save_css_file( $request ) {
		$filename = (string) $request['filename'];
		if ( ! in_array( $filename, self::get_css_allowlist(), true ) ) {
			return new WP_REST_Response( [ 'error' => 'File not in allowlist.' ], 403 );
		}

		$params   = $request->get_json_params();
		$contents = isset( $params['contents'] ) ? (string) $params['contents'] : null;
		if ( null === $contents ) {
			return new WP_REST_Response( [ 'error' => 'Missing "contents".' ], 400 );
		}

		$path    = trailingslashit( get_stylesheet_directory() ) . 'assets/css/' . $filename;
		$written = file_put_contents( $path, $contents );
		if ( false === $written ) {
			return new WP_REST_Response( [ 'error' => 'Could not write CSS file.' ], 500 );
		}
		return rest_ensure_response( [ 'success' => true, 'path' => str_replace( ABSPATH, '', $path ) ] );
	}

	// ─── Per-page CSS files ────────────────────────────────────────────

	public function get_page_css_file( $request ) {
		$slug = $request->get_param( 'slug' );
		$path = trailingslashit( get_stylesheet_directory() ) . 'assets/css/pages/' . $slug . '.css';

		if ( ! file_exists( $path ) ) {
			return rest_ensure_response( [
				'exists'   => false,
				'contents' => '',
				'path'     => str_replace( ABSPATH, '', $path ),
			] );
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_REST_Response( [ 'error' => 'Could not read file.' ], 500 );
		}

		return rest_ensure_response( [
			'exists'   => true,
			'contents' => $contents,
			'path'     => str_replace( ABSPATH, '', $path ),
		] );
	}

	public function save_page_css_file( $request ) {
		$slug     = $request->get_param( 'slug' );
		$contents = $request->get_param( 'contents' );
		$dir      = trailingslashit( get_stylesheet_directory() ) . 'assets/css/pages';
		$path     = $dir . '/' . $slug . '.css';

		if ( null === $contents ) {
			return new WP_REST_Response( [ 'error' => 'Missing "contents".' ], 400 );
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_REST_Response( [ 'error' => 'Could not create directory.' ], 500 );
		}

		$result = file_put_contents( $path, $contents );
		if ( false === $result ) {
			return new WP_REST_Response( [ 'error' => 'Could not write CSS file.' ], 500 );
		}

		return rest_ensure_response( [ 'success' => true, 'path' => str_replace( ABSPATH, '', $path ) ] );
	}
}
