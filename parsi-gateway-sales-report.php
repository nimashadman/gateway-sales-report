<?php
/**
 * Plugin Name: Parsi Gateway Sales Report for WooCommerce
 * Plugin URI: https://github.com/nimashadman/gateway-sales-report
 * Description: گزارش فروش ووکامرس بر اساس درگاه‌های پرداخت با بازه تاریخ شمسی و خروجی اکسل فارسی.
 * Version: 1.0.0
 * Author: نیما شادمان
 * Author URI: https://nimashadman.ir
 * Text Domain: parsi-gateway-sales-report
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package ParsiGatewaySalesReport
 */

defined( 'ABSPATH' ) || exit;

define( 'PGSR_VERSION', '1.0.0' );
define( 'PGSR_FILE', __FILE__ );
define( 'PGSR_PATH', plugin_dir_path( __FILE__ ) );
define( 'PGSR_URL', plugin_dir_url( __FILE__ ) );

require_once PGSR_PATH . 'includes/class-pgsr-jalali.php';
require_once PGSR_PATH . 'includes/class-pgsr-report-service.php';
require_once PGSR_PATH . 'includes/class-pgsr-excel-exporter.php';
require_once PGSR_PATH . 'includes/class-pgsr-admin.php';

/**
 * Boots the plugin after WooCommerce is available.
 */
function pgsr_bootstrap() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>';
				esc_html_e( 'Parsi Gateway Sales Report needs WooCommerce to be installed and active.', 'parsi-gateway-sales-report' );
				echo '</p></div>';
			}
		);

		return;
	}

	PGSR_Admin::instance();
}
add_action( 'plugins_loaded', 'pgsr_bootstrap' );

/**
 * Declares compatibility with WooCommerce HPOS.
 */
function pgsr_declare_woocommerce_features() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'pgsr_declare_woocommerce_features' );
