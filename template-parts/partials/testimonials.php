<?php
/**
 * Testimonials partial.
 *
 * Drop-in replacement for `'global:testimonials'` in the config system.
 *
 * Usage:
 *   get_template_part( 'template-parts/partials/testimonials' );
 *
 * With overrides:
 *   get_template_part( 'template-parts/partials/testimonials', null, [
 *       'props' => [ 'items' => [ [ 'quote' => '...', 'author' => '...' ] ] ],
 *   ] );
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$overrides = ( isset( $args ) && is_array( $args ) ) ? $args : [];
$prop_over = ( isset( $overrides['props'] ) && is_array( $overrides['props'] ) ) ? $overrides['props'] : [];

$section = [
	'type'         => 'testimonial_band',
	'variant'      => isset( $overrides['variant'] ) ? $overrides['variant'] : '',
	'flush_bottom' => ! empty( $overrides['flush_bottom'] ),
	'props'        => array_merge(
		[
			'eyebrow' => 'Testimonials',
			'heading' => 'What our clients say',
			'items'   => [
				[
					'quote'  => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas sed diam eget risus varius blandit.',
					'author' => 'Client Name',
					'role'   => 'Patient',
				],
				[
					'quote'  => 'Praesent commodo cursus magna, vel scelerisque nisl consectetur et. Donec sed odio dui.',
					'author' => 'Another Client',
					'role'   => 'Patient',
				],
				[
					'quote'  => 'Cras mattis consectetur purus sit amet fermentum. Vestibulum id ligula porta felis euismod.',
					'author' => 'Third Client',
					'role'   => 'Patient',
				],
			],
		],
		$prop_over
	),
];

if ( function_exists( 'anchor_render_section' ) ) {
	anchor_render_section( $section );
}
