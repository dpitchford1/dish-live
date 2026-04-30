<?php
/**
 * WP-CLI Recipe Importer — Dish Recipes
 * ======================================
 * Reads recipes-extracted.json and creates dish_recipe posts in WordPress.
 *
 * Usage:
 *   wp eval-file wp-content/plugins/dish-recipes/tools/import-recipes.php
 *
 * Options (define before running or pass via --define):
 *   DRY_RUN   — set to true to preview without writing anything (default: false)
 *   OVERWRITE — set to true to update posts that already exist (default: false)
 *
 * Run a dry-run first:
 *   wp eval-file wp-content/plugins/dish-recipes/tools/import-recipes.php --define="DRY_RUN=1"
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	echo "Run via WP-CLI: wp eval-file wp-content/plugins/dish-recipes/tools/import-recipes.php\n";
	exit( 1 );
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

$dry_run  = defined( 'DRY_RUN' )  && DRY_RUN;
$overwrite = defined( 'OVERWRITE' ) && OVERWRITE;

$json_file = __DIR__ . '/recipes-extracted.json';

// ---------------------------------------------------------------------------
// Preflight
// ---------------------------------------------------------------------------

if ( ! file_exists( $json_file ) ) {
	WP_CLI::error( "recipes-extracted.json not found.\nRun: python3 wp-content/plugins/dish-recipes/tools/extract-recipes.py" );
}

if ( ! post_type_exists( 'dish_recipe' ) ) {
	WP_CLI::error( 'dish_recipe post type not registered. Is the dish-recipes plugin active?' );
}

$json    = file_get_contents( $json_file );
$recipes = json_decode( $json, true );

if ( ! is_array( $recipes ) || empty( $recipes ) ) {
	WP_CLI::error( 'recipes-extracted.json is empty or invalid JSON.' );
}

WP_CLI::log( sprintf( 'Found %d recipes in JSON.', count( $recipes ) ) );

if ( $dry_run ) {
	WP_CLI::log( '--- DRY RUN — no posts will be created ---' );
}

// ---------------------------------------------------------------------------
// Import
// ---------------------------------------------------------------------------

$created  = 0;
$updated  = 0;
$skipped  = 0;
$errors   = [];

foreach ( $recipes as $index => $recipe ) {
	$title = trim( $recipe['title'] ?? '' );

	if ( empty( $title ) ) {
		WP_CLI::warning( sprintf( 'Row %d: empty title, skipping.', $index ) );
		$errors[] = "Row {$index}: empty title";
		continue;
	}

	// Check for existing post by title.
	$existing = get_posts( [
		'post_type'              => 'dish_recipe',
		'post_status'            => 'any',
		'title'                  => $title,
		'posts_per_page'         => 1,
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'fields'                 => 'ids',
	] );

	$existing_id = $existing[0] ?? null;

	if ( $existing_id && ! $overwrite ) {
		WP_CLI::log( "  SKIP (exists, ID {$existing_id}): {$title}" );
		$skipped++;
		continue;
	}

	// ---- Category term ----
	$category_name = trim( $recipe['category'] ?? '' );
	$term_id       = null;

	if ( $category_name ) {
		$term = get_term_by( 'name', $category_name, 'dish_recipe_category' );

		if ( ! $term ) {
			if ( ! $dry_run ) {
				$result = wp_insert_term( $category_name, 'dish_recipe_category' );
				if ( ! is_wp_error( $result ) ) {
					$term_id = (int) $result['term_id'];
					WP_CLI::log( "  Created category: {$category_name}" );
				} else {
					WP_CLI::warning( "  Could not create category '{$category_name}': " . $result->get_error_message() );
				}
			} else {
				WP_CLI::log( "  [DRY] Would create category: {$category_name}" );
			}
		} else {
			$term_id = (int) $term->term_id;
		}
	}

	// ---- Post data ----
	$post_data = [
		'post_title'  => $title,
		'post_status' => 'publish',
		'post_type'   => 'dish_recipe',
		'post_excerpt' => '',
	];

	if ( $dry_run ) {
		$ing_count = array_sum( array_map(
			fn( $s ) => count( $s['items'] ?? [] ),
			$recipe['ingredients'] ?? []
		) );
		$step_count = array_sum( array_map(
			fn( $s ) => count( $s['steps'] ?? [] ),
			$recipe['method'] ?? []
		) );

		WP_CLI::log( sprintf(
			'  [DRY] Would %s: "%s"  [cat: %s | yield: %s | %d ingredients | %d steps]',
			$existing_id ? 'UPDATE' : 'CREATE',
			$title,
			$category_name ?: '—',
			$recipe['yield'] ?: '—',
			$ing_count,
			$step_count
		) );

		$existing_id ? $updated++ : $created++;
		continue;
	}

	// ---- Create / update post ----
	if ( $existing_id ) {
		$post_data['ID'] = $existing_id;
		$post_id = wp_update_post( $post_data, true );
		$action  = 'UPDATED';
	} else {
		$post_id = wp_insert_post( $post_data, true );
		$action  = 'CREATED';
	}

	if ( is_wp_error( $post_id ) ) {
		WP_CLI::warning( "  FAILED: {$title} — " . $post_id->get_error_message() );
		$errors[] = "{$title}: " . $post_id->get_error_message();
		continue;
	}

	// ---- Assign category ----
	if ( $term_id ) {
		wp_set_object_terms( $post_id, $term_id, 'dish_recipe_category' );
	}

	// ---- Post meta ----
	if ( ! empty( $recipe['yield'] ) ) {
		update_post_meta( $post_id, 'dish_recipe_yield', sanitize_text_field( $recipe['yield'] ) );
	}

	update_post_meta(
		$post_id,
		'dish_recipe_ingredients',
		wp_json_encode( $recipe['ingredients'] ?? [], JSON_UNESCAPED_UNICODE )
	);

	update_post_meta(
		$post_id,
		'dish_recipe_method',
		wp_json_encode( $recipe['method'] ?? [], JSON_UNESCAPED_UNICODE )
	);

	// Initialise empty meta so the admin UI renders cleanly.
	if ( ! get_post_meta( $post_id, 'dish_recipe_dietary_flags', true ) ) {
		update_post_meta( $post_id, 'dish_recipe_dietary_flags', '[]' );
	}
	if ( ! get_post_meta( $post_id, 'dish_recipe_template_ids', true ) ) {
		update_post_meta( $post_id, 'dish_recipe_template_ids', '[]' );
	}

	WP_CLI::log( "  {$action} (ID {$post_id}): {$title}" );

	$existing_id ? $updated++ : $created++;
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

WP_CLI::success( sprintf(
	'%sImport complete — Created: %d | Updated: %d | Skipped: %d | Errors: %d',
	$dry_run ? '[DRY RUN] ' : '',
	$created,
	$updated,
	$skipped,
	count( $errors )
) );

if ( ! empty( $errors ) ) {
	WP_CLI::log( "\nErrors:" );
	foreach ( $errors as $e ) {
		WP_CLI::warning( "  {$e}" );
	}
}
