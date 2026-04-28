<?php
/**
 * Anchor Framework — Desktop Navigation Walker
 *
 * Custom walker for wp_nav_menu() that outputs the anchor nav markup:
 * - Regular links get .anchor-nav__link
 * - Items with children get a <button> toggle + dropdown panel
 * - Supports unlimited nesting depth
 *
 * @package Anchor_Framework
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Nav_Walker extends Walker_Nav_Menu {

    public function start_lvl( &$output, $depth = 0, $args = null ) {
        if ( 0 === $depth ) {
            $output .= '<div class="anchor-nav__dropdown"><div class="anchor-nav__dropdown-inner">';
        } else {
            $output .= '<div class="anchor-nav__sub-dropdown">';
        }
    }

    public function end_lvl( &$output, $depth = 0, $args = null ) {
        if ( 0 === $depth ) {
            $output .= '</div></div></div>'; // close dropdown-inner + dropdown + item-wrap
        } else {
            $output .= '</div>';
        }
    }

    public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $classes = empty( $item->classes ) ? [] : (array) $item->classes;
        $active  = in_array( 'current-menu-item', $classes ) || in_array( 'current-menu-ancestor', $classes );
        $has_children = in_array( 'menu-item-has-children', $classes );

        if ( $has_children && 0 === $depth ) {
            $link_class = 'anchor-nav__link' . ( $active ? ' anchor-nav__link--active' : '' );
            $output .= '<div class="anchor-nav__item-wrap">';
            $output .= sprintf(
                '<button class="%s" type="button" aria-expanded="false">%s <i class="fa-solid fa-chevron-down" style="font-size:10px;" aria-hidden="true"></i></button>',
                esc_attr( $link_class ),
                esc_html( $item->title )
            );
        } else {
            $url = ! empty( $item->url ) ? $item->url : '#';
            if ( $depth > 0 ) {
                $link_class = 'anchor-nav__dropdown-link' . ( $active ? ' anchor-nav__dropdown-link--active' : '' );
            } else {
                $link_class = 'anchor-nav__link' . ( $active ? ' anchor-nav__link--active' : '' );
            }
            $output .= sprintf(
                '<a href="%s" class="%s">%s</a>',
                esc_url( $url ),
                esc_attr( $link_class ),
                esc_html( $item->title )
            );
        }
    }

    public function end_el( &$output, $item, $depth = 0, $args = null ) {
        // Closing handled in end_lvl for parents
    }
}
