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
 */
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);

$mode = 'viewprofile';

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup(array('memberlist', 'groups'));

// Setting a variable to let the style designer know where he is...
$template->assign_var('S_IN_MEMBERLIST', true);

// Grab data
$action = $request->variable('action', '');
$user_id = $request->variable('u', ANONYMOUS);
$username = $request->variable('un', '', true);
$group_id = $request->variable('g', 0);
$topic_id = $request->variable('t', 0);

$start = $request->variable('start', 0);
$submit = (isset($_POST['submit'])) ? true : false;

$default_key = 'c';
$sort_key = $request->variable('sk', $default_key);
$sort_dir = $request->variable('sd', 'a');

// Can this user view profiles/memberlist?
if (!$auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel')) {
    if ($user->data['user_id'] != ANONYMOUS) {
        send_status_line(403, 'Forbidden');
        trigger_error('NO_VIEW_USERS');
    }

    login_box('', ((isset($user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)])) ? $user->lang['LOGIN_EXPLAIN_' . strtoupper($mode)] : $user->lang['LOGIN_EXPLAIN_MEMBERLIST']));
}

// Display a profile
if ($user_id == ANONYMOUS && !$username) {
    trigger_error('NO_USER');
}

// Get user...
$sql = 'SELECT *
			FROM ' . USERS_TABLE . '
			WHERE ' . (($username) ? "username_clean = '" . $db->sql_escape(utf8_clean_string($username)) . "'" : "user_id = $user_id");
$result = $db->sql_query($sql);
$member = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$member) {
    trigger_error('NO_USER');
}

// a_user admins and founder are able to view inactive users and bots to be able to manage them more easily
// Normal users are able to see at least users having only changed their profile settings but not yet reactivated.
if (!$auth->acl_get('a_user') && $user->data['user_type'] != USER_FOUNDER) {
    if ($member['user_type'] == USER_IGNORE) {
        trigger_error('NO_USER');
    } else if ($member['user_type'] == USER_INACTIVE && $member['user_inactive_reason'] != INACTIVE_PROFILE) {
        trigger_error('NO_USER');
    }
}

$user_id = (int)$member['user_id'];

// Get group memberships
// Also get visiting user's groups to determine hidden group memberships if necessary.
$auth_hidden_groups = ($user_id === (int)$user->data['user_id'] || $auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? true : false;
$sql_uid_ary = ($auth_hidden_groups) ? array($user_id) : array($user_id, (int)$user->data['user_id']);

// Do the SQL thang
$sql = 'SELECT g.group_id, g.group_name, g.group_type, ug.user_id
			FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
			WHERE ' . $db->sql_in_set('ug.user_id', $sql_uid_ary) . '
				AND g.group_id = ug.group_id
				AND ug.user_pending = 0';
$result = $db->sql_query($sql);

// Divide data into profile data and current user data
$profile_groups = $user_groups = array();
while ($row = $db->sql_fetchrow($result)) {
    $row['user_id'] = (int)$row['user_id'];
    $row['group_id'] = (int)$row['group_id'];

    if ($row['user_id'] == $user_id) {
        $profile_groups[] = $row;
    } else {
        $user_groups[$row['group_id']] = $row['group_id'];
    }
}
$db->sql_freeresult($result);

// Filter out hidden groups and sort groups by name
$group_data = $group_sort = array();
foreach ($profile_groups as $row) {
    if (!$auth_hidden_groups && $row['group_type'] == GROUP_HIDDEN && !isset($user_groups[$row['group_id']])) {
        // Skip over hidden groups the user cannot see
        continue;
    }

    $row['group_name'] = $group_helper->get_name($row['group_name']);

    $group_sort[$row['group_id']] = utf8_clean_string($row['group_name']);
    $group_data[$row['group_id']] = $row;
}
unset($profile_groups);
unset($user_groups);
asort($group_sort);

$group_options = '';
foreach ($group_sort as $group_id => $null) {
    $row = $group_data[$group_id];

    $group_options .= '<option value="' . $row['group_id'] . '" ' . (($row['group_id'] == $member['group_id']) ? ' selected="selected"' : '') . ' >' . $row['group_name'] . '</option>';
}
unset($group_data);
unset($group_sort);

// What colour is the zebra
$sql = 'SELECT friend, foe
			FROM ' . ZEBRA_TABLE . "
			WHERE zebra_id = $user_id
				AND user_id = {$user->data['user_id']}";

$result = $db->sql_query($sql);
$row = $db->sql_fetchrow($result);
$foe = ($row['foe']) ? true : false;
$friend = ($row['friend']) ? true : false;
$db->sql_freeresult($result);

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

if ($config['load_user_activity']) {
    display_user_activity($member);
}

// Do the relevant calculations
$memberdays = max(1, round((time() - $member['user_regdate']) / 86400));
$posts_per_day = $member['user_posts'] / $memberdays;
$percentage = ($config['num_posts']) ? min(100, ($member['user_posts'] / $config['num_posts']) * 100) : 0;

if ($member['user_sig']) {
    $parse_flags = ($member['user_sig_bbcode_bitfield'] ? OPTION_FLAG_BBCODE : 0) | OPTION_FLAG_SMILIES;
    $member['user_sig'] = generate_text_for_display($member['user_sig'], $member['user_sig_bbcode_uid'], $member['user_sig_bbcode_bitfield'], $parse_flags, true);
}

// We need to check if the modules 'zebra' ('friends' & 'foes' mode),  'notes' ('user_notes' mode) and  'warn' ('warn_user' mode) are accessible to decide if we can display appropriate links
$zebra_enabled = $friends_enabled = $foes_enabled = $user_notes_enabled = $warn_user_enabled = false;

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
    $zebra_enabled = ($module->loaded('ucp_zebra')) ? true : false;
    $friends_enabled = ($module->loaded('ucp_zebra', 'friends')) ? true : false;
    $foes_enabled = ($module->loaded('ucp_zebra', 'foes')) ? true : false;

    unset($module);
}

