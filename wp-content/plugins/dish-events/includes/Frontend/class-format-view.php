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
}
