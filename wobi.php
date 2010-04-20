<?php
$WOBI_TRACKER_URL = get_bloginfo('url') . '/wp-content/plugins/wobi/announce.php';

// Example URL : "http://www.goodlooking.org/beta/wp-content/uploads/2010/04/charlie.mp3"
// Example Path: "/var/www/goodlooking/beta/wp-content/uploads/2010/04/charlie.mp3"

function _wobi_errorMessage()
{
    return "<p class=wobi-error>";
}

// Returns true on success, false on error.
function _wobi_addTorrent($torrent_file_path, $file_url, $file_path)
{

    global $WOBI_TRACKER_URL;
	require ("config.php");

	$tracker_url = $TRACKER_URL;
    $httpseed = true;
    $relative_path = "../../torrents/"; // From wp-content/plugins/wobi/ to wp-content/torrents/
    $getrightseed = true;
    $httpftplocation = $url;
    $target_path = "torrents/";

    $autoset = true;
    $filename = ""; // Extracted from torrent (if $autoset)
    // $url = "";   // Extracted from torrent (if $autoset) [now function argument]
	$hash = "";     // Extracted from torrent (if $autoset)

	// Already connected.
    // $db = mysql_connect($dbhost, $dbuser, $dbpass) or die(errorMessage() . "Couldn't connect to the database, contact the administrator</p>");
	// mysql_select_db($database) or die(errorMessage() . "Can't open the database.</p>");
	
	require_once ("funcsv2.php");
	require_once ("BDecode.php");
	require_once ("BEncode.php");
	
    // Check for errors, we don't care right?
    $fd = fopen($torrent_file_path, "rb") or die(_wobi_errorMessage() . "File upload error 1</p>\n");
    is_uploaded_file($torrent_file_path) or die(_wobi_errorMessage() . "File upload error 2</p>\n");
    $alltorrent = fread($fd, filesize($torrent_file_path));

    $array = BDecode($alltorrent);
    if (!$array)
    {
        echo _wobi_errorMessage() . "Error: The parser was unable to load your torrent.  Please re-create and re-upload the torrent.</p>\n";
        return false;
    }
    if (strtolower($array["announce"]) != $tracker_url)
    {
        echo _wobi_errorMessage() . "Error: The tracker announce URL does not match this:<br>$tracker_url<br>Please re-create and re-upload the torrent.</p>\n";
        return false;
    }
    if ($httpseed && $relative_path == "")
    {
        echo _wobi_errorMessage() . "Error: HTTP seeding was checked however no relative path was given.</p>\n";
        return false;
    }
    if ($httpseed && $relative_path != "")
    {
        if (Substr($relative_path, -1) == "/")
        {
            if (!is_dir($relative_path))
            {
                echo _wobi_errorMessage() . "Error: HTTP seeding relative path ends in / but is not a valid directory.</p>\n";
                return false;
            }
        }
        else
        {
            if (!is_file($relative_path))
            {
                echo _wobi_errorMessage() . "Error: HTTP seeding relative path is not a valid file.</p>\n";
                return false;
            }
        }
    }
    if ($getrightseed && $httpftplocation == "")
    {
        echo _wobi_errorMessage() . "Error: GetRight HTTP seeding was checked however no URL was given.</p>\n";
        endOutput();
        exit;
    }
    if ($getrightseed && (Substr($httpftplocation, 0, 7) != "http://" && Substr($httpftplocation, 0, 6) != "ftp://"))
    {
        echo _wobi_errorMessage() . "Error: GetRight HTTP seeding URL must start with http:// or ftp://</p>\n";
        endOutput();
        exit;
    }
    $hash = @sha1(BEncode($array["info"]));
    fclose($fd);
    
    $target_path = $target_path . basename( clean($_FILES['torrent']['name'])); 
    $move_torrent = move_uploaded_file($_FILES["torrent"]["tmp_name"], $target_path);
    if ($move_torrent == false)
    {
        echo _wobi_errorMessage() . "Unable to move " . $_FILES["torrent"]["tmp_name"] . " to torrents/</p>\n";
    }	
	

	if (!empty($filename))
		$filename = clean($filename);
	
	if (!empty($url))
		$url = clean($url);

	if ($autoset)
	{
		if (strlen($filename) == 0 && isset($array["info"]["name"]))
			$filename = $array["info"]["name"];
	}
	

	//figure out total size of all files in torrent
	$info = $array["info"];
	$total_size = 0;
	if (isset($info["files"]))
	{
		foreach ($info["files"] as $file)
		{
			$total_size = $total_size + $file["length"];
		}
	}
	else
	{
		$total_size = $info["length"];
	}
	
	//Validate torrent file, make sure everything is correct
	
	$filename = mysql_escape_string($filename);
	$filename = htmlspecialchars(clean($filename));
	$url = htmlspecialchars(mysql_escape_string($url));

	if ((strlen($hash) != 40) || !verifyHash($hash))
	{
		echo _wobi_errorMessage() . "Error: Info hash must be exactly 40 hex bytes.</p>\n";
		endOutput();
	}

	if (Substr($url, 0, 7) != "http://" && $url != "")
	{
		echo _wobi_errorMessage() . "Error: The Torrent URL does not start with http:// Make sure you entered a correct URL.</p>\n";
		endOutput();
	}

	$query = "INSERT INTO ".$prefix."namemap (info_hash, filename, url, size, pubDate) VALUES (\"$hash\", \"$filename\", \"$url\", \"$total_size\", \"" . date('D, j M Y h:i:s') . "\")";
	$status = makeTorrent($hash, true);
	quickQuery($query);
	if ($status)
	{
		echo "<p class=\"success\">Torrent was added successfully.</p>\n";
		echo "<a href=\"newtorrents.php\"><img src=\"images/add.png\" border=\"0\" class=\"icon\" alt=\"Add Torrent\" title=\"Add Torrent\" /></a><a href=\"newtorrents.php\">Add Another Torrent</a><br>\n";
		//rename torrent file to match filename
		rename("torrents/" . clean($_FILES['torrent']['name']), "torrents/" . $filename . ".torrent");
		//make torrent file readable by all
		chmod("torrents/" . $filename . ".torrent", 0644);
	
		//run RSS generator
		require_once("rss_generator.php");
		//Display information from DumpTorrentCGI.php
		require_once("torrent_functions.php");
	}
	else
	{
		echo _wobi_errorMessage() . "There were some errors. Check if this torrent has been added previously.</p>\n";
		//delete torrent file if it doesn't exist in database
		$query = "SELECT COUNT(*) FROM ".$prefix."summary WHERE info_hash = '$hash'";
		$results = mysql_query($query) or die(_wobi_errorMessage() . "Can't do SQL query - " . mysql_error() . "</p>");
		$data = mysql_fetch_row($results);
		if ($data[0] == 0)
		{
			if (file_exists("torrents/" . $_FILES['torrent']['name']))
				unlink("torrents/" . $_FILES['torrent']['name']);
		}
		//make torrent file readable by all
		chmod("torrents/" . $filename . ".torrent", 0644);
		endOutput();
	}
}

// Creates and register a torrent file to the tracker server.
//
// Returns http path of .torrent file.
function wobi_publish_file($file_path, $http_path)
{
    require_once 'Tracker.php';

    // 1- Create the Torrent at "$file_path.torrent"
    $torrent = new Torrent(array($file_path), $TRACKER_URL . '/announce.php');
    $myFile = $file_path . ".torrent";
    $fh = fopen($myFile, 'w') or die("can't open file");
    $stringData = (string)$torrent;
    fwrite($fh, $stringData);
    fclose($fh);

    // 2- Register to RivetTracker
}


// Show the links to the torrents of given post
function wobi_the_torrents($post_id = NULL)
{
    global $post;
    if ($post_id == NULL)
        $post_id = $post->ID;
    // - Retrieve list of torrents from post custom fields
    // - Format and display
}

// Creates and register torrent files to the tracker server.
//
// Adds torrent link to the post custom fields.
function wobi_publish($post_id)
{
    // - Find files for which no torrents exists.
    // - Create the Torrents.
    // - Store the Torrents in custom fields.
    // - Remove torrents custom fields no longer valid.
}
