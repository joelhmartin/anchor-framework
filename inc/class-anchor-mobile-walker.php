<?php
/**
 * Anchor Framework — Mobile Navigation Walker
 *
 * Custom walker for the mobile overlay accordion menu.
 * Items with children render as toggle buttons with nested accordion panels.
 * Supports unlimited nesting depth.
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Mobile_Walker extends Walker_Nav_Menu {

    public function start_lvl( &$output, $depth = 0, $args = null ) {
        // The sub panel is opened in start_el for parent items
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        $output .= "</div></div>\n";
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes      = empty( $item->classes ) ? [] : (array) $item->classes;
        $has_children = in_array( 'menu-item-has-children', $classes );
        $active       = in_array( 'current-menu-item', $classes );
        $slug         = sanitize_title( $item->title );

        if ( $has_children ) {
            $sub_id = 'mobile-sub-' . $slug;
            $output .= '<div>';
            $output .= sprintf(
                '<button class="anchor-mobile-overlay__link anchor-mobile-overlay__toggle" type="button" data-target="%s">%s <span class="anchor-mobile-overlay__toggle-icon"><i class="fa-solid fa-chevron-down" style="font-size:16px;" aria-hidden="true"></i></span></button>',
                esc_attr( $sub_id ),
                esc_html( $item->title )
            );
            $output .= sprintf( '<div class="anchor-mobile-overlay__sub" id="%s">', esc_attr( $sub_id ) );
        } else {
            $url = ! empty( $item->url ) ? $item->url : '#';
            if ( $depth > 0 ) {
                $output .= sprintf(
                    '<a href="%s" class="anchor-mobile-overlay__sub-link">%s</a>',
                    esc_url( $url ),
                    esc_html( $item->title )
                );
            } else {
                $active_class = $active ? ' anchor-mobile-overlay__link--active' : '';
                $output .= sprintf(
                    '<a href="%s" class="anchor-mobile-overlay__link%s">%s</a>',
                    esc_url( $url ),
                    esc_attr( $active_class ),
                    esc_html( $item->title )
                );
            }
        }
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        // Parent wrappers closed in end_lvl
    }
}
