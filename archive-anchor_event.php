<?php
/**
 * Event Archive Template
 * Hero from events config + event grid sorted by event date ascending.
 *
 * @package Anchor_Framework
 */

get_header();

$events_config = anchor_get_page_config( 'events' );
$sidebar       = $events_config['sidebar'] ?? [];

// Render config-driven hero sections if available
if ( $events_config && ! empty( $events_config['sections'] ) ) {
	// Render only hero-type sections from config (first section assumed to be hero)
	$hero_sections = [];
	foreach ( $events_config['sections'] as $section ) {
		if ( is_string( $section ) ) {
			$hero_sections[] = $section;
			break;
		}
		if ( is_array( $section ) && isset( $section['component'] ) ) {
			$type = $section['component'];
			if ( stripos( $type, 'hero' ) !== false ) {
				$hero_sections[] = $section;
				break;
			}
		}
		// Take first section as hero regardless
		$hero_sections[] = $section;
		break;
	}
	if ( $hero_sections ) {
		anchor_render_sections( $hero_sections );
	}
} else {
	// Default hero
	?>
	<div class="anchor-hero anchor-hero--short anchor-hero--solid">
		<div class="anchor-hero__content anchor-hero__content--short anchor-section-pad">
		<div class="anchor-container">
			<span class="anchor-badge anchor-badge--accent anchor-hero__badge-nav">Events</span>
			<h1 class="anchor-hero__heading anchor-hero__heading--page">
				Upcoming Events
			</h1>
			<p class="anchor-hero__subtitle">
				<?php echo esc_html( anchor_get_site( 'description', 'Stay up to date with our upcoming events.' ) ); ?>
			</p>
		</div>
		</div>
	</div>
	<?php
}
?>

<!-- Event Grid + Sidebar -->
<div class="anchor-section anchor-section-pad">
	<div class="anchor-container">
		<div class="anchor-sidebar-layout">
			<!-- Main -->
			<div>
				<?php if ( have_posts() ) : ?>
				<div class="anchor-grid anchor-grid--2 anchor-reveal-stagger">
					<?php while ( have_posts() ) : the_post();
						$event_date     = get_post_meta( get_the_ID(), 'anchor_event_date', true );
						$event_time     = get_post_meta( get_the_ID(), 'anchor_event_time', true );
						$event_location = get_post_meta( get_the_ID(), 'anchor_event_location', true );
					?>
						<div>
							<a href="<?php the_permalink(); ?>" class="anchor-post-card">
								<?php if ( has_post_thumbnail() ) : ?>
								<div class="anchor-post-card__image">
									<?php the_post_thumbnail( 'medium_large' ); ?>
								</div>
								<?php endif; ?>
								<div class="anchor-post-card__body">
									<div class="anchor-post-card__meta anchor-event-meta">
										<?php if ( $event_date ) : ?>
										<span class="anchor-event-meta__item">
											<?php echo anchor_icon( 'calendar', 12 ); ?>
											<?php echo esc_html( $event_date ); ?>
										</span>
										<?php endif; ?>
										<?php if ( $event_time ) : ?>
										<span class="anchor-event-meta__item">
											<?php echo anchor_icon( 'clock', 12 ); ?>
											<?php echo esc_html( $event_time ); ?>
										</span>
										<?php endif; ?>
									</div>
									<h3 class="anchor-post-card__title"><?php the_title(); ?></h3>
									<?php if ( $event_location ) : ?>
									<p class="anchor-event-meta__location">
										<?php echo anchor_icon( 'map-pin', 12 ); ?>
										<?php echo esc_html( $event_location ); ?>
									</p>
									<?php endif; ?>
									<p class="anchor-event-meta__excerpt anchor-line-clamp-2"><?php echo esc_html( get_the_excerpt() ); ?></p>
									<span class="anchor-link-arrow anchor-archive__link-arrow">
										View Event <?php echo anchor_icon( 'arrow-right', 14 ); ?>
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
					<p class="anchor-archive__empty-text">No upcoming events found.</p>
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

<!-- Global CTA -->
<?php
$globals = anchor_get_globals_config();
if ( ! empty( $globals['cta_band'] ) ) {
	anchor_render_sections( [ 'global:cta_band' ] );
}
?>

<?php get_footer(); ?>
