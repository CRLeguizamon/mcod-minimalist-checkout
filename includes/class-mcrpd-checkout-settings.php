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
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'mcmchk_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		// General Section
		add_settings_section( 'mcmchk_general_section', __( 'General Settings', 'mcod-minimalist-checkout-for-woocommerce' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'force_template', __( 'Force Template Layout', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'force_template', 'desc' => __( 'Force the minimalist template to load on the checkout page. Use this if your theme does not allow selecting the page template.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'use_theme_hf', __( 'Use Theme Header/Footer', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'use_theme_hf', 'desc' => __( 'Use your theme\'s header and footer instead of a completely blank page.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'primary_color', __( 'Primary Color', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_color_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'primary_color', 'default' => '#1773b0' ) );
		add_settings_field( 'hide_labels', __( 'Hide Field Labels', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'hide_labels', 'desc' => __( 'Hide labels above checkout fields for a cleaner design (shop style) and use placeholders only. (Enabled by default)', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'disable_different_shipping_address', __( 'Disable "Use a different shipping address" button', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'disable_different_shipping_address', 'desc' => __( 'Always use the billing address as the shipping address and hide the shipping address selector.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'disable_coupon', __( 'Disable Coupon Field', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_general_section', array( 'key' => 'disable_coupon', 'desc' => __( 'Hide the discount code field on the checkout page.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );

		// Branding Section
		add_settings_section( 'mcmchk_branding_section', __( 'Branding', 'mcod-minimalist-checkout-for-woocommerce' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'hide_brand_header', __( 'Hide Brand Header', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'hide_brand_header', 'desc' => __( 'Check to hide the entire brand header section completely.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'brand_logo', __( 'Brand Logo URL', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_media_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_logo' ) );
		add_settings_field( 'brand_logo_width', __( 'Brand Logo Width (px)', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_logo_width', 'desc' => __( 'Set the maximum width of the logo in pixels. Default is 125.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );
		add_settings_field( 'brand_name', __( 'Brand Name', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_branding_section', array( 'key' => 'brand_name', 'desc' => __( 'Used as fallback if logo is not provided.', 'mcod-minimalist-checkout-for-woocommerce' ) ) );

		// Footer Links
		add_settings_section( 'mcmchk_footer_section', __( 'Footer Links', 'mcod-minimalist-checkout-for-woocommerce' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'link_refund', __( 'Refund Policy Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_refund' ) );
		add_settings_field( 'link_privacy', __( 'Privacy Policy Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_privacy' ) );
		add_settings_field( 'link_terms', __( 'Terms of Service Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_terms' ) );
		add_settings_field( 'link_contact', __( 'Contact Link', 'mcod-minimalist-checkout-for-woocommerce' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcmchk_footer_section', array( 'key' => 'link_contact' ) );
	}

	/**
	 * Sanitize settings.
	 */
	public function sanitize_settings( $input ) {
		$output = get_option( self::OPTION_NAME, array() );

		$output['force_template']    = isset( $input['force_template'] ) ? '1' : '0';
		$output['use_theme_hf']      = isset( $input['use_theme_hf'] ) ? '1' : '0';
		$output['hide_labels']       = isset( $input['hide_labels'] ) ? '1' : '0';
		$output['hide_brand_header'] = isset( $input['hide_brand_header'] ) ? '1' : '0';
		$output['disable_different_shipping_address'] = isset( $input['disable_different_shipping_address'] ) ? '1' : '0';
		$output['disable_coupon']    = isset( $input['disable_coupon'] ) ? '1' : '0';
		$output['primary_color']     = isset( $input['primary_color'] ) ? sanitize_hex_color( $input['primary_color'] ) : '#1773b0';
		$output['brand_logo']        = isset( $input['brand_logo'] ) ? esc_url_raw( $input['brand_logo'] ) : '';
		$output['brand_logo_width']  = isset( $input['brand_logo_width'] ) ? absint( $input['brand_logo_width'] ) : 125;
		$output['brand_name']        = isset( $input['brand_name'] ) ? sanitize_text_field( $input['brand_name'] ) : '';
		$output['link_refund']       = isset( $input['link_refund'] ) ? esc_url_raw( $input['link_refund'] ) : '';
		$output['link_privacy']      = isset( $input['link_privacy'] ) ? esc_url_raw( $input['link_privacy'] ) : '';
		$output['link_terms']        = isset( $input['link_terms'] ) ? esc_url_raw( $input['link_terms'] ) : '';
		$output['link_contact']      = isset( $input['link_contact'] ) ? esc_url_raw( $input['link_contact'] ) : '';

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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Minimalist Checkout Settings', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h1>
			
			<h2 class="nav-tab-wrapper mcrpd-main-tabs" style="margin-bottom: 20px;">
				<a href="#mcrpd-tab-design" class="nav-tab nav-tab-active" data-tab="design"><?php esc_html_e( 'Design & Settings', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
				<a href="#mcrpd-tab-fields" class="nav-tab" data-tab="fields"><?php esc_html_e( 'Checkout Fields', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
				<a href="#mcrpd-tab-docs" class="nav-tab" data-tab="docs"><?php esc_html_e( 'Compatibility & Help', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'mcmchk_settings_group' ); ?>

				<!-- Tab 1: Design & General Settings -->
				<div id="mcrpd-main-tab-design" class="mcrpd-main-tab-content active">
					<?php do_settings_sections( 'mcrpd-checkout-settings' ); ?>
				</div>

				<!-- Tab 2: Checkout Field Editor -->
				<div id="mcrpd-main-tab-fields" class="mcrpd-main-tab-content" style="display: none;">
					<div class="mcrpd-field-editor-wrap">
						<h2><?php esc_html_e( 'Checkout Fields', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>

						<!-- Tab Navigation -->
						<div class="mcrpd-field-tabs">
							<button type="button" class="mcrpd-field-tab active" data-tab="billing"><?php esc_html_e( 'Billing', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="shipping"><?php esc_html_e( 'Shipping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="order"><?php esc_html_e( 'Order', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
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
								<p><?php esc_html_e( 'Minimalist Checkout transforms the standard WooCommerce checkout page into a streamlined, clean, and conversion-focused experience. It optionally hides the theme header and footer to eliminate distractions and applies a modern, responsive two-column layout.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Checkout Page Setup', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'For Minimalist Checkout to work properly, you must configure and edit the page selected as "Checkout" in WooCommerce:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								
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

								<p><?php esc_html_e( 'Once assigned, edit the page and ensure these two requirements are met:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<ul style="list-style-type: decimal; margin-left: 20px; margin-bottom: 15px;">
									<li><strong><?php esc_html_e( 'Page Attributes:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Select the "Minimalist Checkout" template.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></li>
									<li><strong><?php esc_html_e( 'Content:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong> <?php esc_html_e( 'The page MUST contain the WooCommerce shortcode:', 'mcod-minimalist-checkout-for-woocommerce' ); ?> <code>[woocommerce_checkout]</code></li>
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

				<?php submit_button(); ?>
			</form>
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
		if ( 'hide_labels' === $key && ! isset( $options[ $key ] ) ) {
			return '1';
		}
		return isset( $options[ $key ] ) ? $options[ $key ] : $default;
	}

	public function render_checkbox_field( $args ) {
		$key   = $args['key'];
		$value = $this->get_setting( $key, '0' );
		echo '<label class="mcrpd-checkbox-field-label">';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="1" ' . checked( 1, $value, false ) . ' /> ';
		if ( ! empty( $args['desc'] ) ) {
			echo '<span class="description">' . esc_html( $args['desc'] ) . '</span>';
		}
		echo '</label>';
	}

	public function render_text_field( $args ) {
		$key   = $args['key'];
		$value = $this->get_setting( $key, '' );
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" />';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
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
		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="' . esc_attr( $value ) . '" style="margin-right:10px;" />';
		echo '<button class="button mcrpd-media-upload">' . esc_html__( 'Upload Image', 'mcod-minimalist-checkout-for-woocommerce' ) . '</button>';
	}
}
