<?php
/**
 * Recipe Meta Box.
 *
 * Tabbed meta box on the dish_recipe edit screen.
 *
 * Tabs:
 *   1. Overview      — yield, times, difficulty, cuisine, course
 *   2. Ingredients   — sectioned repeater (sections → items)
 *   3. Method        — sectioned repeater (sections → steps)
 *   4. Dietary       — checkbox grid
 *   5. Related       — class template IDs multi-select
 *   6. PDF           — legacy PDF attachment upload
 *
 * @package Dish\Recipes\Admin
 */

declare( strict_types=1 );

namespace Dish\Recipes\Admin;

/**
 * Class RecipeMetaBox
 */
final class RecipeMetaBox {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * Unit dropdown options, grouped for <optgroup> rendering.
	 * Key = stored value, value = display label.
	 * Empty string key = "no unit" (whole items: eggs, vanilla beans, etc.)
	 *
	 * @var array<string, array<string, string>>
	 */
	public const UNIT_OPTIONS = [
		'Weight'        => [
			'g'   => 'g',
			'kg'  => 'kg',
			'oz'  => 'oz',
			'lb'  => 'lb',
		],
		'Volume'        => [
			'ml'    => 'ml',
			'l'     => 'L',
			'tsp'   => 'tsp',
			'tbsp'  => 'tbsp',
			'cup'   => 'cup',
			'fl_oz' => 'fl oz',
		],
		'Length'        => [
			'cm'    => 'cm',
			'mm'    => 'mm',
			'inch'  => 'inch',
		],
		'Temperature'   => [
			'c' => '°C',
			'f' => '°F',
		],
		'Count / Other' => [
			''        => '(none)',
			'pinch'   => 'pinch',
			'handful' => 'handful',
			'bunch'   => 'bunch',
			'slice'   => 'slice',
			'sheet'   => 'sheet',
			'sprig'   => 'sprig',
			'clove'   => 'clove',
		],
	];

	/**
	 * Difficulty options.
	 *
	 * @var array<string, string>
	 */
	private const DIFFICULTY_OPTIONS = [
		''         => '— Select —',
		'easy'     => 'Easy',
		'medium'   => 'Medium',
		'advanced' => 'Advanced',
	];

