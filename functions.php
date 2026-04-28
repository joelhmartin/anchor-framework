<?php
/**
 * Anchor Framework - Functions and definitions.
 *
 * A config-first WordPress theme framework.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ---------------------------------------------------------------------------
 * 1. Include framework modules from /inc/
 * ---------------------------------------------------------------------------
 *
 * Each file is wrapped in a file_exists() check so the theme can boot even
 * when individual modules have not been created yet.
 */
$anchor_inc_files = array(
    '/inc/config-loader.php',
    '/inc/section-renderer.php',
    '/inc/media-resolver.php',
    '/inc/helpers.php',
    '/inc/validation.php',
    '/inc/page-content-loader.php',
    '/inc/class-anchor-nav-walker.php',
    '/inc/class-anchor-mobile-walker.php',
    '/inc/class-anchor-footer-walker.php',
    '/inc/updater.php',
);

foreach ( $anchor_inc_files as $anchor_inc_file ) {
    $anchor_inc_path = get_template_directory() . $anchor_inc_file;
    if ( file_exists( $anchor_inc_path ) ) {
        require_once $anchor_inc_path;
    }
}

/**
 * ---------------------------------------------------------------------------
 * 2. Theme Setup
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_theme_setup' ) ) {
    /**
     * Set up theme defaults and register support for various WordPress features.
     *
     * @since 1.0.0
     */
    function anchor_theme_setup() {

        // Let WordPress handle the document title tag.
        add_theme_support( 'title-tag' );

        // Enable support for Post Thumbnails on posts and pages.
        add_theme_support( 'post-thumbnails' );

        // Switch core markup to valid HTML5.
        add_theme_support(
            'html5',
            array(
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                'style',
                'script',
            )
        );

        // Enable support for a custom logo.
        add_theme_support( 'custom-logo' );

        // Register navigation menus.
        register_nav_menus( [
            'primary' => __( 'Primary Navigation', 'anchor-framework' ),
            'footer'  => __( 'Footer Navigation', 'anchor-framework' ),
        ] );
    }
}
add_action( 'after_setup_theme', 'anchor_theme_setup' );

if ( ! function_exists( 'anchor_content_width' ) ) {
    /**
     * Set the content width in pixels, based on the theme layout.
     *
     * @since 1.0.0
     */
    function anchor_content_width() {
        $GLOBALS['content_width'] = apply_filters( 'anchor_content_width', 1280 );
    }
}
add_action( 'after_setup_theme', 'anchor_content_width', 0 );

/**
 * Control posts per page for blog and events archives via config.
 */
if ( ! function_exists( 'anchor_archive_posts_per_page' ) ) {
    function anchor_archive_posts_per_page( $query ) {
        if ( is_admin() || ! $query->is_main_query() ) return;

        if ( $query->is_home() || $query->is_category() || $query->is_tag() ) {
            $blog_config = function_exists( 'anchor_get_page_config' ) ? anchor_get_page_config( 'blog' ) : [];
            if ( ! empty( $blog_config['per_page'] ) ) {
                $query->set( 'posts_per_page', (int) $blog_config['per_page'] );
            }
        }

        if ( $query->is_post_type_archive( 'anchor_event' ) ) {
            $events_config = function_exists( 'anchor_get_page_config' ) ? anchor_get_page_config( 'events' ) : [];
            if ( ! empty( $events_config['per_page'] ) ) {
                $query->set( 'posts_per_page', (int) $events_config['per_page'] );
            }
        }
    }
}
add_action( 'pre_get_posts', 'anchor_archive_posts_per_page' );

