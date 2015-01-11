<?
////// Quick Quips tsn.7 Script //////

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
/*
$sql = "SELECT f.* $lastread_select
	FROM $sql_from
	WHERE f.forum_id = $forum_id";
$result = $db->sql_query($sql);
$forum_data = $db->sql_fetchrow($result);
$db->sql_freeresult($result);
*/

$sql = "SELECT MAX(quip_id) AS max_id FROM phpbb_quickquips";
$result = $db->sql_query($sql);
$max_quip_id_query = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if ( !$max_quip_id_query )
{
	trigger_error('NO_QQ_1');
}

$max_quip = (int) $db->sql_fetchfield('max_id', 0, $max_quip_id_query);

if ( !$max_quip )
{
	trigger_error('NO_QQ_2');
}

srand((double)microtime()*1000000);
$qid = rand(0,$max_quip);

$sql = "SELECT q.quip_id, q.quip_text
	FROM phpbb_quickquips q
	WHERE q.quip_id = " . $qid;
$result = $db->sql_query($sql);
$ran_quip_info_query = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

if (!$ran_quip_info_query)
{
	trigger_error('NO_QQ_3');
}

$quip = array();

$quip['quip_id'] = $db->sql_fetchfield(quip_id, 0, $ran_quip_info_query);
$quip['quip_text'] = $db->sql_fetchfield(quip_text, 0, $ran_quip_info_query);

$template->assign_block_vars('quickquip', array(
		'ID' => $quip['quip_id'],
		'TEXT' => $quip['quip_text'],
		'MAX_ID' => $max_quip)
		);
