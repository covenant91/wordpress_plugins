/**
 * Classic editor: toggle caption visibility and run test-connection AJAX.
 */
(function ($) {
	'use strict';

	// Show/hide caption textarea when checkbox changes.
	$(document).on('change', '.smp-platform-row input[type="checkbox"]', function () {
		var $wrap = $(this).closest('.smp-platform-row').find('.smp-caption-wrap');
		if ($(this).is(':checked')) {
			$wrap.slideDown(150);
		} else {
			$wrap.slideUp(150);
		}
	});

	// Live character count for caption textareas.
	$(document).on('input', '.smp-caption', function () {
		var $ta    = $(this);
		var limit  = parseInt($ta.data('limit'), 10);
		var count  = $ta.val().length;
		var $count = $ta.siblings('.smp-char-count');
		$count.text(count + ' / ' + limit);
		if (count > limit) {
			$count.addClass('smp-over-limit');
		} else {
			$count.removeClass('smp-over-limit');
		}
	});

	// Test Connection buttons.
	$(document).on('click', '.smp-test-connection', function (e) {
		e.preventDefault();
		var $btn      = $(this);
		var platform  = $btn.data('platform');
		var $result   = $btn.siblings('.smp-test-result');

		$btn.prop('disabled', true).text(wspAdmin.testing);
		$result.text('').removeClass('smp-ok smp-fail');

		$.post(ajaxurl, {
			action:   'wsp_test_connection',
			platform: platform,
			nonce:    wspAdmin.nonce
		}, function (response) {
			$btn.prop('disabled', false).text(wspAdmin.testLabel);
			if (response.success) {
				$result.text(response.data.message).addClass('smp-ok');
			} else {
				$result.text(response.data.message).addClass('smp-fail');
			}
		}).fail(function () {
			$btn.prop('disabled', false).text(wspAdmin.testLabel);
			$result.text(wspAdmin.error).addClass('smp-fail');
		});
	});

}(jQuery));
