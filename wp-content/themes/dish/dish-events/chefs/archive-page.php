<?php
/**
 * Template: dish_chef archive page — "Meet the Team".
 *
 * Served when WordPress routes to the native dish_chef post type archive
 * (e.g. /chef/). Wraps the theme header and footer around a full chef grid
 * queried from the main WP loop.
 *
 * Theme override: {theme}/dish-events/chefs/archive-page.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\ChefRepository;

get_header();

$chefs = ChefRepository::query( [ 'exclude_team' => true ] );
$team  = ChefRepository::query( [ 'team_only'    => true ] );
?>

<main id="main-content" class="main--content fluid">

    <h2 class="page-heading">
        <?php esc_html_e( 'Meet the Team', 'dish-events' ); ?>
    </h2>
    <?php if ( get_the_archive_description() ) : ?>
        <div class="archive-description">
            <?php the_archive_description(); ?>
        </div>
    <?php endif; ?>


<?php if ( empty( $chefs ) && empty( $team ) ) : ?>

    <div class="">
        <p class="">
            <?php esc_html_e( 'No chefs to display at the moment. Check back soon!', 'dish-events' ); ?>
        </p>
    </div>

<?php else : ?>

<?php if ( ! empty( $chefs ) ) : ?>
    <section class="">
        <?php if ( ! empty( $team ) ) : ?>
            <h3 class="section-heading"><?php esc_html_e( 'The Chefs', 'dish-events' ); ?></h3>
        <?php endif; ?>
        <div class="grid-general grid--4col">
            <?php foreach ( $chefs as $chef ) : ?>
                <?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ( ! empty( $team ) ) : ?>
    <section class="">
        <h3 class="section-heading"><?php esc_html_e( 'The Team', 'dish-events' ); ?></h3>
        <div class="grid-general grid--4col">
            <?php foreach ( $team as $chef ) : ?>
                <?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php endif; ?>

</main>

<?php get_footer(); ?>
