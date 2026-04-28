<?php
/**
 * Anchor Framework — Media Resolver
 *
 * Resolves media references from config arrays to actual URLs.
 * Supports WordPress attachment IDs, absolute URLs, media config keys,
 * and theme-relative paths.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolve a single media reference to a URL.
 *
 * Resolution order:
 *   1. null/empty string → ''
 *   2. Integer → WordPress attachment URL via wp_get_attachment_url()
 *   3. String starting with http:// or https:// → returned as-is
 *   4. String matching a key in media config → recursively resolve that key's value
 *   5. String starting with '/' → prepend active theme URI (stylesheet directory)
 *   6. Anything else → returned as-is
 *
 * @param mixed $value The media reference to resolve.
 * @return string The resolved URL, or empty string.
 */
if ( ! function_exists( 'anchor_resolve_media' ) ) {
    function anchor_resolve_media( $value ) {

        // 1. Null or empty.
        if ( $value === null || $value === '' ) {
            return '';
        }

        // 2. Integer — WordPress attachment ID.
        if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
            $url = wp_get_attachment_url( (int) $value );
            return $url ? $url : '';
        }

        // Must be a string from here on.
        if ( ! is_string( $value ) ) {
            return '';
        }

        // 3. Absolute URL — return as-is.
        if ( strpos( $value, 'http://' ) === 0 || strpos( $value, 'https://' ) === 0 ) {
            return $value;
        }

        // 4. Named key in media config — recursively resolve.
        $media_config = function_exists( 'anchor_get_media_config' ) ? anchor_get_media_config() : [];
        if ( ! empty( $media_config ) && isset( $media_config[ $value ] ) ) {
            return anchor_resolve_media( $media_config[ $value ] );
        }

        // 5. Theme-relative path starting with '/'.
        if ( strpos( $value, '/' ) === 0 ) {
            return get_stylesheet_directory_uri() . $value;
        }

        // 6. Fallback — return as-is.
        return $value;
    }
}

/**
 * Recursively resolve media references within a props array.
 *
 * Keys that are treated as media references:
 *   - Exact names: image, photo, background, logo, icon_image
 *   - Keys ending with: _image, _photo, _bg, _media
 *
 * Nested arrays (e.g. 'items', 'members', 'images') are recursed into.
 *
 * @param array $props The props array to process.
 * @return array The props array with media references resolved.
 */
if ( ! function_exists( 'anchor_resolve_media_in_props' ) ) {
    function anchor_resolve_media_in_props( $props ) {

        if ( ! is_array( $props ) ) {
            return $props;
        }

        // Keys whose values should be resolved as media.
        $media_keys = [ 'image', 'image_mobile', 'photo', 'background', 'logo', 'icon_image' ];

        // Suffixes that indicate a media value.
        $media_suffixes = [ '_image', '_photo', '_bg', '_media' ];

        foreach ( $props as $key => &$value ) {

            // Check if this key is a media key.
            $is_media_key = in_array( $key, $media_keys, true );

            if ( ! $is_media_key ) {
                foreach ( $media_suffixes as $suffix ) {
                    if ( substr( $key, -strlen( $suffix ) ) === $suffix ) {
                        $is_media_key = true;
                        break;
                    }
                }
            }

            if ( $is_media_key && ! is_array( $value ) ) {
                // Resolve the media reference.
                $value = anchor_resolve_media( $value );
            } elseif ( is_array( $value ) ) {
                // Recurse into nested arrays.
                // Check if it's a sequential array (list of items).
                if ( isset( $value[0] ) && is_array( $value[0] ) ) {
                    // List of items — resolve each one.
                    foreach ( $value as $idx => &$item ) {
                        if ( is_array( $item ) ) {
                            $item = anchor_resolve_media_in_props( $item );
                        }
                    }
                    unset( $item );
                } else {
                    // Associative array — recurse.
                    $value = anchor_resolve_media_in_props( $value );
                }
            }
        }
        unset( $value );

        return $props;
    }
}
