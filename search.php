<?php
/**
 * Search Results Template
 * Short hero with search query, results loop, and no-results state.
 *
 * @package Anchor_Framework
 */

get_header();
?>

<!-- Hero -->
<div class="anchor-hero anchor-hero--short anchor-hero--solid">
	<div class="anchor-hero__content anchor-hero__content--short anchor-section-pad">
		<span class="anchor-badge anchor-badge--glass anchor-hero__badge-nav">Search</span>
		<h1 class="anchor-hero__heading anchor-hero__heading--blog">
			<?php /* translators: %s: search query */ ?>
			Results for &ldquo;<?php echo esc_html( get_search_query() ); ?>&rdquo;
		</h1>
		<p class="anchor-hero__subtitle--search">
			<?php
			global $wp_query;
			$result_count = $wp_query->found_posts;
			printf(
				/* translators: %d: number of results */
				esc_html( _n( '%d result found', '%d results found', $result_count, 'anchor-framework' ) ),
				intval( $result_count )
			);
			?>
		</p>
	</div>
</div>

<!-- Results -->
<div class="anchor-section anchor-section-pad">
	<div class="anchor-container anchor-search-container">
		<?php if ( have_posts() ) : ?>
		<div class="anchor-grid anchor-grid--2 anchor-reveal-stagger">
			<?php while ( have_posts() ) : the_post(); ?>
				<div>
					<a href="<?php the_permalink(); ?>" class="anchor-post-card">
						<?php if ( has_post_thumbnail() ) : ?>
						<div class="anchor-post-card__image">
							<?php the_post_thumbnail( 'medium_large' ); ?>
						</div>
						<?php endif; ?>
						<div class="anchor-post-card__body">
							<div class="anchor-post-card__meta">
								<span class="anchor-post-card__meta-type"><?php echo esc_html( get_post_type() ); ?></span>
								<span><?php echo anchor_icon( 'calendar', 12 ); ?> <?php echo esc_html( get_the_date( 'F j, Y' ) ); ?></span>
							</div>
							<h3 class="anchor-post-card__title"><?php the_title(); ?></h3>
							<p class="anchor-archive__excerpt anchor-line-clamp-2"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<span class="anchor-link-arrow anchor-archive__link-arrow">
								Read More <?php echo anchor_icon( 'arrow-right', 14 ); ?>
							</span>
						</div>
					</a>
				</div>
			<?php endwhile; ?>
		</div>

		<!-- Pagination -->
		<?php
		$total_pages = $GLOBALS['wp_query']->max_num_pages;
		if ( $total_pages > 1 ) :
			$current = max( 1, get_query_var( 'paged' ) );
		?>
		<nav class="anchor-pagination">
			<?php if ( $current > 1 ) : ?>
				<a href="<?php echo esc_url( get_pagenum_link( $current - 1 ) ); ?>" class="anchor-pagination__item"><?php echo anchor_icon( 'chevron-left', 16 ); ?></a>
			<?php else : ?>
				<span class="anchor-pagination__item anchor-pagination__item--disabled"><?php echo anchor_icon( 'chevron-left', 16 ); ?></span>
			<?php endif; ?>

			<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
				<a href="<?php echo esc_url( get_pagenum_link( $i ) ); ?>" class="anchor-pagination__item <?php echo $i === $current ? 'anchor-pagination__item--active' : ''; ?>">
					<?php echo esc_html( $i ); ?>
				</a>
			<?php endfor; ?>

			<?php if ( $current < $total_pages ) : ?>
				<a href="<?php echo esc_url( get_pagenum_link( $current + 1 ) ); ?>" class="anchor-pagination__item"><?php echo anchor_icon( 'chevron-right', 16 ); ?></a>
			<?php else : ?>
				<span class="anchor-pagination__item anchor-pagination__item--disabled"><?php echo anchor_icon( 'chevron-right', 16 ); ?></span>
			<?php endif; ?>
		</nav>
		<?php endif; ?>

		<?php else : ?>
		<!-- No results -->
		<div class="anchor-search__empty">
			<div class="anchor-search__empty-icon">
				<?php echo anchor_icon( 'search', 24 ); ?>
			</div>
			<h2 class="anchor-search__empty-heading">
				No results found
			</h2>
			<p class="anchor-search__empty-text">
				We couldn&rsquo;t find anything matching your search. Try different keywords or browse our latest content.
			</p>
			<div class="anchor-search__empty-actions">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="anchor-btn anchor-btn--primary">
					<span>Back to Home</span>
					<?php echo anchor_icon( 'arrow-right', 14 ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="anchor-btn anchor-btn--ghost">
					<span>Browse Blog</span>
				</a>
			</div>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php get_footer(); ?>
