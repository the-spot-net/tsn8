$(document).ready(function () {
	// Implement jQuery UI Buttons & Button Sets
	$(function () {
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

		$('input[data-mod="tsn8_checkbox"]').each(function (ele) {
			$(this).button({
				icons: {
					primary: ($(this).is(':checked')) ? 'ui-icon-check' : 'ui-icon-blank'
				},
				text: true
			});

		}).change(function () {
			$(this).button("option", {
				icons: {
					primary: (this.checked) ? 'ui-icon-check' : 'ui-icon-blank'
				},
				text: true
			});
		});
		$('.buttonset').buttonset();

		$('select').selectmenu({
			change: function () {
				var sourceElementId = $(this).attr('id').replace('-button', ''),
					$source = $('#' + sourceElementId);

				$source.val($(this).val()); // Update the source selection
				$source.children().each(function () {
					var $this = $(this),
						$setting = $($this.data('toggle-setting'));
					$setting.toggle($this.is(':selected'));
				});
			}
		});

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

		// mobile sticky nav
		var mn = $(".tsn8_mobile_nav"),
			mns = "tsn8_mobile_nav_scrolled";

		$(window).scroll(function () {
			if ($(this).scrollTop() >= 50) {
				mn.addClass(mns);
			} else {
				mn.removeClass(mns);
			}
		});

		$(document).on('click', '.tsn8_expand', function() {
			var targetClass = $(this).attr('data-expand-target');
			$('.'+targetClass).slideToggle();
			$(this).toggleClass('tsn8_icon_expand_down').toggleClass('tsn8_icon_expand_up');
		})

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