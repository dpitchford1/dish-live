<?php
/**
 * Class Details meta box for the dish_class post type.
 *
 * Four checkbox-list sections, each with:
 *  - Toggle All checkbox
 *  - Editable item list (add / remove items)
 *  - Default items pre-seeded on new classes
 *
 * Sections
 * --------
 *  What to Bring        → dish_what_to_bring      (JSON)
 *  Class Requirements   → dish_class_requirements  (JSON)
 *  What's Included      → dish_whats_included      (JSON)
 *  Dietary Flags        → dish_dietary_flags        (JSON)
 *
 * Storage format per key: [{"label":"...","checked":true}, ...]
 *
 * Form field pattern per section (prefix e.g. 'dish_wtb'):
 *   dish_wtb_label[]   — hidden input for every item (always submitted)
 *   dish_wtb_checked[] — checkbox value for checked items only
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

/**
 * Class ClassDetailsMetaBox
 */
final class ClassDetailsMetaBox {

	private const NONCE_ACTION = 'dish_save_class_details';
	private const NONCE_FIELD  = 'dish_class_details_nonce';

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register the meta box.
	 * Hooked to 'add_meta_boxes'.
	 */
	public function register(): void {
		add_meta_box(
			'dish_class_details',
			__( 'Class Details', 'dish-events' ),
			[ $this, 'render' ],
			'dish_class',
			'normal',
			'default'
		);
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	/**
	 * Render the meta box.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		$attendee_note = (string) get_post_meta( $post->ID, 'dish_attendee_note', true );
		?>
		<div class="dish-class-details">
			<?php foreach ( $this->sections() as $config ) : ?>
				<?php $this->render_section( $post->ID, $config ); ?>
			<?php endforeach; ?>
		</div>

		<div style="margin-top:16px;padding-top:16px;border-top:1px solid #f0f0f1;">
			<label for="dish_attendee_note" style="font-weight:600;display:block;margin-bottom:6px;"><?php esc_html_e( 'Attendee Note', 'dish-events' ); ?></label>
			<textarea id="dish_attendee_note" name="dish_attendee_note"
				rows="3" class="large-text"><?php echo esc_textarea( $attendee_note ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Optional message displayed to attendees on the class page (e.g. parking info, what to wear, etc.).', 'dish-events' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render one checkbox-list section.
	 *
	 * @param int                  $post_id
	 * @param array<string,mixed>  $config
	 */
	private function render_section( int $post_id, array $config ): void {
		$saved = get_post_meta( $post_id, $config['meta_key'], true );

		// Decode saved JSON, or fall back to defaults on new/empty post.
		$items = [];
		if ( $saved !== '' && $saved !== null && $saved !== false ) {
			$decoded = json_decode( (string) $saved, true );
			$items   = is_array( $decoded ) ? $decoded : [];
		}
		if ( empty( $items ) ) {
			$items = $config['defaults'];
		}

		$prefix = $config['prefix'];
		?>
		<div class="dish-detail-section" data-prefix="<?php echo esc_attr( $prefix ); ?>">

			<div class="dish-detail-section__header">
				<h4 class="dish-detail-section__title"><?php echo esc_html( $config['title'] ); ?></h4>
				<label class="dish-toggle-all-label">
					<input type="checkbox" class="dish-toggle-all">
					<?php esc_html_e( 'Toggle All', 'dish-events' ); ?>
				</label>
			</div>

			<ul class="dish-detail-list">
				<?php foreach ( $items as $item ) :
					$label   = (string) ( $item['label'] ?? '' );
					$checked = (bool) ( $item['checked'] ?? false );
					if ( $label === '' ) continue;
					?>
					<li class="dish-detail-item">
						<label>
							<input type="checkbox"
								class="dish-item-check"
								name="<?php echo esc_attr( $prefix ); ?>_checked[]"
								value="<?php echo esc_attr( $label ); ?>"
								<?php checked( $checked ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<input type="hidden"
							name="<?php echo esc_attr( $prefix ); ?>_label[]"
							value="<?php echo esc_attr( $label ); ?>">
						<button type="button" class="dish-remove-item button-link"
							title="<?php esc_attr_e( 'Remove item', 'dish-events' ); ?>">
						<span class="dashicons dashicons-trash"></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>

			<div class="dish-detail-section__add">
				<input type="text"
					class="dish-add-item-input"
					placeholder="<?php esc_attr_e( 'Add item…', 'dish-events' ); ?>"
					aria-label="<?php esc_attr_e( 'New item label', 'dish-events' ); ?>">
				<button type="button" class="button dish-add-item-btn">
					<?php esc_html_e( 'Add', 'dish-events' ); ?>
				</button>
			</div>

		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	/**
	 * Save all section data.
	 * Hooked to 'save_post_dish_class'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::NONCE_FIELD ] )
			|| ! wp_verify_nonce( sanitize_key( $_POST[ self::NONCE_FIELD ] ), self::NONCE_ACTION )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( $this->sections() as $config ) {
			$this->save_section( $post_id, $config['prefix'], $config['meta_key'] );
		}

		update_post_meta( $post_id, 'dish_attendee_note', sanitize_textarea_field( $_POST['dish_attendee_note'] ?? '' ) );
	}

	/**
	 * Reconstruct and save one section from the submitted label + checked arrays.
	 *
	 * @param int    $post_id
	 * @param string $prefix   e.g. 'dish_wtb'
	 * @param string $meta_key e.g. 'dish_what_to_bring'
	 */
	private function save_section( int $post_id, string $prefix, string $meta_key ): void {
		// All item labels (from hidden inputs — always submitted, even for unchecked).
		$labels = isset( $_POST[ $prefix . '_label' ] ) && is_array( $_POST[ $prefix . '_label' ] )
			? array_map( 'sanitize_text_field', $_POST[ $prefix . '_label' ] )
			: [];

		// Only checked item labels (checkbox values — absent when unchecked).
		$checked = isset( $_POST[ $prefix . '_checked' ] ) && is_array( $_POST[ $prefix . '_checked' ] )
			? array_map( 'sanitize_text_field', $_POST[ $prefix . '_checked' ] )
			: [];

		$items = [];
		foreach ( $labels as $label ) {
			$label = trim( $label );
			if ( $label === '' ) {
				continue;
			}
			$items[] = [
				'label'   => $label,
				'checked' => in_array( $label, $checked, true ),
			];
		}

		update_post_meta( $post_id, $meta_key, wp_json_encode( $items ) );
	}

	// -------------------------------------------------------------------------
	// Section configuration
	// -------------------------------------------------------------------------

	/**
	 * Ordered section definitions.
	 * Kept as a method (not a const) so titles can be translated via __().
	 *
	 * @return array<string, array<string,mixed>>
	 */
	private function sections(): array {
		return [
			'what_to_bring' => [
				'title'    => __( 'What to Bring', 'dish-events' ),
				'prefix'   => 'dish_wtb',
				'meta_key' => 'dish_what_to_bring',
				'defaults' => [
					[ 'label' => 'Your Appetite',     'checked' => true ],
					[ 'label' => 'Closed Toed Shoes', 'checked' => true ],
				],
			],
			'class_requirements' => [
				'title'    => __( 'Class Requirements', 'dish-events' ),
				'prefix'   => 'dish_req',
				'meta_key' => 'dish_class_requirements',
				'defaults' => [
					[ 'label' => 'Tie Back Hair',                   'checked' => true ],
					[ 'label' => 'Remove Large Jewellery',          'checked' => true ],
					[ 'label' => 'State Allergies before arriving', 'checked' => true ],
				],
			],
			'whats_included' => [
				'title'    => __( "What's Included", 'dish-events' ),
				'prefix'   => 'dish_inc',
				'meta_key' => 'dish_whats_included',
				'defaults' => [
					[ 'label' => 'Arrival Drink', 'checked' => true ],
					[ 'label' => '3 Course Meal', 'checked' => true ],
					[ 'label' => 'Instructions',  'checked' => true ],
					[ 'label' => 'Equipment',     'checked' => true ],
				],
			],
			'dietary_flags' => [
				'title'    => __( 'Dietary Flags', 'dish-events' ),
				'prefix'   => 'dish_diet',
				'meta_key' => 'dish_dietary_flags',
				'defaults' => [
					[ 'label' => 'Contains Gluten',          'checked' => false ],
					[ 'label' => 'Contains Nuts',            'checked' => false ],
					[ 'label' => 'Contains Pork',            'checked' => false ],
					[ 'label' => 'Vegetarian Friendly',      'checked' => false ],
					[ 'label' => 'Pescatarian Friendly',     'checked' => false ],
					[ 'label' => 'Dairy Free Friendly',      'checked' => false ],
				],
			],
		];
	}
}
