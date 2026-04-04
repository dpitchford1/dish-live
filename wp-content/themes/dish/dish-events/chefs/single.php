<?php
/**
 * Template: dish_chef single page.
 *
 * Displays a chef's full profile — hero photo, name, role, bio, social links,
 * and a grid of upcoming classes they are assigned to teach.
 *
 * Theme override: {theme}/dish-events/chefs/single.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Helpers\DateHelper;

get_header();

while ( have_posts() ) :
	the_post();

	$chef_id   = get_the_ID();
	$role      = (string) get_post_meta( $chef_id, 'dish_chef_role',      true );
	$website   = (string) get_post_meta( $chef_id, 'dish_chef_website',   true );
	$instagram = (string) get_post_meta( $chef_id, 'dish_chef_instagram', true );
	$linkedin  = (string) get_post_meta( $chef_id, 'dish_chef_linkedin',  true );
	$tiktok    = (string) get_post_meta( $chef_id, 'dish_chef_tiktok',    true );

	// Gallery images.
	$raw_gallery   = get_post_meta( $chef_id, 'dish_chef_gallery_ids', true ) ?: '[]';
	$chef_gallery  = array_values( array_filter( array_map( 'absint', (array) json_decode( $raw_gallery, true ) ) ) );

	// Upcoming public classes taught by this chef.
	$class_ids = get_posts( [
		'post_type'      => 'dish_class',
		'post_status'    => 'publish',
		'posts_per_page' => 6,
		'fields'         => 'ids',
		'meta_query'     => [
			'relation' => 'AND',
			[
				'key'     => 'dish_start_datetime',
				'value'   => time(),
				'compare' => '>=',
				'type'    => 'NUMERIC',
			],
			// Exclude private class instances.
			[
				'relation' => 'OR',
				[ 'key' => 'dish_is_private', 'compare' => 'NOT EXISTS' ],
				[ 'key' => 'dish_is_private', 'value'   => '1', 'compare' => '!=' ],
			],
		],
	] );

	// Filter to classes where this chef is assigned.
	// NOTE: Do NOT use $post here — it would overwrite the global WP $post and
	// corrupt the_title() / get_the_ID() calls that follow in the template.
	$upcoming = [];
	foreach ( $class_ids as $cid ) {
		$ids = ClassRepository::get_chef_ids( (int) $cid );
		if ( in_array( $chef_id, $ids, true ) ) {
			$class_post = get_post( (int) $cid );
			if ( $class_post ) {
				$upcoming[] = $class_post;
			}
		}
	}
	?>

	<main id="main-content" class="main--content fluid">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'dish-chef' ); ?>>

            <?php if ( has_post_thumbnail() ) : ?>
                <div class="dish-chef-photo-wrap">
                    <?php echo wp_get_attachment_image( get_post_thumbnail_id(), 'portait-m', false, [ 'class' => 'dish-chef-photo', 'loading' => 'eager', 'sizes' => '160px' ] ); ?>
                </div>
            <?php endif; ?>

            <h2 class="dish-chef-title"><?php the_title(); ?></h2>

            <?php if ( $role ) : ?>
                <p class="dish-chef-role"><?php echo esc_html( $role ); ?></p>
            <?php endif; ?>

            <?php if ( $website || $instagram || $linkedin || $tiktok ) : ?>
                <nav class="dish-chef-social" aria-label="<?php esc_attr_e( 'Chef social links', 'dish-events' ); ?>">
                    <?php if ( $website ) : ?>
                        <a href="<?php echo esc_url( $website ); ?>" class="dish-chef-social__link" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Website', 'dish-events' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( $instagram ) : ?>
                        <a href="<?php echo esc_url( $instagram ); ?>" class="dish-chef-social__link dish-chef-social__link--instagram" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'Instagram', 'dish-events' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( $linkedin ) : ?>
                        <a href="<?php echo esc_url( $linkedin ); ?>" class="dish-chef-social__link dish-chef-social__link--linkedin" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'LinkedIn', 'dish-events' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( $tiktok ) : ?>
                        <a href="<?php echo esc_url( $tiktok ); ?>" class="dish-chef-social__link dish-chef-social__link--tiktok" target="_blank" rel="noopener noreferrer">
                            <?php esc_html_e( 'TikTok', 'dish-events' ); ?>
                        </a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>

			<?php if ( get_the_content() ) : ?>
				<div class="dish-chef-bios">
					<?php the_content(); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $chef_gallery ) ) : ?>
				<section class="dish-chef-gallery dish-container" aria-label="<?php esc_attr_e( 'Chef gallery', 'dish-events' ); ?>">
					<div class="dish-gallery-grid">
						<?php foreach ( $chef_gallery as $gid ) :
							$src = wp_get_attachment_image_src( $gid, 'basecamp-img-xl' );
							if ( ! $src ) continue;
							$alt = (string) get_post_meta( $gid, '_wp_attachment_image_alt', true );
						?>
							<figure class="dish-gallery-grid__item">
								<a href="<?php echo esc_url( $src[0] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo wp_get_attachment_image( $gid, 'basecamp-img-sm', false, [ 'alt' => $alt, 'loading' => 'lazy', 'sizes' => '(max-width: 600px) 100vw, 50vw' ] ); ?>
								</a>
							</figure>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( ! empty( $upcoming ) ) : ?>
				<section class="dish-chef-upcoming dish-container">
					<h3 class="dish-chef-upcoming__heading">
						<?php
						printf(
							/* translators: %s: chef's name */
							esc_html__( 'Upcoming Classes for %s', 'dish-events' ),
							'<span class="dish-chef-upcoming__name">' . esc_html( get_the_title() ) . '</span>'
						);
						?>
					</h3>
					<div class="dish-card-grid">
						<?php foreach ( $upcoming as $class ) : ?>
							<?php include \Dish\Events\Frontend\Frontend::locate( 'classes/card.php' ); ?>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

		</article>
	</main>

<?php endwhile; ?>

<?php get_footer(); ?>
