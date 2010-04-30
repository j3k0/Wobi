<?php
define('WOBI_URL', get_bloginfo('url') . '/wp-content/plugins/wobi-bittorrent');
define('WOBI_TRACKER_URL', WOBI_URL . '/announce.php');
define('WOBI_TORRENT_URL', WOBI_URL . '/torrents');

function _wobi_errorMessage()
{
    return "<p class=wobi-error>";
}

// Returns true on success, false on error.
function _wobi_addTorrent($torrent_file_path, $torrent_file_url, $file_path, $file_url)
{
	require ("config.php");

	$tracker_url = WOBI_TRACKER_URL;
    $httpseed = true;
    $tmp1 = explode("/wp-content/", $file_path);
    $relative_path = "../../" . $tmp1[1];
    $getrightseed = false;
    $httpftplocation = $file_url;
    $target_path = "torrents/";

    $autoset = true;
    $filename = ""; // $file_path; // Extracted from torrent (if $autoset)
    $url = "$file_url";      // Extracted from torrent (if $autoset)
	$hash = "";     // Extracted from torrent (if $autoset)

	// TODO: Only if not already connected.
    // $db = mysql_connect($dbhost, $dbuser, $dbpass) or die(errorMessage() . "Couldn't connect to the database, contact the administrator.</p>");
	// mysql_select_db($database) or die(errorMessage() . "Can't open the database.</p>");
	
	require_once ("funcsv2.php");
	require_once ("BDecode.php");
	require_once ("BEncode.php");
	
    // Check for errors, we don't care right?
    $fd = fopen($torrent_file_path, "rb") or die(_wobi_errorMessage() . "File upload error 1</p>\n");
    // is_uploaded_file($torrent_file_path) or die(_wobi_errorMessage() . "File upload error 2</p>\n");
    $alltorrent = fread($fd, filesize($torrent_file_path));

    $array = BDecode($alltorrent);
    if (!$array)
    {
        $wobi_error = _wobi_errorMessage() . "Error: The parser was unable to load your torrent.  Please re-create and re-upload the torrent.</p>\n";
        return false;
    }
    if (strtolower($array["announce"]) != $tracker_url)
    {
        $wobi_error = _wobi_errorMessage() . "Error: The tracker announce URL does not match this:<br>$tracker_url<br>Please re-create and re-upload the torrent.</p>\n";
        return false;
    }
    if ($httpseed && $relative_path == "")
    {
        $wobi_error = _wobi_errorMessage() . "Error: HTTP seeding was checked however no relative path was given.</p>\n";
        return false;
    }
    if ($httpseed && $relative_path != "")
    {
        if (Substr($relative_path, -1) == "/")
        {
            if (!is_dir($relative_path))
            {
                $wobi_error = _wobi_errorMessage() . "Error: HTTP seeding relative path ends in / but is not a valid directory.</p>\n";
                return false;
            }
        }
        else
        {
            if (!is_file($relative_path))
            {
                $wobi_error = _wobi_errorMessage() . "Error: HTTP seeding relative path is not a valid file.</p>\n";
                return false;
            }
        }
    }
    if ($getrightseed && $httpftplocation == "")
    {
        $wobi_error = _wobi_errorMessage() . "Error: GetRight HTTP seeding was checked however no URL was given.</p>\n";
        return false;
    }
    if ($getrightseed && (Substr($httpftplocation, 0, 7) != "http://" && Substr($httpftplocation, 0, 6) != "ftp://"))
    {
        $wobi_error = _wobi_errorMessage() . "Error: GetRight HTTP seeding URL must start with http:// or ftp://</p>\n";
        return false;
    }
    $hash = @sha1(BEncode($array["info"]));
    fclose($fd);
    
    $target_path = $target_path . basename($torrent_file_path); 
    $move_torrent = rename($torrent_file_path, $target_path);
    if ($move_torrent == false)
    {
        $wobi_error = errorMessage() . "Unable to move $torrent_file_path to torrents/</p>\n";
    }	

	if (!empty($filename)) // XXX can probably remove this...
		$filename = clean($filename);
	if (!empty($url)) // XXX and this
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
		$wobi_error = _wobi_errorMessage() . "Error: Info hash must be exactly 40 hex bytes.</p>\n";
        return false;
	}

	if (Substr($url, 0, 7) != "http://" && $url != "")
	{
		$wobi_error = _wobi_errorMessage() . "Error: The Torrent URL does not start with http:// Make sure you entered a correct URL.</p>\n";
        return false;
	}

	$query = "INSERT INTO ".$prefix."namemap (info_hash, filename, url, size, pubDate) VALUES (\"$hash\", \"$filename\", \"$url\", \"$total_size\", \"" . date('D, j M Y h:i:s') . "\")";
	$status = makeTorrent($hash, true);
	quickQuery($query);
    chmod($target_path, 0644);
	if ($status)
	{
		$wobi_error = "<p class=\"success\">Torrent was added successfully.</p>\n";
        require_once("wobi_functions.php");
        _wobi_addWebseedfiles($target_path, $relative_path, $httpftplocation, $hash);
        return true;
	}
	else
	{
		$wobi_error = _wobi_errorMessage() . "There were some errors. Check if this torrent has been added previously.</p>\n";
        return false;
	}
}

// Creates and register a torrent file to the tracker server.
//
// Returns http path of .torrent file.
function wobi_publish_file($file_path, $file_url)
{
    require_once 'Torrent.php';

    $filename = basename($file_path);
    $torrent_file_path = $file_path . ".torrent";
    $torrent_file_url = WOBI_TORRENT_URL . "/$filename.torrent";

    // 1- Create the Torrent
    $torrent = new Torrent(array($file_path), WOBI_TRACKER_URL);
    $fh = fopen($torrent_file_path, 'w') or die("can't open file");
    $stringData = (string)$torrent;
    fwrite($fh, $stringData);
    fclose($fh);

    // 2- Register to RivetTracker
    if (_wobi_addTorrent($torrent_file_path, $torrent_file_url, $file_path, $file_url))
        return $torrent_file_url;
    else
        return false;
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
