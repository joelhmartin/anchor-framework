<?php
/**
 * IDE admin page — three-panel layout host.
 *
 * Registered as the "Live Editor" submenu under the Anchor top-level
 * menu (created by Anchor_Editor_Page). This class only enqueues assets
 * and renders a single <div id="anchor-ide"> host element; the rest is
 * client-side JS.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_IDE_Page {

	private static $instance = null;
	const SCREEN_ID = 'anchor_page_anchor-live-editor';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function init() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets( $hook ) {
		if ( $hook !== self::SCREEN_ID ) return;

		$tpl_dir = get_template_directory();
		$tpl_url = get_template_directory_uri();

		wp_enqueue_style(
			'anchor-editor-ide',
			$tpl_url . '/assets/editor/css/ide.css',
			[],
			file_exists( $tpl_dir . '/assets/editor/css/ide.css' ) ? filemtime( $tpl_dir . '/assets/editor/css/ide.css' ) : '1.0.0'
		);

		// Single bundled JS (esbuild output).
		$bundle_rel = '/dist/ide.min.js';
		if ( file_exists( $tpl_dir . $bundle_rel ) ) {
			wp_enqueue_script(
				'anchor-editor-ide',
				$tpl_url . $bundle_rel,
				[],
				filemtime( $tpl_dir . $bundle_rel ),
				true
			);
			wp_localize_script( 'anchor-editor-ide', 'anchorIDE', [
				'restBase' => rest_url( 'anchor-assistant/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'monacoVs' => 'https://cdn.jsdelivr.net/npm/monaco-editor@0.45.0/min/vs',
			] );
		}
	}

	/**
	 * Called by Anchor_Editor_Page::render_live_editor (from Phase 1).
	 * Renders the IDE host element.
	 */
	public function render_host() {
		echo '<div class="wrap">';
		echo '<div id="anchor-ide" data-loading="1"><p>Loading Anchor IDE…</p></div>';
		echo '</div>';
	}
}