/**
 * ---------------------------------------------------------------------------
 * 3. Enqueue Styles
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_enqueue_styles' ) ) {
    /**
     * Enqueue Google Fonts and theme stylesheets.
     *
     * CSS load order is enforced via dependency chains so that variables are
     * available before any rule-set that references them.
     *
     * @since 1.0.0
     */
    function anchor_enqueue_styles() {

        $theme_version = wp_get_theme()->get( 'Version' );
        $css_dir       = get_template_directory_uri() . '/assets/css/';

        // --- Font Awesome 6 Free -----------------------------------------
        wp_enqueue_style(
            'anchor-font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css',
            array(),
            null
        );

        // --- Google Fonts ------------------------------------------------
        $google_fonts_url = add_query_arg(
            array(
                'family' => implode(
                    '&family=',
                    array(
                        'Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800',
                        'Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700',
                        'IBM+Plex+Mono:ital,wght@0,400;0,500;0,600;1,400;1,500;1,600',
                    )
                ),
                'display' => 'swap',
            ),
            'https://fonts.googleapis.com/css2'
        );

        wp_enqueue_style(
            'anchor-google-fonts',
            $google_fonts_url,
            array(),
            null // Google Fonts URLs should not be versioned.
        );

        // --- Theme CSS files (ordered via dependency chain) ---------------
        $css_files = array(
            'anchor-variables'  => array( 'file' => 'variables.css',  'deps' => array( 'anchor-google-fonts' ) ),
            'anchor-base'       => array( 'file' => 'base.css',       'deps' => array( 'anchor-variables' ) ),
            'anchor-layout'     => array( 'file' => 'layout.css',     'deps' => array( 'anchor-base' ) ),
            'anchor-components' => array( 'file' => 'components.css', 'deps' => array( 'anchor-layout' ) ),
            'anchor-sections'   => array( 'file' => 'sections.css',   'deps' => array( 'anchor-components' ) ),
            'anchor-responsive' => array( 'file' => 'responsive.css', 'deps' => array( 'anchor-sections' ) ),
        );

        foreach ( $css_files as $handle => $meta ) {
            wp_enqueue_style(
                $handle,
                $css_dir . $meta['file'],
                $meta['deps'],
                $theme_version
            );
        }
    }
}
add_action( 'wp_enqueue_scripts', 'anchor_enqueue_styles' );

/**
 * ---------------------------------------------------------------------------
 * 4. Enqueue Scripts
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_enqueue_scripts' ) ) {
    /**
     * Enqueue theme JavaScript files.
     *
     * @since 1.0.0
     */
    function anchor_enqueue_scripts() {

        $theme_version = wp_get_theme()->get( 'Version' );
        $js_dir        = get_template_directory_uri() . '/assets/js/';

        wp_enqueue_script(
            'anchor-scroll-reveal',
            $js_dir . 'scroll-reveal.js',
            array(),
            $theme_version,
            true // Load in footer.
        );

        wp_enqueue_script(
            'anchor-navigation',
            $js_dir . 'navigation.js',
            array(),
            $theme_version,
            true // Load in footer.
        );
    }
}
add_action( 'wp_enqueue_scripts', 'anchor_enqueue_scripts' );

/**
 * ---------------------------------------------------------------------------
 * 4b. Dequeue WordPress Default Styles
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_dequeue_wp_styles' ) ) {
    function anchor_dequeue_wp_styles() {
        // Remove Gutenberg block styles — not used in this classic theme.
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );
        wp_dequeue_style( 'wc-block-style' ); // WooCommerce blocks, if present.

        // Remove WordPress global styles (WP 5.9+) — we manage our own tokens.
        wp_dequeue_style( 'global-styles' );
        wp_dequeue_style( 'global-styles-inline-css' );

        // Remove classic theme compatibility styles (WP 6.1+).
        wp_dequeue_style( 'classic-theme-styles' );

        // Remove core emoji scripts and styles — rarely needed.
        wp_dequeue_script( 'wp-emoji-release' );
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
    }
}
add_action( 'wp_enqueue_scripts', 'anchor_dequeue_wp_styles', 20 );

/**
 * ---------------------------------------------------------------------------
 * 4c. Remove WordPress Auto-Generated Head Noise
 * ---------------------------------------------------------------------------
 */
remove_action( 'wp_head', 'wp_generator' );             // WordPress version in meta.
remove_action( 'wp_head', 'wlwmanifest_link' );         // Windows Live Writer.
remove_action( 'wp_head', 'rsd_link' );                 // Really Simple Discovery.
remove_action( 'wp_head', 'wp_shortlink_wp_head' );     // Shortlink tag.
remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 ); // Prev/next post links.

