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
        add_option('wobi_content', 'on');
    }
    /*
    // register widget
    if (function_exists('register_sidebar_widget'))
        register_sidebar_widget('Smart YouTube', 'yte_widget'); 

    if (function_exists('register_widget_control')) 
        register_widget_control('Smart YouTube', 'yte_widgetcontrol');
    */
}

function wobi_content($the_content, $side=0)
{
    // Adds links to .torrent...
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

