<?php
/**
 * Anchor Framework — Config Loader
 *
 * Handles loading configuration files from child theme with parent theme fallback.
 * Config files live in the config/ directory and return PHP arrays.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Load a config file by name.
 *
 * Looks for config/$file.php in the child theme first, then the parent theme.
 * Results are cached in a static array for the duration of the request.
 *
 * @param string $file Config file name (without .php extension), e.g. 'site' or 'pages/home'.
 * @return array The config array, or empty array if file not found.
 */
if ( ! function_exists( 'anchor_load_config' ) ) {
    function anchor_load_config( $file ) {
        static $cache = [];

        if ( isset( $cache[ $file ] ) ) {
            return $cache[ $file ];
        }

        $relative_path = 'config/' . $file . '.php';

        // Child theme first, then parent theme.
        $child_path  = get_stylesheet_directory() . '/' . $relative_path;
        $parent_path = get_template_directory() . '/' . $relative_path;

        if ( file_exists( $child_path ) ) {
            $cache[ $file ] = include $child_path;
        } elseif ( file_exists( $parent_path ) ) {
            $cache[ $file ] = include $parent_path;
        } else {
            $cache[ $file ] = [];
        }

        // Ensure we always return an array.
        if ( ! is_array( $cache[ $file ] ) ) {
            $cache[ $file ] = [];
        }

        return $cache[ $file ];
    }
}

/**
 * Get the site-wide configuration.
 *
 * @return array
 */
if ( ! function_exists( 'anchor_get_site_config' ) ) {
    function anchor_get_site_config() {
        return anchor_load_config( 'site' );
    }
}

/**
 * Get the navigation configuration.
 *
 * @return array
 */
if ( ! function_exists( 'anchor_get_navigation_config' ) ) {
    function anchor_get_navigation_config() {
        return anchor_load_config( 'navigation' );
    }
}

/**
 * Get the globals configuration (reusable section definitions).
 *
 * @return array
 */
if ( ! function_exists( 'anchor_get_globals_config' ) ) {
    function anchor_get_globals_config() {
        return anchor_load_config( 'globals' );
    }
}

/**
 * Get the media configuration (named media references).
 *
 * @return array
 */
if ( ! function_exists( 'anchor_get_media_config' ) ) {
    function anchor_get_media_config() {
        return anchor_load_config( 'media' );
    }
}

/**
 * Get the shared data configuration (services, team, etc.).
 *
 * @return array
 */
if ( ! function_exists( 'anchor_get_data_config' ) ) {
    function anchor_get_data_config() {
        return anchor_load_config( 'data' );
    }
}

/**
 * Get a specific data set by key (e.g. 'services', 'team').
 *
 * @param string $key   The data set key.
 * @param array  $default Fallback value.
 * @return array
 */
if ( ! function_exists( 'anchor_get_data' ) ) {
    function anchor_get_data( $key, $default = [] ) {
        $data = anchor_get_data_config();
        return isset( $data[ $key ] ) ? $data[ $key ] : $default;
    }
}

/**
 * Get a page-specific configuration by slug.
 *
 * @param string $slug Page config slug, e.g. 'home' or 'services/service-one'.
 * @return array|null The page config array, or null if file doesn't exist.
 */
if ( ! function_exists( 'anchor_get_page_config' ) ) {
    function anchor_get_page_config( $slug ) {
        $relative_path = 'config/pages/' . $slug . '.php';

        $child_path  = get_stylesheet_directory() . '/' . $relative_path;
        $parent_path = get_template_directory() . '/' . $relative_path;

        if ( ! file_exists( $child_path ) && ! file_exists( $parent_path ) ) {
            return null;
        }

        return anchor_load_config( 'pages/' . $slug );
    }
}

/**
 * Determine the config slug for the current WordPress query.
 *
 * Maps the current page/view to a config file path under config/pages/.
 *
 * @return string|null The config slug, or null if no mapping is found.
 */
if ( ! function_exists( 'anchor_determine_page_slug' ) ) {
    function anchor_determine_page_slug() {

        // Front page (static or default).
        if ( is_front_page() ) {
            return 'home';
        }

        // Blog posts page (when a static front page is set).
        if ( is_home() ) {
            return 'blog';
        }

        // Custom post type archive: events.
        if ( is_post_type_archive( 'anchor_event' ) ) {
            return 'events';
        }

        // Single event.
        if ( is_singular( 'anchor_event' ) ) {
            return 'single-event';
        }

        // Single blog post.
        if ( is_singular( 'post' ) ) {
            return 'single-post';
        }

        // Single page — try hierarchical slug resolution.
        if ( is_page() ) {
            $page   = get_queried_object();
            $slug   = $page->post_name;
            $parent = $page->post_parent;

            // If the page has a parent, build the hierarchical slug.
            if ( $parent ) {
                $parent_post = get_post( $parent );
                $parent_slug = $parent_post ? $parent_post->post_name : '';

                if ( $parent_slug ) {
                    // Try parent/child first (e.g. 'services/service-one').
                    $hierarchical = $parent_slug . '/' . $slug;
                    $config = anchor_get_page_config( $hierarchical );
                    if ( $config !== null ) {
                        return $hierarchical;
                    }

                    // Try just the child slug (e.g. 'service-one').
                    $config = anchor_get_page_config( $slug );
                    if ( $config !== null ) {
                        return $slug;
                    }

                    // Try a generic fallback based on parent (e.g. 'single-service' for parent 'services').
                    $generic = 'single-' . rtrim( $parent_slug, 's' );
                    $config = anchor_get_page_config( $generic );
                    if ( $config !== null ) {
                        return $generic;
                    }
                }
            }

            // No parent or no hierarchical match — use the page slug directly.
            return $slug;
        }

        // Category, tag, or other archive pages.
        if ( is_category() || is_tag() || is_archive() ) {
            return 'blog';
        }

        // Search results.
        if ( is_search() ) {
            return 'search';
        }

        // 404 page.
        if ( is_404() ) {
            return '404';
        }

        // No mapping found.
        return null;
    }
}
