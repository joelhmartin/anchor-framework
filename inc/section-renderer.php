<?php
/**
 * Anchor Framework — Section Renderer
 *
 * The core rendering engine. Takes arrays of section definitions from page configs
 * and renders them as HTML using template part files.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render an array of section definitions.
 *
 * Each item can be:
 *   - A section definition array with 'type', optional 'variant', and 'props'
 *   - A string starting with 'global:' to reference a global section definition
 *
 * @param array $sections Array of section definitions.
 */
if ( ! function_exists( 'anchor_render_sections' ) ) {
    function anchor_render_sections( $sections ) {

        if ( empty( $sections ) || ! is_array( $sections ) ) {
            return;
        }

        foreach ( $sections as $section ) {

            // Resolve global references (e.g. 'global:cta_band').
            if ( is_string( $section ) && strpos( $section, 'global:' ) === 0 ) {
                $global_key = substr( $section, 7 ); // Remove 'global:' prefix.
                $globals    = function_exists( 'anchor_get_globals_config' ) ? anchor_get_globals_config() : [];

                if ( isset( $globals[ $global_key ] ) && is_array( $globals[ $global_key ] ) ) {
                    $section = $globals[ $global_key ];
                } else {
                    // Global not found — skip silently.
                    continue;
                }
            }

            // Skip invalid or empty sections.
            if ( empty( $section ) || ! is_array( $section ) ) {
                continue;
            }

            anchor_render_section( $section );
        }
    }
}

/**
 * Render a single section.
 *
 * Validates the section, merges defaults, resolves media, builds the wrapper
 * element, sets template data, and includes the template part.
 *
 * @param array $section Section definition with 'type', optional 'variant' and 'props'.
 */
if ( ! function_exists( 'anchor_render_section' ) ) {
    function anchor_render_section( $section ) {

        // Validate the section.
        if ( function_exists( 'anchor_validate_section' ) && ! anchor_validate_section( $section ) ) {
            return;
        }

        $type    = $section['type'];
        $variant = isset( $section['variant'] ) ? $section['variant'] : '';
        $props   = isset( $section['props'] ) ? $section['props'] : [];

        // Merge with defaults for this section type.
        if ( function_exists( 'anchor_get_section_defaults' ) ) {
            $defaults = anchor_get_section_defaults( $type );
            if ( ! empty( $defaults ) ) {
                $props = array_merge( $defaults, $props );
            }
        }

        // Resolve media references in props.
        if ( function_exists( 'anchor_resolve_media_in_props' ) ) {
            $props = anchor_resolve_media_in_props( $props );
        }

        // Update section with resolved props.
        $section['props']   = $props;
        $section['variant'] = $variant;

        // Build CSS class string.
        $css_class = 'anchor-section';

        // Add variant class if present.
        if ( $variant ) {
            $css_class .= ' anchor-section--' . esc_attr( $variant );
        }

        // Add dark class if props indicate dark.
        if ( ! empty( $props['dark'] ) ) {
            $css_class .= ' anchor-section--dark';
        }

        // Add surface background class.
        if ( ! empty( $props['surface_bg'] ) ) {
            $css_class .= ' anchor-section--surface';
        }

        // Remove bottom padding when flush_bottom is set.
        if ( ! empty( $section['flush_bottom'] ) ) {
            $css_class .= ' anchor-section--flush-bottom';
        }

        // Note: section-pad is NOT added here — each section template handles its own
        // horizontal padding internally, since some sections need full-bleed backgrounds.

        // Section-level scroll reveal removed — content-level stagger animations remain.

        // Set template data for the section template.
        if ( function_exists( 'anchor_set_template_data' ) ) {
            anchor_set_template_data( $section );
        }

        // Build the template part path (underscores become hyphens).
        $template_slug = str_replace( '_', '-', sanitize_file_name( $type ) );

        // Output section wrapper and template.
        printf(
            '<section class="%s" data-section-type="%s" data-section-variant="%s">',
            esc_attr( $css_class ),
            esc_attr( $type ),
            esc_attr( $variant )
        );

        get_template_part( 'template-parts/sections/' . $template_slug );

        echo '</section>';
    }
}
