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

	// Get new posts
	//ajax_fetch_html('module_update_posts.php?', 'divNewPosts', true);
	//ajax_fetch_html('tsn/myspot/modules/new_posts.php?', 'divNewPosts', sid, true);
	ajax_fetch_html('tsn/myspot/modules/new_posts.php?', 'divNewPosts', true);

});

//function ajax_fetch_html(url, elementid, sid, refresh) {
function ajax_fetch_html(url, elementid, refresh) {
	//url: url + "sid=" +sid
	$.ajax({
		url: url + "sid=" +Math.random()
	}).done(function (data) {
		console.log(data);
		$('#' + elementid).html(data);
		if (refresh) {
			setTimeout(function () {
				//ajax_fetch_html(url, elementid, sid, refresh);
				ajax_fetch_html(url, elementid, refresh);
			}, 30000);
		}
		console.log('fetched');
	});
}
