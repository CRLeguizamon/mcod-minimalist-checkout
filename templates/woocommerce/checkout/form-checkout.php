<?php
/**
 * Custom WooCommerce Checkout Form Override.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Check if there are checkout fields to render
if ( empty( $checkout->get_checkout_fields() ) ) {
	echo esc_html__( 'No checkout fields are available.', 'mcod-minimalist-checkout' );
	return;
}

$settings = get_option( 'mcrpd_settings', array() );
$hide_brand_header = ! empty( $settings['hide_brand_header'] ) ? true : false;
$brand_name = ! empty( $settings['brand_name'] ) ? $settings['brand_name'] : get_bloginfo( 'name' );
$brand_logo = ! empty( $settings['brand_logo'] ) ? $settings['brand_logo'] : '';
$brand_logo_width = ! empty( $settings['brand_logo_width'] ) ? absint( $settings['brand_logo_width'] ) : 125;
$link_refund = ! empty( $settings['link_refund'] ) ? $settings['link_refund'] : '';
$link_privacy = ! empty( $settings['link_privacy'] ) ? $settings['link_privacy'] : '';
$link_terms = ! empty( $settings['link_terms'] ) ? $settings['link_terms'] : '';
$link_contact = ! empty( $settings['link_contact'] ) ? $settings['link_contact'] : '';
$hide_labels = ! isset( $settings['hide_labels'] ) || '1' === $settings['hide_labels'] ? ' mcrpd-hide-labels' : '';
?>

<div class="mcrpd-shop-checkout-wrap<?php echo esc_attr( $hide_labels ); ?>">

	<!-- Left Column: Fields, Shipping, Payment -->
	<div class="mcrpd-shop-checkout-left">
		
		<?php if ( ! $hide_brand_header ) : ?>
		<!-- Brand Header & Back to Cart -->
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
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="mcrpd-cart-icon-link" aria-label="<?php esc_attr_e( 'Back to cart', 'mcod-minimalist-checkout' ); ?>">
				<svg class="mcrpd-cart-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="9" cy="21" r="1"></circle>
					<circle cx="20" cy="21" r="1"></circle>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
				</svg>
			</a>
		</header>
		<?php endif; ?>

		<?php
		// Render WooCommerce notices/alerts inside the left column, above the form
		do_action( 'woocommerce_before_checkout_form', $checkout );
		?>

		<!-- Main WooCommerce Checkout Form -->
		<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

			<!-- Customer Fields (Contact Details + Billing & Shipping Address) -->
			<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
			<div id="customer_details">
				<?php do_action( 'woocommerce_checkout_billing' ); ?>
				<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			</div>
			<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

			<!-- Shipping Methods Section -->
			<div class="mcrpd-checkout-section mcrpd-shipping-methods-section">
				<h3 class="mcrpd-section-title"><?php esc_html_e( 'Shipping methods', 'mcod-minimalist-checkout' ); ?></h3>
				<div id="mcrpd-checkout-shipping-methods">
					<?php MCRPD_Checkout_Loader::get_instance()->render_shipping_methods(); ?>
				</div>
			</div>

			<!-- Payment Gateways Section -->
			<div class="mcrpd-checkout-section mcrpd-payment-section">
				<h3 class="mcrpd-section-title"><?php esc_html_e( 'Payment', 'mcod-minimalist-checkout' ); ?></h3>
				<p class="mcrpd-section-subtitle"><?php esc_html_e( 'All transactions are secure and encrypted.', 'mcod-minimalist-checkout' ); ?></p>
				
				<div id="payment" class="woocommerce-checkout-payment">
					<?php woocommerce_checkout_payment(); ?>
				</div>
			</div>

		</form>

		<!-- Footer policy links -->
		<footer class="mcrpd-checkout-footer-links">
			<?php if ( $link_refund ) : ?>
				<a href="<?php echo esc_url( $link_refund ); ?>" target="_blank"><?php esc_html_e( 'Refund policy', 'mcod-minimalist-checkout' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_privacy ) : ?>
				<a href="<?php echo esc_url( $link_privacy ); ?>" target="_blank"><?php esc_html_e( 'Privacy policy', 'mcod-minimalist-checkout' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_terms ) : ?>
				<a href="<?php echo esc_url( $link_terms ); ?>" target="_blank"><?php esc_html_e( 'Terms of service', 'mcod-minimalist-checkout' ); ?></a>
			<?php endif; ?>
			<?php if ( $link_contact ) : ?>
				<a href="<?php echo esc_url( $link_contact ); ?>" target="_blank"><?php esc_html_e( 'Contact', 'mcod-minimalist-checkout' ); ?></a>
			<?php endif; ?>
		</footer>

	</div>

	<!-- Right Column: Cart items, coupon code, totals -->
	<div class="mcrpd-shop-checkout-right">
		<div class="mcrpd-shop-checkout-right-inner">
			
			<div class="mcrpd-checkout-review-wrap">
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
				
				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>
				
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</div>

		</div>
	</div>

</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
