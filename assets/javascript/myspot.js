/**
 * Created by @neotsn on 3/24/14.
 */

$(document).ready(function () {

	var login_status = $("#login_status").val();
	//var sid = getUrlParameter('sid');

	//if (login_status == 1) {
	//	// Get mini profile
	//	ajax_fetch_html('module_mini_profile.php?', 'divMiniProfile', false);
	//}
	//
	//// Get tsn Special Report
	//ajax_fetch_html('module_news.php?', 'divSpecialReport', false);
	//
	//// Get forum index
	//ajax_fetch_html('module_mini_index.php?', 'divMiniForumIndex', false);

	// Get new posts, if the conainer div was drawn (extension-based setting)
	if($('#divNewPosts')) {
		ajax_fetch_html('tsn/myspot/modules/new_posts.php?', 'divNewPosts', true);
	}

});

function ajax_fetch_html(url, elementid, refresh) {
	$.ajax({
		url: url + "sid=" +Math.random()
	}).done(function (data) {
		$('#' + elementid).html(data);
		if (refresh) {
			setTimeout(function () {
				ajax_fetch_html(url, elementid, refresh);
			}, 30000);
		}
	});
}
