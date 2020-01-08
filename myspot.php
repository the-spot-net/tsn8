<?php

/**
 * Created by thepizzy.net
 * User: @neotsn
 * Date: 1/11/2015
 * Time: 8:12 PM
 */
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include_once($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

//module imports
//include_once($phpbb_root_path . 'modules/newposts.php');
//include_once(append_sid($phpbb_root_path . 'tsn/myspot/modules/new_posts.'.$phpEx));
//include_once($phpbb_root_path . 'module_quickquips.php');
include_once($phpbb_root_path . 'tsn_module_mini_forums.php');

// display the page
page_header($user->lang['MYSPOT'], true);
$template->set_filenames(array('body' => 'myspot.html'));
$template->assign_vars(array(
	'S_ALLOW_MINI_PROFILE' => !empty($config['tsn_enable_miniprofile']),
	'S_ALLOW_MYSPOT_LOGIN' => !empty($config['tsn_enable_myspot']),
	'S_ALLOW_MINI_FORUMS' => !empty($config['tsn_enable_miniforums']),
	'S_ALLOW_SPECIAL_REPORT' => !empty($config['tsn_enable_specialreport']),
	'S_ALLOW_NEW_POSTS' => !empty($config['tsn_enable_newposts']),
	'S_USER_ID' => $user->data['user_id'],
));
page_footer();
