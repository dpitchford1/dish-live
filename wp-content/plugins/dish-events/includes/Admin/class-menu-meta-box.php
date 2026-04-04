<?php
/**
 * Menu meta box.
 *
 * Attaches to dish_class_template and stores:
 *   dish_menu_items           — textarea, newline-separated dish list
 *   dish_menu_dietary_flags   — JSON string[], allergen keys from DIETARY_FLAGS
 *   dish_menu_friendly_for    — JSON string[], keys from FRIENDLY_FOR
 *   dish_menu_custom_dietary  — JSON string[], free-text custom dietary labels
 *   dish_menu_custom_friendly — JSON string[], free-text custom friendly-for labels
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use WP_Post;

/**
 * Class MenuMetaBox
 */
final class MenuMetaBox {

	/**
	 * Allergen flags — key → display label.
	 * Used in admin checkboxes and surfaced as public constants for templates.
	 */
	public const DIETARY_FLAGS = [
		'seafood'   => 'Seafood',
		'shellfish' => 'Shellfish',
		'gluten'    => 'Gluten',
		'dairy'     => 'Dairy',
		'eggs'      => 'Eggs',
		'nuts'      => 'Nuts',
		'tree_nuts' => 'Tree Nuts',
		'pork'      => 'Pork',
	];

