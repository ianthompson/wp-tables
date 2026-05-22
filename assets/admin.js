(function ($) {
	'use strict';

	var frame;
	var $attachmentId = $('#wpt-csv-attachment-id');
	var $fileLabel = $('#wpt-csv-file-label');
	var $preview = $('#wpt-csv-preview');
	var $columnWidths = $('#wpt-column-widths');
	var $headerOption = $('#wpt-first-row-headers');
	var $fontSize = $('#wpt-font-size');
	var $customFontSize = $('#wpt-custom-font-size');
	var $fontFamily = $('#wpt-font-family');
	var $borderStyle = $('#wpt-border-style');
	var $borderColor = $('#wpt-border-color');
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
			fontSize: $fontSize.val(),
			customFontSize: $customFontSize.val(),
			fontFamily: $fontFamily.val(),
			borderStyle: $borderStyle.val(),
			borderColor: $borderColor.val(),
			columnWidths: getColumnWidths()
		}).done(function (response) {
			if (response.success && response.data.html) {
				$preview.html(response.data.html);
				$columnWidths.html(response.data.widthControls);
				return;
			}

			previewMessage(wptAdmin.previewError);
		}).fail(function (response) {
			var message = response.responseJSON && response.responseJSON.data && response.responseJSON.data.message;
			previewMessage(message || wptAdmin.previewError);
		});
	}

	function getColumnWidths() {
		var widths = {};

		$columnWidths.find('.wpt-column-width').each(function (index) {
			var $row = $(this);
			widths[index] = {
				value: $row.find('input').val(),
				unit: $row.find('select').val()
			};
		});

		return widths;
	}

	function updateWidthInput() {
		var $row = $(this).closest('.wpt-column-width');
		var auto = $row.find('select').val() === 'auto';

		$row.find('input').prop('disabled', auto);
	}

	function updateCustomFontSize() {
		$customFontSize.prop('readonly', $fontSize.val() !== 'custom');
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

	$headerOption.add($fontFamily).add($borderStyle).add($borderColor).on('change', loadPreview);
	$fontSize.on('change', function () {
		updateCustomFontSize();
		loadPreview();
	});
	$customFontSize.on('change', loadPreview);
	$columnWidths.on('change', 'select', function () {
		updateWidthInput.call(this);

		if ($(this).val() === 'auto' || $(this).closest('.wpt-column-width').find('input').val()) {
			loadPreview();
		}
	});
	$columnWidths.on('change', 'input', loadPreview);
})(jQuery);
