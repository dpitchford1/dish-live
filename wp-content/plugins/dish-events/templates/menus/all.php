<?php
/**
 * Template: menus catalogue.
 *
 * Rendered by [dish_menus] via MenuView::render_all().
 *
 * Variables in scope (set by MenuView):
 *   $entries — array of associative arrays:
 *     'template'      WP_Post  dish_class_template post
 *     'format'        WP_Post|null
 *     'template_url'  string   permalink of the class-template page
 *     'next_date'     int|null Unix timestamp of the nearest future instance, or null
 *     'menu_items'    string   newline-separated dish list (may be empty)
 *     'dietary_flags' string[] allergen keys
 *     'friendly_for'  string[] friendly-for keys
 *
 * Theme override: {theme}/dish-events/menus/all.php
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
<div class="dish-menus">

	<?php foreach ( $entries as $entry ) :
		$_template     = $entry['template'];
		$_format       = $entry['format'];
		$_template_url = $entry['template_url'];
		$_next_date    = $entry['next_date'];
		$_has_dietary  = ! empty( $entry['dietary_flags'] );
		$_has_friendly = ! empty( $entry['friendly_for'] );
		$_has_custom_dietary  = ! empty( $entry['custom_dietary'] );
		$_has_custom_friendly = ! empty( $entry['custom_friendly'] );
		$_thumb_id     = get_post_thumbnail_id( $_template->ID );
	?>
	<article class="dish-menu-entry">

		<?php if ( $_thumb_id ) : ?>
			<a href="<?php echo esc_url( $_template_url ); ?>" class="dish-menu-entry__image-link" tabindex="-1" aria-hidden="true">
				<?php echo wp_get_attachment_image( $_thumb_id, 'basecamp-img-sm', false, [ 'class' => 'dish-menu-entry__img', 'loading' => 'lazy' ] ); ?>
			</a>
		<?php endif; ?>

		<div class="dish-menu-entry__body">

		<header class="dish-menu-entry__header">
			<?php if ( $_next_date ) : ?>
				<time class="dish-menu-entry__date"
				      datetime="<?php echo esc_attr( gmdate( 'c', $_next_date ) ); ?>">
					<?php
					/* translators: %s: human-readable date */
					printf( esc_html__( 'Next class: %s', 'dish-events' ), esc_html( DateHelper::to_display( $_next_date ) ) );
					?>
				</time>
			<?php endif; ?>
			<h2 class="dish-menu-entry__title">
				<a href="<?php echo esc_url( $_template_url ); ?>">
					<?php echo esc_html( $_template->post_title ); ?>
				</a>
			</h2>
		</header>

		<div class="dish-menu-entry__meta">
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

		</div><!-- /.dish-menu-entry__body -->

	</article>
	<?php endforeach; ?>

</div>
