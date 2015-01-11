<?php
    include"includes/team_parse.php";

    $team_stats = array();
    $members    = array();
    
    // read stats of teamid 3
    readTeamStats(13134, &$team_stats, &$members);
    
    print_r($team_stats);
    print_r($members);
?> 