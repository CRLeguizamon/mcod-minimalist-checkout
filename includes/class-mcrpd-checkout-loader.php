<?php
/**
 * Main plugin loader class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class MCMCHK_Checkout_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var MCMCHK_Checkout_Loader|null
	 */
	private static $instance = null;

	/**
	 * Get active instance.
	 *
	 * @return MCMCHK_Checkout_Loader
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Register Page Template (use PHP_INT_MAX on template_include so Force Template overrides Elementor Page Templates at priority 11 and Theme Builder at priority 99)
		add_filter( 'theme_page_templates', array( $this, 'register_page_template' ) );
		add_filter( 'template_include', array( $this, 'load_page_template' ), PHP_INT_MAX );

		// Hook into WooCommerce templates override
		add_filter( 'woocommerce_locate_template', array( $this, 'override_woocommerce_template' ), 10, 3 );

		// Add custom body classes (use PHP_INT_MAX to clean up conflicting Elementor template classes when Minimalist mode is active)
		add_filter( 'body_class', array( $this, 'add_body_classes' ), PHP_INT_MAX );

		// Load assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );

		// Decouple payment from checkout order review hook
		add_action( 'template_redirect', array( $this, 'handle_checkout_decoupling' ) );

		// Sync WooCommerce AJAX order review updates
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_checkout_fragments' ) );

		// Ensure customer billing/shipping country is always within allowed store countries.
		// Prevents WooCommerce from returning empty address fields when a logged-in user or
		// Express Checkout wallet (Stripe Link / Apple Pay) sets a country outside allowed countries.
		add_action( 'wp', array( $this, 'mcmchk_ensure_valid_customer_countries' ), 5 );
		add_action( 'template_redirect', array( $this, 'mcmchk_ensure_valid_customer_countries' ), 5 );
		add_action( 'woocommerce_checkout_init', array( $this, 'mcmchk_ensure_valid_customer_countries' ), 5 );
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'mcmchk_ensure_valid_customer_countries' ), 1 );
		add_filter( 'woocommerce_checkout_get_value', array( $this, 'mcmchk_filter_checkout_country_value' ), 10, 2 );

		// Customize checkout fields
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), PHP_INT_MAX );

		// Register debug shortcode
		add_shortcode( 'mcmchk_debug', array( $this, 'render_debug_shortcode' ) );

		// AJAX login handler for inline checkout login
		add_action( 'wp_ajax_nopriv_mcmchk_ajax_login', array( $this, 'handle_ajax_login' ) );

		// AJAX handler for Minimalist Cart updates (quantity stepper, item removal, coupons)
		add_action( 'wp_ajax_mcmchk_update_cart_item', array( $this, 'handle_ajax_update_cart_item' ) );
		add_action( 'wp_ajax_nopriv_mcmchk_update_cart_item', array( $this, 'handle_ajax_update_cart_item' ) );

		// Save newsletter opt-in checkbox to order meta
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_newsletter_order_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_newsletter_order_meta_in_admin' ) );

		// Normalize state codes (e.g. AMA <-> CO-AMA) for Stripe Express Checkout (Link/Apple Pay/Google Pay) & Store API
		add_filter( 'rest_pre_dispatch', array( $this, 'normalize_store_api_address_states' ), 10, 3 );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'normalize_posted_checkout_states' ), 10, 1 );

		// WooCommerce Subscriptions: intercept the recurring totals block when a custom template is set.
		// 'wp' fires on normal page loads; 'woocommerce_checkout_update_order_review' fires during AJAX
		// checkout fragment refreshes so both paths are covered.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			add_action( 'wp', array( $this, 'mcmchk_setup_subscription_hooks' ), 20 );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'mcmchk_setup_subscription_hooks' ), 5 );
		}
	}

	/**
	 * Register the Minimalist Checkout and Minimalist Cart Page Templates.
	 */
	public function register_page_template( $templates ) {
		$templates['templates/minimalist-checkout-page.php'] = __( 'Minimalist Checkout', 'mcod-minimalist-checkout-for-woocommerce' );
		$templates['templates/minimalist-cart-page.php']     = __( 'Minimalist Cart', 'mcod-minimalist-checkout-for-woocommerce' );
		return $templates;
	}

	/**
	 * Load the custom Page Template from our plugin when Minimalist Checkout, Cart, or Order Received is active.
	 */
	public function load_page_template( $template ) {
		// Never override Order Pay endpoint
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return $template;
		}

		if ( self::is_minimalist_page() ) {
			$plugin_template = MCMCHK_TEMPLATES . 'minimalist-checkout-page.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Check if the current page or AJAX request represents the Minimalist Checkout.
	 *
	 * @return bool
	 */
	public static function is_minimalist_checkout() {
		// Never treat the Order Received (Thank You), Order Pay, or Cart page as the Minimalist Checkout form.
		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) {
			return false;
		}
		if ( ! wp_doing_ajax() && function_exists( 'is_cart' ) && is_cart() ) {
			return false;
		}

		$checkout_page_id = wc_get_page_id( 'checkout' );
		$cart_page_id     = wc_get_page_id( 'cart' );
		$settings         = get_option( 'mcmchk_settings', array() );
		$force_template   = ! empty( $settings['force_template'] );

		// Determine if we are currently in a WooCommerce checkout context (page load or checkout AJAX).
		$is_checkout_context = false;
		if ( wp_doing_ajax() ) {
			$wc_ajax = isset( $_GET['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $wc_ajax, array( 'update_order_review', 'checkout', 'apply_coupon', 'remove_coupon' ), true ) ) {
				$is_checkout_context = true;
			}
		} elseif ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$is_checkout_context = true;
		} elseif ( function_exists( 'is_page' ) && $checkout_page_id > 0 && is_page( $checkout_page_id ) ) {
			$is_checkout_context = true;
		}

		if ( $is_checkout_context ) {
			if ( $force_template ) {
				return true;
			}

			if ( $checkout_page_id > 0 ) {
				$meta_template = get_post_meta( $checkout_page_id, '_wp_page_template', true );
				if ( 'templates/minimalist-checkout-page.php' === $meta_template ) {
					return true;
				}
			}
		}

		// Fallback: check if the currently viewed non-cart page manually uses the Minimalist Checkout template.
		if ( ! wp_doing_ajax() && function_exists( 'is_page' ) && is_page() ) {
			$current_page_id = get_queried_object_id();
			if ( $current_page_id > 0 && (int) $current_page_id !== (int) $cart_page_id ) {
				$meta_template = get_post_meta( $current_page_id, '_wp_page_template', true );
				if ( 'templates/minimalist-checkout-page.php' === $meta_template ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if the current page or AJAX request represents the Minimalist Cart.
	 * Activated via 'force_cart_template' setting OR by assigning the Minimalist Cart / Minimalist Checkout page template.
	 *
	 * @return bool
	 */
	public static function is_minimalist_cart() {
		if ( wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'mcmchk_update_cart_item' === $action ) {
				return true;
			}
			return false;
		}

		if ( function_exists( 'is_wc_endpoint_url' ) && ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) ) {
			return false;
		}

		$cart_page_id        = wc_get_page_id( 'cart' );
		$settings            = get_option( 'mcmchk_settings', array() );
		$force_cart_template = ! empty( $settings['force_cart_template'] );

		$is_cart_context = ( function_exists( 'is_cart' ) && is_cart() ) || ( function_exists( 'is_page' ) && $cart_page_id > 0 && is_page( $cart_page_id ) );

		if ( $is_cart_context ) {
			if ( $force_cart_template ) {
				return true;
			}

			if ( $cart_page_id > 0 ) {
				$meta_template = get_post_meta( $cart_page_id, '_wp_page_template', true );
				if ( in_array( $meta_template, array( 'templates/minimalist-cart-page.php', 'templates/minimalist-checkout-page.php' ), true ) ) {
					return true;
				}
			}
		}

		// Fallback: check if any custom page manually uses the Minimalist Cart template.
		if ( function_exists( 'is_page' ) && is_page() ) {
			$current_page_id = get_queried_object_id();
			if ( $current_page_id > 0 ) {
				$meta_template = get_post_meta( $current_page_id, '_wp_page_template', true );
				if ( 'templates/minimalist-cart-page.php' === $meta_template ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if the current page is the Minimalist Order Received (Thank You) endpoint.
	 * Activated via 'force_thankyou_template' setting.
	 *
	 * @return bool
	 */
	public static function is_minimalist_thankyou() {
		if ( wp_doing_ajax() ) {
			return false;
		}

		if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'order-received' ) ) {
			return false;
		}

		$settings                = get_option( 'mcmchk_settings', array() );
		$force_thankyou_template = ! empty( $settings['force_thankyou_template'] );

		return $force_thankyou_template;
	}

	/**
	 * Check if any Minimalist experience (Checkout, Cart, or Order Received) is active.
	 *
	 * @return bool
	 */
	public static function is_minimalist_page() {
		return self::is_minimalist_checkout() || self::is_minimalist_cart() || self::is_minimalist_thankyou();
	}

	/**
	 * Override WooCommerce templates when on our custom Minimalist Checkout, Cart, or Order Received layout.
	 */
	public function override_woocommerce_template( $template, $template_name, $template_path ) {
		if ( self::is_minimalist_page() ) {
			$custom_template = MCMCHK_TEMPLATES . 'woocommerce/' . $template_name;
			if ( file_exists( $custom_template ) ) {
				return $custom_template;
			}
		}
		return $template;
	}

	/**
	 * Add custom classes to the body tag.
	 */
	public function add_body_classes( $classes ) {
		if ( self::is_minimalist_page() ) {
			// Remove conflicting Elementor page-template classes when Force Template overrides an Elementor template
			$classes = array_values( array_diff( (array) $classes, array(
				'elementor-template-full-width',
				'elementor-template-canvas',
				'elementor-page',
			) ) );

			$classes[] = 'mcrpd-shop-checkout-body';

			if ( self::is_minimalist_cart() ) {
				$classes[] = 'mcrpd-minimalist-cart-body';
			} elseif ( self::is_minimalist_thankyou() ) {
				$classes[] = 'mcrpd-minimalist-thankyou-body';
			}

			$settings     = get_option( 'mcmchk_settings', array() );
			$use_theme_hf = ! empty( $settings['use_theme_hf'] ) ? true : false;

			if ( ! $use_theme_hf ) {
				$classes[] = 'mcrpd-distraction-free';
			}

			if ( ! empty( $settings['disable_add_to_subscription'] ) ) {
				$classes[] = 'mcrpd-hide-add-to-subscription';
			}
		}
		return $classes;
	}

	/**
	 * Enqueue stylesheet and script files.
	 */
	public function enqueue_assets() {
		if ( self::is_minimalist_page() ) {
			// Enqueue Inter font from Google Fonts for shop-like modern feel
			wp_enqueue_style( 'mcrpd-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), '1.0.0' );
			
			// Enqueue local assets
			wp_enqueue_style( 'mcrpd-checkout-css', MCMCHK_ASSETS . 'css/mcrpd-checkout.css', array(), MCMCHK_VERSION );
			
			// Dynamic primary color CSS
			$settings = get_option( 'mcmchk_settings', array() );
			$primary_color = ! empty( $settings['primary_color'] ) ? sanitize_hex_color( $settings['primary_color'] ) : '#1773b0';
			if ( ! $primary_color ) {
				$primary_color = '#1773b0';
			}
			$custom_css = "
				:root,
				body.mcrpd-shop-checkout-body {
					--mcmchk-primary: {$primary_color};
				}
				.woocommerce-checkout input[type='text']:focus,
				.woocommerce-checkout input[type='email']:focus,
				.woocommerce-checkout input[type='tel']:focus,
				.woocommerce-checkout input[type='password']:focus,
				.woocommerce-checkout input[type='number']:focus,
				.woocommerce-checkout textarea:focus,
				.woocommerce-checkout select:focus,
				.woocommerce-checkout .mcrpd-input:focus,
				.woocommerce-checkout .mcrpd-input-text:focus,
				.woocommerce-checkout .mcrpd-input-number:focus,
				.woocommerce-checkout .mcrpd-input-select:focus,
				.woocommerce-checkout .mcrpd-input-textarea:focus,
				.mcrpd-coupon-input:focus,
				input.mcrpd-coupon-input:focus,
				.mcrpd-cart-wrapper input[type='text']:focus,
				.mcrpd-cart-wrapper input[type='number']:focus,
				.mcrpd-login-field input:focus,
				.woocommerce-checkout .select2-container--default.select2-container--focus .select2-selection--single,
				.woocommerce-checkout .select2-container--default.select2-container--open .select2-selection--single {
					border-color: {$primary_color} !important;
					box-shadow: 0 0 0 1px {$primary_color} !important;
				}
				.woocommerce-checkout #payment #place_order,
				.mcrpd-login-button,
				.mcrpd-coupon-button,
				.mcrpd-empty-cart-button,
				.mcrpd-cart-checkout-btn,
				.mcrpd-cart-cta-wrapper a.checkout-button,
				.mcrpd-thankyou-continue-btn,
				.mcrpd-thankyou-download-btn,
				.wcsatt-add-cart-to-subscription-wrapper .wcsatt-add-to-subscription-button {
					background-color: {$primary_color} !important;
					color: #ffffff !important;
				}
				.mcrpd-login-link,
				.mcrpd-forgot-password-link,
				.mcrpd-mobile-summary-toggle-label,
				.mcrpd-coupon-tag,
				.mcrpd-remove-coupon-btn,
				.mcrpd-cart-continue-link,
				.mcrpd-thankyou-icon-circle,
				.wcsatt-add-cart-to-subscription-wrapper table.my_account_subscriptions td.subscription-id a {
					color: {$primary_color} !important;
				}
				.mcrpd-thankyou-icon-circle {
					border-color: {$primary_color} !important;
				}
				.mcrpd-payment-methods-list li.wc_payment_method.payment_box_active {
					border-color: {$primary_color} !important;
				}
				ul.mcrpd-shipping-methods-list input[type='radio'],
				.mcrpd-shipping-radio-label input[type='radio'],
				.wcsatt-add-cart-to-subscription-wrapper .wcsatt-add-cart-to-subscription-action-input {
					accent-color: {$primary_color} !important;
				}
			";
			wp_add_inline_style( 'mcrpd-checkout-css', $custom_css );

			wp_enqueue_script( 'mcrpd-checkout-js', MCMCHK_ASSETS . 'js/mcrpd-checkout.js', array( 'jquery' ), MCMCHK_VERSION, true );

			// Pass settings to our JavaScript
			wp_localize_script( 'mcrpd-checkout-js', 'mcmchk_params', array(
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'wc_ajax_url'              => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'apply_coupon_nonce'       => wp_create_nonce( 'apply-coupon' ),
				'remove_coupon_nonce'      => wp_create_nonce( 'remove-coupon' ),
				'login_nonce'              => wp_create_nonce( 'mcrpd-login-nonce' ),
				'cart_nonce'               => wp_create_nonce( 'mcmchk-cart-nonce' ),
				'i18n_login_success'       => __( 'Login successful. Reloading...', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_login_empty'         => __( 'Please enter your username and password.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_coupon_empty'        => __( 'Please enter a discount code.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_coupon_applied'      => __( 'Coupon applied.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_coupon_error'        => __( 'Error applying coupon. Please try again.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_remove_coupon_error' => __( 'Could not remove the coupon.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_connection_error'    => __( 'Connection error. Please try again.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_show_order_summary'  => __( 'Show order summary', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_hide_order_summary'  => __( 'Hide order summary', 'mcod-minimalist-checkout-for-woocommerce' ),
			) );
		}
	}

	/**
	 * Decouple payment options from the standard review order hook when loading the template.
	 */
	public function handle_checkout_decoupling() {
		if ( self::is_minimalist_checkout() ) {
			// Remove default payment from review order
			remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );

			// Remove default coupon form from top of page
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

			// Remove default WooCommerce login form (we use our own inline AJAX login)
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );

			// Handle empty cart message
			remove_action( 'woocommerce_checkout_cart_empty', 'wc_empty_cart_message', 10 );
			add_action( 'woocommerce_checkout_cart_empty', array( $this, 'mcmchk_custom_empty_cart_message' ) );
		}
	}

	/**
	 * Display custom empty cart message with a button to go back to home.
	 */
	public function mcmchk_custom_empty_cart_message() {
		?>
		<div class="mcrpd-empty-cart-wrapper">
			<div class="mcrpd-empty-cart-box">
				<div class="mcrpd-empty-cart-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="9" cy="21" r="1"></circle>
						<circle cx="20" cy="21" r="1"></circle>
						<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
					</svg>
				</div>
				<h2 class="mcrpd-empty-cart-title"><?php esc_html_e( 'No products in the cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mcrpd-empty-cart-button"><?php esc_html_e( 'Back to home', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX login from the inline checkout login form.
	 */
	public function handle_ajax_login() {
		check_ajax_referer( 'mcrpd-login-nonce', 'security' );

		// Rate-limiting per IP address (max 5 failed attempts per 5 minutes)
		$remote_ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$transient_key = 'mcmchk_login_attempts_' . md5( $remote_ip );
		$attempts      = (int) get_transient( $transient_key );

		if ( $attempts >= 5 ) {
			wp_send_json_error(
				array(
					'message' => __( 'Too many failed login attempts. Please wait a few minutes and try again.', 'mcod-minimalist-checkout-for-woocommerce' ),
				)
			);
		}

		$username = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
		// Passwords must not be sanitized to preserve special characters.
		$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your username and password.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		}

		$credentials = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			set_transient( $transient_key, $attempts + 1, 5 * MINUTE_IN_SECONDS );
			wp_send_json_error(
				array(
					'message' => __( 'Invalid username/email or password. Please try again.', 'mcod-minimalist-checkout-for-woocommerce' ),
				)
			);
		}

		delete_transient( $transient_key );
		wp_send_json_success( array( 'message' => __( 'Login successful. Reloading...', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
	}

	/**
	 * Save newsletter opt-in state to the WooCommerce Order meta.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $data  Posted checkout data.
	 */
	public function save_newsletter_order_meta( $order, $data ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by WooCommerce checkout process.
		$opted_in = ! empty( $_POST['mcmchk_newsletter'] ) ? 'yes' : 'no';
		$order->update_meta_data( '_mcmchk_newsletter_optin', $opted_in );

		if ( 'yes' === $opted_in ) {
			$email = $order->get_billing_email();
			do_action( 'mcmchk_newsletter_opted_in', $order->get_id(), $email, $order );
		}
	}

	/**
	 * Display newsletter opt-in status in the WooCommerce Admin Order screen.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_newsletter_order_meta_in_admin( $order ) {
		$opted_in = $order->get_meta( '_mcmchk_newsletter_optin' );
		if ( 'yes' === $opted_in ) {
			echo '<p><strong>' . esc_html__( 'Marketing Newsletter:', 'mcod-minimalist-checkout-for-woocommerce' ) . '</strong> ' . esc_html__( 'Subscribed', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
		}
	}

	/**
	 * Normalize a state/department code against the registered WooCommerce states for a given country.
	 * Resolves mismatches between Stripe Express Checkout / Link ISO codes (e.g. "AMA") and
	 * country-prefixed state plugins (e.g. "CO-AMA"), or vice-versa.
	 *
	 * @param string $state   Raw state code or name.
	 * @param string $country Two-letter ISO country code (e.g. "CO").
	 * @return string Normalized state code.
	 */
	public function normalize_state_code_for_country( $state, $country ) {
		if ( empty( $state ) || empty( $country ) || ! function_exists( 'WC' ) || ! isset( WC()->countries ) ) {
			return $state;
		}

		$country = strtoupper( trim( (string) $country ) );
		$state   = trim( (string) $state );
		$states  = WC()->countries->get_states( $country );

		if ( empty( $states ) || ! is_array( $states ) ) {
			return $state;
		}

		// 1. Exact match
		if ( isset( $states[ $state ] ) ) {
			return $state;
		}

		$upper_state = strtoupper( $state );
		if ( isset( $states[ $upper_state ] ) ) {
			return $upper_state;
		}

		// 2. Stripe Link / Wallet sends unprefixed ISO subdivision (e.g. "AMA"), while site uses "CO-AMA"
		$prefixed = $country . '-' . $upper_state;
		if ( isset( $states[ $prefixed ] ) ) {
			return $prefixed;
		}

		// 3. Input has "CO-AMA" prefix, while site uses unprefixed "AMA"
		$prefix_str = $country . '-';
		if ( 0 === strpos( $upper_state, $prefix_str ) ) {
			$unprefixed = substr( $upper_state, strlen( $prefix_str ) );
			if ( isset( $states[ $unprefixed ] ) ) {
				return $unprefixed;
			}
		}

		return $state;
	}

	/**
	 * Check whether opt-in state code normalization for Stripe Link / Express Checkout is enabled.
	 *
	 * @return bool
	 */
	public function is_state_normalization_enabled() {
		$settings = get_option( 'mcmchk_settings', array() );
		return ! empty( $settings['normalize_state_iso_codes'] ) && '1' === (string) $settings['normalize_state_iso_codes'];
	}

	/**
	 * Normalize billing_address.state and shipping_address.state on WooCommerce Store API requests
	 * before schema validation runs (fixes Stripe Link / Apple Pay / Google Pay state validation errors).
	 * Only active when explicitly enabled in plugin settings.
	 *
	 * @param mixed           $result  Response to replace the requested version with.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request used to generate the response.
	 * @return mixed
	 */
	public function normalize_store_api_address_states( $result, $server, $request ) {
		if ( ! ( $request instanceof WP_REST_Request ) || ! $this->is_state_normalization_enabled() ) {
			return $result;
		}

		$route = $request->get_route();
		if ( false === strpos( $route, '/wc/store/' ) ) {
			return $result;
		}

		// Ensure existing WC()->customer session states are also normalized
		if ( function_exists( 'WC' ) && isset( WC()->customer ) && is_object( WC()->customer ) ) {
			$cust_b_country = WC()->customer->get_billing_country();
			$cust_b_state   = WC()->customer->get_billing_state();
			if ( $cust_b_country && $cust_b_state ) {
				$norm_b = $this->normalize_state_code_for_country( $cust_b_state, $cust_b_country );
				if ( $norm_b !== $cust_b_state ) {
					WC()->customer->set_billing_state( $norm_b );
				}
			}

			$cust_s_country = WC()->customer->get_shipping_country();
			$cust_s_state   = WC()->customer->get_shipping_state();
			if ( $cust_s_country && $cust_s_state ) {
				$norm_s = $this->normalize_state_code_for_country( $cust_s_state, $cust_s_country );
				if ( $norm_s !== $cust_s_state ) {
					WC()->customer->set_shipping_state( $norm_s );
				}
			}
		}

		$address_keys = array( 'billing_address', 'shipping_address' );
		foreach ( $address_keys as $addr_key ) {
			$address = $request->get_param( $addr_key );
			if ( is_array( $address ) && ! empty( $address['state'] ) ) {
				$country = ! empty( $address['country'] )
					? $address['country']
					: ( function_exists( 'WC' ) && isset( WC()->customer ) ? WC()->customer->get_billing_country() : '' );

				$normalized_state = $this->normalize_state_code_for_country( $address['state'], $country );
				if ( $normalized_state !== $address['state'] ) {
					$address['state'] = $normalized_state;
					$request->set_param( $addr_key, $address );
				}
			}
		}

		// Also support Store API /wc/store/v1/batch sub-requests
		$batch_requests = $request->get_param( 'requests' );
		if ( is_array( $batch_requests ) ) {
			$modified = false;
			foreach ( $batch_requests as $idx => $sub_req ) {
				if ( isset( $sub_req['body'] ) && is_array( $sub_req['body'] ) ) {
					foreach ( $address_keys as $addr_key ) {
						if ( ! empty( $sub_req['body'][ $addr_key ]['state'] ) ) {
							$country = ! empty( $sub_req['body'][ $addr_key ]['country'] )
								? $sub_req['body'][ $addr_key ]['country']
								: ( function_exists( 'WC' ) && isset( WC()->customer ) ? WC()->customer->get_billing_country() : '' );

							$norm = $this->normalize_state_code_for_country( $sub_req['body'][ $addr_key ]['state'], $country );
							if ( $norm !== $sub_req['body'][ $addr_key ]['state'] ) {
								$batch_requests[ $idx ]['body'][ $addr_key ]['state'] = $norm;
								$modified = true;
							}
						}
					}
				}
			}
			if ( $modified ) {
				$request->set_param( 'requests', $batch_requests );
			}
		}

		return $result;
	}

	/**
	 * Normalize billing_state and shipping_state during standard WooCommerce checkout submission.
	 * Only active when explicitly enabled in plugin settings.
	 *
	 * @param array $data Posted checkout data.
	 * @return array
	 */
	public function normalize_posted_checkout_states( $data ) {
		if ( ! is_array( $data ) || ! $this->is_state_normalization_enabled() ) {
			return $data;
		}

		if ( ! empty( $data['billing_state'] ) && ! empty( $data['billing_country'] ) ) {
			$data['billing_state'] = $this->normalize_state_code_for_country( $data['billing_state'], $data['billing_country'] );
		}

		if ( ! empty( $data['shipping_state'] ) && ! empty( $data['shipping_country'] ) ) {
			$data['shipping_state'] = $this->normalize_state_code_for_country( $data['shipping_state'], $data['shipping_country'] );
		}

		return $data;
	}

	/**
	 * Add decoupled blocks back to the WooCommerce AJAX response so both columns refresh automatically.
	 */
	public function add_checkout_fragments( $fragments ) {
		if ( self::is_minimalist_checkout() ) {
			// 1. Payment Methods Fragment
			ob_start();
			woocommerce_checkout_payment();
			$fragments['#payment'] = ob_get_clean();

			// 2. Shipping Methods Fragment
			ob_start();
			$this->render_shipping_methods();
			$fragments['#mcrpd-checkout-shipping-methods'] = ob_get_clean();
		}
		return $fragments;
	}

	/**
	 * Render WooCommerce shipping methods in our custom left column.
	 *
	 * We intentionally avoid calling cart-shipping.php because it outputs
	 * table elements (<tr>, <th>, <td>) that are invalid inside a <div>.
	 * Browsers auto-correct the DOM and the layout breaks. Instead, we
	 * render the exact same data using clean semantic HTML (ul/li).
	 */
	public function render_shipping_methods() {
		if ( ! WC()->cart->needs_shipping() || ! WC()->cart->show_shipping() ) {
			echo '<p class="mcrpd-no-shipping-required">' . esc_html__( 'No shipping required.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
			return;
		}

		$packages       = WC()->shipping()->get_packages();
		$total_packages = count( $packages );

		foreach ( $packages as $i => $package ) {
			$available_methods = $package['rates'];
			$chosen_method     = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			$package_name      = apply_filters(
				'mcmchk_shipping_package_name',
				( $i + 1 > 1 )
					// translators: %d represents the shipping package number.
					? sprintf( _x( 'Shipping %d', 'shipping packages', 'mcod-minimalist-checkout-for-woocommerce' ), ( $i + 1 ) )
					: _x( 'Shipping', 'shipping packages', 'mcod-minimalist-checkout-for-woocommerce' ),
				$i,
				$package
			);

			if ( $total_packages > 1 && ! empty( $package_name ) ) {
				echo '<p class="mcrpd-shipping-package-name">' . esc_html( $package_name ) . '</p>';
			}

			if ( ! empty( $available_methods ) ) {
				$list_id = ( 0 === (int) $i ) ? 'shipping_method' : 'shipping_method_' . absint( $i );
				echo '<ul id="' . esc_attr( $list_id ) . '" class="woocommerce-shipping-methods mcrpd-shipping-methods-list">';

				foreach ( $available_methods as $method ) {
					$method_id   = $method->id;
					$method_name = sanitize_title( $method->id );
					$input_id    = 'shipping_method_' . absint( $i ) . '_' . $method_name;
					$is_checked  = ( $chosen_method === $method->id );
					$label_text  = wc_cart_totals_shipping_method_label( $method );

					printf(
						'<li class="%s">',
						esc_attr( $is_checked ? 'mcrpd-shipping-card selected active' : 'mcrpd-shipping-card' )
					);

					if ( 1 < count( $available_methods ) ) {
						printf(
							'<input type="radio" name="shipping_method[%1$d]" data-index="%1$d" id="%2$s" value="%3$s" class="shipping_method" %4$s />',
							absint( $i ),
							esc_attr( $input_id ),
							esc_attr( $method_id ),
							checked( $is_checked, true, false )
						);
					} else {
						printf(
							'<input type="hidden" name="shipping_method[%1$d]" data-index="%1$d" id="%2$s" value="%3$s" class="shipping_method" />',
							absint( $i ),
							esc_attr( $input_id ),
							esc_attr( $method_id )
						);
					}

					printf(
						'<label for="%s">%s</label>',
						esc_attr( $input_id ),
						wp_kses_post( $label_text ) // Contains <span> price markup
					);

					// Allow third-party plugins (e.g. pickup points) to hook after each shipping rate.
					do_action( 'mcmchk_after_shipping_rate', $method, $i );

					echo '</li>';
				}

				echo '</ul>';
			} else {
				// No methods available for this package.
				echo '<p class="mcrpd-no-shipping-available">' . wp_kses_post( apply_filters( 'mcmchk_no_shipping_available_html', __( 'No shipping options available. Please check your address or contact us.', 'mcod-minimalist-checkout-for-woocommerce' ) ) ) . '</p>';
			}
		}
	}

	/**
	 * Resolve a valid country code for a checkout section ('billing' or 'shipping')
	 * against WooCommerce's allowed/shipping countries list.
	 *
	 * If a logged-in user's saved country (or a country set in session by Stripe Link / Apple Pay)
	 * is outside the store's allowed countries, WooCommerce's WC_Countries::get_address_fields()
	 * returns an empty array, causing all billing/shipping fields to disappear.
	 *
	 * @param string $country Current country code.
	 * @param string $section Section type ('billing' or 'shipping').
	 * @return string Valid two-letter ISO country code.
	 */
	public function mcmchk_get_valid_country_for_section( $country, $section = 'billing' ) {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->countries ) || ! is_object( WC()->countries ) ) {
			return (string) $country;
		}

		$country = strtoupper( trim( (string) $country ) );
		$allowed = ( 'shipping' === $section )
			? WC()->countries->get_shipping_countries()
			: WC()->countries->get_allowed_countries();

		if ( empty( $allowed ) || ! is_array( $allowed ) ) {
			return ! empty( $country ) ? $country : WC()->countries->get_base_country();
		}

		if ( ! empty( $country ) && array_key_exists( $country, $allowed ) ) {
			return $country;
		}

		$base_country = WC()->countries->get_base_country();
		if ( ! empty( $base_country ) && array_key_exists( $base_country, $allowed ) ) {
			return $base_country;
		}

		reset( $allowed );
		return (string) key( $allowed );
	}

	/**
	 * Ensure WC()->customer billing and shipping countries are valid within the store's allowed countries.
	 * Self-heals sessions and logged-in user profiles where Stripe Link, Apple Pay, or legacy user_meta
	 * stored a country code outside woocommerce_allowed_countries.
	 */
	public function mcmchk_ensure_valid_customer_countries() {
		if ( ! function_exists( 'WC' ) || ! isset( WC()->customer ) || ! is_object( WC()->customer ) ) {
			return;
		}

		$current_billing = WC()->customer->get_billing_country();
		$valid_billing   = $this->mcmchk_get_valid_country_for_section( $current_billing, 'billing' );
		if ( ! empty( $valid_billing ) && $valid_billing !== $current_billing ) {
			WC()->customer->set_billing_country( $valid_billing );
		}

		$current_shipping = WC()->customer->get_shipping_country();
		$valid_shipping   = $this->mcmchk_get_valid_country_for_section( $current_shipping, 'shipping' );
		if ( ! empty( $valid_shipping ) && $valid_shipping !== $current_shipping ) {
			WC()->customer->set_shipping_country( $valid_shipping );
		}
	}

	/**
	 * Filter checkout field values for billing_country and shipping_country so WC_Checkout::get_value()
	 * never returns a country outside the store's allowed countries.
	 *
	 * @param mixed  $value Current value (or null).
	 * @param string $input Field key.
	 * @return mixed
	 */
	public function mcmchk_filter_checkout_country_value( $value, $input ) {
		if ( 'billing_country' !== $input && 'shipping_country' !== $input ) {
			return $value;
		}

		if ( ! function_exists( 'WC' ) || ! isset( WC()->countries ) || ! is_object( WC()->countries ) ) {
			return $value;
		}

		$section = ( 'shipping_country' === $input ) ? 'shipping' : 'billing';

		// Check explicit value if already filtered, otherwise check WC()->customer value
		$candidate = ! empty( $value ) ? (string) $value : '';
		if ( empty( $candidate ) && isset( WC()->customer ) && is_object( WC()->customer ) ) {
			$candidate = ( 'shipping' === $section )
				? (string) WC()->customer->get_shipping_country()
				: (string) WC()->customer->get_billing_country();
		}

		if ( ! empty( $candidate ) ) {
			$valid = $this->mcmchk_get_valid_country_for_section( $candidate, $section );
			if ( $valid !== $candidate ) {
				return $valid;
			}
		}

		return $value;
	}

	/**
	 * Clean, standardize, and format WooCommerce checkout fields.
	 */
	public function customize_checkout_fields( $fields ) {
		if ( ! self::is_minimalist_checkout() ) {
			return $fields;
		}

		// Defensive self-healing: if WC_Countries::get_address_fields() returned an empty address set
		// (e.g. because a logged-in user's billing_country or Express Checkout wallet country was not
		// in woocommerce_allowed_countries, or a 3rd-party filter cleared them), restore the base fields
		// before applying Minimalist Checkout field overrides.
		if ( function_exists( 'WC' ) && isset( WC()->countries ) && is_object( WC()->countries ) ) {
			if ( ! isset( $fields['billing'] ) || ! is_array( $fields['billing'] ) ) {
				$fields['billing'] = array();
			}
			if ( ! isset( $fields['billing']['billing_first_name'] ) || ! isset( $fields['billing']['billing_address_1'] ) ) {
				$valid_b_country  = $this->mcmchk_get_valid_country_for_section(
					( isset( WC()->customer ) && is_object( WC()->customer ) ) ? WC()->customer->get_billing_country() : '',
					'billing'
				);
				$default_b_fields = WC()->countries->get_address_fields( $valid_b_country, 'billing_' );
				if ( is_array( $default_b_fields ) && ! empty( $default_b_fields ) ) {
					$fields['billing'] = array_merge( $default_b_fields, $fields['billing'] );
				}
			}

			if ( isset( $fields['shipping'] ) && is_array( $fields['shipping'] ) ) {
				if ( ! isset( $fields['shipping']['shipping_first_name'] ) || ! isset( $fields['shipping']['shipping_address_1'] ) ) {
					$valid_s_country  = $this->mcmchk_get_valid_country_for_section(
						( isset( WC()->customer ) && is_object( WC()->customer ) ) ? WC()->customer->get_shipping_country() : '',
						'shipping'
					);
					$default_s_fields = WC()->countries->get_address_fields( $valid_s_country, 'shipping_' );
					if ( is_array( $default_s_fields ) && ! empty( $default_s_fields ) ) {
						$fields['shipping'] = array_merge( $default_s_fields, $fields['shipping'] );
					}
				}
			}
		}

		$settings        = get_option( 'mcmchk_settings', array() );
		$field_overrides = isset( $settings['field_overrides'] ) ? $settings['field_overrides'] : array();

		// Default placeholder fallbacks
		$default_placeholders = array(
			'billing' => array(
				'billing_first_name' => __( 'First name', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_last_name'  => __( 'Last name', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_company'    => __( 'Company (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_address_1'  => __( 'Street address', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_address_2'  => __( 'Apartment, suite, unit, etc. (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_city'       => __( 'Town / City', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_postcode'   => __( 'Postcode / ZIP (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_phone'      => __( 'Phone', 'mcod-minimalist-checkout-for-woocommerce' ),
				'billing_email'      => __( 'Email address', 'mcod-minimalist-checkout-for-woocommerce' ),
			),
			'shipping' => array(
				'shipping_first_name' => __( 'First name', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_last_name'  => __( 'Last name', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_company'    => __( 'Company (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_address_1'  => __( 'Street address', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_address_2'  => __( 'Apartment, suite, unit, etc. (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_city'       => __( 'Town / City', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_postcode'   => __( 'Postcode / ZIP (optional)', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_phone'      => __( 'Phone', 'mcod-minimalist-checkout-for-woocommerce' ),
				'shipping_email'      => __( 'Email address', 'mcod-minimalist-checkout-for-woocommerce' ),
			),
		);

		$sections = array( 'billing', 'shipping', 'order' );

		foreach ( $sections as $section ) {
			if ( ! isset( $fields[ $section ] ) || ! is_array( $fields[ $section ] ) ) {
				continue;
			}

			$overrides = isset( $field_overrides[ $section ] ) ? $field_overrides[ $section ] : array();

			if ( ! empty( $overrides ) ) {
				$new_fields = array();

				foreach ( $overrides as $key => $props ) {
					if ( ! isset( $fields[ $section ][ $key ] ) ) {
						continue;
					}

					// If disabled in settings, remove it
					if ( empty( $props['enabled'] ) ) {
						unset( $fields[ $section ][ $key ] );
						continue;
					}

					// Apply custom labels and placeholders
					if ( ! empty( $props['label'] ) ) {
						$fields[ $section ][ $key ]['label'] = $props['label'];
					}
					if ( ! empty( $props['placeholder'] ) ) {
						$fields[ $section ][ $key ]['placeholder'] = $props['placeholder'];
					} else {
						// Apply default fallback if original is empty
						if ( empty( $fields[ $section ][ $key ]['placeholder'] ) && isset( $default_placeholders[ $section ][ $key ] ) ) {
							$fields[ $section ][ $key ]['placeholder'] = is_array( $default_placeholders[ $section ][ $key ] )
								? $default_placeholders[ $section ][ $key ]['placeholder']
								: $default_placeholders[ $section ][ $key ];
						}
					}

					// Set priority
					if ( isset( $props['priority'] ) ) {
						$fields[ $section ][ $key ]['priority'] = (int) $props['priority'];
					}

					// Apply width class
					if ( isset( $props['width'] ) ) {
						$classes = isset( $fields[ $section ][ $key ]['class'] ) ? $fields[ $section ][ $key ]['class'] : array();
						if ( ! is_array( $classes ) ) {
							$classes = array( $classes );
						}
						// Remove any existing width classes
						$classes = array_diff( $classes, array( 'form-row-first', 'form-row-last', 'form-row-wide', 'form-row-full' ) );
						// Add the new width class
						$classes[] = $props['width'];
						$fields[ $section ][ $key ]['class'] = array_values( $classes );
					}

					$new_fields[ $key ] = $fields[ $section ][ $key ];
				}

				// Keep any fields that are not in overrides (like fields added by third-party plugins)
				foreach ( $fields[ $section ] as $key => $field ) {
					if ( ! isset( $new_fields[ $key ] ) && ! isset( $overrides[ $key ] ) ) {
						$new_fields[ $key ] = $field;
					}
				}

				// Sort fields within section by priority
				uasort( $new_fields, function( $a, $b ) {
					$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
					$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
					return $a_priority <=> $b_priority;
				} );

				$fields[ $section ] = $new_fields;
			} else {
				// No overrides, apply fallbacks
				if ( isset( $default_placeholders[ $section ] ) ) {
					foreach ( $default_placeholders[ $section ] as $key => $val ) {
						if ( isset( $fields[ $section ][ $key ] ) && empty( $fields[ $section ][ $key ]['placeholder'] ) ) {
							$fields[ $section ][ $key ]['placeholder'] = is_array( $val ) ? $val['placeholder'] : $val;
						}
					}
				}
			}
		}

		return $fields;
	}

	/**
	 * Set up WooCommerce Subscriptions recurring totals interception for both Checkout and Cart.
	 *
	 * Called on 'wp' hook (priority 20), 'woocommerce_checkout_update_order_review' (priority 5),
	 * and inside handle_ajax_update_cart_item(). Removes WCS's default output and replaces
	 * it with our custom template renderer when a template is saved.
	 */
	public function mcmchk_setup_subscription_hooks() {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		$settings = get_option( 'mcmchk_settings', array() );

		// Optionally disable WooCommerce Subscriptions' "Add cart to an existing subscription" block
		if ( ! empty( $settings['disable_add_to_subscription'] ) && self::is_minimalist_page() ) {
			add_filter( 'wcsatt_cart_to_subscription_options_enabled', '__return_false', 999 );
			add_filter( 'woocommerce_subscriptions_adding_cart_to_subscription_supported', '__return_false', 999 );
			remove_action( 'woocommerce_checkout_order_review', array( 'WCS_ATT_Display_Cart', 'show_cart_to_subscription_options' ), 100 );
			remove_action( 'woocommerce_after_cart_totals', array( 'WCS_ATT_Display_Cart', 'show_cart_to_subscription_options' ), 100 );
		}

		$template = isset( $settings['subscription_recurring_template'] ) ? trim( $settings['subscription_recurring_template'] ) : '';

		if ( empty( $template ) ) {
			return; // Nothing to do: keep WCS default output.
		}

		// Remove WooCommerce Subscriptions' default recurring totals renderer on Checkout and Cart.
		remove_action( 'woocommerce_review_order_after_order_total', array( 'WC_Subscriptions_Cart', 'display_recurring_totals' ), 10 );
		remove_action( 'woocommerce_cart_totals_after_order_total', array( 'WC_Subscriptions_Cart', 'display_recurring_totals' ), 10 );

		// Add our custom renderer at the same priority on both Checkout and Cart.
		add_action( 'woocommerce_review_order_after_order_total', array( $this, 'mcmchk_display_custom_recurring_totals' ), 10 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'mcmchk_display_custom_recurring_totals' ), 10 );
	}

	/**
	 * Render a template string with {{variables}} and {{current_date | X}} placeholders resolved.
	 *
	 * @param string $template Raw HTML template from settings.
	 * @param array  $vars     Associative array of variable_name => resolved_value.
	 */
	private function mcmchk_render_resolved_subscription_template( $template, $vars ) {
		$keys   = array_map(
			function( $k ) { return '{{' . $k . '}}'; },
			array_keys( $vars )
		);
		$values = array_values( $vars );

		$output = str_replace( $keys, $values, $template );

		// Dynamic date variable: {{current_date | X}}
		$output = preg_replace_callback( '/\{\{\s*current_date\s*\|\s*(\d+)\s*\}\}/i', function( $matches ) {
			$days = (int) $matches[1];
			return esc_html( date_i18n( wc_date_format(), strtotime( "+{$days} days" ) ) );
		}, $output );

		// Output the resolved template (wp_kses_post was already applied on save).
		echo '<div class="mcrpd-recurring-template-wrap">' . wp_kses_post( $output ) . '</div>';
	}

	/**
	 * Render the custom recurring totals block with variables resolved (Cart & Checkout).
	 *
	 * Only called when a template is saved (set up by mcmchk_setup_subscription_hooks).
	 * If the cart has no subscriptions the block is simply not rendered.
	 */
	public function mcmchk_display_custom_recurring_totals() {
		if ( ! isset( WC()->cart ) ) {
			return;
		}

		// Ensure recurring_carts are calculated if the cart contains a subscription
		if ( empty( WC()->cart->recurring_carts ) && class_exists( 'WC_Subscriptions_Cart' ) && method_exists( 'WC_Subscriptions_Cart', 'cart_contains_subscription' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			WC()->cart->calculate_totals();
		}

		// Guard: only render when there are recurring carts.
		if ( empty( WC()->cart->recurring_carts ) ) {
			return;
		}

		$settings = get_option( 'mcmchk_settings', array() );
		$template = isset( $settings['subscription_recurring_template'] ) ? trim( $settings['subscription_recurring_template'] ) : '';

		if ( empty( $template ) ) {
			return;
		}

		$vars = $this->mcmchk_get_recurring_cart_vars();
		$this->mcmchk_render_resolved_subscription_template( $template, $vars );
	}

	/**
	 * Render the Recurring Payment Block on the Minimalist Order Received (Thank You) sidebar.
	 *
	 * @param WC_Order $order The order object.
	 */
	public function mcmchk_display_order_recurring_totals( $order ) {
		if ( ! class_exists( 'WC_Subscriptions' ) || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$subscriptions = function_exists( 'wcs_get_subscriptions_for_order' )
			? wcs_get_subscriptions_for_order( $order->get_id(), array( 'order_type' => 'any' ) )
			: array();

		$has_subscription_item = false;
		if ( empty( $subscriptions ) ) {
			foreach ( $order->get_items() as $item ) {
				$product = $item->get_product();
				if ( $product && class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
					$has_subscription_item = true;
					break;
				}
			}
		}

		if ( empty( $subscriptions ) && ! $has_subscription_item ) {
			return;
		}

		$settings = get_option( 'mcmchk_settings', array() );
		$template = isset( $settings['subscription_recurring_template'] ) ? trim( $settings['subscription_recurring_template'] ) : '';

		if ( ! empty( $template ) ) {
			$vars = $this->mcmchk_get_recurring_order_vars( $order, $subscriptions );
			$this->mcmchk_render_resolved_subscription_template( $template, $vars );
		} elseif ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				?>
				<div class="mcrpd-totals-row mcrpd-recurring-total-row">
					<span class="mcrpd-totals-label"><?php esc_html_e( 'Recurring total', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
					<span class="mcrpd-totals-value"><?php echo wp_kses_post( $subscription->get_formatted_order_total() ); ?></span>
				</div>
				<?php
				break;
			}
		}
	}

	/**
	 * Build a map of {{variable}} => resolved value from an Order and its WC_Subscription objects.
	 *
	 * @param WC_Order $order         The order object.
	 * @param array    $subscriptions Array of WC_Subscription objects for this order.
	 * @return array Associative array of variable name => resolved string.
	 */
	private function mcmchk_get_recurring_order_vars( $order, $subscriptions = array() ) {
		$vars = array(
			'recurring_subtotal' => '',
			'recurring_total'    => '',
			'period'             => '',
			'interval'           => '',
			'period_name'        => '',
			'first_renewal_date' => '',
			'sign_up_fee'        => '',
			'trial_period'       => '',
		);

		if ( ! empty( $subscriptions ) ) {
			foreach ( $subscriptions as $subscription ) {
				$currency                   = $subscription->get_currency();
				$vars['recurring_subtotal'] = wp_kses_post( wc_price( $subscription->get_subtotal(), array( 'currency' => $currency ) ) );
				$vars['recurring_total']    = wp_kses_post( wc_price( $subscription->get_total(), array( 'currency' => $currency ) ) );

				$interval   = max( 1, (int) $subscription->get_billing_interval() );
				$period_key = $subscription->get_billing_period();

				if ( ! empty( $period_key ) ) {
					if ( function_exists( 'wcs_get_subscription_period_strings' ) ) {
						$period_label = wcs_get_subscription_period_strings( $interval, $period_key );
					} elseif ( class_exists( 'WC_Subscriptions_Product' ) && method_exists( 'WC_Subscriptions_Product', 'get_period_strings' ) ) {
						$period_strings = WC_Subscriptions_Product::get_period_strings( $interval, $period_key );
						$period_label   = is_array( $period_strings ) ? ( 1 === $interval ? $period_strings['singular'] : $period_strings['plural'] ) : $period_strings;
					} else {
						$period_label = $interval . ' ' . $period_key;
					}

					$vars['interval']    = esc_html( (string) $interval );
					$vars['period_name'] = esc_html( sanitize_key( $period_key ) );
					if ( is_string( $period_label ) && '' !== $period_label ) {
						$vars['period'] = esc_html( $period_label );
					} else {
						/* translators: 1: billing interval number, 2: billing period (month, year, etc.) */
						$vars['period'] = esc_html( sprintf( _x( '%1$d %2$s', 'billing period', 'mcod-minimalist-checkout-for-woocommerce' ), $interval, $period_key ) );
					}
				}

				$next_payment_ts = method_exists( $subscription, 'get_time' ) ? (int) $subscription->get_time( 'next_payment' ) : 0;
				if ( $next_payment_ts > 0 ) {
					$vars['first_renewal_date'] = esc_html( date_i18n( wc_date_format(), $next_payment_ts ) );
				}

				$sign_up_fee         = method_exists( $subscription, 'get_sign_up_fee' ) ? (float) $subscription->get_sign_up_fee() : 0.0;
				$vars['sign_up_fee'] = wp_kses_post( wc_price( $sign_up_fee, array( 'currency' => $currency ) ) );

				foreach ( $subscription->get_items() as $item ) {
					$product = $item->get_product();
					if ( ! $product ) {
						continue;
					}
					$trial_length     = method_exists( $product, 'get_meta' ) ? (int) $product->get_meta( '_subscription_trial_length' ) : (int) get_post_meta( $product->get_id(), '_subscription_trial_length', true );
					$trial_period_key = sanitize_key( method_exists( $product, 'get_meta' ) ? (string) $product->get_meta( '_subscription_trial_period' ) : (string) get_post_meta( $product->get_id(), '_subscription_trial_period', true ) );
					if ( $trial_length > 0 && ! empty( $trial_period_key ) ) {
						/* translators: 1: trial length number, 2: trial period (day, week, month, year) */
						$vars['trial_period'] = esc_html( sprintf( _x( '%1$d %2$s', 'trial period', 'mcod-minimalist-checkout-for-woocommerce' ), $trial_length, $trial_period_key ) );
					}
					break;
				}

				break;
			}

			return $vars;
		}

		// Fallback: resolve from the order's subscription product line item if WC_Subscription object is not yet available
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			$period_key = method_exists( $product, 'get_meta' ) ? $product->get_meta( '_subscription_period' ) : get_post_meta( $product->get_id(), '_subscription_period', true );
			if ( empty( $period_key ) ) {
				continue;
			}

			$currency                   = $order->get_currency();
			$vars['recurring_subtotal'] = wp_kses_post( wc_price( $item->get_subtotal(), array( 'currency' => $currency ) ) );
			$vars['recurring_total']    = wp_kses_post( wc_price( $item->get_total() + $item->get_total_tax(), array( 'currency' => $currency ) ) );

			$interval = method_exists( $product, 'get_meta' ) ? (int) $product->get_meta( '_subscription_period_interval' ) : (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );
			$interval = max( 1, $interval );

			if ( function_exists( 'wcs_get_subscription_period_strings' ) ) {
				$period_label = wcs_get_subscription_period_strings( $interval, $period_key );
			} else {
				$period_label = $interval . ' ' . $period_key;
			}

			$vars['interval']    = esc_html( (string) $interval );
			$vars['period_name'] = esc_html( sanitize_key( $period_key ) );
			$vars['period']      = esc_html( (string) $period_label );

			$sign_up_fee         = method_exists( $product, 'get_meta' ) ? (float) $product->get_meta( '_subscription_sign_up_fee' ) : (float) get_post_meta( $product->get_id(), '_subscription_sign_up_fee', true );
			$vars['sign_up_fee'] = wp_kses_post( wc_price( $sign_up_fee, array( 'currency' => $currency ) ) );

			$trial_length     = method_exists( $product, 'get_meta' ) ? (int) $product->get_meta( '_subscription_trial_length' ) : (int) get_post_meta( $product->get_id(), '_subscription_trial_length', true );
			$trial_period_key = sanitize_key( method_exists( $product, 'get_meta' ) ? (string) $product->get_meta( '_subscription_trial_period' ) : (string) get_post_meta( $product->get_id(), '_subscription_trial_period', true ) );
			if ( $trial_length > 0 && ! empty( $trial_period_key ) ) {
				/* translators: 1: trial length number, 2: trial period (day, week, month, year) */
				$vars['trial_period'] = esc_html( sprintf( _x( '%1$d %2$s', 'trial period', 'mcod-minimalist-checkout-for-woocommerce' ), $trial_length, $trial_period_key ) );
			}
			break;
		}

		return $vars;
	}

	/**
	 * Build a map of {{variable}} => resolved value from the recurring cart.
	 *
	 * Iterates over all recurring carts provided by WooCommerce Subscriptions
	 * and pulls billing/period data from the first one found.
	 *
	 * @return array Associative array of variable name => resolved string.
	 */
	private function mcmchk_get_recurring_cart_vars() {
		$vars = array(
			'recurring_subtotal' => '',
			'recurring_total'    => '',
			'period'             => '',
			'interval'           => '',
			'period_name'        => '',
			'first_renewal_date' => '',
			'sign_up_fee'        => '',
			'trial_period'       => '',
		);

		// WooCommerce Subscriptions exposes recurring carts via WC()->cart->recurring_carts
		if ( ! isset( WC()->cart ) || ! isset( WC()->cart->recurring_carts ) ) {
			return $vars;
		}

		foreach ( WC()->cart->recurring_carts as $recurring_cart ) {
			// Formatted subtotal and total
			$vars['recurring_subtotal'] = wp_kses_post( wc_price( $recurring_cart->get_subtotal() ) );
			$vars['recurring_total']    = wp_kses_post( wc_price( $recurring_cart->get_total( 'edit' ) ) );

			// Pull subscription period data from the first subscription product in this recurring cart
			foreach ( $recurring_cart->get_cart() as $cart_item ) {
				$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

				if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
					continue;
				}

				// Resolve interval and period (works on subscription products)
				if ( method_exists( $product, 'get_meta' ) ) {
					$interval    = (int) $product->get_meta( '_subscription_period_interval' );
					$period_key  = $product->get_meta( '_subscription_period' );
				} else {
					$interval   = 0;
					$period_key = '';
				}

				// Fallback: read from post meta directly
				if ( empty( $interval ) && $product->get_id() ) {
					$interval   = (int) get_post_meta( $product->get_id(), '_subscription_period_interval', true );
					$period_key = get_post_meta( $product->get_id(), '_subscription_period', true );
				}

				if ( empty( $period_key ) ) {
					continue;
				}

				$interval = max( 1, $interval );

				// WC_Subscriptions_Product::get_period_strings() returns singular/plural labels
				if ( function_exists( 'wcs_get_subscription_period_strings' ) ) {
					$period_strings = wcs_get_subscription_period_strings( $interval, $period_key );
					// Returns a formatted string like "1 month" or "3 months"
					$period_label = $period_strings;
				} elseif ( class_exists( 'WC_Subscriptions_Product' ) && method_exists( 'WC_Subscriptions_Product', 'get_period_strings' ) ) {
					$period_strings = WC_Subscriptions_Product::get_period_strings( $interval, $period_key );
					$period_label   = is_array( $period_strings ) ? ( 1 === $interval ? $period_strings['singular'] : $period_strings['plural'] ) : $period_strings;
				} else {
					$period_label = $interval . ' ' . $period_key;
				}

				$vars['interval']    = esc_html( (string) $interval );
				$vars['period_name'] = esc_html( sanitize_key( $period_key ) );

				if ( is_string( $period_label ) && '' !== $period_label ) {
					$vars['period'] = esc_html( $period_label );
				} else {
					/* translators: 1: billing interval number, 2: billing period (month, year, etc.) */
					$vars['period'] = esc_html( sprintf( _x( '%1$d %2$s', 'billing period', 'mcod-minimalist-checkout-for-woocommerce' ), $interval, $period_key ) );
				}

				// Sign-up fee
				$sign_up_fee = method_exists( $product, 'get_meta' )
					? (float) $product->get_meta( '_subscription_sign_up_fee' )
					: (float) get_post_meta( $product->get_id(), '_subscription_sign_up_fee', true );
				$vars['sign_up_fee'] = wp_kses_post( wc_price( $sign_up_fee ) );

				// Trial period
				$trial_length = method_exists( $product, 'get_meta' )
					? (int) $product->get_meta( '_subscription_trial_length' )
					: (int) get_post_meta( $product->get_id(), '_subscription_trial_length', true );
				$trial_period_key = sanitize_key(
					method_exists( $product, 'get_meta' )
						? (string) $product->get_meta( '_subscription_trial_period' )
						: (string) get_post_meta( $product->get_id(), '_subscription_trial_period', true )
				);

				if ( $trial_length > 0 && ! empty( $trial_period_key ) ) {
					/* translators: 1: trial length number, 2: trial period (day, week, month, year) */
					$vars['trial_period'] = esc_html( sprintf( _x( '%1$d %2$s', 'trial period', 'mcod-minimalist-checkout-for-woocommerce' ), $trial_length, $trial_period_key ) );
				}

				// Only process the first subscription product
				break;
			}

			// First renewal date: fallback or use WCS helper if available
			if ( function_exists( 'wcs_cart_calculate_next_payment_date' ) ) {
				$next_ts = wcs_cart_calculate_next_payment_date( $recurring_cart );
				if ( $next_ts ) {
					$timestamp = is_numeric( $next_ts ) ? (int) $next_ts : strtotime( $next_ts );
					$vars['first_renewal_date'] = esc_html( date_i18n( wc_date_format(), $timestamp ) );
				}
			}

			// Only process the first recurring cart
			break;
		}

		return $vars;
	}

	/**
	 * Handle AJAX updates for the Minimalist Cart (quantity stepper, item removal, and coupon apply/remove).
	 */
	public function handle_ajax_update_cart_item() {
		check_ajax_referer( 'mcmchk-cart-nonce', 'security' );

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error( array( 'message' => __( 'Cart is not available.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		}

		if ( class_exists( 'WC_Subscriptions' ) ) {
			$this->mcmchk_setup_subscription_hooks();
		}

		$sub_action  = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : 'update_qty';
		$notice_html = '';
		$notice_type = 'success';

		if ( 'apply_coupon' === $sub_action ) {
			$coupon_code = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';
			if ( empty( $coupon_code ) ) {
				$notice_html = __( 'Please enter a discount code.', 'mcod-minimalist-checkout-for-woocommerce' );
				$notice_type = 'error';
			} else {
				wc_clear_notices();
				$applied = WC()->cart->apply_coupon( $coupon_code );
				if ( ! $applied ) {
					$error_notices = wc_get_notices( 'error' );
					if ( ! empty( $error_notices ) && isset( $error_notices[0]['notice'] ) ) {
						$notice_html = wp_strip_all_tags( $error_notices[0]['notice'] );
					} else {
						$notice_html = __( 'Invalid discount code.', 'mcod-minimalist-checkout-for-woocommerce' );
					}
					$notice_type = 'error';
				} else {
					$notice_html = __( 'Coupon applied.', 'mcod-minimalist-checkout-for-woocommerce' );
					$notice_type = 'success';
				}
				wc_clear_notices();
			}
		} elseif ( 'remove_coupon' === $sub_action ) {
			$coupon_code = isset( $_POST['coupon_code'] ) ? wc_format_coupon_code( sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) ) : '';
			if ( ! empty( $coupon_code ) ) {
				wc_clear_notices();
				WC()->cart->remove_coupon( $coupon_code );
				wc_clear_notices();
			}
		} else {
			// Default: update_qty or remove item (qty = 0)
			$cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
			$quantity      = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0;

			if ( ! empty( $cart_item_key ) && null !== WC()->cart->get_cart_item( $cart_item_key ) ) {
				if ( $quantity <= 0 ) {
					WC()->cart->remove_cart_item( $cart_item_key );
				} else {
					$cart_item    = WC()->cart->get_cart_item( $cart_item_key );
					$_product     = isset( $cart_item['data'] ) ? $cart_item['data'] : null;
					$max_quantity = ( $_product && $_product->get_max_purchase_quantity() > 0 ) ? $_product->get_max_purchase_quantity() : 9999;
					if ( $quantity > $max_quantity ) {
						$quantity = $max_quantity;
					}
					WC()->cart->set_quantity( $cart_item_key, $quantity, false );
				}
			}
		}

		WC()->cart->calculate_totals();

		if ( WC()->cart->is_empty() ) {
			wp_send_json_success( array(
				'is_empty'     => true,
				'redirect_url' => wc_get_cart_url(),
			) );
		}

		wp_send_json_success( array(
			'is_empty'     => false,
			'items_html'   => self::render_cart_items_html(),
			'summary_html' => self::render_cart_summary_html(),
			'cart_total'   => wp_kses_post( WC()->cart->get_total() ),
			'item_count'   => (int) WC()->cart->get_cart_contents_count(),
			'notice_html'  => $notice_html,
			'notice_type'  => $notice_type,
		) );
	}

	/**
	 * Render the Minimalist Cart items list HTML.
	 *
	 * @return string
	 */
	public static function render_cart_items_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		ob_start();
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				$max_qty           = $_product->get_max_purchase_quantity();
				?>
				<div class="mcrpd-cart-item-card <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
					<div class="mcrpd-cart-item-image-wrapper">
						<?php
						$thumbnail = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key );
						if ( ! $product_permalink ) {
							echo wp_kses_post( $thumbnail );
						} else {
							printf( '<a href="%s" class="mcrpd-cart-item-thumb-link">%s</a>', esc_url( $product_permalink ), wp_kses_post( $thumbnail ) );
						}
						?>
						<span class="mcrpd-product-qty"><?php echo esc_html( $cart_item['quantity'] ); ?></span>
					</div>

					<div class="mcrpd-cart-item-details">
						<div class="mcrpd-cart-item-title">
							<?php
							if ( ! $product_permalink ) {
								echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) );
							} else {
								echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', sprintf( '<a href="%s">%s</a>', esc_url( $product_permalink ), $_product->get_name() ), $cart_item, $cart_item_key ) );
							}
							?>
						</div>

						<div class="mcrpd-cart-item-meta">
							<?php echo wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) ); ?>
						</div>

						<?php
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
							echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification mcrpd-cart-backorder-note">' . esc_html__( 'Available on backorder', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>', $product_id ) );
						}
						?>
					</div>

					<div class="mcrpd-cart-item-controls">
						<?php if ( $_product->is_sold_individually() ) : ?>
							<div class="mcrpd-cart-single-qty-badge">
								<span>1</span>
								<input type="hidden" name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]" value="1" />
							</div>
						<?php else : ?>
							<div class="mcrpd-qty-stepper">
								<button type="button" class="mcrpd-qty-btn mcrpd-qty-minus" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e( 'Decrease quantity', 'mcod-minimalist-checkout-for-woocommerce' ); ?>">&minus;</button>
								<input
									type="number"
									class="mcrpd-qty-input"
									name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]"
									value="<?php echo esc_attr( $cart_item['quantity'] ); ?>"
									min="0"
									<?php if ( $max_qty > 0 ) : ?>
										max="<?php echo esc_attr( $max_qty ); ?>"
									<?php endif; ?>
									step="1"
									inputmode="numeric"
									data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
									aria-label="<?php esc_attr_e( 'Product quantity', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"
								/>
								<button type="button" class="mcrpd-qty-btn mcrpd-qty-plus" data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php esc_attr_e( 'Increase quantity', 'mcod-minimalist-checkout-for-woocommerce' ); ?>">&plus;</button>
							</div>
						<?php endif; ?>

						<div class="mcrpd-cart-item-subtotal-group">
							<span class="mcrpd-cart-item-subtotal">
								<?php echo wp_kses_post( self::get_clean_cart_item_subtotal( $_product, $cart_item, $cart_item_key ) ); ?>
							</span>
							<button
								type="button"
								class="mcrpd-cart-remove-btn"
								data-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>"
								aria-label="<?php esc_attr_e( 'Remove this item', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"
								title="<?php esc_attr_e( 'Remove this item', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"
							>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
							</button>
						</div>
					</div>
				</div>
				<?php
			}
		}
		return ob_get_clean();
	}

	/**
	 * Return a clean formatted subtotal for a cart item without WooCommerce Subscriptions'
	 * verbose recurring/trial/sign-up strings ("durante 1 mes", "Prueba gratuita", "que se deben pagar hoy"),
	 * since those details are already displayed in the Recurring Payment Block in the right sidebar.
	 *
	 * @param WC_Product $product       Product object.
	 * @param array      $cart_item     Cart item data.
	 * @param string     $cart_item_key Cart item key.
	 * @return string Formatted price HTML.
	 */
	public static function get_clean_cart_item_subtotal( $product, $cart_item, $cart_item_key ) {
		if ( class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) {
			if ( isset( $cart_item['line_subtotal'] ) ) {
				$row_price = WC()->cart->display_prices_including_tax()
					? (float) $cart_item['line_subtotal'] + (float) ( isset( $cart_item['line_subtotal_tax'] ) ? $cart_item['line_subtotal_tax'] : 0 )
					: (float) $cart_item['line_subtotal'];
			} else {
				$row_price = WC()->cart->display_prices_including_tax()
					? (float) wc_get_price_including_tax( $product, array( 'qty' => $cart_item['quantity'] ) )
					: (float) wc_get_price_excluding_tax( $product, array( 'qty' => $cart_item['quantity'] ) );
			}
			return wc_price( $row_price );
		}

		return apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key );
	}

	/**
	 * Return a clean formatted line subtotal for an order item on the Thank You page
	 * without WooCommerce Subscriptions appending recurring billing strings.
	 *
	 * @param WC_Order      $order Order object.
	 * @param WC_Order_Item $item  Order line item.
	 * @return string Formatted price HTML.
	 */
	public static function get_clean_order_item_subtotal( $order, $item ) {
		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : false;
		if ( class_exists( 'WC_Subscriptions' ) && ( ( $product && class_exists( 'WC_Subscriptions_Product' ) && WC_Subscriptions_Product::is_subscription( $product ) ) || ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, 'any' ) ) ) ) {
			$tax_display = get_option( 'woocommerce_tax_display_cart' );
			$inc_tax     = ( 'incl' === $tax_display );
			$subtotal    = $order->get_line_subtotal( $item, $inc_tax );
			return wc_price( $subtotal, array( 'currency' => $order->get_currency() ) );
		}

		return $order->get_formatted_line_subtotal( $item );
	}

	/**
	 * Render the Minimalist Cart right-column summary HTML (coupons, totals, and Proceed to Checkout CTA).
	 *
	 * @return string
	 */
	public static function render_cart_summary_html() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		$settings            = get_option( 'mcmchk_settings', array() );
		$disable_coupon      = ! empty( $settings['disable_coupon'] );
		$enable_digital_mode = ! empty( $settings['enable_digital_mode'] );

		ob_start();
		?>
		<h2 class="mcrpd-cart-summary-heading"><?php esc_html_e( 'Order summary', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>

		<?php if ( wc_coupons_enabled() && ! $disable_coupon ) : ?>
			<div class="mcrpd-sidebar-coupon-wrap mcrpd-cart-coupon-wrap">
				<input type="text" id="mcrpd_cart_coupon_code" class="mcrpd-coupon-input" placeholder="<?php esc_attr_e( 'Discount code', 'mcod-minimalist-checkout-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Discount code', 'mcod-minimalist-checkout-for-woocommerce' ); ?>" value="" />
				<button type="button" id="mcrpd_cart_apply_coupon_btn" class="mcrpd-coupon-button"><?php esc_html_e( 'Apply', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
			</div>
			<div id="mcrpd-cart-coupon-message" class="mcrpd-coupon-message"></div>
			<hr class="mcrpd-sidebar-divider" />
		<?php endif; ?>

		<?php do_action( 'mcmchk_before_sidebar_totals' ); ?>

		<div class="mcrpd-sidebar-totals mcrpd-cart-totals-wrapper">
			<!-- Subtotal -->
			<div class="mcrpd-totals-row">
				<span class="mcrpd-totals-label"><?php esc_html_e( 'Subtotal', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				<span class="mcrpd-totals-value"><?php wc_cart_totals_subtotal_html(); ?></span>
			</div>

			<!-- Coupons / Discounts applied -->
			<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
				<div class="mcrpd-totals-row mcrpd-coupon-row">
					<span class="mcrpd-totals-label">
						<?php esc_html_e( 'Discount', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
						<span class="mcrpd-coupon-tag">
							<span class="mcrpd-coupon-tag-icon">🏷️</span>
							<?php echo esc_html( $code ); ?>
							<a href="#" class="mcrpd-cart-remove-coupon-btn mcrpd-remove-coupon-btn" data-coupon="<?php echo esc_attr( $code ); ?>" aria-label="<?php esc_attr_e( 'Remove coupon', 'mcod-minimalist-checkout-for-woocommerce' ); ?>">×</a>
						</span>
					</span>
					<span class="mcrpd-totals-value">-<?php wc_cart_totals_coupon_html( $coupon ); ?></span>
				</div>
			<?php endforeach; ?>

			<!-- Shipping -->
			<?php if ( $enable_digital_mode ) : ?>
				<div class="mcrpd-totals-row">
					<span class="mcrpd-totals-label"><?php esc_html_e( 'Shipping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
					<span class="mcrpd-totals-value"><?php esc_html_e( 'Instant digital', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				</div>
			<?php elseif ( WC()->cart->needs_shipping() ) : ?>
				<div class="mcrpd-totals-row">
					<span class="mcrpd-totals-label"><?php esc_html_e( 'Shipping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
					<span class="mcrpd-totals-value">
						<?php
						if ( WC()->cart->show_shipping() ) {
							echo wp_kses_post( WC()->cart->get_cart_shipping_total() );
						} else {
							esc_html_e( 'Calculated at checkout', 'mcod-minimalist-checkout-for-woocommerce' );
						}
						?>
					</span>
				</div>
			<?php endif; ?>

			<!-- Fees -->
			<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
				<div class="mcrpd-totals-row mcrpd-fee-row">
					<span class="mcrpd-totals-label"><?php echo esc_html( $fee->name ); ?></span>
					<span class="mcrpd-totals-value"><?php wc_cart_totals_fee_html( $fee ); ?></span>
				</div>
			<?php endforeach; ?>

			<!-- Taxes -->
			<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
				<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
					<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
						<div class="mcrpd-totals-row">
							<span class="mcrpd-totals-label"><?php echo esc_html( $tax->label ); ?></span>
							<span class="mcrpd-totals-value"><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<div class="mcrpd-totals-row">
						<span class="mcrpd-totals-label"><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
						<span class="mcrpd-totals-value"><?php wc_cart_totals_taxes_total_html(); ?></span>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<hr class="mcrpd-sidebar-divider" />

			<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>

			<!-- Final Order Total -->
			<div class="mcrpd-totals-row mcrpd-total-row">
				<span class="mcrpd-total-label"><?php esc_html_e( 'Total', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				<span class="mcrpd-total-value">
					<span class="mcrpd-currency-code"><?php echo esc_html( get_woocommerce_currency() ); ?></span>
					<?php wc_cart_totals_order_total_html(); ?>
				</span>
			</div>

			<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>
		</div>

		<div class="wc-proceed-to-checkout mcrpd-cart-cta-wrapper">
			<?php if ( ! has_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout' ) ) : ?>
				<a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="checkout-button button alt wc-forward mcrpd-cart-checkout-btn">
					<?php esc_html_e( 'Proceed to checkout', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				</a>
			<?php endif; ?>
			<div class="mcrpd-cart-express-actions">
				<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
			</div>
		</div>

		<?php do_action( 'mcmchk_after_sidebar_totals' ); ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render front-end diagnostic tool for Administrators.
	 *
	 * @return string
	 */
	public function render_debug_shortcode() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return '';
		}

		$checkout = WC()->checkout();
		if ( ! $checkout ) {
			return '<div class="mcrpd-debug-panel"><p>WooCommerce Checkout object is not initialized.</p></div>';
		}

		// Fetch and sort fields to analyze them
		$billing_fields = $checkout->get_checkout_fields( 'billing' );
		$shipping_fields = $checkout->get_checkout_fields( 'shipping' );
		$order_fields = $checkout->get_checkout_fields( 'order' );

		if ( is_array( $billing_fields ) ) {
			uasort( $billing_fields, function( $a, $b ) {
				$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
				$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
				return $a_priority <=> $b_priority;
			} );
		}
		if ( is_array( $shipping_fields ) ) {
			uasort( $shipping_fields, function( $a, $b ) {
				$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
				$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
				return $a_priority <=> $b_priority;
			} );
		}
		if ( is_array( $order_fields ) ) {
			uasort( $order_fields, function( $a, $b ) {
				$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
				$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
				return $a_priority <=> $b_priority;
			} );
		}

		// Get active plugins related to Checkout/WooCommerce
		$active_plugins = get_option( 'active_plugins', array() );
		$checkout_plugins = array();
		foreach ( $active_plugins as $plugin ) {
			if ( stripos( $plugin, 'woocommerce' ) !== false || stripos( $plugin, 'checkout' ) !== false || stripos( $plugin, 'wpdesk' ) !== false ) {
				$checkout_plugins[] = basename( $plugin );
			}
		}

		ob_start();
		?>
		<div class="mcrpd-debug-panel" style="margin-top: 40px; padding: 25px; background: #1e293b; border-radius: 8px; color: #f8fafc; font-family: monospace; font-size: 13px; line-height: 1.6; border: 1px solid #334155; clear: both;">
			<h3 style="margin: 0 0 15px 0; color: #38bdf8; font-size: 16px; border-bottom: 1px solid #334155; padding-bottom: 8px; font-family: inherit;">🔍 Checkout Diagnostics (Admin)</h3>
			
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
				<div>
					<h4 style="margin: 0 0 8px 0; color: #f1f5f9; font-size: 14px;">⚙️ WooCommerce Settings</h4>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<li><strong>Minimalist Checkout Active:</strong> <?php echo self::is_minimalist_checkout() ? '<span style="color: #4ade80;">YES</span>' : '<span style="color: #f87171;">NO</span>'; ?></li>
						<li><strong>Selling Location:</strong> <?php echo esc_html( get_option( 'woocommerce_allowed_countries' ) ); ?></li>
						<li><strong>Shipping Location:</strong> <?php echo esc_html( get_option( 'woocommerce_ship_to_countries' ) ); ?></li>
						<li><strong>Default Country:</strong> <?php echo esc_html( get_option( 'woocommerce_default_country' ) ); ?></li>
						<li><strong>Allowed Countries (Billing):</strong> <?php echo esc_html( implode( ', ', array_keys( WC()->countries->get_allowed_countries() ) ) ); ?></li>
						<li><strong>Allowed Countries (Shipping):</strong> <?php echo esc_html( implode( ', ', array_keys( WC()->countries->get_shipping_countries() ) ) ); ?></li>
						<?php if ( isset( WC()->customer ) && is_object( WC()->customer ) ) : ?>
							<li><strong>Customer Country (Billing / Shipping):</strong> <?php echo esc_html( WC()->customer->get_billing_country() . ' / ' . WC()->customer->get_shipping_country() ); ?></li>
						<?php endif; ?>
					</ul>
				</div>
				
				<div>
					<h4 style="margin: 0 0 8px 0; color: #f1f5f9; font-size: 14px;">🔌 Active Checkout/Woo Plugins</h4>
					<ul style="list-style: none; padding: 0; margin: 0;">
						<?php if ( empty( $checkout_plugins ) ) : ?>
							<li style="color: #94a3b8;">None detected</li>
						<?php else : ?>
							<?php foreach ( $checkout_plugins as $plug ) : ?>
								<li>✅ <?php echo esc_html( $plug ); ?></li>
							<?php endforeach; ?>
						<?php endif; ?>
					</ul>
				</div>
			</div>

			<div style="margin-bottom: 20px;">
				<h4 style="margin: 0 0 8px 0; color: #f1f5f9; font-size: 14px;">📋 Field Order and Properties (After Filters)</h4>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
					<div>
						<h5 style="margin: 0 0 5px 0; color: #fb7185; font-size: 12px;">Billing</h5>
						<div style="max-height: 250px; overflow-y: auto; background: #0f172a; padding: 10px; border-radius: 4px; border: 1px solid #1e293b;">
							<?php foreach ( $billing_fields as $key => $field ) : ?>
								<div style="margin-bottom: 8px; border-bottom: 1px solid #334155; padding-bottom: 4px;">
									<div style="display: flex; justify-content: space-between;">
										<strong style="color: #94a3b8;"><?php echo esc_html( $key ); ?></strong>
										<span style="color: #f472b6;">P: <?php echo esc_html( isset( $field['priority'] ) ? $field['priority'] : '100' ); ?></span>
									</div>
									<div style="font-size: 11px; color: #cbd5e1;">
										L: <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : '(none)' ); ?><br>
										P: <?php echo esc_html( isset( $field['placeholder'] ) ? $field['placeholder'] : '(none)' ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>

					<div>
						<h5 style="margin: 0 0 5px 0; color: #fb7185; font-size: 12px;">Shipping</h5>
						<div style="max-height: 250px; overflow-y: auto; background: #0f172a; padding: 10px; border-radius: 4px; border: 1px solid #1e293b;">
							<?php foreach ( $shipping_fields as $key => $field ) : ?>
								<div style="margin-bottom: 8px; border-bottom: 1px solid #334155; padding-bottom: 4px;">
									<div style="display: flex; justify-content: space-between;">
										<strong style="color: #94a3b8;"><?php echo esc_html( $key ); ?></strong>
										<span style="color: #f472b6;">P: <?php echo esc_html( isset( $field['priority'] ) ? $field['priority'] : '100' ); ?></span>
									</div>
									<div style="font-size: 11px; color: #cbd5e1;">
										L: <?php echo esc_html( isset( $field['label'] ) ? $field['label'] : '(none)' ); ?><br>
										P: <?php echo esc_html( isset( $field['placeholder'] ) ? $field['placeholder'] : '(none)' ); ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>

			<div style="margin-bottom: 20px;">
				<h4 style="margin: 0 0 8px 0; color: #f1f5f9; font-size: 14px;">💾 Saved Options (mcmchk_settings)</h4>
				<div style="max-height: 200px; overflow-y: auto; background: #0f172a; padding: 10px; border-radius: 4px; border: 1px solid #1e293b; font-size: 11px;">
					<pre style="margin: 0; color: #cbd5e1; white-space: pre-wrap;"><?php
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					echo esc_html( print_r( get_option( 'mcmchk_settings', array() ), true ) );
					?></pre>
				</div>
			</div>
			
			<div style="font-size: 11px; color: #64748b; border-top: 1px solid #334155; padding-top: 8px;">
				<em>* This panel is only shown to administrator users. You can remove it by removing the [mcmchk_debug] shortcode from the checkout page.</em>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
