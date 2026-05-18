<?php
/**
 * Anchor Editor — bootstrap for the consolidated editor (formerly anchor-page-assistant plugin).
 *
 * Loads all editor and AI module classes and registers hooks. Source of truth: spec
 * docs/superpowers/specs/2026-05-12-page-assistant-consolidation.md.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper for path / url / version. Replaces the plugin's APA_* constants.
 */
final class Anchor_Editor {

	const VERSION = '1.0.0';

	public static function path() {
		return trailingslashit( get_template_directory() );
	}

	public static function url() {
		return trailingslashit( get_template_directory_uri() );
	}
}

// Editor utilities
require_once Anchor_Editor::path() . 'inc/editor/class-file-writer.php';
require_once Anchor_Editor::path() . 'inc/editor/class-config-writer.php';

// New writers (Phase 4A)
require_once Anchor_Editor::path() . 'inc/editor/class-css-writer.php';

// Authoring flows (Phase 4B)
require_once Anchor_Editor::path() . 'inc/editor/class-scaffold-service.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-new-page.php';

// Productivity polish (Phase 4C)
require_once Anchor_Editor::path() . 'inc/editor/class-utility-registry.php';
require_once Anchor_Editor::path() . 'inc/editor/class-page-flags.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-utilities.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-page-flags.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-paste-html.php';

// WP-native editor (Phase 5)
require_once Anchor_Editor::path() . 'inc/editor/class-page-sync.php';
require_once Anchor_Editor::path() . 'inc/editor/class-page-sync-hooks.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-sync-pages.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-page-meta.php';
require_once Anchor_Editor::path() . 'inc/editor/class-edit-link-filter.php';

// AI module
require_once Anchor_Editor::path() . 'inc/ai/class-ai-handler.php';
require_once Anchor_Editor::path() . 'inc/ai/class-prompt-builder.php';

// Agent loop (Phase 4A)
require_once Anchor_Editor::path() . 'inc/ai/class-tool-registry.php';
require_once Anchor_Editor::path() . 'inc/ai/class-plan-builder.php';

// Editor service classes
require_once Anchor_Editor::path() . 'inc/editor/admin/class-config-manager.php';

// REST controllers
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-ai.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-config.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-files.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-history.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-media.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-menus.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-tree.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-agent.php';

// Admin pages
require_once Anchor_Editor::path() . 'inc/editor/admin/class-editor-page.php';
require_once Anchor_Editor::path() . 'inc/editor/admin/class-settings-page.php';
require_once Anchor_Editor::path() . 'inc/editor/admin/class-ide-page.php';
require_once Anchor_Editor::path() . 'inc/editor/admin/class-editor-screen.php';

// Frontend chat
require_once Anchor_Editor::path() . 'inc/editor/frontend/class-frontend-chat.php';

/**
 * Instantiate editor singletons. Each instance() call constructs the singleton
 * and registers its WP hooks (admin_menu, rest_api_init, wp_footer, etc.).
 */
function anchor_editor_init() {
	Anchor_Editor_Config_Manager::instance();
	Anchor_AI_Handler::instance();
	Anchor_Editor_Page::instance();
	Anchor_Editor_Settings::instance();
	Anchor_Editor_Frontend_Chat::instance();
	Anchor_Editor_REST_Config::instance();
	Anchor_Editor_REST_AI::instance();
	Anchor_Editor_REST_Menus::instance();
	Anchor_Editor_REST_Media::instance();
	Anchor_Editor_REST_History::instance();
	Anchor_Editor_REST_Files::instance();
	Anchor_Editor_REST_Tree::instance();
	Anchor_Editor_REST_Agent::instance();
	Anchor_Editor_REST_NewPage::instance();
	Anchor_Editor_REST_Utilities::instance();
	Anchor_Editor_REST_PageFlags::instance();
	Anchor_Editor_REST_PasteHTML::instance();
	Anchor_Editor_IDE_Page::instance();
	Anchor_Editor_Screen::instance();
	Anchor_Editor_Page_Sync_Hooks::instance();
	Anchor_Editor_REST_SyncPages::instance();
	Anchor_Editor_REST_Page_Meta::instance();
	Anchor_Editor_Edit_Link_Filter::instance();
	add_action( 'admin_init', [ 'Anchor_Editor_Page_Sync', 'maybe_backfill_once' ] );
}
add_action( 'after_setup_theme', 'anchor_editor_init', 20 );

// WP-CLI command (self-registers under WP_CLI guard — load outside anchor_editor_init).
require_once Anchor_Editor::path() . 'inc/editor/class-page-sync-cli.php';
