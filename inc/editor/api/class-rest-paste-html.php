<?php
if ( ! defined( 'ABSPATH' ) ) exit;
class Anchor_Editor_REST_PasteHTML {
	private static $instance = null;
	private $namespace = 'anchor-assistant/v1';
	public static function instance() {
		if ( null === self::$instance ) { self::$instance = new self(); self::$instance->init(); }
		return self::$instance;
	}
	private function init() { add_action( 'rest_api_init', [ $this, 'register_routes' ] ); }
	public function register_routes() {
		register_rest_route( $this->namespace, '/editor/paste-section', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'paste' ],
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		] );
	}
	public function paste( $request ) {
		$p = $request->get_json_params();
		$html  = isset( $p['html'] ) ? (string) $p['html'] : '';
		$target = isset( $p['target'] ) ? (string) $p['target'] : 'append';
		$slug   = isset( $p['slug'] ) ? (string) $p['slug'] : '';

		if ( $html === '' ) {
			return new WP_REST_Response( [ 'error' => 'Empty HTML' ], 400 );
		}
		$sanitized = wp_kses_post( $html );

		// Wrap in <section> if it doesn't already start with one.
		if ( ! preg_match( '/^\s*<section\b/i', $sanitized ) ) {
			$sanitized = "<section>\n" . $sanitized . "\n</section>\n";
		}

		if ( $target === 'append' ) {
			if ( $slug === '' ) return new WP_REST_Response( [ 'error' => 'slug required for append' ], 400 );
			$path = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
			if ( ! is_file( $path ) ) {
				return new WP_REST_Response( [ 'error' => 'Page does not exist' ], 404 );
			}
			$current = file_get_contents( $path );
			$new = rtrim( $current ) . "\n\n" . $sanitized;
			$r = Anchor_Editor_File_Writer::write_page( $slug, $new );
		} elseif ( $target === 'new-page' ) {
			if ( $slug === '' ) return new WP_REST_Response( [ 'error' => 'slug required for new-page' ], 400 );
			$dest = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
			if ( file_exists( $dest ) ) {
				return new WP_REST_Response( [ 'error' => 'Page already exists' ], 409 );
			}
			$contents = "<?php\n/* Created via Paste-section. */\n?>\n" . $sanitized;
			$r = Anchor_Editor_File_Writer::write_page( $slug, $contents );
		} else {
			return new WP_REST_Response( [ 'error' => 'Unknown target' ], 400 );
		}

		if ( is_wp_error( $r ) ) {
			return new WP_REST_Response( [ 'error' => $r->get_error_message() ], 500 );
		}
		return rest_ensure_response( [ 'path' => $r['path'] ?? '', 'slug' => $slug, 'target' => $target ] );
	}
}
