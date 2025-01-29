<?php
/**
 * Script to restore posts from JSON files in the exported_posts directory.
 *
 * Usage: wp eval-file import.php /path/to/exported_posts
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Prevent direct script access.
}

// Dry run flag: set to true to simulate changes without modifying the database.
$dry_run = true;

// Get the directory from $args[0]
if ( empty( $args[0] ) ) {
    echo "Error: Please provide the path to the exported_posts directory.\n";
    exit( 1 );
}

$directory = rtrim( $args[0], '/' );
if ( ! is_dir( $directory ) ) {
    echo "Error: Directory $directory does not exist.\n";
    exit( 1 );
}

$json_files = glob( "$directory/site_*_posts.json" );
if ( empty( $json_files ) ) {
    echo "No JSON files found in $directory.\n";
    exit( 0 );
}

foreach ( $json_files as $json_file ) {
    echo "Processing $json_file...\n";

    // Parse the JSON file.
    $data = json_decode( file_get_contents( $json_file ), true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        echo "Error parsing JSON file $json_file: " . json_last_error_msg() . "\n";
        continue;
    }

    if ( empty( $data ) ) {
        echo "No posts found in $json_file.\n";
        continue;
    }

    // Extract blog ID from the file name.
    preg_match( '/site_(\d+)_posts\.json$/', $json_file, $matches );
    $blog_id = isset( $matches[1] ) ? intval( $matches[1] ) : 0;

    if ( ! $blog_id ) {
        echo "Error: Could not determine blog ID from file name $json_file.\n";
        continue;
    }

	// For testing.
	/*
	$allowed_blog_ids = [ 9930 ];
	if ( ! in_array( $blog_id, $allowed_blog_ids ) ) {
		echo "Skipping blog ID $blog_id.\n";
		continue;
	}
	*/

    // Switch to the blog.
    switch_to_blog( $blog_id );

    global $wpdb;

	$imported_post_ids = [];
    foreach ( $data as $post ) {
        $post_id           = intval( $post['ID'] );
        $post_content_bad  = $post['post_content_bad'];
        $post_content_good = $post['post_content'];

        // Fetch current post content.
        $current_content = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
                $post_id
            )
        );

        if ( $current_content === null ) {
            echo "Post not found: blog_id:$blog_id post_id:$post_id\n";
            continue;
        }

        // Check if the post has been modified.
        if ( $current_content !== $post_content_bad ) {
            echo "Post skipped due to being modified by user: blog_id:$blog_id post_id:$post_id\n";
            continue;
        }

        // Dry run: simulate the update.
        if ( $dry_run ) {
            echo "Dry run: Post would be restored: blog_id:$blog_id post_id:$post_id\n";
        } else {
            // Overwrite the post content with the good version.
            $update_result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d",
                    $post_content_good,
                    $post_id
                )
            );

            if ( $update_result !== false ) {
                echo "Post restored: blog_id:$blog_id post_id:$post_id\n";
            } else {
                echo "Error updating post: blog_id:$blog_id post_id:$post_id\n";
            }

			wp_cache_delete( $post_id, 'posts' );
        }
    }

	wp_cache_delete( 'wp_get_archives', 'general' );
	wp_cache_delete( 'all_page_ids', 'posts' );
	wp_cache_set_posts_last_changed();

    // Restore the previous blog.
    restore_current_blog();
}

echo "Import process completed.\n";
