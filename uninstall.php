<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'ert_retrigger_logs';
$wpdb->query( "DROP TABLE IF EXISTS $table_name" );

delete_option( 'ert_db_ver' );
delete_option( 'ert_retention_days' );
delete_transient( 'ert_forms_list' );
