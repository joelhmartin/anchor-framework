<?php
/**
 * REST controller — GET /files/tree
 *
 * Returns the file tree under the allowed read/write roots so the IDE
 * file panel can render it. Read-only entries flagged for UI display.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Tree {

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
		register_rest_route( $this->namespace, '/files/tree', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_tree' ],
			'permission_callback' => function() { return current_user_can( 'manage_options' ); },
		] );
	}

	public function get_tree() {
		$child = trailingslashit( get_stylesheet_directory() );
		$theme = trailingslashit( get_template_directory() );

		$tree = [
			[
				'label'    => 'child-theme',
				'path'     => 'child-theme',
				'type'     => 'dir',
				'writable' => true,
				'children' => [
					$this->walk_dir( $child . 'page-content',  'child-theme/page-content',  true ),
					$this->walk_dir( $child . 'assets/css',    'child-theme/assets/css',    true ),
				],
			],
			[
				'label'    => 'theme',
				'path'     => 'theme',
				'type'     => 'dir',
				'writable' => false,
				'children' => [
					$this->walk_dir( $theme . 'template-parts', 'theme/template-parts', false ),
				],
			],
		];

		return rest_ensure_response( [
			'tree'           => $tree,
			'writable_roots' => [ 'child-theme/page-content', 'child-theme/assets/css' ],
			'readable_roots' => [ 'theme/template-parts' ],
			'scaffolds'      => class_exists( 'Anchor_Editor_Scaffold_Service' )
				? Anchor_Editor_Scaffold_Service::list_scaffolds()
				: [],
		] );
	}

	private function walk_dir( $abs, $logical, $writable ) {
		$node = [
			'label'    => basename( $logical ),
			'path'     => $logical,
			'type'     => 'dir',
			'writable' => $writable,
			'children' => [],
		];
		if ( ! is_dir( $abs ) ) {
			return $node;
		}
		$scan = scandir( $abs );
		if ( false === $scan ) {
			return $node;
		}
		foreach ( $scan as $name ) {
			if ( $name === '.' || $name === '..' ) continue;
			$full = $abs . DIRECTORY_SEPARATOR . $name;
			$rel  = $logical . '/' . $name;
			if ( is_dir( $full ) ) {
				$node['children'][] = $this->walk_dir( $full, $rel, $writable );
			} else {
				$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
				if ( ! in_array( $ext, [ 'php', 'css', 'html' ], true ) ) continue;
				$node['children'][] = [
					'label'    => $name,
					'path'     => $rel,
					'type'     => 'file',
					'writable' => $writable,
					'ext'      => $ext,
				];
			}
		}
		return $node;
	}
}
