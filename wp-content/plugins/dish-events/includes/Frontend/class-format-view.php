<?php
/**
 * FormatView — renders dish_format grids for the [dish_formats] shortcode.
 *
 * Shortcode attributes:
 *   limit  (int)    Number of formats to show. Default -1 (all).
 *   order  (string) ASC or DESC. Default ASC.
 *
 * Example: [dish_formats limit="4"]
 *
 * @package Dish\Events\Frontend
 */

declare( strict_types=1 );

namespace Dish\Events\Frontend;

/**
 * Class FormatView
 */
final class FormatView {

	/**
	 * [dish_formats] shortcode handler.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_archive( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'limit' => -1,
				'order' => 'ASC',
			],
			(array) $atts,
			'dish_formats'
		);

		$order  = strtoupper( (string) $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$limit  = (int) $atts['limit'];

		$formats = get_posts( [
			'post_type'      => 'dish_format',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'menu_order',
			'order'          => $order,
			'meta_query'     => [ 'relation' => 'OR', [
				'key'     => 'dish_format_is_private',
				'compare' => 'NOT EXISTS',
			], [
				'key'     => 'dish_format_is_private',
				'value'   => '1',
				'compare' => '!=',
			] ],
		] );

		if ( empty( $formats ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="dish-card-grid dish-format-grid">
			<?php foreach ( $formats as $format ) : ?>
				<?php include Frontend::locate( 'formats/card.php' ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * [dish_class_types format_id="" limit="-1" order="ASC" columns="3"]
	 *
	 * Renders a card grid of all published dish_class_template posts belonging
	 * to a given format. Shows all types regardless of whether active instances
	 * exist — use this instead of [dish_classes] when you want class types, not
	 * dated instances.
	 *
	 * Attributes:
	 *   format_id (int)    Required. The dish_format post ID to filter by.
	 *   limit     (int)    Max templates to return. Default -1 (all).
	 *   order     (string) ASC or DESC. Default ASC.
	 *   columns   (int)    Grid column count (2, 3, or 4). Default 3.
	 *
	 * Example: [dish_class_types format_id="38" columns="4"]
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render_class_types( array|string $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'format_id'    => 0,
				'private_only' => '',
				'limit'        => -1,
				'order'        => 'ASC',
				'columns'      => 3,
			],
			(array) $atts,
			'dish_class_types'
		);

		// private_only="1" — auto-resolve to the first private format.
		if ( ! empty( $atts['private_only'] ) ) {
			$private_formats = get_posts( [
				'post_type'      => 'dish_format',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'meta_query'     => [ [
					'key'     => 'dish_format_is_private',
					'value'   => '1',
					'compare' => '=',
				] ],
			] );
			$format_id = ! empty( $private_formats ) ? (int) $private_formats[0]->ID : 0;
		} else {
			$format_id = (int) $atts['format_id'];
		}

		if ( ! $format_id ) {
			return '';
		}

		$order     = strtoupper( (string) $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$col_count = max( 2, min( 4, (int) $atts['columns'] ) );

		$templates = get_posts( [
			'post_type'      => 'dish_class_template',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
			'orderby'        => 'title',
			'order'          => $order,
			'no_found_rows'  => true,
			'meta_query'     => [ [
				'key'     => 'dish_format_id',
				'value'   => $format_id,
				'type'    => 'NUMERIC',
				'compare' => '=',
			] ],
		] );

		if ( empty( $templates ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="dish-card-grid dish-template-grid grid-general grid--<?php echo esc_attr( (string) $col_count ); ?>col">
			<?php foreach ( $templates as $template ) : ?>
				<?php include Frontend::locate( 'class-templates/card.php' ); ?>
			<?php endforeach; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
