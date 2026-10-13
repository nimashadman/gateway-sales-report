<?php
/**
 * Excel exporter.
 *
 * @package ParsiGatewaySalesReport
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exports report as an Excel-compatible HTML workbook.
 */
class PGSR_Excel_Exporter {
	/**
	 * Sends the report file and exits.
	 *
	 * @param array $report Report data.
	 * @return void
	 */
	public function download( array $report ) {
		$filename = sprintf(
			'gateway-sales-%s-%s.xls',
			str_replace( '/', '-', PGSR_Jalali::normalize_digits( $report['from_jalali'] ) ),
			str_replace( '/', '-', PGSR_Jalali::normalize_digits( $report['to_jalali'] ) )
		);

		nocache_headers();
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo "\xEF\xBB\xBF";
		echo $this->render_html( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Renders Excel-compatible HTML.
	 *
	 * @param array $report Report data.
	 * @return string
	 */
	private function render_html( array $report ) {
		ob_start();
		?>
		<html dir="rtl" lang="fa-IR">
		<head>
			<meta charset="UTF-8">
			<style>
				body, table { font-family: Tahoma, Vazirmatn, Arial, sans-serif; direction: rtl; }
				table { border-collapse: collapse; width: 100%; }
				th { background: #17324d; color: #fff; font-weight: 700; }
				th, td { border: 1px solid #b9c3cf; padding: 8px; mso-number-format:"\@"; }
				.title { background: #eef4f8; font-size: 18px; font-weight: 700; color: #17324d; }
				.total { background: #e8f6ef; font-weight: 700; }
				.amount { text-align: left; direction: ltr; }
			</style>
		</head>
		<body>
			<table>
				<tr><td class="title" colspan="5"><?php echo esc_html( PGSR_Jalali::persian_digits( 'گزارش فروش درگاه‌ها از ' . $report['from_jalali'] . ' تا ' . $report['to_jalali'] ) ); ?></td></tr>
				<tr><td colspan="5"><?php echo esc_html( PGSR_Jalali::persian_digits( 'تعداد سفارش‌ها: ' . $report['orders_count'] . ' | جمع کل: ' . wc_format_decimal( $report['total'], wc_get_price_decimals() ) ) ); ?></td></tr>
			</table>
			<br>
			<table>
				<tr>
					<th><?php esc_html_e( 'Gateway', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Gateway ID', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Orders', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Total', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Share', 'parsi-gateway-sales-report' ); ?></th>
				</tr>
				<?php foreach ( $report['summary'] as $item ) : ?>
					<tr>
						<td><?php echo esc_html( $item['gateway_title'] ); ?></td>
						<td><?php echo esc_html( $item['gateway_id'] ); ?></td>
						<td><?php echo esc_html( PGSR_Jalali::persian_digits( $item['orders_count'] ) ); ?></td>
						<td class="amount"><?php echo esc_html( PGSR_Jalali::persian_digits( wc_format_decimal( $item['total'], wc_get_price_decimals() ) ) ); ?></td>
						<td><?php echo esc_html( PGSR_Jalali::persian_digits( $report['total'] > 0 ? round( ( $item['total'] / $report['total'] ) * 100, 2 ) . '%' : '0%' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr class="total">
					<td colspan="2"><?php esc_html_e( 'Total', 'parsi-gateway-sales-report' ); ?></td>
					<td><?php echo esc_html( PGSR_Jalali::persian_digits( $report['orders_count'] ) ); ?></td>
					<td class="amount"><?php echo esc_html( PGSR_Jalali::persian_digits( wc_format_decimal( $report['total'], wc_get_price_decimals() ) ) ); ?></td>
					<td><?php echo esc_html( PGSR_Jalali::persian_digits( '100%' ) ); ?></td>
				</tr>
			</table>
			<br>
			<table>
				<tr>
					<th><?php esc_html_e( 'Order', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Date', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Status', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Gateway', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Customer', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Total', 'parsi-gateway-sales-report' ); ?></th>
					<th><?php esc_html_e( 'Currency', 'parsi-gateway-sales-report' ); ?></th>
				</tr>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( PGSR_Jalali::persian_digits( $row['order_number'] ) ); ?></td>
						<td><?php echo esc_html( $row['date'] ); ?></td>
						<td><?php echo esc_html( $row['status'] ); ?></td>
						<td><?php echo esc_html( $row['gateway_title'] ); ?></td>
						<td><?php echo esc_html( $row['customer'] ); ?></td>
						<td class="amount"><?php echo esc_html( PGSR_Jalali::persian_digits( wc_format_decimal( $row['total'], wc_get_price_decimals() ) ) ); ?></td>
						<td><?php echo esc_html( $row['currency'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
