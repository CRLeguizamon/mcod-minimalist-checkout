<?php
/**
 * Template Name: Minimalist Checkout
 * Description: Minimalist and distraction-free checkout page template, inspired by shop.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$settings       = get_option( 'mcmchk_settings', array() );
$use_theme_hf   = ! empty( $settings['use_theme_hf'] ) ? true : false;
$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

// If not a block theme, try to load the traditional header.php
if ( ! $is_block_theme ) {
	get_header();
} else {
	// Block themes (FSE) don't use header.php, so we must provide the HTML canvas.
	?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
	<?php
	// If user wants theme header/footer, attempt to load the block template part.
	if ( $use_theme_hf && function_exists( 'block_template_part' ) ) {
		block_template_part( 'header' );
	}
}
?>

	<div class="mcrpd-checkout-page-container">
		
		<!-- Main Content Wrapper -->
		<main class="mcrpd-checkout-main-content">
			<?php
			$raw_content = '';
			if ( have_posts() ) {
				while ( have_posts() ) {
					the_post();
					$raw_content = (string) get_post_field( 'post_content', get_the_ID() );
				}
			}

			// Render the WooCommerce shortcode directly to prevent page builders (Elementor, Divi, Gutenberg Blocks)
			// from wrapping the layout in boxed sections or overriding the checkout/cart with builder widgets.
			if ( class_exists( 'MCMCHK_Checkout_Loader' ) && MCMCHK_Checkout_Loader::is_minimalist_cart() ) {
				echo do_shortcode( '[woocommerce_cart]' );
			} else {
				echo do_shortcode( '[woocommerce_checkout]' );
			}

			if ( ! empty( $raw_content ) && has_shortcode( $raw_content, 'mcmchk_debug' ) ) {
				echo do_shortcode( '[mcmchk_debug]' );
			}
			?>
		</main>

	</div>

<?php
if ( ! $is_block_theme ) {
	get_footer();
} else {
	if ( $use_theme_hf && function_exists( 'block_template_part' ) ) {
		block_template_part( 'footer' );
	}
	wp_footer();
	?>
</body>
</html>
	<?php
}
