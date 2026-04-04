<?php

declare(strict_types=1);

/**
 * dish_the_breadcrumb()
 *
 * Global template tag — renders the schema.org breadcrumb nav for all dish CPT
 * single pages. Auto-detects the current post type and builds the full crumb
 * trail from the Classes archive root down to the current page.
 *
 * Supported trails:
 *   dish_format         → Classes › [Current Format]
 *   dish_class_template → Classes › [Format] › [Current Template]
 *   dish_class          → Classes › [Format] › [Template] › [Current Date]
 *
 * Called from: dish-events/{formats,class-templates,classes}/single.php
 *
 * @package basecamp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\FormatRepository;
use Dish\Events\Helpers\DateHelper;

/**
 * Output the breadcrumb nav for the current dish CPT single page.
 *
 * @return void
 */
function dish_the_breadcrumb(): void {

	$post_type   = get_post_type();
	$archive_url = (string) get_post_type_archive_link( 'dish_format' );

	/** @var array<int, array{url: string|null, label: string, color?: string, current?: bool}> $crumbs */
	$crumbs = [];

	switch ( $post_type ) {

		case 'dish_format':
			$format_color = (string) FormatRepository::get_meta( get_the_ID(), 'dish_format_color' );
			$crumbs       = [
				[ 'url' => $archive_url, 'label' => __( 'Classes', 'dish-events' ) ],
				[ 'url' => null,         'label' => get_the_title(), 'color' => $format_color, 'current' => true ],
			];
			break;

		case 'dish_class_template':
			$template_id  = get_the_ID();
			$format_id    = (int) get_post_meta( $template_id, 'dish_format_id', true );
			$format       = $format_id ? get_post( $format_id ) : null;
			$format_color = ( $format && $format_id )
				? (string) get_post_meta( $format_id, 'dish_format_color', true )
				: '';

			$crumbs = [
				[ 'url' => $archive_url, 'label' => __( 'Classes', 'dish-events' ) ],
			];

			if ( $format instanceof \WP_Post && 'publish' === $format->post_status ) {
				$crumbs[] = [
					'url'   => (string) get_permalink( $format ),
					'label' => $format->post_title,
					'color' => $format_color,
				];
			}

			$crumbs[] = [ 'url' => null, 'label' => get_the_title(), 'current' => true ];
			break;

		case 'dish_class':
			$class_id     = get_the_ID();
			$template_id  = (int) get_post_meta( $class_id, 'dish_template_id', true );
			$template     = $template_id ? get_post( $template_id ) : null;
			$format_id    = $template_id ? (int) get_post_meta( $template_id, 'dish_format_id', true ) : 0;
			$format_post  = $format_id ? get_post( $format_id ) : null;
			$format_color = $format_post
				? (string) get_post_meta( $format_id, 'dish_format_color', true )
				: '';
			$start        = (int) get_post_meta( $class_id, 'dish_start_datetime', true );

			$crumbs = [
				[ 'url' => $archive_url, 'label' => __( 'Classes', 'dish-events' ) ],
			];

			if ( $format_post instanceof \WP_Post && 'publish' === $format_post->post_status ) {
				$crumbs[] = [
					'url'   => (string) get_permalink( $format_post ),
					'label' => $format_post->post_title,
					'color' => $format_color,
				];
			}

			if ( $template instanceof \WP_Post && 'publish' === $template->post_status ) {
				$crumbs[] = [
					'url'   => (string) get_permalink( $template ),
					'label' => $template->post_title,
				];
			}

			$crumbs[] = [
				'url'     => null,
				'label'   => $start ? DateHelper::to_display( $start ) : get_the_title(),
				'current' => true,
			];
			break;

		default:
			return;
	}

	?>
	<nav class="dish-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'dish-events' ); ?>">
		<ol
			itemscope
			itemtype="https://schema.org/BreadcrumbList"
			class="dish-breadcrumb__list"
		>
			<?php foreach ( $crumbs as $position => $crumb ) :
				$is_current = ! empty( $crumb['current'] );
				$color      = $crumb['color'] ?? '';
				$pos        = $position + 1;
			?>
			<li
				class="dish-breadcrumb__item<?php echo $is_current ? ' dish-breadcrumb__item--current' : ''; ?>"
				itemprop="itemListElement"
				itemscope
				itemtype="https://schema.org/ListItem"
				<?php if ( $is_current ) : ?>aria-current="page"<?php endif; ?>
			>
				<?php if ( $crumb['url'] ) : ?>
					<a
						itemprop="item"
						href="<?php echo esc_url( $crumb['url'] ); ?>"
						<?php if ( $color ) : ?>class="dish-format-pill" style="--format-color:<?php echo esc_attr( $color ); ?>"<?php endif; ?>
					>
						<span itemprop="name"><?php echo esc_html( $crumb['label'] ); ?></span>
					</a>
				<?php else : ?>
					<span
						itemprop="item"
						<?php if ( $color ) : ?>class="dish-format-pill" style="--format-color:<?php echo esc_attr( $color ); ?>"<?php endif; ?>
					>
						<span itemprop="name"><?php echo esc_html( $crumb['label'] ); ?></span>
					</span>
				<?php endif; ?>
				<meta itemprop="position" content="<?php echo esc_attr( (string) $pos ); ?>">
			</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}