	/**
	 * Dietary flag keys and labels.
	 * Mirrors dish-events MenuMetaBox::DIETARY_FLAGS — kept in sync manually.
	 *
	 * @var array<string, string>
	 */
	private const DIETARY_FLAGS = [
		'gluten-free'  => 'Gluten Free',
		'dairy-free'   => 'Dairy Free',
		'vegetarian'   => 'Vegetarian',
		'vegan'        => 'Vegan',
		'nut-free'     => 'Nut Free',
		'egg-free'     => 'Egg Free',
		'shellfish'    => 'Contains Shellfish',
		'halal'        => 'Halal',
	];

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_action( 'add_meta_boxes',        $this, 'add_meta_box' );
		$loader->add_action( 'save_post_dish_recipe', $this, 'save', 10, 1 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
	}

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box on the dish_recipe edit screen.
	 */
	public function add_meta_box(): void {
		add_meta_box(
			'dish_recipe_details',
			__( 'Recipe Details', 'dish-recipes' ),
			[ $this, 'render' ],
			'dish_recipe',
			'normal',
			'high'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the tabbed meta box.
	 *
	 * @param \WP_Post $post
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'dish_recipe_save_meta', 'dish_recipe_nonce' );

		$yield       = get_post_meta( $post->ID, 'dish_recipe_yield',       true );
		$prep_time   = get_post_meta( $post->ID, 'dish_recipe_prep_time',   true );
		$cook_time   = get_post_meta( $post->ID, 'dish_recipe_cook_time',   true );
		$total_time  = get_post_meta( $post->ID, 'dish_recipe_total_time',  true );
		$difficulty  = get_post_meta( $post->ID, 'dish_recipe_difficulty',  true );
		$cuisine     = get_post_meta( $post->ID, 'dish_recipe_cuisine',     true );
		$course      = get_post_meta( $post->ID, 'dish_recipe_course',      true );
		$notes       = get_post_meta( $post->ID, 'dish_recipe_notes',       true );
		$is_spotlight = (bool) get_post_meta( $post->ID, 'dish_recipe_is_spotlight', true );
		$pdf_id      = (int) get_post_meta( $post->ID, 'dish_recipe_pdf_id', true );

		$dietary_raw  = get_post_meta( $post->ID, 'dish_recipe_dietary_flags', true );
		$dietary_set  = $dietary_raw ? (array) json_decode( $dietary_raw, true ) : [];

		$tmpl_raw     = get_post_meta( $post->ID, 'dish_recipe_template_ids', true );
		$tmpl_ids     = $tmpl_raw ? (array) json_decode( $tmpl_raw, true ) : [];

		$ingredients_raw = get_post_meta( $post->ID, 'dish_recipe_ingredients', true );
		$ingredients     = $ingredients_raw ? json_decode( $ingredients_raw, true ) : [];
		if ( ! is_array( $ingredients ) || empty( $ingredients ) ) {
			$ingredients = [ [ 'heading' => '', 'items' => [ [ 'qty' => '', 'unit' => '', 'item' => '', 'note' => '' ] ] ] ];
		}

		$method_raw = get_post_meta( $post->ID, 'dish_recipe_method', true );
		$method     = $method_raw ? json_decode( $method_raw, true ) : [];
		if ( ! is_array( $method ) || empty( $method ) ) {
			$method = [ [ 'heading' => '', 'steps' => [ [ 'step' => 1, 'text' => '' ] ] ] ];
		}

		// Retrieve class templates for the Related tab (if dish-events is active).
		$class_templates = [];
		if ( defined( 'DISH_EVENTS_VERSION' ) ) {
			$class_templates = get_posts( [
				'post_type'      => 'dish_class_template',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			] );
		}

		$pdf_filename = $pdf_id ? basename( get_attached_file( $pdf_id ) ) : '';
		?>
		<div class="dish-recipe-meta-box">

			<nav class="dish-meta-tabs">
				<button type="button" class="dish-meta-tab-btn is-active" data-tab="overview"><?php esc_html_e( 'Overview',    'dish-recipes' ); ?></button>
				<button type="button" class="dish-meta-tab-btn"           data-tab="ingredients"><?php esc_html_e( 'Ingredients', 'dish-recipes' ); ?></button>
				<button type="button" class="dish-meta-tab-btn"           data-tab="method"><?php esc_html_e( 'Method',      'dish-recipes' ); ?></button>
				<button type="button" class="dish-meta-tab-btn"           data-tab="dietary"><?php esc_html_e( 'Dietary',     'dish-recipes' ); ?></button>
				<button type="button" class="dish-meta-tab-btn"           data-tab="related"><?php esc_html_e( 'Related',     'dish-recipes' ); ?></button>
				<button type="button" class="dish-meta-tab-btn"           data-tab="pdf"><?php esc_html_e( 'PDF',         'dish-recipes' ); ?></button>
			</nav>

			<?php /* ----------------------------------------------------------------
			 * Tab 1 — Overview
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel is-active" id="dish-tab-overview">

				<div class="dish-meta-row">
					<label for="dish_recipe_yield"><?php esc_html_e( 'Yield / Serves', 'dish-recipes' ); ?></label>
					<input type="text" id="dish_recipe_yield" name="dish_recipe_yield"
						value="<?php echo esc_attr( $yield ); ?>"
						placeholder="e.g. Serves 4, Makes 24 pieces" class="regular-text">
				</div>

				<div class="dish-meta-row dish-meta-row--inline">
					<div>
						<label for="dish_recipe_prep_time"><?php esc_html_e( 'Prep Time (mins)', 'dish-recipes' ); ?></label>
						<input type="number" id="dish_recipe_prep_time" name="dish_recipe_prep_time"
							value="<?php echo esc_attr( $prep_time ); ?>" min="0" class="small-text">
					</div>
					<div>
						<label for="dish_recipe_cook_time"><?php esc_html_e( 'Cook Time (mins)', 'dish-recipes' ); ?></label>
						<input type="number" id="dish_recipe_cook_time" name="dish_recipe_cook_time"
							value="<?php echo esc_attr( $cook_time ); ?>" min="0" class="small-text">
					</div>
					<div>
						<label for="dish_recipe_total_time">
							<?php esc_html_e( 'Total Time (mins)', 'dish-recipes' ); ?>
							<span class="dish-meta-hint"><?php esc_html_e( 'Override auto-sum', 'dish-recipes' ); ?></span>
						</label>
						<input type="number" id="dish_recipe_total_time" name="dish_recipe_total_time"
							value="<?php echo esc_attr( $total_time ); ?>" min="0" class="small-text">
					</div>
				</div>

				<div class="dish-meta-row">
					<label for="dish_recipe_difficulty"><?php esc_html_e( 'Difficulty', 'dish-recipes' ); ?></label>
					<select id="dish_recipe_difficulty" name="dish_recipe_difficulty">
						<?php foreach ( self::DIFFICULTY_OPTIONS as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $difficulty, $val ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="dish-meta-row dish-meta-row--inline">
					<div>
						<label for="dish_recipe_cuisine"><?php esc_html_e( 'Cuisine', 'dish-recipes' ); ?></label>
						<input type="text" id="dish_recipe_cuisine" name="dish_recipe_cuisine"
							value="<?php echo esc_attr( $cuisine ); ?>"
							placeholder="e.g. Italian, Japanese" class="regular-text">
					</div>
					<div>
						<label for="dish_recipe_course"><?php esc_html_e( 'Course', 'dish-recipes' ); ?></label>
						<input type="text" id="dish_recipe_course" name="dish_recipe_course"
							value="<?php echo esc_attr( $course ); ?>"
							placeholder="e.g. Main, Dessert, Starter" class="regular-text">
					</div>
				</div>

				<div class="dish-meta-row">
					<label for="dish_recipe_notes"><?php esc_html_e( 'Notes / Tips / Variations', 'dish-recipes' ); ?></label>
					<textarea id="dish_recipe_notes" name="dish_recipe_notes"
						rows="5" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
				</div>

			<div class="dish-meta-row">
				<label class="dish-meta-checkbox">
					<input type="checkbox" name="dish_recipe_is_spotlight" value="1" <?php checked( $is_spotlight ); ?>>
					<?php esc_html_e( 'Is Spotlight — feature this recipe on the homepage spotlight component', 'dish-recipes' ); ?>
				</label>
			</div>

			</div><!-- #dish-tab-overview -->

			<?php /* ----------------------------------------------------------------
			 * Tab 2 — Ingredients (sectioned repeater)
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel" id="dish-tab-ingredients">

				<p class="dish-meta-hint"><?php esc_html_e( 'Add sections for recipes with multiple components (e.g. "For the sauce"). Leave the section heading blank for simple recipes.', 'dish-recipes' ); ?></p>

				<div id="dish-ingredient-sections">
					<?php foreach ( $ingredients as $s_idx => $section ) :
						$s_heading = esc_attr( $section['heading'] ?? '' );
						?>
						<div class="dish-section-block" data-section="<?php echo (int) $s_idx; ?>">
							<div class="dish-section-header">
								<span class="dish-section-drag dashicons dashicons-move"></span>
								<input type="text"
									name="dish_recipe_ingredients[<?php echo (int) $s_idx; ?>][heading]"
									value="<?php echo $s_heading; ?>"
									placeholder="<?php esc_attr_e( 'Section heading (optional)', 'dish-recipes' ); ?>"
									class="dish-section-heading regular-text">
								<button type="button" class="dish-section-remove button-link dish-remove-red"
									aria-label="<?php esc_attr_e( 'Remove section', 'dish-recipes' ); ?>">
									<?php esc_html_e( '✕ Remove section', 'dish-recipes' ); ?>
								</button>
							</div>

							<div class="dish-ingredient-rows">
								<?php foreach ( ( $section['items'] ?? [] ) as $i_idx => $ing ) : ?>
									<div class="dish-ingredient-row">
										<span class="dish-row-drag dashicons dashicons-move"></span>
										<input type="text"
											name="dish_recipe_ingredients[<?php echo (int) $s_idx; ?>][items][<?php echo (int) $i_idx; ?>][qty]"
											value="<?php echo esc_attr( $ing['qty'] ?? '' ); ?>"
											placeholder="<?php esc_attr_e( 'Qty', 'dish-recipes' ); ?>"
											class="dish-ing-qty">
										<?php echo $this->render_unit_select(
											"dish_recipe_ingredients[{$s_idx}][items][{$i_idx}][unit]",
											$ing['unit'] ?? ''
										); ?>
										<input type="text"
											name="dish_recipe_ingredients[<?php echo (int) $s_idx; ?>][items][<?php echo (int) $i_idx; ?>][item]"
											value="<?php echo esc_attr( $ing['item'] ?? '' ); ?>"
											placeholder="<?php esc_attr_e( 'Ingredient', 'dish-recipes' ); ?>"
											class="dish-ing-item">
										<input type="text"
											name="dish_recipe_ingredients[<?php echo (int) $s_idx; ?>][items][<?php echo (int) $i_idx; ?>][note]"
											value="<?php echo esc_attr( $ing['note'] ?? '' ); ?>"
											placeholder="<?php esc_attr_e( 'Note (optional)', 'dish-recipes' ); ?>"
											class="dish-ing-note">
										<button type="button" class="dish-row-remove button-link dish-remove-red"
											aria-label="<?php esc_attr_e( 'Remove ingredient', 'dish-recipes' ); ?>">✕</button>
									</div>
								<?php endforeach; ?>
							</div>

							<button type="button" class="dish-add-ingredient button button-secondary">
								<?php esc_html_e( '+ Add Ingredient', 'dish-recipes' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" id="dish-add-ingredient-section" class="button button-primary">
					<?php esc_html_e( '+ Add Section', 'dish-recipes' ); ?>
				</button>

				<?php /* Hidden template row — cloned by JS */ ?>
				<template id="dish-ingredient-row-template">
					<div class="dish-ingredient-row">
						<span class="dish-row-drag dashicons dashicons-move"></span>
						<input type="text" name="" value=""
							placeholder="<?php esc_attr_e( 'Qty', 'dish-recipes' ); ?>" class="dish-ing-qty">
						<?php echo $this->render_unit_select( '__UNIT_NAME__', '' ); ?>
						<input type="text" name="" value=""
							placeholder="<?php esc_attr_e( 'Ingredient', 'dish-recipes' ); ?>" class="dish-ing-item">
						<input type="text" name="" value=""
							placeholder="<?php esc_attr_e( 'Note (optional)', 'dish-recipes' ); ?>" class="dish-ing-note">
						<button type="button" class="dish-row-remove button-link dish-remove-red"
							aria-label="<?php esc_attr_e( 'Remove ingredient', 'dish-recipes' ); ?>">✕</button>
					</div>
				</template>

				<?php /* Hidden template section — cloned by JS */ ?>
				<template id="dish-ingredient-section-template">
					<div class="dish-section-block" data-section="">
						<div class="dish-section-header">
							<span class="dish-section-drag dashicons dashicons-move"></span>
							<input type="text" name="" value=""
								placeholder="<?php esc_attr_e( 'Section heading (optional)', 'dish-recipes' ); ?>"
								class="dish-section-heading regular-text">
							<button type="button" class="dish-section-remove button-link dish-remove-red">
								<?php esc_html_e( '✕ Remove section', 'dish-recipes' ); ?>
							</button>
						</div>
						<div class="dish-ingredient-rows"></div>
						<button type="button" class="dish-add-ingredient button button-secondary">
							<?php esc_html_e( '+ Add Ingredient', 'dish-recipes' ); ?>
						</button>
					</div>
				</template>

			</div>

			<?php /* ----------------------------------------------------------------
			 * Tab 3 — Method (sectioned repeater)
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel" id="dish-tab-method">

				<p class="dish-meta-hint"><?php esc_html_e( 'Add sections for recipes with multiple components. Steps auto-number within each section. Leave section heading blank for simple recipes.', 'dish-recipes' ); ?></p>

				<div id="dish-method-sections">
					<?php foreach ( $method as $s_idx => $section ) :
						$s_heading = esc_attr( $section['heading'] ?? '' );
						?>
						<div class="dish-section-block" data-section="<?php echo (int) $s_idx; ?>">
							<div class="dish-section-header">
								<span class="dish-section-drag dashicons dashicons-move"></span>
								<input type="text"
									name="dish_recipe_method[<?php echo (int) $s_idx; ?>][heading]"
									value="<?php echo $s_heading; ?>"
									placeholder="<?php esc_attr_e( 'Section heading (optional)', 'dish-recipes' ); ?>"
									class="dish-section-heading regular-text">
								<button type="button" class="dish-section-remove button-link dish-remove-red">
									<?php esc_html_e( '✕ Remove section', 'dish-recipes' ); ?>
								</button>
							</div>

							<ol class="dish-method-steps">
								<?php foreach ( ( $section['steps'] ?? [] ) as $step_idx => $step ) : ?>
									<li class="dish-method-step">
										<span class="dish-row-drag dashicons dashicons-move"></span>
										<textarea
											name="dish_recipe_method[<?php echo (int) $s_idx; ?>][steps][<?php echo (int) $step_idx; ?>][text]"
											rows="2" class="large-text"><?php echo esc_textarea( $step['text'] ?? '' ); ?></textarea>
										<button type="button" class="dish-row-remove button-link dish-remove-red"
											aria-label="<?php esc_attr_e( 'Remove step', 'dish-recipes' ); ?>">✕</button>
									</li>
								<?php endforeach; ?>
							</ol>

							<button type="button" class="dish-add-step button button-secondary">
								<?php esc_html_e( '+ Add Step', 'dish-recipes' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>

				<button type="button" id="dish-add-method-section" class="button button-primary">
					<?php esc_html_e( '+ Add Section', 'dish-recipes' ); ?>
				</button>

				<?php /* Hidden template step — cloned by JS */ ?>
				<template id="dish-method-step-template">
					<li class="dish-method-step">
						<span class="dish-row-drag dashicons dashicons-move"></span>
						<textarea name="" rows="2" class="large-text"></textarea>
						<button type="button" class="dish-row-remove button-link dish-remove-red"
							aria-label="<?php esc_attr_e( 'Remove step', 'dish-recipes' ); ?>">✕</button>
					</li>
				</template>

				<template id="dish-method-section-template">
					<div class="dish-section-block" data-section="">
						<div class="dish-section-header">
							<span class="dish-section-drag dashicons dashicons-move"></span>
							<input type="text" name="" value=""
								placeholder="<?php esc_attr_e( 'Section heading (optional)', 'dish-recipes' ); ?>"
								class="dish-section-heading regular-text">
							<button type="button" class="dish-section-remove button-link dish-remove-red">
								<?php esc_html_e( '✕ Remove section', 'dish-recipes' ); ?>
							</button>
						</div>
						<ol class="dish-method-steps"></ol>
						<button type="button" class="dish-add-step button button-secondary">
							<?php esc_html_e( '+ Add Step', 'dish-recipes' ); ?>
						</button>
					</div>
				</template>

			</div>

			<?php /* ----------------------------------------------------------------
			 * Tab 4 — Dietary flags
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel" id="dish-tab-dietary">
				<div class="dish-checkbox-grid">
					<?php foreach ( self::DIETARY_FLAGS as $key => $label ) : ?>
						<label class="dish-checkbox-label">
							<input type="checkbox"
								name="dish_recipe_dietary_flags[]"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( in_array( $key, $dietary_set, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<?php /* ----------------------------------------------------------------
			 * Tab 5 — Related class templates
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel" id="dish-tab-related">
				<?php if ( empty( $class_templates ) ) : ?>
					<p class="dish-meta-hint">
						<?php esc_html_e( 'No published class templates found, or the Dish Events plugin is not active.', 'dish-recipes' ); ?>
					</p>
				<?php else : ?>
					<p class="dish-meta-hint"><?php esc_html_e( 'Select the class templates this recipe is featured in. Hold Ctrl / Cmd to select multiple.', 'dish-recipes' ); ?></p>
					<select name="dish_recipe_template_ids[]" multiple size="10" class="dish-template-select">
						<?php foreach ( $class_templates as $tmpl ) : ?>
							<option value="<?php echo (int) $tmpl->ID; ?>"
								<?php selected( in_array( $tmpl->ID, array_map( 'intval', $tmpl_ids ), true ) ); ?>>
								<?php echo esc_html( $tmpl->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
			</div>

			<?php /* ----------------------------------------------------------------
			 * Tab 6 — Legacy PDF
			 * -------------------------------------------------------------- */ ?>
			<div class="dish-meta-tab-panel" id="dish-tab-pdf">
				<p class="dish-meta-hint"><?php esc_html_e( 'Attach the original PDF recipe for download. Used during the migration period before full on-page content is entered.', 'dish-recipes' ); ?></p>
				<input type="hidden" id="dish_recipe_pdf_id" name="dish_recipe_pdf_id"
					value="<?php echo esc_attr( $pdf_id ?: '' ); ?>">
				<div class="dish-pdf-preview">
					<?php if ( $pdf_filename ) : ?>
						<span id="dish-pdf-filename"><?php echo esc_html( $pdf_filename ); ?></span>
						<button type="button" id="dish-pdf-remove" class="button-link dish-remove-red">
							<?php esc_html_e( 'Remove', 'dish-recipes' ); ?>
						</button>
					<?php else : ?>
						<span id="dish-pdf-filename"></span>
					<?php endif; ?>
				</div>
				<button type="button" id="dish-pdf-upload" class="button button-secondary">
					<?php esc_html_e( 'Upload / Select PDF', 'dish-recipes' ); ?>
				</button>
			</div>

		</div><!-- .dish-recipe-meta-box -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Unit select helper
	// -------------------------------------------------------------------------

	/**
	 * Render a unit <select> element with grouped <optgroup> options.
	 *
	 * @param string $name     Field name attribute.
	 * @param string $selected Currently selected unit key.
	 * @return string HTML string.
	 */
	private function render_unit_select( string $name, string $selected ): string {
		$html = '<select name="' . esc_attr( $name ) . '" class="dish-ing-unit">';

		foreach ( self::UNIT_OPTIONS as $group_label => $options ) {
			$html .= '<optgroup label="' . esc_attr( $group_label ) . '">';
			foreach ( $options as $val => $label ) {
				$html .= '<option value="' . esc_attr( $val ) . '"'
					. selected( $selected, $val, false ) . '>'
					. esc_html( $label ) . '</option>';
			}
			$html .= '</optgroup>';
		}

		$html .= '</select>';

		return $html;
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save all meta box fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		// Nonce check.
		if (
			! isset( $_POST['dish_recipe_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dish_recipe_nonce'] ) ), 'dish_recipe_save_meta' )
		) {
			return;
		}

