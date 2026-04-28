<?php
/**
 * Single Event Template
 * Event post with hero, article body, sidebar, and CTA.
 *
 * @package Anchor_Framework
 */

get_header();

$events_config = anchor_get_page_config( 'events' );
$sidebar       = $events_config['sidebar'] ?? [];

while ( have_posts() ) : the_post();
	$categories     = get_the_category();
	$category_name  = ! empty( $categories ) ? $categories[0]->name : '';
	$featured_image = get_the_post_thumbnail_url( get_the_ID(), 'full' );

	// Event meta
	$event_date     = get_post_meta( get_the_ID(), 'anchor_event_date', true );
	$event_time     = get_post_meta( get_the_ID(), 'anchor_event_time', true );
	$event_location = get_post_meta( get_the_ID(), 'anchor_event_location', true );
?>

<!-- Hero -->
<div class="anchor-hero anchor-hero--blog<?php echo ! $featured_image ? ' anchor-hero--solid' : ''; ?>">
	<?php if ( $featured_image ) : ?>
	<div class="anchor-hero__bg">
		<img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php the_title_attribute(); ?>">
		<div class="anchor-hero__overlay anchor-hero__overlay--gradient-top"></div>
	</div>
	<?php endif; ?>

	<div class="anchor-hero__content anchor-hero__content--short anchor-section-pad">
	<div class="anchor-container">
	<div class="anchor-hero__inner">
		<a href="<?php echo esc_url( get_post_type_archive_link( 'anchor_event' ) ?: home_url( '/events/' ) ); ?>"
		   class="anchor-badge anchor-badge--glass anchor-hero__badge-nav">
			<?php echo anchor_icon( 'arrow-left', 12 ); ?>
			<span>All Events</span>
		</a>

		<?php if ( $category_name ) : ?>
		<div class="anchor-hero__badge-wrap">
			<span class="anchor-badge anchor-badge--accent"><?php echo esc_html( $category_name ); ?></span>
		</div>
		<?php endif; ?>

		<h1 class="anchor-hero__heading anchor-hero__heading--blog">
			<?php the_title(); ?>
		</h1>
	</div>
	</div>
	</div>
</div>

<!-- Article + Sidebar -->
<div class="anchor-section anchor-section-pad">
	<div class="anchor-container">
		<div class="anchor-sidebar-layout">
			<!-- Main content -->
			<div class="anchor-article__main">
				<!-- Meta bar -->
				<div class="anchor-article__meta-bar">
					<?php if ( $event_date ) : ?>
					<span class="anchor-article__meta-item">
						<?php echo anchor_icon( 'calendar', 15 ); ?>
						<?php echo esc_html( $event_date ); ?>
					</span>
					<?php endif; ?>
					<?php if ( $event_time ) : ?>
					<span class="anchor-article__meta-item">
						<?php echo anchor_icon( 'clock', 15 ); ?>
						<?php echo esc_html( $event_time ); ?>
					</span>
					<?php endif; ?>
					<?php if ( $event_location ) : ?>
					<span class="anchor-article__meta-item">
						<?php echo anchor_icon( 'map-pin', 15 ); ?>
						<?php echo esc_html( $event_location ); ?>
					</span>
					<?php endif; ?>
				</div>

				<!-- Body -->
				<article class="anchor-article">
					<?php the_content(); ?>
				</article>

				<!-- Bottom CTA card -->
				<div class="anchor-article__cta-card">
					<h3 class="anchor-article__cta-heading">
						Interested in this event?
					</h3>
					<p class="anchor-article__cta-text">
						<?php echo esc_html( anchor_get_site( 'description', 'Get in touch with us today.' ) ); ?>
					</p>
					<a href="<?php echo esc_url( anchor_get_site( 'default_cta.url', '/contact/' ) ); ?>"
					   class="anchor-btn anchor-btn--white anchor-article__cta-btn">
						<span><?php echo esc_html( anchor_get_site( 'default_cta.label', 'Get in Touch' ) ); ?></span>
						<?php echo anchor_icon( 'arrow-right', 14 ); ?>
					</a>
				</div>

				<!-- Back link -->
				<div class="anchor-article__back-wrap">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'anchor_event' ) ?: home_url( '/events/' ) ); ?>" class="anchor-link-arrow anchor-article__back-link">
						<?php echo anchor_icon( 'arrow-left', 14 ); ?>
						Back to Events
					</a>
				</div>
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

<?php endwhile; ?>

<?php get_footer(); ?>
