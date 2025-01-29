#!/bin/bash

WP_PATH='/path/to/wp';
SCRIPT_PATH='/path/to/this/script/dir'

# Directory to store dump files
DUMP_DIR="$SCRIPT_PATH/dump_files"
EXPORT_DIR="$SCRIPT_PATH/exported_posts"
mkdir -p "$DUMP_DIR" "$EXPORT_DIR"

# Get list of site IDs
site_ids=$(mysql -N -e "SELECT blog_id FROM wp_blogs" commons_wp)

cd "$WP_PATH"

for site_id in $site_ids; do
    echo "Processing site $site_id..."

    # Dump the posts table with utf8
    mysqldump --default-character-set=utf8 commons_wp wp_${site_id}_posts > "$DUMP_DIR/site_${site_id}_utf8.sql"

    # Dump the posts table with utf8mb4
    mysqldump --default-character-set=utf8mb4 commons_wp wp_${site_id}_posts > "$DUMP_DIR/site_${site_id}_utf8mb4.sql"

		awk '!/^(.*SET NAMES|-- Dump completed on)/' "$DUMP_DIR/site_${site_id}_utf8.sql" > "$DUMP_DIR/site_${site_id}_utf8_clean.sql"
		awk '!/^(.*SET NAMES|-- Dump completed on)/' "$DUMP_DIR/site_${site_id}_utf8mb4.sql" > "$DUMP_DIR/site_${site_id}_utf8mb4_clean.sql"

    # Calculate checksums
		checksum_utf8=$(md5sum "$DUMP_DIR/site_${site_id}_utf8_clean.sql" | awk '{print $1}')
		checksum_utf8mb4=$(md5sum "$DUMP_DIR/site_${site_id}_utf8mb4_clean.sql" | awk '{print $1}')

    # Compare checksums
    if [[ "$checksum_utf8" == "$checksum_utf8mb4" ]]; then
        echo "No differences detected for site $site_id."
    else
        echo "Differences detected for site $site_id. Analyzing rows..."

        # Call PHP script for detailed analysis
				wp eval-file "$SCRIPT_PATH/analyze_rows.php" "$site_id" "$EXPORT_DIR/site_${site_id}_posts.json"
    fi

    # Clean up dump files
    rm -f "$DUMP_DIR/site_${site_id}_utf8.sql" "$DUMP_DIR/site_${site_id}_utf8mb4.sql" "$DUMP_DIR/site_${site_id}_utf8_clean.sql" "$DUMP_DIR/site_${site_id}_utf8mb4_clean.sql"
done

echo "Processing complete."
