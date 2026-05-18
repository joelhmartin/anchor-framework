<?php
/**
 * Scaffold service — discover page-scaffold templates and create new
 * page-content files from them.
 *
 * Scaffolds live at: theme/templates/page-scaffolds/{key}.php
 * Created files land at: child-theme/page-content/{slug}.php
 *
 * Refuses to overwrite an existing page-content file (caller can use
 * write_file directly for overwrites).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Scaffold_Service {

	/** Pretty labels for the shipped scaffolds. */
	const LABELS = [
		'blank'         => 'Blank — minimal',
		'landing-page'  => 'Landing page — hero + features + CTA',
		'service-page'  => 'Service page — intro + sections + FAQ + CTA',
		'blog-post'     => 'Blog post — title + body + author',
	];

	/**
	 * List the scaffolds available on disk.
	 *
	 * @return array of [ 'key' => slug, 'label' => label ]
	 */
	public static function list_scaffolds() {
		$dir = trailingslashit( get_template_directory() ) . 'templates/page-scaffolds';
		$out = [];
		if ( ! is_dir( $dir ) ) return $out;
		$scan = scandir( $dir );
		if ( false === $scan ) return $out;
		foreach ( $scan as $name ) {
			if ( $name === '.' || $name === '..' ) continue;
			if ( substr( $name, -4 ) !== '.php' ) continue;
			$key = substr( $name, 0, -4 );
			$out[] = [
				'key'   => $key,
				'label' => self::LABELS[ $key ] ?? ucfirst( str_replace( '-', ' ', $key ) ),
			];
		}
		return $out;
	}

	/**
	 * Create a new page-content file from a scaffold. Refuses overwrite.
	 *
	 * @return array|WP_Error  [ 'success' => true, 'path', 'slug', 'template' ] on success.
	 */
	public static function create_from_scaffold( $template_key, $slug ) {
		$template_key = (string) $template_key;
		$slug         = (string) $slug;

		// Restrict template_key to filesystem-safe identifier characters before
		// using it in any path. Block traversal.
		if ( ! preg_match( '#^[A-Za-z0-9_\-]+$#', $template_key ) ) {
			return new WP_Error( 'invalid_template', 'Invalid template key.' );
		}

		if ( ! preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug ) ) {
			return new WP_Error( 'invalid_slug', 'Invalid slug. Use letters, digits, dash, underscore, slash.' );
		}
		$scaffolds_root = trailingslashit( get_template_directory() ) . 'templates/page-scaffolds';
		$scaffold_path  = $scaffolds_root . '/' . $template_key . '.php';

		// Defense-in-depth: realpath must resolve inside the scaffolds root.
		$real_root = realpath( $scaffolds_root );
		$real_path = realpath( $scaffold_path );
		if ( $real_root === false || $real_path === false || strpos( $real_path, $real_root . DIRECTORY_SEPARATOR ) !== 0 ) {
			return new WP_Error( 'unknown_template', "Unknown scaffold template: {$template_key}" );
		}
		if ( ! is_file( $real_path ) ) {
			return new WP_Error( 'unknown_template', "Unknown scaffold template: {$template_key}" );
		}

		// Refuse overwrite.
		$dest_abs = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
		if ( file_exists( $dest_abs ) ) {
			return new WP_Error( 'exists', "Page already exists at {$slug}" );
		}

		$contents = file_get_contents( $real_path );
		if ( false === $contents ) {
			return new WP_Error( 'read_failed', 'Could not read scaffold file.' );
		}

		if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
			return new WP_Error( 'writer_missing', 'File writer unavailable' );
		}
		$write = Anchor_Editor_File_Writer::write_page( $slug, $contents );
		if ( is_wp_error( $write ) ) {
			return $write;
		}

		// Pair with a WP page post.
		if ( class_exists( 'Anchor_Editor_Page_Sync' ) ) {
			$post_id = Anchor_Editor_Page_Sync::ensure_post( $slug );
			if ( is_wp_error( $post_id ) ) {
				// Roll back the file we just wrote so we don't leave an orphan.
				if ( file_exists( $dest_abs ) ) @unlink( $dest_abs );
				return $post_id;
			}
			return [
				'success'  => true,
				'path'     => $write['path'] ?? $dest_abs,
				'slug'     => $slug,
				'template' => $template_key,
				'post_id'  => $post_id,
				'edit_url' => Anchor_Editor_Page_Sync::get_edit_url( $post_id ),
			];
		}

		// Page_Sync unavailable (defensive — should not happen in production): fall back to legacy response.
		return [
			'success'  => true,
			'path'     => $write['path'] ?? $dest_abs,
			'slug'     => $slug,
			'template' => $template_key,
		];
	}
}
