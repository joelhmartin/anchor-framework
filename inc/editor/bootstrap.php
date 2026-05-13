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

require_once Anchor_Editor::path() . 'inc/editor/class-file-writer.php';
require_once Anchor_Editor::path() . 'inc/editor/class-config-writer.php';
require_once Anchor_Editor::path() . 'inc/ai/class-ai-handler.php';
require_once Anchor_Editor::path() . 'inc/ai/class-prompt-builder.php';

// REST controllers
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-ai.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-config.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-files.php';
require_once Anchor_Editor::path() . 'inc/editor/api/class-rest-history.php';
