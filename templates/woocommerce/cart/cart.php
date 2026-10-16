<?php
/**
 * Custom WooCommerce Cart Page Override (Minimalist Cart).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$settings          = get_option( 'mcmchk_settings', array() );
$hide_brand_header = ! empty( $settings['hide_brand_header'] ) ? true : false;
$brand_name        = ! empty( $settings['brand_name'] ) ? $settings['brand_name'] : get_bloginfo( 'name' );
$brand_logo        = ! empty( $settings['brand_logo'] ) ? $settings['brand_logo'] : '';
$brand_logo_width  = ! empty( $settings['brand_logo_width'] ) ? absint( $settings['brand_logo_width'] ) : 125;
$link_refund       = ! empty( $settings['link_refund'] ) ? $settings['link_refund'] : '';
$link_privacy      = ! empty( $settings['link_privacy'] ) ? $settings['link_privacy'] : '';
$link_terms        = ! empty( $settings['link_terms'] ) ? $settings['link_terms'] : '';
$link_contact      = ! empty( $settings['link_contact'] ) ? $settings['link_contact'] : '';
$shop_url          = wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$item_count        = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
?>

<div class="mcrpd-shop-checkout-wrap mcrpd-cart-wrapper">

	<!-- Left Column: Brand Header, Cart Items List, Footer Links -->
	<div class="mcrpd-shop-checkout-left mcrpd-cart-left">

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
				<a href="<?php echo esc_url( $shop_url ); ?>" class="mcrpd-cart-continue-link">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
					<span><?php esc_html_e( 'Continue shopping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				</a>
			</header>
		<?php endif; ?>

		<?php do_action( 'woocommerce_before_cart' ); ?>

		<div class="mcrpd-cart-header-row">
			<h1 class="mcrpd-cart-main-title">
				<?php esc_html_e( 'Your cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?>
				<span class="mcrpd-cart-count-badge" id="mcrpd-cart-count-badge"><?php echo esc_html( $item_count ); ?></span>
			</h1>
			<?php if ( $hide_brand_header ) : ?>
				<a href="<?php echo esc_url( $shop_url ); ?>" class="mcrpd-cart-continue-link">
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
					<span><?php esc_html_e( 'Continue shopping', 'mcod-minimalist-checkout-for-woocommerce' ); ?></span>
				</a>
			<?php endif; ?>
		</div>

		<form class="woocommerce-cart-form mcrpd-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
			<?php do_action( 'woocommerce_before_cart_table' ); ?>
			<?php do_action( 'woocommerce_before_cart_contents' ); ?>

			<div id="mcrpd-cart-items-list" class="mcrpd-cart-items-list shop_table shop_table_responsive cart woocommerce-cart-form__contents">
				<?php echo MCMCHK_Checkout_Loader::render_cart_items_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>

			<?php do_action( 'woocommerce_cart_contents' ); ?>

			<div class="mcrpd-cart-hidden-actions" style="display:none;">
				<button type="submit" class="button" name="update_cart" value="<?php esc_attr_e( 'Update cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?>"><?php esc_html_e( 'Update cart', 'mcod-minimalist-checkout-for-woocommerce' ); ?></button>
				<?php do_action( 'woocommerce_cart_actions' ); ?>
				<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
			</div>

			<?php do_action( 'woocommerce_after_cart_contents' ); ?>
			<?php do_action( 'woocommerce_after_cart_table' ); ?>
		</form>

		<?php do_action( 'woocommerce_after_cart' ); ?>

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

	<!-- Right Column: Cart Order Summary, Coupon Input, Totals & Proceed to Checkout CTA -->
	<div class="mcrpd-shop-checkout-right mcrpd-cart-right">
		<div class="mcrpd-shop-checkout-right-inner">
			<div id="mcrpd-cart-summary-box" class="mcrpd-cart-summary-box cart_totals">
				<?php echo MCMCHK_Checkout_Loader::render_cart_summary_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</div>

</div>