		// Don't save during autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Capability check.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// ---- Overview ----
		$fields = [
			'dish_recipe_yield'      => 'sanitize_text_field',
			'dish_recipe_prep_time'  => 'absint',
			'dish_recipe_cook_time'  => 'absint',
			'dish_recipe_total_time' => 'absint',
			'dish_recipe_difficulty' => 'sanitize_text_field',
			'dish_recipe_cuisine'    => 'sanitize_text_field',
			'dish_recipe_course'     => 'sanitize_text_field',
			'dish_recipe_notes'      => 'sanitize_textarea_field',
		];

		foreach ( $fields as $key => $sanitizer ) {
			$value = isset( $_POST[ $key ] ) ? call_user_func( $sanitizer, wp_unslash( $_POST[ $key ] ) ) : '';
			update_post_meta( $post_id, $key, $value );
		}

		update_post_meta( $post_id, 'dish_recipe_is_spotlight', isset( $_POST['dish_recipe_is_spotlight'] ) ? '1' : '0' );

		// ---- Ingredients ----
		$ingredients = [];
		$raw_sections = isset( $_POST['dish_recipe_ingredients'] )
			? (array) wp_unslash( $_POST['dish_recipe_ingredients'] )
			: [];

		foreach ( $raw_sections as $section ) {
			$heading = sanitize_text_field( $section['heading'] ?? '' );
			$items   = [];

			foreach ( (array) ( $section['items'] ?? [] ) as $ing ) {
				$item_text = sanitize_text_field( $ing['item'] ?? '' );
				if ( '' === $item_text ) {
					continue;
				}
				$items[] = [
					'qty'  => sanitize_text_field( $ing['qty']  ?? '' ),
					'unit' => sanitize_text_field( $ing['unit'] ?? '' ),
					'item' => $item_text,
					'note' => sanitize_text_field( $ing['note'] ?? '' ),
				];
			}

			if ( '' !== $heading || ! empty( $items ) ) {
				$ingredients[] = [ 'heading' => $heading, 'items' => $items ];
			}
		}

