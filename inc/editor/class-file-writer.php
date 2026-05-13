<?php
/**
 * File Writer — safely writes raw PHP/HTML strings to page-content files.
 *
 * Unlike Anchor_Editor_Config_Writer (which serializes PHP arrays), this class
 * writes user/AI-authored PHP/HTML. It jails writes to
 * `{stylesheet_directory}/page-content/` via realpath, syntax-checks
 * the content with `php -l` before committing, and reuses the same
 * timestamped `.bak` backup ring for rollback.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anchor_Editor_File_Writer {

	/**
	 * Return the absolute directory inside which page-content files live.
	 */
	public static function page_content_dir() {
		return trailingslashit( get_stylesheet_directory() ) . 'page-content';
	}

	/**
	 * Validate a slug (letters, digits, dash, underscore, forward slash only).
	 * Forward slash is allowed for nested pages like 'services/service-one'.
	 */
	public static function is_valid_slug( $slug ) {
		if ( ! is_string( $slug ) || '' === $slug ) {
			return false;
		}
		return (bool) preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug );
	}

	/**
	 * Resolve the target path for a slug, guaranteeing it lives inside
	 * page_content_dir(). Returns '' if the resolved path escapes the jail.
	 *
	 * Uses realpath() on the parent directory (which always exists once we
	 * create it on demand) to defeat traversal attempts.
	 */
	public static function resolve_page_path( $slug ) {
		if ( ! self::is_valid_slug( $slug ) ) {
			return '';
		}

		$root = self::page_content_dir();
		if ( ! is_dir( $root ) ) {
			wp_mkdir_p( $root );
		}

		$root_real = realpath( $root );
		if ( false === $root_real ) {
			return '';
		}

		$target     = $root . '/' . $slug . '.php';
		$parent_dir = dirname( $target );

		if ( ! is_dir( $parent_dir ) ) {
			wp_mkdir_p( $parent_dir );
		}

		$parent_real = realpath( $parent_dir );
		if ( false === $parent_real ) {
			return '';
		}

		// Parent dir must be inside (or equal to) the page-content root.
		if ( $parent_real !== $root_real && 0 !== strpos( $parent_real, $root_real . DIRECTORY_SEPARATOR ) ) {
			return '';
		}

		return $parent_real . DIRECTORY_SEPARATOR . basename( $target );
	}

	/**
	 * Read the raw contents of a page-content file.
	 *
	 * @param string $slug Page slug.
	 * @return array { contents: string, exists: bool, path: string } or WP_Error.
	 */
	public static function read_page( $slug ) {
		$path = self::resolve_page_path( $slug );
		if ( '' === $path ) {
			return new WP_Error( 'invalid_slug', 'Invalid page slug.' );
		}

		if ( ! file_exists( $path ) ) {
			return [
				'exists'   => false,
				'path'     => $path,
				'contents' => '',
			];
		}

		$contents = file_get_contents( $path );
		if ( false === $contents ) {
			return new WP_Error( 'read_failed', 'Could not read ' . $path );
		}

		return [
			'exists'   => true,
			'path'     => $path,
			'contents' => $contents,
		];
	}

	/**
	 * Write raw PHP/HTML contents to a page-content file.
	 *
	 * Flow: jail check → write to .tmp → php -l → atomic rename → backup ring.
	 *
	 * @param string $slug     Page slug.
	 * @param string $contents Full file contents (including `<?php` if needed).
	 * @return array|WP_Error  { path: string } on success.
	 */
	public static function write_page( $slug, $contents ) {
		$path = self::resolve_page_path( $slug );
		if ( '' === $path ) {
			return new WP_Error( 'invalid_slug', 'Invalid page slug.' );
		}

		if ( ! is_string( $contents ) ) {
			return new WP_Error( 'invalid_contents', 'Contents must be a string.' );
		}

		// Write to temp file first.
		$tmp     = $path . '.tmp';
		$written = file_put_contents( $tmp, $contents );
		if ( false === $written ) {
			return new WP_Error( 'write_failed', 'Could not write temp file.' );
		}

		// Syntax-check the temp file. If php -l is unavailable (disabled
		// shell_exec), fall back to writing without a lint check — the
		// error will surface on the next page load. We prefer the check.
		$lint_error = self::lint_php( $tmp );
		if ( is_wp_error( $lint_error ) ) {
			@unlink( $tmp );
			return $lint_error;
		}

		// Backup existing file before overwriting.
		if ( file_exists( $path ) ) {
			$backup = $path . '.' . date( 'Y-m-d-His' ) . '.bak';
			@copy( $path, $backup );
			self::cleanup_backups( $path, 10 );
		}

		// Atomic rename.
		if ( ! rename( $tmp, $path ) ) {
			@unlink( $tmp );
			return new WP_Error( 'rename_failed', 'Could not finalize write.' );
		}

		return [ 'path' => $path ];
	}

	/**
	 * Run `php -l` against a file. Returns null on success, WP_Error on syntax
	 * failure or when the PHP binary is not reachable.
	 *
	 * If shell_exec is disabled, returns null (skip the check) rather than
	 * blocking a legitimate write; the site-level PHP parser is the final say.
	 */
	public static function lint_php( $path ) {
		if ( ! function_exists( 'shell_exec' ) ) {
			return null;
		}

		$php = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
		// FPM binaries don't accept -l; fall back to the CLI `php` on PATH.
		if ( false !== strpos( $php, 'fpm' ) ) {
			$php = 'php';
		}
		$cmd = escapeshellarg( $php ) . ' -l ' . escapeshellarg( $path ) . ' 2>&1';
		$out = @shell_exec( $cmd );

		if ( null === $out || '' === trim( $out ) ) {
			return null; // Couldn't run; skip.
		}

		if ( false !== stripos( $out, 'No syntax errors detected' ) ) {
			return null;
		}

		return new WP_Error( 'syntax_error', trim( $out ) );
	}

	/**
	 * Prune `.bak` backups, keeping the most recent $keep.
	 */
	private static function cleanup_backups( $file_path, $keep = 10 ) {
		$dir   = dirname( $file_path );
		$base  = basename( $file_path );
		$files = glob( $dir . '/' . $base . '.*.bak' );

		if ( $files && count( $files ) > $keep ) {
			sort( $files );
			$to_delete = array_slice( $files, 0, count( $files ) - $keep );
			foreach ( $to_delete as $f ) {
				@unlink( $f );
			}
		}
	}

	/**
	 * List available partials in template-parts/partials/ (parent theme).
	 *
	 * @return array of ['slug' => 'cta-band', 'label' => 'Cta Band', 'path' => '...']
	 */
	public static function list_partials() {
		$dir = trailingslashit( get_template_directory() ) . 'template-parts/partials';
		if ( ! is_dir( $dir ) ) {
			return [];
		}

		$files = glob( $dir . '/*.php' );
		if ( ! $files ) {
			return [];
		}

		$out = [];
		foreach ( $files as $f ) {
			$slug    = basename( $f, '.php' );
			$out[] = [
				'slug'  => $slug,
				'label' => ucwords( str_replace( '-', ' ', $slug ) ),
				'path'  => $f,
			];
		}
		return $out;
	}
}
