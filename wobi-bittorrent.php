<?php
/*
Plugin Name: Wobi - Bittorrent
Plugin URI: http://www.fovea.cc/wobi
Description: Bittorrent tracker for your website. Wobi will create and host links to .torrent files for your audio and videos media.
Version: 20100420.0
Author: Jean-Christophe Hoelt
Author URI: http://www.fovea.cc/
License: GPL2
*/

/*  Copyright 2010 Jean-Christophe Hoelt (email: hoelt@fovea.cc)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once 'wobi.php';

$WOBI_URL = defined('WP_PLUGIN_URL')
    ? trailingslashit(WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)))
    : trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__));
define('WOBI_URL', $WOBI_URL);

if (isset($_GET['activate']) && $_GET['activate'] == 'true') {
    wobi_install();
}

if (get_option('wobi_content') == 'on')  {
    add_filter('the_content', 'wobi_content', 100);
}

add_action( 'plugins_loaded', 'wobi_install' );
// add_action( 'after_plugin_row', 'yte_check_plugin_version' );

add_action('save_post', 'wobi_save_post');
// TODO v3.2 - don't use the edit_post hook.
add_action('edit_post', 'wobi_save_post');


function wobi_install()
{
    if(get_option('wobi_content' == '') || !get_option('wobi_content')){
        $prefix = "rt_";
        $makenamemap= 'CREATE TABLE ' . $prefix . 'namemap (info_hash char(40) NOT NULL default "", filename varchar(250) NOT NULL default "", url varchar(250) NOT NULL default "", size bigint(20) unsigned NOT NULL, pubDate varchar(25) NOT NULL default "", PRIMARY KEY(info_hash)) ENGINE = innodb'; 	
        $makesummary = 'CREATE TABLE ' . $prefix . 'summary (info_hash char(40) NOT NULL default "", dlbytes bigint unsigned NOT NULL default 0, seeds int unsigned NOT NULL default 0, leechers int unsigned NOT NULL default 0, finished int unsigned NOT NULL default 0, lastcycle int unsigned NOT NULL default "0", lastSpeedCycle int unsigned NOT NULL DEFAULT "0", speed bigint unsigned NOT NULL default 0, piecelength int(11) NOT NULL default -1, numpieces int(11) NOT NULL default 0, PRIMARY KEY (info_hash)) ENGINE = innodb';
        $maketimestamps = 'CREATE TABLE ' . $prefix . 'timestamps (info_hash char(40) not null, sequence int unsigned not null auto_increment, bytes bigint unsigned not null, delta smallint unsigned not null, primary key(sequence), key sorting (info_hash)) ENGINE = innodb';
        $makespeedlimit = 'CREATE TABLE ' . $prefix . 'speedlimit (uploaded bigint(25) NOT NULL default 0, total_uploaded bigint(30) NOT NULL default 0, started bigint(25) NOT NULL default 0) ENGINE = innodb';
        $makewebseedfiles = 'CREATE TABLE ' . $prefix . 'webseedfiles (info_hash char(40) default NULL, filename char(250) NOT NULL default "", startpiece int(11) NOT NULL default 0, endpiece int(11) NOT NULL default 0, startpieceoffset int(11) NOT NULL default 0, fileorder int(11) NOT NULL default 0, UNIQUE KEY fileseq (info_hash,fileorder)) ENGINE = innodb';	
        mysql_query($makesummary) or die(_wobi_errorMessage() . "Can't make the summary table: " . mysql_error() . "</p>");
        mysql_query($makenamemap) or die(_wobi_errorMessage() . "Can't make the namemap table: " . mysql_error() . "</p>");
        mysql_query($maketimestamps) or die(_wobi_errorMessage() . "Can't make the timestamps table: " . mysql_error() . "</p>");
        mysql_query($makespeedlimit) or die(_wobi_errorMessage() . "Can't make the speedlimit table: " . mysql_error() . "</p>");
        mysql_query($makewebseedfiles) or die(_wobi_errorMessage() . "Can't make the webseedfiles table: " . mysql_error() . "</p>");
        mysql_query("INSERT INTO ".$prefix."speedlimit values (0,0,0)") or die(_wobi_errorMessage() . "Can't insert zeros into speedlimit table: " . mysql_error() . "</p>");
        echo "<p class=\"success\">Database was created successfully!</p><br><br>";
        add_option('wobi_content', 'on');
    }
    // if(get_option('wobi_config' == '') || !get_option('wobi_config')){
        $fpath = WP_CONTENT_DIR . "/plugins/wobi-bittorrent/dbconfig.php";
        $f = fopen($fpath, "w");
        fwrite($f, "<?php\n");
        fwrite($f, '$dbhost = \''.DB_HOST."';\n");
        fwrite($f, '$dbuser = \''.DB_USER."';\n");
        fwrite($f, '$dbpass = \''.DB_PASSWORD."';\n");
        fwrite($f, '$database = \''.DB_NAME."';\n");
        fwrite($f, '$website_url = \''. WP_CONTENT_DIR ."/plugins/wobi-bittorrent';\n\n");
        fclose($f);
    //  echo "<p class=\"success\">Config file created successfully!</p><br><br>";
    //    add_option('wobi_config', 'on');
    //}
    /*
    // register widget
    if (function_exists('register_sidebar_widget'))
    register_sidebar_widget('Smart YouTube', 'yte_widget'); 

    if (function_exists('register_widget_control')) 
    register_widget_control('Smart YouTube', 'yte_widgetcontrol');
     */
}
//delete_option('wobi_config');

function wobi_content($the_content, $side=0)
{
    // Adds links to .torrent...
    $first = true;
    $custom_fields = get_post_custom();
    foreach ($custom_fields as $k=>$v) {
        if (substr($k,0,8) == "torrent-") {
            $filename = explode("-",$k,2);
            $filename = $filename[1];
            $torrent_url = $v[0];
            if ($first) {
                $first = false;
                $the_content .= "<div class=torrents><h3>Download Torrents</h3><ul>";
            }
            $the_content .= "<li><a href=\"$torrent_url\">$filename</a></li>";
        }
    }
    if ($first === false)
        $the_content .= "</ul></div>";
    return $the_content;
}

function wobi_save_post($post_ID)
{
    $save_dir = getcwd();
    chdir(WP_CONTENT_DIR . "/plugins/wobi-bittorrent");
    if(!$_POST)
        return;

    /* $content = $_POST["post_content"];
       $_POST["media"] = get_bloginfo("url")."/wp-content/upload/*[^']";
       get_po*/
    $custom_fields = get_post_custom($post_ID);
    $my_custom_field = $custom_fields['enclosure'];
    $added = array();
    if (is_array($my_custom_field)) {
        foreach ($my_custom_field as $key => $value) {
            $tmp1 = explode("\n", $value);
            $file_url = trim($tmp1[0]);
            $file_type = trim($tmp1[2]);
            $tmp2 = explode("/wp-content/", $file_url);
            $file_path = WP_CONTENT_DIR . "/" . $tmp2[1];
            $filename = basename($file_path);
            if (!isset($added[$filename])) {
                if (get_post_meta($post_ID, "torrent-$filename", true) == '') {
                    if ($pf = wobi_publish_file($file_path, $file_url)) {
                        $added[$filename] = $pf;
                        add_post_meta($post_ID, "torrent-$filename", $pf, true);
                    }
                }
            }
        }
    }
    chdir($save_dir);
}