// Custom Profile Fields
$profile_fields = array();
if ($config['load_cpf_viewprofile']) {
    $cp = $phpbb_container->get('profilefields.manager');
    $profile_fields = $cp->grab_profile_fields_data($user_id);
    $profile_fields = (isset($profile_fields[$user_id])) ? $cp->generate_profile_fields_template_data($profile_fields[$user_id]) : array();
}

/**
 * Modify user data before we display the profile
 *
 * @event   core.memberlist_view_profile
 * @var    array    member                    Array with user's data
 * @var    bool    user_notes_enabled        Is the mcp user notes module enabled?
 * @var    bool    warn_user_enabled        Is the mcp warnings module enabled?
 * @var    bool    zebra_enabled            Is the ucp zebra module enabled?
 * @var    bool    friends_enabled            Is the ucp friends module enabled?
 * @var    bool    foes_enabled            Is the ucp foes module enabled?
 * @var    bool    friend                    Is the user friend?
 * @var    bool    foe                        Is the user foe?
 * @var    array    profile_fields            Array with user's profile field data
 * @since   3.1.0-a1
 * @changed 3.1.0-b2 Added friend and foe status
 * @changed 3.1.0-b3 Added profile fields data
 */
$vars = array(
    'member',
    'user_notes_enabled',
    'warn_user_enabled',
    'zebra_enabled',
    'friends_enabled',
    'foes_enabled',
    'friend',
    'foe',
    'profile_fields',
);
extract($phpbb_dispatcher->trigger_event('core.memberlist_view_profile', compact($vars)));

$template->assign_vars(phpbb_show_profile($member, $user_notes_enabled, $warn_user_enabled));

// If the user has m_approve permission or a_user permission, then list then display unapproved posts
if ($auth->acl_getf_global('m_approve') || $auth->acl_get('a_user')) {
    $sql = 'SELECT COUNT(post_id) AS posts_in_queue
				FROM ' . POSTS_TABLE . '
				WHERE poster_id = ' . $user_id . '
					AND ' . $db->sql_in_set('post_visibility', array(ITEM_UNAPPROVED, ITEM_REAPPROVE));
    $result = $db->sql_query($sql);
    $member['posts_in_queue'] = (int)$db->sql_fetchfield('posts_in_queue');
    $db->sql_freeresult($result);
} else {
    $member['posts_in_queue'] = 0;
}

