
<pre>
<?
	/*
	 *	readTeamStats(teamid)
	 *		-	Read WhatPulse team statistics from the webapi into an array.
	 *
	 *	Author: wasted@whatpulse.org
	 *  Based on script for www.sogamed.com
	 */
	function readTeamStats($teamid, $team_stats, $members)
	{
		// 
		$statistics_tags = array("TeamName", "TeamDescription", "TeamMembers", "TeamClicks", 
								 "TeamKeys", "TeamRank", "TeamDateFormed", "TeamFounder");
								 
		$member_tags 	 = array("MemberName", "MemberUserID", "MemberKeys", "MemberClicks", 
								 "MemberLastpulse");
	
		// init xml parser and read xml file
		$data   = implode("", file("http://whatpulse.org/api/team.php?TeamID=".$teamid));
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, $data, $values, $index);
		xml_parser_free($parser);
		
		for ($x = 0; $x < sizeof($statistics_tags); $x++) 
		{
			$team_stats[$statistics_tags[$x]] = $values[$index[$statistics_tags[$x]][0]]['value'];
		}		
		
		for ($x = 0; $x < sizeof($index['Member']); $x++) 
		{
			for ($y = 0; $y < sizeof($member_tags); $y++) 
			{
				if ($values[$index[$member_tags[$y]][$x]]['level'] == 5)
				{
					$members[$x][$member_tags[$y]] = $values[$index[$member_tags[$y]][$x]]['value'];
				}
			}
		}
	}
	
	$team_stats = array();
	$members    = array();
	
	readTeamStats(13134, &$team_stats, &$members);
	
	print_r($team_stats);
	print_r($members);
?>
</pre>
 

