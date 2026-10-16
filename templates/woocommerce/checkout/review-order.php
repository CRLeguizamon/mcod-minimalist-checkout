<?php
/**
 * Custom WooCommerce Checkout Order Review Override.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="mcrpd-checkout-review-inner woocommerce-checkout-review-order-table">

	<!-- Mobile Order Summary Toggle Bar (visible on <= 1000px) -->
	<button type="button" class="mcrpd-mobile-summary-toggle" aria-expanded="false" aria-controls="mcrpd-OrderSummaryCollapsible">
		<span class="mcrpd-mobile-summary-toggle-left">
			<svg class="mcrpd-summary-cart-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
				<circle cx="9" cy="21" r="1"></circle>
				<circle cx="20" cy="21" r="1"></circle>
				<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
			</svg>
			<span class="mcrpd-mobile-summary-toggle-label"><?php esc_html_e( 'Show order summary', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
			<svg class="mcrpd-summary-chevron-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
				<polyline points="6 9 12 15 18 9"></polyline>
			</svg>
		</span>
		<span class="mcrpd-mobile-summary-toggle-total"><?php wc_cart_totals_order_total_html(); ?></span>
	</button>

	<div id="mcrpd-OrderSummaryCollapsible" class="mcrpd-order-summary-collapsible">

	<!-- Cart Products List -->
	<?php do_action( 'mcmchk_before_sidebar_product_list' ); ?>
	<ul class="mcrpd-sidebar-product-list">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

			if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				$thumbnail = $_product->get_image( array( 64, 64 ) );
				?>
				<li class="mcrpd-sidebar-product-item">
					<div class="mcrpd-product-thumbnail-wrap">
						<div class="mcrpd-product-thumbnail">
							<?php echo wp_kses_post( $thumbnail ); ?>
						</div>
						<span class="mcrpd-product-quantity-badge"><?php echo esc_html( $cart_item['quantity'] ); ?></span>
					</div>
					<div class="mcrpd-product-info">
						<span class="mcrpd-product-name"><?php echo esc_html( $_product->get_name() ); ?></span>
						<?php echo wp_kses_post( wc_get_formatted_cart_item_data( $cart_item ) ); ?>
					</div>
					<div class="mcrpd-product-total-price">
						<?php echo wp_kses_post( MCMCHK_Checkout_Loader::get_clean_cart_item_subtotal( $_product, $cart_item, $cart_item_key ) ); ?>
					</div>
				</li>
				<?php
			}
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</ul>
	
	<small class="mcrpd-return-to-cart-wrap">
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="mcrpd-return-to-cart-link">
			<?php esc_html_e( 'Return to cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
		</a>
	</small>

	<!-- Coupon / Discount Section -->
	<?php 
	$mcmchk_settings = get_option( 'mcmchk_settings', array() );
	$disable_coupon = isset( $mcmchk_settings['disable_coupon'] ) ? $mcmchk_settings['disable_coupon'] : '0';
	
	if ( wc_coupons_enabled() && '1' !== $disable_coupon ) : 
	?>
		<div class="mcrpd-sidebar-coupon-wrap">
			<input type="text" id="mcrpd-coupon-input" class="mcrpd-coupon-input" placeholder="<?php esc_attr_e( 'Discount code', 'mcod-minimalist-checkout-for-woocommerce' ); ?>" aria-label="<?php esc_attr_e( 'Discount code', 'mcod-minimalist-checkout-for-woocommerce' ); ?>" />
			<button type="button" id="mcrpd-coupon-submit" class="mcrpd-coupon-button"><?php esc_html_e( 'Apply', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
		</div>
		<div id="mcrpd-coupon-msg" class="mcrpd-coupon-message"></div>
	<?php endif; ?>

	<hr class="mcrpd-sidebar-divider" />

	<!-- Totals Rows -->
	<?php do_action( 'mcmchk_before_sidebar_totals' ); ?>
	<div class="mcrpd-sidebar-totals">
		
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
						<a href="#" class="mcrpd-remove-coupon-btn" data-coupon="<?php echo esc_attr( $code ); ?>" aria-label="<?php esc_attr_e( 'Remove coupon', 'mcod-minimalist-checkout-for-woocommerce' ); ?>">×</a>
					</span>
				</span>
				<span class="mcrpd-totals-value">-<?php wc_cart_totals_coupon_html( $coupon ); ?></span>
			</div>
		<?php endforeach; ?>

		<!-- Shipping Cost (Simplified representation) -->
		<div class="mcrpd-totals-row">
			<span class="mcrpd-totals-label"><?php esc_html_e( 'Shipping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
			<span class="mcrpd-totals-value">
				<?php
				$digital_mode = isset( $mcmchk_settings['enable_digital_mode'] ) ? $mcmchk_settings['enable_digital_mode'] : '0';
				
				if ( '1' === $digital_mode ) {
					esc_html_e( 'Instant digital', 'mcod-minimalist-checkout-for-woocommerce' );
				} else {
					if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
						$packages       = WC()->shipping()->get_packages();
						$shipping_total = 0;
						$shipping_label = '';
						$incl_tax       = WC()->cart->display_prices_including_tax();
						
						foreach ( $packages as $i => $package ) {
							$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
							if ( $chosen_method && isset( $package['rates'][ $chosen_method ] ) ) {
								$rate            = $package['rates'][ $chosen_method ];
								$shipping_total += (float) $rate->cost;
								if ( $incl_tax && method_exists( $rate, 'get_shipping_tax' ) ) {
									$shipping_total += (float) $rate->get_shipping_tax();
								}
								$shipping_label = $rate->label;
							}
						}
						
						if ( $shipping_total > 0 ) {
							echo wp_kses_post( wc_price( $shipping_total ) );
						} else {
							if ( $shipping_label ) {
								echo esc_html( $shipping_label );
							} else {
								esc_html_e( 'Free', 'mcod-minimalist-checkout-for-woocommerce' );
							}
						}
					} else {
						esc_html_e( 'Free', 'mcod-minimalist-checkout-for-woocommerce' );
					}
				}
				?>
			</span>
		</div>

		<!-- Cart Fees (if any) -->
		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<div class="mcrpd-totals-row mcrpd-fee-row">
				<span class="mcrpd-totals-label"><?php echo esc_html( $fee->name ); ?></span>
				<span class="mcrpd-totals-value"><?php wc_cart_totals_fee_html( $fee ); ?></span>
			</div>
		<?php endforeach; ?>

		<!-- Taxes (if enabled) -->
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

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>

		<!-- Final Order Total -->
		<div class="mcrpd-totals-row mcrpd-total-row">
			<span class="mcrpd-total-label"><?php esc_html_e( 'Total', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
			<span class="mcrpd-total-value">
				<span class="mcrpd-currency-code"><?php echo esc_html( get_woocommerce_currency() ); ?></span>
				<?php wc_cart_totals_order_total_html(); ?>
			</span>
		</div>

		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>

	</div>
	<?php do_action( 'mcmchk_after_sidebar_totals' ); ?>

	</div><!-- /#mcrpd-OrderSummaryCollapsible -->

</div>
