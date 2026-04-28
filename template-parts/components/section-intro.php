<?php
/**
 * Component: Section Intro
 *
 * Convenience wrapper that renders the heading-group component with the
 * standard section intro pattern (eyebrow + heading + text above a section's
 * content). Accepts the same data as heading-group.
 *
 * Data (via anchor_get_template_data):
 *   eyebrow        (string|null)
 *   heading        (string)
 *   heading_accent (string|null)
 *   text           (string|null)
 *   align          (string) 'left' | 'center'. Default 'left'.
 *   dark           (bool)
 *   tag            (string) Default 'h2'.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$data = anchor_get_template_data();

// Delegate rendering to the heading-group component.
anchor_render_component( 'heading-group', $data );
