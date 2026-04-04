<?php
/**
 * Date & Time panel for the Class Settings meta box.
 *
 * Handles: start/end datetime, recurrence rule.
 *
 * @package Dish\Events\Admin\Panels
 */

declare( strict_types=1 );

namespace Dish\Events\Admin\Panels;

/**
 * Class DatetimePanel
 */
final class DatetimePanel {

	use MetaBoxHelpers;

	/**
	 * Render the Date & Time panel.
	 *
	 * @param array<string,mixed> $meta Flat post meta array.
	 */
	public function render( array $meta ): void {
		$start  = $this->epoch_to_local( (int) ( $meta['dish_start_datetime'] ?? 0 ) );
		$end    = $this->epoch_to_local( (int) ( $meta['dish_end_datetime'] ?? 0 ) );
		$r      = is_array( $meta['dish_recurrence'] ?? null ) ? $meta['dish_recurrence'] : [];
		$r_type = $r['type'] ?? 'none';
		$child_ids     = is_array( $r['child_ids'] ?? null ) ? $r['child_ids'] : [];
		$is_rec_parent = ! empty( $child_ids );
		?>
		<div class="dish-metabox__panel" data-panel="datetime">
			<table class="form-table dish-form-table">
				<tr>
					<th><label for="dish_start_datetime"><?php esc_html_e( 'Start', 'dish-events' ); ?></label></th>
					<td>
						<input type="datetime-local" id="dish_start_datetime" name="dish_start_datetime"
							value="<?php echo esc_attr( $start ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><label for="dish_end_datetime"><?php esc_html_e( 'End', 'dish-events' ); ?></label></th>
					<td>
						<input type="datetime-local" id="dish_end_datetime" name="dish_end_datetime"
							value="<?php echo esc_attr( $end ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><label for="dish_recurrence_type"><?php esc_html_e( 'Recurrence', 'dish-events' ); ?></label></th>
					<td>
						<select id="dish_recurrence_type" name="dish_recurrence[type]">
							<option value="none"    <?php selected( $r_type, 'none' ); ?>><?php esc_html_e( 'Does not repeat', 'dish-events' ); ?></option>

							<option value="weekly"  <?php selected( $r_type, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'dish-events' ); ?></option>
							<option value="monthly" <?php selected( $r_type, 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'dish-events' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<div id="dish-recurrence-options" <?php echo $r_type === 'none' ? 'hidden' : ''; ?>>
				<table class="form-table dish-form-table">
					<tr>
						<th><label for="dish_recurrence_interval"><?php esc_html_e( 'Repeat every', 'dish-events' ); ?></label></th>
						<td>
							<input type="number" id="dish_recurrence_interval" name="dish_recurrence[interval]"
								value="<?php echo esc_attr( (string) ( $r['interval'] ?? 1 ) ); ?>"
								min="1" max="52" class="small-text">
							<span id="dish-recurrence-unit" class="description">
								<?php echo esc_html( $this->interval_unit_label( $r_type ) ); ?>
							</span>
						</td>
					</tr>
					<tr id="dish-recurrence-days-row" <?php echo $r_type !== 'weekly' ? 'hidden' : ''; ?>>
						<th><?php esc_html_e( 'On days', 'dish-events' ); ?></th>
						<td>
							<?php
							$days    = $r['days'] ?? [];
							$day_map = [
								'MO' => __( 'Mon', 'dish-events' ),
								'TU' => __( 'Tue', 'dish-events' ),
								'WE' => __( 'Wed', 'dish-events' ),
								'TH' => __( 'Thu', 'dish-events' ),
								'FR' => __( 'Fri', 'dish-events' ),
								'SA' => __( 'Sat', 'dish-events' ),
								'SU' => __( 'Sun', 'dish-events' ),
							];
							foreach ( $day_map as $val => $label ) {
								printf(
									'<label style="margin-right:8px"><input type="checkbox" name="dish_recurrence[days][]" value="%s"%s> %s</label>',
									esc_attr( $val ),
									in_array( $val, $days, true ) ? ' checked' : '',
									esc_html( $label )
								);
							}
							?>
						</td>
					</tr>
					<tr id="dish-recurrence-monthly-by-row" <?php echo $r_type !== 'monthly' ? 'hidden' : ''; ?>>
						<th><label for="dish_recurrence_monthly_by"><?php esc_html_e( 'Repeats on', 'dish-events' ); ?></label></th>
						<td>
							<select name="dish_recurrence[monthly_by]" id="dish_recurrence_monthly_by">
								<option value="date"    <?php selected( $r['monthly_by'] ?? 'date', 'date' ); ?>><?php esc_html_e( 'Same date each month', 'dish-events' ); ?></option>
								<option value="weekday" <?php selected( $r['monthly_by'] ?? 'date', 'weekday' ); ?>><?php esc_html_e( 'A specific weekday', 'dish-events' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="dish-recurrence-monthly-week-row" <?php echo ( $r_type !== 'monthly' || ( $r['monthly_by'] ?? 'date' ) !== 'weekday' ) ? 'hidden' : ''; ?>>
						<th><?php esc_html_e( 'On the', 'dish-events' ); ?></th>
						<td>
							<select name="dish_recurrence[monthly_week]" id="dish_recurrence_monthly_week" style="margin-right:6px">
								<option value="1"  <?php selected( (int) ( $r['monthly_week'] ?? 1 ), 1 );  ?>><?php esc_html_e( '1st', 'dish-events' ); ?></option>
								<option value="2"  <?php selected( (int) ( $r['monthly_week'] ?? 1 ), 2 );  ?>><?php esc_html_e( '2nd', 'dish-events' ); ?></option>
								<option value="3"  <?php selected( (int) ( $r['monthly_week'] ?? 1 ), 3 );  ?>><?php esc_html_e( '3rd', 'dish-events' ); ?></option>
								<option value="4"  <?php selected( (int) ( $r['monthly_week'] ?? 1 ), 4 );  ?>><?php esc_html_e( '4th', 'dish-events' ); ?></option>
								<option value="-1" <?php selected( (int) ( $r['monthly_week'] ?? 1 ), -1 ); ?>><?php esc_html_e( 'Last', 'dish-events' ); ?></option>
							</select>
							<select name="dish_recurrence[monthly_day]" id="dish_recurrence_monthly_day" style="margin-right:6px">
								<?php
								$saved_mday = $r['monthly_day'] ?? 'SA';
								$mday_map   = [
									'MO' => __( 'Monday',    'dish-events' ),
									'TU' => __( 'Tuesday',   'dish-events' ),
									'WE' => __( 'Wednesday', 'dish-events' ),
									'TH' => __( 'Thursday',  'dish-events' ),
									'FR' => __( 'Friday',    'dish-events' ),
									'SA' => __( 'Saturday',  'dish-events' ),
									'SU' => __( 'Sunday',    'dish-events' ),
								];
								foreach ( $mday_map as $val => $label ) :
									printf(
										'<option value="%s"%s>%s</option>',
										esc_attr( $val ),
										selected( $saved_mday, $val, false ),
										esc_html( $label )
									);
								endforeach;
								?>
							</select>
							<span class="description"><?php esc_html_e( 'of the month', 'dish-events' ); ?></span>
						</td>
					</tr>
					<tr>
						<th><label for="dish_recurrence_ends"><?php esc_html_e( 'Ends', 'dish-events' ); ?></label></th>
						<td>
							<select id="dish_recurrence_ends" name="dish_recurrence[ends]">
								<option value="count" <?php selected( $r['ends'] ?? 'count', 'count' ); ?>><?php esc_html_e( 'After N occurrences', 'dish-events' ); ?></option>
								<option value="date"  <?php selected( $r['ends'] ?? '', 'date' ); ?>><?php esc_html_e( 'On date', 'dish-events' ); ?></option>
							</select>
						</td>
					</tr>
					<tr id="dish-recurrence-count-row">
						<th><label for="dish_recurrence_count"><?php esc_html_e( 'Occurrences', 'dish-events' ); ?></label></th>
						<td>
							<input type="number" id="dish_recurrence_count" name="dish_recurrence[count]"
								value="<?php echo esc_attr( (string) ( $r['count'] ?? 8 ) ); ?>"
								min="1" max="365" class="small-text">
						</td>
					</tr>
					<tr id="dish-recurrence-end-date-row" hidden>
						<th><label for="dish_recurrence_end_date"><?php esc_html_e( 'End date', 'dish-events' ); ?></label></th>
						<td>
							<input type="date" id="dish_recurrence_end_date" name="dish_recurrence[end_date]"
								value="<?php echo esc_attr( $r['end_date'] ?? '' ); ?>">
						</td>
					</tr>
				</table>
			</div>

		<?php if ( $is_rec_parent ) : ?>
		<div style="margin-top:12px;padding:10px 12px;background:#f0f6ff;border-left:3px solid #2271b1;border-radius:2px">
			<label style="display:flex;align-items:center;gap:8px;cursor:pointer">
				<input type="checkbox" name="dish_apply_to_series" value="1">
				<span style="font-weight:600"><?php
					/* translators: %d: number of child instances */
					printf( esc_html__( 'Apply changes to all %d instances in this series', 'dish-events' ), count( $child_ids ) );
				?></span>
			</label>
			<p style="margin:4px 0 0 24px;color:#646970;font-size:12px"><?php esc_html_e( 'Propagates title, content, chefs, ticket type, and all settings to every child class. Dates are preserved.', 'dish-events' ); ?></p>
		</div>
		<?php endif; ?>
		</div><!-- .dish-metabox__panel -->
		<?php
	}

	/**
	 * Whether the "apply to series" checkbox was submitted.
	 *
	 * @return bool
	 */
	public function should_apply_to_series(): bool {
		return ! empty( $_POST['dish_apply_to_series'] );
	}

	/**
	 * Save Date & Time fields.
	 *
	 * @param int $post_id
	 */
	public function save( int $post_id ): void {
		$start = $this->local_to_epoch( sanitize_text_field( wp_unslash( $_POST['dish_start_datetime'] ?? '' ) ) );
		$end   = $this->local_to_epoch( sanitize_text_field( wp_unslash( $_POST['dish_end_datetime']   ?? '' ) ) );

		update_post_meta( $post_id, 'dish_start_datetime', $start );
		update_post_meta( $post_id, 'dish_end_datetime', $end );

		$rec = wp_unslash( $_POST['dish_recurrence'] ?? [] );
		$this->save_recurrence( $post_id, is_array( $rec ) ? $rec : [], $start, $end );
	}
	// -------------------------------------------------------------------------

	/**
	 * Validate, build, and save the recurrence JSON meta.
	 * Triggers generation via RecurrenceManager on shutdown.
	 *
	 * @param int                 $post_id
	 * @param array<string,mixed> $raw
	 * @param int                 $start UTC timestamp.
	 * @param int                 $end   UTC timestamp.
	 */
	private function save_recurrence( int $post_id, array $raw, int $start, int $end ): void {
		$type = sanitize_key( $raw['type'] ?? 'none' );

		if ( $type === 'none' ) {
			delete_post_meta( $post_id, 'dish_recurrence' );
			delete_post_meta( $post_id, 'dish_recurrence_parent_id' );
			return;
		}

		$allowed_types = [ 'weekly', 'monthly' ];
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return;
		}

		$interval = max( 1, absint( $raw['interval'] ?? 1 ) );
		$ends     = sanitize_key( $raw['ends'] ?? 'count' );

		$days = [];
		if ( $type === 'weekly' && ! empty( $raw['days'] ) && is_array( $raw['days'] ) ) {
			$allowed_days = [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ];
			$days         = array_values(
				array_intersect( array_map( 'strtoupper', $raw['days'] ), $allowed_days )
			);
		}

		$rule = [
			'type'     => $type,
			'interval' => $interval,
			'days'     => $days,
			'ends'     => $ends,
		];

		if ( $type === 'monthly' ) {
			$monthly_by   = sanitize_key( $raw['monthly_by'] ?? 'date' );
			$rule['monthly_by'] = in_array( $monthly_by, [ 'date', 'weekday' ], true ) ? $monthly_by : 'date';
			if ( $rule['monthly_by'] === 'weekday' ) {
				$week_val = (int) ( $raw['monthly_week'] ?? 1 );
				$rule['monthly_week'] = in_array( $week_val, [ 1, 2, 3, 4, -1 ], true ) ? $week_val : 1;
				$allowed_mdays    = [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ];
				$raw_mday         = strtoupper( sanitize_text_field( $raw['monthly_day'] ?? 'SA' ) );
				$rule['monthly_day'] = in_array( $raw_mday, $allowed_mdays, true ) ? $raw_mday : 'SA';
			}
		}

		if ( $ends === 'count' ) {
			$rule['count'] = max( 1, absint( $raw['count'] ?? 8 ) );
		} else {
			$rule['end_date'] = sanitize_text_field( $raw['end_date'] ?? '' );
		}

		// Preserve any previously generated child IDs.
		$existing         = json_decode( (string) get_post_meta( $post_id, 'dish_recurrence', true ), true );
		$rule['child_ids'] = $existing['child_ids'] ?? [];

		update_post_meta( $post_id, 'dish_recurrence', wp_json_encode( $rule ) );

		// Generate children after save_post completes (avoids recursive hook firing).
		add_action( 'shutdown', function () use ( $post_id, $rule, $start, $end ): void {
			( new \Dish\Events\Recurrence\RecurrenceManager() )->generate( $post_id, $rule, $start, $end );
		} );
	}
}
