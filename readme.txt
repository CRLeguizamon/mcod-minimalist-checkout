=== MCOD Minimalist Checkout for WooCommerce ===
Contributors: crleguizamon
Donate link: https://www.paypal.com/paypalme/cristian18josue
Tags: woocommerce, checkout, checkout-field-editor, cart, minimalist
Requires at least: 5.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Minimalist Checkout, Cart & Thank You page for WooCommerce with a drag-and-drop Checkout Field Editor & Manager to boost conversions.

== Description ==

**Modernize your entire WooCommerce purchase flow — from Cart to Checkout to Order Confirmation — and customize every checkout field in one lightweight plugin.**

Default WooCommerce cart and checkout pages are cluttered with theme headers, navigation menus, sidebar widgets, and unnecessary form fields that distract shoppers and cause cart abandonment. Most store owners end up installing multiple heavy plugins just to get a clean checkout layout and a checkout field editor.

**MCOD Minimalist Checkout for WooCommerce** solves both problems at once. It replaces your standard WooCommerce **Cart**, **Checkout**, and **Order Received (Thank You)** pages with a fast, distraction-free, two-column minimalist design — while giving you a built-in **Checkout Field Editor & Manager** to reorder, hide, resize, and rename checkout fields with drag-and-drop simplicity.

### Why Store Owners Choose Minimalist Checkout

* **All-in-One Purchase Funnel**: Deliver a cohesive, high-converting 2-column experience across your **Cart**, **Checkout**, and **Thank You (Order Received)** pages.
* **Built-in Checkout Field Editor (Checkout Manager)**: Reorder fields via drag-and-drop, enable or disable optional fields, change field widths (100% or 50% side-by-side), and customize labels and placeholders without writing code.
* **Distraction-Free Conversion Mode**: Automatically removes noisy theme headers, menus, and footers so customers stay 100% focused on completing their order (or toggle your theme header/footer back on anytime).
* **Zero Bloat & Native WooCommerce Hooks**: Built strictly on standard WooCommerce hooks and templates for maximum speed and compatibility with payment gateways and shipping plugins.

---

### Key Features

#### 1. Visual Checkout Field Editor & Manager
Stop losing sales to long, overwhelming forms. Customize your Billing, Shipping, and Order Notes fields directly from the visual field manager:
* **Drag-and-Drop Field Reordering**: Rearrange checkout fields in seconds to match your customers' natural flow.
* **Show or Hide Optional Fields**: Easily disable unnecessary fields like *Company*, *Apartment / Suite (Address 2)*, *Postcode / ZIP*, *Phone*, or *Order Notes* to reduce checkout friction.
* **Custom Labels & Placeholders**: Rename any field label or placeholder to match your brand voice or local terminology.
* **Flexible Column Widths**: Set any field to **100% Full Width**, **50% Left**, or **50% Right** to create compact side-by-side rows.
* **Floating-Style Clean Mode**: Optionally hide labels above inputs and rely on clean placeholders for a modern, app-like form.
* **One-Click Reset**: Restore default WooCommerce field settings anytime with a single click.

#### 2. Interactive AJAX Minimalist Cart
Turn your WooCommerce Cart page into a fast, modern two-column shopping summary:
* **Instant AJAX Quantity Stepper**: Customers can increment (`+`), decrement (`-`), or remove items with immediate live total recalculation — no "Update Cart" button or page reloads required.
* **Collapsible Coupon Code Drawer**: Keep bargain-hunting distractions out of the way while letting customers with a promo code redeem or remove coupons via AJAX.
* **Express Checkout Ready**: Supports accelerated wallet buttons (Apple Pay, Google Pay, Stripe Link, PayPal) right below the primary checkout CTA.

#### 3. High-Converting Two-Column Checkout
* **Inline AJAX Customer Login**: Returning customers can sign in directly inside the Contact section without leaving the checkout page.
* **Single Address Mode**: Option to always use the billing address as the shipping address and hide the *"Use a different shipping address"* toggle.
* **Digital Products Mode**: Selling courses, software, or downloads? Enable Digital Mode to hide shipping methods and display *"Instant digital"* across Cart, Checkout, and Order Confirmation.
* **Marketing Newsletter Opt-in**: Built-in *"Email me with news and offers"* checkbox under the customer email that saves opt-in consent directly to the WooCommerce order.
* **Mobile Order Summary Accordion**: Sticky, collapsible order summary bar on mobile devices so shoppers can review their items and totals with a tap.

#### 4. Modern Order Received (Thank You) Page
Leave a lasting post-purchase impression with a two-column Order Confirmation page:
* **Clear Order Overview**: Displays confirmation status, order number, date, total, payment method, and customer billing/shipping details in clean cards.
* **Instant Digital Downloads**: Highlights downloadable files and remaining download counts right on the confirmation screen.
* **Customizable "Continue Shopping" Button**: Customize both the button text and destination URL (e.g., redirect buyers to a custom onboarding page, member dashboard, or special offer) with a simple toggle.

#### 5. Payment Gateways, Express Checkout & Subscriptions Compatibility
* **Stripe, PayPal & Local Gateways**: Seamless visual and functional integration with Stripe Elements, Stripe UPE, PayPal, and local payment methods.
* **Stripe Link, Apple Pay & Google Pay State Fix**: Includes an optional ISO state code normalizer that resolves regional state prefix mismatches (e.g., `AMA` vs `CO-AMA`) on WooCommerce Store API requests.
* **WooCommerce Subscriptions Ready**: Clean product rows without repetitive subscription clutter, plus a customizable **Recurring Payment Block** with dynamic variables (`{{recurring_total}}`, `{{period}}`, `{{first_renewal_date}}`, `{{trial_period}}`, `{{sign_up_fee}}`) across Cart, Checkout, and Thank You sidebars.
* **Elementor & Page Builder Compatible**: Works seamlessly even if your pages use *Elementor Full Width*, *Elementor Canvas*, or custom theme templates via one-click **Force Template** settings.

