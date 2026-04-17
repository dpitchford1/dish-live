<?php
/**
 * Sidebar: Styleguide section.
 *
 * Used on the Styleguide page. Shows class formats, FAQs, and
 * upcoming classes.
 * Include from a page template via:
 *
 *   get_template_part( 'templates/sidebars/sidebar', 'styleguide' );
 *
 * @package basecamp
 */

use Dish\Events\Data\ClassRepository;
use Dish\Events\Frontend\Frontend;
?>
<aside class="aside">
    <h2 class="section-subtitle">More Dish</h2>
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
        <h3 class="card-title"><?php esc_html_e( 'FAQs', 'basecamp' ); ?></h3>
        <?php foreach ( $faqs as $faq ) : ?>
            <details class="aside-faq">
                <summary><?php echo esc_html( $faq->post_title ); ?></summary>
                <div><?php echo wp_kses_post( $faq->post_content ); ?></div>
            </details>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

</aside>
