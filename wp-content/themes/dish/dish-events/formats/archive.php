<?php
/**
 * Template: dish_format archive — Class Formats listing.
 *
 * Served when WordPress routes to the dish_format post type archive
 * (e.g. /classes/). Renders a grid of all published formats.
 *
 * Theme override: {theme}/dish-events/formats/archive.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Frontend\Frontend;

get_header();

// Pull hero image and intro content from the page assigned in Settings → Pages.
$_formats_page_id = (int) \Dish\Events\Admin\Settings::get( 'formats_page' );
$_formats_page    = $_formats_page_id ? get_post( $_formats_page_id ) : null;

$_base_args = [
	'post_type'      => 'dish_format',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
];

$public_formats = get_posts( array_merge( $_base_args, [
	'meta_query' => [ [
		'key'     => 'dish_format_is_private',
		'compare' => 'NOT EXISTS',
	] ],
] ) );

$private_formats = get_posts( array_merge( $_base_args, [
	'meta_query' => [ [
		'key'   => 'dish_format_is_private',
		'value' => '1',
	] ],
] ) );
?>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php if ( $_formats_page && has_post_thumbnail( $_formats_page ) ) : ?>
<section class="global--hero">
<?php Basecamp_Frontend::picture( get_post_thumbnail_id( $_formats_page ), [
    'landscape_size' => 'basecamp-img-xl',
    'loading'        => 'eager',
    'fetchpriority'  => 'high',
    'img_class'      => 'hero--img size-basecamp-img-xl',
] ); ?>
    <div class="hero--wrapper">
        <div class="hero--text-block">
            <div class="hero--cta">
            <div class="hero--content">
                <h1 class="hero--heading"><?php echo $_formats_page ? esc_html( get_the_title( $_formats_page ) ) : esc_html__( 'Class Formats', 'dish-events' ); ?></h1>
            </div>
           </div>
        </div>
    </div>
</section>
<?php endif; ?>

<main id="main-content" class="main--content fluid-content event--region">

    <?php if ( ! $_formats_page || ! has_post_thumbnail( $_formats_page ) ) : ?>
        <!-- <h1 class="dish-archive-title"><?php echo $_formats_page ? esc_html( get_the_title( $_formats_page ) ) : esc_html__( 'Class Formats', 'dish-events' ); ?></h1> -->
    <?php endif; ?>

	<?php if ( $_formats_page ) : ?>
		<article class="entry--content entry--blurb">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', $_formats_page ) ) ); ?>
		</article>
	<?php endif; ?>

	<?php if ( empty( $public_formats ) && empty( $private_formats ) ) : ?>

		<div class="dish-container">
			<p class="dish-no-results"><?php esc_html_e( 'No formats to display at the moment. Check back soon!', 'dish-events' ); ?></p>
		</div>

	<?php else : ?>

		<?php if ( ! empty( $public_formats ) ) : ?>
        <section class="grid-general grid--3col formats--public">
            <?php foreach ( $public_formats as $format ) : ?>
                <?php include Frontend::locate( 'formats/card.php' ); ?>
            <?php endforeach; ?>
        </section>
		<?php endif; ?>

		<?php if ( ! empty( $private_formats ) ) : ?>
			<section class="content-region spotlight-wrapper fluid-content formats--private">
                <h2 class="section-heading text--centered"><?php esc_html_e( 'Corporate Events and Private Parties', 'dish-events' ); ?></h2>
        <?php foreach ( $private_formats as $format ) : ?>
            <?php include Frontend::locate( 'formats/card-private.php' ); ?>
        <?php endforeach; ?>
			</section>
		<?php endif; ?>

	<?php endif; ?>

</main>

<?php get_footer(); ?>
