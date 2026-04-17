<?php
/**
 * Recurrence engine for dish_class posts.
 *
 * Responsibilities
 * ----------------
 *  generate()       — Create child posts for a new or changed recurrence rule.
 *                     Existing children outside the new rule are trashed.
 *  update_series()  — Propagate changes from the parent to all children
 *                     (price, capacity, chef assignments, etc.), leaving
 *                     child-specific dates untouched.
 *  delete_series()  — Trash all children when the parent is deleted/trashed.
 *
 * Storage contract
 * ----------------
 *  Parent  dish_recurrence         → JSON rule (type/interval/days/ends/count/end_date/child_ids)
 *  Child   dish_recurrence_parent_id → (int) parent post ID
 *
 * Datetime storage
 * ----------------
 *  All epoch timestamps are UTC integers (same as ClassMetaBox).
 *
 * Limits
 * ------
 *  MAX_OCCURRENCES caps generation at 365 children to prevent runaway loops.
 *
 * @package Dish\Events\Recurrence
 */

declare( strict_types=1 );

namespace Dish\Events\Recurrence;

/**
 * Class RecurrenceManager
 */
final class RecurrenceManager {

	private const MAX_OCCURRENCES = 365;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generate or regenerate child posts for a parent class.
	 *
	 * Called on shutdown after save_post so the parent meta is fully committed.
	 *
	 * @param int                  $parent_id Parent post ID.
	 * @param array<string,mixed>  $rule      Parsed recurrence rule.
	 * @param int                  $start     Parent start timestamp (UTC).
	 * @param int                  $end       Parent end timestamp (UTC).
	 */
	public function generate( int $parent_id, array $rule, int $start, int $end ): void {
		if ( $start <= 0 ) {
			return;
		}

		$duration    = max( 0, $end - $start );
		$occurrences = $this->build_occurrences( $rule, $start );

		if ( empty( $occurrences ) ) {
			return;
		}

		// Load existing children so we can reuse or trash stale ones.
		$stored_rule = json_decode( (string) get_post_meta( $parent_id, 'dish_recurrence', true ), true );
		$existing_ids = is_array( $stored_rule['child_ids'] ?? null ) ? array_map( 'intval', $stored_rule['child_ids'] ) : [];

		// Match occurrence dates to existing children by index to preserve IDs
		// where possible (avoids breaking any booking references).
		$kept_ids   = [];
		$needed     = count( $occurrences );
		$reuse_pool = array_values( $existing_ids );

		// Meta keys that must always stay in sync with the parent (dates excluded).
		$sync_keys = [ 'dish_template_id', 'dish_format_id', 'dish_ticket_type_id', 'dish_chef_ids' ];

		foreach ( $occurrences as $i => $occ_start ) {
			$occ_end = $occ_start + $duration;

			if ( isset( $reuse_pool[ $i ] ) ) {
				// Update existing child in place.
				$child_id = $reuse_pool[ $i ];
				update_post_meta( $child_id, 'dish_start_datetime', $occ_start );
				update_post_meta( $child_id, 'dish_end_datetime', $occ_end );
				// Sync essential parent meta so changes made after initial generation propagate.
				foreach ( $sync_keys as $sync_key ) {
					$val = get_post_meta( $parent_id, $sync_key, true );
					if ( $val !== '' && $val !== false ) {
						update_post_meta( $child_id, $sync_key, $val );
					}
				}
				$kept_ids[] = $child_id;
			} else {
				// Create a new child.
				$child_id = $this->create_child( $parent_id, $occ_start, $occ_end );
				if ( $child_id ) {
					$kept_ids[] = $child_id;
				}
			}
		}

		// Trash any existing children that are beyond the new occurrence count.
		$stale_ids = array_slice( $reuse_pool, $needed );
		foreach ( $stale_ids as $stale_id ) {
			wp_trash_post( $stale_id );
		}

		// Persist the updated child_ids list on the parent.
		if ( is_array( $stored_rule ) ) {
			$stored_rule['child_ids'] = $kept_ids;
			update_post_meta( $parent_id, 'dish_recurrence', wp_json_encode( $stored_rule ) );
		}
	}

