<?php
/**
 * Logs List Table Class
 *
 * Extends WP_List_Table to provide a professional table view for logs
 * with pagination, sorting, filtering, and bulk actions.
 *
 * @package ElementorRetriggerTool
 */

namespace ElementorRetriggerTool;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Logs_List_Table
 *
 * Professional logs table with full WordPress standards compliance
 */
class Logs_List_Table extends \WP_List_Table {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Per page limit
	 *
	 * @var int
	 */
	private $per_page = 20;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'e_retrigger_logs';

		parent::__construct(
			[
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'cb'            => '<input type="checkbox" />',
			'created_at'    => __( 'Date', 'elementor-retrigger-tool' ),
			'submission_id' => __( 'Sub ID', 'elementor-retrigger-tool' ),
			'actions'       => __( 'Actions', 'elementor-retrigger-tool' ),
			'status'        => __( 'Status', 'elementor-retrigger-tool' ),
			'message'       => __( 'Message', 'elementor-retrigger-tool' ),
			'details'       => __( 'Details', 'elementor-retrigger-tool' ),
		];
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return [
			'created_at'    => [ 'created_at', true ],
			'submission_id' => [ 'submission_id', false ],
			'status'        => [ 'status', false ],
		];
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return [
			'delete' => __( 'Delete', 'elementor-retrigger-tool' ),
		];
	}

	/**
	 * Process bulk actions
	 */
	public function process_bulk_action() {
		global $wpdb;

		// Security check
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'bulk-' . $this->_args['plural'] ) ) {
			return;
		}

		// Delete action
		if ( 'delete' === $this->current_action() ) {
			if ( ! empty( $_POST['log'] ) ) {
				$log_ids = array_map( 'absint', $_POST['log'] );
				if ( ! empty( $log_ids ) ) {
					$placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)", $log_ids ) );

					add_settings_error(
						'e_retrigger',
						'logs_deleted',
						sprintf(
							/* translators: %d: number of logs deleted */
							__( 'Deleted %d log(s).', 'elementor-retrigger-tool' ),
							count( $log_ids )
						),
						'updated'
					);
				}
			}
		}
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		global $wpdb;

		// Process bulk actions first
		$this->process_bulk_action();

		// Get parameters
		$paged         = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$orderby       = isset( $_GET['orderby'] ) ? sanitize_text_field( $_GET['orderby'] ) : 'created_at';
		$order         = isset( $_GET['order'] ) ? sanitize_text_field( $_GET['order'] ) : 'DESC';
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';

		// Validate orderby
		$valid_orderby = [ 'created_at', 'submission_id', 'status', 'id' ];
		if ( ! in_array( $orderby, $valid_orderby, true ) ) {
			$orderby = 'created_at';
		}

		// Validate order
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		// Build WHERE clause
		$where  = [];
		$params = [];

		if ( ! empty( $search ) ) {
			$where[]  = '(submission_id LIKE %s OR message LIKE %s OR actions LIKE %s)';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
			$params[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		if ( ! empty( $filter_status ) && in_array( $filter_status, [ 'success', 'failed' ], true ) ) {
			$where[]  = 'status = %s';
			$params[] = $filter_status;
		}

		$where_sql = ! empty( $where ) ? ' WHERE ' . implode( ' AND ', $where ) : '';

		// Get total items
		$total_items = $wpdb->get_var(
			empty( $params )
				? "SELECT COUNT(*) FROM {$this->table_name} $where_sql"
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table_name} $where_sql", $params )
		);

		// Calculate offset
		$offset = ( $paged - 1 ) * $this->per_page;

		// Get items
		$sql          = "SELECT * FROM {$this->table_name} $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
		$params[]     = $this->per_page;
		$params[]     = $offset;
		$this->items = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

		// Set pagination
		$this->set_pagination_args(
			[
				'total_items' => $total_items,
				'per_page'    => $this->per_page,
				'total_pages' => ceil( $total_items / $this->per_page ),
			]
		);
	}

	/**
	 * Checkbox column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log[]" value="%d" />', $item->id );
	}

	/**
	 * Created at column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_created_at( $item ) {
		return esc_html( $item->created_at );
	}

	/**
	 * Submission ID column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_submission_id( $item ) {
		$sub_link = admin_url( 'admin.php?page=e-form-submissions&action=view&id=' . $item->submission_id );
		return sprintf(
			'<a href="%s" target="_blank">#%d <span class="dashicons dashicons-external"></span></a>',
			esc_url( $sub_link ),
			absint( $item->submission_id )
		);
	}

	/**
	 * Actions column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_actions( $item ) {
		return esc_html( $item->actions );
	}

	/**
	 * Status column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_status( $item ) {
		$color = $item->status === 'success' ? '#46b450' : '#dc3232';
		return sprintf(
			'<span style="color:#fff; background:%s; padding: 3px 8px; border-radius: 3px; font-size: 10px; text-transform: uppercase;">%s</span>',
			esc_attr( $color ),
			esc_html( $item->status )
		);
	}

	/**
	 * Message column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_message( $item ) {
		return '<strong>' . esc_html( $item->message ) . '</strong>';
	}

	/**
	 * Details column
	 *
	 * @param object $item Item object.
	 * @return string
	 */
	public function column_details( $item ) {
		$buttons = [];

		// Debug info button
		if ( ! empty( $item->full_debug ) ) {
			$buttons[] = sprintf(
				'<button type="button" class="button button-small view-log-debug" data-log-id="%d" data-debug="%s">
					<span class="dashicons dashicons-visibility" style="line-height:1.3;"></span> %s
				</button>',
				absint( $item->id ),
				esc_attr( $item->full_debug ),
				__( 'Debug', 'elementor-retrigger-tool' )
			);
		}

		// Request/Response button
		if ( ! empty( $item->request_data ) || ! empty( $item->response_data ) ) {
			$buttons[] = sprintf(
				'<button type="button" class="button button-small view-log-request-response"
					data-log-id="%d"
					data-request="%s"
					data-response="%s"
					data-submission-id="%d">
					<span class="dashicons dashicons-editor-code" style="line-height:1.3;"></span> %s
				</button>',
				absint( $item->id ),
				! empty( $item->request_data ) ? esc_attr( $item->request_data ) : '',
				! empty( $item->response_data ) ? esc_attr( $item->response_data ) : '',
				absint( $item->submission_id ),
				__( 'Request/Response', 'elementor-retrigger-tool' )
			);
		}

		return ! empty( $buttons ) ? implode( ' ', $buttons ) : '<span style="color:#999;">—</span>';
	}

	/**
	 * Default column
	 *
	 * @param object $item        Item object.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '—';
	}

	/**
	 * Extra tablenav for filters
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_text_field( $_GET['filter_status'] ) : '';
		?>
		<div class="alignleft actions">
			<select name="filter_status">
				<option value=""><?php esc_html_e( 'All Statuses', 'elementor-retrigger-tool' ); ?></option>
				<option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Success', 'elementor-retrigger-tool' ); ?></option>
				<option value="failed" <?php selected( $filter_status, 'failed' ); ?>><?php esc_html_e( 'Failed', 'elementor-retrigger-tool' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'elementor-retrigger-tool' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Message to show when no items found
	 */
	public function no_items() {
		esc_html_e( 'No logs found.', 'elementor-retrigger-tool' );
	}
}
