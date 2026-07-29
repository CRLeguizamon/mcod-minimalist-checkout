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

class MCRPD_Checkout_Settings {

	/**
	 * Settings key
	 */
	const OPTION_NAME = 'mcrpd_settings';

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
			__( 'Minimalist Checkout', 'mcod-minimalist-checkout' ),
			__( 'Minimalist Checkout', 'mcod-minimalist-checkout' ),
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
		wp_enqueue_style( 'mcrpd-admin-css', MCRPD_ASSETS . 'css/mcrpd-admin.css', array(), MCRPD_VERSION );
		wp_enqueue_script( 'mcrpd-admin-js', MCRPD_ASSETS . 'js/mcrpd-admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-color-picker' ), MCRPD_VERSION, true );
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'mcrpd_settings_group', self::OPTION_NAME, array( $this, 'sanitize_settings' ) );

		// General Section
		add_settings_section( 'mcrpd_general_section', __( 'General Settings', 'mcod-minimalist-checkout' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'use_theme_hf', __( 'Use Theme Header/Footer', 'mcod-minimalist-checkout' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcrpd_general_section', array( 'key' => 'use_theme_hf', 'desc' => __( 'Use your theme\'s header and footer instead of a completely blank page.', 'mcod-minimalist-checkout' ) ) );
		add_settings_field( 'primary_color', __( 'Primary Color', 'mcod-minimalist-checkout' ), array( $this, 'render_color_field' ), 'mcrpd-checkout-settings', 'mcrpd_general_section', array( 'key' => 'primary_color', 'default' => '#1773b0' ) );
		add_settings_field( 'hide_labels', __( 'Hide Field Labels', 'mcod-minimalist-checkout' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcrpd_general_section', array( 'key' => 'hide_labels', 'desc' => __( 'Hide labels above checkout fields for a cleaner design (shop style) and use placeholders only. (Enabled by default)', 'mcod-minimalist-checkout' ) ) );
		add_settings_field( 'disable_different_shipping_address', __( 'Disable "Use a different shipping address" button', 'mcod-minimalist-checkout' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcrpd_general_section', array( 'key' => 'disable_different_shipping_address', 'desc' => __( 'Always use the billing address as the shipping address and hide the shipping address selector.', 'mcod-minimalist-checkout' ) ) );

		// Branding Section
		add_settings_section( 'mcrpd_branding_section', __( 'Branding', 'mcod-minimalist-checkout' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'hide_brand_header', __( 'Hide Brand Header', 'mcod-minimalist-checkout' ), array( $this, 'render_checkbox_field' ), 'mcrpd-checkout-settings', 'mcrpd_branding_section', array( 'key' => 'hide_brand_header', 'desc' => __( 'Check to hide the entire brand header section completely.', 'mcod-minimalist-checkout' ) ) );
		add_settings_field( 'brand_logo', __( 'Brand Logo URL', 'mcod-minimalist-checkout' ), array( $this, 'render_media_field' ), 'mcrpd-checkout-settings', 'mcrpd_branding_section', array( 'key' => 'brand_logo' ) );
		add_settings_field( 'brand_logo_width', __( 'Brand Logo Width (px)', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_branding_section', array( 'key' => 'brand_logo_width', 'desc' => __( 'Set the maximum width of the logo in pixels. Default is 125.', 'mcod-minimalist-checkout' ) ) );
		add_settings_field( 'brand_name', __( 'Brand Name', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_branding_section', array( 'key' => 'brand_name', 'desc' => __( 'Used as fallback if logo is not provided.', 'mcod-minimalist-checkout' ) ) );

		// Footer Links
		add_settings_section( 'mcrpd_footer_section', __( 'Footer Links', 'mcod-minimalist-checkout' ), null, 'mcrpd-checkout-settings' );
		add_settings_field( 'link_refund', __( 'Refund Policy Link', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_footer_section', array( 'key' => 'link_refund' ) );
		add_settings_field( 'link_privacy', __( 'Privacy Policy Link', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_footer_section', array( 'key' => 'link_privacy' ) );
		add_settings_field( 'link_terms', __( 'Terms of Service Link', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_footer_section', array( 'key' => 'link_terms' ) );
		add_settings_field( 'link_contact', __( 'Contact Link', 'mcod-minimalist-checkout' ), array( $this, 'render_text_field' ), 'mcrpd-checkout-settings', 'mcrpd_footer_section', array( 'key' => 'link_contact' ) );
	}

	/**
	 * Sanitize settings.
	 */
	public function sanitize_settings( $input ) {
		$output = get_option( self::OPTION_NAME, array() );

		$output['use_theme_hf']      = isset( $input['use_theme_hf'] ) ? '1' : '0';
		$output['hide_labels']       = isset( $input['hide_labels'] ) ? '1' : '0';
		$output['hide_brand_header'] = isset( $input['hide_brand_header'] ) ? '1' : '0';
		$output['disable_different_shipping_address'] = isset( $input['disable_different_shipping_address'] ) ? '1' : '0';
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
			<h1><?php esc_html_e( 'Minimalist Checkout Settings', 'mcod-minimalist-checkout' ); ?></h1>
			
			<h2 class="nav-tab-wrapper mcrpd-main-tabs" style="margin-bottom: 20px;">
				<a href="#mcrpd-tab-design" class="nav-tab nav-tab-active" data-tab="design"><?php esc_html_e( 'Design & Settings', 'mcod-minimalist-checkout' ); ?></a>
				<a href="#mcrpd-tab-fields" class="nav-tab" data-tab="fields"><?php esc_html_e( 'Checkout Fields', 'mcod-minimalist-checkout' ); ?></a>
				<a href="#mcrpd-tab-docs" class="nav-tab" data-tab="docs"><?php esc_html_e( 'Compatibilidad & Ayuda', 'mcod-minimalist-checkout' ); ?></a>
			</h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'mcrpd_settings_group' ); ?>

				<!-- Tab 1: Design & General Settings -->
				<div id="mcrpd-main-tab-design" class="mcrpd-main-tab-content active">
					<?php do_settings_sections( 'mcrpd-checkout-settings' ); ?>
				</div>

				<!-- Tab 2: Checkout Field Editor -->
				<div id="mcrpd-main-tab-fields" class="mcrpd-main-tab-content" style="display: none;">
					<div class="mcrpd-field-editor-wrap">
						<h2><?php esc_html_e( '📋 Checkout Fields', 'mcod-minimalist-checkout' ); ?></h2>

						<!-- Tab Navigation -->
						<div class="mcrpd-field-tabs">
							<button type="button" class="mcrpd-field-tab active" data-tab="billing"><?php esc_html_e( 'Billing', 'mcod-minimalist-checkout' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="shipping"><?php esc_html_e( 'Shipping', 'mcod-minimalist-checkout' ); ?></button>
							<button type="button" class="mcrpd-field-tab" data-tab="order"><?php esc_html_e( 'Order', 'mcod-minimalist-checkout' ); ?></button>
						</div>

						<!-- Tab Contents -->
						<?php
						$sections = array(
							'billing'  => __( 'Billing', 'mcod-minimalist-checkout' ),
							'shipping' => __( 'Shipping', 'mcod-minimalist-checkout' ),
							'order'    => __( 'Order', 'mcod-minimalist-checkout' ),
						);

						foreach ( $sections as $section_key => $section_label ) :
							$is_first = ( 'billing' === $section_key );
							$fields   = $this->get_section_fields( $section_key, $field_overrides );
						?>
						<div id="mcrpd-tab-<?php echo esc_attr( $section_key ); ?>"
							 class="mcrpd-field-tab-content <?php echo $is_first ? 'active' : ''; ?>"
							 data-section="<?php echo esc_attr( $section_key ); ?>">
							<table class="mcrpd-field-list">
								<thead>
									<tr>
										<th></th>
										<th><?php esc_html_e( 'Field', 'mcod-minimalist-checkout' ); ?></th>
										<th><?php esc_html_e( 'Active', 'mcod-minimalist-checkout' ); ?></th>
										<th><?php esc_html_e( 'Width', 'mcod-minimalist-checkout' ); ?></th>
										<th><?php esc_html_e( 'Label', 'mcod-minimalist-checkout' ); ?></th>
										<th><?php esc_html_e( 'Placeholder', 'mcod-minimalist-checkout' ); ?></th>
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
										class="<?php echo ! $is_enabled ? 'mcrpd-field-disabled' : ''; ?>">
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
													<span class="mcrpd-lock-icon dashicons dashicons-lock" title="<?php esc_attr_e( 'Required WooCommerce field', 'mcod-minimalist-checkout' ); ?>"></span>
												<?php endif; ?>
											</div>
										</td>
										<td>
											<select class="mcrpd-field-width-select" <?php disabled( ! $is_enabled ); ?>>
												<option value="form-row-wide" <?php selected( $width, 'form-row-wide' ); ?>><?php esc_html_e( '100%', 'mcod-minimalist-checkout' ); ?></option>
												<option value="form-row-first" <?php selected( $width, 'form-row-first' ); ?>><?php esc_html_e( '50% (Left)', 'mcod-minimalist-checkout' ); ?></option>
												<option value="form-row-last" <?php selected( $width, 'form-row-last' ); ?>><?php esc_html_e( '50% (Right)', 'mcod-minimalist-checkout' ); ?></option>
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
						<textarea id="mcrpd_field_overrides_json"
								  name="<?php echo esc_attr( self::OPTION_NAME . '[field_overrides_json]' ); ?>"
								  style="display:none;"></textarea>
						<input type="hidden"
							   id="mcrpd_reset_fields_flag"
							   name="<?php echo esc_attr( self::OPTION_NAME . '[reset_fields_flag]' ); ?>"
							   value="0" />

						<!-- Actions footer -->
						<div class="mcrpd-field-editor-actions">
							<button type="button" class="mcrpd-reset-fields-btn">
								<?php esc_html_e( '🔄 Restore Default Values', 'mcod-minimalist-checkout' ); ?>
							</button>
							<span class="mcrpd-editor-note">
								<?php esc_html_e( 'Drag fields to reorder. Changes will apply after saving.', 'mcod-minimalist-checkout' ); ?>
							</span>
						</div>
					</div>
				</div>

				<!-- Tab 3: Documentation -->
				<div id="mcrpd-main-tab-docs" class="mcrpd-main-tab-content" style="display: none;">
					<div class="mcrpd-docs-wrap">
						<h2><?php esc_html_e( '📖 Documentación y Compatibilidad', 'mcod-minimalist-checkout' ); ?></h2>
						
						<div class="mcrpd-docs-content">
							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( '¿Qué hace este plugin?', 'mcod-minimalist-checkout' ); ?></h3>
								<p><?php esc_html_e( 'Minimalist Checkout transforma la página de pago estándar de WooCommerce en una experiencia simplificada, limpia y enfocada en la conversión. Oculta el encabezado y pie de página del tema (opcionalmente) para eliminar distracciones, y aplica un diseño de dos columnas moderno y responsivo.', 'mcod-minimalist-checkout' ); ?></p>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Hacer compatibles campos de otros plugins', 'mcod-minimalist-checkout' ); ?></h3>
								<p><?php esc_html_e( 'Si utilizas plugins externos (como editores de campos personalizados, plugins de RUT/Cédula, etc.) para añadir campos extra al checkout, es posible que no tomen el diseño automáticamente. Puedes solucionarlo añadiendo las siguientes clases CSS a esos campos desde la configuración de tu otro plugin:', 'mcod-minimalist-checkout' ); ?></p>
								
								<table class="mcrpd-docs-table">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Elemento', 'mcod-minimalist-checkout' ); ?></th>
											<th><?php esc_html_e( 'Clase CSS a usar', 'mcod-minimalist-checkout' ); ?></th>
											<th><?php esc_html_e( 'Descripción', 'mcod-minimalist-checkout' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><strong><?php esc_html_e( 'Contenedor del Campo (Layout)', 'mcod-minimalist-checkout' ); ?></strong></td>
											<td><code>mcrpd-field</code></td>
											<td><?php esc_html_e( 'Aplica el espaciado correcto para que el campo encaje en el diseño.', 'mcod-minimalist-checkout' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Contenedor 50% de ancho', 'mcod-minimalist-checkout' ); ?></strong></td>
											<td><code>mcrpd-field-half</code></td>
											<td><?php esc_html_e( 'Para colocar dos campos uno al lado del otro.', 'mcod-minimalist-checkout' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Caja de Texto (Input Text)', 'mcod-minimalist-checkout' ); ?></strong></td>
											<td><code>mcrpd-input-text</code></td>
											<td><?php esc_html_e( 'Estiliza la caja para que luzca igual que las del plugin.', 'mcod-minimalist-checkout' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Caja Numérica (Input Number)', 'mcod-minimalist-checkout' ); ?></strong></td>
											<td><code>mcrpd-input-number</code></td>
											<td><?php esc_html_e( 'Ideal para campos de cédula, teléfono o códigos.', 'mcod-minimalist-checkout' ); ?></td>
										</tr>
										<tr>
											<td><strong><?php esc_html_e( 'Menú Desplegable (Select)', 'mcod-minimalist-checkout' ); ?></strong></td>
											<td><code>mcrpd-input-select</code></td>
											<td><?php esc_html_e( 'Aplica la flecha y bordes minimalistas a las listas.', 'mcod-minimalist-checkout' ); ?></td>
										</tr>
									</tbody>
								</table>

								<h4><?php esc_html_e( 'Ejemplo práctico:', 'mcod-minimalist-checkout' ); ?></h4>
								<p><?php esc_html_e( 'Si tu plugin te pide "Clases CSS para el contenedor", pon: ', 'mcod-minimalist-checkout' ); ?> <code>mcrpd-field mcrpd-field-half</code></p>
								<p><?php esc_html_e( 'Si te pide "Clases CSS para el input", pon: ', 'mcod-minimalist-checkout' ); ?> <code>mcrpd-input-text</code></p>
								
								<div class="mcrpd-docs-notice">
									<p><strong><?php esc_html_e( 'Nota automática:', 'mcod-minimalist-checkout' ); ?></strong> <?php esc_html_e( 'Cualquier campo que utilice la clase nativa', 'mcod-minimalist-checkout' ); ?> <code>input-text</code> <?php esc_html_e( 'de WooCommerce (que la mayoría de plugins añade por defecto) heredará los estilos visuales de forma automática.', 'mcod-minimalist-checkout' ); ?></p>
								</div>
							</div>

							<div class="mcrpd-docs-card">
								<h3><?php esc_html_e( 'Preguntas Frecuentes (FAQ)', 'mcod-minimalist-checkout' ); ?></h3>
								<ul class="mcrpd-docs-faq">
									<li>
										<strong><?php esc_html_e( '¿Por qué no veo la cabecera y pie de página de mi web en el checkout?', 'mcod-minimalist-checkout' ); ?></strong>
										<p><?php esc_html_e( 'Es el comportamiento por defecto para crear un entorno sin distracciones. Puedes reactivarlos en la pestaña "Design & Settings" marcando la opción "Use Theme Header/Footer".', 'mcod-minimalist-checkout' ); ?></p>
									</li>
									<li>
										<strong><?php esc_html_e( '¿Cómo cambio el orden de los campos?', 'mcod-minimalist-checkout' ); ?></strong>
										<p><?php esc_html_e( 'Ve a la pestaña "Checkout Fields" y arrastra los campos usando el ícono de las tres rayas a la izquierda de cada fila.', 'mcod-minimalist-checkout' ); ?></p>
									</li>
								</ul>
							</div>
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
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME . '[' . $key . ']' ) . '" value="1" ' . checked( 1, $value, false ) . ' />';
		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . esc_html( $args['desc'] ) . '</p>';
		}
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
		echo '<button class="button mcrpd-media-upload">' . esc_html__( 'Upload Image', 'mcod-minimalist-checkout' ) . '</button>';
	}
}