$template->assign_vars(array(
    'L_POSTS_IN_QUEUE' => $user->lang('NUM_POSTS_IN_QUEUE', $member['posts_in_queue']),

    'POSTS_DAY'     => $user->lang('POST_DAY', $posts_per_day),
    'POSTS_PCT'     => $user->lang('POST_PCT', $percentage),
    'POSTS_DAY_NUM' => number_format($posts_per_day, 2),
    'POSTS_PCT_NUM' => number_format($percentage, 2),

    'SIGNATURE'      => $member['user_sig'],
    'POSTS_IN_QUEUE' => $member['posts_in_queue'],

    'PM_IMG'            => $user->img('icon_contact_pm', $user->lang['SEND_PRIVATE_MESSAGE']),
    'L_SEND_EMAIL_USER' => $user->lang('SEND_EMAIL_USER', $member['username']),
    'EMAIL_IMG'         => $user->img('icon_contact_email', $user->lang['EMAIL']),
    'JABBER_IMG'        => $user->img('icon_contact_jabber', $user->lang['JABBER']),
    'SEARCH_IMG'        => $user->img('icon_user_search', $user->lang['SEARCH']),

    'S_PROFILE_ACTION' => append_sid("{$phpbb_root_path}memberlist.$phpEx", 'mode=group'),
    'S_GROUP_OPTIONS'  => $group_options,
    'S_CUSTOM_FIELDS'  => (isset($profile_fields['row']) && sizeof($profile_fields['row'])) ? true : false,

    'U_USER_ADMIN' => ($auth->acl_get('a_user')) ? append_sid("{$phpbb_admin_path}index.$phpEx", 'i=users&amp;mode=overview&amp;u=' . $user_id, true, $user->session_id) : '',
    'U_USER_BAN'   => ($auth->acl_get('m_ban') && $user_id != $user->data['user_id']) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=ban&amp;mode=user&amp;u=' . $user_id, true, $user->session_id) : '',
    'U_MCP_QUEUE'  => ($auth->acl_getf_global('m_approve')) ? append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=queue', true, $user->session_id) : '',

    'U_SWITCH_PERMISSIONS' => ($auth->acl_get('a_switchperm') && $user->data['user_id'] != $user_id) ? append_sid("{$phpbb_root_path}ucp.$phpEx", "mode=switch_perm&amp;u={$user_id}&amp;hash=" . generate_link_hash('switchperm')) : '',
    'U_EDIT_SELF'          => ($user_id == $user->data['user_id'] && $auth->acl_get('u_chgprofileinfo')) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=ucp_profile&amp;mode=profile_info') : '',

    'S_USER_NOTES'    => ($user_notes_enabled) ? true : false,
    'S_WARN_USER'     => ($warn_user_enabled) ? true : false,
    'S_ZEBRA'         => ($user->data['user_id'] != $user_id && $user->data['is_registered'] && $zebra_enabled) ? true : false,
    'U_ADD_FRIEND'    => (!$friend && !$foe && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
    'U_ADD_FOE'       => (!$friend && !$foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;mode=foes&amp;add=' . urlencode(htmlspecialchars_decode($member['username']))) : '',
    'U_REMOVE_FRIEND' => ($friend && $friends_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;usernames[]=' . $user_id) : '',
    'U_REMOVE_FOE'    => ($foe && $foes_enabled) ? append_sid("{$phpbb_root_path}ucp.$phpEx", 'i=zebra&amp;remove=1&amp;mode=foes&amp;usernames[]=' . $user_id) : '',

    'U_CANONICAL' => generate_board_url() . '/' . append_sid("memberlist.$phpEx", 'mode=viewprofile&amp;u=' . $user_id, true, ''),
));

if (!empty($profile_fields['row'])) {
    $template->assign_vars($profile_fields['row']);
}

if (!empty($profile_fields['blockrow'])) {
    foreach ($profile_fields['blockrow'] as $field_data) {
        $template->assign_block_vars('custom_fields', $field_data);
    }
}

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

    $template->assign_vars(array(
            'S_USER_INACTIVE'      => true,
            'USER_INACTIVE_REASON' => $inactive_reason
        )
    );
}

// Output the page
page_header($user->lang['MYSPOT']);

$template->set_filenames(array(
    'body' => 'modules/mini_profile.html'
));

page_footer();
