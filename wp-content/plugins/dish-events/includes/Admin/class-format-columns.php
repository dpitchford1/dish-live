<?php
/**
 * Custom admin columns for the dish_format CPT list table.
 *
 * Columns added
 * -------------
 *  - dish_thumb        : featured image thumbnail
 *  - dish_color        : colour swatch (dish_format_color meta)
 *  - dish_ticket_types : count of linked ticket types
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;
use Dish\Events\Data\FormatRepository;

/**
 * Class FormatColumns
 */
final class FormatColumns {

	public function register_hooks( Loader $loader ): void {
		$loader->add_filter( 'manage_dish_format_posts_columns',        $this, 'add_columns' );
		$loader->add_action( 'manage_dish_format_posts_custom_column',  $this, 'render_column', 10, 2 );
	}

	/**
	 * Define the column set for the dish_format list table.
	 *
	 * @param array<string,string> $columns
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		$new = [
			'cb'                => $columns['cb'],
			'dish_thumb'        => __( 'IMG', 'dish-events' ),
			'title'             => $columns['title'],
			'dish_color'        => __( 'Colour', 'dish-events' ),
			'dish_visibility'   => __( 'Visibility', 'dish-events' ),
			'dish_ticket_types' => __( 'Ticket Types', 'dish-events' ),
		];

		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}

		return $new;
	}

	/**
	 * Render each custom column cell.
	 *
	 * @param string $column  Column key.
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
					echo '<span style="color:#999">&mdash;</span>';
				}
				break;

			case 'dish_color':
					$color = (string) FormatRepository::get_meta( $post_id, 'dish_format_color' );
				if ( $color ) {
					printf(
						'<span style="display:inline-block;width:24px;height:24px;border-radius:50%%;background:%s;border:1px solid rgba(0,0,0,.15);vertical-align:middle" title="%s"></span> <code style="font-size:11px">%s</code>',
						esc_attr( $color ),
						esc_attr( $color ),
						esc_html( $color )
					);
				} else {
					echo '<span style="color:#999">&mdash;</span>';
				}
				break;

			case 'dish_visibility':
				$is_private = (bool) get_post_meta( $post_id, 'dish_format_is_private', true );
				if ( $is_private ) {
					echo '<span style="color:#92400e;background:#fef9c3;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">Private</span>';
				} else {
					echo '<span style="color:#065f46;background:#d1fae5;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">Public</span>';
				}
				break;

			case 'dish_ticket_types':
				global $wpdb;
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$count = (int) $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}dish_ticket_types WHERE format_id = %d",
					$post_id
				) );
				echo esc_html( (string) $count );
				break;
		}
	}
}
