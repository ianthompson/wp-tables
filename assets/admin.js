(function ($) {
	'use strict';

	var frame;
	var $attachmentId = $('#wpt-csv-attachment-id');
	var $fileLabel = $('#wpt-csv-file-label');
	var $preview = $('#wpt-csv-preview');
	var $headerOption = $('#wpt-first-row-headers');
	var $fontSize = $('#wpt-font-size');
	var $removeButton = $('#wpt-remove-csv');

	function isCsv(attachment) {
		var filename = attachment.filename || '';
		return /\.csv$/i.test(filename);
	}

	function previewMessage(message) {
		$preview.html($('<p />').text(message));
	}

	function loadPreview() {
		var attachmentId = Number($attachmentId.val());

		if (!attachmentId) {
			previewMessage(wptAdmin.emptyPreview);
			return;
		}

		previewMessage(wptAdmin.loadingPreview);

		$.post(wptAdmin.ajaxUrl, {
			action: wptAdmin.action,
			nonce: wptAdmin.nonce,
			attachmentId: attachmentId,
			hasHeaders: $headerOption.is(':checked') ? 1 : 0,
			fontSize: $fontSize.val()
		}).done(function (response) {
			if (response.success && response.data.html) {
				$preview.html(response.data.html);
				return;
			}

			previewMessage(wptAdmin.previewError);
		}).fail(function (response) {
			var message = response.responseJSON && response.responseJSON.data && response.responseJSON.data.message;
			previewMessage(message || wptAdmin.previewError);
		});
	}

	$('#wpt-select-csv').on('click', function () {
		if (frame) {
			frame.open();
			return;
		}

		frame = wp.media({
			title: wptAdmin.chooseTitle,
			button: { text: wptAdmin.chooseButton },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();

			if (!isCsv(attachment)) {
				window.alert(wptAdmin.invalidFile);
				return;
			}

			$attachmentId.val(attachment.id);
			$fileLabel.text(attachment.title || attachment.filename);
			$removeButton.removeClass('is-hidden');
			loadPreview();
		});

		frame.open();
	});

	$removeButton.on('click', function () {
		$attachmentId.val('');
		$fileLabel.text(wptAdmin.noFile);
		$removeButton.addClass('is-hidden');
		loadPreview();
	});

	$headerOption.add($fontSize).on('change', loadPreview);
})(jQuery);
