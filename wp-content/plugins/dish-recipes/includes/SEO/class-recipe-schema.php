<?php
/**
 * Recipe Schema output.
 *
 * Outputs a Schema.org Recipe JSON-LD block in <head> on single recipe pages.
 *
 * recipeInstructions format:
 *   - Multiple sections → HowToSection[] each containing HowToStep[]
 *   - Single section (no heading) → flat HowToStep[] (no wrapping section)
 *
 * recipeIngredient is always a flat string array per the spec.
 *
 * @package Dish\Recipes\SEO
 */

declare( strict_types=1 );

namespace Dish\Recipes\SEO;

use Dish\Recipes\Data\RecipeRepository;

/**
 * Class RecipeSchema
 */
final class RecipeSchema {

	/**
	 * Output the JSON-LD block.
	 * Hooked to wp_head — only runs on single dish_recipe pages.
	 */
	public function output(): void {
		if ( ! is_singular( 'dish_recipe' ) ) {
			return;
		}

		$post = get_queried_object();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$schema = $this->build( $post );

		if ( empty( $schema ) ) {
			return;
		}

		echo '<script type="application/ld+json">'
			. wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			. '</script>';
	}

	// -------------------------------------------------------------------------
	// Schema builder
	// -------------------------------------------------------------------------

	/**
	 * Build the Schema.org Recipe array for a given post.
	 *
	 * @param \WP_Post $post
	 * @return array<string, mixed>
	 */
	private function build( \WP_Post $post ): array {
		$id = $post->ID;

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Recipe',
			'name'     => get_the_title( $id ),
		];

		// Image — Google recommends supplying multiple aspect ratios (1x1, 4x3, 16x9)
		// for best rich result eligibility. We supply all available sizes and let
		// Google pick the best fit. Omit entirely if no featured image is set.
		if ( has_post_thumbnail( $id ) ) {
			$thumbnail_id = get_post_thumbnail_id( $id );
			$images       = [];

			foreach ( [ 'large', 'medium_large', 'medium', 'thumbnail' ] as $size ) {
				$url = wp_get_attachment_image_url( $thumbnail_id, $size );
				if ( $url && ! in_array( $url, $images, true ) ) {
					$images[] = $url;
				}
			}

			if ( ! empty( $images ) ) {
				$schema['image'] = $images;
			}
		}

		// Description from excerpt.
		$excerpt = get_the_excerpt( $post );
		if ( $excerpt ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}

		// Author — studio name.
		$schema['author'] = [
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		];

		// Published date.
		$schema['datePublished'] = get_the_date( 'Y-m-d', $id );

		// Times.
		$prep  = (int) get_post_meta( $id, 'dish_recipe_prep_time',  true );
		$cook  = (int) get_post_meta( $id, 'dish_recipe_cook_time',  true );
		$total = (int) get_post_meta( $id, 'dish_recipe_total_time', true );

		if ( $prep ) {
			$schema['prepTime'] = $this->to_iso_duration( $prep );
		}
		if ( $cook ) {
			$schema['cookTime'] = $this->to_iso_duration( $cook );
		}

		// Total time: use manual override if set, otherwise sum prep + cook.
		$computed_total = $total ?: ( $prep + $cook );
		if ( $computed_total ) {
			$schema['totalTime'] = $this->to_iso_duration( $computed_total );
		}

		// Yield.
		$yield = get_post_meta( $id, 'dish_recipe_yield', true );
		if ( $yield ) {
			$schema['recipeYield'] = $yield;
		}

		// Category from taxonomy.
		$terms = get_the_terms( $id, 'dish_recipe_category' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$schema['recipeCategory'] = $terms[0]->name;
		}

		// Cuisine + course from meta.
		$cuisine = get_post_meta( $id, 'dish_recipe_cuisine', true );
		if ( $cuisine ) {
			$schema['recipeCuisine'] = $cuisine;
		}

		// Keywords from tags if taxonomy exists and has terms.
		$tags = get_the_terms( $id, 'dish_recipe_tag' );
		if ( is_array( $tags ) && ! empty( $tags ) ) {
			$schema['keywords'] = implode( ', ', wp_list_pluck( $tags, 'name' ) );
		}

