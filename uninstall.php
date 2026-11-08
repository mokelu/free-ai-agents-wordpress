<?php
/**
 * Uninstall handler: remove plugin tables and options.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 *
 * @package BGLiveChat
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Drop the custom tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bglc_message" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}bglc_thread" );  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Remove options and the cached model list.
foreach ( array(
	'bglc_baseurl', 'bglc_defaultmodel', 'bglc_maxtokens', 'bglc_apikey',
	'bglc_bot_enabled', 'bglc_bot_prompt', 'bglc_headline', 'bglc_greeting',
	'bglc_prechat', 'bglc_tab_chat', 'bglc_floating',
	'bglc_color', 'bglc_position',
) as $option ) {
	delete_option( $option );
}
delete_transient( 'bglc_models' );
