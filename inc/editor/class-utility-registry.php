<?php
/**
 * Utility-class registry — parses docs/utilities.md and caches the result
 * in a transient keyed by file mtime.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Utility_Registry {

	const TRANSIENT = 'anchor_editor_utilities_v1';
	const TTL       = HOUR_IN_SECONDS;

	/** @return array of [ 'class' => 'anchor-x', 'description' => '...', 'family' => '...' ] */
	public static function get_classes() {
		$path = trailingslashit( get_template_directory() ) . 'docs/utilities.md';
		if ( ! is_file( $path ) ) return [];
		$mtime = filemtime( $path );

		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && ( $cached['_mtime'] ?? 0 ) === $mtime ) {
			return $cached['classes'];
		}

		$classes = self::parse( $path );
		set_transient( self::TRANSIENT, [ '_mtime' => $mtime, 'classes' => $classes ], self::TTL );
		return $classes;
	}

	public static function flush_cache() { delete_transient( self::TRANSIENT ); }

	private static function parse( $path ) {
		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		if ( ! is_array( $lines ) ) return [];
		$out    = [];
		$family = 'General';
		foreach ( $lines as $line ) {
			if ( preg_match( '/^###\s+(.+)$/', $line, $m ) ) {
				$family = trim( $m[1] );
				continue;
			}
			// Match bullets like "- `.anchor-grid` — desc..."
			if ( preg_match( '/^-\s+`\.(anchor-[^`]+)`\s*(?:—|--)?\s*(.*)$/u', $line, $m ) ) {
				$class_token = $m[1];
				$description = trim( $m[2] );
				foreach ( self::expand_range( $class_token ) as $cls ) {
					$out[] = [
						'class'       => $cls,
						'description' => $description,
						'family'      => $family,
					];
				}
			}
		}
		return $out;
	}

	/** Expand range tokens like "anchor-grid-{1..6}" into individual classes. */
	private static function expand_range( $token ) {
		if ( preg_match( '/^(.+?)\{(\d+)\.\.(\d+)\}(.*)$/', $token, $m ) ) {
			$out = [];
			$start = (int) $m[2];
			$end   = (int) $m[3];
			for ( $i = $start; $i <= $end; $i++ ) {
				$out[] = $m[1] . $i . $m[4];
			}
			return $out;
		}
		return [ $token ];
	}
}