		update_post_meta( $post_id, 'dish_recipe_ingredients', wp_json_encode( $ingredients ) );

		// ---- Method ----
		$method      = [];
		$raw_methods = isset( $_POST['dish_recipe_method'] )
			? (array) wp_unslash( $_POST['dish_recipe_method'] )
			: [];

		foreach ( $raw_methods as $section ) {
			$heading = sanitize_text_field( $section['heading'] ?? '' );
			$steps   = [];
			$step_n  = 1;

			foreach ( (array) ( $section['steps'] ?? [] ) as $step ) {
				$text = sanitize_textarea_field( $step['text'] ?? '' );
				if ( '' === $text ) {
					continue;
				}
				$steps[] = [ 'step' => $step_n++, 'text' => $text ];
			}

			if ( '' !== $heading || ! empty( $steps ) ) {
				$method[] = [ 'heading' => $heading, 'steps' => $steps ];
			}
		}

		update_post_meta( $post_id, 'dish_recipe_method', wp_json_encode( $method ) );

		// ---- Dietary flags ----
		$valid_flags  = array_keys( self::DIETARY_FLAGS );
		$raw_dietary  = isset( $_POST['dish_recipe_dietary_flags'] )
			? (array) wp_unslash( $_POST['dish_recipe_dietary_flags'] )
			: [];
		$clean_dietary = array_values(
			array_filter( $raw_dietary, static fn( $f ) => in_array( $f, $valid_flags, true ) )
		);
		update_post_meta( $post_id, 'dish_recipe_dietary_flags', wp_json_encode( $clean_dietary ) );

