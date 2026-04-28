<?php
/**
 * Anchor Framework — GitHub Updater
 *
 * Wires up plugin-update-checker so the WordPress admin sees update
 * notifications when a new release is published on GitHub.
 *
 * Repo: https://github.com/joelhmartin/anchor-framework
 *
 * For private repositories, define ANCHOR_FRAMEWORK_GH_TOKEN in wp-config.php
 * with a GitHub Personal Access Token that has `repo` scope.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if ( ! function_exists( 'anchor_framework_bootstrap_updater' ) ) {
	function anchor_framework_bootstrap_updater() {
		$theme_root = get_template_directory();

		$updater = PucFactory::buildUpdateChecker(
			'https://github.com/joelhmartin/anchor-framework/',
			$theme_root . '/style.css',
			'anchor-framework'
		);

		$updater->setBranch( 'main' );

		// Use full GitHub releases (zip attached to a tag) rather than tag tarballs.
		$vcs_api = $updater->getVcsApi();
		if ( method_exists( $vcs_api, 'enableReleaseAssets' ) ) {
			$vcs_api->enableReleaseAssets();
		}

		// Optional: authenticate against a private repo.
		if ( defined( 'ANCHOR_FRAMEWORK_GH_TOKEN' ) && ANCHOR_FRAMEWORK_GH_TOKEN ) {
			$updater->setAuthentication( ANCHOR_FRAMEWORK_GH_TOKEN );
		}
	}
}

anchor_framework_bootstrap_updater();
