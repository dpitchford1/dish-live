<?php
/**
 * Recipe archive template.
 *
 * Used for:
 *   - is_post_type_archive('dish_recipe')   → /recipes/
 *   - is_tax('dish_recipe_category')        → /recipes/mains/ etc.
 *
 * Loaded by TemplateLoader. Theme override: {theme}/dish-recipes/archive.php
 *
 * When called from [dish_recipes] shortcode, $recipes and $loader are
 * passed via extract() by TemplateLoader::load_template().
 * When loaded as the WP archive template, the standard loop is used.
 *
 * @package Dish\Recipes
 *
 * @var \WP_Post[]             $recipes  (set when called from shortcode only)
 * @var int                    $columns  (set when called from shortcode only)
 * @var \Dish\Recipes\Frontend\TemplateLoader $loader
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// When called as a WP archive template (not shortcode), collect posts from loop.
$is_shortcode = isset( $recipes );

if ( ! $is_shortcode ) {
	get_header();
	$columns = 3;
	$recipes = [];
	while ( have_posts() ) {
		the_post();
		$recipes[] = get_post();
	}
	$loader = new \Dish\Recipes\Frontend\TemplateLoader();
}
?>
<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php $hero_image_id = dish_recipes_get_hero_image_id(); ?>
<?php if ( $hero_image_id ) : ?>
<section class="global--hero">
    <?php Basecamp_Frontend::picture( $hero_image_id, [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero--img size-basecamp-img-xl',
    ] ); ?>
    <div class="hero--wrapper">
        <div class="hero--text-block">
            <div class="hero--cta">
            <div class="hero--content">
                <h1 class="hero--heading"><?php echo is_tax() ? esc_html( single_term_title( '', false ) ) : esc_html__( 'Recipes', 'dish-recipes' ); ?></h1>
            </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ( ! $is_shortcode ) : ?>
    <?php /* Category filter nav */ ?>
    <?php
    $categories = get_terms( [
        'taxonomy'   => 'dish_recipe_category',
        'hide_empty' => true,
        'orderby'    => 'name',
    ] );
    if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) :
        $current_term = is_tax() ? get_queried_object() : null;
        ?>

<nav class="subnav content--utility fluid-content" aria-label="<?php esc_attr_e( 'Filter recipes by category', 'dish-recipes' ); ?>">
    <ul class="is--flex-list">
        <li><span class="filter-label"><?php esc_html_e( 'Filter by category', 'dish-recipes' ); ?></span></li>
        <li>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_recipe' ) ); ?>"
                class="recipe-filter-btn<?php echo ! $current_term ? ' menu--selected' : ''; ?>">
                <?php esc_html_e( 'All', 'dish-recipes' ); ?>
            </a>
        </li>
        <?php foreach ( $categories as $cat ) : ?>
            <li>
                <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>"
                    class="recipe-filter-btn<?php echo ( $current_term && $current_term->term_id === $cat->term_id ) ? ' menu--selected' : ''; ?>">
                    <?php echo esc_html( $cat->name ); ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

    <?php endif; ?>
<?php endif; ?>

<?php /* ── Main Content ─────────────────────────────────────────── */ ?>
<main id="main-content" class="main--content fluid-content event--region">

<div class="entry--content recipe-archive">
    <header class="recipe-archive__header">
        <?php if ( is_tax() ) : ?>
            <h1 class="recipe-archive__title"><?php single_term_title(); ?></h1>
        <?php else : ?>
            <h1 class="recipe-archive__title"><?php esc_html_e( 'Recipes', 'dish-recipes' ); ?></h1>
        <?php endif; ?>
    </header>

	<?php if ( ! empty( $recipes ) ) : ?>
		<div class="recipe-archive__grid recipe-archive__grid--cols-<?php echo (int) $columns; ?>">
			<?php foreach ( $recipes as $recipe ) :
				$loader->load_template( 'card.php', [
					'recipe' => $recipe,
					'loader' => $loader,
				] );
			endforeach; ?>
		</div>
	<?php else : ?>
		<p class="recipe-archive__empty"><?php esc_html_e( 'No recipes found.', 'dish-recipes' ); ?></p>
	<?php endif; ?>

	<?php if ( ! $is_shortcode ) : ?>
		<?php the_posts_pagination(); ?>
		
	<?php endif; ?>

</div><!-- .recipe-archive -->

</main>
<?php get_footer(); ?>