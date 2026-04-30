<?php
/**
 * Recipe category term meta — image field on add/edit screens.
 *
 * Stores: dish_recipe_category_image_id (attachment ID) as term meta.
 *
 * @package Dish\Recipes\Admin
 */

declare( strict_types=1 );

namespace Dish\Recipes\Admin;

/**
 * Class CategoryMeta
 */
class CategoryMeta {

	private const META_KEY = 'dish_recipe_category_image_id';

	/**
	 * Register hooks via the plugin Loader.
	 *
	 * @param \Dish\Recipes\Core\Loader $loader
	 */
	public function register_hooks( \Dish\Recipes\Core\Loader $loader ): void {
		$loader->add_action( 'dish_recipe_category_add_form_fields',  $this, 'render_add_field' );
		$loader->add_action( 'dish_recipe_category_edit_form_fields', $this, 'render_edit_field' );
		$loader->add_action( 'created_dish_recipe_category',          $this, 'save', 10, 1 );
		$loader->add_action( 'edited_dish_recipe_category',           $this, 'save', 10, 1 );
		$loader->add_action( 'admin_enqueue_scripts',                 $this, 'enqueue_media_uploader' );
	}

	// -------------------------------------------------------------------------
	// Enqueue
	// -------------------------------------------------------------------------

	/**
	 * Enqueue media uploader on taxonomy screens.
	 *
	 * @param string $hook
	 */
	public function enqueue_media_uploader( string $hook ): void {
		if ( ! in_array( $hook, [ 'edit-tags.php', 'term.php' ], true ) ) {
			return;
		}
		if ( ( $_GET['taxonomy'] ?? '' ) !== 'dish_recipe_category' ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'dish-recipes-term-image',
			plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/dish-recipes-term-image.js',
			[ 'jquery' ],
			'1.0.0',
			true
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Field on the Add Term screen (plain <div>, no <tr>).
	 */
	public function render_add_field(): void {
		?>
		<div class="form-field dish-term-image-wrap">
			<label for="dish-term-image-id"><?php esc_html_e( 'Category Image', 'dish-recipes' ); ?></label>
			<div id="dish-term-image-preview"></div>
			<input type="hidden" id="dish-term-image-id" name="dish_recipe_category_image_id" value="">
			<button type="button" class="button" id="dish-term-image-select"><?php esc_html_e( 'Select Image', 'dish-recipes' ); ?></button>
			<p class="description"><?php esc_html_e( 'Shown as the hero image when this category is active on the recipes page.', 'dish-recipes' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Field on the Edit Term screen (inside a <tr>).
	 *
	 * @param \WP_Term $term
	 */
	public function render_edit_field( \WP_Term $term ): void {
		$image_id  = (int) get_term_meta( $term->term_id, self::META_KEY, true );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<tr class="form-field dish-term-image-wrap">
			<th scope="row">
				<label for="dish-term-image-id"><?php esc_html_e( 'Category Image', 'dish-recipes' ); ?></label>
			</th>
			<td>
				<div id="dish-term-image-preview">
					<?php if ( $image_url ) : ?>
						<img src="<?php echo esc_url( $image_url ); ?>" style="max-width:300px;height:auto;display:block;margin-bottom:8px;" alt="">
					<?php endif; ?>
				</div>
				<input type="hidden" id="dish-term-image-id" name="dish_recipe_category_image_id" value="<?php echo esc_attr( (string) $image_id ); ?>">
				<button type="button" class="button" id="dish-term-image-select"><?php esc_html_e( 'Select Image', 'dish-recipes' ); ?></button>
				<?php if ( $image_id ) : ?>
					<button type="button" class="button" id="dish-term-image-remove"><?php esc_html_e( 'Remove', 'dish-recipes' ); ?></button>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Shown as the hero image when this category is active on the recipes page.', 'dish-recipes' ); ?></p>
			</td>
		</tr>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save term meta on create/update.
	 *
	 * @param int $term_id
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['dish_recipe_category_image_id'] ) ) {
			return;
		}
		$image_id = absint( $_POST['dish_recipe_category_image_id'] );
		if ( $image_id ) {
			update_term_meta( $term_id, self::META_KEY, $image_id );
		} else {
			delete_term_meta( $term_id, self::META_KEY );
		}
	}

	// -------------------------------------------------------------------------
	// Static getter
	// -------------------------------------------------------------------------

	/**
	 * Get the image attachment ID for a given term.
	 *
	 * @param  int|\WP_Term $term Term ID or WP_Term object.
	 * @return int  0 if none set.
	 */
	public static function get_image_id( int|\WP_Term $term ): int {
		$term_id = $term instanceof \WP_Term ? $term->term_id : $term;
		return (int) get_term_meta( $term_id, self::META_KEY, true );
	}
}
