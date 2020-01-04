<?php
/**
 * Created by thepizzy.net
 * User: @neotsn
 * Date: 1/16/2015
 * Time: 10:45 PM
 */

/**
 * @ignore
 */
if (!defined('IN_PHPBB')) {
    define('IN_PHPBB', true);
}
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include_once($phpbb_root_path . 'common.' . $phpEx);
include_once($phpbb_root_path . 'includes/functions_display.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('viewforum');

$showOutput = request_var('s', 0);

display_forums('', $config['load_moderators']);

$order_legend = ($config['legend_sort_groupname']) ? 'group_name' : 'group_legend';
// Grab group details for legend display
if ($auth->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) {
    $sql = 'SELECT group_id, group_name, group_colour, group_type, group_legend
		FROM ' . GROUPS_TABLE . '
		WHERE group_legend > 0
		ORDER BY ' . $order_legend . ' ASC';
} else {
    $sql = 'SELECT g.group_id, g.group_name, g.group_colour, g.group_type, g.group_legend
		FROM ' . GROUPS_TABLE . ' g
		LEFT JOIN ' . USER_GROUP_TABLE . ' ug
			ON (
				g.group_id = ug.group_id
				AND ug.user_id = ' . $user->data['user_id'] . '
				AND ug.user_pending = 0
			)
		WHERE g.group_legend > 0
			AND (g.group_type <> ' . GROUP_HIDDEN . ' OR ug.user_id = ' . $user->data['user_id'] . ')
		ORDER BY g.' . $order_legend . ' ASC';
}
$result = $db->sql_query($sql);

$legend = [];
while ($row = $db->sql_fetchrow($result)) {
    $colour_text = ($row['group_colour']) ? ' style="color:#' . $row['group_colour'] . '"' : '';
    $group_name = ($row['group_type'] == GROUP_SPECIAL) ? $user->lang['G_' . $row['group_name']] : $row['group_name'];

    if ($row['group_name'] == 'BOTS' || ($user->data['user_id'] != ANONYMOUS && !$auth->acl_get('u_viewprofile'))) {
        $legend[] = '<span' . $colour_text . '>' . $group_name . '</span>';
    } else {
        $legend[] = '<a' . $colour_text . ' href="' . append_sid("{$phpbb_root_path}memberlist.$phpEx",
                'mode=group&amp;g=' . $row['group_id']) . '">' . $group_name . '</a>';
    }
}
$db->sql_freeresult($result);

$legend = implode($user->lang['COMMA_SEPARATOR'], $legend);

// Generate birthday list if required ...
$birthday_list = [];
if ($config['load_birthdays'] && $config['allow_birthdays'] && $auth->acl_gets('u_viewprofile', 'a_user', 'a_useradd', 'a_userdel')) {
    $time = $user->create_datetime();
    $now = phpbb_gmgetdate($time->getTimestamp() + $time->getOffset());

    // Display birthdays of 29th february on 28th february in non-leap-years
    $leap_year_birthdays = '';
    if ($now['mday'] == 28 && $now['mon'] == 2 && !$time->format('L')) {
        $leap_year_birthdays = " OR u.user_birthday LIKE '" . $db->sql_escape(sprintf('%2d-%2d-', 29, 2)) . "%'";
    }

    $sql = 'SELECT u.user_id, u.username, u.user_colour, u.user_birthday
		FROM ' . USERS_TABLE . ' u
		LEFT JOIN ' . BANLIST_TABLE . " b ON (u.user_id = b.ban_userid)
		WHERE (b.ban_id IS NULL
			OR b.ban_exclude = 1)
			AND (u.user_birthday LIKE '" . $db->sql_escape(sprintf('%2d-%2d-', $now['mday'], $now['mon'])) . "%' $leap_year_birthdays)
			AND u.user_type IN (" . USER_NORMAL . ', ' . USER_FOUNDER . ')';
    $result = $db->sql_query($sql);

    while ($row = $db->sql_fetchrow($result)) {
        $birthday_username = get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']);
        $birthday_year = (int)substr($row['user_birthday'], -4);
        $birthday_age = ($birthday_year) ? max(0, $now['year'] - $birthday_year) : '';

        $template->assign_block_vars('birthdays', [
            'USERNAME' => $birthday_username,
            'AGE'      => $birthday_age,
        ]);

        // For 3.0 compatibility
        if ($age = (int)substr($row['user_birthday'], -4)) {
            $birthday_list[] = $birthday_username . (($birthday_year) ? ' (' . $birthday_age . ')' : '');
        }
    }
    $db->sql_freeresult($result);
}

$online_users = obtain_users_online();

// Assign index specific vars
$template->assign_vars([
        'TOTAL_POSTS'             => $user->lang('TOTAL_POSTS_COUNT', (int)$config['num_posts']),
        'TOTAL_FORUM_POSTS'       => (int)$config['num_posts'],
        'TOTAL_TOPICS'            => $user->lang('TOTAL_TOPICS', (int)$config['num_topics']),
        'TOTAL_FORUM_TOPICS'      => (int)$config['num_topics'],
        'TOTAL_USERS'             => $user->lang('TOTAL_USERS', (int)$config['num_users']),
        'TOTAL_FORUM_USERS'       => (int)$config['num_users'],
        'TOTAL_USERS_VALUE'       => $online_users['total_online'],// DONE
        'VISIBLE_USERS_VALUE'     => $online_users['visible_online'],// DONE
        'HIDDEN_USERS_VALUE'      => $online_users['hidden_online'],// DONE
        'GUEST_USERS_VALUE'       => $online_users['guests_online'],// DONE
        'NEWEST_USER'             => $user->lang('NEWEST_USER',
            get_username_string('full', $config['newest_user_id'], $config['newest_username'],
                $config['newest_user_colour'])),
        'LEGEND'                  => $legend,
        'BIRTHDAY_LIST'           => (empty($birthday_list)) ? '' : implode($user->lang['COMMA_SEPARATOR'],
            $birthday_list),
        'FORUM_IMG'               => $user->img('forum_read', 'NO_UNREAD_POSTS'),
        'FORUM_UNREAD_IMG'        => $user->img('forum_unread', 'UNREAD_POSTS'),
        'FORUM_LOCKED_IMG'        => $user->img('forum_read_locked', 'NO_UNREAD_POSTS_LOCKED'),
        'FORUM_UNREAD_LOCKED_IMG' => $user->img('forum_unread_locked', 'UNREAD_POSTS_LOCKED'),
        'S_LOGIN_ACTION'          => append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=login'),
        'U_SEND_PASSWORD'         => ($config['email_enable']) ? append_sid("{$phpbb_root_path}ucp.$phpEx",
            'mode=sendpassword') : '',
        'S_DISPLAY_BIRTHDAY_LIST' => ($config['load_birthdays']) ? true : false,
        'S_INDEX'                 => true,
        'S_MYSPOT_LOGIN_REDIRECT' => '<input type="hidden" name="redirect" value="' . append_sid('./myspot.' . $phpEx,
                '', true, $user->session_id) . '">',
        'U_MARK_FORUMS'           => ($user->data['is_registered'] || $config['load_anon_lastread']) ? append_sid("{$phpbb_root_path}index.$phpEx",
            'hash=' . generate_link_hash('global') . '&amp;mark=forums&amp;mark_time=' . time()) : '',
        'U_MCP'                   => ($auth->acl_get('m_') || $auth->acl_getf_global('m_')) ? append_sid("{$phpbb_root_path}mcp.$phpEx",
            'i=main&amp;mode=front', true, $user->session_id) : '',
    ]
);

$page_title = ($config['board_index_text'] !== '') ? $config['board_index_text'] : $user->lang['INDEX'];

/**
 * You can use this event to modify the page title and load data for the index
 * @event core.index_modify_page_title
 * @var    string    page_title        Title of the index page
 * @since 3.1.0-a1
 */
$vars = ['page_title'];
extract($phpbb_dispatcher->trigger_event('core.index_modify_page_title', compact($vars)));

// Output page
if ($showOutput) {
    page_header($page_title, true);

    $template->set_filenames([
            'body' => 'modules/mini_forums.html',
        ]
    );

    page_footer();
}
