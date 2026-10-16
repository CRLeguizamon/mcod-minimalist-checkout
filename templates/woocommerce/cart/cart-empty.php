<?php
/**
 * Custom WooCommerce Empty Cart Override (Minimalist Cart).
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
$shop_url          = wc_get_page_permalink( 'shop' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
?>

<div class="mcrpd-empty-cart-page-wrap">
	<?php if ( ! $hide_brand_header ) : ?>
		<header class="mcrpd-checkout-brand-header mcrpd-empty-cart-brand-header">
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

	<?php do_action( 'woocommerce_before_cart' ); ?>

	<div class="mcrpd-empty-cart-wrapper">
		<div class="mcrpd-empty-cart-icon">
			<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="9" cy="21" r="1"></circle>
				<circle cx="20" cy="21" r="1"></circle>
				<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
			</svg>
		</div>
		<h2 class="mcrpd-empty-cart-title"><?php esc_html_e( 'Your cart is currently empty.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></h2>
		<p class="mcrpd-empty-cart-text"><?php esc_html_e( 'Looks like you have not added any items to your cart yet.', 'mcod-minimalist-checkout-for-woocommerce' ); ?></p>
		<a class="mcrpd-empty-cart-button" href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', $shop_url ) ); ?>">
			<?php echo esc_html( apply_filters( 'woocommerce_return_to_shop_text', __( 'Return to shop', 'mcod-minimalist-checkout-for-woocommerce' ) ) ); ?>
		</a>
	</div>
</div>
