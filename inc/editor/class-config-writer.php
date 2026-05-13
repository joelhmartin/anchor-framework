<?php
/**
 * Config Writer — safely writes PHP arrays back to config files.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Config_Writer {

    /**
     * Write a PHP array to a config file.
     *
     * @param string $file_path Absolute path to the config file.
     * @param array  $data      The array to write.
     * @param string $header    Optional file header comment.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public static function write( $file_path, array $data, $header = '' ) {
        // Backup existing file.
        if ( file_exists( $file_path ) ) {
            $backup = $file_path . '.' . date( 'Y-m-d-His' ) . '.bak';
            copy( $file_path, $backup );

            // Keep only last 10 backups.
            self::cleanup_backups( $file_path, 10 );
        }

        // Build file content.
        $output  = "<?php\n";
        if ( $header ) {
            $output .= "/**\n * {$header}\n */\n";
        }
        $output .= "return " . self::export_array( $data, 0 ) . ";\n";

        // Write atomically (write to temp, then rename).
        $tmp = $file_path . '.tmp';
        $written = file_put_contents( $tmp, $output );

        if ( false === $written ) {
            return new WP_Error( 'write_failed', 'Could not write to ' . $file_path );
        }

        if ( ! rename( $tmp, $file_path ) ) {
            unlink( $tmp );
            return new WP_Error( 'rename_failed', 'Could not finalize write to ' . $file_path );
        }

        // Clear any config cache.
        if ( function_exists( 'anchor_load_config' ) ) {
            // The config loader uses a static cache; we can't clear it directly.
            // But the next page load will read the fresh file.
        }

        return true;
    }

    /**
     * Export an array as a clean, readable PHP string.
     * Better formatting than var_export().
     */
    public static function export_array( $data, $depth = 0 ) {
        $indent  = str_repeat( '    ', $depth );
        $inner   = str_repeat( '    ', $depth + 1 );
        $is_list = array_keys( $data ) === range( 0, count( $data ) - 1 );

        $output = "[\n";

        foreach ( $data as $key => $value ) {
            $output .= $inner;

            if ( ! $is_list ) {
                $output .= self::export_value( $key ) . ' => ';
            }

            $output .= self::export_value( $value, $depth + 1 );
            $output .= ",\n";
        }

        $output .= $indent . ']';
        return $output;
    }

    /**
     * Export a single value as a PHP literal.
     */
    private static function export_value( $value, $depth = 0 ) {
        if ( is_null( $value ) ) {
            return 'null';
        }
        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }
        if ( is_int( $value ) || is_float( $value ) ) {
            return (string) $value;
        }
        if ( is_string( $value ) ) {
            // Use single quotes, escape internal single quotes.
            return "'" . str_replace( "'", "\\'", $value ) . "'";
        }
        if ( is_array( $value ) ) {
            if ( empty( $value ) ) {
                return '[]';
            }
            return self::export_array( $value, $depth );
        }
        return var_export( $value, true );
    }

    /**
     * Remove old backup files, keeping the most recent $keep.
     */
    private static function cleanup_backups( $file_path, $keep = 10 ) {
        $dir   = dirname( $file_path );
        $base  = basename( $file_path );
        $files = glob( $dir . '/' . $base . '.*.bak' );

        if ( $files && count( $files ) > $keep ) {
            sort( $files );
            $to_delete = array_slice( $files, 0, count( $files ) - $keep );
            foreach ( $to_delete as $f ) {
                unlink( $f );
            }
        }
    }
}
