<?php
/**
 * Archive Template (Blog)
 * Featured post hero + grid with sidebar + pagination.
 *
 * @package Anchor_Framework
 */

get_header();

$blog_config   = anchor_get_page_config( 'blog' );
$sidebar       = $blog_config['sidebar'] ?? [];
$show_featured = $blog_config['show_featured'] ?? true;

// Build featured slides from sticky posts
$featured_slides = [];
$featured_ids    = [];
if ( $show_featured && ! is_paged() ) {
	$stickies = get_option( 'sticky_posts' );
	if ( $stickies ) {
		$sticky_query = new WP_Query( [
			'post__in'       => $stickies,
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );
		while ( $sticky_query->have_posts() ) {
			$sticky_query->the_post();
			$p = get_post();
			$featured_ids[] = $p->ID;
			$cats = get_the_category( $p->ID );
			$badges = [ 'Featured' ];
			if ( $cats ) $badges[] = $cats[0]->name;

			$featured_slides[] = [
				'title'   => get_the_title( $p ),
				'excerpt' => get_the_excerpt( $p ),
				'image'   => get_the_post_thumbnail_url( $p->ID, 'full' ),
				'url'     => get_permalink( $p ),
				'badges'  => $badges,
				'meta'    => get_the_date( 'F j, Y', $p ),
			];
		}
		wp_reset_postdata();
	}
}
?>

<!-- Featured hero carousel (page 1 only) -->
<?php if ( ! empty( $featured_slides ) && ! is_paged() ) : ?>
	<?php anchor_render_component( 'hero-carousel', [ 'slides' => $featured_slides ] ); ?>
<?php endif; ?>

<!-- Grid + Sidebar -->
<div class="anchor-section anchor-section-pad">
	<div class="anchor-container">
		<!-- Header -->
		<div class="anchor-archive__header">
			<div>
				<span class="anchor-badge anchor-badge--brand anchor-archive__badge">Blog</span>
				<h2 class="anchor-archive__title">
					Insights & <span class="anchor-text-drama anchor-archive__drama">Resources</span>
				</h2>
			</div>
		</div>

		<div class="anchor-sidebar-layout">
			<!-- Main -->
			<div>
				<?php if ( have_posts() ) : ?>
				<div class="anchor-grid anchor-grid--2 anchor-reveal-stagger">
					<?php while ( have_posts() ) : the_post(); ?>
						<?php if ( ! empty( $featured_ids ) && in_array( get_the_ID(), $featured_ids ) && ! is_paged() ) continue; ?>
						<div>
							<a href="<?php the_permalink(); ?>" class="anchor-post-card">
								<?php if ( has_post_thumbnail() ) : ?>
								<div class="anchor-post-card__image">
									<?php the_post_thumbnail( 'medium_large' ); ?>
									<?php $cats = get_the_category(); ?>
									<?php if ( $cats ) : ?>
										<div class="anchor-post-card__category">
											<span class="anchor-badge anchor-post-card__category-badge"><?php echo esc_html( $cats[0]->name ); ?></span>
										</div>
									<?php endif; ?>
								</div>
								<?php endif; ?>
								<div class="anchor-post-card__body">
									<div class="anchor-post-card__meta">
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
				<div class="anchor-archive__empty">
					<p class="anchor-archive__empty-text">No posts found.</p>
				</div>
				<?php endif; ?>
			</div>

			<!-- Sidebar -->
			<div class="anchor-hide-mobile">
				<div class="anchor-sidebar__sticky">
					<?php anchor_render_component( 'content-sidebar', [
						'title'    => $sidebar['title'] ?? 'Get in Touch',
						'subtitle' => $sidebar['subtitle'] ?? '',
					] ); ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php get_footer(); ?>
