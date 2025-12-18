<?php
/**
 * Admin Enhancements Class
 *
 * Handles advanced admin features including:
 * - CodeMirror integration for JSON viewing
 * - Import/Export functionality
 * - Enhanced modals with code editors
 * - Request/Response capture and viewing
 *
 * @package ElementorRetriggerTool
 */

namespace ElementorRetriggerTool;

/**
 * Class Admin_Enhancements
 *
 * Advanced admin features for the plugin
 */
class Admin_Enhancements {

	/**
	 * Initialize admin enhancements
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_ajax_e_retrigger_export_logs', [ __CLASS__, 'ajax_export_logs' ] );
		add_action( 'wp_ajax_e_retrigger_import_settings', [ __CLASS__, 'ajax_import_settings' ] );
		add_action( 'wp_ajax_e_retrigger_export_settings', [ __CLASS__, 'ajax_export_settings' ] );
	}

	/**
	 * Enqueue admin assets (CodeMirror)
	 *
	 * @param string $hook Current admin page hook.
	 */
	public static function enqueue_assets( $hook ) {
		// Only load on our plugin pages
		if ( strpos( $hook, 'e-retrigger-tool' ) === false ) {
			return;
		}

		// Enqueue CodeMirror from WordPress core
		wp_enqueue_code_editor( [ 'type' => 'application/json' ] );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		// Enqueue custom admin JS
		wp_enqueue_script(
			'e-retrigger-admin',
			plugin_dir_url( dirname( __FILE__ ) ) . 'assets/admin.js',
			[ 'jquery', 'wp-codemirror' ],
			'10.0.0',
			true
		);

		// Localize script with AJAX data
		wp_localize_script(
			'e-retrigger-admin',
			'eRetriggerAdmin',
			[
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'exportLogsUrl'  => wp_nonce_url( admin_url( 'admin-ajax.php?action=e_retrigger_export_logs' ), 'export_logs' ),
				'exportSettingsUrl' => wp_nonce_url( admin_url( 'admin-ajax.php?action=e_retrigger_export_settings' ), 'export_settings' ),
				'nonces'         => [
					'process'       => wp_create_nonce( 'e_retrigger_process' ),
					'getData'       => wp_create_nonce( 'e_retrigger_process' ),
					'importSettings' => wp_create_nonce( 'import_settings' ),
				],
			]
		);

		// Enqueue custom admin CSS
		wp_add_inline_style(
			'wp-codemirror',
			self::get_inline_css()
		);
	}

