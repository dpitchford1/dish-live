<?php
/**
 * Dish Events — Template tags
 *
 * Global template functions for use in theme templates.
 * Not autoloadable (no class) — loaded explicitly by dish-events.php.
 *
 * Functions defined here:
 *   dish_the_breadcrumb()      — schema.org BreadcrumbList nav for dish CPT singles
 *   dish_the_menu()            — class menu section (items, dietary flags, friendly-for)
 *   dish_the_format_pill()     — coloured format badge anchor
 *   dish_get_enquiry_url()     — resolve the configured enquiry destination
 *   dish_the_upcoming_classes() — grid of upcoming class instance cards
 *   dish_the_spotlight_class() — "Class in the Spotlight" promotional component
 *   dish_the_format_pill()  — format pill anchor for card partials
 *   dish_get_enquiry_url()  — configured enquiry URL (page permalink or mailto fallback)
 *
 * @package Dish\Events
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Admin\MenuMetaBox;
use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\FormatRepository;
use Dish\Events\Frontend\Frontend;
use Dish\Events\Helpers\DateHelper;

/**
 * Output the breadcrumb nav for the current dish CPT single page.
 *
 * Builds a schema.org BreadcrumbList trail from the Classes archive root
 * down to the current page. Supported trails:
 *
 *   dish_format         → Classes › [Current Format]
 *   dish_class_template → Classes › [Format] › [Current Template]
 *   dish_class          → Classes › [Format] › [Template] › [Current Date]
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
				[ 'url' => $archive_url, 'label' => __( 'Class Formats', 'dish-events' ) ],
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
<nav class="breadcrumb-global" aria-label="<?php esc_attr_e( 'Breadcrumb', 'dish-events' ); ?>">
    <ol itemscope itemtype="https://schema.org/BreadcrumbList" class="is--flex-list">
        <?php foreach ( $crumbs as $position => $crumb ) :
            $is_current = ! empty( $crumb['current'] );
            $color      = $crumb['color'] ?? '';
            $pos        = $position + 1;
        ?>
        <li class="dish-breadcrumb__item<?php echo $is_current ? ' dish-breadcrumb__item--current' : ''; ?>" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" <?php if ( $is_current ) : ?>aria-current="page"<?php endif; ?>>
        <?php if ( $crumb['url'] ) : ?>
            <a itemprop="item" href="<?php echo esc_url( $crumb['url'] ); ?>" <?php if ( $color ) : ?>class="" style="--format-color:<?php echo esc_attr( $color ); ?>"<?php endif; ?>><span itemprop="name"><?php echo esc_html( $crumb['label'] ); ?></span></a> <span class="separator" aria-hidden="true">&nbsp;/&nbsp;</span>
        <?php else : ?>
            <span itemprop="item" <?php if ( $color ) : ?>class="" style="--format-color:<?php echo esc_attr( $color ); ?>"<?php endif; ?>><span itemprop="name" class="menu--selected"><?php echo esc_html( $crumb['label'] ); ?></span></span>
        <?php endif; ?>
            <meta itemprop="position" content="<?php echo esc_attr( (string) $pos ); ?>">
        </li>
        <?php endforeach; ?>
    </ol>
</nav>
	<?php
}

/**
 * Output the menu section for a class template.
 *
 * Renders "The Menu" section — items list, dietary flags, and friendly-for labels.
 * Used on both dish_class_template and dish_class single pages; data is always
 * sourced from the class template post meta.
 *
 * Outputs nothing when the template has no menu items or dietary flags.
 *
 * @param int $template_id The dish_class_template post ID to pull menu data from.
 * @return void
 */
