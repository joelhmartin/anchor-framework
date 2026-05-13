<?php
/**
 * Config Manager — reads and writes child theme config files.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_Config_Manager {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the child theme config directory path.
     */
    public function get_config_dir() {
        return get_stylesheet_directory() . '/config';
    }

    /**
     * Check if the config directory is writable.
     */
    public function is_writable() {
        return is_writable( $this->get_config_dir() );
    }

    // ----- PAGE CONFIGS -----

    /**
     * List all available page config slugs.
     */
    public function list_pages() {
        $dir   = $this->get_config_dir() . '/pages';
        $pages = [];

        if ( ! is_dir( $dir ) ) return $pages;

        foreach ( glob( $dir . '/*.php' ) as $file ) {
            $slug  = basename( $file, '.php' );
            $pages[] = [
                'slug'     => $slug,
                'file'     => $file,
                'modified' => filemtime( $file ),
            ];
        }
        return $pages;
    }

    /**
     * Read a page config by slug.
     */
    public function get_page( $slug ) {
        $slug = sanitize_file_name( $slug );
        $file = $this->get_config_dir() . '/pages/' . $slug . '.php';

        if ( ! file_exists( $file ) ) {
            return new WP_Error( 'not_found', "Page config '{$slug}' not found." );
        }

        $config = include $file;
        return is_array( $config ) ? $config : [];
    }

    /**
     * Write a page config by slug.
     */
    public function save_page( $slug, array $config ) {
        $slug = sanitize_file_name( $slug );
        $file = $this->get_config_dir() . '/pages/' . $slug . '.php';

        // Validate sections if present.
        if ( ! empty( $config['sections'] ) && function_exists( 'anchor_validate_section' ) ) {
            foreach ( $config['sections'] as $section ) {
                if ( is_array( $section ) && ! anchor_validate_section( $section ) ) {
                    return new WP_Error( 'invalid_section', 'One or more sections failed validation.' );
                }
            }
        }

        return Anchor_Editor_Config_Writer::write( $file, $config, "Page config: {$slug}" );
    }

    // ----- SITE CONFIG -----

    public function get_site() {
        $file = $this->get_config_dir() . '/site.php';
        return file_exists( $file ) ? include $file : [];
    }

    public function save_site( array $config ) {
        return Anchor_Editor_Config_Writer::write(
            $this->get_config_dir() . '/site.php',
            $config,
            'Site-wide business data.'
        );
    }

    // ----- DATA CONFIG -----

    public function get_data() {
        $file = $this->get_config_dir() . '/data.php';
        return file_exists( $file ) ? include $file : [];
    }

    public function save_data( array $config ) {
        return Anchor_Editor_Config_Writer::write(
            $this->get_config_dir() . '/data.php',
            $config,
            'Shared entity data (services, team, etc.).'
        );
    }

    // ----- GLOBALS CONFIG -----

    public function get_globals() {
        $file = $this->get_config_dir() . '/globals.php';
        return file_exists( $file ) ? include $file : [];
    }

    public function save_globals( array $config ) {
        return Anchor_Editor_Config_Writer::write(
            $this->get_config_dir() . '/globals.php',
            $config,
            'Global reusable section definitions.'
        );
    }

    // ----- MEDIA CONFIG -----

    public function get_media() {
        $file = $this->get_config_dir() . '/media.php';
        return file_exists( $file ) ? include $file : [];
    }

    public function save_media( array $config ) {
        return Anchor_Editor_Config_Writer::write(
            $this->get_config_dir() . '/media.php',
            $config,
            'Media asset map.'
        );
    }
}
