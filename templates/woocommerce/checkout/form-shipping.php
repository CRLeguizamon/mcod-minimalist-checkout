<?php
/**
 * Custom WooCommerce form-shipping.php Template Override.
 *
 * Compatible with woocommerce_checkout_fields filter:
 * - If shipping fields are unset, the Delivery section adapts gracefully.
 * - If billing_phone is unset, it simply won't render.
 * - If order_comments is unset, the additional fields section hides cleanly.
 * - Field priority sorting ensures reordering plugin compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
$settings                           = get_option( 'mcrpd_settings', array() );
$disable_different_shipping_address = ! empty( $settings['disable_different_shipping_address'] );
$ship_to_different_checked          = $disable_different_shipping_address ? false : (bool) apply_filters( 'woocommerce_ship_to_different_address_checked', 'shipping' === get_option( 'woocommerce_ship_to_destination' ) ? 1 : 0 );
?>
<div class="woocommerce-shipping-fields mcrpd-shipping-fields-wrap">

	<!-- Choice of shipping address (Only if shipping is required) -->
	<?php if ( true === WC()->cart->needs_shipping_address() && ! $disable_different_shipping_address ) : ?>
		<div class="mcrpd-checkout-section mcrpd-shipping-selector-section">
			<h3 class="mcrpd-section-title"><?php esc_html_e( 'Shipping address', 'mcod-minimalist-checkout' ); ?></h3>
			<div class="mcrpd-shipping-selector-group">
				<label class="mcrpd-shipping-radio-label <?php echo ! $ship_to_different_checked ? 'selected' : ''; ?>">
					<input type="radio" name="mcrpd_shipping_different" value="no" <?php checked( ! $ship_to_different_checked ); ?> />
					<span class="mcrpd-radio-text"><?php esc_html_e( 'Same as billing address', 'mcod-minimalist-checkout' ); ?></span>
				</label>
				<label class="mcrpd-shipping-radio-label <?php echo $ship_to_different_checked ? 'selected' : ''; ?>">
					<input type="radio" name="mcrpd_shipping_different" value="yes" <?php checked( $ship_to_different_checked ); ?> />
					<span class="mcrpd-radio-text"><?php esc_html_e( 'Use a different shipping address', 'mcod-minimalist-checkout' ); ?></span>
				</label>
			</div>
			
			<!-- Native checkbox (hidden, controlled by radios) -->
			<input type="checkbox" id="ship-to-different-address-checkbox" class="input-checkbox" name="ship_to_different_address" value="1" <?php checked( $ship_to_different_checked ); ?> style="display: none !important;" />
		</div>
	<?php endif; ?>

	<!-- Shipping address wrapper -->
	<div class="shipping_address" style="<?php echo $ship_to_different_checked ? 'display: block;' : 'display: none;'; ?>">
		<!-- Primary Delivery / Shipping section -->
		<div class="mcrpd-checkout-section mcrpd-delivery-section">
			<h3 class="mcrpd-section-title"><?php esc_html_e( 'Delivery', 'mcod-minimalist-checkout' ); ?></h3>

			<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

			<div class="woocommerce-shipping-fields__field-wrapper">
				<?php
				$fields = $checkout->get_checkout_fields( 'shipping' );

				// Sort fields by priority to maintain compatibility with field reordering plugins
				if ( ! empty( $fields ) && is_array( $fields ) ) {
					uasort( $fields, function( $a, $b ) {
						$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
						$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
						return $a_priority <=> $b_priority;
					} );

					// Render all shipping fields
					foreach ( $fields as $key => $field ) {
						woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
					}
				}
				?>
			</div>

			<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>
		</div>
	</div>

	<!-- Additional/Order Fields (Notes, Custom WPDesk fields, etc) -->
	<div class="woocommerce-additional-fields mcrpd-checkout-section mcrpd-additional-fields-section">
		<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

		<?php
		$order_fields = $checkout->get_checkout_fields( 'order' );
		$has_order_fields = ! empty( $order_fields ) && is_array( $order_fields );
		?>

		<?php if ( $has_order_fields || apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) ) ) : ?>
			
			<?php if ( ! WC()->cart->needs_shipping() || wc_ship_to_billing_address_only() ) : ?>
				<h3 class="mcrpd-section-title"><?php esc_html_e( 'Additional information', 'mcod-minimalist-checkout' ); ?></h3>
			<?php endif; ?>

			<?php if ( $has_order_fields ) : ?>
			<div class="woocommerce-additional-fields__field-wrapper">
				<?php
				uasort( $order_fields, function( $a, $b ) {
					$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
					$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
					return $a_priority <=> $b_priority;
				} );
				foreach ( $order_fields as $key => $field ) {
					woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
				}
				?>
			</div>
			<?php endif; ?>

		<?php endif; ?>

		<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
	</div>

</div>
