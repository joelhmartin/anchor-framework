<?php
/**
 * CSS file writer — atomic write + .bak rotation for child-theme CSS paths.
 *
 * Mirrors Anchor_Editor_File_Writer but for CSS roots:
 *   {stylesheet}/assets/css/*.css
 *   {stylesheet}/assets/css/pages/{slug}.css
 *
 * The realpath jail prevents traversal outside the stylesheet's assets/css/ tree.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_CSS_Writer {

	const BAK_KEEP = 10;

	/**
	 * Write a CSS file. Path must be relative to the stylesheet's assets/css/.
	 *
	 * @param string $relative_path e.g. "site.css" or "pages/pricing.css".
	 * @param string $contents      Raw CSS to write.
	 * @return array|WP_Error       [success, path, bak_path] on success.
	 */
	public static function write_css( $relative_path, $contents ) {
		$abs = self::resolve_path( $relative_path );
		if ( is_wp_error( $abs ) ) return $abs;

		// Backup existing.
		$bak_path = '';
		if ( file_exists( $abs ) ) {
			$bak_path = $abs . '.' . date( 'Y-m-d-His' ) . '.bak';
			if ( ! copy( $abs, $bak_path ) ) {
				return new WP_Error( 'backup_failed', 'Could not back up existing file.' );
			}
			self::cleanup_backups( $abs, self::BAK_KEEP );
		}

		// Atomic write via tempnam + rename in the same directory.
		$dir = dirname( $abs );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir_failed', 'Could not create directory: ' . $dir );
		}
		$tmp = tempnam( $dir, basename( $abs ) . '.tmp.' );
		if ( false === $tmp ) {
			return new WP_Error( 'tmp_failed', 'Could not create temp file.' );
		}
		if ( false === file_put_contents( $tmp, $contents, LOCK_EX ) ) {
			@unlink( $tmp );
			return new WP_Error( 'write_failed', 'Could not write temp file.' );
		}
		if ( ! rename( $tmp, $abs ) ) {
			@unlink( $tmp );
			return new WP_Error( 'rename_failed', 'Could not finalize write.' );
		}

		return [ 'success' => true, 'path' => $abs, 'bak_path' => $bak_path ];
	}

	/**
	 * Read a CSS file under the allowed roots.
	 *
	 * @return array|WP_Error [exists, contents, path]
	 */
	public static function read_css( $relative_path ) {
		$abs = self::resolve_path( $relative_path );
		if ( is_wp_error( $abs ) ) return $abs;

		if ( ! file_exists( $abs ) ) {
			return [ 'exists' => false, 'contents' => '', 'path' => $abs ];
		}
		$contents = file_get_contents( $abs );
		if ( false === $contents ) {
			return new WP_Error( 'read_failed', 'Could not read file.' );
		}
		return [ 'exists' => true, 'contents' => $contents, 'path' => $abs ];
	}

	/**
	 * Validate path is inside the child-theme assets/css/ jail.
	 */
	private static function resolve_path( $relative_path ) {
		if ( ! preg_match( '#^[A-Za-z0-9_\-/]+\.css$#', $relative_path ) ) {
			return new WP_Error( 'invalid_path', 'Invalid CSS path: ' . $relative_path );
		}
		$root = trailingslashit( get_stylesheet_directory() ) . 'assets/css';
		if ( ! is_dir( $root ) && ! wp_mkdir_p( $root ) ) {
			return new WP_Error( 'mkdir_root', 'Could not create assets/css root.' );
		}
		$root_real = realpath( $root );
		if ( false === $root_real ) {
			return new WP_Error( 'realpath_root', 'Could not resolve assets/css root.' );
		}
		$target = $root . '/' . $relative_path;
		$parent = dirname( $target );
		if ( ! is_dir( $parent ) && ! wp_mkdir_p( $parent ) ) {
			return new WP_Error( 'mkdir_parent', 'Could not create parent directory.' );
		}
		$parent_real = realpath( $parent );
		if ( false === $parent_real ) {
			return new WP_Error( 'realpath_parent', 'Could not resolve parent directory.' );
		}
		$root_with_sep = rtrim( $root_real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( $parent_real !== $root_real && strpos( $parent_real, $root_with_sep ) !== 0 ) {
			return new WP_Error( 'path_jail', 'Path escapes the assets/css/ jail.' );
		}
		return $parent_real . DIRECTORY_SEPARATOR . basename( $target );
	}

	private static function cleanup_backups( $abs, $keep ) {
		$pattern = $abs . '.*.bak';
		$files   = glob( $pattern ) ?: [];
		if ( count( $files ) <= $keep ) return;
		usort( $files, function( $a, $b ) { return filemtime( $b ) - filemtime( $a ); } );
		foreach ( array_slice( $files, $keep ) as $stale ) {
			@unlink( $stale );
		}
	}
}
