<?php
/**
 * Page-flag marker parser/writer for per-page header/footer toggles.
 *
 * Markers live at the very top of a page-content/{slug}.php file, one
 * per line, as: <?php /* anchor: no-header *_/ ?>  and/or no-footer.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Page_Flags {

	const MARKER_NO_HEADER = 'anchor: no-header';
	const MARKER_NO_FOOTER = 'anchor: no-footer';

	public static function get_flags( $slug ) {
		$path = self::path_for( $slug );
		if ( ! $path || ! is_file( $path ) ) {
			return [ 'no_header' => false, 'no_footer' => false ];
		}
		$contents = file_get_contents( $path );
		if ( $contents === false ) return [ 'no_header' => false, 'no_footer' => false ];
		$head = substr( $contents, 0, 300 );
		return [
			'no_header' => strpos( $head, self::MARKER_NO_HEADER ) !== false,
			'no_footer' => strpos( $head, self::MARKER_NO_FOOTER ) !== false,
		];
	}

	public static function set_flags( $slug, $flags ) {
		$path = self::path_for( $slug );
		if ( ! $path || ! is_file( $path ) ) {
			return new WP_Error( 'no_file', 'Page does not exist.' );
		}
		$contents = file_get_contents( $path );
		if ( $contents === false ) return new WP_Error( 'read_failed', 'Could not read file.' );

		// Strip any existing anchor marker lines from the top.
		$stripped = preg_replace(
			'/^(<\?php\s*\/\*\s*anchor:\s*no-(?:header|footer)\s*\*\/\s*\?>\s*\n)+/m',
			'',
			$contents,
			1
		);
		if ( $stripped === null ) $stripped = $contents;

		$prefix = '';
		if ( ! empty( $flags['no_header'] ) ) {
			$prefix .= "<?php /* " . self::MARKER_NO_HEADER . " */ ?>\n";
		}
		if ( ! empty( $flags['no_footer'] ) ) {
			$prefix .= "<?php /* " . self::MARKER_NO_FOOTER . " */ ?>\n";
		}
		$new = $prefix . $stripped;

		if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
			return new WP_Error( 'writer_missing', 'File writer unavailable' );
		}
		$write = Anchor_Editor_File_Writer::write_page( $slug, $new );
		if ( is_wp_error( $write ) ) return $write;
		return self::get_flags( $slug );
	}

	private static function path_for( $slug ) {
		if ( ! preg_match( '#^[A-Za-z0-9_\-/]+$#', $slug ) ) return null;
		return trailingslashit( get_stylesheet_directory() ) . 'page-content/' . $slug . '.php';
	}
}