---

### Love Minimalist Checkout? Rate Us on WordPress.org!

If **MCOD Minimalist Checkout for WooCommerce** helps your store look more professional and convert more visitors into customers, please consider [leaving a 5-star review on WordPress.org](https://wordpress.org/support/plugin/mcod-minimalist-checkout-for-woocommerce/reviews/#new-post). Your feedback helps other store owners discover the plugin and motivates us to keep adding new features for free!

* [Official WordPress.org Plugin Page](https://wordpress.org/plugins/mcod-minimalist-checkout-for-woocommerce/)
* [Support Forum & Questions](https://wordpress.org/support/plugin/mcod-minimalist-checkout-for-woocommerce/)

== Installation ==

1. Upload the `mcod-minimalist-checkout-for-woocommerce` folder to the `/wp-content/plugins/` directory, or install it directly through the **Plugins > Add New** screen in WordPress.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **Minimalist Checkout** in your WordPress Admin sidebar.
4. Enable **Force Checkout Template**, **Force Cart Template**, and **Force Order Received (Thank You) Template** (or assign the *Minimalist Checkout* / *Minimalist Cart* Page Template in the WordPress page editor).
5. Upload your store logo, pick your primary brand color, and customize your checkout fields in the **Checkout Fields** tab.

== Frequently Asked Questions ==

= Do I need a separate Checkout Field Editor plugin? =
No! Minimalist Checkout includes a built-in **Checkout Field Editor & Manager**. You can drag and drop fields to reorder them, disable optional fields (like Company, Address Line 2, Postcode, Phone, or Order Notes), change column widths (50% or 100%), and customize labels and placeholders directly from the **Checkout Fields** tab.

= Can I apply the minimalist design to the Cart and Thank You pages too? =
Yes! Version 2.0 includes dedicated **Minimalist Cart** and **Minimalist Order Received (Thank You)** templates so your customers experience a consistent, distraction-free two-column design from start to finish.

= Can I change the "Continue shopping" button text and link on the Thank You page? =
Yes! In **Minimalist Checkout > Design & Settings**, turn on the **Thank You Page "Continue Shopping" Button** toggle to reveal custom fields for both the **Button Text** and the **Button Destination Link (URL)**.

= Does it work with Elementor, Divi, Astra, and Block Themes? =
Yes. By enabling the **Force Template** toggles in the plugin settings, Minimalist Checkout automatically overrides theme and page builder layouts (including *Elementor Full Width* and *Elementor Canvas*) on the Cart, Checkout, and Order Received pages without breaking your other pages.

= Does it work with my payment gateway (Stripe, PayPal, Apple Pay, Google Pay)? =
Yes! Because the plugin uses native WooCommerce checkout hooks and fragments, it works out of the box with Stripe (including Stripe Link, Apple Pay, and Google Pay Express Checkout), PayPal, bank transfers, cash on delivery, and regional gateways.

= If I hide the theme header and footer, will my tracking pixels and Google Analytics still work? =
Yes! Distraction-free mode only removes the visual theme header and footer markup. Standard WordPress `wp_head()` and `wp_footer()` hooks still execute normally, so Meta Pixel, Google Analytics (GA4), Google Tag Manager, and conversion scripts work without interruption.

= Does it support WooCommerce Subscriptions? =
Yes. When WooCommerce Subscriptions is active, the plugin cleans up verbose subscription descriptions on product line items and lets you customize the **Recurring Payment Block** across the Cart, Checkout, and Order Received sidebars using dynamic variables.

== Screenshots ==

1. The distraction-free, 2-column minimalist checkout layout.
2. The plugin's customization settings panel and visual Checkout Field Editor inside WordPress Admin.

== Changelog ==

= 2.0 =
* Major Release: Complete purchase flow redesign including Minimalist Cart, Minimalist Order Confirmation (Thank You), and an upgraded Admin Settings experience.
* New: Minimalist Cart layout with real-time AJAX quantity stepper (+ / -), one-click item removal, and live coupon redemption.
* New: 2-column Order Received (Thank You) confirmation page with order overview, customer addresses, and digital downloads.
* New: Customizer for the Thank You page "Continue shopping" button — toggle to customize both the button text and destination link (URL).
* New: Redesigned Admin Settings panel (`page=mcrpd-checkout-settings`) with quick store preview links, modern toggle switches, organized setting cards, live logo preview, and a WordPress.org review card.
* New: One-click "Force Template" settings for Cart, Checkout, and Order Received pages.
* New: Full compatibility with Stripe Express Checkout (Stripe Link, Apple Pay, Google Pay) and automatic state/department ISO code normalization.
* New: Self-healing customer country validation so logged-in users always see their Billing and Shipping address fields even after testing Express Checkout wallets.
* New: WooCommerce Subscriptions Recurring Payment Block across Cart, Checkout, and Order Received sidebars with clean product line items.
* Compatibility: Elementor Full Width and Canvas template compatibility with automatic container centering and mobile edge-to-edge alignment.

= 1.0.5 =
* Fix: Terms and conditions checkbox visibility and duplication issues resolved.

= 1.0.4 =
* Tweak: Improved visual compatibility with Stripe (the 'Save to account' checkbox is now perfectly aligned).
* Tweak: Cleaner and more compact look for the payment methods selection area.

= 1.0.3 =
* Fix: CSS compatibility for Stripe, fieldsets, and discount borders.
* Compat: Added support for Digital Products.
* Compat: WooCommerce Subscriptions compatibility.

= 1.0.2 =
* Initial stable release.
