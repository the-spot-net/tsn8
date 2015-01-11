<?php
header( "Expires: Mon, 26 Jul 1997 05:00:00 GMT" );  // disable IE caching
header( "Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . " GMT" ); 
header( "Cache-Control: no-cache, must-revalidate" ); 
header( "Pragma: no-cache" );

//
// ScarySoftware RSS parser 
// Copyright (c) 2006 Scary Software
// ( Generates HTML from a RSS feeds )
//    
// Licensed Under the Gnu Public License
//

// Here are the feeds - you can add to them or change them
if (!isset($HTTP_GET_VARS['feedid'])) 
	$feedid = 0;
else 
	$feedid = $HTTP_GET_VARS['feedid'];
	
$RSSFEEDS = array(
	0 => "http://pipes.yahoo.com/pipes/pipe.run?_id=mi1Gsi1V3hGO_8NddfQQIA&_render=rss",
	1 => "http://twitter.com/statuses/user_timeline/12804332.rss",
	2 => "http://thepizzy.net/bluejournal/?feed=rss2"
);


//
//  Makes a pretty HTML page bit from the title, 
//  description and link
//
function FormatRow1($title, $link) {
return <<<HTML
<td class="youtube_title"><p><a href="$link">$title</a></p></td>
HTML;
}

function FormatRow2($link, $thumb, $description) {
return <<<HTML
	<td class="youtube_thumb"><a href="$link" title="$description"><img src="$thumb" border="0" alt="$description"></a></td>
HTML;
}

function FormatRow3 ($author) {
return <<<HTML
	<td class="youtube_data">By: <a href="http://www.youtube.com/profile?user=$author" class="screen_name"><b>$author</b></a></td>
HTML;
}

// we'll buffer the output
ob_start();

// Now we make sure that we have a feed selected to work with
$rss_url = $RSSFEEDS[$feedid];

// Server friendly page cache
$ttl = 60;// 60 secs  
$cachefilename = "youtube_cache";//md5($rss_url);
if (file_exists($cachefilename) && (time() - $ttl < filemtime($cachefilename))) {
	// We recently did the work, so we'll save bandwidth by not doing it again
	include($cachefilename);
	/*$sInfo = "
		<script type=\"text/javascript\">
			window.onload = function () {
				var divInfoToReturn = document.getElementById(\"divInfoToReturn\");
				parent.displayTwitterFeed(divInfoToReturn.innerHTML);
			};
		</script>
		<div id=\"divInfoToReturn\">";
		//$sInfo = $sContent;
		//$sInfo .= "</div>";
		//echo $sContent;
	//echo $sContent;*/
	exit();
}

// Now we read the feed
$rss_feed = file_get_contents($rss_url);

// Now we replace a few things that may cause problems later
$rss_feed = str_replace("<![CDATA[", "", $rss_feed);
$rss_feed = str_replace("]]>", "", $rss_feed);
$rss_feed = str_replace("\n", "", $rss_feed);


// Now we get the nodes that we're interested in
preg_match_all('#<title>(.*?)</title>#', $rss_feed, $title, PREG_SET_ORDER); 
preg_match_all('#<link>(.*?)</link>#', $rss_feed, $link, PREG_SET_ORDER);
preg_match_all('/<img alt="" src="(.*?)">/', $rss_feed, $thumb, PREG_SET_ORDER);
preg_match_all('#&lt;div style=&quot;font-size:12px;margin:3px 0px;&quot;&gt;&lt;span&gt;(.*?)&lt;/span&gt;#', $rss_feed, $description, PREG_SET_ORDER);  
preg_match_all('#<author>(.*?)</author>#', $rss_feed, $author, PREG_SET_ORDER); 
//preg_match_all('#<created_at>(.*?)</created_at>#', $rss_feed, $time, PREG_SET_ORDER); 

//
// Now that the RSS/XML is parsed.. Lets Make HTML !
//

// If there is not at least one title, then the feed was empty
// it happens sometimes, so lets be prepared and do something 
// reasonable
if(count($text) < 0)
{
	echo "No news at present, please check back later.<br><br>";
}
else
{
	/*$sInfo = "
		<script type=\"text/javascript\">
			window.onload = function () {
				var divInfoToReturn = document.getElementById(\"divInfoToReturn\");
				parent.displayTwitterFeed(divInfoToReturn.innerHTML);
			};
		</script>
		<div id=\"divInfoToReturn\">";
		
		<input type="button" value="Refresh" onclick="requestTwitterFeed()" height="28px" />
		*/
	$cols = 4;
	$sInfo = '
	<table cellpadding="0px" cellspacing="0">
		<tr>
			<td colspan="'.$cols.'" class="youtube" align="right"></td>
		</tr>
		<tr>';
	// OK Here we go, this is the fun part
	// Well do up the top 4 entries from the feed
	$row1 = "";
	$row2 = "";
	for ($counter = 0; $counter <= 3; $counter++ )
	{
		// We do a reality check to make sure there is something we can show
		if(!empty($link[$counter][1]))
		{
			// Then we'll make a good faith effort to make the title
			// valid HTML
			$description[$counter][1] = str_replace("&", "&", $description[$counter][1]);
			$description[$counter][1] = str_replace("&apos;", "'", $description[$counter][1]); 	

			$title[$counter+1][1] = str_replace("&", "&", $title[$counter+1][1]);
			$title[$counter+1][1] = str_replace("&apos;", "'", $title[$counter+1][1]); 
			
			// The description often has encoded HTML entities in it, and
			// we probably don't want these, so we'll decode them
			$description[$counter][1] =  html_entity_decode( $description[$counter][1]);
			$title[$counter+1][1] =  html_entity_decode( $title[$counter+1][1]); 	

			// Now we make a pretty page bit from the data we retrieved from
			// the RSS feed.  Remember the function FormatRow from the 
			// beginning of the program ?  Here we put it to use.
			//$row = FormatRow($text[$counter][1],$screen_name[$counter][1],$profile_image_url[$counter][1]);
			$row1 .= FormatRow1($title[$counter+1][1], $link[$counter+1][1]);
			$row2 .= FormatRow2($link[$counter+1][1], $thumb[$counter][1], $description[$counter][1]);
			$row3 .= FormatRow3($author[$counter][1]);

			// And now we'll output the new page bit!
			//$sInfo .= $row1;
		}
	}
	$sInfo .= $row1 . '</tr><tr>' . $row2 . '</tr><tr>' . $row3 .'</tr></table></div>';
	echo $sInfo;
}
		
// Finally we'll save a copy of the pretty HTML we just created
// so that we can skip most of the work next time
$fp = fopen($cachefilename, 'w'); 
fwrite($fp, $sInfo); //ob_get_contents()); 
fclose($fp); 

// All Finished!
ob_end_flush(); // Send the output to the browser
?>