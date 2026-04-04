<?php
/**
 * Registers the dish_class_template Custom Post Type.
 *
 * Class Templates are the public-facing canonical records for each class
 * offering (e.g. "German Beer Garden", "Knife Skills"). They hold the
 * title, description, featured image, gallery, and ticket type FK.
 *
 * Dated class instances (dish_class) are thin children that reference a
 * template via the dish_template_id post meta FK.
 *
 * URL structure (Phase 2.5):
 *   /classes/{format}/{slug}/
 *
 * The rewrite currently uses a temporary 'class-template' slug so Phase 2
 * can be tested without the %dish_class_format% token logic.
 * Phase 2.5 (ClassTemplateAdmin) replaces this rewrite with:
 *   ['slug' => 'classes/%dish_class_format%', 'with_front' => false]
 * and registers the post_type_link filter to resolve the token.
 *
 * @package Dish\Events\CPT
 */

declare( strict_types=1 );

namespace Dish\Events\CPT;

/**
 * Class ClassTemplatePost
 */
final class ClassTemplatePost {

	/**
	 * Register the CPT.
	 * Hooked to 'init'.
	 */
	public function register(): void {
		$this->register_post_type();
	}

	// -------------------------------------------------------------------------
	// Post Type
	// -------------------------------------------------------------------------

	private function register_post_type(): void {
		$labels = [
			'name'                  => __( 'Class Templates',                    'dish-events' ),
			'singular_name'         => __( 'Class Template',                     'dish-events' ),
			'add_new'               => __( 'Add New',                            'dish-events' ),
			'add_new_item'          => __( 'Add New Class Template',             'dish-events' ),
			'edit_item'             => __( 'Edit Class Template',                'dish-events' ),
			'new_item'              => __( 'New Class Template',                 'dish-events' ),
			'view_item'             => __( 'View Class Template',                'dish-events' ),
			'view_items'            => __( 'View Class Templates',               'dish-events' ),
			'search_items'          => __( 'Search Class Templates',             'dish-events' ),
			'not_found'             => __( 'No class templates found.',          'dish-events' ),
			'not_found_in_trash'    => __( 'No class templates found in Trash.', 'dish-events' ),
			'all_items'             => __( 'Class Templates',                    'dish-events' ),
			'archives'              => __( 'Class Template Archives',            'dish-events' ),
			'attributes'            => __( 'Class Template Attributes',          'dish-events' ),
			'insert_into_item'      => __( 'Insert into class template',         'dish-events' ),
			'uploaded_to_this_item' => __( 'Uploaded to this class template',    'dish-events' ),
			'menu_name'             => __( 'Class Templates',                    'dish-events' ),
			'name_admin_bar'        => __( 'Class Template',                     'dish-events' ),
		];

		register_post_type( 'dish_class_template', [
			'labels'             => $labels,
			'description'        => __( 'Canonical class descriptions with pricing and format. Public-facing template for dated class instances.', 'dish-events' ),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=dish_class', // Under Dish Events menu.
			'show_in_nav_menus'  => true,
			'show_in_rest'       => false,  // No block editor.
			'has_archive'        => false,  // Archive is the dish_class_format taxonomy term archive.
			'hierarchical'       => false,
			'supports'           => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
			// Phase 2.5 registers a custom add_rewrite_rule() for classes/{format-slug}/{template-slug}/
			// via ClassTemplateAdmin::register_rewrite_rule() + filter_post_type_link().
			'rewrite'            => false,
			'capability_type'    => 'post',
			'query_var'          => true,
			'delete_with_user'   => false,
		] );
	}
}
