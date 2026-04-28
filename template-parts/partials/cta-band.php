<?php
/**
 * CTA Band partial.
 *
 * Drop-in replacement for `'global:cta_band'` in the config system.
 * Produces the same wrapper + section markup as the config renderer.
 *
 * Usage from a page-content/{slug}.php file:
 *   get_template_part( 'template-parts/partials/cta-band' );
 *
 * With overrides (WordPress 5.5+):
 *   get_template_part( 'template-parts/partials/cta-band', null, [
 *       'props'        => [ 'heading' => 'Different heading' ],
 *       'variant'      => 'card',
 *       'flush_bottom' => true,
 *   ] );
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overrides   = ( isset( $args ) && is_array( $args ) ) ? $args : [];
$prop_over   = ( isset( $overrides['props'] ) && is_array( $overrides['props'] ) ) ? $overrides['props'] : [];

$section = [
	'type'         => 'cta_band',
	'variant'      => isset( $overrides['variant'] ) ? $overrides['variant'] : 'expand',
	'flush_bottom' => ! empty( $overrides['flush_bottom'] ),
	'props'        => array_merge(
		[
			'eyebrow'          => 'Take the Next Step',
			'heading'          => 'Lorem ipsum dolor sit',
			'heading_accent'   => 'amet?',
			'text'             => 'Praesent commodo cursus magna, vel scelerisque nisl consectetur et. Donec sed odio dui. Maecenas faucibus mollis interdum.',
			'primary_cta'      => [ 'label' => 'Get in Touch',   'url' => '/contact/' ],
			'secondary_cta'    => [ 'label' => 'Learn About Us', 'url' => '/about/' ],
			'background_image' => 'cta_background',
		],
		$prop_over
	),
];

if ( function_exists( 'anchor_render_section' ) ) {
	anchor_render_section( $section );
}
