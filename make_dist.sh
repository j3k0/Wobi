#!/bin/sh
rm -f *~
ZIPFILE="wobi-bittorrent-`date +%Y%m%d`.zip"
DIRECTORY="wobi-bittorrent"
FILES="wobi.php wobi_functions.php Torrent.php funcsv2.php announce.php tracker.php config.php BDecode.php BEncode.php sha1lib.php readme.txt wobi-bittorrent.php scrape.php seed.php"
mkdir -p $DIRECTORY/torrents
chmod 770 $DIRECTORY/torrents
touch $DIRECTORY/torrents/index.php
touch $DIRECTORY/wp-settings.php
cp $FILES $DIRECTORY
zip -r $ZIPFILE $DIRECTORY/
rm -fr $DIRECTORY
