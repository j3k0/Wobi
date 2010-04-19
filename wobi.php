<?php
require_once 'Tracker.php';

// Creates and register a torrent file to the tracker server.
//
// Returns http path of .torrent file.
function wobi_publish_file($file_path, $http_path)
{
    // 1- Create the Torrent
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
