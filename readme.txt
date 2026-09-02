=== MCOD Minimalist Checkout for WooCommerce ===
Contributors: crleguizamon
Donate link: https://www.paypal.com/paypalme/cristian18josue
Tags: woocommerce, checkout, minimalist, shop, distraction-free
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A minimalist, beautiful, and fast checkout for WooCommerce, inspired by shop.

== Description ==

MCOD Minimalist Checkout for WooCommerce replaces the standard and noisy WooCommerce checkout form with a premium two-column layout, highly optimized and inspired by the shop checkout flow.

* **Premium Design**: Clean interface with Inter typography, subtle shadows, and interactive shop-blue cards.
* **Fully Isolated & Distraction-Free**: Removes main menus, promotional banners, and theme footers, keeping the user 100% focused on finalizing their purchase.
* **Complete AJAX Integration**: Allows shipping rates calculation, applying/removing discount coupons, and updating payment methods immediately without page reloads.
* **Smart Address Mapping**: First screen intuitive for delivery address, with automatic background synchronization for the billing address to maintain compatibility with external payment gateways.
* **Clean & Secure Development**: Built using standard WordPress and WooCommerce hooks to ensure future compatibility.
* **Customization Settings**: Built-in settings panel to toggle optional fields, discount coupons, and tweak the user experience directly from WooCommerce > Settings > Minimalist Checkout.

== Installation ==

1. Upload the `mcod-minimalist-checkout` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Edit the WooCommerce Checkout page and, under the 'Page Attributes -> Template' section, select 'Minimalist Checkout'.

== Frequently Asked Questions ==

= Does it work with my payment gateway? =
Yes! We've built the checkout using native WooCommerce hooks to ensure high compatibility with popular gateways such as Stripe, PayPal, and others.

= Will it break my current theme? =
No. Our checkout is fully isolated and distraction-free. It intentionally removes your theme's header and footer on the checkout page to keep the user focused, so theme conflicts are virtually non-existent.

= Can I hide the coupon field? =
Yes! You can easily toggle the coupon form on or off directly from the plugin settings panel.

= How do I customize it? =
Go to WooCommerce > Settings > Minimalist Checkout to manage fields and toggle features on and off.

== Screenshots ==

1. The distraction-free, 2-column checkout layout.
2. The plugin's customization settings panel inside WooCommerce.

== Changelog ==

= 1.0.3 =
* Fix: CSS compatibility for Stripe, fieldsets, and discount borders.
* Compat: Added support for Digital Products.
* Compat: WooCommerce Subscriptions compatibility.

= 1.0.2 =
* Initial stable release.
