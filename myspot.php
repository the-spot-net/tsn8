<?php

/********************************************************************
 *	MySpot.php
 *	By: Chris Chiles
 *	Date: 3/31/08
 *	Version: 7.00
 *	(C) 2006-2008 - The-Spot.Network LLC
 ********************************************************************/
 
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include_once($phpbb_root_path . 'common.' . $phpEx);
include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('search');
//$user->setup('viewforum');

//module imports
	include_once($phpbb_root_path . 'modules/newposts.php');
	include_once($phpbb_root_path . 'modules/birthdays.php');
	//include_once($phpbb_root_path . 'modules/whatpulse.php');
	include_once($phpbb_root_path . 'module_quickquips.php');
	//include_once($phpbb_root_path . 'modules/mini-index.php');
	//display_forums('', $config['load_moderators']);

//display the page
	page_header(($l_search_title) ? $l_search_title : $user->lang['MYSPOT']);

	$template->set_filenames(array('body' => 'myspot.html'));
	
	make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));

	page_footer();
?>