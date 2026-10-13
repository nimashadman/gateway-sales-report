<?php
/**
 * Report data service.
 *
 * @package ParsiGatewaySalesReport
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds sales report data from WooCommerce orders.
 */
class PGSR_Report_Service {
	/**
	 * Gets installed payment gateways.
	 *
	 * @return array
	 */
	public function get_gateways() {
		$gateways = array();

		if ( function_exists( 'WC' ) && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
				$gateways[ $gateway_id ] = array(
					'id'      => $gateway_id,
					'title'   => $gateway->get_title(),
					'enabled' => 'yes' === $gateway->enabled,
				);
			}
		}

		return $gateways;
	}

	/**
	 * Builds report rows and totals.
	 *
	 * @param string $from_jalali Start Jalali date.
	 * @param string $to_jalali End Jalali date.
	 * @param string $gateway_filter Gateway id or empty.
	 * @return array|WP_Error
	 */
	public function get_report( $from_jalali, $to_jalali, $gateway_filter = '' ) {
		$from = PGSR_Jalali::jalali_to_mysql_datetime( $from_jalali, false );
		$to   = PGSR_Jalali::jalali_to_mysql_datetime( $to_jalali, true );

		if ( ! $from || ! $to ) {
			return new WP_Error( 'invalid_date', __( 'Please enter dates in yyyy/mm/dd Jalali format.', 'parsi-gateway-sales-report' ) );
		}

		if ( strtotime( $from ) > strtotime( $to ) ) {
			return new WP_Error( 'invalid_range', __( 'Start date must be before end date.', 'parsi-gateway-sales-report' ) );
		}

		$args = array(
			'limit'        => -1,
			'status'       => array( 'wc-completed' ),
			'date_created' => $from . '...' . $to,
			'orderby'      => 'date',
			'order'        => 'ASC',
			'return'       => 'objects',
		);

		if ( $gateway_filter ) {
			$args['payment_method'] = sanitize_key( $gateway_filter );
		}

		$orders   = wc_get_orders( $args );
		$gateways = $this->get_gateways();
		$summary  = array();
		$rows     = array();
		$total    = 0.0;

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order || ! $order->is_paid() ) {
				continue;
			}

			$method_id    = $order->get_payment_method();
			$method_title = $order->get_payment_method_title();
			if ( '' === $method_title && isset( $gateways[ $method_id ] ) ) {
				$method_title = $gateways[ $method_id ]['title'];
			}
			if ( '' === $method_title ) {
				$method_title = __( 'Unknown gateway', 'parsi-gateway-sales-report' );
			}

			$amount = (float) $order->get_total();
			$total += $amount;

			if ( ! isset( $summary[ $method_id ] ) ) {
				$summary[ $method_id ] = array(
					'gateway_id'    => $method_id,
					'gateway_title' => $method_title,
					'orders_count'  => 0,
					'total'         => 0.0,
				);
			}

			$summary[ $method_id ]['orders_count']++;
			$summary[ $method_id ]['total'] += $amount;

			$rows[] = array(
				'order_id'      => $order->get_id(),
				'order_number'  => $order->get_order_number(),
				'date'          => PGSR_Jalali::format_order_date( $order->get_date_created() ),
				'status'        => wc_get_order_status_name( $order->get_status() ),
				'gateway_id'    => $method_id,
				'gateway_title' => $method_title,
				'customer'      => trim( $order->get_formatted_billing_full_name() ),
				'total'         => $amount,
				'currency'      => $order->get_currency(),
			);
		}

		uasort(
			$summary,
			static function ( $a, $b ) {
				return $b['total'] <=> $a['total'];
			}
		);

		return array(
			'from_jalali'    => $from_jalali,
			'to_jalali'      => $to_jalali,
			'gateway_filter' => $gateway_filter,
			'gateways'       => $gateways,
			'summary'        => array_values( $summary ),
			'rows'           => $rows,
			'total'          => $total,
			'orders_count'   => count( $rows ),
			'generated_at'   => current_time( 'mysql' ),
		);
	}
}
