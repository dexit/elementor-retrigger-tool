<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled.
 * Removes all plugin data from the database.
 *
 * @package ElementorRetriggerTool
 * @since 10.1.0
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data on uninstall
 *
 * This function is called when the plugin is deleted via the WordPress admin.
 * It removes all plugin options, database tables, and transients.
 *
 * @since 10.1.0
 */
function elementor_retrigger_tool_uninstall() {
	global $wpdb;

	// Check if user has permission
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	/**
	 * Fires before plugin data is removed
	 *
	 * Allows third-party code to run cleanup tasks or prevent uninstall.
	 *
	 * @since 10.1.0
	 */
	do_action( 'elementor_retrigger_before_uninstall' );

	// Get option: should we keep data on uninstall?
	$keep_data = get_option( 'e_retrigger_keep_data_on_uninstall', false );

	if ( ! $keep_data ) {
		// Drop custom table
		$table_name = $wpdb->prefix . 'e_retrigger_logs';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Remove options
		delete_option( 'e_retrigger_db_ver' );
		delete_option( 'e_retrigger_retention_days' );
		delete_option( 'e_retrigger_keep_data_on_uninstall' );

		// Remove transients
		delete_transient( 'e_retrigger_forms' );

		// Clear scheduled events
		wp_clear_scheduled_hook( 'e_retrigger_daily_cleanup_event' );
	}

	/**
	 * Fires after plugin data is removed
	 *
	 * @since 10.1.0
	 *
	 * @param bool $keep_data Whether data was kept or removed.
	 */
	do_action( 'elementor_retrigger_after_uninstall', $keep_data );
}

// Run uninstall
elementor_retrigger_tool_uninstall();
