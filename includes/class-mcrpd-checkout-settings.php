<?php
/**
 * Settings page for the plugin.
 *
 * Includes: General settings, Branding, Footer links,
 * and a visual Checkout Field Editor with drag-and-drop reordering,
 * enable/disable toggles, and custom label/placeholder overrides.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MCMCHK_Checkout_Settings {

	/**
	 * Settings key
	 */
	const OPTION_NAME = 'mcmchk_settings';

	/**
	 * Fields that cannot be disabled (required by WooCommerce).
	 *
	 * @var array
	 */
	private $locked_fields = array(
		'billing_first_name',
		'billing_last_name',
		'billing_email',
		'billing_country',
		'billing_address_1',
		'billing_city',
		'shipping_first_name',
		'shipping_last_name',
		'shipping_country',
		'shipping_address_1',
		'shipping_city',
	);

	/**
	 * Default WooCommerce checkout fields with their default labels/placeholders.
	 *
	 * @var array
	 */
	private $default_fields = array(
		'billing' => array(
			'billing_first_name' => array( 'label' => 'First name',              'placeholder' => 'First name' ),
			'billing_last_name'  => array( 'label' => 'Last name',               'placeholder' => 'Last name' ),
			'billing_company'    => array( 'label' => 'Company',                 'placeholder' => 'Company (optional)' ),
			'billing_country'    => array( 'label' => 'Country / Region',        'placeholder' => '' ),
			'billing_address_1'  => array( 'label' => 'Street address',          'placeholder' => 'Street address' ),
			'billing_address_2'  => array( 'label' => 'Apartment, suite, etc.', 'placeholder' => 'Apartment, suite, unit, etc. (optional)' ),
			'billing_city'       => array( 'label' => 'Town / City',             'placeholder' => 'Town / City' ),
			'billing_state'      => array( 'label' => 'State / County',          'placeholder' => '' ),
			'billing_postcode'   => array( 'label' => 'Postcode / ZIP',          'placeholder' => 'Postcode / ZIP (optional)' ),
			'billing_phone'      => array( 'label' => 'Phone',                   'placeholder' => 'Phone' ),
			'billing_email'      => array( 'label' => 'Email address',           'placeholder' => 'Email address' ),
		),
		'shipping' => array(
			'shipping_first_name' => array( 'label' => 'First name',              'placeholder' => 'First name' ),
			'shipping_last_name'  => array( 'label' => 'Last name',               'placeholder' => 'Last name' ),
			'shipping_company'    => array( 'label' => 'Company',                 'placeholder' => 'Company (optional)' ),
			'shipping_country'    => array( 'label' => 'Country / Region',        'placeholder' => '' ),
			'shipping_address_1'  => array( 'label' => 'Street address',          'placeholder' => 'Street address' ),
			'shipping_address_2'  => array( 'label' => 'Apartment, suite, etc.', 'placeholder' => 'Apartment, suite, unit, etc. (optional)' ),
			'shipping_city'       => array( 'label' => 'Town / City',             'placeholder' => 'Town / City' ),
			'shipping_state'      => array( 'label' => 'State / County',          'placeholder' => '' ),
			'shipping_postcode'   => array( 'label' => 'Postcode / ZIP',          'placeholder' => 'Postcode / ZIP (optional)' ),
		),
		'order' => array(
			'order_comments' => array( 'label' => 'Order notes', 'placeholder' => 'Notes about your order, e.g. special notes for delivery.' ),
		),
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add menu item to WordPress main menu.
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Minimalist Checkout', 'mcod-minimalist-checkout-for-woocommerce' ),
			__( 'Minimalist Checkout', 'mcod-minimalist-checkout-for-woocommerce' ),
			'manage_woocommerce',
			'mcrpd-checkout-settings',
			array( $this, 'render_settings_page' ),
			'dashicons-cart',
			55
		);
	}

	/**
	 * Enqueue admin scripts for color picker, media uploader, sortable, and field editor.
	 */
	public function enqueue_admin_assets( $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'mcrpd-checkout-settings' !== $current_page ) {
			return;
		}

		// WordPress core dependencies
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'dashicons' );

		// Plugin admin assets
		wp_enqueue_style( 'mcrpd-admin-css', MCMCHK_ASSETS . 'css/mcrpd-admin.css', array(), MCMCHK_VERSION );
		wp_enqueue_script( 'mcrpd-admin-js', MCMCHK_ASSETS . 'js/mcrpd-admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), MCMCHK_VERSION, true );
		wp_localize_script(
			'mcrpd-admin-js',
			'mcmchk_admin_params',
			array(
				'i18n_confirm_reset' => __( 'Are you sure you want to restore all fields to their default configuration? This action will remove your customizations.', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_select_logo'   => __( 'Select Logo', 'mcod-minimalist-checkout-for-woocommerce' ),
				'i18n_use_image'     => __( 'Use this image', 'mcod-minimalist-checkout-for-woocommerce' ),
			)
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'mcmchk_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		// 1. Page Templates & Post-Purchase Section
		add_settings_section(
			'mcmchk_templates_section',
			__( 'Page Templates & Post-Purchase', 'mcod-minimalist-checkout-for-woocommerce' ),
			array( $this, 'render_templates_section_description' ),
			'mcrpd-checkout-settings'
		);
		add_settings_field( 'force_template', __( 'Force Checkout Template', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_templates_section', array( 'key' => 'force_template', 'desc' => __( 'Force the minimalist template to load on the checkout page. Use this if your theme or page builder does not allow selecting the page template.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'force_cart_template', __( 'Force Cart Template', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_templates_section', array( 'key' => 'force_cart_template', 'desc' => __( 'Replace the default WooCommerce Cart with a distraction-free, two-column Minimalist Cart layout with instant AJAX quantity updates.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'force_thankyou_template', __( 'Force Order Received (Thank You) Template', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_templates_section', array( 'key' => 'force_thankyou_template', 'desc' => __( 'Replace the WooCommerce Order Received (Thank You) endpoint with a clean, two-column Minimalist Order Confirmation page.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field(
			'custom_thankyou_btn',
			__( 'Thank You Page "Continue Shopping" Button', 'mcod-minimalist-checkout-for-woocommerce' ),
			array( $this, 'render_thankyou_button_field' ),
			'mcrpd-checkout-settings',
			'mcmchk_templates_section'
		);
		add_settings_field( 'use_theme_hf', __( 'Use Theme Header & Footer', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_templates_section', array( 'key' => 'use_theme_hf', 'desc' => __( 'Load your active theme\'s header and footer instead of the isolated distraction-free canvas.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );

		// 2. Checkout Experience & Compatibility Section
		add_settings_section(
			'mcmchk_general_section',
			__( 'Checkout Experience & Compatibility', 'mcod-minimalist-checkout-for-woocommerce' ),
			array( $this, 'render_general_section_description' ),
			'mcrpd-checkout-settings'
		);
		add_settings_field( 'primary_color', __( 'Primary Accent Color', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_color_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'primary_color', 'default' => '#1773b0' ) );
		add_settings_field( 'hide_labels', __( 'Hide Field Labels', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'hide_labels', 'desc' => __( 'Hide labels above checkout fields for a cleaner design and use placeholders only. (Enabled by default)', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'disable_different_shipping_address', __( 'Single Address Mode (Hide Shipping Selector)', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'disable_different_shipping_address', 'desc' => __( 'Always use the billing address as the shipping address and hide the "Use a different shipping address" toggle.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'disable_coupon', __( 'Disable Coupon Field', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'disable_coupon', 'desc' => __( 'Hide the discount code field on both the checkout and minimalist cart pages.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'enable_newsletter', __( 'Show Newsletter Opt-in Checkbox', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'enable_newsletter', 'desc' => __( 'Display the "Email me with news and offers" checkbox below the billing email field and save customer preference to the order.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'enable_digital_mode', __( 'Enable Digital Products Mode', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'enable_digital_mode', 'desc' => __( 'Hides the shipping methods section and changes shipping label to "Instant digital" across Cart, Checkout, and Order Received.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field(
			'normalize_state_iso_codes',
			esc_html__( 'Normalize State Codes (Express Checkout)', 'mcod-minimalist-checkout-for-woocommerce' ) . ' <span class="mcrpd-badge-stripe">' . esc_html__( 'Stripe', 'mcod-minimalist-checkout-for-woocommerce' ) . '</span>',
			array( $this, 'render_checkbox_field' ),
			'mcrpd-checkout-settings',
			'mcmchk_general_section',
			array(
				'key'           => 'normalize_state_iso_codes',
				'highlight_box' => true,
				'badge'         => __( 'Stripe', 'mcod-minimalist-checkout-for-woocommerce' ),
				'box_title'     => __( 'Stripe Link, Apple Pay & Google Pay — Regional State Code Fix', 'mcod-minimalist-checkout-for-woocommerce' ),
				'desc'          => __( 'Enable ONLY if customers see an "invalid state (e.g. AMA). Must be one of: CO-AMA..." error when paying with Stripe Link, Apple Pay, or Google Pay. Some regional location/shipping plugins register state codes with a country prefix (like "CO-AMA"), whereas Stripe Express Checkout sends standard ISO codes without the prefix ("AMA"). When enabled, the checkout automatically converts between both formats so Express Checkout payments do not fail. (Disabled by default)', 'mcod-minimalist-checkout-for-woocommerce' ),
			)
		);

		// 3. Branding Section
		add_settings_section(
			'mcmchk_branding_section',
			__( 'Branding & Header', 'mcod-minimalist-checkout-for-woocommerce' ),
			array( $this, 'render_branding_section_description' ),
			'mcrpd-checkout-settings'
		);
		add_settings_field( 'hide_brand_header', __( 'Hide Brand Header', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'hide_brand_header', 'desc' => __( 'Check to hide the entire brand header section completely.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'brand_logo', __( 'Brand Logo URL', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_media_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_logo' ) );
		add_settings_field( 'brand_logo_width', __( 'Brand Logo Width (px)', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_logo_width', 'desc' => __( 'Set the maximum width of the logo in pixels. Default is 125.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'brand_name', __( 'Brand Name', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_name', 'desc' => __( 'Used as fallback if logo is not provided.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );

		// 4. Footer Links
		add_settings_section(
			'mcmchk_footer_section',
			__( 'Footer Policy Links', 'mcod-minimalist-checkout-for-woocommerce' ),
			array( $this, 'render_footer_section_description' ),
			'mcrpd-checkout-settings'
		);
		add_settings_field( 'link_refund', __( 'Refund Policy Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_refund', 'placeholder' => 'https://example.com/refund-policy' ) );
		add_settings_field( 'link_privacy', __( 'Privacy Policy Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_privacy', 'placeholder' => 'https://example.com/privacy-policy' ) );
		add_settings_field( 'link_terms', __( 'Terms of Service Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_terms', 'placeholder' => 'https://example.com/terms' ) );
		add_settings_field( 'link_contact', __( 'Contact Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_contact', 'placeholder' => 'https://example.com/contact' ) );

		// 5. Subscriptions Section (only registered if WooCommerce Subscriptions is active)
		if ( class_exists( 'WC_Subscriptions' ) ) {
			add_settings_section(
				'mcmchk_subscription_section',
				__( 'Subscriptions', 'mcod-minimalist-checkout-for-woocommerce' ),
				array( $this, 'render_subscription_section_description' ),
				'mcrpd-checkout-settings'
			);
			add_settings_field(
				'disable_add_to_subscription',
				__( 'Disable "Add Cart to Existing Subscription"', 'mcod-minimalist-checkout-for-woocommerce' ),
				array( $this, 'render_checkbox_field' ),
				'mcrpd-checkout-settings',
				'mcmchk_subscription_section',
				array(
					'key'  => 'disable_add_to_subscription',
					'desc' => __( 'Hide the "Do you want to add your cart to an existing subscription?" option and table displayed by WooCommerce Subscriptions in the Order Summary.', 'mcod-minimalist-checkout-for-woocommerce' ),
				)
			);
			add_settings_field(
				'subscription_recurring_template',
				__( 'Recurring Payment Block', 'mcod-minimalist-checkout-for-woocommerce' ),
				array( $this, 'render_textarea_field' ),
				'mcrpd-checkout-settings',
				'mcmchk_subscription_section',
				array(
					'key'  => 'subscription_recurring_template',
					'desc' => __( 'Customize the recurring payment block shown in the Checkout, Cart, and Order Received (Thank You) sidebars. Leave empty to use the default WooCommerce Subscriptions output. Use the variables listed below.', 'mcod-minimalist-checkout-for-woocommerce' ),
					'rows' => 8,
				)
			);
		}
	}

	/**
	 * Sanitize settings.
	 */
	public function sanitize_settings( $input ) {
		$output = get_option( self::OPTION_NAME, array() );

		$output['force_template']              = isset( $input['force_template'] ) ? '1' : '0';
		$output['force_cart_template']         = isset( $input['force_cart_template'] ) ? '1' : '0';
		$output['force_thankyou_template']     = isset( $input['force_thankyou_template'] ) ? '1' : '0';
		$output['custom_thankyou_btn_enabled'] = isset( $input['custom_thankyou_btn_enabled'] ) ? '1' : '0';
		$output['thankyou_btn_text']           = isset( $input['thankyou_btn_text'] ) ? sanitize_text_field( wp_unslash( $input['thankyou_btn_text'] ) ) : '';
		$output['thankyou_btn_url']            = isset( $input['thankyou_btn_url'] ) ? esc_url_raw( wp_unslash( $input['thankyou_btn_url'] ) ) : '';
		$output['use_theme_hf']                = isset( $input['use_theme_hf'] ) ? '1' : '0';
		$output['hide_labels']                 = isset( $input['hide_labels'] ) ? '1' : '0';
		$output['hide_brand_header']           = isset( $input['hide_brand_header'] ) ? '1' : '0';
		$output['disable_different_shipping_address'] = isset( $input['disable_different_shipping_address'] ) ? '1' : '0';
		$output['disable_coupon']              = isset( $input['disable_coupon'] ) ? '1' : '0';
		$output['enable_newsletter']           = isset( $input['enable_newsletter'] ) ? '1' : '0';
		$output['enable_digital_mode']         = isset( $input['enable_digital_mode'] ) ? '1' : '0';
		$output['normalize_state_iso_codes']   = isset( $input['normalize_state_iso_codes'] ) ? '1' : '0';
		$output['primary_color']               = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#1773b0';
		$output['brand_logo']                  = isset( $input['brand_logo'] ) ? esc_url_raw( $input['brand_logo'] ) : '';
		$output['brand_logo_width']            = isset( $input['brand_logo_width'] ) ? absint( $input['brand_logo_width'] ) : 125;
		$output['brand_name']                  = isset( $input['brand_name'] ) ? sanitize_text_field( $input['brand_name'] ) : '';
		$output['link_refund']                 = isset( $input['link_refund'] ) ? esc_url_raw( $input['link_refund'] ) : '';
		$output['link_privacy']                = isset( $input['link_privacy'] ) ? esc_url_raw( $input['link_privacy'] ) : '';
		$output['link_terms']                  = isset( $input['link_terms'] ) ? esc_url_raw( $input['link_terms'] ) : '';
		$output['link_contact']                = isset( $input['link_contact'] ) ? esc_url_raw( $input['link_contact'] ) : '';

		// Subscriptions settings — only saved when WooCommerce Subscriptions is active.
		if ( class_exists( 'WC_Subscriptions' ) ) {
			$output['disable_add_to_subscription'] = isset( $input['disable_add_to_subscription'] ) ? '1' : '0';
			if ( isset( $input['subscription_recurring_template'] ) ) {
				$output['subscription_recurring_template'] = wp_kses_post( wp_unslash( $input['subscription_recurring_template'] ) );
			}
		}

		// Handle field overrides
		if ( ! empty( $input['reset_fields_flag'] ) && '1' === $input['reset_fields_flag'] ) {
			// Reset: remove all field overrides
			unset( $output['field_overrides'] );
		} elseif ( ! empty( $input['field_overrides_json'] ) ) {
			$decoded = json_decode( wp_unslash( $input['field_overrides_json'] ), true );
			if ( is_array( $decoded ) ) {
				$output['field_overrides'] = $this->sanitize_field_overrides( $decoded );
			}
		}

		return $output;
	}

	/**
	 * Sanitize field overrides array.
	 *
	 * @param array $data Raw decoded JSON data.
	 * @return array Sanitized field overrides.
	 */
	private function sanitize_field_overrides( $data ) {
		$sanitized = array();
		$allowed_sections = array( 'billing', 'shipping', 'order' );

		// Build a flat list of all known field keys for validation
		$known_keys = array();
		foreach ( $this->default_fields as $section => $fields ) {
			foreach ( $fields as $key => $def ) {
				$known_keys[] = $key;
			}
		}

		foreach ( $data as $section => $fields ) {
			if ( ! in_array( $section, $allowed_sections, true ) || ! is_array( $fields ) ) {
				continue;
			}

			$sanitized[ $section ] = array();

			foreach ( $fields as $key => $props ) {
				// Validate against known keys only
				$key = sanitize_key( $key );
				if ( ! in_array( $key, $known_keys, true ) ) {
					continue;
				}

				$is_locked = in_array( $key, $this->locked_fields, true );
				$allowed_widths = array( 'form-row-wide', 'form-row-first', 'form-row-last' );
				$width = isset( $props['width'] ) && in_array( $props['width'], $allowed_widths, true ) ? $props['width'] : 'form-row-wide';

				$sanitized[ $section ][ $key ] = array(
					'enabled'     => $is_locked ? true : ! empty( $props['enabled'] ),
					'priority'    => isset( $props['priority'] ) ? absint( $props['priority'] ) : 100,
					'width'       => $width,
					'label'       => isset( $props['label'] ) ? sanitize_text_field( $props['label'] ) : '',
					'placeholder' => isset( $props['placeholder'] ) ? sanitize_text_field( $props['placeholder'] ) : '',
				);
			}
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$settings        = get_option( self::OPTION_NAME, array() );
		$field_overrides = isset( $settings['field_overrides'] ) ? $settings['field_overrides'] : array();
		$checkout_url    = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$cart_url        = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
		$wporg_review    = 'https://wordpress.org/support/plugin/mcod-minimalist-checkout-for-woocommerce/reviews/#new-post';
		$wporg_plugin    = 'https://wordpress.org/plugins/mcod-minimalist-checkout-for-woocommerce/';
		?>
		<div class="wrap mcrpd-settings-wrap">

			<!-- Modern Admin Hero Header -->
			<div class="mcrpd-admin-header">
				<div class="mcrpd-admin-header-left">
					<div class="mcrpd-admin-logo-badge">
						<span class="dashicons dashicons-cart"></span>
					</div>
					<div class="mcrpd-admin-title-group">
						<div class="mcrpd-admin-title-row">
							<h1><?php esc_html_e( 'Minimalist Checkout for WooCommerce', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h1>
							<span class="mcrpd-version-pill">v<?php echo esc_html( defined( 'MCMCHK_VERSION' ) ? MCMCHK_VERSION : '2.0' ); ?></span>
						</div>
						<p class="mcrpd-admin-subtitle">
							<?php esc_html_e( 'Distraction-free Cart, Checkout & Thank You templates with visual Checkout Field Manager.', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
						</p>
					</div>
				</div>
				<div class="mcrpd-admin-header-actions">
					<a href="<?php echo esc_url( $cart_url ); ?>" target="_blank" rel="noopener noreferrer" class="mcrpd-header-btn mcrpd-header-btn-secondary">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( 'View Cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $checkout_url ); ?>" target="_blank" rel="noopener noreferrer" class="mcrpd-header-btn mcrpd-header-btn-secondary">
						<span class="dashicons dashicons-external"></span>
						<?php esc_html_e( 'View Checkout', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $wporg_review ); ?>" target="_blank" rel="noopener noreferrer" class="mcrpd-header-btn mcrpd-header-btn-star">
						<span class="mcrpd-stars-inline">★★★★★</span>
						<?php esc_html_e( 'Rate Plugin', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
				</div>
			</div>

			<!-- Top Navigation Tabs -->
			<nav class="nav-tab-wrapper mcrpd-main-tabs" aria-label="<?php esc_attr_e( 'Minimalist Checkout Settings Tabs', 'mcod-minimalist-checkout-for-woocommerce' ); ?>">
				<a href="#mcrpd-tab-design" class="nav-tab nav-tab-active" data-tab="design">
					<span class="dashicons dashicons-admin-customizer"></span>
					<?php esc_html_e( 'Design & Settings', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				</a>
				<a href="#mcrpd-tab-fields" class="nav-tab" data-tab="fields">
					<span class="dashicons dashicons-editor-table"></span>
					<?php esc_html_e( 'Checkout Fields', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				</a>
				<a href="#mcrpd-tab-docs" class="nav-tab" data-tab="docs">
					<span class="dashicons dashicons-book-alt"></span>
					<?php esc_html_e( 'Compatibility & Help', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				</a>
			</nav>

			<form method="post" action="options.php" class="mcrpd-settings-form">
				<?php settings_fields( 'mcmchk_settings_group' ); ?>

				<!-- Tab 1: Design & General Settings -->
				<div id="mcrpd-main-tab-design" class="mcrpd-main-tab-content active">
					<?php do_settings_sections( 'mcrpd-checkout-settings' ); ?>
				</div>

				<!-- Tab 2: Checkout Field Editor -->
				<div id="mcrpd-main-tab-fields" class="mcrpd-main-tab-content" style="display: none;">
					<div class="mcrpd-field-editor-wrap">
						<div class="mcrpd-field-editor-header">
							<div>
								<h2><?php esc_html_e( 'Checkout Field Editor & Manager', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
								<p class="mcrpd-field-editor-subtitle"><?php esc_html_e( 'Drag rows to reorder fields, toggle optional fields on or off, adjust column widths (100% or 50%), and customize labels or placeholders.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
							</div>
						</div>

						<!-- Tab Navigation -->
						<div class="mcrpd-field-tabs">
							<button type="button" class="mcrpd-field-tab active" data-tab="billing"><?php esc_html_e( 'Billing Fields', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="shipping"><?php esc_html_e( 'Shipping Fields', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="order"><?php esc_html_e( 'Order Notes', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
						</div>

						<!-- Tab Contents -->
						<?php
						$sections = array(
							'billing'  => __( 'Billing', 'mcod-minimalist-checkout-for-woocommerce' ),
							'shipping' => __( 'Shipping', 'mcod-minimalist-checkout-for-woocommerce' ),
							'order'    => __( 'Order', 'mcod-minimalist-checkout-for-woocommerce' ),
						);

						foreach ( $sections as $section_key => $section_label ) :
							$is_first = ( 'billing' === $section_key );
							$fields   = $this->get_section_fields( $section_key, $field_overrides );
						?>
						<div id="mcrpd-tab-<?php echo esc_attr( $section_key ); ?>"
							 class="mcrpd-field-tab-content <?php echo esc_attr( $is_first ? 'active' : '' ); ?>"
							 data-section="<?php echo esc_attr( $section_key ); ?>">
							<table class="mcrpd-field-list">
								<thead>
									<tr>
										<th></th>
										<th><?php esc_html_e( 'Field', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										<th><?php esc_html_e( 'Active', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										<th><?php esc_html_e( 'Width', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										<th><?php esc_html_e( 'Label', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										<th><?php esc_html_e( 'Placeholder', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $fields as $field_key => $field_data ) :
										$is_locked = in_array( $field_key, $this->locked_fields, true );
										$is_enabled = $field_data['enabled'];
										$width      = isset( $field_data['width'] ) ? $field_data['width'] : 'form-row-wide';
										$default    = isset( $this->default_fields[ $section_key ][ $field_key ] )
													? $this->default_fields[ $section_key ][ $field_key ]
													: array( 'label' => '', 'placeholder' => '' );
									?>
									<tr data-field-key="<?php echo esc_attr( $field_key ); ?>"
										class="<?php echo esc_attr( ! $is_enabled ? 'mcrpd-field-disabled' : '' ); ?>">
										<td class="mcrpd-drag-handle"><span class="dashicons dashicons-menu"></span></td>
										<td><span class="mcrpd-field-key"><?php echo esc_html( $field_key ); ?></span></td>
										<td>
											<div class="mcrpd-toggle-wrap">
												<label class="mcrpd-toggle">
													<input type="checkbox"
														   class="mcrpd-field-enabled-toggle"
														   <?php checked( $is_enabled ); ?>
														   <?php disabled( $is_locked ); ?> />
													<span class="mcrpd-toggle-slider"></span>
												</label>
												<?php if ( $is_locked ) : ?>
													<span class="mcrpd-lock-icon dashicons dashicons-lock" title="<?php esc_attr_e( 'Required WooCommerce field', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"></span>
												<?php endif; ?>
											</div>
										</td>
										<td>
											<select class="mcrpd-field-width-select" <?php disabled( ! $is_enabled ); ?>>
												<option value="form-row-wide" <?php selected( $width, 'form-row-wide' ); ?>><?php esc_html_e( '100%', 'mcod-minimalist-checkout-for-woocommerce' ); ?></option>
												<option value="form-row-first" <?php selected( $width, 'form-row-first' ); ?>><?php esc_html_e( '50% (Left)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></option>
												<option value="form-row-last" <?php selected( $width, 'form-row-last' ); ?>><?php esc_html_e( '50% (Right)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></option>
											</select>
										</td>
										<td>
											<input type="text"
												   class="mcrpd-field-label-input"
												   value="<?php echo esc_attr( $field_data['label'] ); ?>"
												   placeholder="<?php echo esc_attr( $default['label'] ); ?>"
												   <?php disabled( ! $is_enabled ); ?> />
										</td>
										<td>
											<input type="text"
												   class="mcrpd-field-placeholder-input"
												   value="<?php echo esc_attr( $field_data['placeholder'] ); ?>"
												   placeholder="<?php echo esc_attr( $default['placeholder'] ); ?>"
												   <?php disabled( ! $is_enabled ); ?> />
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php endforeach; ?>

						<!-- Hidden JSON field for serialized data -->
						<textarea id="mcmchk_field_overrides_json"
								  name="<?php echo esc_attr( self::OPTION_NAME . '[field_overrides_json]' ); ?>"
								  style="display:none;"></textarea>
						<input type="hidden"
							   id="mcmchk_reset_fields_flag"
							   name="<?php echo esc_attr( self::OPTION_NAME . '[reset_fields_flag]' ); ?>"
							   value="0" />

						<!-- Actions footer -->
						<div class="mcrpd-field-editor-actions">
							<button type="button" class="mcrpd-reset-fields-btn">
								<?php esc_html_e( 'Restore Default Values', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
							</button>
							<span class="mcrpd-editor-note">
								<?php esc_html_e( 'Drag fields to reorder. Changes will apply after saving.', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
							</span>
						</div>
					</div>
				</div>

				<!-- Tab 3: Documentation -->
				<div id="mcrpd-main-tab-docs" class="mcrpd-main-tab-content" style="display: none;">
					<div class="mcrpd-docs-wrap">
						<h2><?php esc_html_e( 'Documentation & Compatibility', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
						
						<div class="mcrpd-docs-content">
							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'What does this plugin do?', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'Minimalist Checkout transforms the standard WooCommerce Checkout, Cart, and Order Received (Thank You) pages into a streamlined, clean, and conversion-focused experience. It optionally hides the theme header and footer to eliminate distractions and applies a modern, responsive two-column layout.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Checkout, Cart & Order Received Setup', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'For Minimalist Checkout to work properly, configure your WooCommerce pages using either the Page Template selector or the "Force Template" checkboxes in the Design & Settings tab:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								
								<?php
								$checkout_page_id = wc_get_page_id( 'checkout' );
								if ( ! $checkout_page_id || $checkout_page_id <= 0 || ! get_post( $checkout_page_id ) ) :
								?>
									<div style="background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 12px; margin-bottom: 15px; border-radius: 4px;">
										<p style="color: #b91c1c; margin: 0;">
											<strong><?php esc_html_e( 'Warning:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong>
							<?php
							echo wp_kses_post( sprintf(
								/* translators: %s: URL to WooCommerce advanced settings */
								esc_html__( 'Your checkout page is not created or assigned. You must create it and assign it in %s.', 'mcod-minimalist-checkout-for-woocommerce' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced' ) ) . '" style="color: #991b1b; text-decoration: underline;">WooCommerce &gt; Settings &gt; Advanced</a>'
							) );
											?>
										</p>
									</div>
								<?php endif; ?>

								<ul style="list-style-type: decimal; margin-left: 20px; margin-bottom: 15px;">
									<li><strong><?php esc_html_e( 'Checkout Page:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Enable "Force Checkout Template" in settings OR select the "Minimalist Checkout" Page Template on your Checkout page (with the [woocommerce_checkout] shortcode).', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><strong><?php esc_html_e( 'Cart Page:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Enable "Force Cart Template" in settings OR select the "Minimalist Cart" (or "Minimalist Checkout") Page Template on your Cart page.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><strong><?php esc_html_e( 'Order Received (Thank You) Endpoint:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Enable "Force Order Received (Thank You) Template" in settings to hijack the order confirmation URL with the same minimalist two-column layout.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
								</ul>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Diagnostics & Debugging', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'If you experience issues with fields not hiding, incorrect ordering, or 3rd-party plugin conflicts, you can add the following shortcode to your checkout page content (below [woocommerce_checkout]):', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<p><code>[mcmchk_debug]</code></p>
								<p><?php esc_html_e( 'This will render a diagnostic panel (visible only to Administrators) directly on the checkout frontend. It displays active session status, WooCommerce region settings, active checkout plugins, field priority rules after filters, and a complete dump of the mcmchk_settings database option.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( '3rd-Party Plugin Field Compatibility', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'If you use 3rd-party plugins (such as custom field editors, ID/Tax number plugins, etc.) to add extra fields to the checkout, they might not inherit the styling automatically. You can resolve this by adding the following CSS classes to those fields within your other plugin\'s settings:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								
								<table class="mcrpd-docs-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Element', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'CSS Class to Use', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Description', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><strong><?php esc_html_e( 'Field Container (Layout)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong></td>
											<td><code>mcrpd-field</code></td>
											<td><?php esc_html_e( 'Applies proper spacing and structure to fit the layout.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( '50% Width Container', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong></td>
											<td><code>mcrpd-field-half</code></td>
											<td><?php esc_html_e( 'Places two fields side-by-side.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Text Input', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong></td>
											<td><code>mcrpd-input-text</code></td>
											<td><?php esc_html_e( 'Styles the text input box to match the checkout theme.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Number Input', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong></td>
											<td><code>mcrpd-input-number</code></td>
											<td><?php esc_html_e( 'Ideal for ID numbers, phone numbers, or numeric codes.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Select Dropdown', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong></td>
											<td><code>mcrpd-input-select</code></td>
											<td><?php esc_html_e( 'Applies minimalist borders and arrow indicators to dropdowns.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td>
										</tr>
									</tbody>
								</table>

								<h4><?php esc_html_e( 'Practical Example:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h4>
								<p><?php esc_html_e( 'If your plugin asks for "Container CSS Classes", enter:', 'mcod-minimalist-checkout-for-woocommerce' ); ?> <code>mcrpd-field mcrpd-field-half</code></p>
								<p><?php esc_html_e( 'If it asks for "Input CSS Classes", enter:', 'mcod-minimalist-checkout-for-woocommerce' ); ?> <code>mcrpd-input-text</code></p>
								
								<div class="mcrpd-docs-notice">
									<p><strong><?php esc_html_e( 'Automatic Note:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Any field using the native WooCommerce', 'mcod-minimalist-checkout-for-woocommerce' ); ?> <code>input-text</code> <?php esc_html_e( 'class (which most plugins include by default) will automatically inherit these visual styles.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								</div>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Custom Hooks (Actions & Filters)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'While this plugin uses native WooCommerce hooks for the checkout form (Billing, Shipping, etc.), it provides its own custom actions to inject content into the order summary sidebar:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
									<li><code>mcmchk_before_sidebar_product_list</code> - <?php esc_html_e( 'Fires before the cart products list.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><code>mcmchk_before_sidebar_totals</code> - <?php esc_html_e( 'Fires before the order totals container.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><code>mcmchk_after_sidebar_totals</code> - <?php esc_html_e( 'Fires at the very bottom of the sidebar.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
								</ul>
								
								<h4><?php esc_html_e( 'Custom Shipping Hooks', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h4>
								<p><?php esc_html_e( 'To prevent conflicts with WooCommerce core, the plugin replaces the native shipping loop hooks with its own unique prefixes:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<ul style="list-style-type: disc; margin-left: 20px; margin-bottom: 15px;">
									<li><code>mcmchk_shipping_package_name</code> - <?php esc_html_e( 'Filters the shipping package name.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><code>mcmchk_no_shipping_available_html</code> - <?php esc_html_e( 'Filters the HTML shown when no shipping methods are available.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><code>mcmchk_after_shipping_rate</code> - <?php esc_html_e( 'Action fired after each shipping rate is rendered.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
								</ul>
								
								<h4><?php esc_html_e( 'Standard WooCommerce Hooks', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h4>
								<p><?php esc_html_e( 'To add features to the main forms (Contact, Billing address, Delivery), continue using native WooCommerce hooks. For example:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<ul style="list-style-type: disc; margin-left: 20px;">
									<li><code>woocommerce_before_checkout_billing_form</code> / <code>woocommerce_after_checkout_billing_form</code></li>
									<li><code>woocommerce_before_checkout_shipping_form</code> / <code>woocommerce_after_checkout_shipping_form</code></li>
								</ul>
							</div>

							<?php if ( class_exists( 'WC_Subscriptions' ) ) : ?>
							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Subscription Text Variables', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'Use these variables in the "Recurring Payment Block" textarea (Design & Settings tab). They will be replaced with live values from the cart.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<table class="mcrpd-docs-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Variable', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Description', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
											<th><?php esc_html_e( 'Example output', 'mcod-minimalist-checkout-for-woocommerce' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr><td><code>{{recurring_subtotal}}</code></td><td><?php esc_html_e( 'Subtotal of the recurring cart (formatted price)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td>$13</td></tr>
										<tr><td><code>{{recurring_total}}</code></td><td><?php esc_html_e( 'Total of the recurring payment (formatted price)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td>$13</td></tr>
										<tr><td><code>{{period}}</code></td><td><?php esc_html_e( 'Billing period label', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td><?php esc_html_e( '1 month', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td></tr>
										<tr><td><code>{{interval}}</code></td><td><?php esc_html_e( 'Billing interval number', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td>1</td></tr>
										<tr><td><code>{{period_name}}</code></td><td><?php esc_html_e( 'Period name only (singular)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td><?php esc_html_e( 'month', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td></tr>
										<tr><td><code>{{first_renewal_date}}</code></td><td><?php esc_html_e( 'Date of the first renewal payment (Requires official WCS helper)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td><?php esc_html_e( 'October 1, 2026', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td></tr>
										<tr><td><code>{{current_date | 31}}</code></td><td><?php esc_html_e( 'Dynamic future date. Replaces the number with any amount of days to add to today.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td><?php esc_html_e( 'October 10, 2026', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td></tr>
										<tr><td><code>{{sign_up_fee}}</code></td><td><?php esc_html_e( 'Sign-up / enrollment fee (formatted price)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td>$0</td></tr>
										<tr><td><code>{{trial_period}}</code></td><td><?php esc_html_e( 'Free trial period string (empty if no trial)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td><td><?php esc_html_e( '7 days', 'mcod-minimalist-checkout-for-woocommerce' ); ?></td></tr>
									</tbody>
								</table>
								<h4 style="margin-top:15px;"><?php esc_html_e( 'Example template:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h4>
								<pre style="background:#f0f0f1;padding:10px;border-radius:4px;font-size:12px;white-space:pre-wrap;"><?php echo esc_html( "<div class=\"mcrpd-subscription-block\">\n  <p class=\"mcrpd-sub-title\">Recurring payment</p>\n  <div class=\"mcrpd-sub-row\">\n    <span>Subtotal</span><span>{{recurring_subtotal}} for {{period}}</span>\n  </div>\n  <div class=\"mcrpd-sub-row mcrpd-sub-total\">\n    <span>Recurring payment</span><span><strong>{{recurring_total}}</strong> for {{period}}</span>\n  </div>\n  <small>First renewal: {{first_renewal_date}}</small>\n</div>" ); ?></pre>
							</div>
							<?php endif; ?>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Stripe Link, Apple Pay & Google Pay — State / Department Code Compatibility', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'By default, both WooCommerce core and Stripe Express Checkout (Link, Apple Pay, Google Pay) use unprefixed ISO 3166-2 subdivision codes for states/departments (for example, "AMA", "ANT", or "DC" in Colombia).', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<p><?php esc_html_e( 'However, in certain countries (especially in Latin America), third-party shipping or city-selector plugins override WooCommerce states and add a 2-letter country prefix (e.g., "CO-AMA", "CO-ANT", "CO-DC"). Because Stripe Link submits addresses directly to the WooCommerce Store API (/wp-json/wc/store/v1/checkout) using standard unprefixed codes ("AMA"), WooCommerce rejects the payment with:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<p><code><?php esc_html_e( 'The provided state (AMA) is not valid. Must be one of: CO-AMA, CO-ANT...', 'mcod-minimalist-checkout-for-woocommerce' ); ?></code></p>
								<p><?php esc_html_e( 'If your store uses a regional plugin that prefixes state codes and you use Stripe Link or Express Checkout, enable "Normalize State Codes for Stripe Link & Express Checkout" in the "Design & Settings" tab. When enabled, Minimalist Checkout automatically translates between unprefixed ("AMA") and prefixed ("CO-AMA") state codes before WooCommerce validates the order.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Frequently Asked Questions (FAQ)', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<ul class="mcrpd-docs-faq">
									<li>
										<strong><?php esc_html_e( 'Why are my theme header and footer hidden on the checkout page?', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong>
										<p><?php esc_html_e( 'This is the default distraction-free mode to increase conversion rates. You can re-enable your theme header and footer in the "Design & Settings" tab by checking "Use Theme Header/Footer".', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
									</li>
									<li>
										<strong><?php esc_html_e( 'If I disable the header, will I lose my tracking pixels and analytics?', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong>
										<p><?php esc_html_e( 'No! The "distraction-free" mode only hides the visual elements of the header and footer using CSS. The underlying WordPress <head> is still fully loaded. All your Facebook Pixels, Google Analytics, Tag Manager, and SEO meta tags will continue to work perfectly.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
									</li>
									<li>
										<strong><?php esc_html_e( 'How do I reorder checkout fields?', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong>
										<p><?php esc_html_e( 'Go to the "Checkout Fields" tab and drag the fields using the handle icon on the left side of each row.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
									</li>
								</ul>
							</div>
							
							<p style="margin-top: 30px; font-style: italic; color: #64748b; text-align: center;">
								<?php esc_html_e( 'This plugin is in constant evolution. If you have ideas or suggestions, do not hesitate to contact me at hola@devcristian.com. Your collaboration is highly valued!', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
							</p>
						</div>
					</div>
				</div>

				<div class="mcrpd-submit-bar">
					<?php submit_button( __( 'Save Settings', 'mcod-minimalist-checkout-for-woocommerce' ), 'primary', 'submit', false ); ?>
				</div>
			</form>

			<!-- WordPress.org Rating & Community Banner (Scoped strictly to plugin settings page) -->
			<div class="mcrpd-review-banner">
				<div class="mcrpd-review-banner-icon">
					<span class="dashicons dashicons-star-filled"></span>
				</div>
				<div class="mcrpd-review-banner-content">
					<h3><?php esc_html_e( 'Enjoying Minimalist Checkout for WooCommerce?', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
					<p>
						<?php esc_html_e( 'If this plugin helps your store look cleaner and convert better, please consider leaving a 5-star rating on WordPress.org. Your feedback motivates us to keep maintaining and adding new features for free!', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</p>
					<p class="mcrpd-developer-contact">
						<?php esc_html_e( 'Developer:', 'mcod-minimalist-checkout-for-woocommerce' ); ?> <a href="mailto:hola@devcristian.com">hola@devcristian.com</a>
					</p>
				</div>
				<div class="mcrpd-review-banner-actions">
					<a href="<?php echo esc_url( $wporg_review ); ?>" target="_blank" rel="noopener noreferrer" class="mcrpd-review-cta-btn">
						<?php esc_html_e( 'Leave a 5-Star Review ★★★★★', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
					<a href="<?php echo esc_url( $wporg_plugin ); ?>" target="_blank" rel="noopener noreferrer" class="mcrpd-review-secondary-link">
						<?php esc_html_e( 'View on WordPress.org', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
					<a href="mailto:hola@devcristian.com" class="mcrpd-review-secondary-link">
						<?php esc_html_e( 'Contact Developer', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</a>
				</div>
			</div>

		</div>
		<?php
	}

	/**
	 * Get the fields for a section, merged with any saved overrides and sorted by priority.
	 *
	 * @param string $section        Section key (billing, shipping, order).
	 * @param array  $field_overrides Saved field overrides.
	 * @return array Merged and sorted fields.
	 */
	private function get_section_fields( $section, $field_overrides ) {
		$defaults  = isset( $this->default_fields[ $section ] ) ? $this->default_fields[ $section ] : array();
		$overrides = isset( $field_overrides[ $section ] ) ? $field_overrides[ $section ] : array();
		$result    = array();
		$priority  = 10;

		// If we have saved overrides, use their order
		if ( ! empty( $overrides ) ) {
			foreach ( $overrides as $key => $override ) {
				// Only include fields that exist in our defaults
				if ( ! isset( $defaults[ $key ] ) ) {
					continue;
				}
				$result[ $key ] = array(
					'enabled'     => ! empty( $override['enabled'] ),
					'priority'    => isset( $override['priority'] ) ? (int) $override['priority'] : $priority,
					'width'       => isset( $override['width'] ) ? $override['width'] : 'form-row-wide',
					'label'       => isset( $override['label'] ) ? $override['label'] : '',
					'placeholder' => isset( $override['placeholder'] ) ? $override['placeholder'] : '',
				);
				$priority += 10;
			}

			// Append any defaults that weren't in overrides (new fields added by WooCommerce updates)
			foreach ( $defaults as $key => $def ) {
				if ( ! isset( $result[ $key ] ) ) {
					$result[ $key ] = array(
						'enabled'     => true,
						'priority'    => $priority,
						'width'       => 'form-row-wide',
						'label'       => '',
						'placeholder' => '',
					);
					$priority += 10;
				}
			}
		} else {
			// No overrides: use defaults in original WooCommerce order
			foreach ( $defaults as $key => $def ) {
				$result[ $key ] = array(
					'enabled'     => true,
					'priority'    => $priority,
					'width'       => 'form-row-wide',
					'label'       => '',
					'placeholder' => '',
				);
				$priority += 10;
			}
		}

		// Sort the final result by priority
		uasort( $result, function( $a, $b ) {
			$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
			$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
			return $a_priority <=> $b_priority;
		} );

		return $result;
	}

	// =========================================================================
	// Generic Field Renderers (used by Settings API)
	// =========================================================================

	private function get_setting( $key, $default = '' ) {
		$options = get_option( self::OPTION_NAME, array() );
		if ( in_array( $key, array( 'hide_labels', 'enable_newsletter' ), true ) && ! isset( $options[ $key ] ) ) {
			return '1';
		}
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	/**
	 * Section descriptions for Design & Settings tab.
	 */
	public function render_templates_section_description() {
		echo '<p class="mcrpd-section-intro">' . esc_html__( 'Choose which WooCommerce pages should use the distraction-free minimalist layout and customize post-purchase actions.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
	}

	public function render_general_section_description() {
		echo '<p class="mcrpd-section-intro">' . esc_html__( 'Configure accent colors, checkout form behavior, digital product optimizations, and Express Checkout compatibility.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
	}

	public function render_branding_section_description() {
		echo '<p class="mcrpd-section-intro">' . esc_html__( 'Customize the store logo or brand name displayed at the top of your Cart, Checkout, and Order Received pages.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
	}

	public function render_footer_section_description() {
		echo '<p class="mcrpd-section-intro">' . esc_html__( 'Add policy and support links displayed at the bottom of the left column. Leave any field empty to hide that link.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
	}

	public function render_checkbox_field( $args ) {
		$key           = $args['key'];
		$value         = $this->get_setting( $key, '0' );
		$highlight_box = ! empty( $args['highlight_box'] );

		if ( $highlight_box ) {
			echo '<div class="mcrpd-setting-box-yellow">';
			if ( ! empty( $args['badge'] ) || ! empty( $args['box_title'] ) ) {
				echo '<div class="mcrpd-setting-box-header">';
				if ( ! empty( $args['badge'] ) ) {
					echo '<span class="mcrpd-badge-stripe">' . esc_html( $args['badge'] ) . '</span>';
				}
				if ( ! empty( $args['box_title'] ) ) {
					echo '<span class="mcrpd-setting-box-title">' . esc_html( $args['box_title'] ) . '</span>';
				}
				echo '</div>';
			}
		}

		echo '<label class="mcrpd-checkbox-field-label mcrpd-switch-label">';
		echo '<span class="mcrpd-toggle mcrpd-settings-toggle">';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="1" ' . checked( 1, $value, false ) . ' />';
		echo '<span class="mcrpd-toggle-slider"></span>';
		echo '</span>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<span class="description">' . esc_html( $args['desc'] ) . '</span>';
		}
		echo '</label>';

		if ( $highlight_box ) {
			echo '</div>';
		}
	}

	/**
	 * Render the "Continue shopping" button customization checkbox and collapsible text/link fields.
	 */
	public function render_thankyou_button_field() {
		$enabled          = $this->get_setting( 'custom_thankyou_btn_enabled', '0' );
		$btn_text         = $this->get_setting( 'thankyou_btn_text', '' );
		$btn_url          = $this->get_setting( 'thankyou_btn_url', '' );
		$default_shop_url = function_exists( 'wc_get_page_permalink' ) && wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
		?>
		<div class="mcrpd-thankyou-btn-setting-wrap">
			<label class="mcrpd-checkbox-field-label mcrpd-switch-label">
				<span class="mcrpd-toggle mcrpd-settings-toggle">
					<input type="checkbox"
						   id="mcmchk_custom_thankyou_btn_enabled"
						   name="<?php echo esc_attr( self::OPTION_NAME . '[custom_thankyou_btn_enabled]' ); ?>"
						   value="1"
						   <?php checked( '1', (string) $enabled ); ?> />
					<span class="mcrpd-toggle-slider"></span>
				</span>
				<span class="description">
					<?php esc_html_e( 'Customize the "Continue shopping" button text and destination link on the Order Received (Thank You) page.', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				</span>
			</label>

			<div id="mcrpd-thankyou-btn-subfields" class="mcrpd-collapsible-subfields" style="<?php echo ( '1' === (string) $enabled ) ? '' : 'display: none;'; ?>">
				<div class="mcrpd-subfield-item">
					<label for="mcmchk_thankyou_btn_text" class="mcrpd-subfield-label">
						<?php esc_html_e( 'Button Text', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</label>
					<input type="text"
						   id="mcmchk_thankyou_btn_text"
						   class="regular-text"
						   name="<?php echo esc_attr( self::OPTION_NAME . '[thankyou_btn_text]' ); ?>"
						   value="<?php echo esc_attr( $btn_text ); ?>"
						   placeholder="<?php esc_attr_e( 'Continue shopping', 'mcod-minimalist-checkout-for-woocommerce' ); ?>" />
					<span class="mcrpd-subfield-hint"><?php esc_html_e( 'Leave empty to use default ("Continue shopping").', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				</div>

				<div class="mcrpd-subfield-item">
					<label for="mcmchk_thankyou_btn_url" class="mcrpd-subfield-label">
						<?php esc_html_e( 'Button Destination Link (URL)', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</label>
					<input type="url"
						   id="mcmchk_thankyou_btn_url"
						   class="regular-text"
						   name="<?php echo esc_attr( self::OPTION_NAME . '[thankyou_btn_url]' ); ?>"
						   value="<?php echo esc_attr( $btn_url ); ?>"
						   placeholder="<?php echo esc_attr( $default_shop_url ); ?>" />
					<span class="mcrpd-subfield-hint"><?php esc_html_e( 'Leave empty to redirect to your default WooCommerce Shop page.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_text_field( $args ) {
		$key         = $args['key'];
		$value       = $this->get_setting( $key, '' );
		$placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( $placeholder ) . '" />';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render a textarea settings field.
	 *
	 * @param array $args Field arguments: key, desc, rows.
	 */
	public function render_textarea_field( $args ) {
		$key   = $args['key'];
		$value = $this->get_setting( $key, '' );
		$rows  = isset( $args['rows'] ) ? absint( $args['rows'] ) : 6;
		echo '<textarea class="large-text mcrpd-code-textarea" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" rows="' . esc_attr( $rows ) . '">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render the description for the Subscriptions settings section.
	 */
	public function render_subscription_section_description() {
		echo '<p class="mcrpd-section-intro">' . esc_html__( 'Configure WooCommerce Subscriptions options and customize the recurring payment block displayed in the Checkout, Cart, and Order Received sidebars. See the "Compatibility & Help" tab for the full variable reference.', 'mcod-minimalist-checkout-for-woocommerce' ) . '</p>';
	}

	public function render_color_field( $args ) {
		$key     = $args['key'];
		$default = isset( $args['default'] ) ? $args['default'] : '';
		$value   = $this->get_setting( $key, $default );
		echo '<input type="text" class="mcrpd-color-picker" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" />';
	}

	public function render_media_field( $args ) {
		$key   = $args['key'];
		$value = $this->get_setting( $key, '' );
		echo '<div class="mcrpd-media-field-group">';
		echo '<input type="text" class="regular-text mcrpd-media-url-input" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" placeholder="https://..." />';
		echo '<button type="button" class="button mcrpd-media-upload"><span class="dashicons dashicons-upload"></span> ' . esc_html__( 'Upload Image', 'mcod-minimalist-checkout-for-woocommerce' ) . '</button>';
		echo '</div>';
		echo '<div class="mcrpd-logo-preview-wrap" style="' . ( ! empty( $value ) ? '' : 'display:none;' ) . '">';
		echo '<img src="' . esc_url( $value ) . '" alt="" class="mcrpd-logo-preview-img" />';
		echo '</div>';
	}
}
