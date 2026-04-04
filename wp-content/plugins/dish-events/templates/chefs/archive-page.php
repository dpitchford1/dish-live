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

<main id="primary" class="site-main dish-chefs-page">

	<header class="dish-archive-header dish-container">
		<h1 class="dish-archive-title">
			<?php esc_html_e( 'Meet the Team', 'dish-events' ); ?>
		</h1>
		<?php if ( get_the_archive_description() ) : ?>
			<div class="dish-archive-description">
				<?php the_archive_description(); ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( empty( $chefs ) && empty( $team ) ) : ?>

		<div class="dish-container">
			<p class="dish-no-results">
				<?php esc_html_e( 'No chefs to display at the moment. Check back soon!', 'dish-events' ); ?>
			</p>
		</div>

	<?php else : ?>

		<?php if ( ! empty( $chefs ) ) : ?>
			<section class="dish-chefs-section dish-container">
				<?php if ( ! empty( $team ) ) : ?>
					<h2 class="dish-section-title"><?php esc_html_e( 'The Chefs', 'dish-events' ); ?></h2>
				<?php endif; ?>
				<div class="dish-card-grid dish-chef-grid">
					<?php foreach ( $chefs as $chef ) : ?>
						<?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php if ( ! empty( $team ) ) : ?>
			<section class="dish-chefs-section dish-team-section dish-container">
				<h2 class="dish-section-title"><?php esc_html_e( 'The Team', 'dish-events' ); ?></h2>
				<div class="dish-card-grid dish-chef-grid">
					<?php foreach ( $team as $chef ) : ?>
						<?php include \Dish\Events\Frontend\Frontend::locate( 'chefs/card.php' ); ?>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

	<?php endif; ?>

</main>

<?php get_footer(); ?>
