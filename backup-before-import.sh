#!/bin/bash

BACKUP_DIR="./backup"
EXPORTED_FILES_DIR="./exported_posts"
DB_NAME="your-database-name-here"

mkdir -p "$BACKUP_DIR"

EXPORTED_FILES=$(ls -1 $EXPORTED_FILES_DIR)
for file in $EXPORTED_FILES; do
  # Files are of the form site_123_posts.json and we need to get the site ID
	site_id=$(echo $file | sed 's/site_\([0-9]*\)_posts.json/\1/')
	echo "Backing up site $site_id..."
	mysqldump $DB_NAME wp_${site_id}_posts > $BACKUP_DIR/wp_${site_id}_posts.sql
done
