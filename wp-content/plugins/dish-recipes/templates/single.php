<?php
/**
 * Single recipe template.
 *
 * Loaded by TemplateLoader for is_singular('dish_recipe').
 * Theme override: {theme}/dish-recipes/single.php
 *
 * Available via the global WP loop:
 *   get_the_ID(), get_the_title(), get_the_content(), etc.
 *
 * @package Dish\Recipes
 */

declare( strict_types=1 );

use Dish\Recipes\Data\RecipeRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$recipe_id   = get_the_ID();
	$prep_time   = (int) get_post_meta( $recipe_id, 'dish_recipe_prep_time',  true );
	$cook_time   = (int) get_post_meta( $recipe_id, 'dish_recipe_cook_time',  true );
	$total_time  = (int) get_post_meta( $recipe_id, 'dish_recipe_total_time', true );
	$yield       = get_post_meta( $recipe_id, 'dish_recipe_yield',      true );
	$difficulty  = get_post_meta( $recipe_id, 'dish_recipe_difficulty', true );
	$cuisine     = get_post_meta( $recipe_id, 'dish_recipe_cuisine',    true );
	$course      = get_post_meta( $recipe_id, 'dish_recipe_course',     true );
	$notes       = get_post_meta( $recipe_id, 'dish_recipe_notes',      true );
	$pdf_id      = (int) get_post_meta( $recipe_id, 'dish_recipe_pdf_id', true );
	$ingredients = RecipeRepository::get_ingredients( $recipe_id );
	$method      = RecipeRepository::get_method( $recipe_id );

	$dietary_raw = get_post_meta( $recipe_id, 'dish_recipe_dietary_flags', true );
	$dietary     = $dietary_raw ? (array) json_decode( $dietary_raw, true ) : [];

	$computed_total = $total_time ?: ( $prep_time + $cook_time );

	$difficulty_labels = [ 'easy' => 'Easy', 'medium' => 'Medium', 'advanced' => 'Advanced' ];
	?>

	<article id="recipe-<?php echo (int) $recipe_id; ?>" <?php post_class( 'recipe-single' ); ?>>

		<?php /* ----------------------------------------------------------------
		 * Hero
		 * -------------------------------------------------------------- */ ?>
		<header class="recipe-single__header">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="recipe-single__hero">
					<?php the_post_thumbnail( 'large', [ 'class' => 'recipe-single__hero-img', 'alt' => get_the_title() ] ); ?>
				</div>
			<?php endif; ?>

			<div class="recipe-single__intro">
				<h1 class="recipe-single__title"><?php the_title(); ?></h1>
				<?php if ( has_excerpt() ) : ?>
					<div class="recipe-single__excerpt"><?php the_excerpt(); ?></div>
				<?php endif; ?>
			</div>
		</header>

		<?php /* ----------------------------------------------------------------
		 * Meta strip
		 * -------------------------------------------------------------- */ ?>
		<div class="recipe-single__meta-strip">
			<?php if ( $prep_time ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Prep', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value"><?php echo (int) $prep_time; ?> <?php esc_html_e( 'min', 'dish-recipes' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $cook_time ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Cook', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value"><?php echo (int) $cook_time; ?> <?php esc_html_e( 'min', 'dish-recipes' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $computed_total ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Total', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value"><?php echo (int) $computed_total; ?> <?php esc_html_e( 'min', 'dish-recipes' ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $yield ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Yield', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value"><?php echo esc_html( $yield ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( $difficulty && isset( $difficulty_labels[ $difficulty ] ) ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Difficulty', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value recipe-difficulty--<?php echo esc_attr( $difficulty ); ?>">
						<?php echo esc_html( $difficulty_labels[ $difficulty ] ); ?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( $cuisine ) : ?>
				<div class="recipe-meta-item">
					<span class="recipe-meta-item__label"><?php esc_html_e( 'Cuisine', 'dish-recipes' ); ?></span>
					<span class="recipe-meta-item__value"><?php echo esc_html( $cuisine ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<?php /* ----------------------------------------------------------------
		 * Dietary flags
		 * -------------------------------------------------------------- */ ?>
		<?php if ( ! empty( $dietary ) ) : ?>
			<div class="recipe-single__dietary">
				<?php foreach ( $dietary as $flag ) : ?>
					<span class="recipe-dietary-badge recipe-dietary-badge--<?php echo esc_attr( $flag ); ?>">
						<?php echo esc_html( ucwords( str_replace( '-', ' ', $flag ) ) ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php /* ----------------------------------------------------------------
		 * Post content (intro / description from the editor)
		 * -------------------------------------------------------------- */ ?>
		<?php
		$content = get_the_content();
		if ( $content ) :
		?>
			<div class="recipe-single__content">
				<?php the_content(); ?>
			</div>
		<?php endif; ?>

		<?php /* ----------------------------------------------------------------
		 * Ingredients
		 * -------------------------------------------------------------- */ ?>
		<?php if ( ! empty( $ingredients ) ) : ?>
			<section class="recipe-single__ingredients">
				<h2><?php esc_html_e( 'Ingredients', 'dish-recipes' ); ?></h2>

				<?php foreach ( $ingredients as $section ) : ?>
					<div class="recipe-ingredient-section">
						<?php if ( ! empty( $section['heading'] ) ) : ?>
							<h3 class="recipe-section-heading"><?php echo esc_html( $section['heading'] ); ?></h3>
						<?php endif; ?>

						<?php if ( ! empty( $section['items'] ) ) : ?>
							<ul class="recipe-ingredient-list">
								<?php foreach ( $section['items'] as $ing ) :
									$qty  = trim( $ing['qty']  ?? '' );
									$unit = trim( $ing['unit'] ?? '' );
									$item = trim( $ing['item'] ?? '' );
									$note = trim( $ing['note'] ?? '' );

									if ( ! $item ) continue;
									?>
									<li class="recipe-ingredient">
										<span class="recipe-ingredient__qty-unit"><?php echo esc_html( trim( "{$qty} {$unit}" ) ); ?></span>
										<span class="recipe-ingredient__item"><?php echo esc_html( $item ); ?></span>
										<?php if ( $note ) : ?>
											<span class="recipe-ingredient__note"><?php echo esc_html( $note ); ?></span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php /* ----------------------------------------------------------------
		 * Method
		 * -------------------------------------------------------------- */ ?>
		<?php if ( ! empty( $method ) ) : ?>
			<section class="recipe-single__method">
				<h2><?php esc_html_e( 'Method', 'dish-recipes' ); ?></h2>

				<?php foreach ( $method as $section ) : ?>
					<div class="recipe-method-section">
						<?php if ( ! empty( $section['heading'] ) ) : ?>
							<h3 class="recipe-section-heading"><?php echo esc_html( $section['heading'] ); ?></h3>
						<?php endif; ?>

						<?php if ( ! empty( $section['steps'] ) ) : ?>
							<ol class="recipe-method-steps">
								<?php foreach ( $section['steps'] as $step ) : ?>
									<?php if ( ! empty( $step['text'] ) ) : ?>
										<li class="recipe-method-step"><?php echo esc_html( $step['text'] ); ?></li>
									<?php endif; ?>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>

		<?php /* ----------------------------------------------------------------
		 * Notes / Tips
		 * -------------------------------------------------------------- */ ?>
		<?php if ( $notes ) : ?>
			<section class="recipe-single__notes">
				<h2><?php esc_html_e( 'Notes', 'dish-recipes' ); ?></h2>
				<div class="recipe-notes__content"><?php echo wp_kses_post( wpautop( $notes ) ); ?></div>
			</section>
		<?php endif; ?>

		<?php /* ----------------------------------------------------------------
		 * Related class templates (if dish-events active)
		 * -------------------------------------------------------------- */ ?>
		<?php
		if ( defined( 'DISH_EVENTS_VERSION' ) ) :
			$template_ids = RecipeRepository::get_template_ids( $recipe_id );
			if ( ! empty( $template_ids ) ) :
				$class_templates = get_posts( [
					'post_type'      => 'dish_class_template',
					'post_status'    => 'publish',
					'post__in'       => $template_ids,
					'posts_per_page' => count( $template_ids ),
					'orderby'        => 'post__in',
				] );
				?>
				<section class="recipe-single__related-classes">
					<h2><?php esc_html_e( 'This Recipe Is Featured In', 'dish-recipes' ); ?></h2>
					<ul class="recipe-related-classes__list">
						<?php foreach ( $class_templates as $tmpl ) : ?>
							<li>
								<a href="<?php echo esc_url( get_permalink( $tmpl->ID ) ); ?>">
									<?php echo esc_html( $tmpl->post_title ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
				<?php
			endif;
		endif;
		?>

		<?php /* ----------------------------------------------------------------
		 * Legacy PDF download
		 * -------------------------------------------------------------- */ ?>
		<?php if ( $pdf_id ) :
			$pdf_url = wp_get_attachment_url( $pdf_id );
			if ( $pdf_url ) :
				?>
				<div class="recipe-single__pdf">
					<a href="<?php echo esc_url( $pdf_url ); ?>" class="button button--outline" target="_blank" rel="noopener">
						<?php esc_html_e( 'Download Recipe PDF', 'dish-recipes' ); ?>
					</a>
				</div>
			<?php
			endif;
		endif;
		?>

	</article>

<?php endwhile; ?>

<?php get_footer(); ?>
