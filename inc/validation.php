<?php
/**
 * Anchor Framework — Section Validation & Defaults
 *
 * Provides validation for section definitions and default prop values
 * for every supported section type, ensuring templates always have
 * all expected keys available.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Validate a section definition.
 *
 * A valid section must be an array with a 'type' key.
 * When WP_DEBUG is enabled, invalid sections trigger a helpful error message.
 *
 * @param mixed $section The section definition to validate.
 * @return bool True if valid, false otherwise.
 */
if ( ! function_exists( 'anchor_validate_section' ) ) {
    function anchor_validate_section( $section ) {

        // Must be an array.
        if ( ! is_array( $section ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                trigger_error(
                    'Anchor Framework: Section definition must be an array. Received ' . gettype( $section ) . '.',
                    E_USER_WARNING
                );
            }
            return false;
        }

        // Must have a 'type' key.
        if ( empty( $section['type'] ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                trigger_error(
                    'Anchor Framework: Section definition is missing the required "type" key. Keys present: ' . implode( ', ', array_keys( $section ) ) . '.',
                    E_USER_WARNING
                );
            }
            return false;
        }

        // Validate that the type is a known section type (warning only, still renders).
        $known_types = array_keys( anchor_get_all_section_defaults() );
        if ( ! in_array( $section['type'], $known_types, true ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                trigger_error(
                    sprintf(
                        'Anchor Framework: Unknown section type "%s". Known types: %s.',
                        esc_html( $section['type'] ),
                        implode( ', ', $known_types )
                    ),
                    E_USER_NOTICE
                );
            }
            // Still return true — allow rendering of custom section types.
        }

        return true;
    }
}

/**
 * Get default props for a given section type.
 *
 * Used to merge with provided props so section templates always have
 * all expected keys available, avoiding undefined index notices.
 *
 * @param string $type The section type identifier.
 * @return array Default props array for the type, or empty array if unknown.
 */
if ( ! function_exists( 'anchor_get_section_defaults' ) ) {
    function anchor_get_section_defaults( $type ) {
        $all_defaults = anchor_get_all_section_defaults();
        return isset( $all_defaults[ $type ] ) ? $all_defaults[ $type ] : [];
    }
}

/**
 * Get the complete map of section types to their default props.
 *
 * Centralized here so both validation and defaults use the same source of truth.
 *
 * @return array Associative array of type => default props.
 */
if ( ! function_exists( 'anchor_get_all_section_defaults' ) ) {
    function anchor_get_all_section_defaults() {
        return [

            'hero' => [
                'eyebrow'       => '',
                'heading'       => '',
                'heading_lines' => [],
                'text'          => '',
                'image'         => '',
                'ticker_items'  => [],
                'values'        => [],
                'primary_cta'   => null,
                'secondary_cta' => null,
                'back_link'     => null,
                'category'      => '',
                'min_height'    => '',
            ],

            'split_content' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'text'           => '',
                'image'          => '',
                'image_alt'      => '',
                'image_position' => 'left',
                'badge'          => null,
                'cta'            => null,
                'aspect'         => '4/4',
                'dark'           => false,
            ],

            'text_block' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'content'        => '',
                'align'          => 'center',
                'max_width'      => '4xl',
                'divider'        => '',
            ],

            'card_grid' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'text'           => '',
                'columns'        => 3,
                'items'          => [],
                'cta'            => null,
                'align'          => 'left',
                'dark'           => false,
            ],

            'icon_list' => [
                'eyebrow'          => '',
                'heading'          => '',
                'heading_accent'   => '',
                'text'             => '',
                'columns'          => 3,
                'items'            => [],
                'dark'             => false,
                'background_image' => '',
            ],

            'testimonial_band' => [
                'eyebrow' => '',
                'heading' => '',
                'items'   => [],
                'layout'  => 'grid',
            ],

            'faq_list' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'items'          => [],
                'columns'        => 1,
            ],

            'cta_band' => [
                'eyebrow'          => '',
                'heading'          => '',
                'heading_accent'   => '',
                'text'             => '',
                'primary_cta'      => null,
                'secondary_cta'    => null,
                'align'            => 'center',
                'background_image' => '',
            ],

            'shortcode_block' => [
                'heading'    => '',
                'text'       => '',
                'shortcode'  => '',
                'max_width'  => '',
                'background' => 'none',
            ],

            'gallery_band' => [
                'heading' => '',
                'images'  => [],
                'columns' => 3,
                'aspect'  => '16/10',
            ],

            'team_grid' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'text'           => '',
                'columns'        => 4,
                'members'        => [],
                'layout'         => 'grid',
                'cta'            => null,
            ],

            'contact_block' => [
                'heading'        => '',
                'text'           => '',
                'show_form'      => true,
                'show_map'       => false,
                'form_shortcode' => '',
                'fields'         => [],
            ],

            'services_tabs' => [
                'eyebrow'        => '',
                'heading'        => '',
                'heading_accent' => '',
                'text'           => '',
                'tabs'           => [],
                'cta'            => null,
            ],

        ];
    }
}
