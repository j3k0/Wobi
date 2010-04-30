<?php

require_once("BDecode.php");
require_once("BEncode.php");

function _wobi_addWebseedfiles($torrent_file_path, $relative_path, $httplocation, $hash)
{
    $prefix = WOBI_PREFIX;
    $fd = fopen($torrent_file_path, "rb") or die(errorMessage() . "File upload error 1</p>");
    $alltorrent = fread($fd, filesize($torrent_file_path));
    fclose($fd);
	$array = BDecode($alltorrent);

    // Add in Bittornado HTTP seeding spec
    //
    //add information into database
    $info = $array["info"] or die("Invalid torrent file.");
    $fsbase = $relative_path;
    
    // We need single file only!
    mysql_query("INSERT INTO ".$prefix."webseedfiles (info_hash,filename,startpiece,endpiece,startpieceoffset,fileorder) values (\"$hash\", \"".mysql_real_escape_string($fsbase)."\", 0, ". (strlen($array["info"]["pieces"])/20 - 1).", 0, 0)");
    
    // Edit torrent file
    //
    $data_array = $array;
    $data_array["httpseeds"][0] = WOBI_URL . "/seed.php";
    //$data_array["url-list"][0] = $httplocation;
    
    $to_write = BEncode($data_array);
    //write torrent file
    $write_httpseed = fopen($torrent_file_path, "wb");
    fwrite($write_httpseed, $to_write);
    fclose($write_httpseed);
    
    //add in piecelength and number of pieces
    $query = "UPDATE ".$prefix."summary SET piecelength=\"" . $info["piece length"] . "\", numpieces=\"" . strlen ($array["info"]["pieces"])/20 . "\" WHERE info_hash=\"" . $hash . "\"";
    quickQuery($query);
}