function dish_the_menu( int $template_id ): void {

	if ( ! $template_id ) {
		return;
	}

	$menu_items    = (string) get_post_meta( $template_id, 'dish_menu_items', true );
	$menu_dietary  = (array)  json_decode( (string) get_post_meta( $template_id, 'dish_menu_dietary_flags', true ) ?: '[]', true );
	$menu_friendly = (array)  json_decode( (string) get_post_meta( $template_id, 'dish_menu_friendly_for',  true ) ?: '[]', true );

	if ( ! $menu_items && ! $menu_dietary ) {
		return;
	}

	$flag_labels     = MenuMetaBox::DIETARY_FLAGS;
	$friendly_labels = MenuMetaBox::FRIENDLY_FOR;
	?>
	<!-- <section class="dish-class-menus dish-containers" aria-label="<?php esc_attr_e( 'Class menu', 'dish-events' ); ?>"> -->
		<h3 class="dish-class-menu__heading"><?php esc_html_e( 'The Menu', 'dish-events' ); ?></h3>

		<?php if ( $menu_items ) :
			$items = array_filter( array_map( 'trim', explode( "\n", $menu_items ) ) );
		?>
			<ol class="dish-menu-lists">
				<?php foreach ( $items as $item ) : ?>
					<li class="dish-menu-list__item"><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>

		<?php if ( $menu_dietary || $menu_friendly ) : ?>
			<div class="dish-menu-dietary">
				<?php if ( $menu_dietary ) :
					$flag_display = array_map(
						fn( $k ) => $flag_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
						$menu_dietary
					);
				?>
					<p class="dish-menu-dietary__flags"><em><?php esc_html_e( 'Dietary Flags:', 'dish-events' ); ?></em> <?php echo esc_html( implode( ', ', $flag_display ) ); ?></p>
				<?php endif; ?>
				<?php if ( $menu_friendly ) :
					$friendly_display = array_map(
						fn( $k ) => $friendly_labels[ $k ] ?? ucfirst( str_replace( '_', ' ', $k ) ),
						$menu_friendly
					);
				?>
					<p class="dish-menu-dietary__friendly"><?php echo esc_html( implode( '/', $friendly_display ) . ' ' . __( 'Friendly', 'dish-events' ) ); ?></p>
				<?php endif; ?>
				<?php if ( $menu_dietary ) : ?>
					<p class="dish-menu-dietary__disclaimer"><em><?php esc_html_e( '*Please contact us if any of the above dietary flags apply to you to ensure we can accommodate your dietary requirements.*', 'dish-events' ); ?></em></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

	<!-- </section> -->
	<?php
}

/**
 * Output the format pill anchor element.
 *
 * Renders the coloured format badge used on class and template cards.
 * Outputs nothing if either argument is missing.
 *
 * @param \WP_Post|null $format_post  The dish_format post, or null.
 * @param string        $format_color Hex colour for the --format-color CSS custom property.
 * @return void
 */
function dish_the_format_pill( ?\WP_Post $format_post, string $format_color ): void {

	if ( ! $format_post || ! $format_color ) {
		return;
	}
	?>
	<a href="<?php echo esc_url( (string) get_permalink( $format_post ) ); ?>"
	   class="dish-format-pill"
	   style="--format-color:<?php echo esc_attr( $format_color ); ?>"
	   aria-label="<?php echo esc_attr( sprintf( __( 'Format: %s', 'dish-events' ), $format_post->post_title ) ); ?>"
	><?php echo esc_html( $format_post->post_title ); ?></a>
	<?php
}

/**
 * Return the configured enquiry destination URL.
 *
 * Reads the `enquiry_page` plugin setting; falls back to a mailto: link using
 * `studio_email` (or the WP admin email as a last resort).
 *
 * @return string
 */
function dish_get_enquiry_url(): string {
	$enquiry_page = (int) Settings::get( 'enquiry_page', 0 );
	return $enquiry_page
		? (string) get_permalink( $enquiry_page )
		: 'mailto:' . Settings::get( 'studio_email', (string) get_bloginfo( 'admin_email' ) );
}

