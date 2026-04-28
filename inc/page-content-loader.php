<?php
/**
 * Page Content Loader
 *
 * Prefers a direct PHP/HTML page file at
 * `{child-theme}/page-content/{slug}.php` over the config-driven
 * section renderer. Lets page templates (page.php, front-page.php)
 * short-circuit when the file exists — otherwise they fall back to
 * the existing config-to-sections pipeline.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the absolute path to a page-content file for the given slug,
 * or '' if no such file exists.
 *
 * Looks in the child theme first, then the parent.
 *
 * @param string $slug Page slug (e.g. 'home', 'about', 'services/service-one').
 * @return string Absolute file path, or '' if not found.
 */
function anchor_get_page_content_path( $slug ) {
	$slug = (string) $slug;
	if ( '' === $slug ) {
		return '';
	}

	// Guard against traversal. Allow letters, digits, dash, underscore, slash.
	if ( ! preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug ) ) {
		return '';
	}

	$rel = '/page-content/' . $slug . '.php';

	$child_path = get_stylesheet_directory() . $rel;
	if ( file_exists( $child_path ) ) {
		return $child_path;
	}

	$parent_path = get_template_directory() . $rel;
	if ( $parent_path !== $child_path && file_exists( $parent_path ) ) {
		return $parent_path;
	}

	return '';
}

/**
 * Include the page-content file for the given slug if one exists.
 *
 * Returns true if a file was included (caller should stop rendering),
 * false otherwise (caller should run the config-based fallback).
 *
 * @param string $slug Page slug.
 * @return bool
 */
function anchor_load_page_content( $slug ) {
	$path = anchor_get_page_content_path( $slug );
	if ( '' === $path ) {
		return false;
	}

	include $path;
	return true;
}
