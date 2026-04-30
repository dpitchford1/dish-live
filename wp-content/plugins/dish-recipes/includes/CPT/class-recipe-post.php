<?php
/**
 * Registers the dish_recipe Custom Post Type and dish_recipe_category taxonomy.
 *
 * URL structure:
 *   /recipes/                              CPT archive
 *   /recipes/mains/                        Taxonomy term archive
 *   /recipes/mains/lemongrass-chicken/     Single recipe
 *
 * The taxonomy rewrite slug matches the CPT slug ('recipes') so that term
 * archives nest cleanly under /recipes/. WordPress resolves the collision
 * between CPT archive and taxonomy archive by treating taxonomy terms as
 * children of the CPT base URL.
 *
 * @package Dish\Recipes\CPT
 */

declare( strict_types=1 );

namespace Dish\Recipes\CPT;

/**
 * Class RecipePost
 */
final class RecipePost {

	/**
	 * Register the CPT and taxonomy.
	 * Hooked to 'init'.
	 */
	public function register(): void {
		$this->register_taxonomy();
		$this->register_post_type();
		$this->add_archive_rewrite_rule();
	}

	/**
	 * Add an explicit rewrite rule for /recipes/ so the archive resolves.
	 *
	 * When the CPT slug contains a taxonomy token (recipes/%dish_recipe_category%),
	 * WordPress only emits a rule matching that literal pattern — no plain
	 * recipes/?$ rule is created for the archive. We add it manually at top
	 * priority so it matches before the taxonomy rules.
	 */
	private function add_archive_rewrite_rule(): void {
		// Archive: /recipes/
		add_rewrite_rule(
			'^recipes/?$',
			'index.php?post_type=dish_recipe',
			'top'
		);

		// Paginated archive: /recipes/page/2/
		add_rewrite_rule(
			'^recipes/page/([0-9]+)/?$',
			'index.php?post_type=dish_recipe&paged=$matches[1]',
			'top'
		);

		// Paginated taxonomy term archive: /recipes/mains/page/2/
		add_rewrite_rule(
			'^recipes/([^/]+)/page/([0-9]+)/?$',
			'index.php?dish_recipe_category=$matches[1]&paged=$matches[2]',
			'top'
		);

		// Single recipe: /recipes/{category}/{post-slug}/
		// Must be 'top' priority — the taxonomy catch-all recipes/(.+?)/?$ would
		// otherwise match and resolve to a term archive instead of the single post.
		add_rewrite_rule(
			'^recipes/([^/]+)/([^/]+)/?$',
			'index.php?dish_recipe_category=$matches[1]&dish_recipe=$matches[2]',
			'top'
		);
	}

	// -------------------------------------------------------------------------
	// Taxonomy — must be registered before the CPT so WP can build rewrites
	// -------------------------------------------------------------------------

	private function register_taxonomy(): void {
		$labels = [
			'name'                       => __( 'Recipe Categories',                    'dish-recipes' ),
			'singular_name'              => __( 'Recipe Category',                      'dish-recipes' ),
			'search_items'               => __( 'Search Recipe Categories',             'dish-recipes' ),
			'all_items'                  => __( 'All Recipe Categories',                'dish-recipes' ),
			'parent_item'                => __( 'Parent Recipe Category',               'dish-recipes' ),
			'parent_item_colon'          => __( 'Parent Recipe Category:',              'dish-recipes' ),
			'edit_item'                  => __( 'Edit Recipe Category',                 'dish-recipes' ),
			'update_item'                => __( 'Update Recipe Category',               'dish-recipes' ),
			'add_new_item'               => __( 'Add New Recipe Category',              'dish-recipes' ),
			'new_item_name'              => __( 'New Recipe Category Name',             'dish-recipes' ),
			'not_found'                  => __( 'No recipe categories found.',          'dish-recipes' ),
			'menu_name'                  => __( 'Categories',                           'dish-recipes' ),
		];

		register_taxonomy( 'dish_recipe_category', 'dish_recipe', [
			'labels'            => $labels,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => false,
			'show_admin_column' => true,
			'query_var'         => true,
			// Rewrite under /recipes/ so term archives are /recipes/mains/ etc.
			'rewrite'           => [
				'slug'         => 'recipes',
				'with_front'   => false,
				'hierarchical' => true,
			],
		] );
	}

	// -------------------------------------------------------------------------
	// Post Type
	// -------------------------------------------------------------------------

