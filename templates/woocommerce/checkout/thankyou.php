<?php
/**
 * Custom WooCommerce Order Received (Thank You) Override (Minimalist Order Confirmation).
 *
 * @var WC_Order|false $order
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$settings            = get_option( 'mcmchk_settings', array() );
$hide_brand_header   = ! empty( $settings['hide_brand_header'] ) ? true : false;
$brand_name          = ! empty( $settings['brand_name'] ) ? $settings['brand_name'] : get_bloginfo( 'name' );
$brand_logo          = ! empty( $settings['brand_logo'] ) ? $settings['brand_logo'] : '';
$brand_logo_width    = ! empty( $settings['brand_logo_width'] ) ? absint( $settings['brand_logo_width'] ) : 125;
$enable_digital_mode = ! empty( $settings['enable_digital_mode'] ) ? true : false;
$link_refund         = ! empty( $settings['link_refund'] ) ? $settings['link_refund'] : '';
$link_privacy        = ! empty( $settings['link_privacy'] ) ? $settings['link_privacy'] : '';
$link_terms          = ! empty( $settings['link_terms'] ) ? $settings['link_terms'] : '';
$link_contact        = ! empty( $settings['link_contact'] ) ? $settings['link_contact'] : '';
$shop_url            = wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$custom_btn_enabled  = ! empty( $settings['custom_thankyou_btn_enabled'] ) && '1' === (string) $settings['custom_thankyou_btn_enabled'];
$continue_btn_text   = ( $custom_btn_enabled && ! empty( $settings['thankyou_btn_text'] ) )
	? $settings['thankyou_btn_text']
	: __( 'Continue shopping', 'mcod-minimalist-checkout-for-woocommerce' );
$continue_btn_url    = ( $custom_btn_enabled && ! empty( $settings['thankyou_btn_url'] ) )
	? $settings['thankyou_btn_url']
	: apply_filters( 'woocommerce_return_to_shop_redirect', $shop_url );
?>

<div class="mcrpd-shop-checkout-wrap mcrpd-thankyou-wrapper">

	<!-- Left Column: Confirmation Hero, Order Meta, Gateway Output, Customer Details, Footer Links -->
	<div class="mcrpd-shop-checkout-left mcrpd-thankyou-left">

		<?php if ( ! $hide_brand_header ) : ?>
			<header class="mcrpd-checkout-brand-header">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mcrpd-brand-logo-link">
					<?php
					if ( $brand_logo ) {
						echo '<img src="' . esc_url( $brand_logo ) . '" alt="' . esc_attr( $brand_name ) . '" class="mcrpd-brand-logo-img" style="max-width: ' . esc_attr( $brand_logo_width ) . 'px;" />';
					} elseif ( has_custom_logo() ) {
						the_custom_logo();
					} else {
						echo '<span class="mcrpd-brand-name">' . esc_html( $brand_name ) . '</span>';
					}
					?>
				</a>
			</header>
		<?php endif; ?>

		<?php if ( $order ) : ?>
			<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

			<?php if ( $order->has_status( 'failed' ) ) : ?>
				<!-- Failed Order State -->
				<div class="mcrpd-thankyou-hero mcrpd-thankyou-hero-failed">
					<div class="mcrpd-thankyou-icon-circle mcrpd-thankyou-icon-failed">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
					</div>
					<div class="mcrpd-thankyou-hero-text">
						<span class="mcrpd-thankyou-order-label">
							<?php
							/* translators: %s: order number */
							echo esc_html( sprintf( __( 'Order #%s', 'mcod-minimalist-checkout-for-woocommerce' ), $order->get_order_number() ) );
							?>
						</span>
						<h1 class="mcrpd-thankyou-heading"><?php esc_html_e( 'Payment could not be processed', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h1>
					</div>
				</div>

				<div class="mcrpd-thankyou-card mcrpd-thankyou-failed-card">
					<p class="mcrpd-thankyou-failed-message">
						<?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
					</p>
					<div class="mcrpd-thankyou-failed-actions">
						<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="mcrpd-thankyou-continue-btn">
							<?php esc_html_e( 'Pay for order', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
						</a>
						<?php if ( is_user_logged_in() ) : ?>
							<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="mcrpd-thankyou-secondary-btn">
								<?php esc_html_e( 'My account', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

			<?php else : ?>
				<!-- Confirmed Order Hero -->
				<?php
				$first_name = $order->get_billing_first_name();
				?>
				<div class="mcrpd-thankyou-hero">
					<div class="mcrpd-thankyou-icon-circle">
						<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</div>
					<div class="mcrpd-thankyou-hero-text">
						<span class="mcrpd-thankyou-order-label">
							<?php
							/* translators: %s: order number */
							echo esc_html( sprintf( __( 'Confirmation #%s', 'mcod-minimalist-checkout-for-woocommerce' ), $order->get_order_number() ) );
							?>
						</span>
						<h1 class="mcrpd-thankyou-heading">
							<?php
							if ( ! empty( $first_name ) ) {
								/* translators: %s: customer first name */
								echo esc_html( sprintf( __( 'Thank you, %s!', 'mcod-minimalist-checkout-for-woocommerce' ), $first_name ) );
							} else {
								echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mcod-minimalist-checkout-for-woocommerce' ), $order ) );
							}
							?>
						</h1>
					</div>
				</div>

				<!-- Order Overview Strip -->
				<div class="mcrpd-thankyou-overview-grid">
					<div class="mcrpd-thankyou-overview-item">
						<span class="mcrpd-thankyou-overview-label"><?php esc_html_e( 'Order number', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
						<strong class="mcrpd-thankyou-overview-value">#<?php echo esc_html( $order->get_order_number() ); ?></strong>
					</div>
					<div class="mcrpd-thankyou-overview-item">
						<span class="mcrpd-thankyou-overview-label"><?php esc_html_e( 'Date', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
						<strong class="mcrpd-thankyou-overview-value"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong>
					</div>
					<div class="mcrpd-thankyou-overview-item">
						<span class="mcrpd-thankyou-overview-label"><?php esc_html_e( 'Total', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
						<strong class="mcrpd-thankyou-overview-value"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
					</div>
					<?php if ( $order->get_payment_method_title() ) : ?>
						<div class="mcrpd-thankyou-overview-item">
							<span class="mcrpd-thankyou-overview-label"><?php esc_html_e( 'Payment method', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
							<strong class="mcrpd-thankyou-overview-value"><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
						</div>
					<?php endif; ?>
				</div>

				<!-- Order Status Update Card -->
				<div class="mcrpd-thankyou-card mcrpd-thankyou-status-card">
					<h2 class="mcrpd-thankyou-card-title"><?php esc_html_e( 'Your order is confirmed', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
					<p class="mcrpd-thankyou-card-desc">
						<?php
						if ( $enable_digital_mode ) {
							esc_html_e( 'We have received your order and sent a confirmation email with your receipt and instant digital access details.', 'mcod-minimalist-checkout-for-woocommerce' );
						} else {
							esc_html_e( 'We have accepted your order and are getting it ready. A confirmation email has been sent to your email address.', 'mcod-minimalist-checkout-for-woocommerce' );
						}
						?>
					</p>
					<?php if ( $order->get_Customer_note() ) : ?>
						<div class="mcrpd-thankyou-order-note">
							<strong><?php esc_html_e( 'Order note:', 'mcod-minimalist-checkout-for-woocommerce' ); ?></strong>
							<span><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<?php
				// Digital Downloads Card (if applicable)
				$downloads = $order->get_downloadable_items();
				$show_downloads = $order->has_downloadable_item() && $order->is_download_permitted();
				if ( $show_downloads && ! empty( $downloads ) ) :
				?>
					<div class="mcrpd-thankyou-card mcrpd-thankyou-downloads-card">
						<h2 class="mcrpd-thankyou-card-title"><?php esc_html_e( 'Downloads', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
						<div class="mcrpd-thankyou-downloads-list">
							<?php foreach ( $downloads as $download ) : ?>
								<div class="mcrpd-thankyou-download-row">
									<div class="mcrpd-thankyou-download-info">
										<strong class="mcrpd-thankyou-download-product"><?php echo esc_html( $download['product_name'] ); ?></strong>
										<?php if ( is_numeric( $download['downloads_remaining'] ) ) : ?>
											<span class="mcrpd-thankyou-download-meta">
												<?php
												/* translators: %s: downloads remaining count */
												echo esc_html( sprintf( __( '%s downloads remaining', 'mcod-minimalist-checkout-for-woocommerce' ), $download['downloads_remaining'] ) );
												?>
											</span>
										<?php endif; ?>
									</div>
									<a href="<?php echo esc_url( $download['download_url'] ); ?>" class="mcrpd-thankyou-download-btn">
										<?php echo esc_html( $download['download_name'] ); ?>
									</a>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php
				// Customer & Address Details Card
				$show_customer_details = is_user_logged_in() || $order->get_user_id() === get_current_user_id();
				$show_shipping         = ! $enable_digital_mode && ! wc_ship_to_billing_address_only() && $order->needs_shipping_address();
				if ( $show_customer_details ) :
				?>
					<div class="mcrpd-thankyou-card mcrpd-thankyou-details-card">
						<h2 class="mcrpd-thankyou-card-title"><?php esc_html_e( 'Customer information', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>

						<div class="mcrpd-thankyou-addresses-grid">
							<div class="mcrpd-thankyou-address-col">
								<h3 class="mcrpd-thankyou-subtitle"><?php esc_html_e( 'Contact information', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<?php if ( $order->get_billing_email() ) : ?>
									<p class="mcrpd-thankyou-info-line"><?php echo esc_html( $order->get_billing_email() ); ?></p>
								<?php endif; ?>
								<?php if ( $order->get_billing_phone() ) : ?>
									<p class="mcrpd-thankyou-info-line"><?php echo esc_html( $order->get_billing_phone() ); ?></p>
								<?php endif; ?>

								<h3 class="mcrpd-thankyou-subtitle mcrpd-mt-16"><?php esc_html_e( 'Billing address', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
								<address class="mcrpd-thankyou-address">
									<?php echo wp_kses_post( $order->get_formatted_billing_address( esc_html__( 'N/A', 'mcod-minimalist-checkout-for-woocommerce' ) ) ); ?>
								</address>
							</div>

							<div class="mcrpd-thankyou-address-col">
								<?php if ( $order->get_payment_method_title() ) : ?>
									<h3 class="mcrpd-thankyou-subtitle"><?php esc_html_e( 'Payment method', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
									<p class="mcrpd-thankyou-info-line">
										<?php echo wp_kses_post( $order->get_payment_method_title() ); ?>
										&mdash; <strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
									</p>
								<?php endif; ?>

								<?php if ( $show_shipping ) : ?>
									<h3 class="mcrpd-thankyou-subtitle mcrpd-mt-16"><?php esc_html_e( 'Shipping address', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
									<address class="mcrpd-thankyou-address">
										<?php echo wp_kses_post( $order->get_formatted_shipping_address( esc_html__( 'N/A', 'mcod-minimalist-checkout-for-woocommerce' ) ) ); ?>
									</address>

									<?php if ( $order->get_shipping_method() ) : ?>
										<h3 class="mcrpd-thankyou-subtitle mcrpd-mt-16"><?php esc_html_e( 'Shipping method', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
										<p class="mcrpd-thankyou-info-line"><?php echo esc_html( $order->get_shipping_method() ); ?></p>
									<?php endif; ?>
								<?php elseif ( $enable_digital_mode ) : ?>
									<h3 class="mcrpd-thankyou-subtitle mcrpd-mt-16"><?php esc_html_e( 'Delivery method', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h3>
									<p class="mcrpd-thankyou-info-line"><?php esc_html_e( 'Instant digital', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endif; ?>

			<?php endif; ?>

			<!-- Gateway Instructions & Conversion Tracking Hooks (without duplicate WooCommerce order table) -->
			<?php
			$had_order_details_table = has_action( 'woocommerce_thankyou', 'woocommerce_order_details_table' );
			if ( false !== $had_order_details_table ) {
				remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', $had_order_details_table );
			}
			$had_order_downloads = has_action( 'woocommerce_thankyou', 'woocommerce_order_downloads_table' );
			if ( false !== $had_order_downloads ) {
				remove_action( 'woocommerce_thankyou', 'woocommerce_order_downloads_table', $had_order_downloads );
			}

			ob_start();
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
			do_action( 'woocommerce_thankyou', $order->get_id() );
			$gateway_hooks_html = trim( ob_get_clean() );

			if ( false !== $had_order_details_table ) {
				add_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', $had_order_details_table );
			}
			if ( false !== $had_order_downloads ) {
				add_action( 'woocommerce_thankyou', 'woocommerce_order_downloads_table', $had_order_downloads );
			}

			if ( ! empty( $gateway_hooks_html ) ) :
			?>
				<div class="mcrpd-thankyou-card mcrpd-thankyou-gateway-hooks">
					<?php echo $gateway_hooks_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>

			<!-- Bottom Actions -->
			<div class="mcrpd-thankyou-bottom-actions">
				<?php if ( $link_contact ) : ?>
					<p class="mcrpd-thankyou-help-text">
						<?php esc_html_e( 'Need help?', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
						<a href="<?php echo esc_url( $link_contact ); ?>" target="_blank"><?php esc_html_e( 'Contact us', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
					</p>
				<?php else : ?>
					<span></span>
				<?php endif; ?>
				<a href="<?php echo esc_url( $continue_btn_url ); ?>" class="mcrpd-thankyou-continue-btn">
					<?php echo esc_html( $continue_btn_text ); ?>
				</a>
			</div>

		<?php else : ?>
			<!-- Fallback when no order object is available -->
			<div class="mcrpd-thankyou-card">
				<h1 class="mcrpd-thankyou-heading">
					<?php echo wp_kses_post( apply_filters( 'woocommerce_thankyou_order_received_text', esc_html__( 'Thank you. Your order has been received.', 'mcod-minimalist-checkout-for-woocommerce' ), null ) ); ?>
				</h1>
				<div class="mcrpd-thankyou-bottom-actions" style="margin-top: 20px;">
					<a href="<?php echo esc_url( $continue_btn_url ); ?>" class="mcrpd-thankyou-continue-btn">
						<?php echo esc_html( $continue_btn_text ); ?>
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- Footer policy links -->
		<footer class="mcrpd-checkout-footer-links">
			<?php if ( $link_refund ) : ?>
				<a href="<?php echo esc_url( $link_refund ); ?>" target="_blank"><?php esc_html_e( 'Refund policy', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_privacy ) : ?>
				<a href="<?php echo esc_url( $link_privacy ); ?>" target="_blank"><?php esc_html_e( 'Privacy policy', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_terms ) : ?>
				<a href="<?php echo esc_url( $link_terms ); ?>" target="_blank"><?php esc_html_e( 'Terms of service', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_contact ) : ?>
				<a href="<?php echo esc_url( $link_contact ); ?>" target="_blank"><?php esc_html_e( 'Contact', 'mcod-minimalist-checkout-for-woocommerce' ); ?></a>
			<?php endif; ?>
		</footer>

	</div>

	<?php if ( $order ) : ?>
		<!-- Right Column: Purchased Items & Order Totals Summary -->
		<div class="mcrpd-shop-checkout-right mcrpd-thankyou-right">
			<div class="mcrpd-shop-checkout-right-inner">

				<div class="mcrpd-checkout-review-inner">
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
						<span class="mcrpd-mobile-summary-toggle-total"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
					</button>

					<div id="mcrpd-OrderSummaryCollapsible" class="mcrpd-order-summary-collapsible">
						<?php do_action( 'mcmchk_before_sidebar_product_list' ); ?>

						<!-- Purchased Items List -->
						<ul class="mcrpd-sidebar-product-list">
							<?php
							$order_items = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
							foreach ( $order_items as $item_id => $item ) :
								if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) {
									continue;
								}
								$product   = $item->get_product();
								$thumbnail = $product ? $product->get_image( array( 64, 64 ) ) : wc_placeholder_img( array( 64, 64 ) );
								?>
								<li class="mcrpd-sidebar-product-item">
									<div class="mcrpd-product-thumbnail-wrap">
										<div class="mcrpd-product-thumbnail">
											<?php echo wp_kses_post( $thumbnail ); ?>
										</div>
										<span class="mcrpd-product-quantity-badge"><?php echo esc_html( $item->get_quantity() ); ?></span>
									</div>
									<div class="mcrpd-product-info">
										<span class="mcrpd-product-name"><?php echo wp_kses_post( apply_filters( 'woocommerce_order_item_name', $item->get_name(), $item, false ) ); ?></span>
										<div class="mcrpd-product-variation">
											<?php
											wc_display_item_meta( $item );
											do_action( 'woocommerce_order_item_meta_start', $item_id, $item, $order, false );
											do_action( 'woocommerce_order_item_meta_end', $item_id, $item, $order, false );
											?>
										</div>
									</div>
									<div class="mcrpd-product-total-price">
										<?php echo wp_kses_post( MCMCHK_Checkout_Loader::get_clean_order_item_subtotal( $order, $item ) ); ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>

						<hr class="mcrpd-sidebar-divider" />

						<?php do_action( 'mcmchk_before_sidebar_totals' ); ?>

						<!-- Order Totals Rows -->
						<div class="mcrpd-sidebar-totals">
							<?php
							$item_totals = $order->get_order_item_totals();
							if ( $enable_digital_mode && isset( $item_totals['shipping'] ) ) {
								$item_totals['shipping']['value'] = esc_html__( 'Instant digital', 'mcod-minimalist-checkout-for-woocommerce' );
							}
							foreach ( $item_totals as $key => $total ) :
								$is_order_total = ( 'order_total' === $key );
								if ( $is_order_total ) :
									?>
									<hr class="mcrpd-sidebar-divider" />
									<div class="mcrpd-totals-row mcrpd-total-row">
										<span class="mcrpd-total-label"><?php echo esc_html( wp_strip_all_tags( rtrim( $total['label'], ':' ) ) ); ?></span>
										<span class="mcrpd-total-value">
											<span class="mcrpd-currency-code"><?php echo esc_html( $order->get_currency() ); ?></span>
											<?php echo wp_kses_post( $total['value'] ); ?>
										</span>
									</div>
								<?php else : ?>
									<div class="mcrpd-totals-row mcrpd-total-row-<?php echo esc_attr( sanitize_html_class( $key ) ); ?>">
										<span class="mcrpd-totals-label"><?php echo esc_html( wp_strip_all_tags( rtrim( $total['label'], ':' ) ) ); ?></span>
										<span class="mcrpd-totals-value"><?php echo wp_kses_post( $total['value'] ); ?></span>
									</div>
									<?php
								endif;
							endforeach;

							if ( class_exists( 'WC_Subscriptions' ) ) {
								MCMCHK_Checkout_Loader::get_instance()->mcmchk_display_order_recurring_totals( $order );
							}
							?>
						</div>

						<?php do_action( 'mcmchk_after_sidebar_totals' ); ?>
					</div>
				</div>

			</div>
		</div>
	<?php endif; ?>

</div>
