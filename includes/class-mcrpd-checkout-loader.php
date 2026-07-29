<?php
/**
 * Main plugin loader class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class MCRPD_Checkout_Loader {

	/**
	 * Singleton instance.
	 *
	 * @var MCRPD_Checkout_Loader|null
	 */
	private static $instance = null;

	/**
	 * Get active instance.
	 *
	 * @return MCRPD_Checkout_Loader
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
		// Register Page Template
		add_filter( 'theme_page_templates', array( $this, 'register_page_template' ) );
		add_filter( 'template_include', array( $this, 'load_page_template' ) );

		// Hook into WooCommerce templates override
		add_filter( 'woocommerce_locate_template', array( $this, 'override_woocommerce_template' ), 10, 3 );

		// Add custom body classes
		add_filter( 'body_class', array( $this, 'add_body_classes' ) );

		// Load assets
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ), 15 );

		// Decouple payment from checkout order review hook
		add_action( 'template_redirect', array( $this, 'handle_checkout_decoupling' ) );

		// Sync WooCommerce AJAX order review updates
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_checkout_fragments' ) );

		// Customize checkout fields
		add_filter( 'woocommerce_checkout_fields', array( $this, 'customize_checkout_fields' ), PHP_INT_MAX );

		// Register debug shortcode
		add_shortcode( 'mcrpd_debug', array( $this, 'render_debug_shortcode' ) );

		// AJAX login handler for inline checkout login
		add_action( 'wp_ajax_nopriv_mcrpd_ajax_login', array( $this, 'handle_ajax_login' ) );
	}

	/**
	 * Register the Minimalist Checkout Page Template.
	 */
	public function register_page_template( $templates ) {
		$templates['templates/minimalist-checkout-page.php'] = __( 'Minimalist Checkout', 'mcod-minimalist-checkout' );
		return $templates;
	}

	/**
	 * Load the custom Page Template from our plugin.
	 */
	public function load_page_template( $template ) {
		if ( is_page() ) {
			$meta_template = get_post_meta( get_the_ID(), '_wp_page_template', true );
			if ( 'templates/minimalist-checkout-page.php' === $meta_template ) {
				$plugin_template = MCRPD_TEMPLATES . 'minimalist-checkout-page.php';
				if ( file_exists( $plugin_template ) ) {
					return $plugin_template;
				}
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
		// Always check the WooCommerce checkout page ID setting to be robust against early calls
		// from other plugins before the main query (is_page()) is fully initialized.
		$checkout_page_id = wc_get_page_id( 'checkout' );
		if ( $checkout_page_id > 0 ) {
			$meta_template = get_post_meta( $checkout_page_id, '_wp_page_template', true );
			if ( 'templates/minimalist-checkout-page.php' === $meta_template ) {
				return true;
			}
		}

		// Fallback to checking current page if we are actually on a page that manually has the template
		if ( function_exists( 'is_page' ) && is_page() ) {
			$meta_template = get_post_meta( get_the_ID(), '_wp_page_template', true );
			if ( 'templates/minimalist-checkout-page.php' === $meta_template ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Override WooCommerce templates when on our custom checkout layout.
	 */
	public function override_woocommerce_template( $template, $template_name, $template_path ) {
		if ( self::is_minimalist_checkout() ) {
			$custom_template = MCRPD_TEMPLATES . 'woocommerce/' . $template_name;
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
		if ( self::is_minimalist_checkout() ) {
			$classes[] = 'mcrpd-shop-checkout-body';
			
			$settings = get_option( 'mcrpd_settings', array() );
			$use_theme_hf = ! empty( $settings['use_theme_hf'] ) ? true : false;
			
			if ( ! $use_theme_hf ) {
				$classes[] = 'mcrpd-distraction-free';
			}
		}
		return $classes;
	}

	/**
	 * Enqueue stylesheet and script files.
	 */
	public function enqueue_assets() {
		if ( self::is_minimalist_checkout() ) {
			// Enqueue Inter font from Google Fonts for shop-like modern feel
			wp_enqueue_style( 'mcrpd-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap', array(), '1.0.0' );
			
			// Enqueue local assets
			wp_enqueue_style( 'mcrpd-checkout-css', MCRPD_ASSETS . 'css/mcrpd-checkout.css', array(), MCRPD_VERSION );
			
			// Dynamic primary color CSS
			$settings = get_option( 'mcrpd_settings', array() );
			$primary_color = ! empty( $settings['primary_color'] ) ? sanitize_hex_color( $settings['primary_color'] ) : '#1773b0';
			$custom_css = "
				.woocommerce-checkout input[type='text']:focus,
				.woocommerce-checkout input[type='email']:focus,
				.woocommerce-checkout input[type='tel']:focus,
				.woocommerce-checkout select:focus,
				.woocommerce-checkout .select2-container--default.select2-container--focus .select2-selection--single,
				.woocommerce-checkout .select2-container--default.select2-container--open .select2-selection--single {
					border-color: {$primary_color} !important;
					box-shadow: 0 0 0 1px {$primary_color} !important;
				}
				.woocommerce-checkout #payment #place_order {
					background-color: {$primary_color} !important;
				}
				.mcrpd-coupon-button {
					background-color: {$primary_color} !important;
				}
			";
			wp_add_inline_style( 'mcrpd-checkout-css', $custom_css );

			wp_enqueue_script( 'mcrpd-checkout-js', MCRPD_ASSETS . 'js/mcrpd-checkout.js', array( 'jquery' ), MCRPD_VERSION, true );

			// Pass settings to our JavaScript
			wp_localize_script( 'mcrpd-checkout-js', 'mcrpd_params', array(
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'wc_ajax_url'         => WC_AJAX::get_endpoint( '%%endpoint%%' ),
				'apply_coupon_nonce'  => wp_create_nonce( 'apply-coupon' ),
				'remove_coupon_nonce' => wp_create_nonce( 'remove-coupon' ),
				'login_nonce'         => wp_create_nonce( 'mcrpd-login-nonce' ),
				'i18n_login_success'  => __( 'Login successful. Reloading...', 'mcod-minimalist-checkout' ),
				'i18n_login_empty'    => __( 'Please enter your username and password.', 'mcod-minimalist-checkout' ),
				'i18n_coupon_empty'   => __( 'Please enter a discount code.', 'mcod-minimalist-checkout' ),
				'i18n_coupon_error'   => __( 'Error applying coupon. Please try again.', 'mcod-minimalist-checkout' ),
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
		}
	}

	/**
	 * Handle AJAX login from the inline checkout login form.
	 */
	public function handle_ajax_login() {
		check_ajax_referer( 'mcrpd-login-nonce', 'security' );

		$username = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ) ) : '';
		// Passwords must not be sanitized to preserve special characters.
		$password = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $username ) || empty( $password ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your username and password.', 'mcod-minimalist-checkout' ) ) );
		}

		$credentials = array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		);

		$user = wp_signon( $credentials, is_ssl() );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => $user->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Login successful. Reloading...', 'mcod-minimalist-checkout' ) ) );
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
			echo '<p class="mcrpd-no-shipping-required">' . esc_html__( 'No shipping required.', 'mcod-minimalist-checkout' ) . '</p>';
			return;
		}

		$packages = WC()->shipping()->get_packages();

		foreach ( $packages as $i => $package ) {
			$available_methods = $package['rates'];
			$chosen_method     = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			$package_name      = apply_filters(
				'woocommerce_shipping_package_name',
				( $i + 1 > 1 )
					// translators: %d represents the shipping package number.
					? sprintf( _x( 'Shipping %d', 'shipping packages', 'mcod-minimalist-checkout' ), ( $i + 1 ) )
					: _x( 'Shipping', 'shipping packages', 'mcod-minimalist-checkout' ),
				$i,
				$package
			);

			if ( ! empty( $available_methods ) ) {
				echo '<ul id="shipping_method" class="woocommerce-shipping-methods mcrpd-shipping-methods-list">';

				foreach ( $available_methods as $method ) {
					$method_id   = esc_attr( $method->id );
					$method_name = sanitize_title( $method->id );
					$input_id    = 'shipping_method_' . esc_attr( $i ) . '_' . $method_name;
					$is_checked  = ( $chosen_method === $method->id );
					$label_text  = wc_cart_totals_shipping_method_label( $method );

					printf(
						'<li class="%s">',
						$is_checked ? 'mcrpd-shipping-card selected active' : 'mcrpd-shipping-card'
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

					echo '</li>';
				}

				echo '</ul>';
			} else {
				// No methods available for this package.
				echo '<p class="mcrpd-no-shipping-available">' . wp_kses_post( apply_filters( 'woocommerce_no_shipping_available_html', __( 'No shipping options available. Please check your address or contact us.', 'mcod-minimalist-checkout' ) ) ) . '</p>';
			}

			// Allow third-party plugins to hook after shipping methods.
			do_action( 'woocommerce_after_shipping_rate', $method ?? null, $i );
		}
	}

	/**
	 * Clean, standardize, and format WooCommerce checkout fields.
	 */
	public function customize_checkout_fields( $fields ) {
		if ( ! self::is_minimalist_checkout() ) {
			return $fields;
		}

		$settings        = get_option( 'mcrpd_settings', array() );
		$field_overrides = isset( $settings['field_overrides'] ) ? $settings['field_overrides'] : array();

		// Default placeholder fallbacks
		$default_placeholders = array(
			'billing' => array(
				'billing_first_name' => __( 'First name', 'mcod-minimalist-checkout' ),
				'billing_last_name'  => __( 'Last name', 'mcod-minimalist-checkout' ),
				'billing_company'    => __( 'Company (optional)', 'mcod-minimalist-checkout' ),
				'billing_address_1'  => __( 'Street address', 'mcod-minimalist-checkout' ),
				'billing_address_2'  => __( 'Apartment, suite, unit, etc. (optional)', 'mcod-minimalist-checkout' ),
				'billing_city'       => __( 'Town / City', 'mcod-minimalist-checkout' ),
				'billing_postcode'   => __( 'Postcode / ZIP (optional)', 'mcod-minimalist-checkout' ),
				'billing_phone'      => __( 'Phone', 'mcod-minimalist-checkout' ),
				'billing_email'      => __( 'Email address', 'mcod-minimalist-checkout' ),
			),
			'shipping' => array(
				'shipping_first_name' => __( 'First name', 'mcod-minimalist-checkout' ),
				'shipping_last_name'  => __( 'Last name', 'mcod-minimalist-checkout' ),
				'shipping_company'    => array( 'placeholder' => __( 'Company (optional)', 'mcod-minimalist-checkout' ) ),
				'shipping_address_1'  => __( 'Street address', 'mcod-minimalist-checkout' ),
				'shipping_address_2'  => __( 'Apartment, suite, unit, etc. (optional)', 'mcod-minimalist-checkout' ),
				'shipping_city'       => __( 'Town / City', 'mcod-minimalist-checkout' ),
				'shipping_postcode'   => __( 'Postcode / ZIP (optional)', 'mcod-minimalist-checkout' ),
				'shipping_phone'      => __( 'Phone', 'mcod-minimalist-checkout' ),
				'shipping_email'      => __( 'Email address', 'mcod-minimalist-checkout' ),
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
						<li><strong>Allowed Countries (Shipping):</strong> <?php echo esc_html( implode( ', ', array_keys( WC()->countries->get_shipping_countries() ) ) ); ?></li>
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
				<h4 style="margin: 0 0 8px 0; color: #f1f5f9; font-size: 14px;">💾 Saved Options (mcrpd_settings)</h4>
				<div style="max-height: 200px; overflow-y: auto; background: #0f172a; padding: 10px; border-radius: 4px; border: 1px solid #1e293b; font-size: 11px;">
					<pre style="margin: 0; color: #cbd5e1; white-space: pre-wrap;"><?php
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					echo esc_html( print_r( get_option( 'mcrpd_settings', array() ), true ) );
					?></pre>
				</div>
			</div>
			
			<div style="font-size: 11px; color: #64748b; border-top: 1px solid #334155; padding-top: 8px;">
				<em>* This panel is only shown to administrator users. You can remove it by removing the [mcrpd_debug] shortcode from the checkout page.</em>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