	/**
	 * Propagate non-date meta changes from parent to all children.
	 *
	 * Called when a user explicitly opts to update the full series.
	 * Child-specific start/end datetimes are preserved.
	 *
	 * @param int $parent_id Parent post ID.
	 */
	public function update_series( int $parent_id ): void {
		$rule     = json_decode( (string) get_post_meta( $parent_id, 'dish_recurrence', true ), true );
		$child_ids = is_array( $rule['child_ids'] ?? null ) ? array_map( 'intval', $rule['child_ids'] ) : [];

		if ( empty( $child_ids ) ) {
			return;
		}

		// Meta to copy wholesale from parent → child (dates excluded).
		$copy_keys = [
			'dish_template_id',
			'dish_ticket_type_id',
			'dish_booking_opens',
			'dish_chef_ids',
			'dish_class_type',
			'dish_min_attendees',
			'dish_max_attendees',
			'dish_checkout_override',
			'dish_checkout_fields_json',
			'dish_social_override',
			'dish_share_title',
			'dish_share_description',
			'dish_share_image_id',
			'dish_featured',
			'dish_external_booking_url',
			'dish_show_qr',
			'dish_template_name',
		];

		$parent = get_post( $parent_id );

		foreach ( $child_ids as $child_id ) {
			// Propagate post fields.
			if ( $parent instanceof \WP_Post ) {
				wp_update_post( [
					'ID'           => $child_id,
					'post_title'   => $parent->post_title,
					'post_content' => $parent->post_content,
					'post_excerpt' => $parent->post_excerpt,
				] );
			}
			// Propagate meta.
			foreach ( $copy_keys as $key ) {
				$value = get_post_meta( $parent_id, $key, true );
				update_post_meta( $child_id, $key, $value );
			}
		}
	}

	/**
	 * Trash all children when the parent is deleted or trashed.
	 *
	 * @param int $parent_id Parent post ID.
	 */
	public function delete_series( int $parent_id ): void {
		$rule      = json_decode( (string) get_post_meta( $parent_id, 'dish_recurrence', true ), true );
		$child_ids = is_array( $rule['child_ids'] ?? null ) ? array_map( 'intval', $rule['child_ids'] ) : [];

		foreach ( $child_ids as $child_id ) {
			wp_trash_post( $child_id );
		}
	}

	// -------------------------------------------------------------------------
	// Occurrence calculation
	// -------------------------------------------------------------------------

	/**
	 * Build a list of UTC start timestamps for all occurrences in the rule.
	 * The first occurrence (the parent itself) is excluded — only children.
	 *
	 * @param  array<string,mixed> $rule
	 * @param  int                 $parent_start UTC epoch of the parent start.
	 * @return array<int>          Ordered list of UTC start epochs for children.
	 */
	private function build_occurrences( array $rule, int $parent_start ): array {
		$type     = (string) ( $rule['type'] ?? 'none' );
		$interval = max( 1, (int) ( $rule['interval'] ?? 1 ) );
		$ends     = (string) ( $rule['ends'] ?? 'count' );

		$tz = new \DateTimeZone( wp_timezone_string() );
		$dt = ( new \DateTimeImmutable( '@' . $parent_start ) )->setTimezone( $tz );

		// Determine the end condition.
		$max_count = self::MAX_OCCURRENCES;
		$end_limit = PHP_INT_MAX;

		if ( $ends === 'count' ) {
			// count includes the parent, so children = count - 1.
			$max_count = min( max( 0, (int) ( $rule['count'] ?? 8 ) - 1 ), self::MAX_OCCURRENCES );
		} elseif ( $ends === 'date' && ! empty( $rule['end_date'] ) ) {
			$end_dt    = \DateTimeImmutable::createFromFormat( 'Y-m-d', $rule['end_date'], $tz );
			$end_limit = $end_dt ? (int) $end_dt->setTime( 23, 59, 59 )->format( 'U' ) : PHP_INT_MAX;
		}

		$occurrences = [];

		switch ( $type ) {
			case 'daily':
				$occurrences = $this->generate_interval_series( $dt, $interval, 'days', $max_count, $end_limit );
				break;

			case 'weekly':
				$days = $rule['days'] ?? [];
				if ( empty( $days ) ) {
					// No days selected — fall back to same-weekday-as-parent.
					$occurrences = $this->generate_interval_series( $dt, $interval * 7, 'days', $max_count, $end_limit );
				} else {
					$occurrences = $this->generate_weekly_series( $dt, $interval, $days, $max_count, $end_limit );
				}
				break;

			case 'monthly':
				if ( ( $rule['monthly_by'] ?? 'date' ) === 'weekday' ) {
					$week_pos = (int) ( $rule['monthly_week'] ?? 1 );
					$day_abbr = strtoupper( (string) ( $rule['monthly_day'] ?? 'SA' ) );
					$occurrences = $this->generate_monthly_weekday_series( $dt, $interval, $week_pos, $day_abbr, $max_count, $end_limit );
				} else {
					$occurrences = $this->generate_interval_series( $dt, $interval, 'months', $max_count, $end_limit );
				}
				break;
		}

		return $occurrences;
	}