	private function register_post_type(): void {
		// Replace %dish_recipe_category% in single permalinks with the actual term slug.
		add_filter( 'post_type_link', [ $this, 'filter_post_type_link' ], 10, 2 );

		// Strip the %dish_recipe_category% token from the CPT archive URL so it
		// resolves to /recipes/ rather than /recipes/%dish_recipe_category%/.
		add_filter( 'post_type_archive_link', [ $this, 'filter_archive_link' ], 10, 2 );

		$labels = [
			'name'                  => __( 'Recipes',                        'dish-recipes' ),
			'singular_name'         => __( 'Recipe',                         'dish-recipes' ),
			'add_new'               => __( 'Add New',                        'dish-recipes' ),
			'add_new_item'          => __( 'Add New Recipe',                 'dish-recipes' ),
			'edit_item'             => __( 'Edit Recipe',                    'dish-recipes' ),
			'new_item'              => __( 'New Recipe',                     'dish-recipes' ),
			'view_item'             => __( 'View Recipe',                    'dish-recipes' ),
			'view_items'            => __( 'View Recipes',                   'dish-recipes' ),
			'search_items'          => __( 'Search Recipes',                 'dish-recipes' ),
			'not_found'             => __( 'No recipes found.',              'dish-recipes' ),
			'not_found_in_trash'    => __( 'No recipes found in Trash.',     'dish-recipes' ),
			'all_items'             => __( 'All Recipes',                    'dish-recipes' ),
			'archives'              => __( 'Recipe Archives',                'dish-recipes' ),
			'attributes'            => __( 'Recipe Attributes',              'dish-recipes' ),
			'insert_into_item'      => __( 'Insert into recipe',             'dish-recipes' ),
			'uploaded_to_this_item' => __( 'Uploaded to this recipe',        'dish-recipes' ),
			'menu_name'             => __( 'Recipes',                        'dish-recipes' ),
			'name_admin_bar'        => __( 'Recipe',                         'dish-recipes' ),
		];

		register_post_type( 'dish_recipe', [
			'labels'             => $labels,
			'description'        => __( 'Structured recipe content with ingredients, method steps, and Schema.org output.', 'dish-recipes' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'menu_icon'          => 'dashicons-food',
			'menu_position'      => 25,
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,
			'has_archive'        => true,
			'hierarchical'       => false,
			'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
			'rewrite'            => [
				'slug'       => 'recipes/%dish_recipe_category%',
				'with_front' => false,
			],
			'capability_type'    => 'post',
			'query_var'          => true,
			'delete_with_user'   => false,
			'taxonomies'         => [ 'dish_recipe_category' ],
		] );
	}

	// -------------------------------------------------------------------------
	// Permalink filter
	// -------------------------------------------------------------------------

	/**
	 * Strip the rewrite token from the CPT archive URL.
	 * WordPress includes the literal token in the archive URL when the CPT slug
	 * contains a taxonomy placeholder — this filter corrects it back to /recipes/.
	 *
	 * @param string $link      The archive permalink.
	 * @param string $post_type The post type slug.
	 * @return string
	 */
	public function filter_archive_link( string $link, string $post_type ): string {
		if ( 'dish_recipe' !== $post_type ) {
			return $link;
		}

		// Remove the literal token and any trailing double-slash.
		$link = str_replace( '%dish_recipe_category%/', '', $link );
		$link = str_replace( '%dish_recipe_category%', '', $link );

		return trailingslashit( $link );
	}

	/**
	 * Replace %dish_recipe_category% in the permalink with the recipe's
	 * primary category slug. Falls back to 'uncategorised' if none is set.
	 *
	 * @param string   $post_link The post permalink.
	 * @param \WP_Post $post      The post object.
	 * @return string
	 */
	public function filter_post_type_link( string $post_link, \WP_Post $post ): string {
		if ( 'dish_recipe' !== $post->post_type ) {
			return $post_link;
		}

		if ( ! str_contains( $post_link, '%dish_recipe_category%' ) ) {
			return $post_link;
		}

		$terms = get_the_terms( $post->ID, 'dish_recipe_category' );
		$slug  = ( ! empty( $terms ) && ! is_wp_error( $terms ) )
			? $terms[0]->slug
			: 'uncategorised';

		return str_replace( '%dish_recipe_category%', $slug, $post_link );
	}
}