		// Ingredients — flat string array.
		$ingredients_flat = RecipeRepository::get_ingredients_flat( $id );
		if ( ! empty( $ingredients_flat ) ) {
			$schema['recipeIngredient'] = $ingredients_flat;
		}

		// Instructions — HowToSection or flat HowToStep.
		$instructions = $this->build_instructions( $id );
		if ( ! empty( $instructions ) ) {
			$schema['recipeInstructions'] = $instructions;
		}

		// Dietary flags → suitableForDiet.
		$suitable = $this->build_suitable_for_diet( $id );
		if ( ! empty( $suitable ) ) {
			$schema['suitableForDiet'] = count( $suitable ) === 1 ? $suitable[0] : $suitable;
		}

		return $schema;
	}

	// -------------------------------------------------------------------------
	// Instructions builder
	// -------------------------------------------------------------------------

	/**
	 * Build the recipeInstructions value.
	 *
	 * Multiple sections with headings → HowToSection[].
	 * Single section or no heading → flat HowToStep[].
	 *
	 * @param int $recipe_id
	 * @return array<int, mixed>
	 */
	private function build_instructions( int $recipe_id ): array {
		$sections = RecipeRepository::get_method( $recipe_id );

		if ( empty( $sections ) ) {
			return [];
		}

		$use_sections = count( $sections ) > 1
			|| ( ! empty( $sections[0]['heading'] ) );

		if ( $use_sections ) {
			$output = [];

			foreach ( $sections as $section ) {
				$step_items = $this->build_how_to_steps( $section['steps'] ?? [] );

				if ( empty( $step_items ) ) {
					continue;
				}

				$section_schema = [
					'@type'           => 'HowToSection',
					'itemListElement' => $step_items,
				];

				if ( ! empty( $section['heading'] ) ) {
					$section_schema['name'] = $section['heading'];
				}

				$output[] = $section_schema;
			}

			return $output;
		}

		// Single section — flat HowToStep array.
		return $this->build_how_to_steps( $sections[0]['steps'] ?? [] );
	}

	/**
	 * Build an array of HowToStep objects from a steps array.
	 *
	 * @param array<int, array{step: int, text: string}> $steps
	 * @return array<int, array<string, mixed>>
	 */
	private function build_how_to_steps( array $steps ): array {
		$output   = [];
		$position = 1;

		foreach ( $steps as $step ) {
			$text = trim( $step['text'] ?? '' );
			if ( '' === $text ) {
				continue;
			}
			$output[] = [
				'@type'    => 'HowToStep',
				'position' => $position++,
				'text'     => $text,
			];
		}

		return $output;
	}

	// -------------------------------------------------------------------------
	// Dietary flags
	// -------------------------------------------------------------------------

	/**
	 * Map dietary flag keys to Schema.org DietRestriction URIs.
	 *
	 * @param int $recipe_id
	 * @return string[]
	 */
	private function build_suitable_for_diet( int $recipe_id ): array {
		$map = [
			'gluten-free' => 'https://schema.org/GlutenFreeDiet',
			'vegan'       => 'https://schema.org/VeganDiet',
			'vegetarian'  => 'https://schema.org/VegetarianDiet',
			'halal'       => 'https://schema.org/HalalDiet',
		];

		$raw  = get_post_meta( $recipe_id, 'dish_recipe_dietary_flags', true );
		$flags = $raw ? (array) json_decode( $raw, true ) : [];

		$suitable = [];
		foreach ( $flags as $flag ) {
			if ( isset( $map[ $flag ] ) ) {
				$suitable[] = $map[ $flag ];
			}
		}

		return $suitable;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Convert minutes to an ISO 8601 duration string (PTxHxM).
	 *
	 * @param int $minutes
	 * @return string e.g. "PT45M", "PT1H30M"
	 */
	private function to_iso_duration( int $minutes ): string {
		if ( $minutes <= 0 ) {
			return '';
		}

		$hours = intdiv( $minutes, 60 );
		$mins  = $minutes % 60;

		$duration = 'PT';
		if ( $hours ) {
			$duration .= $hours . 'H';
		}
		if ( $mins ) {
			$duration .= $mins . 'M';
		}

		return $duration;
	}
}
