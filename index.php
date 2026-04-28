<?php
/**
 * The main template file.
 *
 * Fallback for any template that is not matched by a more specific file.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

get_header(); ?>
<main class="anchor-main anchor-section-pad">
    <div class="anchor-container">
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
            <article>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <?php the_excerpt(); ?>
            </article>
        <?php endwhile; else : ?>
            <p><?php esc_html_e( 'No content found.', 'anchor-framework' ); ?></p>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
