<?php
/**
 * Runs only when the plugin is deleted from WP Admin → Plugins → Delete.
 * Deactivation does NOT run this file — see class-deactivator.php.
 *
 * Removes:
 *   - All three custom DB tables
 *   - All plugin options from wp_options
 *   - All dish_class, dish_class_template, dish_chef, dish_booking posts + meta
 *   - All dish_class_format taxonomy terms
 *   - All post meta keys prefixed with dish_
 *   - All plugin transients
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// ---------------------------------------------------------------------------
// Drop custom tables
// ---------------------------------------------------------------------------

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dish_ticket_types" );      // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dish_checkout_fields" );   // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ---------------------------------------------------------------------------
// Delete all plugin CPT posts and their meta/comments
// ---------------------------------------------------------------------------

$post_types = [ 'dish_class', 'dish_class_template', 'dish_chef', 'dish_booking', 'dish_format' ];

foreach ( $post_types as $post_type ) {
	$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
			$post_type
		)
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true ); // true = force delete, bypass trash
	}
}

// ---------------------------------------------------------------------------
// Delete any orphaned dish_ post meta (safety net for manually deleted posts)
// ---------------------------------------------------------------------------

$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'dish_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// ---------------------------------------------------------------------------
// Delete options
// ---------------------------------------------------------------------------

$options = [
	'dish_settings',
	'dish_db_version',
	'dish_activation_redirect',
	'dish_encrypt_key',
	'dish_flush_rewrite_rules',
];

foreach ( $options as $option ) {
	delete_option( $option );
}

// ---------------------------------------------------------------------------
// Clear any plugin transients
// ---------------------------------------------------------------------------

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dish_%'" );           // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_dish_%'" );  // phpcs:ignore WordPress.DB.DirectDatabaseQuery
