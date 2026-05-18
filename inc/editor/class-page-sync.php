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
}
