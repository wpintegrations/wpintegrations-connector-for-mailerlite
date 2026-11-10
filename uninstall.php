<?php
/**
 * Removes tables, options and cron events when the plugin is deleted.
 *
 * @package WPIntegrations\MailerLiteConnector
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Both editions share this data. Bail on an installed Pro copy, not just an
 * active one: a bulk delete can remove this plugin while Pro is momentarily
 * deactivated, taking Pro's tokens and integrations with it.
 */
$wpi_mlite_pro = 'wpintegrations-connector-for-mailerlite-pro/wpintegrations-connector-for-mailerlite-pro.php';

$wpi_mlite_active = (array) get_option( 'active_plugins', array() );
$wpi_mlite_shared = in_array( $wpi_mlite_pro, $wpi_mlite_active, true )
	|| ( is_multisite() && array_key_exists( $wpi_mlite_pro, (array) get_site_option( 'active_sitewide_plugins', array() ) ) )
	|| file_exists( WP_PLUGIN_DIR . '/' . $wpi_mlite_pro );

if ( $wpi_mlite_shared ) {
	return;
}

global $wpdb;

// Plugin tables. Names are constants, not user input.
foreach ( array( 'wpi_mlite_flows', 'wpi_mlite_logs', 'wpi_mlite_jobs' ) as $wpi_mlite_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of plugin-owned tables.
	$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $wpi_mlite_table ) );
}

// Options.
delete_option( 'wpi_mlite_settings' );
delete_option( 'wpi_mlite_version' );
delete_option( 'wpi_mlite_migrations' );
delete_option( 'wpi_mlite_recent_shapes' );

// Transients: each form plugin's scan cache, plus MailerLite group
// caches swept by prefix (they are keyed by a hash of the API key).
foreach ( array( 'elementor', 'cf7', 'wpforms', 'fluentform', 'gravityforms' ) as $wpi_mlite_trigger ) {
	delete_transient( 'wpi_mlite_' . $wpi_mlite_trigger . '_forms' );
}
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '\_transient\_wpi\_mlite\_api\_%'
	    OR option_name LIKE '\_transient\_timeout\_wpi\_mlite\_api\_%'"
);

// Cron events.
wp_clear_scheduled_hook( 'wpi_mlite_queue_tick' );
wp_clear_scheduled_hook( 'wpi_mlite_daily_purge' );
