<?php
/**
 * WP-CLI: Import recipe descriptions from recipe-descriptions.csv
 *
 * Reads tools/recipe-descriptions.csv (columns: recipe_name, excerpt),
 * matches each row to a dish_recipe post by title, and updates post_excerpt.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/dish-recipes/tools/import-descriptions.php
 *
 * Dry-run (preview only, no DB writes):
 *   wp eval-file wp-content/plugins/dish-recipes/tools/import-descriptions.php --define="DRY_RUN=true"
 *
 * Flags (set via --define):
 *   DRY_RUN  — preview matches without writing (default false)
 */

$dry_run  = defined( 'DRY_RUN' ) && DRY_RUN;
$csv_file = __DIR__ . '/recipe-descriptions.csv';

if ( ! file_exists( $csv_file ) ) {
	WP_CLI::error( "recipe-descriptions.csv not found at: {$csv_file}" );
}

// ── Parse the CSV ────────────────────────────────────────────────────────────
// Expected columns: recipe_name, excerpt
// First row must be a header row.

$parsed = []; // [ title => description ]

if ( ( $fh = fopen( $csv_file, 'r' ) ) === false ) {
	WP_CLI::error( "Could not open {$csv_file}" );
}

$header = fgetcsv( $fh );
if ( ! $header ) {
	WP_CLI::error( 'CSV appears to be empty.' );
}

// Normalise header names (trim whitespace, lowercase)
$header = array_map( fn( $h ) => strtolower( trim( $h ) ), $header );
$name_col   = array_search( 'recipe_name', $header, true );
$excerpt_col = array_search( 'excerpt', $header, true );

if ( $name_col === false || $excerpt_col === false ) {
	WP_CLI::error( 'CSV must have columns: recipe_name, excerpt (got: ' . implode( ', ', $header ) . ')' );
}

while ( ( $row = fgetcsv( $fh ) ) !== false ) {
	$title = trim( $row[ $name_col ] ?? '' );
	$desc  = trim( $row[ $excerpt_col ] ?? '' );
	if ( $title === '' || $desc === '' ) {
		continue;
	}
	$parsed[ $title ] = $desc;
}

fclose( $fh );

if ( empty( $parsed ) ) {
	WP_CLI::error( 'No descriptions parsed from the CSV. Check formatting.' );
}

WP_CLI::log( sprintf( 'Parsed %d descriptions from CSV.', count( $parsed ) ) );

// ── Load all dish_recipe posts once ──────────────────────────────────────────
$posts = get_posts( [
	'post_type'      => 'dish_recipe',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'all',
] );

// Index by normalised title for fuzzy matching
$post_index = [];
foreach ( $posts as $post ) {
	$key = strtolower( trim( $post->post_title ) );
	$post_index[ $key ] = $post;
}

// ── Match & update ────────────────────────────────────────────────────────────
$updated  = 0;
$skipped  = 0;
$no_match = 0;

foreach ( $parsed as $title => $desc ) {
	$key  = strtolower( trim( $title ) );
	$post = $post_index[ $key ] ?? null;

	if ( ! $post ) {
		WP_CLI::warning( "No post found for: \"{$title}\"" );
		$no_match++;
		continue;
	}

	if ( $dry_run ) {
		WP_CLI::log( "[DRY RUN] Would update ID {$post->ID}: \"{$post->post_title}\"" );
		WP_CLI::log( "          → " . wp_trim_words( $desc, 20 ) );
		$updated++;
		continue;
	}

	$result = wp_update_post( [
		'ID'           => $post->ID,
		'post_excerpt' => wp_kses( $desc, [] ), // plain text only
	], true );

	if ( is_wp_error( $result ) ) {
		WP_CLI::warning( "Failed to update ID {$post->ID} \"{$post->post_title}\": " . $result->get_error_message() );
		$skipped++;
	} else {
		WP_CLI::log( "Updated [{$post->ID}]: {$post->post_title}" );
		$updated++;
	}
}

// ── Summary ──────────────────────────────────────────────────────────────────
WP_CLI::log( '' );
if ( $dry_run ) {
	WP_CLI::success( "DRY RUN complete. Would update: {$updated} | No match: {$no_match}" );
} else {
	WP_CLI::success( "Done. Updated: {$updated} | Skipped (errors): {$skipped} | No match: {$no_match}" );
}
