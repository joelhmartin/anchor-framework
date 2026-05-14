<?php
/**
 * Tool registry for the Anchor Editor agent loop.
 *
 * Each tool is callable from a plan step. Tools validate args, enforce
 * blast-radius rules (path roots), and return { success, result, error }.
 *
 * Allowed write roots (relative to stylesheet directory):
 *   - page-content/{slug}.php
 *   - assets/css/*.css
 *   - assets/css/pages/{slug}.css
 *
 * Allowed read roots (read-only):
 *   - {template_directory}/template-parts/*
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_AI_Tool_Registry {

	/**
	 * Tool catalogue for system-prompt injection.
	 *
	 * @return array  name => description string
	 */
	public static function get_tool_catalogue() {
		return [
			'list_dir'   => 'List a directory under the allowed roots. args: { path }. Returns { entries: [...] }.',
			'read_file'  => 'Read a file under the allowed read roots. args: { path }. Returns { contents }.',
			'write_file' => 'Write a PHP or CSS file under the allowed write roots. args: { path, contents }. Returns { bak_path }.',
			'lint_php'   => 'Run php -l against a PHP file. args: { path }. Returns { ok, error }.',
			'done'       => 'Terminal step. args: { summary }. No file-system effect.',
		];
	}

	/**
	 * Execute a tool call.
	 *
	 * @return array { success, result, error }
	 */
	public static function execute_tool( $name, $args ) {
		$args = is_array( $args ) ? $args : [];
		switch ( $name ) {
			case 'list_dir':   return self::tool_list_dir( $args );
			case 'read_file':  return self::tool_read_file( $args );
			case 'write_file': return self::tool_write_file( $args );
			case 'lint_php':   return self::tool_lint_php( $args );
			case 'done':       return self::tool_done( $args );
			default:
				return [ 'success' => false, 'error' => "Unknown tool: {$name}" ];
		}
	}

	/* ───────────── tool implementations ───────────── */

	private static function tool_list_dir( $args ) {
		$path = $args['path'] ?? '';
		$abs  = self::resolve_read_path( $path );
		if ( is_wp_error( $abs ) ) {
			return [ 'success' => false, 'error' => $abs->get_error_message() ];
		}
		if ( ! is_dir( $abs ) ) {
			return [ 'success' => false, 'error' => "Not a directory: {$path}" ];
		}
		$entries = [];
		foreach ( scandir( $abs ) as $name ) {
			if ( $name === '.' || $name === '..' ) continue;
			$full = $abs . DIRECTORY_SEPARATOR . $name;
			$entries[] = [
				'name' => $name,
				'type' => is_dir( $full ) ? 'dir' : 'file',
				'path' => rtrim( $path, '/' ) . '/' . $name,
			];
		}
		return [ 'success' => true, 'result' => [ 'entries' => $entries ] ];
	}

	private static function tool_read_file( $args ) {
		$path = $args['path'] ?? '';
		$abs  = self::resolve_read_path( $path );
		if ( is_wp_error( $abs ) ) {
			return [ 'success' => false, 'error' => $abs->get_error_message() ];
		}
		if ( ! is_file( $abs ) ) {
			return [ 'success' => false, 'error' => "Not a file: {$path}" ];
		}
		$contents = file_get_contents( $abs );
		if ( false === $contents ) {
			return [ 'success' => false, 'error' => 'Read failed' ];
		}
		return [ 'success' => true, 'result' => [ 'contents' => $contents, 'size' => strlen( $contents ) ] ];
	}

	private static function tool_write_file( $args ) {
		$path     = (string) ( $args['path']     ?? '' );
		$contents = (string) ( $args['contents'] ?? '' );

		// Identify writer by extension.
		$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		if ( $ext === 'php' ) {
			// PHP must be in page-content/.
			$slug = self::extract_page_content_slug( $path );
			if ( is_wp_error( $slug ) ) {
				return [ 'success' => false, 'error' => $slug->get_error_message() ];
			}
			if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
				return [ 'success' => false, 'error' => 'File writer unavailable' ];
			}
			$result = Anchor_Editor_File_Writer::write_page( $slug, $contents );
			if ( is_wp_error( $result ) ) {
				return [ 'success' => false, 'error' => $result->get_error_message() ];
			}
			return [ 'success' => true, 'result' => [ 'path' => $result['path'], 'bak_path' => $result['bak_path'] ?? '' ] ];
		}

		if ( $ext === 'css' ) {
			$css_rel = self::extract_css_relative_path( $path );
			if ( is_wp_error( $css_rel ) ) {
				return [ 'success' => false, 'error' => $css_rel->get_error_message() ];
			}
			$result = Anchor_Editor_CSS_Writer::write_css( $css_rel, $contents );
			if ( is_wp_error( $result ) ) {
				return [ 'success' => false, 'error' => $result->get_error_message() ];
			}
			return [ 'success' => true, 'result' => [ 'path' => $result['path'], 'bak_path' => $result['bak_path'] ] ];
		}

		return [ 'success' => false, 'error' => "Unsupported extension for write: .{$ext}" ];
	}

	private static function tool_lint_php( $args ) {
		$path = $args['path'] ?? '';
		$slug = self::extract_page_content_slug( $path );
		if ( is_wp_error( $slug ) ) {
			return [ 'success' => false, 'error' => $slug->get_error_message() ];
		}
		$abs = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
		if ( ! is_file( $abs ) ) {
			return [ 'success' => false, 'error' => "File does not exist: {$path}" ];
		}
		if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
			return [ 'success' => false, 'error' => 'Linter unavailable' ];
		}
		$lint = Anchor_Editor_File_Writer::lint_php( $abs );
		if ( is_wp_error( $lint ) ) {
			return [ 'success' => true, 'result' => [ 'ok' => false, 'error' => $lint->get_error_message() ] ];
		}
		return [ 'success' => true, 'result' => [ 'ok' => true, 'error' => null ] ];
	}

	private static function tool_done( $args ) {
		$summary = (string) ( $args['summary'] ?? '' );
		return [ 'success' => true, 'result' => [ 'summary' => $summary ] ];
	}

	/* ───────────── path validation helpers ───────────── */

	/**
	 * Resolve a "logical" path (e.g. "child-theme/page-content/home") to an
	 * absolute path inside one of the allowed READ roots.
	 *
	 * Logical roots accepted:
	 *   - child-theme/page-content/
	 *   - child-theme/assets/css/
	 *   - theme/template-parts/
	 */
	private static function resolve_read_path( $logical ) {
		$logical = ltrim( (string) $logical, '/' );
		$child   = trailingslashit( get_stylesheet_directory() );
		$theme   = trailingslashit( get_template_directory() );

		$roots = [
			'child-theme/page-content' => $child . 'page-content',
			'child-theme/assets/css'   => $child . 'assets/css',
			'theme/template-parts'     => $theme . 'template-parts',
		];

		foreach ( $roots as $prefix => $abs_root ) {
			if ( $logical === $prefix ) {
				return realpath( $abs_root ) ?: $abs_root;
			}
			if ( strpos( $logical, $prefix . '/' ) === 0 ) {
				$rest = substr( $logical, strlen( $prefix ) + 1 );
				if ( strpos( $rest, '..' ) !== false ) {
					return new WP_Error( 'path_traversal', 'Path contains ..' );
				}
				$target = $abs_root . '/' . $rest;
				$root_real = realpath( $abs_root );
				$target_real = realpath( $target );
				if ( $target_real === false ) {
					// Allow non-existing files for read (returns "not found" later).
					$parent = realpath( dirname( $target ) );
					if ( $parent === false || strpos( $parent, $root_real ) !== 0 ) {
						return new WP_Error( 'path_jail', 'Path outside allowed roots' );
					}
					return $target;
				}
				if ( strpos( $target_real, $root_real ) !== 0 ) {
					return new WP_Error( 'path_jail', 'Path outside allowed roots' );
				}
				return $target_real;
			}
		}
		return new WP_Error( 'path_root', "Path not under any allowed root: {$logical}" );
	}

	/**
	 * Pull the {slug} from a child-theme/page-content/{slug}.php path.
	 */
	private static function extract_page_content_slug( $logical ) {
		$logical = ltrim( (string) $logical, '/' );
		$prefix  = 'child-theme/page-content/';
		if ( strpos( $logical, $prefix ) !== 0 ) {
			return new WP_Error( 'path_root', 'PHP write must target child-theme/page-content/' );
		}
		$rest = substr( $logical, strlen( $prefix ) );
		if ( ! preg_match( '#^([A-Za-z0-9_\-/]+)\.php$#', $rest, $m ) ) {
			return new WP_Error( 'invalid_path', 'Invalid page-content path' );
		}
		return $m[1];
	}

	/**
	 * Pull the relative path inside child-theme/assets/css/ from a logical path.
	 */
	private static function extract_css_relative_path( $logical ) {
		$logical = ltrim( (string) $logical, '/' );
		$prefix  = 'child-theme/assets/css/';
		if ( strpos( $logical, $prefix ) !== 0 ) {
			return new WP_Error( 'path_root', 'CSS write must target child-theme/assets/css/' );
		}
		return substr( $logical, strlen( $prefix ) );
	}
}
