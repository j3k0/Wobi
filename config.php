<?php //Please do NOT edit this file, use the admin page for changes.
require_once "../../../wp-config.php";
$GLOBALS['hiddentracker'] = false;
$GLOBALS['scrape'] = true;
$GLOBALS['report_interval'] = 1800;
$GLOBALS['min_interval'] = 300;
$GLOBALS['maxpeers'] = 50;
$GLOBALS['NAT'] = false;
$GLOBALS['persist'] = false;
$GLOBALS['ip_override'] = false;
$GLOBALS['countbytes'] = true;
$upload_username = 'upload';
$upload_password = '';
$admin_username = 'admin';
$admin_password = '';
$GLOBALS['title'] = 'Wobi Bittorrent';
$dbhost = DB_HOST;
$dbuser = DB_USER;
$dbpass = DB_PASSWORD;
$database = DB_NAME;
$enablerss = false;
$rss_title = 'RSS';
$rss_link = 'http://';
$rss_description = '';
$website_url = WP_CONTENT_DIR . '/plugins/wobi-bittorrent';
$GLOBALS['max_upload_rate'] = 2046;
$GLOBALS['max_uploads'] = 10;
$timezone = '+0000';
define(WOBI_PREFIX, 'rt_');
$prefix = WOBI_PREFIX;
?>
