/**
 * Minimalist Checkout for WooCommerce - Interaction & Syncing script
 */

(function ($) {
	'use strict';

	$(document).ready(function () {
		init_address_syncing();
		init_coupon_handlers();
		init_card_highlights();
		init_inline_login();
	});

	// Re-initialize dynamic visuals after WooCommerce completes an AJAX checkout refresh
	$(document.body).on('updated_checkout', function () {
		init_card_highlights();
	});

	/**
	 * 1. Address Syncing Mechanics
	 * Since Billing Address is primary, and Delivery (Shipping) is secondary:
	 * If "Same as billing" is active, copy all Billing fields into Shipping fields in the background.
	 */
	function init_address_syncing() {
		const $shippingSelector = $('input[name="mcmchk_shipping_different"]');
		const $shippingAddressWrap = $('.shipping_address');

		// Handle radio group toggle
		$shippingSelector.on('change', function () {
			const isDifferent = $(this).val() === 'yes';

			// Toggle parent active classes for styling
			$shippingSelector.closest('.mcrpd-shipping-radio-label').removeClass('selected');
			$(this).closest('.mcrpd-shipping-radio-label').addClass('selected');

			// Trigger native checkbox state
			$('#ship-to-different-address-checkbox').prop('checked', isDifferent).trigger('change');
		});

		// Sync our custom radio state if the native checkbox is changed by other scripts
		$(document).on('change', '#ship-to-different-address-checkbox', function () {
			const isChecked = $(this).is(':checked');
			const $radioYes = $('input[name="mcmchk_shipping_different"][value="yes"]');
			const $radioNo = $('input[name="mcmchk_shipping_different"][value="no"]');

			if (isChecked) {
				$radioYes.prop('checked', true).closest('.mcrpd-shipping-radio-label').addClass('selected');
				$radioNo.prop('checked', false).closest('.mcrpd-shipping-radio-label').removeClass('selected');
				$shippingAddressWrap.slideDown(250);
			} else {
				$radioNo.prop('checked', true).closest('.mcrpd-shipping-radio-label').addClass('selected');
				$radioYes.prop('checked', false).closest('.mcrpd-shipping-radio-label').removeClass('selected');
				$shippingAddressWrap.slideUp(250);
				perform_fields_sync();
			}
		});

		// Address fields to synchronize
		const fieldsToSync = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];

		// Sync on change or blur of billing fields to shipping fields
		fieldsToSync.forEach(function (field) {
			$('#billing_' + field).on('change blur', function () {
				if (!$shippingSelector.length || $('input[name="mcmchk_shipping_different"]:checked').val() === 'no') {
					const val = $(this).val();
					$('#shipping_' + field).val(val);

					// Trigger change only on calculation-affecting fields when they lose focus or change
					if (['country', 'state', 'postcode', 'city', 'address_1'].includes(field)) {
						$('#shipping_' + field).trigger('change');
					}
				}
			});
		});

		// Trigger initial sync if "no" is checked (Same as billing)
		if (!$shippingSelector.length || $('input[name="mcmchk_shipping_different"]:checked').val() === 'no') {
			perform_fields_sync();
		}

		function perform_fields_sync() {
			fieldsToSync.forEach(function (field) {
				const billingVal = $('#billing_' + field).val();
				$('#shipping_' + field).val(billingVal).trigger('change');
			});
		}
	}

	/**
	 * 2. shop-style AJAX Coupon Handlers
	 */
	function init_coupon_handlers() {

		// Apply Coupon
		$(document).on('click', '#mcrpd-coupon-submit', function (e) {
			e.preventDefault();

			const $couponInput = $('#mcrpd-coupon-input');
			const $couponMsg = $('#mcrpd-coupon-msg');
			
			const couponCode = $couponInput.val().trim();
			if (!couponCode) {
				return;
			}

			// Block sidebar container to show loading spinner
			$('.mcrpd-shop-checkout-right-inner').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			$couponMsg.removeClass('success error').html('');

			const ajaxUrl = mcmchk_params.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon');

			$.ajax({
				type: 'POST',
				url: ajaxUrl,
				data: {
					security: mcmchk_params.apply_coupon_nonce,
					coupon_code: couponCode
				},
				success: function (response) {
					$('.mcrpd-shop-checkout-right-inner').unblock();

					// Wrap response in a div so .find() can locate root-level elements
					const $html = $('<div>').html(response);
					const $errors = $html.find('.woocommerce-error');
					const $messages = $html.find('.woocommerce-message');

					if ($errors.length > 0) {
						$couponMsg.addClass('error').html($errors.text().trim());
					} else if ($messages.length > 0) {
						$couponMsg.addClass('success').html($messages.text().trim());
						$couponInput.val('');
						// Trigger standard WooCommerce AJAX totals update
						$(document.body).trigger('update_checkout');
					} else {
						// Fallback if WooCommerce doesn't return default notices
						$couponMsg.addClass('success').html('Coupon applied.');
						$couponInput.val('');
						$(document.body).trigger('update_checkout');
					}
				},
				error: function () {
					$('.mcrpd-shop-checkout-right-inner').unblock();
					$couponMsg.addClass('error').html('Connection error. Please try again.');
				}
			});
		});

		// Remove Coupon
		$(document).on('click', '.mcrpd-remove-coupon-btn', function (e) {
			e.preventDefault();

			const couponCode = $(this).data('coupon');
			if (!couponCode) {
				return;
			}

			$('.mcrpd-shop-checkout-right-inner').block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});

			const ajaxUrl = mcmchk_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_coupon');

			$.ajax({
				type: 'POST',
				url: ajaxUrl,
				data: {
					security: mcmchk_params.remove_coupon_nonce,
					coupon: couponCode
				},
				success: function () {
					$('.mcrpd-shop-checkout-right-inner').unblock();
					$(document.body).trigger('update_checkout');
				},
				error: function () {
					$('.mcrpd-shop-checkout-right-inner').unblock();
					alert('Could not remove the coupon.');
				}
			});
		});
	}

	/**
	 * 3. Premium Interactive Card Highlights
	 * Highlights card modules (li boxes) when active radios change inside shipping or payment containers.
	 * Also enables clicking anywhere on a shipping li to select its radio.
	 */
	function init_card_highlights() {
		// Shipping list items card highlights
		$('.mcrpd-shipping-methods-list input[type="radio"]').each(function () {
			const $radio = $(this);
			const $card = $radio.closest('li');

			if ($radio.is(':checked')) {
				$card.addClass('selected active');
			} else {
				$card.removeClass('selected active');
			}
		});

		// Listen to user changes in shipping
		$(document).on('change', '.mcrpd-shipping-methods-list input[type="radio"]', function () {
			$('.mcrpd-shipping-methods-list li').removeClass('selected active');
			$(this).closest('li').addClass('selected active');
		});

		// Click anywhere on a shipping li to select its radio (shop UX)
		$(document).on('click', '.mcrpd-shipping-methods-list li', function (e) {
			// Don't double-trigger if clicking the radio directly
			if ($(e.target).is('input[type="radio"]')) {
				return;
			}
			const $radio = $(this).find('input[type="radio"]');
			if ($radio.length && !$radio.prop('checked')) {
				$radio.prop('checked', true).trigger('change');
			}
		});

		// Payment gateway card highlights
		$('.mcrpd-payment-methods-list input[type="radio"]').each(function () {
			const $radio = $(this);
			const $card = $radio.closest('li.wc_payment_method');

			if ($radio.is(':checked')) {
				$card.addClass('payment_box_active');
			} else {
				$card.removeClass('payment_box_active');
			}
		});

		// Listen to user changes in payment gateways
		$(document).on('change', '.mcrpd-payment-methods-list input[type="radio"]', function () {
			$('.mcrpd-payment-methods-list li.wc_payment_method').removeClass('payment_box_active');
			$(this).closest('li.wc_payment_method').addClass('payment_box_active');
		});
	}

	/**
	 * 4. Inline AJAX Login
	 * Toggles the login form in the Contact section and handles authentication
	 * without navigating away from the checkout page.
	 */
	function init_inline_login() {
		const $toggle = $('#mcrpd-login-toggle');
		const $loginForm = $('#mcrpd-inline-login');
		const $loginMsg = $('#mcrpd-login-message');

		if (!$toggle.length) {
			return;
		}

		// Toggle login form visibility
		$toggle.on('click', function (e) {
			e.preventDefault();
			$loginForm.slideToggle(250);
			$(this).toggleClass('active');
		});

		// Submit login via Enter key in password field
		$(document).on('keydown', '#mcrpd-login-password', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#mcrpd-login-submit').trigger('click');
			}
		});

		// Handle AJAX login submission
		$(document).on('click', '#mcrpd-login-submit', function (e) {
			e.preventDefault();

			const username = $('#mcrpd-login-username').val().trim();
			const password = $('#mcrpd-login-password').val();

			if (!username || !password) {
				$loginMsg.removeClass('success').addClass('error').html(mcmchk_params.i18n_login_empty);
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true).css('opacity', '0.6');
			$loginMsg.removeClass('success error').html('');

			$.ajax({
				type: 'POST',
				url: mcmchk_params.ajax_url,
				data: {
					action: 'mcmchk_ajax_login',
					security: mcmchk_params.login_nonce,
					username: username,
					password: password
				},
				success: function (response) {
					if (response.success) {
						$loginMsg.removeClass('error').addClass('success').html(response.data.message);
						// Reload the checkout page after a short delay to restore session
						setTimeout(function () {
							window.location.reload();
						}, 800);
					} else {
						$loginMsg.removeClass('success').addClass('error').html(response.data.message);
						$btn.prop('disabled', false).css('opacity', '1');
					}
				},
				error: function () {
					$loginMsg.removeClass('success').addClass('error').html('Connection error. Please try again.');
					$btn.prop('disabled', false).css('opacity', '1');
				}
			});
		});
	}

})(jQuery);
