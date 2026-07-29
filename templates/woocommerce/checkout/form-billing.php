<?php
/**
 * Custom WooCommerce form-billing.php Template Override.
 *
 * Compatible with woocommerce_checkout_fields filter:
 * - If billing_email is unset, the Contact section adapts gracefully.
 * - If billing fields are unset, the hidden billing wrapper stays empty.
 * - Field priority sorting ensures reordering plugin compatibility.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$all_billing_fields = $checkout->get_checkout_fields( 'billing' );
$has_email          = isset( $all_billing_fields['billing_email'] );
?>
<div class="woocommerce-billing-fields mcrpd-billing-fields-wrap">

	<?php if ( $has_email ) : ?>
	<!-- Contact section (Only shown if billing_email exists) -->
	<div class="mcrpd-checkout-section mcrpd-contact-section">
		<div class="mcrpd-section-header">
			<h3 class="mcrpd-section-title"><?php esc_html_e( 'Contact', 'mcod-minimalist-checkout' ); ?></h3>
			<?php if ( ! is_user_logged_in() ) : ?>
				<div class="mcrpd-login-wrap">
					<span><?php esc_html_e( 'Already have an account?', 'mcod-minimalist-checkout' ); ?></span>
					<a href="#" class="mcrpd-login-link" id="mcrpd-login-toggle"><?php esc_html_e( 'Log in', 'mcod-minimalist-checkout' ); ?></a>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( ! is_user_logged_in() ) : ?>
		<!-- Inline AJAX Login Form (toggled via JS) -->
		<div class="mcrpd-inline-login-form" id="mcrpd-inline-login" style="display: none;">
			<div class="mcrpd-login-fields-row">
				<div class="mcrpd-login-field">
					<input type="text" id="mcrpd-login-username" placeholder="<?php esc_attr_e( 'Username or email', 'mcod-minimalist-checkout' ); ?>" autocomplete="username" />
				</div>
				<div class="mcrpd-login-field">
					<input type="password" id="mcrpd-login-password" placeholder="<?php esc_attr_e( 'Password', 'mcod-minimalist-checkout' ); ?>" autocomplete="current-password" />
				</div>
			</div>
			<div class="mcrpd-login-actions">
				<button type="button" id="mcrpd-login-submit" class="mcrpd-login-button"><?php esc_html_e( 'Log in', 'mcod-minimalist-checkout' ); ?></button>
				<a href="<?php echo esc_url( wp_lostpassword_url( wc_get_checkout_url() ) ); ?>" class="mcrpd-forgot-password-link"><?php esc_html_e( 'Forgot password?', 'mcod-minimalist-checkout' ); ?></a>
			</div>
			<div id="mcrpd-login-message" class="mcrpd-login-message"></div>
		</div>
		<?php endif; ?>
		
		<div class="mcrpd-contact-fields">
			<?php woocommerce_form_field( 'billing_email', $all_billing_fields['billing_email'], $checkout->get_value( 'billing_email' ) ); ?>
			<div class="mcrpd-checkbox-wrapper mcrpd-newsletter-wrap">
				<label class="mcrpd-checkbox-label">
					<input type="checkbox" id="mcrpd_newsletter" name="mcrpd_newsletter" value="1" checked />
					<span class="mcrpd-checkbox-text"><?php esc_html_e( 'Email me with news and offers', 'mcod-minimalist-checkout' ); ?></span>
				</label>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<!-- Billing Fields wrapper -->
	<div class="mcrpd-checkout-section mcrpd-billing-section">
		
		<h3 class="mcrpd-section-title"><?php esc_html_e( 'Billing address', 'mcod-minimalist-checkout' ); ?></h3>

		<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

		<div class="woocommerce-billing-fields__field-wrapper">
			<?php
			$fields = $all_billing_fields;
			
			// Sort fields by priority to maintain compatibility with field reordering plugins
			if ( ! empty( $fields ) && is_array( $fields ) ) {
				uasort( $fields, function( $a, $b ) {
					$a_priority = isset( $a['priority'] ) ? (int) $a['priority'] : 100;
					$b_priority = isset( $b['priority'] ) ? (int) $b['priority'] : 100;
					return $a_priority <=> $b_priority;
				} );
			}

			// Render billing fields except email (rendered separately)
			foreach ( $fields as $key => $field ) {
				if ( 'billing_email' === $key ) {
					continue;
				}
				woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
			}
			?>
		</div>

		<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>

	</div>

</div>
