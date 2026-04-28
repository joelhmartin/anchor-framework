<?php
/**
 * Front Page Template
 * Loads page config for 'home' and renders config-driven sections.
 *
 * @package Anchor_Framework
 */

get_header();

if ( function_exists( 'anchor_load_page_content' ) && anchor_load_page_content( 'home' ) ) {
	// Direct-PHP home page rendered by the loader.
} else {
	$config = anchor_get_page_config( 'home' );

	if ( $config && ! empty( $config['sections'] ) ) {
		anchor_render_sections( $config['sections'] );
	} else {
		// Fallback if no config
		echo '<main class="anchor-main anchor-section-pad"><div class="anchor-container">';
		while ( have_posts() ) {
			the_post();
			the_content();
		}
		echo '</div></main>';
	}
}

get_footer();
