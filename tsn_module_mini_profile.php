<?php
/**
 * Created by thepizzy.net
 * User: @neotsn
 * Date: 1/17/2015
 * Time: 5:14 PM
 */

use phpbb\auth\auth;
use phpbb\config\db;
use phpbb\db\driver\driver_interface;
use phpbb\di\container_builder;
use phpbb\event\dispatcher;
use phpbb\profilefields\manager;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @var auth                               $auth
 * @var user                               $user
 * @var template                           $template
 * @var request_interface                  $request
 * @var driver_interface                   $db
 * @var db                                 $config
 * @var container_builder|ContainerBuilder $phpbb_container
 * @var manager                            $cp
 * @var dispatcher                         $phpbb_dispatcher
 * @var string                             $phpbb_admin_path
 * @var \phpbb\group\helper                $group_helper
 */
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup(['memberlist', 'groups']);

// Setting a variable to let the style designer know where he is...
$template->assign_var('S_IN_MEMBERLIST', true);

// Grab data
$user_id = $request->variable('u', ANONYMOUS);
$username = $request->variable('un', '', true);
/*
// Can this user view profiles/memberlist?
if (!$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel')) {

    $mode = 'viewprofile';

    if ($user->data['user_id'] != ANONYMOUS) {
        send_status_line(403, 'Forbidden');
        trigger_error('NO_VIEW_USERS');
    }

    login_box('', ((isset($user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)])) ? $user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)] : $user->lang['LOGIN_EXPLAIN_MEMBERLIST']));
}*/

// Display a profile
if ($user_id == ANONYMOUS && !$username) {
    trigger_error('NO_USER');
}

// Get user...
$sql_array = [
    'SELECT' => 'u.*',
    'FROM'   => [
        USERS_TABLE => 'u',
    ],
    'WHERE'  => (($username) ? "u.username_clean = '" . $db->sql_escape(utf8_clean_string($username)) . "'" : "u.user_id = $user_id"),
];
$result = $db->sql_query($db->sql_build_query('SELECT', $sql_array));
$member = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$member) {
    trigger_error('NO_USER');
}

$user_id = (int)$member['user_id'];

// Necessary for phpbb_show_profile()
if ($config['load_onlinetrack']) {
    $sql = 'SELECT MAX(session_time) AS session_time, MIN(session_viewonline) AS session_viewonline
				FROM ' . SESSIONS_TABLE . "
				WHERE session_user_id = $user_id";
    $result = $db->sql_query($sql);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);

    $member['session_time'] = (isset($row['session_time'])) ? $row['session_time'] : 0;
    $member['session_viewonline'] = (isset($row['session_viewonline'])) ? $row['session_viewonline'] : 0;
    unset($row);
}

// We need to check if the modules 'notes' ('user_notes' mode) and  'warn' ('warn_user' mode) are accessible to decide if we can display appropriate links
$user_notes_enabled = $warn_user_enabled = false;

// Only check if the user is logged in
if ($user->data['is_registered']) {

    if (!class_exists('p_master')) {
        include($phpbb_root_path . 'includes/functions_module.' . $phpEx);
    }

    $module = new p_master();
    $module->list_modules('ucp');
    $module->list_modules('mcp');

    $user_notes_enabled = ($module->loaded('mcp_notes', 'user_notes')) ? true : false;
    $warn_user_enabled = ($module->loaded('mcp_warn', 'warn_user')) ? true : false;

    unset($module);
}

// This is where name, rank and avatar come from...
$template->assign_vars(phpbb_show_profile($member, $user_notes_enabled, $warn_user_enabled));

// Do the relevant calculations
$posts_per_day = $member['user_posts'] / max(1, round((time() - $member['user_regdate']) / 86400));
$percentage = ($config['num_posts']) ? min(100, ($member['user_posts'] / $config['num_posts']) * 100) : 0;

$template->assign_vars([
    'POSTS_DAY'     => $user->lang('POST_DAY', $posts_per_day),
    'POSTS_PCT'     => $user->lang('POST_PCT', $percentage),
    'POSTS_DAY_NUM' => number_format($posts_per_day, 2),
    'POSTS_PCT_NUM' => number_format($percentage, 2),

    'U_USER_ADMIN' => ($auth->acl_get('a_user')) ? append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview&amp;u=' . $user_id, true, $user->session_id) : '',
]);

// Inactive reason/account?
if ($member['user_type'] == USER_INACTIVE) {
    $user->add_lang('acp/common');

    $inactive_reason = $user->lang['INACTIVE_REASON_UNKNOWN'];

    switch ($member['user_inactive_reason']) {
        case INACTIVE_REGISTER:
            $inactive_reason = $user->lang['INACTIVE_REASON_REGISTER'];
            break;

        case INACTIVE_PROFILE:
            $inactive_reason = $user->lang['INACTIVE_REASON_PROFILE'];
            break;

        case INACTIVE_MANUAL:
            $inactive_reason = $user->lang['INACTIVE_REASON_MANUAL'];
            break;

        case INACTIVE_REMIND:
            $inactive_reason = $user->lang['INACTIVE_REASON_REMIND'];
            break;
    }

    $template->assign_vars([
        'S_USER_INACTIVE'      => true,
        'USER_INACTIVE_REASON' => $inactive_reason,
    ]);
}

// Output the page
page_header($user->lang['MYSPOT']);

$template->set_filenames([
    'body' => 'modules/mini_profile.html',
]);

page_footer();
