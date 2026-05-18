<?php
/**
 * Page Sync Hooks — WP lifecycle hooks that drive Anchor_Editor_Page_Sync
 * from WP admin actions (slug rename, delete, trash).
 *
 * Rename:  post_updated fires after wp_update_post. If post_name changed and
 *          the post is Anchor-managed, renames the paired file. On failure the
 *          DB post_name is reverted and the error is logged.
 *
 * Delete:  before_delete_post captures the slug into a per-request cache.
 *          deleted_post (fires after the row is gone) reads the cache and
 *          deletes the file.
 *
 * Trash:   no hook — file stays on disk so WP untrash can bring it back live.
 *
 * @package Anchor_Editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Anchor_Editor_Page_Sync_Hooks {

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	/** @var self|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	// -----------------------------------------------------------------------
	// Per-request slug cache for the two-phase delete
	// -----------------------------------------------------------------------

	/** @var array<int,string> Maps post_id → slug captured in before_delete_post. */
	private $pending_deletes = array();

	// -----------------------------------------------------------------------
	// Hook registration
	// -----------------------------------------------------------------------

	private function init() {
		add_action( 'post_updated',      array( $this, 'on_post_updated' ),    10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_before_delete' ),  10, 2 );
		add_action( 'deleted_post',      array( $this, 'on_deleted_post' ),    10, 1 );
		// wp_trash_post intentionally omitted — soft state, file stays on disk.
	}

	// -----------------------------------------------------------------------
	// Handlers
	// -----------------------------------------------------------------------

	/**
	 * Fires after wp_update_post. Renames the paired file when post_name changes.
	 *
	 * Uses a static reentrancy guard to prevent the revert wp_update_post call
	 * from re-entering this handler and creating an infinite loop.
	 *
	 * NOTE: WP appends '__trashed' to post_name when trashing a post. We
	 * explicitly skip that internal WP slug-mangling so the file stays on disk
	 * while the post is in the trash (and can be restored).
	 *
	 * @param int     $post_id     The updated post ID.
	 * @param WP_Post $post_after  Post object after update.
	 * @param WP_Post $post_before Post object before update.
	 */
	public function on_post_updated( $post_id, $post_after, $post_before ) {
		// Reentrancy guard: the revert wp_update_post call must not recurse.
		static $reverting = false;
		if ( $reverting ) {
			return;
		}

		// Only act on managed posts.
		if ( ! Anchor_Editor_Page_Sync::is_anchor_managed( $post_id ) ) {
			return;
		}

		// Use the full hierarchical URI (e.g. 'services/web-design') so that
		// nested pages rename the correct file.  get_page_uri() may return ''
		// for unpublished posts; fall back to post_name in that edge case.
		$old_slug = get_page_uri( $post_before ) ?: $post_before->post_name;
		$new_slug = get_page_uri( $post_after )  ?: $post_after->post_name;

		// Nothing to do if slug is unchanged.
		if ( $old_slug === $new_slug ) {
			return;
		}

		// WP appends '__trashed' to post_name when moving to trash. Skip this
		// internal slug-mangling: the file must stay on disk for restore to work.
		if ( 'trash' === $post_after->post_status ) {
			return;
		}

		$result = Anchor_Editor_Page_Sync::rename( $old_slug, $new_slug );

		if ( is_wp_error( $result ) ) {
			// File rename failed — revert the DB post_name to keep DB and FS in sync.
			error_log(
				sprintf(
					'[Anchor_Editor_Page_Sync_Hooks] File rename failed for post %d (%s → %s): %s. Reverting DB slug.',
					$post_id,
					$old_slug,
					$new_slug,
					$result->get_error_message()
				)
			);

			// Revert only the leaf post_name (not the full URI).
			$reverting = true;
			wp_update_post(
				array(
					'ID'        => $post_id,
					'post_name' => $post_before->post_name,
				)
			);
			$reverting = false;
		}
	}

	/**
	 * Fires before a post is permanently deleted. Captures the slug so we can
	 * delete the file in on_deleted_post after the DB row is gone.
	 *
	 * @param int     $post_id The post ID about to be deleted.
	 * @param WP_Post $post    The post object.
	 */
	public function on_before_delete( $post_id, $post ) {
		if ( ! Anchor_Editor_Page_Sync::is_anchor_managed( $post_id ) ) {
			return;
		}
		// Capture the full hierarchical URI while the post (and its parents) still
		// exist in the DB.  Fall back to post_name if get_page_uri returns ''.
		$this->pending_deletes[ (int) $post_id ] = get_page_uri( $post ) ?: $post->post_name;
	}

	/**
	 * Fires after a post is permanently deleted. Uses the cached slug to delete
	 * the paired page-content file.
	 *
	 * @param int $post_id The deleted post ID.
	 */
	public function on_deleted_post( $post_id ) {
		$post_id = (int) $post_id;

		if ( ! isset( $this->pending_deletes[ $post_id ] ) ) {
			return;
		}

		$slug = $this->pending_deletes[ $post_id ];
		unset( $this->pending_deletes[ $post_id ] );

		$result = Anchor_Editor_Page_Sync::delete( $slug );

		if ( is_wp_error( $result ) ) {
			error_log(
				sprintf(
					'[Anchor_Editor_Page_Sync_Hooks] File delete failed for post %d (slug: %s): %s',
					$post_id,
					$slug,
					$result->get_error_message()
				)
			);
		}
	}
}
