<?php
/**
 * Plugin Name: MCOD Minimalist Checkout for WooCommerce
 * Description: A minimalist, beautiful, and fast checkout for WooCommerce, inspired by shop.
 * Version: 1.0.0
 * Author: crleguizamon
 * Author URI: https://mcodform.com/
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Requires Plugins: woocommerce
 * License: GPLv3
 * Text Domain: mcod-minimalist-checkout
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define core constants
define( 'MCRPD_VERSION', '1.0.0' );
define( 'MCRPD_PLUGIN_FILE', __FILE__ );
define( 'MCRPD_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCRPD_URL', plugin_dir_url( __FILE__ ) );
define( 'MCRPD_INC', MCRPD_DIR . 'includes/' );
define( 'MCRPD_TEMPLATES', MCRPD_DIR . 'templates/' );
define( 'MCRPD_ASSETS', MCRPD_URL . 'assets/' );

/**
 * Initialize the plugin.
 */
function mcrpd_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'mcrpd_missing_wc_notice' );
		return;
	}

	// Include the main loader
	require_once MCRPD_INC . 'class-mcrpd-checkout-loader.php';
	
	// Include Settings page
	require_once MCRPD_INC . 'class-mcrpd-checkout-settings.php';
	new MCRPD_Checkout_Settings();

	// Initialize the loader
	MCRPD_Checkout_Loader::get_instance();
}
add_action( 'plugins_loaded', 'mcrpd_init' );

/**
 * Render admin notice when WooCommerce is missing.
 */
function mcrpd_missing_wc_notice() {
	echo '<div class="error"><p>' . esc_html__( 'Minimalist Checkout for WooCommerce requires WooCommerce to be installed and active.', 'mcod-minimalist-checkout' ) . '</p></div>';
}
