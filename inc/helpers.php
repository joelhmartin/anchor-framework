<?php
/**
 * Anchor Framework — Helper Functions
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Template data passing system.
 * Sections and components receive their data through this global bridge.
 */
if ( ! function_exists( 'anchor_set_template_data' ) ) {
    function anchor_set_template_data( $data ) {
        global $anchor_template_data;
        $anchor_template_data = $data;
    }
}

if ( ! function_exists( 'anchor_get_template_data' ) ) {
    function anchor_get_template_data() {
        global $anchor_template_data;
        return $anchor_template_data ? $anchor_template_data : [];
    }
}

/**
 * Render a component template with data.
 * Usage: anchor_render_component('button', ['label' => 'Click', 'url' => '/']);
 */
if ( ! function_exists( 'anchor_render_component' ) ) {
    function anchor_render_component( $name, $data = [] ) {
        $previous_data = anchor_get_template_data();
        anchor_set_template_data( $data );
        get_template_part( 'template-parts/components/' . sanitize_file_name( $name ) );
        anchor_set_template_data( $previous_data );
    }
}

/**
 * Get a value from site config.
 * Usage: anchor_get_site('phone') or anchor_get_site('logo.header_light', '/default.svg')
 */
if ( ! function_exists( 'anchor_get_site' ) ) {
    function anchor_get_site( $key, $default = '' ) {
        $config = function_exists('anchor_get_site_config') ? anchor_get_site_config() : [];
        if ( strpos( $key, '.' ) !== false ) {
            $keys = explode( '.', $key );
            $value = $config;
            foreach ( $keys as $k ) {
                if ( is_array( $value ) && isset( $value[ $k ] ) ) {
                    $value = $value[ $k ];
                } else {
                    return $default;
                }
            }
            return $value;
        }
        return isset( $config[ $key ] ) ? $config[ $key ] : $default;
    }
}

/**
 * Get the default CTA from site config.
 */
if ( ! function_exists( 'anchor_get_cta' ) ) {
    function anchor_get_cta() {
        return anchor_get_site( 'default_cta', [ 'label' => 'Contact Us', 'url' => '/contact/' ] );
    }
}

/**
 * Escape content but allow basic HTML tags.
 * Safe for displaying user-configured text that might contain <strong>, <em>, <a>, <br>, <span>.
 */
if ( ! function_exists( 'anchor_esc_content' ) ) {
    function anchor_esc_content( $text ) {
        return wp_kses( $text, [
            'strong' => [],
            'em'     => [],
            'b'      => [],
            'i'      => [],
            'a'      => [ 'href' => [], 'target' => [], 'rel' => [], 'class' => [] ],
            'br'     => [],
            'span'   => [ 'class' => [], 'style' => [] ],
        ] );
    }
}

/**
 * Build a class string from base, modifiers array, and extra classes.
 * Usage: anchor_class('anchor-btn', ['primary', 'lg'], 'custom-class')
 * Returns: "anchor-btn anchor-btn--primary anchor-btn--lg custom-class"
 */
if ( ! function_exists( 'anchor_class' ) ) {
    function anchor_class( $base, $modifiers = [], $extra = '' ) {
        $classes = [ $base ];
        if ( ! empty( $modifiers ) ) {
            foreach ( (array) $modifiers as $mod ) {
                if ( ! empty( $mod ) ) {
                    $classes[] = $base . '--' . $mod;
                }
            }
        }
        if ( ! empty( $extra ) ) {
            $classes[] = $extra;
        }
        return implode( ' ', array_filter( $classes ) );
    }
}

/**
 * Get navigation config shortcut.
 */
if ( ! function_exists( 'anchor_get_nav' ) ) {
    function anchor_get_nav( $key = null ) {
        $config = function_exists('anchor_get_navigation_config') ? anchor_get_navigation_config() : [];
        if ( $key === null ) return $config;
        return isset( $config[ $key ] ) ? $config[ $key ] : [];
    }
}

/**
 * Check if the current page matches a URL (for active nav state).
 */
if ( ! function_exists( 'anchor_is_current_url' ) ) {
    function anchor_is_current_url( $url ) {
        $current = trailingslashit( $_SERVER['REQUEST_URI'] );
        $target = trailingslashit( $url );
        return $current === $target;
    }
}

/**
 * Check if any child URL of a nav item is current (for parent active state).
 */
if ( ! function_exists( 'anchor_has_active_child' ) ) {
    function anchor_has_active_child( $item ) {
        if ( empty( $item['children'] ) ) return false;
        foreach ( $item['children'] as $child ) {
            if ( anchor_is_current_url( $child['url'] ) ) return true;
        }
        return false;
    }
}

/**
 * Simple SVG icon helper. Returns inline SVG for common icons.
 * Icons: arrow-right, arrow-left, chevron-down, chevron-left, chevron-right,
 *        phone, mail, map-pin, clock, calendar, tag, search, menu, x,
 *        facebook, instagram, youtube, twitter, linkedin
 */
if ( ! function_exists( 'anchor_icon' ) ) {
    function anchor_icon( $name, $size = 16 ) {
        // Maps theme icon names to Font Awesome 6 Free classes.
        $fa_map = [
            'arrow-right'    => 'fa-solid fa-arrow-right',
            'arrow-left'     => 'fa-solid fa-arrow-left',
            'arrow-up-right' => 'fa-solid fa-arrow-up-right',
            'chevron-down'   => 'fa-solid fa-chevron-down',
            'chevron-left'   => 'fa-solid fa-chevron-left',
            'chevron-right'  => 'fa-solid fa-chevron-right',
            'phone'          => 'fa-solid fa-phone',
            'mail'           => 'fa-solid fa-envelope',
            'map-pin'        => 'fa-solid fa-location-dot',
            'clock'          => 'fa-solid fa-clock',
            'calendar'       => 'fa-regular fa-calendar',
            'tag'            => 'fa-solid fa-tag',
            'search'         => 'fa-solid fa-magnifying-glass',
            'send'           => 'fa-solid fa-paper-plane',
            'check-circle'   => 'fa-solid fa-circle-check',
            'loader'         => 'fa-solid fa-spinner',
            'share'          => 'fa-solid fa-share-nodes',
            'heart'          => 'fa-solid fa-heart',
            'users'          => 'fa-solid fa-users',
            'compass'        => 'fa-solid fa-compass',
            'star'           => 'fa-solid fa-star',
            'shield'         => 'fa-solid fa-shield-halved',
            'tooth'          => 'fa-solid fa-tooth',
            'stethoscope'    => 'fa-solid fa-stethoscope',
            'syringe'        => 'fa-solid fa-syringe',
            'microscope'     => 'fa-solid fa-microscope',
            'x-ray'          => 'fa-solid fa-x-ray',
            'smile'          => 'fa-regular fa-face-smile',
            'facebook'       => 'fa-brands fa-facebook-f',
            'instagram'      => 'fa-brands fa-instagram',
            'youtube'        => 'fa-brands fa-youtube',
            'twitter'        => 'fa-brands fa-x-twitter',
            'linkedin'       => 'fa-brands fa-linkedin-in',
            'tiktok'         => 'fa-brands fa-tiktok',
            'message'        => 'fa-solid fa-comment-dots',
        ];

        if ( ! isset( $fa_map[ $name ] ) ) {
            return '';
        }

        // $size is a dynamic PHP value — inline font-size is intentional.
        return sprintf(
            '<i class="%s" style="font-size:%dpx;" aria-hidden="true"></i>',
            esc_attr( $fa_map[ $name ] ),
            (int) $size
        );
    }
}
