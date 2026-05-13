<?php
/**
 * REST API — AI chat endpoint.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_AI {

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
        // Chat endpoint.
        register_rest_route( 'anchor-assistant/v1', '/ai/chat', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'chat' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );

        // API key management.
        register_rest_route( 'anchor-assistant/v1', '/ai/settings', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'save_settings' ],
                'permission_callback' => function() {
                    return current_user_can( 'manage_options' );
                },
            ],
        ] );

        // Apply config from AI response.
        register_rest_route( 'anchor-assistant/v1', '/ai/apply', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'apply_config' ],
            'permission_callback' => function() {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    /**
     * Handle a chat message.
     */
    public function chat( $request ) {
        $params       = $request->get_json_params();
        $message      = sanitize_textarea_field( $params['message'] ?? '' );
        $history      = $params['history'] ?? [];
        $page_slug    = sanitize_text_field( $params['page_slug'] ?? '' );
        $config_key   = sanitize_text_field( $params['config_key'] ?? '' );
        $post_context          = $params['post_context'] ?? null;
        $selected_section_type = sanitize_key( $params['selected_section_type'] ?? '' );

        if ( empty( $message ) ) {
            return new WP_REST_Response( [ 'error' => 'Message is required.' ], 400 );
        }

        $result = Anchor_AI_Handler::instance()->chat(
            $message,
            $history,
            $page_slug ?: null,
            $config_key ?: null,
            $post_context,
            $selected_section_type ?: null
        );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( $result );
    }

    /**
     * Get AI settings (key configured status, not the key itself).
     */
    public function get_settings() {
        $handler = Anchor_AI_Handler::instance();
        return rest_ensure_response( [
            'configured' => $handler->is_configured(),
            'model'      => Anchor_AI_Handler::MODEL,
        ] );
    }

    /**
     * Save AI settings.
     */
    public function save_settings( $request ) {
        $params = $request->get_json_params();

        if ( isset( $params['api_key'] ) ) {
            Anchor_AI_Handler::instance()->set_api_key( $params['api_key'] );
        }

        return rest_ensure_response( [
            'success'    => true,
            'configured' => Anchor_AI_Handler::instance()->is_configured(),
        ] );
    }

    /**
     * Apply a config change from the AI response.
     */
    public function apply_config( $request ) {
        $params     = $request->get_json_params();
        $config     = $params['config'] ?? null;
        $page_slug  = sanitize_text_field( $params['page_slug'] ?? '' );
        $config_key = sanitize_text_field( $params['config_key'] ?? '' );

        // Direct-PHP file write: payload is { file_write: { slug, contents } }
        // or the simpler form { file_contents: '...' } with page_slug on the request.
        $file_write = $params['file_write'] ?? null;
        if ( is_array( $file_write ) ) {
            return $this->apply_file_write(
                (string) ( $file_write['slug'] ?? $page_slug ),
                (string) ( $file_write['contents'] ?? '' )
            );
        }
        if ( isset( $params['file_contents'] ) ) {
            return $this->apply_file_write( $page_slug, (string) $params['file_contents'] );
        }

        // CSS file write: { css_write: { file: 'site.css', contents: '...' } }
        $css_write = $params['css_write'] ?? null;
        if ( is_array( $css_write ) ) {
            return $this->apply_css_write(
                sanitize_file_name( $css_write['file'] ?? '' ),
                (string) ( $css_write['contents'] ?? '' )
            );
        }

        if ( ! is_array( $config ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid config data.' ], 400 );
        }

        // Handle menu actions.
        if ( ! empty( $config['menu_action'] ) ) {
            return $this->apply_menu_action( $config );
        }

        // Handle post content actions.
        if ( ! empty( $config['post_action'] ) ) {
            return $this->apply_post_action( $config );
        }

        $cm = Anchor_Editor_Config_Manager::instance();

        if ( $page_slug ) {
            $result = $cm->save_page( $page_slug, $config );
        } elseif ( $config_key ) {
            switch ( $config_key ) {
                case 'site':    $result = $cm->save_site( $config ); break;
                case 'data':    $result = $cm->save_data( $config ); break;
                case 'globals': $result = $cm->save_globals( $config ); break;
                case 'media':   $result = $cm->save_media( $config ); break;
                default:        $result = new WP_Error( 'invalid_config', 'Unknown config key.' );
            }
        } else {
            return new WP_REST_Response( [ 'error' => 'No target specified.' ], 400 );
        }

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Write the AI-authored PHP/HTML to page-content/{slug}.php.
     */
    private function apply_file_write( $slug, $contents ) {
        if ( '' === $slug ) {
            return new WP_REST_Response( [ 'error' => 'No page slug on file_write.' ], 400 );
        }
        if ( '' === $contents ) {
            return new WP_REST_Response( [ 'error' => 'Empty file contents.' ], 400 );
        }

        if ( ! class_exists( 'Anchor_Editor_File_Writer' ) ) {
            return new WP_REST_Response( [ 'error' => 'File writer unavailable.' ], 500 );
        }

        $result = Anchor_Editor_File_Writer::write_page( $slug, $contents );
        if ( is_wp_error( $result ) ) {
            $code   = $result->get_error_code();
            $status = 'syntax_error' === $code ? 422 : 500;
            return new WP_REST_Response(
                [
                    'error' => $result->get_error_message(),
                    'code'  => $code,
                ],
                $status
            );
        }

        return rest_ensure_response(
            [
                'success' => true,
                'slug'    => $slug,
                'path'    => $result['path'],
                'target'  => 'page-content',
            ]
        );
    }

    /**
     * Handle menu actions from the AI.
     */
    private function apply_menu_action( $config ) {
        $action   = $config['menu_action'];
        $location = sanitize_text_field( $config['location'] ?? 'primary' );
        $locations = get_nav_menu_locations();

        if ( empty( $locations[ $location ] ) ) {
            return new WP_REST_Response( [ 'error' => "Menu '{$location}' not found." ], 404 );
        }

        $menu_id = $locations[ $location ];

        switch ( $action ) {
            case 'add_item':
                $item_data = [
                    'menu-item-title'     => sanitize_text_field( $config['title'] ?? '' ),
                    'menu-item-url'       => esc_url_raw( $config['url'] ?? '' ),
                    'menu-item-status'    => 'publish',
                    'menu-item-parent-id' => absint( $config['parent_id'] ?? 0 ),
                    'menu-item-type'      => 'custom',
                ];
                $result = wp_update_nav_menu_item( $menu_id, 0, $item_data );
                if ( is_wp_error( $result ) ) {
                    return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
                }
                return rest_ensure_response( [ 'success' => true, 'item_id' => $result ] );

            case 'delete_item':
                // Constrain deletion to actual items in the selected menu — a hallucinated
                // payload from the AI must not be able to delete arbitrary posts by ID.
                $id = absint( $config['id'] ?? 0 );
                if ( ! $id || 'nav_menu_item' !== get_post_type( $id ) ) {
                    return new WP_REST_Response( [ 'error' => 'Menu item not found.' ], 404 );
                }
                $menu_item_ids = wp_list_pluck( wp_get_nav_menu_items( $menu_id ) ?: [], 'ID' );
                if ( ! in_array( $id, $menu_item_ids, true ) ) {
                    return new WP_REST_Response( [ 'error' => 'Menu item not in target menu.' ], 404 );
                }
                if ( false === wp_delete_post( $id, true ) ) {
                    return new WP_REST_Response( [ 'error' => 'Could not delete menu item.' ], 500 );
                }
                return rest_ensure_response( [ 'success' => true ] );

            case 'replace_menu':
                // Don't claim success if a delete or insert failed mid-replace —
                // partial state is worse than an outright failure for the caller.
                $tree     = $config['items'] ?? [];
                $existing = wp_get_nav_menu_items( $menu_id ) ?: [];
                foreach ( $existing as $item ) {
                    if ( false === wp_delete_post( $item->ID, true ) ) {
                        return new WP_REST_Response( [ 'error' => 'Could not clear existing menu items.' ], 500 );
                    }
                }
                $pos          = 1;
                $insert_error = $this->insert_menu_tree( $menu_id, $tree, 0, $pos );
                if ( is_wp_error( $insert_error ) ) {
                    return new WP_REST_Response( [ 'error' => $insert_error->get_error_message() ], 500 );
                }
                return rest_ensure_response( [ 'success' => true ] );

            default:
                return new WP_REST_Response( [ 'error' => 'Unknown menu action.' ], 400 );
        }
    }

    private function insert_menu_tree( $menu_id, $items, $parent_id, &$position ) {
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
            if ( is_wp_error( $new_id ) ) {
                return $new_id;
            }
            if ( ! empty( $item['children'] ) ) {
                $child_error = $this->insert_menu_tree( $menu_id, $item['children'], $new_id, $position );
                if ( is_wp_error( $child_error ) ) {
                    return $child_error;
                }
            }
        }
        return null;
    }

    /**
     * Handle post content actions (edit blog/event content).
     */
    private function apply_post_action( $config ) {
        $post_id = absint( $config['post_id'] ?? 0 );
        if ( ! $post_id ) {
            return new WP_REST_Response( [ 'error' => 'No post_id specified.' ], 400 );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return new WP_REST_Response( [ 'error' => 'Post not found.' ], 404 );
        }

        // Allowlist post types so a hallucinated post_id can't be used to mutate
        // arbitrary posts (pages, attachments, etc.). Default covers the
        // blog/event flows the editor was built for; sites can extend.
        $allowed_types = apply_filters(
            'anchor_editor/post_action/allowed_post_types',
            array( 'post', 'event' )
        );
        if ( ! in_array( $post->post_type, $allowed_types, true ) ) {
            return new WP_REST_Response(
                [ 'error' => 'Invalid post type for this endpoint.' ],
                403
            );
        }

        $update = [ 'ID' => $post_id ];

        if ( isset( $config['title'] ) ) {
            $update['post_title'] = sanitize_text_field( $config['title'] );
        }
        if ( isset( $config['content'] ) ) {
            $update['post_content'] = wp_kses_post( $config['content'] );
        }
        if ( isset( $config['excerpt'] ) ) {
            $update['post_excerpt'] = sanitize_textarea_field( $config['excerpt'] );
        }

        $result = wp_update_post( $update, true );

        if ( is_wp_error( $result ) ) {
            return new WP_REST_Response( [ 'error' => $result->get_error_message() ], 500 );
        }

        // Featured image.
        if ( isset( $config['featured_image_id'] ) ) {
            $img_id = absint( $config['featured_image_id'] );
            if ( $img_id ) {
                set_post_thumbnail( $post_id, $img_id );
            } else {
                delete_post_thumbnail( $post_id );
            }
        }

        // Yoast SEO meta.
        if ( isset( $config['seo_title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $config['seo_title'] ) );
        }
        if ( isset( $config['seo_description'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $config['seo_description'] ) );
        }
        if ( isset( $config['seo_focus_kw'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_focuskw', sanitize_text_field( $config['seo_focus_kw'] ) );
        }
        if ( isset( $config['seo_canonical'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_canonical', esc_url_raw( $config['seo_canonical'] ) );
        }
        if ( isset( $config['seo_og_title'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-title', sanitize_text_field( $config['seo_og_title'] ) );
        }
        if ( isset( $config['seo_og_desc'] ) ) {
            update_post_meta( $post_id, '_yoast_wpseo_opengraph-description', sanitize_text_field( $config['seo_og_desc'] ) );
        }

        return rest_ensure_response( [ 'success' => true, 'post_id' => $post_id ] );
    }

    private function apply_css_write( $filename, $contents ) {
        if ( '' === $contents ) {
            return new WP_REST_Response( [ 'error' => 'Empty CSS contents.' ], 400 );
        }

        // Global theme CSS files (fixed allowlist).
        $allowlist = Anchor_Editor_REST_Files::get_css_allowlist();
        if ( in_array( $filename, $allowlist, true ) ) {
            $path   = trailingslashit( get_stylesheet_directory() ) . 'assets/css/' . $filename;
            $result = file_put_contents( $path, $contents );
            if ( false === $result ) {
                return new WP_REST_Response( [ 'error' => 'Could not write CSS file.' ], 500 );
            }
            return rest_ensure_response( [ 'success' => true, 'target' => 'css', 'file' => $filename ] );
        }

        // Per-page CSS: filename = {slug}.css where slug is a valid page slug.
        $slug = preg_replace( '/\.css$/', '', $filename );
        $slug = sanitize_file_name( $slug );
        if ( '' === $slug || ! preg_match( '/^[a-z0-9_-]+$/', $slug ) ) {
            return new WP_REST_Response( [ 'error' => 'CSS file not in allowlist: ' . $filename ], 403 );
        }

        $dir  = trailingslashit( get_stylesheet_directory() ) . 'assets/css/pages';
        $path = $dir . '/' . $slug . '.css';
        if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
            return new WP_REST_Response( [ 'error' => 'Could not create directory.' ], 500 );
        }

        $result = file_put_contents( $path, $contents );
        if ( false === $result ) {
            return new WP_REST_Response( [ 'error' => 'Could not write per-page CSS file.' ], 500 );
        }

        return rest_ensure_response( [ 'success' => true, 'target' => 'page-css', 'file' => $slug . '.css' ] );
    }
}