		// ---- Related class templates ----
		$raw_tmpl  = isset( $_POST['dish_recipe_template_ids'] )
			? (array) wp_unslash( $_POST['dish_recipe_template_ids'] )
			: [];
		$clean_ids = array_values( array_map( 'strval', array_map( 'absint', $raw_tmpl ) ) );
		update_post_meta( $post_id, 'dish_recipe_template_ids', wp_json_encode( $clean_ids ) );

		// ---- PDF ----
		$pdf_id = absint( $_POST['dish_recipe_pdf_id'] ?? 0 );
		if ( $pdf_id ) {
			update_post_meta( $post_id, 'dish_recipe_pdf_id', $pdf_id );
		} else {
			delete_post_meta( $post_id, 'dish_recipe_pdf_id' );
		}
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue meta box CSS and JS on the dish_recipe edit screen only.
	 *
	 * @param string $hook_suffix Admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'dish_recipe' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'dish-recipes-admin',
			DISH_RECIPES_URL . 'assets/css/dish-recipes-admin.css',
			[],
			DISH_RECIPES_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'dish-recipes-admin',
			DISH_RECIPES_URL . 'assets/js/dish-recipes-admin.js',
			[ 'jquery-ui-sortable' ],
			DISH_RECIPES_VERSION,
			true
		);
	}
}