	/**
	 * Get inline CSS for enhanced modals
	 *
	 * @return string CSS code.
	 */
	private static function get_inline_css() {
		return '
		.e-retrigger-modal-large { max-width: 1200px !important; width: 90%; }
		.e-retrigger-tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
		.e-retrigger-tab { padding: 10px 20px; cursor: pointer; background: #f0f0f1; border: 1px solid #ddd; border-bottom: none; margin-right: 5px; }
		.e-retrigger-tab.active { background: #fff; border-bottom: 1px solid #fff; margin-bottom: -1px; }
		.e-retrigger-tab-content { display: none; }
		.e-retrigger-tab-content.active { display: block; }
		.code-editor-wrapper { border: 1px solid #ddd; border-radius: 4px; overflow: hidden; }
		.CodeMirror { height: 400px !important; font-size: 13px; }
		.e-retrigger-json-viewer { background: #f8f9fa; padding: 15px; border-radius: 4px; max-height: 500px; overflow: auto; }
		.e-retrigger-json-viewer pre { margin: 0; white-space: pre-wrap; }
		.e-retrigger-action-log { background: #fff; border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 4px; }
		.e-retrigger-action-log.success { border-left: 4px solid #46b450; }
		.e-retrigger-action-log.failed { border-left: 4px solid #dc3232; }
		.e-retrigger-log-meta { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 12px; color: #666; }
		.e-retrigger-import-export { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
		.e-retrigger-import-box, .e-retrigger-export-box { background: #f9f9f9; padding: 20px; border: 2px dashed #ddd; border-radius: 4px; text-align: center; }
		.e-retrigger-import-box:hover, .e-retrigger-export-box:hover { border-color: #2271b1; background: #f0f6fc; }
		';
	}

	/**
	 * Render Import/Export tab
	 */
	public static function render_import_export_tab() {
		?>
		<div class="card" style="padding: 20px; max-width: 1200px;">
			<h2><?php esc_html_e( 'Import / Export', 'elementor-retrigger-tool' ); ?></h2>
			<p><?php esc_html_e( 'Import or export plugin settings and logs for backup, migration, or analysis.', 'elementor-retrigger-tool' ); ?></p>

			<div class="e-retrigger-import-export">
				<!-- Export Section -->
				<div class="e-retrigger-export-box">
					<h3><?php esc_html_e( 'Export', 'elementor-retrigger-tool' ); ?></h3>
					<p><?php esc_html_e( 'Download your settings and logs as JSON files.', 'elementor-retrigger-tool' ); ?></p>

					<div style="margin: 20px 0;">
						<button type="button" id="export-settings-btn" class="button button-primary button-large">
							<span class="dashicons dashicons-download" style="line-height: 1.3; margin-right: 5px;"></span>
							<?php esc_html_e( 'Export Settings', 'elementor-retrigger-tool' ); ?>
						</button>
					</div>

					<div style="margin: 20px 0;">
						<button type="button" id="export-logs-btn" class="button button-secondary button-large">
							<span class="dashicons dashicons-download" style="line-height: 1.3; margin-right: 5px;"></span>
							<?php esc_html_e( 'Export Logs (JSON)', 'elementor-retrigger-tool' ); ?>
						</button>
					</div>

					<div style="margin: 20px 0;">
						<button type="button" id="export-logs-csv-btn" class="button button-secondary button-large">
							<span class="dashicons dashicons-media-spreadsheet" style="line-height: 1.3; margin-right: 5px;"></span>
							<?php esc_html_e( 'Export Logs (CSV)', 'elementor-retrigger-tool' ); ?>
						</button>
					</div>
				</div>

				<!-- Import Section -->
				<div class="e-retrigger-import-box">
					<h3><?php esc_html_e( 'Import', 'elementor-retrigger-tool' ); ?></h3>
					<p><?php esc_html_e( 'Restore settings from a previously exported JSON file.', 'elementor-retrigger-tool' ); ?></p>

					<form id="import-settings-form" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
						<?php wp_nonce_field( 'import_settings', 'import_nonce' ); ?>

						<div style="margin-bottom: 20px;">
							<input type="file" name="import_file" id="import-file" accept=".json" style="display: none;">
							<label for="import-file" class="button button-large" style="cursor: pointer;">
								<span class="dashicons dashicons-upload" style="line-height: 1.3; margin-right: 5px;"></span>
								<?php esc_html_e( 'Choose File', 'elementor-retrigger-tool' ); ?>
							</label>
							<span id="import-file-name" style="display: inline-block; margin-left: 10px; color: #666;"></span>
						</div>

						<button type="submit" id="import-settings-btn" class="button button-primary button-large" disabled>
							<span class="dashicons dashicons-upload" style="line-height: 1.3; margin-right: 5px;"></span>
							<?php esc_html_e( 'Import Settings', 'elementor-retrigger-tool' ); ?>
						</button>
					</form>

					<div id="import-result" style="margin-top: 20px;"></div>
				</div>
			</div>

			<!-- Recent Exports -->
			<div style="margin-top: 40px;">
				<h3><?php esc_html_e( 'Export Statistics', 'elementor-retrigger-tool' ); ?></h3>
				<?php self::render_export_statistics(); ?>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Export Settings
			$('#export-settings-btn').on('click', function() {
				var btn = $(this);
				btn.prop('disabled', true).text('Exporting...');

				$.post(ajaxurl, {
					action: 'e_retrigger_export_settings',
					nonce: '<?php echo wp_create_nonce( 'export_settings' ); ?>'
				}, function(response) {
					btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Settings');

					if (response.success) {
						var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
						var url = window.URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'e-retrigger-settings-' + new Date().toISOString().slice(0,10) + '.json';
						a.click();
						window.URL.revokeObjectURL(url);
					} else {
						alert('Export failed: ' + (response.data ? response.data.message : 'Unknown error'));
					}
				});
			});

			// Export Logs JSON
			$('#export-logs-btn').on('click', function() {
				window.location.href = '<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=e_retrigger_export_logs&format=json' ), 'export_logs' ); ?>';
			});

			// Export Logs CSV
			$('#export-logs-csv-btn').on('click', function() {
				window.location.href = '<?php echo wp_nonce_url( admin_url( 'admin-ajax.php?action=e_retrigger_export_logs&format=csv' ), 'export_logs' ); ?>';
			});

			// Import File Selection
			$('#import-file').on('change', function() {
				var fileName = $(this).val().split('\\').pop();
				$('#import-file-name').text(fileName);
				$('#import-settings-btn').prop('disabled', !fileName);
			});

			// Import Settings
			$('#import-settings-form').on('submit', function(e) {
				e.preventDefault();

				var formData = new FormData(this);
				formData.append('action', 'e_retrigger_import_settings');

				$('#import-settings-btn').prop('disabled', true).text('Importing...');
				$('#import-result').html('');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					success: function(response) {
						$('#import-settings-btn').prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Import Settings');

						if (response.success) {
							$('#import-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
							setTimeout(function() { location.reload(); }, 2000);
						} else {
							$('#import-result').html('<div class="notice notice-error"><p>' + (response.data ? response.data.message : 'Import failed') + '</p></div>');
						}
					},
					error: function() {
						$('#import-settings-btn').prop('disabled', false).html('<span class="dashicons dashicons-upload"></span> Import Settings');
						$('#import-result').html('<div class="notice notice-error"><p>Import failed due to server error.</p></div>');
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render export statistics
	 */
	private static function render_export_statistics() {
		global $wpdb;
		$table = $wpdb->prefix . 'e_retrigger_logs';

		$total_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		$success_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'success'" );
		$failed_logs = $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'failed'" );

		?>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metric', 'elementor-retrigger-tool' ); ?></th>
					<th><?php esc_html_e( 'Count', 'elementor-retrigger-tool' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Total Logs', 'elementor-retrigger-tool' ); ?></td>
					<td><strong><?php echo number_format_i18n( $total_logs ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Successful Re-triggers', 'elementor-retrigger-tool' ); ?></td>
					<td><strong style="color: #46b450;"><?php echo number_format_i18n( $success_logs ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Failed Re-triggers', 'elementor-retrigger-tool' ); ?></td>
					<td><strong style="color: #dc3232;"><?php echo number_format_i18n( $failed_logs ); ?></strong></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * AJAX Export Logs
	 */
	public static function ajax_export_logs() {
		check_ajax_referer( 'export_logs' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'e_retrigger_logs';
		$logs = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

		$format = isset( $_GET['format'] ) ? sanitize_text_field( $_GET['format'] ) : 'json';

		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv' );
			header( 'Content-Disposition: attachment; filename="e-retrigger-logs-' . gmdate( 'Y-m-d' ) . '.csv"' );

			$output = fopen( 'php://output', 'w' );
			fputcsv( $output, [ 'ID', 'Submission ID', 'Actions', 'Status', 'Message', 'Created At' ] );

			foreach ( $logs as $log ) {
				fputcsv( $output, [
					$log['id'],
					$log['submission_id'],
					$log['actions'],
					$log['status'],
					$log['message'],
					$log['created_at'],
				] );
			}

			fclose( $output );
		} else {
			header( 'Content-Type: application/json' );
			header( 'Content-Disposition: attachment; filename="e-retrigger-logs-' . gmdate( 'Y-m-d' ) . '.json"' );
			echo wp_json_encode( $logs, JSON_PRETTY_PRINT );
		}

		exit;
	}

	/**
	 * AJAX Export Settings
	 */
	public static function ajax_export_settings() {
		check_ajax_referer( 'export_settings', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$settings = [
			'retention_days' => get_option( 'e_retrigger_retention_days', 30 ),
			'version' => '10.0.0',
			'exported_at' => current_time( 'mysql' ),
		];

		wp_send_json_success( $settings );
	}

	/**
	 * AJAX Import Settings
	 */
	public static function ajax_import_settings() {
		check_ajax_referer( 'import_settings', 'import_nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		if ( empty( $_FILES['import_file'] ) ) {
			wp_send_json_error( [ 'message' => 'No file uploaded' ] );
		}

		$file = $_FILES['import_file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( [ 'message' => 'File upload error' ] );
		}

		if ( $file['type'] !== 'application/json' && pathinfo( $file['name'], PATHINFO_EXTENSION ) !== 'json' ) {
			wp_send_json_error( [ 'message' => 'Invalid file type. Please upload a JSON file.' ] );
		}

		$content = file_get_contents( $file['tmp_name'] );
		$settings = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			wp_send_json_error( [ 'message' => 'Invalid JSON file' ] );
		}

		// Import settings
		if ( isset( $settings['retention_days'] ) ) {
			update_option( 'e_retrigger_retention_days', absint( $settings['retention_days'] ) );
		}

		wp_send_json_success( [ 'message' => 'Settings imported successfully' ] );
	}
}
