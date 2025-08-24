/**
 * Settings Page JavaScript
 *
 * @package TheLibrary
 */

(function($) {
	'use strict';

	/**
	 * Handle field enabled/disabled state changes
	 */
	function toggleRequiredSetting($enabledCheckbox) {
		var $fieldSetting = $enabledCheckbox.closest('.wprl-field-setting');
		var $requiredSetting = $fieldSetting.find('.wprl-required-setting');
		var $requiredCheckbox = $requiredSetting.find('.wprl-field-required');

		if ($enabledCheckbox.is(':checked')) {
			// Field is enabled - show and enable required option
			$requiredSetting.show().removeClass('disabled');
			$requiredCheckbox.prop('disabled', false);
			console.log('Showing required setting for:', $enabledCheckbox.attr('name'));
		} else {
			// Field is disabled - hide required option and uncheck it
			$requiredSetting.hide().addClass('disabled');
			$requiredCheckbox.prop('checked', false).prop('disabled', true);
			console.log('Hiding required setting for:', $enabledCheckbox.attr('name'));
		}
	}

	/**
	 * Initialize settings page functionality
	 */
	function initSettingsPage() {
		console.log('Initializing WPRL Settings Page');

		// Find all field enabled checkboxes
		var $enabledCheckboxes = $('.wprl-field-enabled');
		console.log('Found ' + $enabledCheckboxes.length + ' field enabled checkboxes');

		// Bind change event to field enabled checkboxes
		$enabledCheckboxes.on('change', function() {
			console.log('Field enabled checkbox changed:', $(this).attr('name'));
			toggleRequiredSetting($(this));
		});

		// Initialize form state on page load
		$enabledCheckboxes.each(function() {
			toggleRequiredSetting($(this));
		});

		console.log('WPRL Settings Page initialized successfully');
	}

	// Initialize when document is ready
	$(document).ready(function() {
		initSettingsPage();
	});

})(jQuery);