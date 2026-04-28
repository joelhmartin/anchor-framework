<?php
/**
 * Anchor Framework — Footer Navigation Walker
 *
 * Renders top-level menu items as column headings (h4)
 * and their children as link lists below.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Footer_Walker extends Walker_Nav_Menu {

    public function start_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '<ul class="anchor-footer__nav-list">';
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= '</ul></div>';
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        if ( 0 === $depth ) {
            // Top-level = column heading
            $output .= '<div>';
            $output .= sprintf(
                '<h4 class="anchor-footer__nav-title">%s</h4>',
                esc_html( $item->title )
            );
        } else {
            // Child = link
            $output .= sprintf(
                '<li><a href="%s" class="anchor-footer__nav-link">%s</a></li>',
                esc_url( $item->url ),
                esc_html( $item->title )
            );
        }
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        // Column divs closed in end_lvl
    }
}
