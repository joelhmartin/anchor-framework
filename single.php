<?php
/**
 * Single Post Template
 * Blog post with hero, article body, sidebar, related posts, and CTA.
 *
 * @package Anchor_Framework
 */

get_header();

$blog_config = anchor_get_page_config( 'blog' );
$sidebar     = $blog_config['sidebar'] ?? [];

while ( have_posts() ) : the_post();
	$categories     = get_the_category();
	$category_name  = ! empty( $categories ) ? $categories[0]->name : '';
	$featured_image = get_the_post_thumbnail_url( get_the_ID(), 'full' );

	// Estimate read time
	$content    = get_the_content();
	$word_count = str_word_count( wp_strip_all_tags( $content ) );
	$read_time  = max( 1, ceil( $word_count / 250 ) );
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
		<a href="<?php echo esc_url( get_post_type_archive_link( 'post' ) ?: home_url( '/blog/' ) ); ?>"
		   class="anchor-badge anchor-badge--glass anchor-hero__badge-nav">
			<?php echo anchor_icon( 'arrow-left', 12 ); ?>
			<span>All Posts</span>
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
					<span class="anchor-article__meta-item">
						<?php echo anchor_icon( 'calendar', 15 ); ?>
						<?php echo esc_html( get_the_date( 'F j, Y' ) ); ?>
					</span>
					<span class="anchor-article__meta-item">
						<?php echo anchor_icon( 'clock', 15 ); ?>
						<?php echo esc_html( $read_time ); ?> min read
					</span>
				</div>

				<!-- Body -->
				<article class="anchor-article">
					<?php the_content(); ?>
				</article>

				<!-- Tags -->
				<?php $tags = get_the_tags(); ?>
				<?php if ( $tags ) : ?>
				<div class="anchor-article__tags">
					<div class="anchor-article__tags-list">
						<?php echo anchor_icon( 'tag', 14 ); ?>
						<?php foreach ( $tags as $tag ) : ?>
							<span class="anchor-article__tag">
								<?php echo esc_html( $tag->name ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>

				<!-- Bottom CTA card -->
				<div class="anchor-article__cta-card">
					<h3 class="anchor-article__cta-heading">
						Need help?
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
					<a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="anchor-link-arrow anchor-article__back-link">
						<?php echo anchor_icon( 'arrow-left', 14 ); ?>
						Back to Blog
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

<!-- Related Posts -->
<?php
$related_args = [
	'post_type'      => 'post',
	'posts_per_page' => 2,
	'post__not_in'   => [ get_the_ID() ],
	'orderby'        => 'rand',
];
if ( ! empty( $categories ) ) {
	$related_args['cat'] = $categories[0]->term_id;
}
$related = new WP_Query( $related_args );
if ( $related->have_posts() ) :
?>
<div class="anchor-section anchor-section--surface anchor-section-pad">
	<div class="anchor-related">
		<h3 class="anchor-related__heading">Keep Reading</h3>
		<div class="anchor-grid anchor-grid--2">
			<?php while ( $related->have_posts() ) : $related->the_post(); ?>
			<a href="<?php the_permalink(); ?>" class="anchor-card anchor-card--link anchor-related__card">
				<?php if ( has_post_thumbnail() ) : ?>
				<div class="anchor-related__thumb">
					<?php the_post_thumbnail( 'thumbnail' ); ?>
				</div>
				<?php endif; ?>
				<div class="anchor-related__body">
					<?php $rel_cats = get_the_category(); ?>
					<?php if ( $rel_cats ) : ?>
						<span class="anchor-related__category"><?php echo esc_html( $rel_cats[0]->name ); ?></span>
					<?php endif; ?>
					<h4 class="anchor-related__title"><?php the_title(); ?></h4>
				</div>
			</a>
			<?php endwhile; ?>
		</div>
	</div>
</div>
<?php wp_reset_postdata(); endif; ?>

<?php endwhile; ?>

<?php get_footer(); ?>
