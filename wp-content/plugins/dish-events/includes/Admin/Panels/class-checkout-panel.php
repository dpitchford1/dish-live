<?php
/**
 * Checkout panel for the Class Settings meta box.
 *
 * Handles: per-class checkout field overrides (override toggle + field builder).
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Class CheckoutPanel
 */
final class CheckoutPanel {

	/**
	 * Render the Checkout panel.
	 *
	 * @param array<string,mixed> $meta
	 */
	public function render( array $meta ): void {
		$override = (bool) ( $meta['dish_checkout_override'] ?? false );

		$raw_json = (string) ( $meta['dish_checkout_fields_json'] ?? '' );
		$fields   = [];
		if ( $raw_json !== '' ) {
			$decoded = json_decode( $raw_json, true );
			$fields  = is_array( $decoded ) ? $decoded : [];
		}
		if ( empty( $fields ) ) {
			$fields = [
				[ 'label' => 'First Name', 'type' => 'text', 'required' => true,  'per_attendee' => true ],
				[ 'label' => 'Last Name',  'type' => 'text', 'required' => true,  'per_attendee' => true ],
			];
		}

		$field_types = [
			'text'     => __( 'Text',     'dish-events' ),
			'textarea' => __( 'Textarea', 'dish-events' ),
			'select'   => __( 'Select',   'dish-events' ),
			'checkbox' => __( 'Checkbox', 'dish-events' ),
			'radio'    => __( 'Radio',    'dish-events' ),
		];
		?>
		<div class="dish-metabox__panel" data-panel="checkout" hidden>
			<table class="form-table dish-form-table">
				<tr>
					<th><?php esc_html_e( 'Override global fields', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="dish_checkout_override" value="1"
								<?php checked( $override ); ?>>
							<?php esc_html_e( 'Use custom checkout fields for this class only', 'dish-events' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'When checked, the global checkout fields are ignored for this class.', 'dish-events' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<div class="dish-checkout-fields">
				<h4 style="margin:16px 0 8px;"><?php esc_html_e( 'Checkout Fields', 'dish-events' ); ?></h4>
				<p class="description" style="margin-bottom:12px;">
					<?php esc_html_e( 'Fields collected at checkout. Per-attendee fields repeat once per ticket.', 'dish-events' ); ?>
				</p>

				<table class="widefat striped dish-cf-table" style="table-layout:fixed;">
					<thead>
						<tr>
							<th style="width:36px;"></th>
							<th><?php esc_html_e( 'Label', 'dish-events' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Type', 'dish-events' ); ?></th>
							<th style="width:90px;text-align:center;"><?php esc_html_e( 'Required', 'dish-events' ); ?></th>
							<th style="width:110px;text-align:center;"><?php esc_html_e( 'Per Attendee', 'dish-events' ); ?></th>
							<th style="width:40px;"></th>
						</tr>
					</thead>
					<tbody id="dish-cf-body">
					<?php foreach ( $fields as $idx => $field ) : ?>
						<tr class="dish-cf-row">
							<td><span class="dashicons dashicons-move dish-cf-handle" style="cursor:move;color:#aaa;"></span></td>
							<td>
								<input type="text" class="widefat"
									name="dish_cf[<?php echo $idx; ?>][label]"
									value="<?php echo esc_attr( (string) ( $field['label'] ?? '' ) ); ?>"
									placeholder="<?php esc_attr_e( 'Field label', 'dish-events' ); ?>">
							</td>
							<td>
								<select name="dish_cf[<?php echo $idx; ?>][type]" class="widefat">
									<?php foreach ( $field_types as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>"
											<?php selected( ( $field['type'] ?? 'text' ), $val ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="dish_cf[<?php echo $idx; ?>][required]" value="1"
									<?php checked( ! empty( $field['required'] ) ); ?>>
							</td>
							<td style="text-align:center;">
								<input type="checkbox" name="dish_cf[<?php echo $idx; ?>][per_attendee]" value="1"
									<?php checked( ! empty( $field['per_attendee'] ) ); ?>>
							</td>
							<td>
								<button type="button" class="button-link dish-cf-remove"
									title="<?php esc_attr_e( 'Remove', 'dish-events' ); ?>">
									<span class="dashicons dashicons-trash" style="color:#b32d2e;"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top:10px;">
					<button type="button" class="button" id="dish-cf-add">
						<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;"></span>
						<?php esc_html_e( 'Add Field', 'dish-events' ); ?>
					</button>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Checkout panel fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		update_post_meta( $post_id, 'dish_checkout_override', ! empty( $_POST['dish_checkout_override'] ) );

		$cf_rows          = isset( $_POST['dish_cf'] ) && is_array( $_POST['dish_cf'] ) ? $_POST['dish_cf'] : [];
		$cf_fields        = [];
		$allowed_cf_types = [ 'text', 'textarea', 'select', 'checkbox', 'radio' ];

		foreach ( $cf_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = sanitize_text_field( $row['label'] ?? '' );
			if ( $label === '' ) {
				continue;
			}
			$type = sanitize_key( $row['type'] ?? 'text' );
			if ( ! in_array( $type, $allowed_cf_types, true ) ) {
				$type = 'text';
			}
			$cf_fields[] = [
				'label'        => $label,
				'type'         => $type,
				'required'     => ! empty( $row['required'] ),
				'per_attendee' => ! empty( $row['per_attendee'] ),
			];
		}

		update_post_meta( $post_id, 'dish_checkout_fields_json', wp_json_encode( $cf_fields ) );
	}
}