/**
 * Output a grid of upcoming class instance cards.
 *
 * Fetches upcoming public instances ordered by start time and renders each
 * using the shared classes/card.php partial. Outputs nothing when no
 * instances are found.
 *
 * @param array $args {
 *   @type int      $limit                Max instances to show. Default 10.
 *   @type int      $template_id          Filter to a single class template.
 *   @type int[]    $template_ids         Filter to multiple class templates.
 *   @type bool     $dedupe_by_template   One card per template (the soonest). Default false.
 *   @type string   $heading              Section heading. Empty string to suppress. Default 'Upcoming Classes'.
 *   @type string   $heading_tag          HTML element for the heading. Default 'h2'.
 *   @type string   $section_class        Extra CSS classes on the <section>. Default ''.
 *   @type string   $grid_class           CSS classes on the card grid. Default 'grid-general grid--4col'.
 *   @type bool     $suppress_format_pill Hide the format pill on cards. Default false.
 * }
 * @return void
 */
function dish_the_upcoming_classes( array $args = [] ): void {
	$args = wp_parse_args( $args, [
		'limit'                => 10,
		'template_id'          => 0,
		'template_ids'         => [],
		'dedupe_by_template'   => false,
		'heading'              => __( 'Upcoming Classes', 'dish-events' ),
		'heading_tag'          => 'h2',
		'section_class'        => '',
		'grid_class'           => 'grid-general grid--4col',
		'suppress_format_pill' => false,
	] );

	$query_args = [
		'start_after' => time(),
		'limit'       => $args['dedupe_by_template'] ? -1 : (int) $args['limit'],
		'order'       => 'ASC',
	];

	if ( $args['template_id'] ) {
		$query_args['template_id'] = (int) $args['template_id'];
	} elseif ( ! empty( $args['template_ids'] ) ) {
		$query_args['template_ids'] = array_map( 'absint', (array) $args['template_ids'] );
	}

	$all_classes = ClassRepository::query( $query_args );

	if ( $args['dedupe_by_template'] ) {
		$seen    = [];
		$classes = [];
		$max     = (int) $args['limit'];
		foreach ( $all_classes as $class ) {
			$tid = (int) get_post_meta( $class->ID, 'dish_template_id', true );
			if ( $tid && ! isset( $seen[ $tid ] ) ) {
				$seen[ $tid ] = true;
				$classes[]    = $class;
				if ( $max > 0 && count( $classes ) >= $max ) {
					break;
				}
			}
		}
	} else {
		$classes = $all_classes;
	}

	if ( empty( $classes ) ) {
		return;
	} 

	$section_class        = trim( 'content-region ' . $args['section_class'] );
	$suppress_format_pill = (bool) $args['suppress_format_pill'];
	$tag                  = preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $args['heading_tag'] ) ) ?: 'h2';
	?>
	<section class="<?php echo esc_attr( $section_class ); ?>">
		<?php if ( $args['heading'] ) : ?>
			<<?php echo $tag; ?> class="section-heading"><?php echo esc_html( $args['heading'] ); ?></<?php echo $tag; ?>>
		<?php endif; ?>
		<div class="<?php echo esc_attr( $args['grid_class'] ); ?>">
			<?php foreach ( $classes as $class ) : ?>
				<?php include Frontend::locate( 'classes/card.php' ); ?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * Output the "Class in the Spotlight" promotional component.
 *
 * Queries for the single dish_class_template marked as spotlight
 * (dish_is_spotlight = 1) and renders the spotlight partial.
 * Outputs nothing when no template carries the spotlight flag.
 *
 * @return void
 */
function dish_the_spotlight_class(): void {
	$templates = get_posts( [
		'post_type'      => 'dish_class_template',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => [
			[
				'key'   => 'dish_is_spotlight',
				'value' => '1',
			],
		],
	] );

	if ( empty( $templates ) ) {
		return;
	}

	$template = $templates[0];
	include Frontend::locate( 'class-templates/spotlight.php' );
}
