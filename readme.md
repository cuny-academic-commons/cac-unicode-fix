Whoops! If you run a big WordPress Multisite database migration and find out that you messed up the encoding, these tools may help you to repair it after the fact.

Use at your own risk.

# Finding affected sites

On the source server, use the `check-sites.sh` tool to find sites that are affected. It uses the following heuristic:
1. Connect to the database with both utf8 and utf8mb4 encodings, and generate full dumps of the posts table.
2. If the checksums for these dumps differ, then loop through each post in the site's post table.
3. For each post, fetch it with both encodings and compare (in PHP, so that differences in encoding are detected - checksums don't work)
4. If a post is found to be different, a JSON file is created in the exported_posts directory. The JSON file contains each post's ID, date_modified, "bad" post_content, and "good" post_content.

# Repairing affected sites

Move the exported_posts directory to your destination server.

On the destination server, first run `backup-before-import.sh` or something similar. It will generate a backup for each posts table reflected in the exported_posts directory.

The `import.php` tool must be run using `wp eval-file`. You'll have to pass the path to the exported_posts directory as an argument. There's a `$dry_run` flag in the script that is set to `true` by default. There's also an `$allowed_blog_ids` block that you can use to test with a limited number of sites.

The importer skips any post where the post_content in the destination database does not match the "bad" content from the JSON. In this way, it avoids overwriting any manual change that has been made since the bad migration.

The importer will flush object caches. If you need to flush static page caches, you'll need your own tool.
