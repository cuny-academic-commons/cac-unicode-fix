<?php

if ( count( $args ) < 2 ) {
	echo "Usage: wp eval-file analyze_rows.php <site_id> <output_file>\n";
	exit( 1 );
}

$site_id = intval( $args[0] );
$output_file = $args[1];

global $wpdb;

// Switch to the specified site
switch_to_blog( $site_id );

echo "Analyzing posts for site $site_id...\n";

// Fetch all post IDs
$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts}" );

// Prepare output data
$output_data = [];

foreach ( $post_ids as $post_id ) {
    // Fetch post content in utf8
	$wpdb->query( "SET NAMES utf8" );
    $utf8_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

    // Fetch post content in utf8mb4
	$wpdb->query( "SET NAMES utf8mb4" );
    $utf8mb4_content = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

    // Compare content
    if ( $utf8_content !== $utf8mb4_content ) {
        $date_modified = $wpdb->get_var( $wpdb->prepare( "SELECT post_modified FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

        // Add to output data
        $output_data[] = [
            'ID'               => $post_id,
            'post_content_bad' => $utf8_content,
            'post_content'     => $utf8mb4_content,
            'date_modified'    => $date_modified,
        ];
    }
}

// Write output to JSON file
file_put_contents( $output_file, json_encode( $output_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );

echo "Exported mismatched posts to $output_file\n";

// Restore original site
restore_current_blog();
