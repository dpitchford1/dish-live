<?php
/**
 * Template: dish_class_template single page — Private Events.
 *
 * Used exclusively when the assigned dish_format has dish_format_is_private = 1.
 * Completely separate structure from class-templates/single.php — designed for
 * bespoke / enquiry-driven events rather than the standard bookable class page.
 *
 * Theme override: {theme}/dish-events/class-templates/single-private.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Admin\Settings;

get_header();

while ( have_posts() ) :
	the_post();

	$template_id     = get_the_ID();
	$format_id       = (int) get_post_meta( $template_id, 'dish_format_id', true );
	$format          = $format_id ? get_post( $format_id ) : null;
	$format_color    = $format ? ( (string) get_post_meta( $format_id, 'dish_format_color', true ) ?: '#c0392b' ) : '';
	$format_title    = $format ? $format->post_title : '';

	// Enquiry page URL.
	$enquiry_page_id  = (int) Settings::get( 'enquiry_page', 0 );
	$enquiry_url      = $enquiry_page_id
		? (string) get_permalink( $enquiry_page_id )
		: 'mailto:' . Settings::get( 'studio_email', (string) get_bloginfo( 'admin_email' ) );

	?>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<section class="global--hero">
<?php if ( has_post_thumbnail() ) : ?>
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
    'landscape_size' => 'basecamp-img-xl',
    'loading'        => 'eager',
    'fetchpriority'  => 'high',
    'img_class'      => 'hero--img size-basecamp-img-xl',
] ); ?>
<?php endif; ?>
    <div class="hero--wrapper">
        <div class="hero--text-block">
            <div class="hero--cta">
            <div class="hero--content">
                <h1 class="hero--heading"><?php the_title(); ?></h1>
            </div>
           </div>
        </div>
    </div>
</section>
<?php /* ── Breadcrumb ─────────────────────────────────────────── */ ?>
<div class="fluid-content breadcrumb"><?php dish_the_breadcrumb(); ?></div>

<?php /* ── Main Content ─────────────────────────────────────────── */ ?>
<div class="content--region has--aside fluid-content">

    <main id="main-content" class="main--content inner--content">
		<section id="post-<?php the_ID(); ?>" <?php post_class( 'private-event' ); ?>>

            <div class="private-event__hero-content">
                <?php if ( $format_title ) : ?>
                    <p class="private-event__format-label" style="color:<?php echo esc_attr( $format_color ); ?>">
                        <?php echo esc_html( $format_title ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( has_excerpt() ) : ?>
                    <p class="private-event__intro"><?php echo wp_kses_post( get_the_excerpt() ); ?></p>
                <?php endif; ?>
            </div>

        <?php // ── Body content ─────────────────────────────────────────── ?>
        <?php if ( get_the_content() ) : ?>
            <article class="private-event__body entry--content">
                <?php the_content(); ?>
            </article>
        <?php endif; ?>

            <?php // ── Enquiry CTA ───────────────────────────────────────────── ?>
            <p class="private-event__cta">
                <a href="<?php echo esc_url( $enquiry_url ); ?>" class="button button--secondary"><?php esc_html_e( 'Enquire About This Event', 'dish-events' ); ?></a>
            </p>

		</section>
	</main>
</div>
	<?php
endwhile;

get_footer();
