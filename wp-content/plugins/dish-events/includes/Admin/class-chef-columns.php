<?php
/**
 * Custom admin columns for the dish_chef CPT list table.
 *
 * Columns added
 * -------------
 *  - dish_thumb : featured image thumbnail
 *  - dish_role  : chef role (dish_chef_role meta)
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;

/**
 * Class ChefColumns
 */
final class ChefColumns {

	public function register_hooks( Loader $loader ): void {
		$loader->add_filter( 'manage_dish_chef_posts_columns',       $this, 'add_columns' );
		$loader->add_action( 'manage_dish_chef_posts_custom_column', $this, 'render_column', 10, 2 );
	}

	/**
	 * Define the column set for the dish_chef list table.
	 *
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		$new = [
			'cb'         => $columns['cb'],
			'dish_thumb' => '',
			'title'      => $columns['title'],
			'dish_role'  => __( 'Role', 'dish-events' ),
		];

		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}

		return $new;
	}

	/**
	 * Render each custom column cell.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {

			case 'dish_thumb':
				$thumb = get_the_post_thumbnail( $post_id, [ 60, 60 ] );
				if ( $thumb ) {
					printf(
						'<a href="%s" style="display:block;line-height:0">%s</a>',
						esc_url( get_edit_post_link( $post_id ) ?? '' ),
						$thumb // phpcs:ignore WordPress.Security.EscapeOutput -- WP-generated markup
					);
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_role':
				$role = (string) get_post_meta( $post_id, 'dish_chef_role', true );
				echo $role ? esc_html( $role ) : '<span style="color:#999">—</span>';
				break;
		}
	}
}
