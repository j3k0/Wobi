#!/bin/sh
rm -f *~
DIRECTORY="wobi-`date +%Y%m%d`"
FILES="wobi.php wobi_functions.php Torrent.php funcsv2.php announce.php tracker.php config.php BDecode.php BEncode.php sha1lib.php"
mkdir -p $DIRECTORY/torrents
chmod 770 $DIRECTORY/torrents
touch $DIRECTORY/torrents/index.php
cp $FILES $DIRECTORY
zip -r $DIRECTORY.zip $DIRECTORY/
rm -fr $DIRECTORY
