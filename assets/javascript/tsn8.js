$(document).ready(function () {
	// Implement jQuery UI Buttons & Button Sets
	$(function () {
		$('.buttonset').buttonset();
		$('[data-mod="tsn8_button"]')
			.removeClass('button2')
			.removeClass('button1')
			.css({'width': 'auto'})
			.button({
				text: false
			});
		$('.button2, .button1')
			.removeClass('button2')
			.removeClass('button1')
			.css({'width': 'auto'})
			.button();
		$('select').selectmenu();

		// For posting pages
		$('#font_select').selectmenu({
			change: function (event, ui) {
				var value = $(this).val();
				bbfontstyle('[size=' + value + ']', '[/size]');
			}
		});

		$('#foreground').ColorPicker({
			onSubmit: function (hsb, hex, rgb, el) {
				bbfontstyle('[color=#' + hex + ']', '[/color]');
				$(el).ColorPickerHide();
			}
		});

		$("#smiley_menu").on('click', function (e) {
			e.preventDefault();
			$("#tsn8_newpost_smiley_wrapper").fadeToggle();
		});

		$("#tsn8_newpost_smiley_wrapper").on('mouseleave', function () {
			$(this).fadeOut();
		});

		$('.tsn8_icon_smiley').on('click', function () {
			var code = $(this).attr('data-code');
			insert_text(code, true);
			$('#tsn8_newpost_smiley_wrapper').fadeOut();
		});
	});
});

function getUrlParameter(sParam) {
	var sPageURL = window.location.search.substring(1);
	var sURLVariables = sPageURL.split('&');
	var result = null;

	for (var i = 0; i < sURLVariables.length; i++) {
		var sParameterName = sURLVariables[i].split('=');
		if (sParameterName[0] == sParam) {
			result = sParameterName[1];
			break;
		}
	}

	return result;
}