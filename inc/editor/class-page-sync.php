<?php
/**
 * Page Sync — pairs page-content/{slug}.php files with WP page posts.
 *
 * Public surface:
 *   - is_anchor_managed( $post_or_id ): bool
 *   - find_post_by_slug( $slug ): int|null
 *   - derive_title( $slug ): string
 *   - ensure_post( $slug, $title = null ): int|WP_Error  (idempotent)
 *   - create( $slug, $contents, $title = null ): array|WP_Error
 *   - get_edit_url( $post_id ): string
 *   - rename( $old_slug, $new_slug ): true|WP_Error
 *   - delete( $slug ): true|WP_Error
 *   - trash( $slug ): true            (noop on file; soft state)
 *   - backfill(): array               { created: [], skipped: [] }
 *
 * @package Anchor_Editor
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anchor_Editor_Page_Sync {

	const META_KEY = '_anchor_managed';

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Check whether a post is Anchor-managed (has _anchor_managed = 1).
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return bool
	 */
	public static function is_anchor_managed( $post ) {
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		if ( ! $post_id ) {
			return false;
		}
		return (int) get_post_meta( $post_id, self::META_KEY, true ) === 1;
	}

	/**
	 * Find the WP page post ID for a given slug. Returns null if none found.
	 *
	 * @param string $slug Page slug (supports nested slugs via get_page_by_path).
	 * @return int|null
	 */
	public static function find_post_by_slug( $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		return $page ? (int) $page->ID : null;
	}

	/**
	 * Derive a human-readable title from a slug.
	 * Uses only the last path segment (basename) so 'services/web-design' → 'Web Design'.
	 *
	 * @param string $slug
	 * @return string
	 */
	public static function derive_title( $slug ) {
		$tail = basename( $slug );
		$tail = str_replace( array( '-', '_' ), ' ', $tail );
		return trim( ucwords( $tail ) );
	}

	/**
	 * Validate and normalise a slug (letters, digits, dash, underscore, forward slash only).
	 * Strips a leading slash. Returns '' if invalid.
	 *
	 * @param string $slug
	 * @return string
	 */
	private static function sanitize_slug( $slug ) {
		$slug = (string) $slug;
		if ( ! preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug ) ) {
			return '';
		}
		return ltrim( $slug, '/' );
	}

	// -----------------------------------------------------------------------
	// ensure_post — idempotent paired-post creation
	// -----------------------------------------------------------------------

	/**
	 * Return the ID of the managed WP page for $slug, creating it if absent.
	 *
	 * Returns WP_Error with code 'slug_collision' if a non-managed page already
	 * occupies the slug.
	 *
	 * @param string      $slug
	 * @param string|null $title  Human title; derived from slug if omitted.
	 * @return int|WP_Error
	 */
	public static function ensure_post( $slug, $title = null ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'bad_slug', 'Empty or invalid slug.' );
		}

		$existing_id = self::find_post_by_slug( $slug );
		if ( $existing_id ) {
			if ( self::is_anchor_managed( $existing_id ) ) {
				return $existing_id;
			}
			return new WP_Error(
				'slug_collision',
				"Slug '$slug' is already a non-managed page (ID $existing_id)."
			);
		}

		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title ? $title : self::derive_title( $slug ),
				'post_name'    => $slug,
				'post_content' => '',
				'meta_input'   => array( self::META_KEY => 1 ),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return (int) $id;
	}

	// -----------------------------------------------------------------------
	// create — atomic file + post creation
	// -----------------------------------------------------------------------

	/**
	 * Write a new page-content file and create its paired WP post atomically.
	 *
	 * If the post creation fails, the file is rolled back (unlinked).
	 *
	 * @param string      $slug
	 * @param string      $contents  Full PHP/HTML file contents.
	 * @param string|null $title     Human title; derived from slug if omitted.
	 * @return array|WP_Error  { post_id, slug, path, edit_url } on success.
	 */
	public static function create( $slug, $contents, $title = null ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'bad_slug', 'Empty or invalid slug.' );
		}

		if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
			return new WP_Error( 'no_writer', 'File writer unavailable.' );
		}

		// 1. Write file first (cheaper to rollback than post).
		$write = Anchor_Editor_File_Writer::write_page( $slug, $contents );
		if ( is_wp_error( $write ) ) {
			return $write;
		}
		$path = isset( $write['path'] ) ? $write['path'] : '';

		// 2. Create paired post; rollback file on failure.
		$post_id = self::ensure_post( $slug, $title );
		if ( is_wp_error( $post_id ) ) {
			if ( $path && file_exists( $path ) ) {
				@unlink( $path );
			}
			return $post_id;
		}

		return array(
			'post_id'  => $post_id,
			'slug'     => $slug,
			'path'     => $path,
			'edit_url' => self::get_edit_url( $post_id ),
		);
	}

	/**
	 * Return the Anchor editor URL for a given post ID.
	 * Phase 2 will register the actual screen; this URL is correct already.
	 *
	 * @param int $post_id
	 * @return string
	 */
	public static function get_edit_url( $post_id ) {
		return admin_url( 'admin.php?page=anchor-editor&post=' . (int) $post_id );
	}

	// -----------------------------------------------------------------------
	// rename — sync slug rename to filesystem
	// -----------------------------------------------------------------------

	/**
	 * Rename page-content/{old_slug}.php → page-content/{new_slug}.php.
	 *
	 * Does NOT modify the WP post (the hook class drives renames from post_updated).
	 * Creates the destination directory if it doesn't exist (nested slugs).
	 *
	 * @param string $old_slug
	 * @param string $new_slug
	 * @return true|WP_Error
	 */
	public static function rename( $old_slug, $new_slug ) {
		$old = self::sanitize_slug( $old_slug );
		$new = self::sanitize_slug( $new_slug );

		if ( '' === $old || '' === $new ) {
			return new WP_Error( 'bad_slug', 'Empty or invalid slug.' );
		}
		if ( $old === $new ) {
			return true;
		}

		$base     = trailingslashit( get_stylesheet_directory() ) . 'page-content/';
		$old_path = $base . $old . '.php';
		$new_path = $base . $new . '.php';

		if ( ! file_exists( $old_path ) ) {
			return new WP_Error( 'missing_file', "Source $old.php not found." );
		}
		if ( file_exists( $new_path ) ) {
			return new WP_Error( 'collision', "Destination $new.php already exists." );
		}

		$new_dir = dirname( $new_path );
		if ( ! is_dir( $new_dir ) && ! wp_mkdir_p( $new_dir ) ) {
			return new WP_Error( 'mkdir_failed', "Could not create directory $new_dir." );
		}

		if ( ! @rename( $old_path, $new_path ) ) {
			return new WP_Error( 'rename_failed', 'Could not rename file.' );
		}

		return true;
	}

	// -----------------------------------------------------------------------
	// delete + trash
	// -----------------------------------------------------------------------

	/**
	 * Permanently remove page-content/{slug}.php from the filesystem.
	 *
	 * Returns true if the file is already absent (idempotent).
	 *
	 * @param string $slug
	 * @return true|WP_Error
	 */
	public static function delete( $slug ) {
		$slug = self::sanitize_slug( $slug );
		if ( '' === $slug ) {
			return new WP_Error( 'bad_slug', 'Empty or invalid slug.' );
		}

		$path = trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
		if ( ! file_exists( $path ) ) {
			return true; // Already gone; idempotent.
		}

		if ( ! @unlink( $path ) ) {
			return new WP_Error( 'unlink_failed', "Could not delete $path." );
		}

		return true;
	}

	/**
	 * Noop on the filesystem — file remains so the page can be restored from WP trash.
	 *
	 * @param string $slug Unused; present for API symmetry.
	 * @return true
	 */
	public static function trash( $slug ) {
		// Soft state: file stays on disk. Restoring from WP trash brings the page back live.
		return true;
	}
}
