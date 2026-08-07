<?php
/**
 * Plugin Name: MCOD Minimalist Checkout for WooCommerce
 * Description: A minimalist and fast checkout for WooCommerce.
 * Version: 1.0.1
 * Author: crleguizamon
 * Author URI: https://mcodform.com/
 * Requires PHP: 7.4
 * Requires at least: 5.9
 * Requires Plugins: woocommerce
 * License: GPLv3
 * Text Domain: mcod-minimalist-checkout-for-woocommerce
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define core constants
define( 'MCMCHK_VERSION', '1.0.0' );
define( 'MCMCHK_PLUGIN_FILE', __FILE__ );
define( 'MCMCHK_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCMCHK_URL', plugin_dir_url( __FILE__ ) );
define( 'MCMCHK_INC', MCMCHK_DIR . 'includes/' );
define( 'MCMCHK_TEMPLATES', MCMCHK_DIR . 'templates/' );
define( 'MCMCHK_ASSETS', MCMCHK_URL . 'assets/' );

/**
 * Initialize the plugin.
 */
function mcmchk_init() {
	// Check if WooCommerce is active
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'mcmchk_missing_wc_notice' );
		return;
	}

	// Include the main loader
	require_once MCMCHK_INC . 'class-mcrpd-checkout-loader.php';
	
	// Include Settings page
	require_once MCMCHK_INC . 'class-mcrpd-checkout-settings.php';
	new MCMCHK_Checkout_Settings();

	// Initialize the loader
	MCMCHK_Checkout_Loader::get_instance();
}
add_action( 'plugins_loaded', 'mcmchk_init' );

/**
 * Render admin notice when WooCommerce is missing.
 */
function mcmchk_missing_wc_notice() {
	echo '<div class="error"><p>' . esc_html__( 'Minimalist Checkout for WooCommerce requires WooCommerce to be installed and active.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p></div>';
}