	/**
	 * Friendly-for tags — key → display label.
	 */
	public const FRIENDLY_FOR = [
		'vegetarian_friendly' => 'Vegetarian Friendly',
		'vegetarian'          => 'Vegetarian',
		'vegan'               => 'Vegan',
		'dairy_free_friendly' => 'Dairy Free Friendly',
		'gluten_free_friendly'=> 'Gluten Free Friendly',
		'pescatarian'         => 'Pescatarian',
		'gluten_free'         => 'Gluten Free',
	];

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register(): void {
		add_meta_box(
			'dish_menu_details',
			__( 'Class Menu', 'dish-events' ),
			[ $this, 'render' ],
			'dish_class_template',
			'normal',
			'default'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render( WP_Post $post ): void {
		$menu_items      = (string) get_post_meta( $post->ID, 'dish_menu_items',            true );
		$dietary_saved   = (array)  json_decode( get_post_meta( $post->ID, 'dish_menu_dietary_flags',   true ) ?: '[]', true );
		$friendly_saved  = (array)  json_decode( get_post_meta( $post->ID, 'dish_menu_friendly_for',    true ) ?: '[]', true );
		$custom_dietary  = (array)  json_decode( get_post_meta( $post->ID, 'dish_menu_custom_dietary',  true ) ?: '[]', true );
		$custom_friendly = (array)  json_decode( get_post_meta( $post->ID, 'dish_menu_custom_friendly', true ) ?: '[]', true );

		wp_nonce_field( 'dish_menu_save_' . $post->ID, 'dish_menu_nonce' );
		?>
		<div class="dish-meta-box dish-menu-meta-box">
			<table class="form-table dish-form-table">

				<tr>
					<th><label for="dish_menu_items"><?php esc_html_e( 'Menu Items', 'dish-events' ); ?></label></th>
					<td>
						<textarea id="dish_menu_items" name="dish_menu_items" rows="8" class="large-text"
						          placeholder="<?php esc_attr_e( 'One course or dish per line', 'dish-events' ); ?>"><?php echo esc_textarea( $menu_items ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One dish per line. Displayed on the class page and in the upcoming menus list.', 'dish-events' ); ?></p>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Dietary Flags', 'dish-events' ); ?></th>
					<td>
						<ul class="dish-flag-list" id="dish-custom-dietary-list">
							<?php foreach ( self::DIETARY_FLAGS as $key => $label ) : ?>
								<li>
									<label>
										<input type="checkbox" name="dish_menu_dietary_flags[]"
										       value="<?php echo esc_attr( $key ); ?>"
										       <?php checked( in_array( $key, $dietary_saved, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								</li>
							<?php endforeach; ?>

							<?php if ( ! empty( $custom_dietary ) ) : ?>
								<li class="dish-flag-list__divider" aria-hidden="true"></li>
								<?php foreach ( $custom_dietary as $label ) : ?>
									<li class="dish-custom-flag">
										<input type="text" name="dish_menu_custom_dietary[]"
										       value="<?php echo esc_attr( $label ); ?>"
										       class="regular-text dish-custom-flag__input"
										       placeholder="<?php esc_attr_e( 'Custom flag label', 'dish-events' ); ?>">
										<button type="button" class="dish-custom-flag__remove button-link"
										        aria-label="<?php esc_attr_e( 'Remove', 'dish-events' ); ?>">&#x2715;</button>
									</li>
								<?php endforeach; ?>
							<?php endif; ?>

							<li class="dish-custom-flag__add-row">
								<button type="button" class="dish-custom-flag__add button"
								        data-target="dish-custom-dietary-list"
								        data-field-name="dish_menu_custom_dietary[]"
								        data-placeholder="<?php esc_attr_e( 'Custom flag label', 'dish-events' ); ?>">
									<?php esc_html_e( '+ Add Custom Flag', 'dish-events' ); ?>
								</button>
							</li>
						</ul>
					</td>
				</tr>

				<tr>
					<th><?php esc_html_e( 'Friendly For', 'dish-events' ); ?></th>
					<td>
						<ul class="dish-flag-list" id="dish-custom-friendly-list">
							<?php foreach ( self::FRIENDLY_FOR as $key => $label ) : ?>
								<li>
									<label>
										<input type="checkbox" name="dish_menu_friendly_for[]"
										       value="<?php echo esc_attr( $key ); ?>"
										       <?php checked( in_array( $key, $friendly_saved, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								</li>
							<?php endforeach; ?>

							<?php if ( ! empty( $custom_friendly ) ) : ?>
								<li class="dish-flag-list__divider" aria-hidden="true"></li>
								<?php foreach ( $custom_friendly as $label ) : ?>
									<li class="dish-custom-flag">
										<input type="text" name="dish_menu_custom_friendly[]"
										       value="<?php echo esc_attr( $label ); ?>"
										       class="regular-text dish-custom-flag__input"
										       placeholder="<?php esc_attr_e( 'Custom label', 'dish-events' ); ?>">
										<button type="button" class="dish-custom-flag__remove button-link"
										        aria-label="<?php esc_attr_e( 'Remove', 'dish-events' ); ?>">&#x2715;</button>
									</li>
								<?php endforeach; ?>
							<?php endif; ?>

							<li class="dish-custom-flag__add-row">
								<button type="button" class="dish-custom-flag__add button"
								        data-target="dish-custom-friendly-list"
								        data-field-name="dish_menu_custom_friendly[]"
								        data-placeholder="<?php esc_attr_e( 'Custom label', 'dish-events' ); ?>">
									<?php esc_html_e( '+ Add Custom Friendly For', 'dish-events' ); ?>
								</button>
							</li>
						</ul>
					</td>
				</tr>

			</table>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['dish_menu_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'dish_menu_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Menu items — stored as a sanitized newline-separated string.
		$menu_items = sanitize_textarea_field( wp_unslash( $_POST['dish_menu_items'] ?? '' ) );
		if ( $menu_items ) {
			update_post_meta( $post_id, 'dish_menu_items', $menu_items );
		} else {
			delete_post_meta( $post_id, 'dish_menu_items' );
		}

		// Dietary flags — whitelist against known keys.
		$dietary = array_values( array_intersect(
			array_map( 'sanitize_key', (array) ( $_POST['dish_menu_dietary_flags'] ?? [] ) ),
			array_keys( self::DIETARY_FLAGS )
		) );
		update_post_meta( $post_id, 'dish_menu_dietary_flags', wp_json_encode( $dietary ) );

		// Friendly-for — whitelist against known keys.
		$friendly = array_values( array_intersect(
			array_map( 'sanitize_key', (array) ( $_POST['dish_menu_friendly_for'] ?? [] ) ),
			array_keys( self::FRIENDLY_FOR )
		) );
		update_post_meta( $post_id, 'dish_menu_friendly_for', wp_json_encode( $friendly ) );

		// Custom dietary labels — free-text, sanitized.
		$custom_dietary = array_values( array_filter( array_map(
			'sanitize_text_field',
			array_map( 'wp_unslash', (array) ( $_POST['dish_menu_custom_dietary'] ?? [] ) )
		) ) );
		if ( $custom_dietary ) {
			update_post_meta( $post_id, 'dish_menu_custom_dietary', wp_json_encode( $custom_dietary ) );
		} else {
			delete_post_meta( $post_id, 'dish_menu_custom_dietary' );
		}

		// Custom friendly-for labels — free-text, sanitized.
		$custom_friendly = array_values( array_filter( array_map(
			'sanitize_text_field',
			array_map( 'wp_unslash', (array) ( $_POST['dish_menu_custom_friendly'] ?? [] ) )
		) ) );
		if ( $custom_friendly ) {
			update_post_meta( $post_id, 'dish_menu_custom_friendly', wp_json_encode( $custom_friendly ) );
		} else {
			delete_post_meta( $post_id, 'dish_menu_custom_friendly' );
		}
	}
}
