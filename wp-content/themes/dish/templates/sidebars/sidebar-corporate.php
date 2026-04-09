<?php
/**
 * Sidebar: About section.
 *
 * Used on About-related pages. Shows a compact chefs grid and class formats.
 * Include from a page template via:
 *
 *   get_template_part( 'templates/sidebars/sidebar', 'about' );
 *
 * @package basecamp
 */

use Dish\Events\Data\ChefRepository;
use Dish\Events\Frontend\Frontend;
?>
<aside class="aside">
    <h3>Corporate Sidebar</h3>
    <?php /* ── Chefs ───────────────────────────────────────────────────── */ ?>
    <?php $chefs = ChefRepository::query( [ 'exclude_team' => true, 'limit' => 4 ] ); ?>
    <?php if ( ! empty( $chefs ) ) : ?>
    <section class="aside-section aside-chefs">
        <h3 class="aside-section__title"><?php esc_html_e( 'Meet the Chefs', 'dish-events' ); ?></h3>
        <div class="grid-general grid--2col">
            <?php foreach ( $chefs as $chef ) : ?>
                <?php include Frontend::locate( 'chefs/card.php' ); ?>
            <?php endforeach; ?>
        </div>
        <p>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_chef' ) ); ?>" class="button">
                <?php esc_html_e( 'All Chefs', 'dish-events' ); ?>
            </a>
        </p>
    </section>
    <?php endif; ?>

    <?php /* ── Formats ─────────────────────────────────────────────────── */ ?>
    <?php
    $formats = get_posts( [
        'post_type'      => 'dish_format',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] );
    ?>
    <?php if ( ! empty( $formats ) ) : ?>
    <section class="aside-section aside-formats">
        <h3 class="aside-section__title"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></h3>
        <div class="grid-general grid--2col">
            <?php foreach ( $formats as $format ) : ?>
                <?php include Frontend::locate( 'formats/card.php' ); ?>
            <?php endforeach; ?>
        </div>
        <p>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button">
                <?php esc_html_e( 'All Formats', 'dish-events' ); ?>
            </a>
        </p>
    </section>
    <?php endif; ?>

</aside>
