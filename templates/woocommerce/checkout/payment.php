<?php
/**
 * Custom WooCommerce Checkout Payment Override.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<?php if ( ! wp_doing_ajax() ) : ?>
	<?php do_action( 'woocommerce_review_order_before_payment' ); ?>
<?php endif; ?>

<div id="payment" class="woocommerce-checkout-payment">
	<div class="mcrpd-checkout-payment-inner">
		
		<?php if ( WC()->cart->needs_payment() ) : ?>
			<ul class="wc_payment_methods payment_methods methods mcrpd-payment-methods-list">
				<?php
				if ( ! empty( $available_gateways ) ) {
					foreach ( $available_gateways as $gateway ) {
						wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
					}
				} else {
				$message = apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems there are no available payment methods for your location. Please contact us if you need help.', 'mcod-minimalist-checkout-for-woocommerce' ) : esc_html__( 'Please fill in your delivery details to view available payment methods.', 'mcod-minimalist-checkout-for-woocommerce' ) );
				echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info mcrpd-no-gateways-notice">' . wp_kses_post( $message ) . '</li>';
				}
				?>
			</ul>
		<?php endif; ?>

		<div class="form-row place-order mcrpd-place-order-wrapper">
			<noscript>
				<?php
				/* translators: $1 and $2: opening and closing emphasis tags respectively */
				echo wp_kses_post( sprintf( esc_html__( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the %1$sUpdate totals%2$s button before placing your order. You may be charged more than the amount indicated above if you do not do so.', 'mcod-minimalist-checkout-for-woocommerce' ), '<em>', '</em>' ) );
				?>
				<br/><button type="submit" class="button alt mcrpd-pay-now-button" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
			</noscript>

			<!-- WooCommerce Terms and Conditions (if active) -->
			<div class="mcrpd-checkout-terms-wrapper">
				<?php wc_get_template( 'checkout/terms.php' ); ?>
			</div>

			<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

			<!-- Premium Submit Button -->
			<?php 
			$order_button_text = apply_filters( 'woocommerce_order_button_text', __( 'Pay now', 'mcod-minimalist-checkout-for-woocommerce' ) );
			$order_button_html = apply_filters( 'woocommerce_order_button_html', '<button type="submit" class="button alt mcrpd-pay-now-button" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr( $order_button_text ) . '" data-value="' . esc_attr( $order_button_text ) . '">' . esc_html( $order_button_text ) . '</button>' );
			echo wp_kses_post( $order_button_html );
			?>

			<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

			<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
		</div>
	</div>
</div>

<?php if ( ! wp_doing_ajax() ) : ?>
	<?php do_action( 'woocommerce_review_order_after_payment' ); ?>
<?php endif; ?>
