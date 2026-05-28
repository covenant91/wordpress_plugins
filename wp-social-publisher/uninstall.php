<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || die();

global $wpdb;

// Drop the activity log table.
$table = $wpdb->prefix . 'wsp_post_log';
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Remove all plugin options.
delete_option( 'wsp_version' );
delete_option( 'wsp_settings' );

// Clear any scheduled cron events.
wp_clear_scheduled_hook( 'wsp_token_check' );
wp_clear_scheduled_hook( 'wsp_purge_logs' );
