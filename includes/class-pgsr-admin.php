<?php
/**
 * Admin screen.
 *
 * @package ParsiGatewaySalesReport
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds the WooCommerce report page.
 */
class PGSR_Admin {
	/**
	 * Singleton instance.
	 *
	 * @var PGSR_Admin|null
	 */
	private static $instance = null;

	/**
	 * Report service.
	 *
	 * @var PGSR_Report_Service
	 */
	private $service;

	/**
	 * Gets singleton instance.
	 *
	 * @return PGSR_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->service = new PGSR_Report_Service();

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pgsr_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Registers admin menu.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Parsi Gateway Sales Report', 'parsi-gateway-sales-report' ),
			__( 'Gateway Sales Report', 'parsi-gateway-sales-report' ),
			'manage_woocommerce',
			'pgsr-report',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Loads assets on the plugin page.
	 *
	 * @param string $hook Current hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_pgsr-report' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'pgsr-admin', PGSR_URL . 'assets/css/admin.css', array(), PGSR_VERSION );
		wp_enqueue_script( 'pgsr-admin', PGSR_URL . 'assets/js/admin.js', array(), PGSR_VERSION, true );
	}

	/**
	 * Renders report page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'parsi-gateway-sales-report' ) );
		}

		$defaults = $this->get_default_dates();
		$from     = isset( $_GET['pgsr_from'] ) ? sanitize_text_field( wp_unslash( $_GET['pgsr_from'] ) ) : $defaults['from'];
		$to       = isset( $_GET['pgsr_to'] ) ? sanitize_text_field( wp_unslash( $_GET['pgsr_to'] ) ) : $defaults['to'];
		$gateway  = isset( $_GET['pgsr_gateway'] ) ? sanitize_key( wp_unslash( $_GET['pgsr_gateway'] ) ) : '';
		$report   = $this->service->get_report( $from, $to, $gateway );
		$gateways = $this->service->get_gateways();
		?>
		<div class="wrap pgsr-wrap" dir="rtl">
			<h1><?php esc_html_e( 'Parsi Gateway Sales Report', 'parsi-gateway-sales-report' ); ?></h1>
			<p class="pgsr-subtitle"><?php esc_html_e( 'Sales report for completed and successfully paid WooCommerce orders, grouped by payment gateway.', 'parsi-gateway-sales-report' ); ?></p>

			<form method="get" class="pgsr-filter">
				<input type="hidden" name="page" value="pgsr-report">
				<div class="pgsr-field">
					<label for="pgsr_from"><?php esc_html_e( 'From Jalali date', 'parsi-gateway-sales-report' ); ?></label>
					<input id="pgsr_from" class="pgsr-jalali-input" name="pgsr_from" type="text" value="<?php echo esc_attr( PGSR_Jalali::persian_digits( $from ) ); ?>" placeholder="۱۴۰۳/۰۱/۰۱" inputmode="numeric" autocomplete="off">
				</div>
				<div class="pgsr-field">
					<label for="pgsr_to"><?php esc_html_e( 'To Jalali date', 'parsi-gateway-sales-report' ); ?></label>
					<input id="pgsr_to" class="pgsr-jalali-input" name="pgsr_to" type="text" value="<?php echo esc_attr( PGSR_Jalali::persian_digits( $to ) ); ?>" placeholder="۱۴۰۳/۱۲/۲۹" inputmode="numeric" autocomplete="off">
				</div>
				<div class="pgsr-field">
					<label for="pgsr_gateway"><?php esc_html_e( 'Gateway', 'parsi-gateway-sales-report' ); ?></label>
					<select id="pgsr_gateway" name="pgsr_gateway">
						<option value=""><?php esc_html_e( 'All gateways', 'parsi-gateway-sales-report' ); ?></option>
						<?php foreach ( $gateways as $gateway_id => $item ) : ?>
							<option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( $gateway, $gateway_id ); ?>>
								<?php echo esc_html( $item['title'] . ( $item['enabled'] ? '' : ' - غیرفعال' ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="pgsr-actions">
					<button class="button button-primary" type="submit"><?php esc_html_e( 'Show report', 'parsi-gateway-sales-report' ); ?></button>
					<?php if ( ! is_wp_error( $report ) ) : ?>
						<a class="button pgsr-export" href="<?php echo esc_url( $this->export_url( $from, $to, $gateway ) ); ?>"><?php esc_html_e( 'Download Persian Excel', 'parsi-gateway-sales-report' ); ?></a>
					<?php endif; ?>
				</div>
			</form>

			<?php if ( is_wp_error( $report ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $report->get_error_message() ); ?></p></div>
			<?php else : ?>
				<?php $this->render_report( $report ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handles Excel export.
	 *
	 * @return void
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to export this report.', 'parsi-gateway-sales-report' ) );
		}

		check_admin_referer( 'pgsr_export' );

		$from    = isset( $_GET['pgsr_from'] ) ? sanitize_text_field( wp_unslash( $_GET['pgsr_from'] ) ) : '';
		$to      = isset( $_GET['pgsr_to'] ) ? sanitize_text_field( wp_unslash( $_GET['pgsr_to'] ) ) : '';
		$gateway = isset( $_GET['pgsr_gateway'] ) ? sanitize_key( wp_unslash( $_GET['pgsr_gateway'] ) ) : '';
		$report  = $this->service->get_report( $from, $to, $gateway );

		if ( is_wp_error( $report ) ) {
			wp_die( esc_html( $report->get_error_message() ) );
		}

		$exporter = new PGSR_Excel_Exporter();
		$exporter->download( $report );
	}

	/**
	 * Renders report widgets and tables.
	 *
	 * @param array $report Report data.
	 * @return void
	 */
	private function render_report( array $report ) {
		?>
		<div class="pgsr-cards">
			<div class="pgsr-card">
				<span><?php esc_html_e( 'Total sales', 'parsi-gateway-sales-report' ); ?></span>
				<strong><?php echo wp_kses_post( PGSR_Jalali::persian_digits( wc_price( $report['total'] ) ) ); ?></strong>
			</div>
			<div class="pgsr-card">
				<span><?php esc_html_e( 'Paid orders', 'parsi-gateway-sales-report' ); ?></span>
				<strong><?php echo esc_html( PGSR_Jalali::persian_digits( $report['orders_count'] ) ); ?></strong>
			</div>
			<div class="pgsr-card">
				<span><?php esc_html_e( 'Detected gateways', 'parsi-gateway-sales-report' ); ?></span>
				<strong><?php echo esc_html( PGSR_Jalali::persian_digits( count( $report['gateways'] ) ) ); ?></strong>
			</div>
		</div>

		<h2><?php esc_html_e( 'Gateway summary', 'parsi-gateway-sales-report' ); ?></h2>
		<table class="widefat striped pgsr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Gateway', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Gateway ID', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Orders', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Total', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Share', 'parsi-gateway-sales-report' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $report['summary'] ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No paid orders found in this date range.', 'parsi-gateway-sales-report' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $report['summary'] as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['gateway_title'] ); ?></td>
						<td><code><?php echo esc_html( $item['gateway_id'] ); ?></code></td>
						<td><?php echo esc_html( PGSR_Jalali::persian_digits( $item['orders_count'] ) ); ?></td>
						<td><?php echo wp_kses_post( PGSR_Jalali::persian_digits( wc_price( $item['total'] ) ) ); ?></td>
						<td><?php echo esc_html( PGSR_Jalali::persian_digits( $report['total'] > 0 ? round( ( $item['total'] / $report['total'] ) * 100, 2 ) . '%' : '0%' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Order details', 'parsi-gateway-sales-report' ); ?></h2>
		<table class="widefat striped pgsr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Date', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Status', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Gateway', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Total', 'parsi-gateway-sales-report' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $report['rows'] ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No order details to display.', 'parsi-gateway-sales-report' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td>#<?php echo esc_html( PGSR_Jalali::persian_digits( $row['order_number'] ) ); ?></td>
						<td><?php echo esc_html( $row['date'] ); ?></td>
						<td><?php echo esc_html( $row['status'] ); ?></td>
						<td><?php echo esc_html( $row['gateway_title'] ); ?></td>
						<td><?php echo esc_html( $row['customer'] ); ?></td>
						<td><?php echo wp_kses_post( PGSR_Jalali::persian_digits( wc_price( $row['total'], array( 'currency' => $row['currency'] ) ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Creates export URL.
	 *
	 * @param string $from Start date.
	 * @param string $to End date.
	 * @param string $gateway Gateway id.
	 * @return string
	 */
	private function export_url( $from, $to, $gateway ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'       => 'pgsr_export',
					'pgsr_from'    => PGSR_Jalali::normalize_digits( $from ),
					'pgsr_to'      => PGSR_Jalali::normalize_digits( $to ),
					'pgsr_gateway' => $gateway,
				),
				admin_url( 'admin-post.php' )
			),
			'pgsr_export'
		);
	}

	/**
	 * Returns current Jalali month default dates.
	 *
	 * @return array
	 */
	private function get_default_dates() {
		$timestamp = current_time( 'timestamp' );
		$jalali    = PGSR_Jalali::gregorian_to_jalali(
			(int) gmdate( 'Y', $timestamp ),
			(int) gmdate( 'n', $timestamp ),
			(int) gmdate( 'j', $timestamp )
		);

		return array(
			'from' => sprintf( '%04d/%02d/01', $jalali[0], $jalali[1] ),
			'to'   => sprintf( '%04d/%02d/%02d', $jalali[0], $jalali[1], $jalali[2] ),
		);
	}
}
