<?php
/**
 * Default sidebar dispatcher.
 *
 * Selects which sidebar partial to load based on the current context.
 * Add new conditions here as new sections are built out.
 *
 * Available partials (templates/sidebars/):
 *   sidebar-about.php  — chefs grid + formats
 *   sidebar-chefs.php  — formats + FAQs + upcoming classes
 *
 * To include directly from a page template instead of routing through here:
 *   get_template_part( 'templates/sidebars/sidebar', 'about' );
 */

if ( basecamp_is_page_or_child_of( 'about-dish' ) || is_page( [ 'recipes', 'class-menus', 'upcoming-menus', 'gift-cards' ] ) ) {
    get_template_part( 'templates/sidebars/sidebar', 'about' );
} if ( basecamp_is_page_or_child_of( 'contact-us' ) || is_page( [ 'studio-rental', 'prepared-foods' ] ) ) {
    get_template_part( 'templates/sidebars/sidebar', 'contact' );
} elseif ( is_post_type_archive( 'dish_format' ) || is_singular( 'dish_format' ) ) {
    get_template_part( 'templates/sidebars/sidebar', 'formats' );
} elseif ( is_post_type_archive( 'dish_chef' ) || is_singular( 'dish_chef' ) ) {
    get_template_part( 'templates/sidebars/sidebar', 'chefs' );
} elseif ( is_page( 'style-guide' ) ) {
    get_template_part( 'templates/sidebars/sidebar', 'styleguide' );
} else {
    // Default fallback — nothing rendered until a sidebar is assigned.
}
