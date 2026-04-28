<?php
/**
 * 404 Template
 * Simple not-found page with centered content and link home.
 *
 * @package Anchor_Framework
 */

get_header();
?>

<main class="anchor-main">
	<div class="anchor-404">
		<div class="anchor-404__inner">
			<!-- 404 number -->
			<div class="anchor-404__number">
				404
			</div>

			<h1 class="anchor-404__heading">
				Page Not Found
			</h1>

			<p class="anchor-404__text">
				The page you&rsquo;re looking for doesn&rsquo;t exist or has been moved. Let&rsquo;s get you back on track.
			</p>

			<div class="anchor-404__actions">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="anchor-btn anchor-btn--primary">
					<span>Back to Home</span>
					<?php echo anchor_icon( 'arrow-right', 14 ); ?>
				</a>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="anchor-btn anchor-btn--ghost">
					<span>Contact Us</span>
				</a>
			</div>
		</div>
	</div>
</main>

<?php get_footer(); ?>
