<?php
// Global expanding CTA — renders on every page above the footer
$globals = function_exists( 'anchor_get_globals_config' ) ? anchor_get_globals_config() : [];
if ( ! empty( $globals['cta_band'] ) ) {
    anchor_render_sections( [ 'global:cta_band' ] );
}
?>

<?php get_template_part( 'template-parts/navigation/footer-nav' ); ?>

<?php get_template_part( 'template-parts/components/sticky-footer' ); ?>

<?php wp_footer(); ?>
</body>
</html>
