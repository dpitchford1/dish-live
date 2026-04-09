<?php
/**
 * Sidebar: Chefs section.
 *
 * Used on Chef archive and single pages. Shows class formats, FAQs, and
 * upcoming classes.
 * Include from a page template via:
 *
 *   get_template_part( 'templates/sidebars/sidebar', 'chefs' );
 *
 * @package basecamp
 */

use Dish\Events\Data\ClassRepository;
use Dish\Events\Frontend\Frontend;
?>
<aside class="aside">
    <h3>Chefs Sidebar</h3>
    <?php /* ── Class Formats ────────────────────────────────────────────── */ ?>
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

    <?php /* ── FAQs ─────────────────────────────────────────────────────── */ ?>
    <?php
    $faqs = get_posts( [
        'post_type'      => 'faq',
        'post_status'    => 'publish',
        'posts_per_page' => 5,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ] );
    ?>
    <?php if ( ! empty( $faqs ) ) : ?>
    <section class="aside-section aside-faqs">
        <h3 class="aside-section__title"><?php esc_html_e( 'FAQs', 'basecamp' ); ?></h3>
        <?php foreach ( $faqs as $faq ) : ?>
            <details class="aside-faq">
                <summary><?php echo esc_html( $faq->post_title ); ?></summary>
                <div><?php echo wp_kses_post( $faq->post_content ); ?></div>
            </details>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <?php /* ── Upcoming Classes ─────────────────────────────────────────── */ ?>
    <?php $upcoming = ClassRepository::get_upcoming( 3 ); ?>
    <?php if ( ! empty( $upcoming ) ) : ?>
    <section class="aside-section aside-upcoming">
        <h3 class="aside-section__title"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h3>
        <div class="grid-general grid--1col">
            <?php foreach ( $upcoming as $class ) : ?>
                <?php include Frontend::locate( 'classes/card.php' ); ?>
            <?php endforeach; ?>
        </div>
        <p>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_class' ) ); ?>" class="button">
                <?php esc_html_e( 'All Classes', 'dish-events' ); ?>
            </a>
        </p>
    </section>
    <?php endif; ?>

</aside>
