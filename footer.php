<?php
// Phase 4C: detect per-page no-footer marker. When set, the global CTA
// band, footer-nav, and sticky-footer template-parts are skipped, but
// wp_footer() and the closing </body></html> still run.
$__anchor_no_footer = false;
if (
    function_exists( 'anchor_determine_page_slug' )
    && class_exists( 'Anchor_Editor_Page_Flags' )
) {
    $__anchor_slug = anchor_determine_page_slug();
    if ( $__anchor_slug ) {
        $__flags = Anchor_Editor_Page_Flags::get_flags( $__anchor_slug );
        $__anchor_no_footer = ! empty( $__flags['no_footer'] );
    }
}

if ( ! $__anchor_no_footer ) {
    // Global expanding CTA — renders on every page above the footer
    $globals = function_exists( 'anchor_get_globals_config' ) ? anchor_get_globals_config() : [];
    if ( ! empty( $globals['cta_band'] ) ) {
        anchor_render_sections( [ 'global:cta_band' ] );
    }

    get_template_part( 'template-parts/navigation/footer-nav' );

    get_template_part( 'template-parts/components/sticky-footer' );
}
?>

<?php wp_footer(); ?>
</body>
</html>
