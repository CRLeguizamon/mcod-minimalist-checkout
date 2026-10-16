/**
 * Minimalist Checkout for WooCommerce - Interaction & Syncing script
 */

(function ($) {
	'use strict';

	let isMobileSummaryExpanded = false;

	let cardEventsBound = false;

	$(document).ready(function () {
		init_address_syncing();
		init_coupon_handlers();
		init_card_highlights();
		init_inline_login();
		init_mobile_summary_toggle();
		init_minimalist_cart();
	});

	// Re-initialize dynamic visuals after WooCommerce completes an AJAX checkout refresh
	$(document.body).on('updated_checkout', function () {
		sync_card_highlights();
		sync_mobile_summary_state();
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
				perform_fields_sync(true);
			}
		});

		// Address fields to synchronize
		const fieldsToSync = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country'];

		// Sync on change or blur of billing fields to shipping fields
		fieldsToSync.forEach(function (field) {
			$('#billing_' + field).on('change blur', function () {
				if (!$shippingSelector.length || $('input[name="mcmchk_shipping_different"]:checked').val() === 'no') {
					const val = $(this).val();
					const $shippingField = $('#shipping_' + field);
					if ($shippingField.length && $shippingField.val() !== val) {
						$shippingField.val(val);

						// Trigger change only on calculation-affecting fields when they actually change
						if (['country', 'state', 'postcode', 'city', 'address_1'].includes(field)) {
							$shippingField.trigger('change');
						}
					}
				}
			});
		});

		// Ensure shipping fields are synced right before WooCommerce or Stripe submits the form
		$('form.woocommerce-checkout').on('checkout_place_order', function () {
			if (!$shippingSelector.length || $('input[name="mcmchk_shipping_different"]:checked').val() === 'no') {
				perform_fields_sync(false);
			}
			return true;
		});

		// Trigger initial sync silently if "no" is checked (Same as billing)
		if (!$shippingSelector.length || $('input[name="mcmchk_shipping_different"]:checked').val() === 'no') {
			perform_fields_sync(false);
		}

		function perform_fields_sync(triggerUpdate) {
			let hasLocationChanged = false;
			fieldsToSync.forEach(function (field) {
				const $billingField = $('#billing_' + field);
				const $shippingField = $('#shipping_' + field);
				if ($billingField.length && $shippingField.length) {
					const billingVal = $billingField.val();
					if ($shippingField.val() !== billingVal) {
						$shippingField.val(billingVal);
						if (['country', 'state', 'postcode', 'city', 'address_1'].includes(field)) {
							hasLocationChanged = true;
						}
					}
				}
			});

			if (triggerUpdate && hasLocationChanged) {
				$(document.body).trigger('update_checkout');
			}
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
				$couponMsg.removeClass('success').addClass('error').text(mcmchk_params.i18n_coupon_empty || 'Please enter a discount code.');
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

			$couponMsg.removeClass('success error').text('');

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

					// Safely parse HTML without executing inline scripts
					const parsedNodes = $.parseHTML(response, document, false);
					const $html = $('<div>').append(parsedNodes);
					const $errors = $html.find('.woocommerce-error');
					const $messages = $html.find('.woocommerce-message');

					if ($errors.length > 0) {
						$couponMsg.addClass('error').text($errors.text().trim());
					} else if ($messages.length > 0) {
						$couponMsg.addClass('success').text($messages.text().trim());
						$couponInput.val('');
						// Trigger standard WooCommerce AJAX totals update
						$(document.body).trigger('update_checkout');
					} else {
						// Fallback if WooCommerce doesn't return default notices
						$couponMsg.addClass('success').text(mcmchk_params.i18n_coupon_applied || 'Coupon applied.');
						$couponInput.val('');
						$(document.body).trigger('update_checkout');
					}
				},
				error: function () {
					$('.mcrpd-shop-checkout-right-inner').unblock();
					$couponMsg.addClass('error').text(mcmchk_params.i18n_coupon_error || 'Connection error. Please try again.');
				}
			});
		});

		// Remove Coupon (Checkout)
		$(document).on('click', '.mcrpd-remove-coupon-btn:not(.mcrpd-cart-remove-coupon-btn)', function (e) {
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
					alert(mcmchk_params.i18n_remove_coupon_error || 'Could not remove the coupon.');
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
		sync_card_highlights();

		if (cardEventsBound) {
			return;
		}
		cardEventsBound = true;

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

		// Listen to user changes in top-level payment gateways only (exclude inner radios like Stripe Saved Cards)
		$(document).on('change', '.mcrpd-payment-methods-list li.wc_payment_method > input[type="radio"]', function () {
			$('.mcrpd-payment-methods-list li.wc_payment_method').removeClass('payment_box_active');
			$(this).closest('li.wc_payment_method').addClass('payment_box_active');
		});
	}

	function sync_card_highlights() {
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

		// Top-level payment gateway card highlights (direct child radio only)
		$('.mcrpd-payment-methods-list li.wc_payment_method > input[type="radio"]').each(function () {
			const $radio = $(this);
			const $card = $radio.closest('li.wc_payment_method');

			if ($radio.is(':checked')) {
				$card.addClass('payment_box_active');
			} else {
				$card.removeClass('payment_box_active');
			}
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
				$loginMsg.removeClass('success').addClass('error').text(mcmchk_params.i18n_login_empty);
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true).css('opacity', '0.6');
			$loginMsg.removeClass('success error').text('');

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
						$loginMsg.removeClass('error').addClass('success').text(response.data.message);
						// Reload the checkout page after a short delay to restore session
						setTimeout(function () {
							window.location.reload();
						}, 800);
					} else {
						$loginMsg.removeClass('success').addClass('error').text(response.data.message);
						$btn.prop('disabled', false).css('opacity', '1');
					}
				},
				error: function () {
					$loginMsg.removeClass('success').addClass('error').text(mcmchk_params.i18n_connection_error || 'Connection error. Please try again.');
					$btn.prop('disabled', false).css('opacity', '1');
				}
			});
		});
	}

	/**
	 * 5. Mobile Order Summary Accordion Toggle (<= 1000px)
	 */
	function init_mobile_summary_toggle() {
		$(document).on('click', '.mcrpd-mobile-summary-toggle', function (e) {
			e.preventDefault();
			isMobileSummaryExpanded = !isMobileSummaryExpanded;
			const $btn = $(this);
			const $collapsible = $('#mcrpd-OrderSummaryCollapsible');
			const $label = $btn.find('.mcrpd-mobile-summary-toggle-label');

			$btn.attr('aria-expanded', isMobileSummaryExpanded ? 'true' : 'false');
			$btn.toggleClass('is-open', isMobileSummaryExpanded);

			if (isMobileSummaryExpanded) {
				$label.text(mcmchk_params.i18n_hide_order_summary || 'Hide order summary');
				$collapsible.stop(true, true).slideDown(250, function () {
					$(this).addClass('is-open').css('display', '');
				});
			} else {
				$label.text(mcmchk_params.i18n_show_order_summary || 'Show order summary');
				$collapsible.stop(true, true).slideUp(250, function () {
					$(this).removeClass('is-open').css('display', '');
				});
			}
		});
	}

	function sync_mobile_summary_state() {
		const $btn = $('.mcrpd-mobile-summary-toggle');
		const $collapsible = $('#mcrpd-OrderSummaryCollapsible');
		if (!$btn.length || !$collapsible.length) {
			return;
		}
		$btn.attr('aria-expanded', isMobileSummaryExpanded ? 'true' : 'false');
		$btn.toggleClass('is-open', isMobileSummaryExpanded);
		$collapsible.toggleClass('is-open', isMobileSummaryExpanded);
		$btn.find('.mcrpd-mobile-summary-toggle-label').text(
			isMobileSummaryExpanded
				? (mcmchk_params.i18n_hide_order_summary || 'Hide order summary')
				: (mcmchk_params.i18n_show_order_summary || 'Show order summary')
		);
	}

	/**
	 * 6. Minimalist Cart AJAX Interactions (Quantity Stepper, Item Removal, Coupons)
	 */
	function init_minimalist_cart() {
		if (!$('.mcrpd-cart-wrapper').length) {
			return;
		}

		let qtyDebounceTimer = null;

		function sendCartAjax(payload, onComplete) {
			const $wrapper = $('.mcrpd-cart-wrapper');
			$wrapper.css('opacity', '0.65');

			$.ajax({
				type: 'POST',
				url: mcmchk_params.ajax_url,
				data: $.extend({
					action: 'mcmchk_update_cart_item',
					security: mcmchk_params.cart_nonce
				}, payload),
				success: function (response) {
					$wrapper.css('opacity', '1');
					if (response && response.success && response.data) {
						if (response.data.is_empty && response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
							return;
						}
						if (typeof response.data.items_html === 'string') {
							$('#mcrpd-cart-items-list').html(response.data.items_html);
						}
						if (typeof response.data.summary_html === 'string') {
							$('#mcrpd-cart-summary-box').html(response.data.summary_html);
						}
						if (typeof response.data.item_count !== 'undefined') {
							$('#mcrpd-cart-count-badge').text(response.data.item_count);
						}
						if (response.data.notice_html) {
							const $msg = $('#mcrpd-cart-coupon-message');
							$msg.removeClass('success error')
								.addClass(response.data.notice_type === 'error' ? 'error' : 'success')
								.text(response.data.notice_html)
								.show();
						}
						$(document.body).trigger('updated_cart_totals');
					}
					if (typeof onComplete === 'function') {
						onComplete(response);
					}
				},
				error: function () {
					$wrapper.css('opacity', '1');
				}
			});
		}

		// Stepper Minus / Plus buttons
		$(document).on('click', '.mcrpd-qty-btn', function (e) {
			e.preventDefault();
			const $btn = $(this);
			const key = $btn.data('cart-item-key');
			const $input = $btn.closest('.mcrpd-qty-stepper').find('.mcrpd-qty-input');
			if (!$input.length || !key) {
				return;
			}

			let currentVal = parseInt($input.val(), 10);
			if (isNaN(currentVal)) {
				currentVal = 1;
			}
			const maxAttr = parseInt($input.attr('max'), 10);
			let nextVal = $btn.hasClass('mcrpd-qty-minus') ? currentVal - 1 : currentVal + 1;

			if (nextVal < 0) {
				nextVal = 0;
			}
			if (!isNaN(maxAttr) && maxAttr > 0 && nextVal > maxAttr) {
				nextVal = maxAttr;
			}

			$input.val(nextVal);

			clearTimeout(qtyDebounceTimer);
			qtyDebounceTimer = setTimeout(function () {
				sendCartAjax({
					sub_action: 'update_qty',
					cart_item_key: key,
					quantity: nextVal
				});
			}, 300);
		});

		// Direct manual input change on quantity field
		$(document).on('change', '.mcrpd-qty-input', function () {
			const $input = $(this);
			const key = $input.data('cart-item-key');
			let val = parseInt($input.val(), 10);
			if (isNaN(val) || val < 0) {
				val = 0;
			}
			clearTimeout(qtyDebounceTimer);
			sendCartAjax({
				sub_action: 'update_qty',
				cart_item_key: key,
				quantity: val
			});
		});

		// Remove item button
		$(document).on('click', '.mcrpd-cart-remove-btn', function (e) {
			e.preventDefault();
			const key = $(this).data('cart-item-key');
			if (!key) {
				return;
			}
			sendCartAjax({
				sub_action: 'update_qty',
				cart_item_key: key,
				quantity: 0
			});
		});

		// Apply coupon on Cart
		$(document).on('click', '#mcrpd_cart_apply_coupon_btn', function (e) {
			e.preventDefault();
			const code = ($('#mcrpd_cart_coupon_code').val() || '').trim();
			const $msg = $('#mcrpd-cart-coupon-message');
			if (!code) {
				$msg.removeClass('success').addClass('error').text(mcmchk_params.i18n_coupon_empty || 'Please enter a discount code.').show();
				return;
			}
			sendCartAjax({
				sub_action: 'apply_coupon',
				coupon_code: code
			});
		});

		// Submit coupon on Enter key inside Cart coupon input
		$(document).on('keydown', '#mcrpd_cart_coupon_code', function (e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#mcrpd_cart_apply_coupon_btn').trigger('click');
			}
		});

		// Remove coupon on Cart
		$(document).on('click', '.mcrpd-cart-remove-coupon-btn', function (e) {
			e.preventDefault();
			const code = $(this).data('coupon');
			if (!code) {
				return;
			}
			sendCartAjax({
				sub_action: 'remove_coupon',
				coupon_code: code
			});
		});
	}

})(jQuery);
