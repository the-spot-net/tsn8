<?php
/**
 * Created by thepizzy.net
 * User: @neotsn
 * Date: 1/17/2015
 * Time: 1:12 PM
 */

header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // disable IE caching
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");
header("Pragma: no-cache");

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : '../../../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
//$phpbb_root_path = './phorums/';

$user->session_begin();
$auth->acl($user->data);
$user->setup(array('memberlist', 'groups'));

// Grab the latest topic ID for TSN Special Report
$sql = "SELECT MAX(topic_id) AS topic_id FROM " . TOPICS_TABLE . " WHERE forum_id = 14";
$result = $db->sql_query($sql);

$news_topic_id = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$news_topic_id) {
	trigger_error('NO_TSNSR_1');
}

// Grab the post information and put to an array
$sql = "SELECT t.topic_title, t.topic_views, t.topic_posts_approved, t.topic_time, t.topic_poster, p.enable_smilies, p.post_id, p.post_text, p.bbcode_uid, p.bbcode_bitfield, u.username, u.user_colour
	FROM " . TOPICS_TABLE . " t, " . POSTS_TABLE . " p, " . USERS_TABLE . " u
	WHERE t.forum_id = 14
		AND t.topic_id = " . $news_topic_id['topic_id'] . "
		AND p.topic_id = t.topic_id
		AND p.post_id = t.topic_first_post_id
		AND u.user_id = t.topic_poster";
$result = $db->sql_query($sql);
$news_info = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$news_info) {
	trigger_error('NO_TSNSR_3');
}

/*
 * Available Variable List:
 * $news_info['post_id'] ;
 * $news_info['topic_title'];
 * $news_info['topic_poster'];
 * $news_info['post_text'];
 * $news_info['bbcode_uid'];
 * $news_info['bbcode_bitfield'];
 * $news_info['enable_smilies'];
 * $news_info['poster_id'];
 * $news_info['username'];
 * $news_info['topic_views'];
 * $news_info['topic_posts_approved'];
 * $news_info['topic_time'];
 */

$message = $news_info['post_text'];
$user_name = $news_info['username'];

// Grab the user's avatar for the newest topic
$sql = "SELECT u.user_avatar, u.user_avatar_type, u.user_avatar_width, u.user_avatar_height
	FROM " . USERS_TABLE . " u
	WHERE u.user_id = " . $news_info['topic_poster'];
$result = $db->sql_query($sql);
$news_avatar = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$news_avatar) {
	trigger_error('NO_TSNSR_4');
}

// Prepare the avatar info array
$avatar_info = array(
	'avatar'        => $news_avatar['user_avatar'],
	'avatar_type'   => $news_avatar['user_avatar_type'],
	'avatar_width'  => $news_avatar['user_avatar_width'],
	'avatar_height' => $news_avatar['user_avatar_height'],
);

$avatar_image = preg_replace('/(\.\.\/)+?/', './', phpbb_get_user_avatar($avatar_info));

// Prepare the Message Subject
$subject = censor_text($news_info['post_subject']);

// Prepare the Message Excerpt
// 50 words, no bbcode, no spaces
$message = $news_info['post_text'];
//$message = preg_replace('/\s+?/', ' ', $message); // Collapse spaces
$message = generate_text_for_display($message, $news_info['bbcode_uid'], $news_info['bbcode_bitfield'], 1); // Replace UIDs with BBCode
//strip_bbcode($message); // Remove BBCode

// Smart Excerpt - pull out X words
$allowed_words = 140;
$used_words = explode(' ', $message);
if (sizeof($used_words) > $allowed_words) {
	$excerpt = implode(' ', array_slice($used_words, 0, $allowed_words)) . '... ';
} else {
	$excerpt = implode(' ', $used_words) . ' ';
}
$message = $excerpt;

$phpbb_root_path = './';

$template->assign_vars(array(
	'I_AVATAR_IMG'       => $avatar_image,

	'L_HEADLINE'         => $news_info["topic_title"],
	'L_POST_AUTHOR'      => get_username_string('full', $news_info['topic_poster'], $news_info['username'], $news_info['user_colour']),
	'L_POST_BODY'        => $message,
	'L_POST_DATE'        => $user->format_date($news_info['topic_time']),
	'L_POST_META'        => sprintf($user->lang['SPECIAL_REPORT_VIEWS_COMMENTS_COUNT'], $news_info['topic_views'], $news_info['topic_posts_approved']),

	'U_CONTINUE_READING' => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "p=" . $news_info['post_id']),
	'U_HEADLINE'         => append_sid("{$phpbb_root_path}viewtopic.$phpEx", "p=" . $news_info['post_id']),
	'U_USER_PROFILE'     => append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&u=" . $news_info['topic_poster']),
));

page_header($user->lang['MYSPOT']);

$template->set_filenames(array(
		'body' => 'modules/special_report.html'
	)
);

page_footer();