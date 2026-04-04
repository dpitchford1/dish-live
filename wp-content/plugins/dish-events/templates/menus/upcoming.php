<?php
/**
 * Template: upcoming menus list.
 *
 * Rendered by [dish_upcoming_menus] via MenuView::render_upcoming().
 *
 * Variables in scope (set by MenuView):
 *   $entries — array of associative arrays:
 *     'instance'      WP_Post  dish_class post
 *     'template'      WP_Post  dish_class_template post
 *     'format'        WP_Post|null
 *     'chef_names'    string[]
 *     'start'         int      Unix timestamp
 *     'menu_items'    string   newline-separated dish list (may be empty)
 *     'dietary_flags' string[] allergen keys
 *     'friendly_for'  string[] friendly-for keys
 *
 * Theme override: {theme}/dish-events/menus/upcoming.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Admin\MenuMetaBox;
use Dish\Events\Helpers\DateHelper;

$_flag_labels     = MenuMetaBox::DIETARY_FLAGS;
$_friendly_labels = MenuMetaBox::FRIENDLY_FOR;
?>
<div class="dish-upcoming-menus">

	<?php foreach ( $entries as $entry ) :
		/** @var WP_Post $instance */
		$_instance   = $entry['instance'];
		$_template   = $entry['template'];
		$_format     = $entry['format'];
		$_start      = $entry['start'];
		$_date_label = $_start ? DateHelper::to_display( $_start ) : '';
		$_class_url  = $entry['class_url'];
		$_has_dietary  = ! empty( $entry['dietary_flags'] );
		$_has_friendly = ! empty( $entry['friendly_for'] );
		$_has_custom_dietary  = ! empty( $entry['custom_dietary'] );
		$_has_custom_friendly = ! empty( $entry['custom_friendly'] );
	?>
	<article class="dish-menu-entry">

		<header class="dish-menu-entry__header">
			<?php if ( $_date_label ) : ?>
				<time class="dish-menu-entry__date"
				      datetime="<?php echo esc_attr( $_start ? gmdate( 'c', $_start ) : '' ); ?>">
					<?php echo esc_html( $_date_label ); ?>
				</time>
			<?php endif; ?>
			<h2 class="dish-menu-entry__title">
				<a href="<?php echo esc_url( $_class_url ); ?>">
					<?php echo esc_html( $_template->post_title ); ?>
				</a>
			</h2>
		</header>

		<div class="dish-menu-entry__meta">
			<?php if ( ! empty( $entry['chefs'] ) ) : ?>
				<span class="dish-menu-entry__chef">
					<?php esc_html_e( 'Chef:', 'dish-events' ); ?>
					<?php foreach ( $entry['chefs'] as $_i => $_chef ) :
						if ( $_i > 0 ) echo ', ';
						if ( $_chef['url'] ) : ?>
							<a href="<?php echo esc_url( $_chef['url'] ); ?>"><?php echo esc_html( $_chef['name'] ); ?></a>
						<?php else :
							echo esc_html( $_chef['name'] );
						endif;
					endforeach; ?>
				</span>
			<?php endif; ?>

			<?php if ( $_format ) : ?>
				<span class="dish-menu-entry__format">
					<?php esc_html_e( 'Format:', 'dish-events' ); ?>
					<a href="<?php echo esc_url( get_permalink( $_format->ID ) ); ?>"><?php echo esc_html( $_format->post_title ); ?></a>
				</span>
			<?php endif; ?>
		</div>

		<?php if ( $entry['menu_items'] ) :
			$_items = array_filter( array_map( 'trim', explode( "\n", $entry['menu_items'] ) ) );
		?>
			<div class="dish-menu-entry__items">
				<h3 class="dish-menu-entry__items-heading"><?php esc_html_e( 'Menu', 'dish-events' ); ?></h3>
				<ul class="dish-menu-list">
					<?php foreach ( $_items as $_item ) : ?>
						<li class="dish-menu-list__item"><?php echo esc_html( $_item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( $_has_dietary || $_has_friendly || $_has_custom_dietary || $_has_custom_friendly ) : ?>
			<div class="dish-menu-entry__dietary">

				<?php if ( $_has_dietary ) :
					$_flag_display = array_map(
						fn( $k ) => $_flag_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
						$entry['dietary_flags']
					);
				?>
					<p class="dish-menu-entry__flags">
						<strong><?php esc_html_e( 'Dietary Flags:', 'dish-events' ); ?></strong>
						<?php echo esc_html( implode( ', ', array_merge( $_flag_display, $entry['custom_dietary'] ) ) ); ?>
					</p>
				<?php elseif ( $_has_custom_dietary ) : ?>
					<p class="dish-menu-entry__flags">
						<strong><?php esc_html_e( 'Dietary Flags:', 'dish-events' ); ?></strong>
						<?php echo esc_html( implode( ', ', $entry['custom_dietary'] ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( $_has_friendly || $_has_custom_friendly ) :
					$_friendly_display = [];
					if ( $_has_friendly ) {
						$_friendly_display = array_map(
							fn( $k ) => $_friendly_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
							$entry['friendly_for']
						);
					}
					$_friendly_all = array_merge( $_friendly_display, $entry['custom_friendly'] );
				?>
					<p class="dish-menu-entry__friendly">
						<?php echo esc_html( implode( ' / ', $_friendly_all ) ); ?>
					</p>
				<?php endif; ?>

				<?php if ( $_has_dietary || $_has_custom_dietary ) : ?>
					<p class="dish-menu-entry__disclaimer">
						<?php esc_html_e( 'Please contact us if any of the above dietary flags apply to you to ensure we can accommodate your dietary requirements.', 'dish-events' ); ?>
					</p>
				<?php endif; ?>

			</div>
		<?php endif; ?>

	</article>
	<?php endforeach; ?>

</div>
