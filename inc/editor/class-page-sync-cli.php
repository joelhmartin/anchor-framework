<?php
/**
 * WP-CLI: wp anchor sync-pages
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) return;

class Anchor_Editor_Page_Sync_CLI {
    public function sync_pages() {
        if ( ! class_exists( 'Anchor_Editor_Page_Sync' ) ) {
            WP_CLI::error( 'Anchor_Editor_Page_Sync class not loaded.' );
        }
        $r = Anchor_Editor_Page_Sync::backfill();
        WP_CLI::log( 'Created: ' . count( $r['created'] ) );
        foreach ( $r['created'] as $row ) {
            WP_CLI::log( "  + {$row['slug']} (post {$row['post_id']})" );
        }
        WP_CLI::log( 'Skipped: ' . count( $r['skipped'] ) );
        foreach ( $r['skipped'] as $row ) {
            WP_CLI::log( "  - {$row['slug']}: {$row['reason']}" );
        }
    }
}

WP_CLI::add_command( 'anchor sync-pages', [ Anchor_Editor_Page_Sync_CLI::class, 'sync_pages' ] );
