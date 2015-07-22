<?php
/**
 *
 * This file is part of the phpBB Forum Software package.
 *
 * @copyright (c) phpBB Limited <https://www.phpbb.com>
 * @license       GNU General Public License, version 2 (GPL-2.0)
 *
 * For full copyright and license information, please see
 * the docs/CREDITS.txt file.
 *
 */

/**
 * @ignore
 */
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup('search');

// Define initial vars
$mode = request_var('mode', '');
$search_id = 'active_topics';//request_var('search_id', '');
$start = max(request_var('start', 0), 0);
$post_id = request_var('p', 0);
$topic_id = request_var('t', 0);
$view = request_var('view', '');

$submit = request_var('submit', false);
$keywords = utf8_normalize_nfc(request_var('keywords', '', true));
$add_keywords = utf8_normalize_nfc(request_var('add_keywords', '', true));
$author = request_var('author', '', true);
$author_id = request_var('author_id', 0);
$show_results = 'topics'; //($topic_id) ? 'posts' : request_var('sr', 'posts');
//$show_results	= ($show_results == 'posts') ? 'posts' : 'topics';
$search_terms = request_var('terms', 'all');
$search_fields = request_var('sf', 'all');
$search_child = request_var('sc', true);

$sort_days = request_var('st', 0);
$sort_key = request_var('sk', 't');
$sort_dir = request_var('sd', 'd');

$return_chars = request_var('ch', ($topic_id) ? -1 : 300);
$search_forum = request_var('fid', array(0));

// Is user able to search? Has search been disabled?
if (!$auth->acl_get('u_search') || !$auth->acl_getf_global('f_search') || !$config['load_search']) {
    $template->assign_var('S_NO_SEARCH', true);
    trigger_error('NO_SEARCH');
}

// Check search load limit
if ($user->load && $config['limit_search_load'] && ($user->load > doubleval($config['limit_search_load']))) {
    $template->assign_var('S_NO_SEARCH', true);
    trigger_error('NO_SEARCH_LOAD');
}

// It is applicable if the configuration setting is non-zero, and the user cannot
// ignore the flood setting, and the search is a keyword search.
$interval = ($user->data['user_id'] == ANONYMOUS) ? $config['search_anonymous_interval'] : $config['search_interval'];
if ($interval && !in_array($search_id,
        array('unreadposts', 'unanswered', 'active_topics', 'egosearch')) && !$auth->acl_get('u_ignoreflood')
) {
    if ($user->data['user_last_search'] > time() - $interval) {
        $template->assign_var('S_NO_SEARCH', true);
        trigger_error($user->lang('NO_SEARCH_TIME', (int)($user->data['user_last_search'] + $interval - time())));
    }
}

// Define some vars
$limit_days = array(
    0   => $user->lang['ALL_RESULTS'],
    1   => $user->lang['1_DAY'],
    7   => $user->lang['7_DAYS'],
    14  => $user->lang['2_WEEKS'],
    30  => $user->lang['1_MONTH'],
    90  => $user->lang['3_MONTHS'],
    180 => $user->lang['6_MONTHS'],
    365 => $user->lang['1_YEAR']
);
$sort_by_text = array(
    'a' => $user->lang['SORT_AUTHOR'],
    't' => $user->lang['SORT_TIME'],
    'f' => $user->lang['SORT_FORUM'],
    'i' => $user->lang['SORT_TOPIC_TITLE'],
    's' => $user->lang['SORT_POST_SUBJECT']
);

$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';
gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key, $s_sort_dir,
    $u_sort_param);

$phpbb_content_visibility = $phpbb_container->get('content.visibility');
$pagination = $phpbb_container->get('pagination');

