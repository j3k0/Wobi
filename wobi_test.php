<html>
<head>
	<title>Testing Wobi</title>
	<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
	<!--link rel="stylesheet" href="./css/style.css" type="text/css" /-->
</head>
<body>
<?php
include_once 'wobi.php';

wobi_publish_file("/home/jeko/public_html/test.mp3", "http://localhost/jeko/test.mp3");
?>
