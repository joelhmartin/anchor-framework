<?php
/**
 * REST API — Change history and undo/restore endpoints.
 *
 * The config writer already creates .bak files before every write.
 * This exposes those backups so the chat can offer undo.
 *
 * @package Anchor_Framework
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Anchor_Editor_REST_History {

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

        // List change history (backups) for a config file.
        register_rest_route( $ns, '/history/(?P<type>pages|site|data|globals|media)(?:/(?P<slug>[a-zA-Z0-9_-]+))?', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'list_backups' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );

        // Restore a specific backup.
        register_rest_route( $ns, '/history/restore', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'restore_backup' ],
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ] );
    }

    /**
     * List backup files for a config.
     */
    public function list_backups( $request ) {
        $type = $request['type'];
        $slug = $request['slug'] ?? '';

        $file = $this->resolve_file( $type, $slug );
        if ( ! $file ) {
            return new WP_REST_Response( [ 'error' => 'Unknown config.' ], 404 );
        }

        $dir      = dirname( $file );
        $basename = basename( $file );
        $pattern  = $dir . '/' . $basename . '.*.bak';
        $backups  = glob( $pattern );

        if ( ! $backups ) {
            return rest_ensure_response( [] );
        }

        // Sort newest first.
        rsort( $backups );

        $result = [];
        foreach ( $backups as $bak ) {
            // Extract timestamp from filename: file.php.2026-04-02-143022.bak
            $name = basename( $bak );
            preg_match( '/\.(\d{4}-\d{2}-\d{2}-\d{6})\.bak$/', $name, $m );
            $timestamp = $m[1] ?? '';

            $result[] = [
                'file'      => $bak,
                'timestamp' => $timestamp,
                'date'      => $timestamp ? date( 'M j, Y g:i A', strtotime( str_replace( '-', '', substr( $timestamp, 0, 10 ) ) . ' ' . substr( $timestamp, 11, 2 ) . ':' . substr( $timestamp, 13, 2 ) . ':' . substr( $timestamp, 15, 2 ) ) ) : '',
                'size'      => filesize( $bak ),
            ];
        }

        return rest_ensure_response( $result );
    }

    /**
     * Restore a backup file (swap it with the current config).
     */
    public function restore_backup( $request ) {
        $params  = $request->get_json_params();
        $backup  = $params['backup_file'] ?? '';
        $type    = $params['type'] ?? '';
        $slug    = $params['slug'] ?? '';

        if ( ! $backup || ! file_exists( $backup ) ) {
            return new WP_REST_Response( [ 'error' => 'Backup file not found.' ], 404 );
        }

        $target = $this->resolve_file( $type, $slug );
        if ( ! $target ) {
            return new WP_REST_Response( [ 'error' => 'Unknown config target.' ], 400 );
        }

        // Safety: make sure the backup is in the config directory.
        $config_dir = Anchor_Editor_Config_Manager::instance()->get_config_dir();
        if ( strpos( realpath( $backup ), realpath( $config_dir ) ) !== 0 ) {
            return new WP_REST_Response( [ 'error' => 'Invalid backup path.' ], 403 );
        }

        // Back up the CURRENT file before restoring (so restore is also undoable).
        $pre_restore_backup = $target . '.' . date( 'Y-m-d-His' ) . '.bak';
        copy( $target, $pre_restore_backup );

        // Restore.
        if ( ! copy( $backup, $target ) ) {
            return new WP_REST_Response( [ 'error' => 'Failed to restore.' ], 500 );
        }

        return rest_ensure_response( [ 'success' => true, 'restored_from' => basename( $backup ) ] );
    }

    /**
     * Map type + slug to actual file path.
     */
    private function resolve_file( $type, $slug = '' ) {
        $dir = Anchor_Editor_Config_Manager::instance()->get_config_dir();

        switch ( $type ) {
            case 'pages':
                return $slug ? $dir . '/pages/' . sanitize_file_name( $slug ) . '.php' : null;
            case 'site':
                return $dir . '/site.php';
            case 'data':
                return $dir . '/data.php';
            case 'globals':
                return $dir . '/globals.php';
            case 'media':
                return $dir . '/media.php';
            default:
                return null;
        }
    }
}