if ($keywords || $author || $author_id || $search_id || $submit) {
    // clear arrays
    $id_ary = array();

    // If we are looking for authors get their ids
    $author_id_ary = array();
    $sql_author_match = '';

    $ex_fid_ary = array_unique(array_merge(array_keys($auth->acl_getf('!f_read', true)),
        array_keys($auth->acl_getf('!f_search', true))));

    $not_in_fid = (sizeof($ex_fid_ary)) ? 'WHERE ' . $db->sql_in_set('f.forum_id', $ex_fid_ary,
            true) . " OR (f.forum_password <> '' AND fa.user_id <> " . (int)$user->data['user_id'] . ')' : "";

    $sql = 'SELECT f.forum_id, f.forum_name, f.parent_id, f.forum_type, f.right_id, f.forum_password, f.forum_flags, fa.user_id
		FROM ' . FORUMS_TABLE . ' f
		LEFT JOIN ' . FORUMS_ACCESS_TABLE . " fa ON (fa.forum_id = f.forum_id
			AND fa.session_id = '" . $db->sql_escape($user->session_id) . "')
		$not_in_fid
		ORDER BY f.left_id";
    $result = $db->sql_query($sql);

    $right_id = 0;
    $reset_search_forum = true;
    while ($row = $db->sql_fetchrow($result)) {
        if ($row['forum_password'] && $row['user_id'] != $user->data['user_id']) {
            $ex_fid_ary[] = (int)$row['forum_id'];
            continue;
        }

        // Exclude forums from active topics
        if (!($row['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS) && ($search_id == 'active_topics')) {
            $ex_fid_ary[] = (int)$row['forum_id'];
            continue;
        }

        if (sizeof($search_forum)) {
            if ($search_child) {
                if (in_array($row['forum_id'], $search_forum) && $row['right_id'] > $right_id) {
                    $right_id = (int)$row['right_id'];
                } else if ($row['right_id'] < $right_id) {
                    continue;
                }
            }

            if (!in_array($row['forum_id'], $search_forum)) {
                $ex_fid_ary[] = (int)$row['forum_id'];
                $reset_search_forum = false;
            }
        }
    }
    $db->sql_freeresult($result);

    // find out in which forums the user is allowed to view posts
    $m_approve_posts_fid_sql = $phpbb_content_visibility->get_global_visibility_sql('post', $ex_fid_ary, 'p.');
    $m_approve_topics_fid_sql = $phpbb_content_visibility->get_global_visibility_sql('topic', $ex_fid_ary, 't.');

    if ($reset_search_forum) {
        $search_forum = array();
    }

    // Select which method we'll use to obtain the post_id or topic_id information
    $search_type = $config['search_type'];

    if (!class_exists($search_type)) {
        trigger_error('NO_SUCH_SEARCH_MODULE');
    }
    // We do some additional checks in the module to ensure it can actually be utilised
    $error = false;
    $search = new $search_type($error, $phpbb_root_path, $phpEx, $auth, $config, $db, $user, $phpbb_dispatcher);

    if ($error) {
        trigger_error($error);
    }

    // define some variables needed for retrieving post_id/topic_id information
    $sort_by_sql = array(
        'a' => 'u.username_clean',
        't' => (($show_results == 'posts') ? 'p.post_time' : 't.topic_last_post_time'),
        'f' => 'f.forum_id',
        'i' => 't.topic_title',
        's' => (($show_results == 'posts') ? 'p.post_subject' : 't.topic_title')
    );

    /**
     * Event to modify the SQL parameters before pre-made searches
     *
     * @event core.search_modify_param_before
     * @var    string    keywords        String of the specified keywords
     * @var    array    sort_by_sql        Array of SQL sorting instructions
     * @var    array    ex_fid_ary        Array of excluded forum ids
     * @var    array    author_id_ary    Array of exclusive author ids
     * @var    string    search_id        The id of the search request
     * @since 3.1.3-RC1
     */
    $vars = array(
        'keywords',
        'sort_by_sql',
        'ex_fid_ary',
        'author_id_ary',
        'search_id',
    );
    extract($phpbb_dispatcher->trigger_event('core.search_modify_param_before', compact($vars)));

    // pre-made searches
    $sql = $field = $l_search_title = '';
    if ($search_id) {
        $l_search_title = $user->lang['SEARCH_ACTIVE_TOPICS'];
        $show_results = 'topics';
        $sort_key = 't';
        $sort_dir = 'd';
        $sort_days = request_var('st', 7);
//        $sort_by_sql['t'] = 't.topic_last_post_time';
        $sort_by_sql['t'] = 't.topic_time';

        gen_sort_selects($limit_days, $sort_by_text, $sort_days, $sort_key, $sort_dir, $s_limit_days, $s_sort_key,
            $s_sort_dir, $u_sort_param);
        $s_sort_key = $s_sort_dir = '';

        $last_post_time_sql = ($sort_days) ? ' AND t.topic_last_post_time > ' . (time() - ($sort_days * 24 * 3600)) : '';

        $sql = 'SELECT t.topic_last_post_time, t.topic_id
					FROM ' . TOPICS_TABLE . " t
					WHERE t.topic_moved_id = 0
						$last_post_time_sql
						AND " . $m_approve_topics_fid_sql . '
						' . ((sizeof($ex_fid_ary)) ? ' AND ' . $db->sql_in_set('t.forum_id', $ex_fid_ary, true) : '') . '
					ORDER BY t.topic_last_post_time DESC';
        $field = 'topic_id';
    }

    // show_results should not change after this
    $per_page = ($show_results == 'posts') ? $config['posts_per_page'] : $config['topics_per_page'];
    $total_match_count = 0;

    // Set limit for the $total_match_count to reduce server load
    $total_matches_limit = 1000;
    $found_more_search_matches = false;

    if ($search_id) {
        if ($sql) {
            // Only return up to $total_matches_limit+1 ids (the last one will be removed later)
            $result = $db->sql_query_limit($sql, $total_matches_limit + 1);

            while ($row = $db->sql_fetchrow($result)) {
                $id_ary[] = (int)$row[$field];
            }
            $db->sql_freeresult($result);
        } else {
            $search_id = '';
        }

        $total_match_count = sizeof($id_ary);
        if ($total_match_count) {
            // Limit the number to $total_matches_limit for pre-made searches
            if ($total_match_count > $total_matches_limit) {
                $found_more_search_matches = true;
                $total_match_count = $total_matches_limit;
            }

            // Make sure $start is set to the last page if it exceeds the amount
            $start = $pagination->validate_start($start, $per_page, $total_match_count);

            $id_ary = array_slice($id_ary, $start, $per_page);
        } else {
            // Set $start to 0 if no matches were found
            $start = 0;
        }
    }

    // make sure that some arrays are always in the same order
    sort($ex_fid_ary);

    if ($search->get_search_query()) {
        $total_match_count = $search->keyword_search($show_results, $search_fields, $search_terms, $sort_by_sql,
            $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $m_approve_posts_fid_sql, $topic_id, $author_id_ary,
            $sql_author_match, $id_ary, $start, $per_page);
    }

    $sql_where = '';

    if (sizeof($id_ary)) {
        $sql_where .= $db->sql_in_set(($show_results == 'posts') ? 'p.post_id' : 't.topic_id', $id_ary);
        $sql_where .= (sizeof($ex_fid_ary)) ? ' AND (' . $db->sql_in_set('f.forum_id', $ex_fid_ary,
                true) . ' OR f.forum_id IS NULL)' : '';
        $sql_where .= ' AND ' . (($show_results == 'posts') ? $m_approve_posts_fid_sql : $m_approve_topics_fid_sql);
    }

    if ($show_results == 'posts') {
        include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
    } else {
        include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
    }

    $user->add_lang('viewtopic');

    // Grab icons
    $icons = $cache->obtain_icons();

    // define some vars for urls
    // A single wildcard will make the search results look ugly
    $hilit = phpbb_clean_search_string(str_replace(array('+', '-', '|', '(', ')', '&quot;'), ' ', $keywords));
    $hilit = str_replace(' ', '|', $hilit);

    $u_hilit = urlencode(htmlspecialchars_decode(str_replace('|', ' ', $hilit)));
    $u_show_results = '&amp;sr=' . $show_results;
    $u_search_forum = implode('&amp;fid%5B%5D=', $search_forum);

    $u_search = append_sid("{$phpbb_root_path}search.$phpEx", $u_sort_param . $u_show_results);
    $u_search .= ($search_id) ? '&amp;search_id=' . $search_id : '';
    $u_search .= ($u_hilit) ? '&amp;keywords=' . urlencode(htmlspecialchars_decode($keywords)) : '';
    $u_search .= ($search_terms != 'all') ? '&amp;terms=' . $search_terms : '';
    $u_search .= ($topic_id) ? '&amp;t=' . $topic_id : '';
    $u_search .= ($author) ? '&amp;author=' . urlencode(htmlspecialchars_decode($author)) : '';
    $u_search .= ($author_id) ? '&amp;author_id=' . $author_id : '';
    $u_search .= ($u_search_forum) ? '&amp;fid%5B%5D=' . $u_search_forum : '';
    $u_search .= (!$search_child) ? '&amp;sc=0' : '';
    $u_search .= ($search_fields != 'all') ? '&amp;sf=' . $search_fields : '';
    $u_search .= ($return_chars != 300) ? '&amp;ch=' . $return_chars : '';

    if ($sql_where) {

        $sql_from = TOPICS_TABLE . ' t
				LEFT JOIN ' . FORUMS_TABLE . ' f ON (f.forum_id = t.forum_id)
				' . (($sort_key == 'a') ? ' LEFT JOIN ' . USERS_TABLE . ' u ON (u.user_id = t.topic_poster) ' : '');
        $sql_select = 't.*, f.forum_id, f.forum_name';

        if ($user->data['is_registered']) {
            if ($config['load_db_track'] && $author_id !== $user->data['user_id']) {
                $sql_from .= ' LEFT JOIN ' . TOPICS_POSTED_TABLE . ' tp ON (tp.user_id = ' . $user->data['user_id'] . '
						AND t.topic_id = tp.topic_id)';
                $sql_select .= ', tp.topic_posted';
            }

            if ($config['load_db_lastread']) {
                $sql_from .= ' LEFT JOIN ' . TOPICS_TRACK_TABLE . ' tt ON (tt.user_id = ' . $user->data['user_id'] . '
							AND t.topic_id = tt.topic_id)
						LEFT JOIN ' . FORUMS_TRACK_TABLE . ' ft ON (ft.user_id = ' . $user->data['user_id'] . '
							AND ft.forum_id = f.forum_id)';
                $sql_select .= ', tt.mark_time, ft.mark_time as f_mark_time';
            }
        }

        if ($config['load_anon_lastread'] || ($user->data['is_registered'] && !$config['load_db_lastread'])) {
            $tracking_topics = $request->variable($config['cookie_name'] . '_track', '', true,
                \phpbb\request\request_interface::COOKIE);
            $tracking_topics = ($tracking_topics) ? tracking_unserialize($tracking_topics) : array();
        }

        /**
         * Event to modify the SQL query before the topic data is retrieved
         *
         * @event   core.search_get_active_topic_data
         * @var    string    sql_select        The SQL SELECT string used by search to get topic data
         * @var    string    sql_from        The SQL FROM string used by search to get topic data
         * @var    string    sql_where        The SQL WHERE string used by search to get topic data
         * @var    int        total_match_count    The total number of search matches
         * @since   3.1.0-a1
         * @changed 3.1.0-RC5 Added total_match_count
         */
        $vars = array('sql_select', 'sql_from', 'sql_where', 'total_match_count');
        extract($phpbb_dispatcher->trigger_event('core.search_get_active_topic_data', compact($vars)));

        $sql = "SELECT $sql_select
				FROM $sql_from
				WHERE $sql_where";
        $sql .= ' ORDER BY ' . $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
        $result = $db->sql_query($sql);
        $result_topic_id = 0;

        $rowset = array();

        $forums = $rowset = $shadow_topic_list = array();
        while ($row = $db->sql_fetchrow($result)) {
            $row['forum_id'] = (int)$row['forum_id'];
            $row['topic_id'] = (int)$row['topic_id'];

            if ($row['topic_status'] == ITEM_MOVED) {
                $shadow_topic_list[$row['topic_moved_id']] = $row['topic_id'];
            }

            $rowset[$row['topic_id']] = $row;

            if (!isset($forums[$row['forum_id']]) && $user->data['is_registered'] && $config['load_db_lastread']) {
                $forums[$row['forum_id']]['mark_time'] = $row['f_mark_time'];
            }
            $forums[$row['forum_id']]['topic_list'][] = $row['topic_id'];
            $forums[$row['forum_id']]['rowset'][$row['topic_id']] = &$rowset[$row['topic_id']];
        }
        $db->sql_freeresult($result);

        // If we have some shadow topics, update the rowset to reflect their topic information
        if (sizeof($shadow_topic_list)) {
            $sql = 'SELECT *
					FROM ' . TOPICS_TABLE . '
					WHERE ' . $db->sql_in_set('topic_id', array_keys($shadow_topic_list));
            $result = $db->sql_query($sql);

            while ($row = $db->sql_fetchrow($result)) {
                $orig_topic_id = $shadow_topic_list[$row['topic_id']];

                // We want to retain some values
                $row = array_merge($row, array(
                        'topic_moved_id' => $rowset[$orig_topic_id]['topic_moved_id'],
                        'topic_status'   => $rowset[$orig_topic_id]['topic_status'],
                        'forum_name'     => $rowset[$orig_topic_id]['forum_name']
                    )
                );

                $rowset[$orig_topic_id] = $row;
            }
            $db->sql_freeresult($result);
        }
        unset($shadow_topic_list);

        foreach ($forums as $forum_id => $forum) {
            if ($user->data['is_registered'] && $config['load_db_lastread']) {
                $topic_tracking_info[$forum_id] = get_topic_tracking($forum_id, $forum['topic_list'], $forum['rowset'],
                    array($forum_id => $forum['mark_time']));
            } else if ($config['load_anon_lastread'] || $user->data['is_registered']) {
                $topic_tracking_info[$forum_id] = get_complete_topic_tracking($forum_id, $forum['topic_list']);

                if (!$user->data['is_registered']) {
                    $user->data['user_lastmark'] = (isset($tracking_topics['l'])) ? (int)(base_convert($tracking_topics['l'],
                            36, 10) + $config['board_startdate']) : 0;
                }
            }
        }
        unset($forums);

        /**
         * Modify the rowset data
         *
         * @event   core.search_modify_rowset
         * @var    array    attachments                Array with posts attachments data
         * @var    string    hilit                    String to highlight
         * @var    array    rowset                    Array with the search results data
         * @var    string    show_results            String indicating the show results mode
         * @var    array    topic_tracking_info        Array with the topics tracking data
         * @var    string    u_hilit                    Highlight string to be injected into URL
         * @var    string    view                    Search results view mode
         * @var    array    zebra                    Array with zebra data for the current user
         * @since   3.1.0-b4
         * @changed 3.1.0-b5 Added var show_results
         */
        $vars = array(
            'attachments',
            'hilit',
            'rowset',
            'show_results',
            'topic_tracking_info',
            'u_hilit',
            'view',
            'zebra',
        );
        extract($phpbb_dispatcher->trigger_event('core.search_modify_rowset', compact($vars)));

        foreach ($rowset as $row) {
            $forum_id = $row['forum_id'];
            $result_topic_id = $row['topic_id'];
            $topic_title = censor_text($row['topic_title']);
            $replies = $phpbb_content_visibility->get_count('topic_posts', $row, $forum_id) - 1;

            $view_topic_url_params = "f=$forum_id&amp;t=$result_topic_id" . (($u_hilit) ? "&amp;hilit=$u_hilit" : '');
            $view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params);

            if ($show_results == 'topics') {
                if ($config['load_db_track'] && $author_id === $user->data['user_id']) {
                    $row['topic_posted'] = 1;
                }

                $folder_img = $folder_alt = $topic_type = '';
                topic_status($row, $replies,
                    (isset($topic_tracking_info[$forum_id][$row['topic_id']]) && $row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]) ? true : false,
                    $folder_img, $folder_alt, $topic_type);

                $unread_topic = (isset($topic_tracking_info[$forum_id][$row['topic_id']]) && $row['topic_last_post_time'] > $topic_tracking_info[$forum_id][$row['topic_id']]) ? true : false;

                $topic_unapproved = (($row['topic_visibility'] == ITEM_UNAPPROVED || $row['topic_visibility'] == ITEM_REAPPROVE) && $auth->acl_get('m_approve',
                        $forum_id)) ? true : false;
                $posts_unapproved = ($row['topic_visibility'] == ITEM_APPROVED && $row['topic_posts_unapproved'] && $auth->acl_get('m_approve',
                        $forum_id)) ? true : false;
                $topic_deleted = $row['topic_visibility'] == ITEM_DELETED;
                $u_mcp_queue = ($topic_unapproved || $posts_unapproved) ? append_sid("{$phpbb_root_path}mcp.$phpEx",
                    'i=queue&amp;mode=' . (($topic_unapproved) ? 'approve_details' : 'unapproved_posts') . "&amp;t=$result_topic_id",
                    true, $user->session_id) : '';
                $u_mcp_queue = (!$u_mcp_queue && $topic_deleted) ? append_sid("{$phpbb_root_path}mcp.$phpEx",
                    "i=queue&amp;mode=deleted_topics&amp;t=$result_topic_id", true, $user->session_id) : $u_mcp_queue;

                $row['topic_title'] = preg_replace('#(?!<.*)(?<!\w)(' . $hilit . ')(?!\w|[^<>]*(?:</s(?:cript|tyle))?>)#is',
                    '<span class="posthilit">$1</span>', $row['topic_title']);

                $tpl_ary = array(
                    'TOPIC_AUTHOR'            => get_username_string('username', $row['topic_poster'],
                        $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'TOPIC_AUTHOR_COLOUR'     => get_username_string('colour', $row['topic_poster'],
                        $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'TOPIC_AUTHOR_FULL'       => get_username_string('full', $row['topic_poster'],
                        $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'FIRST_POST_TIME'         => $user->format_date($row['topic_time']),
                    'LAST_POST_SUBJECT'       => $row['topic_last_post_subject'],
                    'LAST_POST_TIME'          => $user->format_date($row['topic_last_post_time']),
                    'LAST_VIEW_TIME'          => $user->format_date($row['topic_last_view_time']),
                    'LAST_POST_AUTHOR'        => get_username_string('username', $row['topic_last_poster_id'],
                        $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'LAST_POST_AUTHOR_COLOUR' => get_username_string('colour', $row['topic_last_poster_id'],
                        $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'LAST_POST_AUTHOR_FULL'   => get_username_string('full', $row['topic_last_poster_id'],
                        $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'TOPIC_TYPE'              => $topic_type,
                    'TOPIC_IMG_STYLE'         => $folder_img,
                    'TOPIC_FOLDER_IMG'        => $user->img($folder_img, $folder_alt),
                    'TOPIC_FOLDER_IMG_ALT'    => $user->lang[$folder_alt],
                    'TOPIC_ICON_IMG'          => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
                    'TOPIC_ICON_IMG_WIDTH'    => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
                    'TOPIC_ICON_IMG_HEIGHT'   => (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
                    'ATTACH_ICON_IMG'         => ($auth->acl_get('u_download') && $auth->acl_get('f_download',
                            $forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach',
                        $user->lang['TOTAL_ATTACHMENTS']) : '',
                    'UNAPPROVED_IMG'          => ($topic_unapproved || $posts_unapproved) ? $user->img('icon_topic_unapproved',
                        ($topic_unapproved) ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',
                    'S_TOPIC_TYPE'            => $row['topic_type'],
                    'S_USER_POSTED'           => (!empty($row['topic_posted'])) ? true : false,
                    'S_UNREAD_TOPIC'          => $unread_topic,
                    'S_TOPIC_REPORTED'        => (!empty($row['topic_reported']) && $auth->acl_get('m_report',
                            $forum_id)) ? true : false,
                    'S_TOPIC_UNAPPROVED'      => $topic_unapproved,
                    'S_POSTS_UNAPPROVED'      => $posts_unapproved,
                    'S_TOPIC_DELETED'         => $topic_deleted,
                    'S_HAS_POLL'              => ($row['poll_start']) ? true : false,
                    'U_LAST_POST'             => append_sid("{$phpbb_root_path}viewtopic.$phpEx",
                            $view_topic_url_params . '&amp;p=' . $row['topic_last_post_id']) . '#p' . $row['topic_last_post_id'],
                    'U_LAST_POST_AUTHOR'      => get_username_string('profile', $row['topic_last_poster_id'],
                        $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
                    'U_TOPIC_AUTHOR'          => get_username_string('profile', $row['topic_poster'],
                        $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
                    'U_NEWEST_POST'           => append_sid("{$phpbb_root_path}viewtopic.$phpEx",
                            $view_topic_url_params . '&amp;view=unread') . '#unread',
                    'U_MCP_REPORT'            => append_sid("{$phpbb_root_path}mcp.$phpEx",
                        'i=reports&amp;mode=reports&amp;t=' . $result_topic_id, true, $user->session_id),
                    'U_MCP_QUEUE'             => $u_mcp_queue,
                );
            }

            $tpl_ary = array_merge($tpl_ary, array(
                'FORUM_ID'      => $forum_id,
                'TOPIC_ID'      => $result_topic_id,
                'POST_ID'       => ($show_results == 'posts') ? $row['post_id'] : false,
                'FORUM_TITLE'   => $row['forum_name'],
                'TOPIC_TITLE'   => $topic_title,
                'TOPIC_REPLIES' => $replies,
                'TOPIC_VIEWS'   => $row['topic_views'],
                'U_VIEW_TOPIC'  => $view_topic_url,
                'U_VIEW_FORUM'  => append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
                'U_VIEW_POST'   => (!empty($row['post_id'])) ? append_sid("{$phpbb_root_path}viewtopic.$phpEx",
                        "f=$forum_id&amp;t=" . $row['topic_id'] . '&amp;p=' . $row['post_id'] . (($u_hilit) ? '&amp;hilit=' . $u_hilit : '')) . '#p' . $row['post_id'] : '',
            ));

            /**
             * Modify the topic data before it is assigned to the template
             *
             * @event   core.search_modify_tpl_ary
             * @var    array    row                Array with topic data
             * @var    array    tpl_ary            Template block array with topic data
             * @var    string    show_results    Display topics or posts
             * @var    string    topic_title        Cleaned topic title
             * @var    int        replies            The number of topic replies
             * @var    string    view_topic_url    The URL to the topic
             * @var    string    folder_img        The folder image of the topic
             * @var    string    folder_alt        The alt attribute of the topic folder img
             * @var    int        topic_type        The topic type
             * @var    bool    unread_topic    Whether the topic has unread posts
             * @var    bool    topic_unapproved    Whether the topic is unapproved
             * @var    int        posts_unapproved    The number of unapproved posts
             * @var    bool    topic_deleted    Whether the topic has been deleted
             * @var    string    u_mcp_queue        The URL to the corresponding MCP queue page
             * @var    array    zebra            The zebra data of the current user
             * @var    array    attachments        All the attachments of the search results
             * @since   3.1.0-a1
             * @changed 3.1.0-b3 Added vars show_results, topic_title, replies,
             *        view_topic_url, folder_img, folder_alt, topic_type, unread_topic,
             *        topic_unapproved, posts_unapproved, topic_deleted, u_mcp_queue,
             *        zebra, attachments
             */
            $vars = array(
                'row',
                'tpl_ary',
                'show_results',
                'topic_title',
                'replies',
                'view_topic_url',
                'folder_img',
                'folder_alt',
                'topic_type',
                'unread_topic',
                'topic_unapproved',
                'posts_unapproved',
                'topic_deleted',
                'u_mcp_queue',
                'zebra',
                'attachments',
            );
            extract($phpbb_dispatcher->trigger_event('core.search_modify_tpl_ary', compact($vars)));

            $template->assign_block_vars('searchresults', $tpl_ary);

            if ($show_results == 'topics') {
                $pagination->generate_template_pagination($view_topic_url, 'searchresults.pagination', 'start',
                    $replies + 1, $config['posts_per_page'], 1, true, true);
            }
        }

        if ($topic_id && ($topic_id == $result_topic_id)) {
            $template->assign_vars(array(
                'SEARCH_TOPIC'      => $topic_title,
                'L_RETURN_TO_TOPIC' => $user->lang('RETURN_TO', $topic_title),
                'U_SEARCH_TOPIC'    => $view_topic_url
            ));
        }
    }
    unset($rowset);

    // Output header
    if ($found_more_search_matches) {
        $l_search_matches = $user->lang('FOUND_MORE_SEARCH_MATCHES', (int)$total_match_count);
    } else {
        $l_search_matches = $user->lang('FOUND_SEARCH_MATCHES', (int)$total_match_count);
    }

    // Check if search backend supports phrase search or not
    $phrase_search_disabled = '';
    if (strpos(html_entity_decode($keywords), '"') !== false && method_exists($search, 'supports_phrase_search')) {
        $phrase_search_disabled = $search->supports_phrase_search() ? false : true;
    }

    $pagination->generate_template_pagination($u_search, 'pagination', 'start', $total_match_count, $per_page, $start);

    $template->assign_vars(array(
        'SEARCH_TITLE'           => $l_search_title,
        'SEARCH_MATCHES'         => $l_search_matches,
        'SEARCH_WORDS'           => $keywords,
        'SEARCHED_QUERY'         => $search->get_search_query(),
        'IGNORED_WORDS'          => (!empty($common_words)) ? implode(' ', $common_words) : '',
        'PHRASE_SEARCH_DISABLED' => $phrase_search_disabled,
        'TOTAL_MATCHES'          => $total_match_count,
        'SEARCH_IN_RESULTS'      => ($search_id) ? false : true,
        'S_SELECT_SORT_DIR'      => $s_sort_dir,
        'S_SELECT_SORT_KEY'      => $s_sort_key,
        'S_SELECT_SORT_DAYS'     => $s_limit_days,
        'S_SEARCH_ACTION'        => $u_search,
        'S_SHOW_TOPICS'          => ($show_results == 'posts') ? false : true,
        'GOTO_PAGE_IMG'          => $user->img('icon_post_target', 'GOTO_PAGE'),
        'NEWEST_POST_IMG'        => $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
        'REPORTED_IMG'           => $user->img('icon_topic_reported', 'TOPIC_REPORTED'),
        'UNAPPROVED_IMG'         => $user->img('icon_topic_unapproved', 'TOPIC_UNAPPROVED'),
        'DELETED_IMG'            => $user->img('icon_topic_deleted', 'TOPIC_DELETED'),
        'POLL_IMG'               => $user->img('icon_topic_poll', 'TOPIC_POLL'),
        'LAST_POST_IMG'          => $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
        'U_SEARCH_WORDS'         => $u_search,
    ));

    /**
     * Modify the title and/or load data for the search results page
     *
     * @event core.search_results_modify_search_title
     * @var    int        author_id        ID of the author to search by
     * @var    string    l_search_title    The title of the search page
     * @var    string    search_id        Predefined search type name
     * @var    string    show_results    Search results output mode - topics or posts
     * @var    int        start            The starting id of the results
     * @since 3.1.0-RC4
     */
    $vars = array('author_id', 'l_search_title', 'search_id', 'show_results', 'start');
    extract($phpbb_dispatcher->trigger_event('core.search_results_modify_search_title', compact($vars)));

    page_header(($l_search_title) ? $l_search_title : $user->lang['SEARCH']);

    $template->set_filenames(array(
            'body' => 'modules/active_posts.html'
        )
    );
    make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));

    page_footer();
}

// Output the basic, empty page
page_header($user->lang['MYSPOT']);

page_footer();
