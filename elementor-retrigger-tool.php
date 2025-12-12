<?php
/**
 * Plugin Name: Elementor Submission Re‑Trigger Tool
 * Plugin URI:  https://example.com/elementor-retrigger-tool
 * Description: Bulk re‑trigger Elementor Pro form submissions with a visual queue, edit‑payload modal, auto‑save, full payload logging, cron cleanup, and more.
 * Version:     10.0.0
 * Author:      Custom Extension
 * Author URI:  https://example.com
 * Text Domain: elementor-retrigger-tool
 * Requires PHP: 7.4
 * Requires at least: 6.0
 *
 * @package ElementorRetriggerTool
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Elementor_Retrigger_Tool' ) ) :

class Elementor_Retrigger_Tool {

	/* ------------------------------------------------------------------ */
	/*  Constants
	/* ------------------------------------------------------------------ */
	const PAGE_SLUG          = 'e-retrigger-tool';
	const AJAX_ACTION        = 'e_retrigger_process';
	const AJAX_GET_DATA      = 'e_retrigger_get_data';
	const LOG_TABLE          = 'e_retrigger_logs';
	const OPTION_RETENTION   = 'e_retrigger_retention_days';
	const CRON_HOOK          = 'e_retrigger_daily_cleanup_event';
	const PER_PAGE           = 20;

	/* ------------------------------------------------------------------ */
	/*  Properties
	/* ------------------------------------------------------------------ */
	private $webhook_debug_info = '';
	private $execution_log_buffer = '';

	/* ------------------------------------------------------------------ */
	/*  Constructor
	/* ------------------------------------------------------------------ */
	public function __construct() {
		/* Admin UI */
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 150 );
		add_action( 'admin_init', [ $this, 'init_plugin_logic' ] );

		/* AJAX */
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'ajax_process_request' ] );
		add_action( 'wp_ajax_' . self::AJAX_GET_DATA, [ $this, 'ajax_get_submission_data' ] );

		/* Cron & Activation */
		add_action( self::CRON_HOOK, [ $this, 'scheduled_log_cleanup' ] );
		register_activation_hook( __FILE__, [ $this, 'activate_plugin' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate_plugin' ] );
	}

	/* ------------------------------------------------------------------ */
	/*  Activation / Deactivation
	/* ------------------------------------------------------------------ */
	public function activate_plugin() {
		$this->check_db_install();

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	public function deactivate_plugin() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/* ------------------------------------------------------------------ */
	/*  Init
	/* ------------------------------------------------------------------ */
	public function init_plugin_logic() {
		$this->check_db_install();
		$this->handle_settings_save();
	}

	/* ------------------------------------------------------------------ */
	/*  Database
	/* ------------------------------------------------------------------ */
	public function check_db_install() {
		if ( get_option( 'e_retrigger_db_ver' ) === '1.0' ) {
			return;
		}

		global $wpdb;
		$table_name     = $wpdb->prefix . self::LOG_TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			submission_id bigint(20) NOT NULL,
			actions varchar(255) NOT NULL,
			status varchar(20) NOT NULL,
			message text NOT NULL,
			full_debug text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'e_retrigger_db_ver', '1.0' );
	}

	/* ------------------------------------------------------------------ */
	/*  Cron
	/* ------------------------------------------------------------------ */
	public function scheduled_log_cleanup() {
		$days = (int) get_option( self::OPTION_RETENTION, 30 );
		if ( $days <= 0 ) {
			return;
		}
		$this->run_cleanup_query( $days );
	}

	private function run_cleanup_query( $days ) {
		global $wpdb;
		$table = $wpdb->prefix . self::LOG_TABLE;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/* ------------------------------------------------------------------ */
	/*  Settings
	/* ------------------------------------------------------------------ */
	private function handle_settings_save() {
		if (
			isset( $_POST['e_retrigger_save_settings'] ) &&
			check_admin_referer( 'e_retrigger_settings', 'e_retrigger_nonce' )
		) {
			if ( isset( $_POST['retention_days'] ) ) {
				update_option( self::OPTION_RETENTION, absint( $_POST['retention_days'] ) );
			}

			if ( ! empty( $_POST['manual_cleanup_days'] ) ) {
				$days = absint( $_POST['manual_cleanup_days'] );
				$this->run_cleanup_query( $days );
				add_settings_error( 'e_retrigger', 'cleanup', "Cleanup executed for logs older than $days days.", 'updated' );
			} else {
				add_settings_error( 'e_retrigger', 'saved', 'Settings saved.', 'updated' );
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Admin UI
	/* ------------------------------------------------------------------ */
	public function register_admin_menu() {
		add_submenu_page(
			'elementor',
			'Re‑Trigger Tool',
			'Re‑Trigger Tool',
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_main_wrapper' ]
		);
	}

	public function render_main_wrapper() {
		$active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'run';
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">Elementor Submission Re‑Trigger Tool</h1>
			<hr class="wp-header-end">
			<nav class="nav-tab-wrapper">
				<a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=run"
				   class="nav-tab <?php echo $active_tab == 'run' ? 'nav-tab-active' : ''; ?>">Run Tool</a>
				<a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=logs"
				   class="nav-tab <?php echo $active_tab == 'logs' ? 'nav-tab-active' : ''; ?>">Logs & History</a>
				<a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=settings"
				   class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
			</nav>

			<div style="margin-top: 20px;">
				<?php
				switch ( $active_tab ) {
					case 'logs':
						$this->render_logs_view();
						break;
					case 'settings':
						$this->render_settings_view();
						break;
					default:
						$this->render_run_tool();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Settings View
	/* ------------------------------------------------------------------ */
	private function render_settings_view() {
		$current_retention = get_option( self::OPTION_RETENTION, 30 );
		?>
		<div class="card" style="max-width: 600px; padding: 20px;">
			<form method="post" action="">
				<?php wp_nonce_field( 'e_retrigger_settings', 'e_retrigger_nonce' ); ?>

				<h3>Automated Cleanup (Cron Job)</h3>
				<p>The system automatically deletes old logs once daily.</p>
				<table class="form-table">
					<tr>
						<th scope="row">Retention Policy</th>
						<td>
							<select name="retention_days">
								<option value="7" <?php selected( $current_retention, 7 ); ?>>1 Week</option>
								<option value="30" <?php selected( $current_retention, 30 ); ?>>1 Month</option>
								<option value="90" <?php selected( $current_retention, 90 ); ?>>3 Months</option>
								<option value="365" <?php selected( $current_retention, 365 ); ?>>1 Year</option>
								<option value="0" <?php selected( $current_retention, 0 ); ?>>Keep Forever</option>
							</select>
						</td>
					</tr>
				</table>

				<hr>
				<h3>Manual Cleanup</h3>
				<div style="display:flex; gap:10px; align-items:center;">
					<select name="manual_cleanup_days">
						<option value="">-- Select Action --</option>
						<option value="7">Delete older than 1 Week</option>
						<option value="30">Delete older than 1 Month</option>
						<option value="1">Delete older than 1 Day</option>
						<option value="0">Delete ALL Logs</option>
					</select>
					<input type="submit" name="e_retrigger_save_settings" class="button button-secondary" value="Run Cleanup Now">
				</div>

				<hr>
				<p class="submit"><input type="submit" name="e_retrigger_save_settings" class="button button-primary" value="Save Settings"></p>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  Logs View
	/* ------------------------------------------------------------------ */
	private function render_logs_view() {
		global $wpdb;
		$table = $wpdb->prefix . self::LOG_TABLE;

		$paged   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order   = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$offset  = ( $paged - 1 ) * self::PER_PAGE;

		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		$total_pages = ceil( $total_items / self::PER_PAGE );

		$logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table ORDER BY $orderby $order LIMIT %d OFFSET %d",
				self::PER_PAGE,
				$offset
			)
		);

		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=logs' );
		$sort_link = function ( $col ) use ( $base_url, $orderby, $order ) {
			$new_order = ( $orderby === $col && $order === 'DESC' ) ? 'ASC' : 'DESC';
			return esc_url( add_query_arg( [ 'orderby' => $col, 'order' => $new_order ], $base_url ) );
		};

		echo '<div class="card" style="padding:0; max-width: 100%;">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
			<th width="160"><a href="' . $sort_link( 'created_at' ) . '">Date</a></th>
			<th width="100"><a href="' . $sort_link( 'submission_id' ) . '">Sub ID</a></th>
			<th width="150">Actions</th>
			<th width="100"><a href="' . $sort_link( 'status' ) . '">Status</a></th>
			<th>Message / Debug Info</th>
		</tr></thead><tbody>';

		if ( empty( $logs ) ) {
			echo '<tr><td colspan="5">No logs found.</td></tr>';
		} else {
			foreach ( $logs as $log ) {
				$color        = $log->status === 'success' ? '#46b450' : '#dc3232';
				$status_badge = sprintf(
					'<span style="color:#fff; background:%s; padding: 3px 8px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">%s</span>',
					$color,
					$log->status
				);
				$sub_link = admin_url( 'admin.php?page=e-form-submissions&action=view&id=' . $log->submission_id );
				echo '<tr>
					<td>' . esc_html( $log->created_at ) . '</td>
					<td><a href="' . esc_url( $sub_link ) . '" target="_blank">#' . esc_html( $log->submission_id ) . ' <span class="dashicons dashicons-external"></span></a></td>
					<td>' . esc_html( $log->actions ) . '</td>
					<td>' . $status_badge . '</td>
					<td><strong>' . esc_html( $log->message ) . '</strong>';
				if ( ! empty( $log->full_debug ) ) {
					echo '<details style="margin-top:5px;"><summary style="color:#2271b1; cursor:pointer; font-size:11px;">View Extended Info</summary><pre style="background:#f0f0f1; padding:10px; overflow-x:auto; font-size:10px;">' . esc_html( $log->full_debug ) . '</pre></details>';
				}
				echo '</td></tr>';
			}
		}
		echo '</tbody></table>';

		if ( $total_pages > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo paginate_links(
				[
					'base'     => add_query_arg( 'paged', '%#%' ),
					'format'   => '',
					'total'    => $total_pages,
					'current'  => $paged,
				]
			);
			echo '</div></div>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------ */
	/*  Run Tool View
	/* ------------------------------------------------------------------ */
	private function render_run_tool() {
		$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$orderby        = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order          = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$filter_form    = isset( $_GET['filter_form'] ) ? sanitize_text_field( $_GET['filter_form'] ) : '';
		$filter_date    = isset( $_GET['filter_date'] ) ? sanitize_text_field( $_GET['filter_date'] ) : '';
		$filter_search  = isset( $_GET['filter_search'] ) ? sanitize_text_field( $_GET['filter_search'] ) : '';

		$submissions_data = $this->get_filtered_submissions( $filter_form, $filter_date, $filter_search, $paged, $orderby, $order );
		$submissions      = $submissions_data['rows'];
		$total_items      = $submissions_data['total'];
		$total_pages      = ceil( $total_items / self::PER_PAGE );
		$unique_forms     = $this->get_unique_forms();

		/* Available actions from Elementor Pro */
		$available_actions = [];
		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			$modules = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' );
			if ( $modules ) {
				foreach ( $modules->get_form_actions() as $slug => $instance ) {
					if ( in_array( $slug, [ 'save-to-database', 'redirect' ], true ) ) {
						continue;
					}
					$available_actions[ $slug ] = $instance->get_label();
				}
			}
		}

		$base_url = add_query_arg(
			[
				'filter_form'   => $filter_form,
				'filter_date'   => $filter_date,
				'filter_search' => $filter_search,
			],
			admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=run' )
		);
		$sort_link = function ( $col ) use ( $base_url, $orderby, $order ) {
			$new_order = ( $orderby === $col && $order === 'DESC' ) ? 'ASC' : 'DESC';
			return esc_url( add_query_arg( [ 'orderby' => $col, 'order' => $new_order ], $base_url ) );
		};

		/* ------------------------------------------------------------------ */
		/*  Inline CSS & JS
		/* ------------------------------------------------------------------ */
		?>
		<style>
			/* Queue list */
			.queue-list { list-style:none; margin:0; padding:0; border:1px solid #ccd0d4; background:#fff; max-height:500px; overflow-y:auto; }
			.queue-list li { display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #f0f0f1; align-items:center; font-size:12px; }
			.queue-list li:last-child { border-bottom:none; }
			.queue-list li.processing { background:#f0f6fc; color:#2271b1; }
			.queue-list li.success { background:#edfaef; color:#46b450; }
			.queue-list li.failed { background:#fcf0f1; color:#d63638; }
			.queue-list li .status-icon:before { content:'\f147'; font-family:dashicons; color:#ccc; }
			.queue-list li.processing .status-icon:before { content:'\f463'; color:#2271b1; animation:spin 2s infinite linear; }
			.queue-list li.success .status-icon:before { content:'\f147'; color:#46b450; }
			.queue-list li.failed .status-icon:before { content:'\f158'; color:#d63638; }
			@keyframes spin { 0%{transform:rotate(0deg);} 100%{transform:rotate(360deg);} }

			/* Layout */
			.col-layout { display:flex; gap:20px; flex-wrap:wrap; }
			.col-left { flex:2; min-width:400px; }
			.col-mid { width:280px; flex-shrink:0; }
			.col-right { flex:2; min-width:350px; }

			/* Filter bar */
			.filter-bar { display:flex; gap:10px; padding:10px; background:#fff; border-bottom:1px solid #ddd; align-items:center; flex-wrap:wrap; }
			.filter-bar select, .filter-bar input { height:30px; line-height:1; }

			/* Modal */
			.e-retrigger-modal { display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); }
			.e-retrigger-modal-content { background:#fefefe; margin:10% auto; padding:20px; border:1px solid #888; width:500px; box-shadow:0 4px 10px rgba(0,0,0,0.2); border-radius:5px; }
			.e-retrigger-close { color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer; }
			.e-retrigger-close:hover { color:black; }
			.e-retrigger-field-row { margin-bottom:10px; }
			.e-retrigger-field-row label { display:block; font-weight:bold; margin-bottom:3px; font-size:12px; }
			.e-retrigger-field-row input, .e-retrigger-field-row textarea { width:100%; }
		</style>

		<div class="col-layout">

			<!-- LEFT: Selection Table -->
			<div class="col-left">
				<div class="card" style="padding:0; margin:0; max-width:100%;">
					<form method="get" class="filter-bar">
						<input type="hidden" name="page" value="<?php echo self::PAGE_SLUG; ?>">
						<input type="hidden" name="tab" value="run">
						<strong>Filter:</strong>
						<select name="filter_form">
							<option value="">All Forms</option>
							<?php foreach ( $unique_forms as $form_name ) : ?>
								<option value="<?php echo esc_attr( $form_name ); ?>" <?php selected( $filter_form, $form_name ); ?>><?php echo esc_html( $form_name ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="date" name="filter_date" value="<?php echo esc_attr( $filter_date ); ?>">
						<input type="text" name="filter_search" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="Email or ID" style="width:120px;">
						<button type="submit" class="button">Apply</button>
						<a href="?page=<?php echo self::PAGE_SLUG; ?>&tab=run" class="button">Reset</a>
					</form>

					<div style="max-height:600px; overflow-y:auto;">
						<table class="wp-list-table widefat fixed striped table-view-list">
							<thead>
								<tr>
									<td id="cb" class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-1"></td>
									<th width="80"><a href="<?php echo $sort_link( 'id' ); ?>">ID</a></th>
									<th>Form</th>
									<th>Email / User</th>
									<th width="120"><a href="<?php echo $sort_link( 'created_at' ); ?>">Date</a></th>
									<th width="50">Edit</th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $submissions ) ) : ?>
									<tr><td colspan="6">No submissions found matching filters.</td></tr>
								<?php else : ?>
									<?php foreach ( $submissions as $sub ) : ?>
										<?php
										$sub_link = admin_url( 'admin.php?page=e-form-submissions&action=view&id=' . $sub->id );
										?>
										<tr>
											<th scope="row" class="check-column">
												<input type="checkbox" name="submission_ids[]" value="<?php echo esc_attr( $sub->id ); ?>" class="sub-checkbox">
											</th>
											<td><a href="<?php echo esc_url( $sub_link ); ?>" target="_blank">#<?php echo esc_html( $sub->id ); ?></a></td>
											<td><?php echo esc_html( $sub->form_name ); ?></td>
											<td><?php echo esc_html( $sub->email ?: '-' ); ?></td>
											<td><?php echo esc_html( date( 'Y-m-d H:i', strtotime( $sub->created_at ) ) ); ?></td>
											<td>
												<button type="button" class="button button-small edit-payload-btn" data-id="<?php echo esc_attr( $sub->id ); ?>" title="Edit Payload & Run">
													<span class="dashicons dashicons-edit" style="line-height:1.3;"></span>
												</button>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>

					<div class="tablenav bottom" style="padding:10px;">
						<div class="tablenav-pages">
							<span class="displaying-num"><?php echo $total_items; ?> items</span>
							<?php
							echo paginate_links(
								[
									'base'     => add_query_arg( 'paged', '%#%' ),
									'format'   => '',
									'total'    => $total_pages,
									'current'  => $paged,
								]
							);
							?>
						</div>
					</div>

					<div style="padding:10px; border-top:1px solid #ccd0d4; background:#f9f9f9;">
						<label>Or Enter Manual ID: <input type="number" id="manual_id_input" class="small-text" placeholder="123"></label>
						<button type="button" class="button" id="add_manual_btn">Add to Queue</button>
					</div>
				</div>
			</div>

			<!-- MIDDLE: Visual Queue -->
			<div class="col-mid">
				<div class="card" style="padding:0; margin:0; height:100%; display:flex; flex-direction:column;">
					<div style="padding:15px; background:#f0f0f1; border-bottom:1px solid #ccd0d4;">
						<strong>2. Queue Items</strong> <span id="queue_count" style="float:right; background:#ccc; color:#fff; padding:0 5px; border-radius:10px; font-size:10px;">0</span>
					</div>
					<div style="flex:1; overflow-y:auto;">
						<ul id="visual_queue_container" class="queue-list">
							<li style="color:#999; justify-content:center; padding:20px;">Queue is empty.</li>
						</ul>
					</div>
				</div>
			</div>

			<!-- RIGHT: Actions & Console -->
			<div class="col-right">
				<div class="card" style="padding:15px; margin-bottom:20px; margin-top:0;">
					<h3>3. Select Actions</h3>
					<div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
						<?php if ( empty( $available_actions ) ) : ?>
							<p>No actions found.</p>
						<?php else : ?>
							<?php foreach ( $available_actions as $slug => $label ) : ?>
								<?php $checked = in_array( $slug, [ 'webhook', 'email' ], true ) ? 'checked' : ''; ?>
								<label><input type="checkbox" class="action-cb" value="<?php echo esc_attr( $slug ); ?>" <?php echo $checked; ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
					<br>
					<button type="button" id="start_process_btn" class="button button-primary button-large" style="width:100%;">Start Re‑Trigger Queue</button>
				</div>

				<div class="card" style="padding:0; background:#1d2327; color:#50fa7b; font-family:monospace; height:300px; display:flex; flex-direction:column;">
					<div style="padding:10px; background:#2c3338; color:#fff; border-bottom:1px solid #000;">
						<strong>Process Log</strong> <span id="queue_status" style="float:right; color:#f1c40f;">Idle</span>
					</div>
					<div id="console_output" style="flex:1; overflow-y:auto; padding:10px; font-size:12px; line-height:1.4;">
						<div style="color:#aaa;">Waiting for input...</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Modal for editing payload -->
		<div id="edit_payload_modal" class="e-retrigger-modal">
			<div class="e-retrigger-modal-content">
				<span class="e-retrigger-close">&times;</span>
				<h2>Edit Submission Payload</h2>
				<p>Modify the data below and run actions immediately. <strong>This will create a NEW submission record.</strong></p>
				<div id="modal_loading" style="display:none;">Loading data...</div>
				<form id="modal_form">
					<input type="hidden" id="modal_sub_id">
					<div id="modal_fields_container" style="max-height:300px; overflow-y:auto; margin-bottom:15px;"></div>
					<button type="button" id="modal_run_btn" class="button button-primary button-large">Run with Changes</button>
				</form>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			/* ------------------------------------------------------------------ */
			/*  Queue logic
			/* ------------------------------------------------------------------ */
			function updateQueueVisuals() {
				var container = $('#visual_queue_container');
				var count = 0;
				var html = '';
				$('.sub-checkbox:checked').each(function() {
					var id = $(this).val();
					var existing = $('#q-item-' + id);
					var classes = '';
					if (existing.length && (existing.hasClass('success') || existing.hasClass('failed') || existing.hasClass('processing'))) {
						classes = existing.attr('class');
					}
					html += '<li id="q-item-' + id + '" class="' + classes + '"><span>Submission #' + id + '</span> <span class="status-icon"></span></li>';
					count++;
				});
				if (count === 0) {
					container.html('<li style="color:#999; justify-content:center; padding:20px;">Queue is empty.</li>');
				} else {
					container.html(html);
				}
				$('#queue_count').text(count);
			}

			$(document).on('change', '.sub-checkbox', updateQueueVisuals);
			$('#cb-select-all-1').on('click', function() {
				$('.sub-checkbox').prop('checked', this.checked);
				updateQueueVisuals();
			});

			$('#add_manual_btn').on('click', function() {
				var id = $('#manual_id_input').val();
				if (id) {
					if ($('input[value="' + id + '"]').length === 0) {
						$('tbody').prepend('<tr><th class="check-column"><input type="checkbox" name="submission_ids[]" value="' + id + '" class="sub-checkbox" checked></th><td><strong>#' + id + '</strong></td><td colspan="4">(Manual Entry)</td></tr>');
					} else {
						$('input[value="' + id + '"]').prop('checked', true);
					}
					$('#manual_id_input').val('');
					updateQueueVisuals();
				}
			});

			/* ------------------------------------------------------------------ */
			/*  Modal logic
			/* ------------------------------------------------------------------ */
			var modal = $('#edit_payload_modal');
			$('.e-retrigger-close').on('click', function() {
				modal.hide();
			});
			$(window).on('click', function(e) {
				if ($(e.target).is(modal)) modal.hide();
			});

			$(document).on('click', '.edit-payload-btn', function() {
				var id = $(this).data('id');
				$('#modal_sub_id').val(id);
				$('#modal_fields_container').html('');
				$('#modal_loading').show();
				modal.show();

				$.post(ajaxurl, {
					action: '<?php echo self::AJAX_GET_DATA; ?>',
					nonce: '<?php echo wp_create_nonce( self::AJAX_ACTION ); ?>',
					id: id
				}, function(res) {
					$('#modal_loading').hide();
					if (res.success) {
						var html = '';
						$.each(res.data, function(key, val) {
							html += '<div class="e-retrigger-field-row"><label>' + key + '</label><input type="text" name="custom_fields[' + key + ']" value="' + val + '"></div>';
						});
						$('#modal_fields_container').html(html);
					} else {
						$('#modal_fields_container').html('<p style="color:red;">Error: ' + res.data.message + '</p>');
					}
				});
			});

			$('#modal_run_btn').on('click', function() {
				var id = $('#modal_sub_id').val();
				var customData = {};
				$('#modal_form').serializeArray().forEach(function(item) {
					var match = item.name.match(/custom_fields\[(.*?)\]/);
					if (match) customData[match[1]] = item.value;
				});

				var actions = [];
				$('.action-cb:checked').each(function() {
					actions.push($(this).val());
				});
				if (actions.length === 0) {
					alert('Please select actions from the main screen first.');
					return;
				}

				$(this).text('Processing...').prop('disabled', true);

				$.post(ajaxurl, {
					action: '<?php echo self::AJAX_ACTION; ?>',
					nonce: '<?php echo wp_create_nonce( self::AJAX_ACTION ); ?>',
					id: id,
					target_actions: actions,
					custom_fields: customData
				}, function(res) {
					$('#modal_run_btn').text('Run with Changes').prop('disabled', false);
					modal.hide();
					if (res.success) alert('Success: ' + res.data.message);
					else alert('Failed: ' + (res.data ? res.data.message : 'Unknown error'));
				});
			});

			/* ------------------------------------------------------------------ */
			/*  Batch process logic
			/* ------------------------------------------------------------------ */
			$('#start_process_btn').on('click', function() {
				var ids = [];
				$('.sub-checkbox:checked').each(function() {
					ids.push($(this).val());
				});
				var actions = [];
				$('.action-cb:checked').each(function() {
					actions.push($(this).val());
				});

				if (ids.length === 0) {
					alert('Queue is empty.');
					return;
				}
				if (actions.length === 0) {
					alert('Select at least one action.');
					return;
				}
				if (!confirm('Re‑trigger ' + actions.join(', ') + ' for ' + ids.length + ' submissions?')) return;

				$('#console_output').html('');
				$('#start_process_btn').prop('disabled', true);

				var total = ids.length;
				var current = 0;

				function log(msg, type = 'info') {
					var color = '#50fa7b';
					if (type === 'error') color = '#ff5555';
					if (type === 'warn') color = '#f1c40f';
					var now = new Date();
					var time = now.toLocaleTimeString('en-GB', { hour12: false });
					$('#console_output').append('<div style="color:' + color + ';">[' + time + '] ' + msg + '</div>');
					var d = $('#console_output');
					d.scrollTop(d.prop('scrollHeight'));
				}

				function processNext() {
					if (ids.length === 0) {
						log('-----------------------------------');
						log('BATCH COMPLETE.', 'info');
						$('#queue_status').text('Done');
						$('#start_process_btn').prop('disabled', false);
						return;
					}
					var id = ids.shift();
					current++;
					$('#queue_status').text('Processing ' + current + '/' + total);
					$('#q-item-' + id).removeClass('failed success').addClass('processing');
					log('Processing ID #' + id + '...', 'warn');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: '<?php echo self::AJAX_ACTION; ?>',
							nonce: '<?php echo wp_create_nonce( self::AJAX_ACTION ); ?>',
							id: id,
							target_actions: actions
						},
						success: function(response) {
							$('#q-item-' + id).removeClass('processing');
							if (response.success) {
								$('#q-item-' + id).addClass('success');
								log('Success: ' + response.data.message);
							} else {
								$('#q-item-' + id).addClass('failed');
								var errMsg = response.data ? response.data.message : 'Unknown error';
								log('Failed: ' + errMsg, 'error');
							}
							processNext();
						},
						error: function(xhr, status, error) {
							$('#q-item-' + id).removeClass('processing').addClass('failed');
							log('Server Error: ' + error, 'error');
							processNext();
						}
					});
				}
				log('Starting Batch of ' + total + ' submissions...');
				processNext();
			});
		});
		</script>
		<?php
	}

	/* ------------------------------------------------------------------ */
	/*  AJAX: Get submission data for modal
	/* ------------------------------------------------------------------ */
	public function ajax_get_submission_data() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		$id = absint( $_POST['id'] );

		if ( ! class_exists( '\ElementorPro\Modules\Forms\Submissions\Database\Query' ) ) {
			wp_send_json_error( [ 'message' => 'Elementor Pro missing' ] );
		}

		$query      = \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance();
		$submission = $query->get_submission( $id );

		if ( ! $submission || empty( $submission['data']['values'] ) ) {
			wp_send_json_error( [ 'message' => 'No data found' ] );
		}

		$data = [];
		foreach ( $submission['data']['values'] as $val ) {
			$data[ $val['key'] ] = $val['value'];
		}

		wp_send_json_success( $data );
	}

	/* ------------------------------------------------------------------ */
	/*  AJAX: Process request
	/* ------------------------------------------------------------------ */
	public function ajax_process_request() {
		check_ajax_referer( self::AJAX_ACTION, 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ] );
		}

		$id            = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$actions       = isset( $_POST['target_actions'] ) ? (array) $_POST['target_actions'] : [];
		$custom_fields = isset( $_POST['custom_fields'] ) ? (array) $_POST['custom_fields'] : null;

		if ( ! $id || empty( $actions ) ) {
			wp_send_json_error( [ 'message' => 'Invalid Data' ] );
		}

		$result = $this->execute_retrigger( $id, $actions, $custom_fields );

		/* Prepare debug info (error or payload) */
		$debug_info = $this->webhook_debug_info;
		if ( is_array( $custom_fields ) ) {
			$debug_info = "EDITED PAYLOAD:\n" . json_encode( $custom_fields, JSON_PRETTY_PRINT ) . "\n\n" . $debug_info;
		}

		if ( is_wp_error( $result ) ) {
			$this->log_to_db( $id, implode( ',', $actions ), 'failed', $result->get_error_message(), $debug_info );
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		} else {
			$this->log_to_db( $id, implode( ',', $actions ), 'success', 'Actions executed successfully', $debug_info );
			wp_send_json_success( [ 'message' => implode( ', ', $result ) ] );
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Logging
	/* ------------------------------------------------------------------ */
	private function log_to_db( $submission_id, $actions, $status, $message, $debug_info ) {
		global $wpdb;
		$table = $wpdb->prefix . self::LOG_TABLE;
		$wpdb->insert(
			$table,
			[
				'submission_id' => $submission_id,
				'actions'       => $actions,
				'status'        => $status,
				'message'       => $message,
				'full_debug'    => $debug_info,
				'created_at'    => current_time( 'mysql' ),
			]
		);
	}

	/* ------------------------------------------------------------------ */
	/*  Execute retrigger
	/* ------------------------------------------------------------------ */
	private function execute_retrigger( $submission_id, $target_actions, $custom_fields = null ) {
		if ( ! class_exists( '\ElementorPro\Modules\Forms\Submissions\Database\Query' ) ) {
			return new WP_Error( 'missing_dep', 'Elementor Pro Submissions module missing.' );
		}

		$query          = \ElementorPro\Modules\Forms\Submissions\Database\Query::get_instance();
		$submission_res = $query->get_submission( $submission_id );

		if ( ! $submission_res || empty( $submission_res['data'] ) ) {
			return new WP_Error( 'not_found', "Submission #$submission_id not found." );
		}

		$data = $submission_res['data'];

		/* Handle custom fields (edit mode) */
		if ( is_array( $custom_fields ) ) {
			$formatted_fields = $custom_fields;
		} else {
			$values          = isset( $data['values'] ) ? $data['values'] : [];
			$formatted_fields = [];
			foreach ( $values as $val ) {
				$formatted_fields[ $val['key'] ] = $val['value'];
			}
		}

		$meta_data = [
			'remote_ip'     => [ 'value' => $data['user_ip'] ?? '', 'title' => 'Remote IP' ],
			'user_agent'    => [ 'value' => $data['user_agent'] ?? '', 'title' => 'User Agent' ],
			'page_url'      => [ 'value' => $data['referer'] ?? '', 'title' => 'Page URL' ],
			'page_title'    => [ 'value' => $data['referer_title'] ?? '', 'title' => 'Page Title' ],
			'date'          => [ 'value' => $data['created_at'] ?? '', 'title' => 'Date' ],
			'time'          => [ 'value' => date( 'H:i:s', strtotime( $data['created_at'] ?? 'now' ) ), 'title' => 'Time' ],
		];

		$post_id     = isset( $data['post']['id'] ) ? (int) $data['post']['id'] : 0;
		$element_id  = isset( $data['element_id'] ) ? $data['element_id'] : '';
		if ( ! $post_id || ! $element_id ) {
			return new WP_Error( 'data_error', "Missing Post/Element ID." );
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );
		if ( ! $document ) {
			return new WP_Error( 'no_doc', "Original Page (ID: $post_id) no longer exists." );
		}

		$elements_data   = $document->get_elements_data();
		$widget_settings = $this->find_element_settings( $elements_data, $element_id );
		if ( ! $widget_settings ) {
			return new WP_Error( 'no_widget', "Form Widget not found." );
		}

		$this->sanitize_settings( $widget_settings, $element_id );

		$mock_record = $this->create_mock_record( $formatted_fields, $widget_settings, $post_id, $element_id, $meta_data );
		$mock_ajax   = new class {
			public $data = [];
			public function add_response_data( $k, $d ) {
				$this->data[ $k ] = $d;
			}
			public function add_admin_error_message( $m ) {}
			public function add_error_message( $m ) {}
			public function get_current_form() {
				return [ 'id' => 'mock' ];
			}
		};

		$executed          = [];
		$actions_registry  = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' )->get_form_actions();
		add_action( 'elementor_pro/forms/webhooks/response', [ $this, 'capture_webhook_error' ], 10, 2 );

		foreach ( $target_actions as $action_slug ) {
			$enabled_actions = $widget_settings['submit_actions'] ?? [];
			if ( ! in_array( $action_slug, $enabled_actions, true ) ) {
				continue;
			}
			$action_instance = $actions_registry[ $action_slug ] ?? null;
			if ( $action_instance ) {
				try {
					if ( 'activity-log' === $action_slug && ! function_exists( 'aal_insert_log' ) ) {
						continue;
					}
					$this->webhook_debug_info = '';
					$action_instance->run( $mock_record, $mock_ajax );
					$query->add_action_log( $submission_id, $action_instance, 'success', 'Manual Re‑trigger via Tool' );
					$executed[] = $action_slug;
				} catch ( \Exception $e ) {
					$error_msg = $this->webhook_debug_info ? $this->webhook_debug_info : $e->getMessage();
					$query->add_action_log( $submission_id, $action_instance, 'failed', 'Manual Re‑trigger Failed: ' . $error_msg );
					if ( empty( $this->webhook_debug_info ) ) {
						$this->webhook_debug_info = $e->getMessage();
					}
					return new WP_Error( 'action_fail', "$action_slug failed: $error_msg" );
				}
			}
		}
		remove_action( 'elementor_pro/forms/webhooks/response', [ $this, 'capture_webhook_error' ] );

		if ( empty( $executed ) ) {
			return new WP_Error( 'no_run', 'No matching enabled actions found.' );
		}
		return $executed;
	}

	/* ------------------------------------------------------------------ */
	/*  Capture webhook error
	/* ------------------------------------------------------------------ */
	public function capture_webhook_error( $response, $record ) {
		if ( is_wp_error( $response ) ) {
			$this->webhook_debug_info = 'WP Error: ' . $response->get_error_message();
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $code ) {
				$msg  = wp_remote_retrieve_response_message( $response );
				$body = wp_remote_retrieve_body( $response );
				$this->webhook_debug_info = "HTTP $code ($msg). Response: $body";
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Sanitize settings
	/* ------------------------------------------------------------------ */
	private function sanitize_settings( &$settings, $element_id ) {
		$settings['id']          = $element_id;
		$settings['form_name']   = $settings['form_name'] ?? 'Elementor Form';
		$defaults = [
			'email_to',
			'email_to_2',
			'email_subject',
			'email_subject_2',
			'email_content',
			'email_content_2',
			'email_from',
			'email_from_2',
			'email_from_name',
			'email_from_name_2',
			'email_reply_to',
			'email_reply_to_2',
			'email_to_cc',
			'email_to_cc_2',
			'email_to_bcc',
			'email_to_bcc_2',
			'email_content_type',
			'email_content_type_2',
		];
		foreach ( $defaults as $key ) {
			if ( ! isset( $settings[ $key ] ) ) {
				$settings[ $key ] = '';
			}
		}
		$arrays = [ 'form_metadata', 'form_metadata_2', 'submissions_metadata' ];
		foreach ( $arrays as $key ) {
			if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				$settings[ $key ] = [];
			}
		}
		if ( isset( $settings['form_fields'] ) && is_array( $settings['form_fields'] ) ) {
			foreach ( $settings['form_fields'] as $k => $field ) {
				if ( ! isset( $field['attachment_type'] ) ) {
					$settings['form_fields'][ $k ]['attachment_type'] = '';
				}
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/*  Create mock record
	/* ------------------------------------------------------------------ */
	private function create_mock_record( $fields, $settings, $post_id, $form_id, $meta ) {
		return new class( $fields, $settings, $post_id, $form_id, $meta ) {
			private $fields, $settings, $post_id, $form_id, $meta;
			public function __construct( $f, $s, $p, $fid, $m ) {
				$this->fields   = $f;
				$this->settings = $s;
				$this->post_id  = $p;
				$this->form_id  = $fid;
				$this->meta     = $m;
			}
			public function get_form_settings( $k = null ) {
				return $k ? ( $this->settings[ $k ] ?? null ) : $this->settings;
			}
			public function get_formatted_data( $flat = false ) {
				return $this->fields;
			}
			public function get( $key ) {
				if ( 'fields' === $key ) {
					$out = [];
					foreach ( $this->fields as $k => $v ) {
						$out[ $k ] = [
							'id'           => $k,
							'custom_id'    => $k,
							'value'        => $v,
							'raw_value'    => $v,
							'title'        => $k,
							'type'         => 'text',
							'attachment_type' => '',
						];
					}
					return $out;
				}
				if ( 'sent_data' === $key ) {
					return $this->fields;
				}
				if ( 'files' === $key ) {
					return [];
				}
				if ( 'meta' === $key ) {
					return $this->meta;
				}
				if ( 'form_settings' === $key ) {
					return $this->settings;
				}
				return null;
			}
			public function replace_setting_shortcodes( $s ) {
				if ( ! is_string( $s ) ) {
					return $s;
				}
				foreach ( $this->fields as $k => $v ) {
					$s = str_replace( [ "[field id=\"$k\"]", "[$k]" ], (string) $v, $s );
				}
				return $s;
			}
			public function get_form_args() {
				return [ 'post_id' => $this->post_id, 'form_id' => $this->form_id ];
			}
			public function get_form_meta( $keys ) {
				$result = [];
				foreach ( $keys as $key ) {
					if ( isset( $this->meta[ $key ] ) ) {
						$result[ $key ] = $this->meta[ $key ];
					}
				}
				return $result;
			}
		};
	}

	/* ------------------------------------------------------------------ */
	/*  Get filtered submissions (for the Run Tool)
	/* ------------------------------------------------------------------ */
	private function get_filtered_submissions( $form_name = '', $date = '', $search = '', $paged = 1, $orderby = 'created_at', $order = 'DESC' ) {
		global $wpdb;
		$table      = $wpdb->prefix . 'e_submissions';
		$val_table  = $wpdb->prefix . 'e_submissions_values';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
			return [ 'rows' => [], 'total' => 0 ];
		}

		$where = 'WHERE 1=1';
		$args  = [];

		if ( ! empty( $form_name ) ) {
			$where .= ' AND s.form_name = %s';
			$args[] = $form_name;
		}
		if ( ! empty( $date ) ) {
			$where .= ' AND s.created_at LIKE %s';
			$args[] = $date . '%';
		}
		if ( ! empty( $search ) ) {
			if ( is_numeric( $search ) ) {
				$where .= ' AND s.id = %d';
				$args[] = $search;
			} else {
				$where .= ' AND v.value LIKE %s';
				$args[] = '%' . $wpdb->esc_like( $search ) . '%';
			}
		}

		$count_sql = "SELECT COUNT(DISTINCT s.id) FROM $table s LEFT JOIN $val_table v ON s.main_meta_id = v.id $where";
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $args );
		}
		$total = $wpdb->get_var( $count_sql );

		$offset = ( $paged - 1 ) * self::PER_PAGE;
		$sql    = "SELECT DISTINCT s.id, s.form_name, s.created_at, v.value as email FROM $table s LEFT JOIN $val_table v ON s.main_meta_id = v.id $where ORDER BY s.$orderby $order LIMIT %d OFFSET %d";
		$query_args = array_merge( $args, [ self::PER_PAGE, $offset ] );
		$sql = $wpdb->prepare( $sql, $query_args );

		return [ 'rows' => $wpdb->get_results( $sql ), 'total' => $total ];
	}

	/* ------------------------------------------------------------------ */
	/*  Get unique form names (cached)
	/* ------------------------------------------------------------------ */
	private function get_unique_forms() {
		if ( false === ( $forms = get_transient( 'e_retrigger_forms' ) ) ) {
			global $wpdb;
			$table = $wpdb->prefix . 'e_submissions';
			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) != $table ) {
				return [];
			}
			$forms = $wpdb->get_col( "SELECT DISTINCT form_name FROM $table ORDER BY form_name ASC" );
			set_transient( 'e_retrigger_forms', $forms, 12 * HOUR_IN_SECONDS );
		}
		return $forms;
	}

	/* ------------------------------------------------------------------ */
	/*  Find element settings in nested element tree
	/* ------------------------------------------------------------------ */
	private function find_element_settings( $elements, $element_id ) {
		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) && $element['id'] === $element_id ) {
				return $element['settings'];
			}
			if ( ! empty( $element['elements'] ) ) {
				$found = $this->find_element_settings( $element['elements'], $element_id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}

new Elementor_Retrigger_Tool();

