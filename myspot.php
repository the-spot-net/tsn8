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
page_header($user->lang['INDEX'], true);
$template->set_filenames(array('body' => 'myspot.html'));
//make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));
page_footer();