<?php
/**
 * Template Name: Minimalist Checkout
 * Description: Minimalist and distraction-free checkout page template, inspired by shop.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

	<div class="mcrpd-checkout-page-container">
		
		<!-- Main Content Wrapper -->
		<main class="mcrpd-checkout-main-content">
			<?php
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					the_content();
				}
			} else {
				// Fallback if no page content, directly execute the WooCommerce checkout shortcode
				echo do_shortcode( '[woocommerce_checkout]' );
			}
			?>
		</main>

	</div>

<?php
get_footer();