	/**
	 * Simple uniform-interval series (daily, monthly).
	 *
	 * @param  \DateTimeImmutable $start
	 * @param  int                $interval
	 * @param  string             $unit     'days' or 'months'
	 * @param  int                $max_count
	 * @param  int                $end_limit UTC epoch
	 * @return array<int>
	 */
	private function generate_interval_series(
		\DateTimeImmutable $start,
		int $interval,
		string $unit,
		int $max_count,
		int $end_limit
	): array {
		$occurrences = [];
		$current     = $start;

		for ( $i = 0; $i < $max_count; $i++ ) {
			$current = $current->modify( "+{$interval} {$unit}" );
			$epoch   = (int) $current->format( 'U' );

			if ( $epoch > $end_limit ) {
				break;
			}

			$occurrences[] = $epoch;
		}

		return $occurrences;
	}

	/**
	 * Weekly series with specific day-of-week selection.
	 *
	 * @param  \DateTimeImmutable $start     Parent start in site timezone.
	 * @param  int                $interval  Every N weeks.
	 * @param  array<string>      $days      e.g. ['MO','WE','FR']
	 * @param  int                $max_count
	 * @param  int                $end_limit UTC epoch
	 * @return array<int>
	 */
	private function generate_weekly_series(
		\DateTimeImmutable $start,
		int $interval,
		array $days,
		int $max_count,
		int $end_limit
	): array {
		$day_map = [ 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 ];

		$target_days = [];
		foreach ( $days as $abbr ) {
			if ( isset( $day_map[ strtoupper( $abbr ) ] ) ) {
				$target_days[] = $day_map[ strtoupper( $abbr ) ];
			}
		}
		sort( $target_days );

		if ( empty( $target_days ) ) {
			return [];
		}

		$occurrences     = [];
		$parent_epoch    = (int) $start->format( 'U' );
		$time_of_day     = $start->format( 'H:i:s' );

		// Start scanning from the beginning of the parent's ISO week.
		$week_start = $start->modify( 'monday this week' );
		$checked    = 0;

		while ( count( $occurrences ) < $max_count && $checked < self::MAX_OCCURRENCES * 7 ) {
			foreach ( $target_days as $dow ) {
				// DateTimeImmutable: 0=Sunday, 1=Monday … 6=Saturday
				$day_name  = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ][ $dow ];
				$candidate = \DateTimeImmutable::createFromFormat(
					'Y-m-d H:i:s',
					$week_start->modify( $day_name )->format( 'Y-m-d' ) . ' ' . $time_of_day,
					$start->getTimezone()
				);

				if ( ! $candidate ) {
					continue;
				}

				$epoch = (int) $candidate->format( 'U' );

				// Skip the parent occurrence itself and any date in the past of it.
				if ( $epoch <= $parent_epoch ) {
					continue;
				}

				if ( $epoch > $end_limit ) {
					return $occurrences;
				}

				$occurrences[] = $epoch;

				if ( count( $occurrences ) >= $max_count ) {
					return $occurrences;
				}
			}

			$week_start = $week_start->modify( '+' . $interval . ' weeks' );
			$checked++;
		}

