<?php
// Phase 4C: detect per-page no-header marker. When set, the navigation
// template-part is skipped below but the standard <head>/<body> opening
// still runs so wp_head(), language_attributes(), and body_class() emit
// as usual.
$__anchor_no_header = false;
if (
    function_exists( 'anchor_determine_page_slug' )
    && class_exists( 'Anchor_Editor_Page_Flags' )
) {
    $__anchor_slug = anchor_determine_page_slug();
    if ( $__anchor_slug ) {
        $__flags = Anchor_Editor_Page_Flags::get_flags( $__anchor_slug );
        $__anchor_no_header = ! empty( $__flags['no_header'] );
    }
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>
</head>
<body <?php body_class( $__anchor_no_header ? 'anchor-no-header' : '' ); ?>>
<?php wp_body_open(); ?>

<?php
if ( ! $__anchor_no_header ) {
    get_template_part( 'template-parts/navigation/header-nav' );
}