/**
 * ---------------------------------------------------------------------------
 * 5. Register Custom Post Type — Events
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_register_event_post_type' ) ) {
    /**
     * Register the "anchor_event" custom post type.
     *
     * @since 1.0.0
     */
    function anchor_register_event_post_type() {

        $labels = array(
            'name'                  => _x( 'Events', 'Post type general name', 'anchor-framework' ),
            'singular_name'         => _x( 'Event', 'Post type singular name', 'anchor-framework' ),
            'menu_name'             => _x( 'Events', 'Admin menu text', 'anchor-framework' ),
            'name_admin_bar'        => _x( 'Event', 'Add New on toolbar', 'anchor-framework' ),
            'add_new'               => __( 'Add New', 'anchor-framework' ),
            'add_new_item'          => __( 'Add New Event', 'anchor-framework' ),
            'new_item'              => __( 'New Event', 'anchor-framework' ),
            'edit_item'             => __( 'Edit Event', 'anchor-framework' ),
            'view_item'             => __( 'View Event', 'anchor-framework' ),
            'all_items'             => __( 'All Events', 'anchor-framework' ),
            'search_items'          => __( 'Search Events', 'anchor-framework' ),
            'parent_item_colon'     => __( 'Parent Events:', 'anchor-framework' ),
            'not_found'             => __( 'No events found.', 'anchor-framework' ),
            'not_found_in_trash'    => __( 'No events found in Trash.', 'anchor-framework' ),
            'archives'              => __( 'Event Archives', 'anchor-framework' ),
            'insert_into_item'      => __( 'Insert into event', 'anchor-framework' ),
            'uploaded_to_this_item' => __( 'Uploaded to this event', 'anchor-framework' ),
            'filter_items_list'     => __( 'Filter events list', 'anchor-framework' ),
            'items_list_navigation' => __( 'Events list navigation', 'anchor-framework' ),
            'items_list'            => __( 'Events list', 'anchor-framework' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => true,
            'rewrite'            => array( 'slug' => 'events' ),
            'menu_icon'          => 'dashicons-calendar-alt',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
            'show_in_rest'       => true,
        );

        register_post_type( 'anchor_event', $args );
    }
}
add_action( 'init', 'anchor_register_event_post_type' );

/**
 * ---------------------------------------------------------------------------
 * 6. Register Taxonomy — Event Category
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'anchor_register_event_category_taxonomy' ) ) {
    /**
     * Register the "anchor_event_category" taxonomy for events.
     *
     * @since 1.0.0
     */
    function anchor_register_event_category_taxonomy() {

        $labels = array(
            'name'                       => _x( 'Event Categories', 'Taxonomy general name', 'anchor-framework' ),
            'singular_name'              => _x( 'Event Category', 'Taxonomy singular name', 'anchor-framework' ),
            'search_items'               => __( 'Search Event Categories', 'anchor-framework' ),
            'popular_items'              => __( 'Popular Event Categories', 'anchor-framework' ),
            'all_items'                  => __( 'All Event Categories', 'anchor-framework' ),
            'parent_item'                => __( 'Parent Event Category', 'anchor-framework' ),
            'parent_item_colon'          => __( 'Parent Event Category:', 'anchor-framework' ),
            'edit_item'                  => __( 'Edit Event Category', 'anchor-framework' ),
            'update_item'                => __( 'Update Event Category', 'anchor-framework' ),
            'add_new_item'               => __( 'Add New Event Category', 'anchor-framework' ),
            'new_item_name'              => __( 'New Event Category Name', 'anchor-framework' ),
            'separate_items_with_commas' => __( 'Separate event categories with commas', 'anchor-framework' ),
            'add_or_remove_items'        => __( 'Add or remove event categories', 'anchor-framework' ),
            'choose_from_most_used'      => __( 'Choose from the most used event categories', 'anchor-framework' ),
            'not_found'                  => __( 'No event categories found.', 'anchor-framework' ),
            'menu_name'                  => __( 'Event Categories', 'anchor-framework' ),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_in_rest'      => true,
            'rewrite'           => array( 'slug' => 'event-category' ),
        );

        register_taxonomy( 'anchor_event_category', 'anchor_event', $args );
    }
}
add_action( 'init', 'anchor_register_event_category_taxonomy' );
