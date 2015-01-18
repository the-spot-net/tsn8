/**
 * Created by @neotsn on 3/24/14.
 */

$(document).ready(function () {

	var login_status = $("#login_status").val();
	var user_id = $("#login_userid").val();

	// Get Mini Profile, if logged in and container exists
	if (login_status == 1 && $('#divMiniProfile')) {
		// Get mini profile
		ajax_fetch_html('tsn_module_mini_profile.php?u='+user_id, 'divMiniProfile', false);
	}

	// Get mini forums, if the container div was drawn (extension-based setting)
	if($('#divMiniForumIndex')) {
		ajax_fetch_html('tsn_module_mini_forums.php?s=1', 'divMiniForumIndex', false);
	}

	// Get tsnSpecialReport, if the container div was drawn (extension-based setting)
	if($('#divSpecialReport')) {
		ajax_fetch_html('tsn_module_special_report.php', 'divSpecialReport', false);
	}

	// Get new posts, if the conainer div was drawn (extension-based setting)
	if($('#divNewPosts')) {
		ajax_fetch_html('tsn_module_new_posts.php', 'divNewPosts', true);
	}

});

function ajax_fetch_html(url, elementid, refresh) {
	$.ajax({
		url: url
	}).done(function (data) {
		$('#' + elementid).html(data);
		if (refresh) {
			setTimeout(function () {
				ajax_fetch_html(url, elementid, refresh);
			}, 30000);
		}
	});
}
