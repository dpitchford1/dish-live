<?php
/**
 * Template: dish_format single page.
 *
 * Displays the format's editorial content (title, featured image, body)
 * followed by:
 *   1. Upcoming scheduled classes for this format (when any exist)
 *   2. All published class templates in this format — always present
 *
 * Theme override: {theme}/dish-events/formats/single.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$format_id    = get_the_ID();
	$format_title = get_the_title();

	// All published templates for this format.
	$templates = get_posts( [
		'post_type'      => 'dish_class_template',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => [ [
			'key'   => 'dish_format_id',
			'value' => $format_id,
			'type'  => 'NUMERIC',
		] ],
	] );

	// Partition into featured and standard templates.
	$featured_templates = array_values( array_filter( $templates, fn( $t ) => (bool) get_post_meta( $t->ID, 'dish_is_featured', true ) ) );
	$standard_templates = array_values( array_filter( $templates, fn( $t ) => ! (bool) get_post_meta( $t->ID, 'dish_is_featured', true ) ) );

	?>
    
<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php if ( has_post_thumbnail() ) : ?>
<div class="hero">
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
    ] ); ?>
</div>
<?php endif; ?>
<?php /* ── Breadcrumb ─────────────────────────────────────────── */ ?>
<div class="fluid-content breadcrumb"><?php dish_the_breadcrumb(); ?></div>

<?php /* ── Main Content ─────────────────────────────────────────── */ ?>
<main id="main-content" class="main--content fluid-content">
    <h1 class="dish-format-title"><?php echo esc_html( sprintf( __( 'Class Format: %s', 'dish-events' ), get_the_title() ) ); ?></h1>

    <?php /* ── If featured exists ───────── */ ?>
    <?php if ( ! empty( $featured_templates ) ) : ?>

        <?php /* ── load 2 column with Content and featured card ───────── */ ?>
        <section class="grid-general grid--2col">

        <?php if ( get_the_content() ) : ?>
            <article class="">
                <?php the_content(); ?>
            </article>
        <?php endif; ?>
        
        <div class="dish-template-listing dish-template-listing--featured">
            <h3>Featured Class Type</h3>
            <?php $suppress_format_pill = true; ?>
            <?php foreach ( $featured_templates as $template ) : ?>
                <?php include locate_template( 'dish-events/class-templates/card.php' ); ?>
            <?php endforeach; ?>
        </div>

        </section>
    <?php /* ── No featured — show image alongside content ───────── */ ?>
    <?php else : ?>
        <section class="grid-general grid--2col">
        <?php if ( get_the_content() ) : ?>
            <article class="">
                <?php the_content(); ?>
            </article>
        <?php endif; ?>

        <div class="dish-format-img">
            <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
                'landscape_size' => 'basecamp-img-sm',
                'loading'        => 'lazy',
            ] ); ?>
        </div>
        </section>
    <?php endif; ?>

    <div id="post-<?php the_ID(); ?>" class="content--region">

        <?php dish_the_upcoming_classes( [
            'template_ids'         => ! empty( $templates ) ? wp_list_pluck( $templates, 'ID' ) : [],
            'dedupe_by_template'   => true,
            /* translators: %s: format name e.g. "Hands On" */
            'heading'              => sprintf( __( 'Upcoming %s Classes', 'dish-events' ), $format_title ),
            'suppress_format_pill' => true,
        ] ); ?>

        <?php if ( ! empty( $standard_templates ) ) : ?>
            <section class="dish-template-listings dish-template-listing--standards">
                <h2 class="dish-template-listing__heading">
                    <?php echo esc_html( sprintf( __( 'Our %s Classes', 'dish-events' ), $format_title ) ); /* translators: %s: format name e.g. "Hands On" */?>
                </h2>
                <div class="grid-general grid--3col">
                    <?php $suppress_format_pill = true; ?>
                    <?php foreach ( $standard_templates as $template ) : ?>
                        <?php include locate_template( 'dish-events/class-templates/card.php' ); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php elseif ( empty( $featured_templates ) ) : ?>
            <p class="dish-no-classes"><?php esc_html_e( 'No classes currently available in this format.', 'dish-events' ); ?></p>
        <?php endif; ?>

    </div>
</main>

<?php endwhile; ?>

<?php get_footer(); ?>
