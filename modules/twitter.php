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
	0 => "http://thespotnet:s0f7w0rks@twitter.com/statuses/friends_timeline.xml",
	1 => "http://thespotnet:s0f7w0rks@twitter.com/statuses/user_timeline.xml",
	2 => "http://thepizzy.net/bluejournal/?feed=rss2"
);


//
//  Makes a pretty HTML page bit from the title, 
//  description and link
//
function FormatRow($text, $screen_name, $profile_image_url) {
return <<<HTML
<!-- Twitter Entry -->
<table class="twitter_entry">
  <tr>
    <td class="twitter_avatar"><a href="http://www.twitter.com/$screen_name"><img src="$profile_image_url" width="25" height="25" border="0"></a></td>
    <td class="twitter_entry"><a href="http://www.twitter.com/$screen_name" class="screen_name"><b>$screen_name</b></a><span class="update">:&nbsp;$text</span></td>
  </tr>
</table>
<!-- End of Twitter Entry -->

HTML;
}

// we'll buffer the output
ob_start();

// Now we make sure that we have a feed selected to work with
$rss_url = $RSSFEEDS[$feedid];

// Server friendly page cache
$ttl = 60;// 60 secs  
$cachefilename = "twitter_cache";//md5($rss_url);
if (file_exists($cachefilename) && (time() - $ttl < filemtime($cachefilename))) {
	// We recently did the work, so we'll save bandwidth by not doing it again
	include($cachefilename);
	exit();
}

// Now we read the feed
$rss_feed = file_get_contents($rss_url);

// Now we replace a few things that may cause problems later
$rss_feed = str_replace("<![CDATA[", "", $rss_feed);
$rss_feed = str_replace("]]>", "", $rss_feed);
$rss_feed = str_replace("\n", "", $rss_feed);


// Now we get the nodes that we're interested in
preg_match_all('#<status>(.*?)</status>#', $rss_feed, $status, PREG_SET_ORDER); 
preg_match_all('#<text>(.*?)</text>#', $rss_feed, $text, PREG_SET_ORDER);
//preg_match_all('#<url>(.*?)</url>#', $rss_feed, $link, PREG_SET_ORDER);
preg_match_all('#<screen_name>(.*?)</screen_name>#', $rss_feed, $screen_name, PREG_SET_ORDER);  
preg_match_all('#<profile_image_url>(.*?)</profile_image_url>#', $rss_feed, $profile_image_url, PREG_SET_ORDER); 
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

	$sInfo = '
	<table cellpadding="0px" cellspacing="0">
		<tr>
			<td class="twitter" align="right"></td>
		</tr>
		<tr>
			<td>';
	// OK Here we go, this is the fun part
	// Well do up the top 3 entries from the feed
	for ($counter = 0; $counter <= 6; $counter++ )
	{
		// We do a reality check to make sure there is something we can show
		if(!empty($text[$counter][1]))
		{
			// Then we'll make a good faith effort to make the title
			// valid HTML
			$text[$counter][1] = str_replace("&", "&", $text[$counter][1]);
			$text[$counter][1] = str_replace("&apos;", "'", $text[$counter][1]); 	

			// The description often has encoded HTML entities in it, and
			// we probably don't want these, so we'll decode them
			$text[$counter][1] =  html_entity_decode( $text[$counter][1]);
			
			$link = '/http:\/\/[a-zA-Z0-9-_.\/]*/';
			//$www =  '/www.[a-zA-Z0-9-_]*.[a-zA-Z]*/';
			$ahref = '<a href="${0}">${0}</a>';
			//$ahref2 = '<a href="http://${0}">${0}</a>';
			$text[$counter][1] = preg_replace($link, $ahref, $text[$counter][1]);
			//$text[$counter][1] = preg_replace($www, $ahref2, $text[$counter][1]);
			
			$alias = '/(@)([\w\d]*)/';
			$ahref = '<a href="http://twitter.com/${2}">@${2}</a>';
			$text[$counter][1] = preg_replace($alias, $ahref, $text[$counter][1]);

			// Now we make a pretty page bit from the data we retrieved from
			// the RSS feed.  Remember the function FormatRow from the 
			// beginning of the program ?  Here we put it to use.
			$row = FormatRow($text[$counter][1],$screen_name[$counter][1],$profile_image_url[$counter][1]);

			// And now we'll output the new page bit!
			$sInfo .= $row;
		}
	}
	$sInfo .= '
			</td>
		</tr>
	</table></div>';
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