		return $occurrences;
	}

	/**
	 * Monthly series anchored to the Nth (or last) weekday of each month.
	 *
	 * e.g. "2nd Saturday of every month"
	 *
	 * @param  \DateTimeImmutable $start
	 * @param  int                $interval  Every N months.
	 * @param  int                $week_pos  1-4 or -1 for last.
	 * @param  string             $day_abbr  'MO','TU','WE','TH','FR','SA','SU'
	 * @param  int                $max_count
	 * @param  int                $end_limit UTC epoch
	 * @return array<int>
	 */
	private function generate_monthly_weekday_series(
		\DateTimeImmutable $start,
		int $interval,
		int $week_pos,
		string $day_abbr,
		int $max_count,
		int $end_limit
	): array {
		$day_names = [
			'SU' => 'Sunday', 'MO' => 'Monday', 'TU' => 'Tuesday',
			'WE' => 'Wednesday', 'TH' => 'Thursday', 'FR' => 'Friday', 'SA' => 'Saturday',
		];
		$day_name    = $day_names[ $day_abbr ] ?? 'Saturday';
		$time_of_day = $start->format( 'H:i:s' );
		$tz          = $start->getTimezone();
		$parent_epoch = (int) $start->format( 'U' );

		$occurrences   = [];
		$current_month = $start->modify( 'first day of this month' );

		for ( $i = 0; count( $occurrences ) < $max_count && $i < self::MAX_OCCURRENCES * 12; $i++ ) {
			$candidate = $this->nth_weekday_of_month( $current_month, $week_pos, $day_name, $time_of_day, $tz );

			if ( $candidate ) {
				$epoch = (int) $candidate->format( 'U' );
				if ( $epoch > $parent_epoch ) {
					if ( $epoch > $end_limit ) {
						break;
					}
					$occurrences[] = $epoch;
				}
			}

			$current_month = $current_month->modify( "+{$interval} months" );
		}

		return $occurrences;
	}

	/**
	 * Return the Nth (or last) occurrence of a weekday in a given month.
	 *
	 * @param  \DateTimeImmutable $month_start  First day of the month, any time.
	 * @param  int                $position     1-4 or -1 for last.
	 * @param  string             $day_name     Full English name, e.g. 'Saturday'.
	 * @param  string             $time_of_day  'H:i:s' string.
	 * @param  \DateTimeZone      $tz
	 * @return \DateTimeImmutable|null
	 */
	private function nth_weekday_of_month(
		\DateTimeImmutable $month_start,
		int $position,
		string $day_name,
		string $time_of_day,
		\DateTimeZone $tz
	): ?\DateTimeImmutable {
		$ordinals = [ 1 => 'first', 2 => 'second', 3 => 'third', 4 => 'fourth' ];

		$modifier = $position === -1
			? "last {$day_name} of this month"
			: ( ( $ordinals[ $position ] ?? 'first' ) . " {$day_name} of this month" );

		$date_str = $month_start->modify( $modifier )->format( 'Y-m-d' );
		$result   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $date_str . ' ' . $time_of_day, $tz );

		return $result ?: null;
	}

	// -------------------------------------------------------------------------
	// Child post creation
	// -------------------------------------------------------------------------

	/**
	 * Create a single child post inheriting all meta from the parent.
	 *
	 * @param  int $parent_id  Parent post ID.
	 * @param  int $start      Child start timestamp (UTC).
	 * @param  int $end        Child end timestamp (UTC).
	 * @return int|false       New post ID or false on failure.
	 */
	private function create_child( int $parent_id, int $start, int $end ): int|false {
		$parent = get_post( $parent_id );
		if ( ! $parent instanceof \WP_Post ) {
			return false;
		}

		$child_id = wp_insert_post( [
			'post_title'   => $parent->post_title,
			'post_content' => $parent->post_content,
			'post_excerpt' => $parent->post_excerpt,
			'post_status'  => $parent->post_status,
			'post_type'    => 'dish_class',
			'post_author'  => $parent->post_author,
		] );

		if ( is_wp_error( $child_id ) || $child_id === 0 ) {
			return false;
		}

		// Copy all parent meta except recurrence fields, instance-specific flags,
		// and datetime keys — datetimes are set explicitly below so the copy loop
		// cannot accidentally inherit the parent's dates.
		// dish_is_private is excluded so marking the parent private does not
		// silently make all children non-bookable when the series is regenerated.
		$skip = [ 'dish_recurrence', 'dish_recurrence_parent_id', 'dish_is_private', 'dish_start_datetime', 'dish_end_datetime' ];
		foreach ( get_post_meta( $parent_id ) as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			// Use update_post_meta (not add_post_meta) so that if a save hook fires
			// concurrently on the child, the second write overwrites rather than
			// stacking a duplicate row. For meta keys that genuinely allow multiple
			// values this plugin does not use any — all dish_* keys are single-value.
			update_post_meta( $child_id, $key, maybe_unserialize( $values[0] ) );
		}

		// Override with child-specific datetime.
		update_post_meta( $child_id, 'dish_start_datetime', $start );
		update_post_meta( $child_id, 'dish_end_datetime', $end );
		update_post_meta( $child_id, 'dish_recurrence_parent_id', $parent_id );

		// Copy taxonomy terms.
		foreach ( get_object_taxonomies( 'dish_class' ) as $taxonomy ) {
			$terms = wp_get_object_terms( $parent_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $child_id, $terms, $taxonomy );
			}
		}

		// Copy featured image.
		$thumb = get_post_thumbnail_id( $parent_id );
		if ( $thumb ) {
			set_post_thumbnail( $child_id, $thumb );
		}

		return $child_id;
	}
}
