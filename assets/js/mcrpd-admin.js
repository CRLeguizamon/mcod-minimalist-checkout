/**
 * Minimalist Checkout - Admin Settings & Field Editor Script
 *
 * Handles:
 * 1. Main settings tab switching (Diseño y Ajustes vs. Campos del Checkout)
 * 2. Inner field section tab switching (Facturación, Envío, Pedido)
 * 3. jQuery UI Sortable drag-and-drop reordering
 * 4. Immediate serialization of field configurations to the hidden JSON textarea
 * 5. Toggles enabling/disabling field rows
 * 6. Reset overrides button
 * 7. Color picker & Media uploader
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		// =====================================================================
		// 1. Main Settings Tabs (Top-level)
		// =====================================================================
		$('.mcrpd-main-tabs .nav-tab').on('click', function(e) {
			e.preventDefault();
			var tab = $(this).data('tab');

			// Switch active class on tabs
			$('.mcrpd-main-tabs .nav-tab').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');

			// Switch active tab content
			$('.mcrpd-main-tab-content').hide();
			$('#mcrpd-main-tab-' + tab).show();

			// If switching to fields tab, trigger a refresh on sortable just in case
			if (tab === 'fields') {
				$('.mcrpd-field-list tbody').sortable('refresh');
			}
		});

		// =====================================================================
		// 2. Inner Field Editor Tabs (Billing, Shipping, Order)
		// =====================================================================
		$('.mcrpd-field-tab').on('click', function() {
			var tab = $(this).data('tab');
			
			// Toggle active tab button
			$('.mcrpd-field-tab').removeClass('active');
			$(this).addClass('active');
			
			// Toggle active tab content
			$('.mcrpd-field-tab-content').removeClass('active');
			$('#mcrpd-tab-' + tab).addClass('active');
		});

		// =====================================================================
		// 3. Serialize Field Data Helper Function
		// =====================================================================
		function mcrpdUpdateFieldJSON() {
			var fieldData = {};

			$('.mcrpd-field-tab-content').each(function() {
				var section = $(this).data('section');
				fieldData[section] = {};

				$(this).find('.mcrpd-field-list tbody tr').each(function(index) {
					var key = $(this).data('field-key');
					if (!key) return;

					fieldData[section][key] = {
						enabled:     $(this).find('.mcrpd-field-enabled-toggle').is(':checked'),
						priority:    (index + 1) * 10,
						width:       $(this).find('.mcrpd-field-width-select').val() || 'form-row-wide',
						label:       $(this).find('.mcrpd-field-label-input').val() || '',
						placeholder: $(this).find('.mcrpd-field-placeholder-input').val() || ''
					};
				});
			});

			$('#mcmchk_field_overrides_json').val(JSON.stringify(fieldData));
		}

		// Run once on page load to initialize the JSON field state
		mcrpdUpdateFieldJSON();

		// =====================================================================
		// 4. jQuery UI Sortable (Drag-and-Drop Reordering)
		// =====================================================================
		$('.mcrpd-field-list tbody').sortable({
			handle: '.mcrpd-drag-handle',
			placeholder: 'ui-sortable-placeholder',
			axis: 'y',
			cursor: 'grabbing',
			opacity: 0.85,
			tolerance: 'pointer',
			forceHelperSize: true,
			update: function() {
				// Re-serialize immediately when ordering stops
				mcrpdUpdateFieldJSON();
			}
		});

		// =====================================================================
		// 5. Input & Toggle Listeners (Sync JSON on any change)
		// =====================================================================
		// Toggle Switch: Disable text inputs and width select when field is disabled
		$(document).on('change', '.mcrpd-field-enabled-toggle', function() {
			var $row = $(this).closest('tr');
			if ($(this).is(':checked')) {
				$row.removeClass('mcrpd-field-disabled');
				$row.find('input[type="text"], select').prop('disabled', false);
			} else {
				$row.addClass('mcrpd-field-disabled');
				$row.find('input[type="text"], select').prop('disabled', true);
			}
			mcrpdUpdateFieldJSON();
		});

		// Label & Placeholder Inputs & Width Select
		$(document).on('change keyup input', '.mcrpd-field-label-input, .mcrpd-field-placeholder-input, .mcrpd-field-width-select', function() {
			mcrpdUpdateFieldJSON();
		});

		// Ensure serialize runs before form submission as final validation step
		$('form').on('submit', function() {
			mcrpdUpdateFieldJSON();
		});

		// =====================================================================
		// 6. Reset overrides Button
		// =====================================================================
		$('.mcrpd-reset-fields-btn').on('click', function(e) {
			e.preventDefault();

			var confirmMsg = (typeof mcmchk_admin_params !== 'undefined' && mcmchk_admin_params.i18n_confirm_reset)
				? mcmchk_admin_params.i18n_confirm_reset
				: 'Are you sure you want to restore all fields to their default configuration? This action will remove your customizations.';

			if (!confirm(confirmMsg)) {
				return;
			}

			// Clear the hidden JSON field and set reset flag
			$('#mcmchk_field_overrides_json').val('{}');
			$('#mcmchk_reset_fields_flag').val('1');

			// Submit form
			$(this).closest('form').submit();
		});

		// =====================================================================
		// 7. Color picker and Media uploader (General Settings tab)
		// =====================================================================
		if ($.fn.wpColorPicker) {
			$('.mcrpd-color-picker').wpColorPicker();
		}

		$('.mcrpd-media-upload').on('click', function(e) {
			e.preventDefault();
			var button = $(this);
			var selectTitle = (typeof mcmchk_admin_params !== 'undefined' && mcmchk_admin_params.i18n_select_logo) ? mcmchk_admin_params.i18n_select_logo : 'Select Logo';
			var buttonText  = (typeof mcmchk_admin_params !== 'undefined' && mcmchk_admin_params.i18n_use_image) ? mcmchk_admin_params.i18n_use_image : 'Use this image';

			var custom_uploader = wp.media({
				title: selectTitle,
				library: { type: 'image' },
				button: { text: buttonText },
				multiple: false
			}).on('select', function() {
				var attachment = custom_uploader.state().get('selection').first().toJSON();
				var $input = button.prev('input');
				$input.val(attachment.url).trigger('change');
			}).open();
		});

		// Live Logo Thumbnail Preview
		$(document).on('change input', '.mcrpd-media-url-input', function() {
			var url = $.trim($(this).val());
			var $previewWrap = $(this).closest('td').find('.mcrpd-logo-preview-wrap');
			if (url) {
				$previewWrap.find('.mcrpd-logo-preview-img').attr('src', url);
				$previewWrap.slideDown(150);
			} else {
				$previewWrap.slideUp(150);
			}
		});

		// =====================================================================
		// 8. Collapsible "Continue shopping" Button Settings (Thank You Page)
		// =====================================================================
		$('#mcmchk_custom_thankyou_btn_enabled').on('change', function() {
			if ($(this).is(':checked')) {
				$('#mcrpd-thankyou-btn-subfields').slideDown(180);
			} else {
				$('#mcrpd-thankyou-btn-subfields').slideUp(180);
			}
		});

	});

})(jQuery);
