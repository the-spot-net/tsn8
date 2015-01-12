$(document).ready(function () {
	// Implement jQuery UI Buttons & Button Sets
	$(function () {
		$('button[data-mod*="tsn8_button"]').button({
			text: false
		});
		$('input[data-mod*="tsn8_button"]').button({
			text: false
		});
		$(".buttonset").buttonset();

	});
});

function getUrlParameter(sParam) {
	var sPageURL = window.location.search.substring(1);
	var sURLVariables = sPageURL.split('&');
	var result = null;

	for(var i = 0; i < sURLVariables.length; i++){
		var sParameterName = sURLVariables[i].split('=');
		if(sParameterName[0] == sParam) {
			result = sParameterName[1];
			break;
		}
	}

	return result;
}