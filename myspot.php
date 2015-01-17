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
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

//module imports
//include_once($phpbb_root_path . 'modules/newposts.php');
//include_once(append_sid($phpbb_root_path . 'tsn/myspot/modules/new_posts.'.$phpEx));
//include_once($phpbb_root_path . 'module_quickquips.php');
//include_once($phpbb_root_path . 'modules/mini-index.php');

// display the page
page_header($user->lang['MYSPOT'], true);
$template->set_filenames(array('body' => 'myspot.html'));
$template->assign_vars(array(
	'S_ALLOW_MYSPOT_LOGIN' => !empty($config['tsn8_activate_myspot_login']),
	'S_ALLOW_MINI_FORUMS' => !empty($config['tsn8_activate_mini_forums']),
	'S_ALLOW_SPECIAL_REPORT' => !empty($config['tsn8_activate_special_report']),
	'S_ALLOW_NEW_POSTS' => !empty($config['tsn8_activate_newposts']),
));
page_footer();