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
}
