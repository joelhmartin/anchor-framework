<?php
/**
 * REST API — Menu management endpoints.
 *
 * Wraps WordPress menu functions so the AI can read and modify nav menus.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_Menus {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes() {
        $ns = 'anchor-assistant/v1';

        // Get all menus and their items.
        register_rest_route( $ns, '/menus', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_menus' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Get a specific menu's items.
        register_rest_route( $ns, '/menus/(?P<location>[a-z_-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'get_menu' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Update a menu (replace all items).
        register_rest_route( $ns, '/menus/(?P<location>[a-z_-]+)', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_menu' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Add a single item to a menu.
        register_rest_route( $ns, '/menus/(?P<location>[a-z_-]+)/items', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'add_item' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );

        // Delete a single menu item.
        register_rest_route( $ns, '/menus/items/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'delete_item' ],
            'permission_callback' => [ $this, 'check_perm' ],
        ] );
    }

    public function check_perm() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get all registered menu locations and their items.
     */
    public function get_menus() {
        $locations = get_nav_menu_locations();
        $result    = [];

        foreach ( $locations as $location => $menu_id ) {
            $menu = wp_get_nav_menu_object( $menu_id );
            if ( ! $menu ) continue;

            $result[ $location ] = [
                'name'  => $menu->name,
                'id'    => $menu_id,
                'items' => $this->format_items( wp_get_nav_menu_items( $menu_id ) ?: [] ),
            ];
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get a single menu by location.
     */
    public function get_menu( $request ) {
        $location  = $request['location'];
        $locations = get_nav_menu_locations();

        if ( empty( $locations[ $location ] ) ) {
            return new WP_REST_Response( [ 'error' => "Menu location '{$location}' not found." ], 404 );
        }

        $menu_id = $locations[ $location ];
        $items   = wp_get_nav_menu_items( $menu_id ) ?: [];

        return rest_ensure_response( [
            'location' => $location,
            'menu_id'  => $menu_id,
            'items'    => $this->format_items( $items ),
            'tree'     => $this->build_tree( $items ),
        ] );
    }

    /**
     * Replace all items in a menu from a tree structure.
     */
    public function save_menu( $request ) {
        $location  = $request['location'];
        $locations = get_nav_menu_locations();

        if ( empty( $locations[ $location ] ) ) {
            return new WP_REST_Response( [ 'error' => "Menu location '{$location}' not found." ], 404 );
        }

        $menu_id = $locations[ $location ];
        $tree    = $request->get_json_params();

        if ( ! is_array( $tree ) ) {
            return new WP_REST_Response( [ 'error' => 'Expected array of menu items.' ], 400 );
        }

        // Remove all existing items.
        $existing = wp_get_nav_menu_items( $menu_id ) ?: [];
        foreach ( $existing as $item ) {
            wp_delete_post( $item->ID, true );
        }

        // Add new items from tree.
        $this->insert_tree( $menu_id, $tree, 0, 1 );

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Add a single item to a menu.
     */
    public function add_item( $request ) {
        $location  = $request['location'];
        $locations = get_nav_menu_locations();

        if ( empty( $locations[ $location ] ) ) {
            return new WP_REST_Response( [ 'error' => "Menu location '{$location}' not found." ], 404 );
        }

        $menu_id = $locations[ $location ];
        $params  = $request->get_json_params();

        $item_data = [
            'menu-item-title'     => sanitize_text_field( $params['title'] ?? '' ),
            'menu-item-url'       => esc_url_raw( $params['url'] ?? '' ),
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => absint( $params['parent_id'] ?? 0 ),
            'menu-item-position'  => absint( $params['position'] ?? 0 ),
        ];

        // If linking to a page, use page object type.
        if ( ! empty( $params['page_id'] ) ) {
            $item_data['menu-item-object-id'] = absint( $params['page_id'] );
            $item_data['menu-item-object']    = 'page';
            $item_data['menu-item-type']      = 'post_type';
        } else {
            $item_data['menu-item-type'] = 'custom';
        }

        $result = wp_update_nav_menu_item( $menu_id, 0, $item_data );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( [ 'success' => true, 'item_id' => $result ] );
    }

    /**
     * Delete a menu item.
     */
    public function delete_item( $request ) {
        $id     = absint( $request['id'] );
        $result = wp_delete_post( $id, true );
        return rest_ensure_response( [ 'success' => (bool) $result ] );
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function format_items( $items ) {
        return array_map( function( $item ) {
            return [
                'id'        => $item->ID,
                'title'     => $item->title,
                'url'       => $item->url,
                'parent_id' => (int) $item->menu_item_parent,
                'type'      => $item->type,
                'object'    => $item->object,
                'object_id' => (int) $item->object_id,
                'position'  => (int) $item->menu_order,
            ];
        }, $items );
    }

    private function build_tree( $items ) {
        $map  = [];
        $tree = [];

        foreach ( $items as $item ) {
            $node = [
                'id'       => $item->ID,
                'title'    => $item->title,
                'url'      => $item->url,
                'children' => [],
            ];
            $map[ $item->ID ] = $node;

            if ( (int) $item->menu_item_parent === 0 ) {
                $tree[] = &$map[ $item->ID ];
            } else {
                $parent_id = (int) $item->menu_item_parent;
                if ( isset( $map[ $parent_id ] ) ) {
                    $map[ $parent_id ]['children'][] = &$map[ $item->ID ];
                }
            }
        }

        return $tree;
    }

    private function insert_tree( $menu_id, $items, $parent_id, &$position ) {
        foreach ( $items as $item ) {
            $item_data = [
                'menu-item-title'     => sanitize_text_field( $item['title'] ?? '' ),
                'menu-item-url'       => esc_url_raw( $item['url'] ?? '' ),
                'menu-item-status'    => 'publish',
                'menu-item-parent-id' => $parent_id,
                'menu-item-position'  => $position++,
                'menu-item-type'      => 'custom',
            ];

            $new_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

            if ( ! is_wp_error( $new_id ) && ! empty( $item['children'] ) ) {
                $this->insert_tree( $menu_id, $item['children'], $new_id, $position );
            }
        }
    }
}
