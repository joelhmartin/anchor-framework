<?php
/**
 * Page Template
 *
 * Rendering order:
 * 1. Direct-PHP page at `{child-theme}/page-content/{slug}.php` (preferred).
 * 2. Config-driven sections via `anchor_render_sections()`.
 * 3. Standard single-page fallback (title + the_content).
 *
 * @package Anchor_Framework
 */

get_header();

$slug = anchor_determine_page_slug();

if ( $slug && function_exists( 'anchor_load_page_content' ) && anchor_load_page_content( $slug ) ) {
	// Direct-PHP page rendered by the loader. Nothing more to do.
} else {
	$config = $slug ? anchor_get_page_config( $slug ) : null;

	if ( $config && ! empty( $config['sections'] ) ) {
		anchor_render_sections( $config['sections'] );
	} else {
		?>
		<main class="anchor-main">
			<div class="anchor-hero anchor-hero--short anchor-hero--solid">
				<div class="anchor-hero__content anchor-hero__content--short anchor-section-pad">
					<h1 class="anchor-hero__heading anchor-hero__heading--page">
						<?php the_title(); ?>
					</h1>
				</div>
			</div>

			<div class="anchor-section anchor-section-pad">
				<div class="anchor-container anchor-page__container">
					<div class="anchor-article">
						<?php while ( have_posts() ) : the_post(); ?>
							<?php the_content(); ?>
						<?php endwhile; ?>
					</div>
				</div>
			</div>
		</main>
		<?php
	}
}

get_footer();
