<?php
/**
 * WP Dashboard widgets for the Dish Events plugin.
 *
 * Registers four at-a-glance widgets on the WordPress dashboard:
 *   1. Upcoming Classes     — next 14 days with booked count.
 *   2. Recent Bookings      — latest 10 booking activity feed.
 *   3. Capacity Overview    — total seats sold vs available across upcoming classes.
 *   4. Classes by Format    — upcoming class count broken down per format.
 *
 * All data is pulled through the existing stateless repository layer.
 * No business logic lives here — only presentation.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Data\BookingRepository;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Data\FormatRepository;
use Dish\Events\Data\ReportsRepository;
use Dish\Events\Data\TicketTypeRepository;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class DashboardWidgets
 */
final class DashboardWidgets {

	/**
	 * Register all four dashboard widgets.
	 * Hooked to wp_dashboard_setup.
	 */
	public function register(): void {
		wp_add_dashboard_widget(
			'dish_widget_upcoming_classes',
			__( '📅 Upcoming Classes', 'dish-events' ),
			[ $this, 'render_upcoming_classes' ]
		);

		wp_add_dashboard_widget(
			'dish_widget_recent_bookings',
			__( '🎟️ Recent Bookings', 'dish-events' ),
			[ $this, 'render_recent_bookings' ]
		);

		wp_add_dashboard_widget(
			'dish_widget_capacity_overview',
			__( '📊 Capacity Overview', 'dish-events' ),
			[ $this, 'render_capacity_overview' ]
		);

		wp_add_dashboard_widget(
			'dish_widget_classes_by_format',
			__( '🗂️ Classes by Format', 'dish-events' ),
			[ $this, 'render_classes_by_format' ]
		);

		wp_add_dashboard_widget(
			'dish_widget_revenue_snapshot',
			__( '💰 Monthly Revenue Snapshot', 'dish-events' ),
			[ $this, 'render_revenue_snapshot' ]
		);
	}

	// -------------------------------------------------------------------------
	// Widget 1 — Upcoming Classes
	// -------------------------------------------------------------------------

	/**
	 * Render the Upcoming Classes widget.
	 *
	 * Shows all published dish_class instances starting within the next 14 days,
	 * ordered ascending. Each row shows: template name (linked to edit screen),
	 * date/time, format label, and confirmed booking count.
	 */
	public function render_upcoming_classes(): void {
		$now      = time();
		$cutoff   = $now + ( 14 * DAY_IN_SECONDS );
		$classes  = ClassRepository::query( [
			'start_after'  => $now,
			'start_before' => $cutoff,
			'order'        => 'ASC',
			'limit'        => 50,
		] );

		if ( empty( $classes ) ) {
			echo '<p>' . esc_html__( 'No classes scheduled in the next 14 days.', 'dish-events' ) . '</p>';
			return;
		}

		// Batch-load booked counts to avoid N+1.
		$class_ids    = array_map( fn( $p ) => $p->ID, $classes );
		$booked_map   = ClassRepository::get_booked_counts_batch( $class_ids );

		// Pre-load template and format data indexed by ID.
		$template_cache = [];
		$format_cache   = [];

		echo '<table class="widefat striped dish-dashboard-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Class', 'dish-events' )   . '</th>';
		echo '<th>' . esc_html__( 'Date', 'dish-events' )    . '</th>';
		echo '<th>' . esc_html__( 'Format', 'dish-events' )  . '</th>';
		echo '<th>' . esc_html__( 'Booked', 'dish-events' )  . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $classes as $class ) {
			$start       = (int) get_post_meta( $class->ID, 'dish_start_datetime', true );
			$template_id = (int) get_post_meta( $class->ID, 'dish_template_id', true );

			// Template name (cached per-widget render).
			if ( ! isset( $template_cache[ $template_id ] ) ) {
				$template_cache[ $template_id ] = get_post( $template_id );
			}
			$template = $template_cache[ $template_id ];
			$name     = $template ? $template->post_title : __( '(No Template)', 'dish-events' );
			$edit_url = get_edit_post_link( $class->ID );

			// Format label (cached).
			$format_id = $template ? (int) get_post_meta( $template->ID, 'dish_format_id', true ) : 0;
			if ( $format_id && ! isset( $format_cache[ $format_id ] ) ) {
				$format_cache[ $format_id ] = get_post( $format_id );
			}
			$format_label = ( $format_id && isset( $format_cache[ $format_id ] ) && $format_cache[ $format_id ] )
				? $format_cache[ $format_id ]->post_title
				: '—';

			$booked       = $booked_map[ $class->ID ] ?? 0;
			$date_display = $start ? wp_date( 'D j M, g:ia', $start ) : '—';

			echo '<tr>';
			echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html( $name ) . '</a></td>';
			echo '<td>' . esc_html( $date_display ) . '</td>';
			echo '<td>' . esc_html( $format_label ) . '</td>';
			echo '<td><strong>' . esc_html( (string) $booked ) . '</strong></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$all_url = admin_url( 'edit.php?post_type=dish_class' );
		echo '<p class="dish-dashboard-footer"><a href="' . esc_url( $all_url ) . '">' . esc_html__( 'View all classes →', 'dish-events' ) . '</a></p>';
	}

	// -------------------------------------------------------------------------
	// Widget 2 — Recent Bookings
	// -------------------------------------------------------------------------

	/**
	 * Render the Recent Bookings activity feed.
	 *
	 * Shows the 10 most recently created dish_booking posts across all classes,
	 * regardless of status. Each row shows: customer name (linked to booking edit
	 * screen), class name, ticket type name, and date booked.
	 */
	public function render_recent_bookings(): void {
		$bookings = get_posts( [
			'post_type'      => 'dish_booking',
			'post_status'    => [ 'dish_pending', 'dish_completed', 'dish_failed', 'dish_refunded', 'dish_cancelled' ],
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] );

		if ( empty( $bookings ) ) {
			echo '<p>' . esc_html__( 'No bookings yet.', 'dish-events' ) . '</p>';
			return;
		}

		// Status → label map.
		$status_labels = [
			'dish_pending'   => __( 'Pending',   'dish-events' ),
			'dish_completed' => __( 'Confirmed', 'dish-events' ),
			'dish_failed'    => __( 'Failed',    'dish-events' ),
			'dish_refunded'  => __( 'Refunded',  'dish-events' ),
			'dish_cancelled' => __( 'Cancelled', 'dish-events' ),
		];

		$status_classes = [
			'dish_pending'   => 'dish-status-pending',
			'dish_completed' => 'dish-status-completed',
			'dish_failed'    => 'dish-status-failed',
			'dish_refunded'  => 'dish-status-refunded',
			'dish_cancelled' => 'dish-status-cancelled',
		];

		$template_cache = [];

		echo '<table class="widefat striped dish-dashboard-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Customer', 'dish-events' )    . '</th>';
		echo '<th>' . esc_html__( 'Class', 'dish-events' )       . '</th>';
		echo '<th>' . esc_html__( 'Status', 'dish-events' )      . '</th>';
		echo '<th>' . esc_html__( 'Date', 'dish-events' )        . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $bookings as $booking ) {
			$customer_name  = get_post_meta( $booking->ID, 'dish_customer_name', true ) ?: __( '(Guest)', 'dish-events' );
			$class_id       = (int) get_post_meta( $booking->ID, 'dish_class_id', true );
			$ticket_type_id = (int) get_post_meta( $booking->ID, 'dish_ticket_type_id', true );
			$ticket_qty     = (int) get_post_meta( $booking->ID, 'dish_ticket_qty', true );
			$edit_url       = get_edit_post_link( $booking->ID );
			$status         = $booking->post_status;

			// Resolve class → template name.
			$class_label = '—';
			if ( $class_id ) {
				$template_id = (int) get_post_meta( $class_id, 'dish_template_id', true );
				if ( $template_id ) {
					if ( ! isset( $template_cache[ $template_id ] ) ) {
						$template_cache[ $template_id ] = get_post( $template_id );
					}
					if ( $template_cache[ $template_id ] ) {
						// Include the class date for context.
						$start       = (int) get_post_meta( $class_id, 'dish_start_datetime', true );
						$date_short  = $start ? wp_date( 'j M', $start ) : '';
						$class_label = $template_cache[ $template_id ]->post_title;
						if ( $date_short ) {
							$class_label .= ' <span class="dish-date-hint">(' . esc_html( $date_short ) . ')</span>';
						}
					}
				}
			}

			// Ticket type name + qty.
			$ticket_label = '—';
			if ( $ticket_type_id ) {
				$tt = TicketTypeRepository::get( $ticket_type_id );
				if ( $tt ) {
					$ticket_label = esc_html( $tt->name );
					if ( $ticket_qty > 1 ) {
						$ticket_label .= ' ×' . $ticket_qty;
					}
				}
			}

			$status_label = $status_labels[ $status ] ?? $status;
			$status_class = $status_classes[ $status ] ?? '';
			$date_display = wp_date( 'j M Y', strtotime( $booking->post_date ) );

			echo '<tr>';
			echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html( $customer_name ) . '</a></td>';
			echo '<td>' . wp_kses( $class_label, [ 'span' => [ 'class' => [] ] ] ) . '</td>';
			echo '<td><span class="dish-status-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
			echo '<td>' . esc_html( $date_display ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		$all_url = admin_url( 'edit.php?post_type=dish_booking' );
		echo '<p class="dish-dashboard-footer"><a href="' . esc_url( $all_url ) . '">' . esc_html__( 'View all bookings →', 'dish-events' ) . '</a></p>';
	}

	// -------------------------------------------------------------------------
	// Widget 3 — Capacity Overview
	// -------------------------------------------------------------------------

	/**
	 * Render the Capacity Overview widget.
	 *
	 * For all upcoming published classes, totals up:
	 *   - Confirmed booked seats (from BookingRepository batch count).
	 *   - Total available seats (sum of dish_ticket_types.capacity per format,
	 *     resolved via the template's dish_format_id).
	 *
	 * Displays a summary row and a per-class breakdown table.
	 */
	public function render_capacity_overview(): void {
		$upcoming = ClassRepository::query( [
			'start_after'  => time(),
			'start_before' => time() + ( 14 * DAY_IN_SECONDS ),
			'order'        => 'ASC',
			'limit'        => 50,
		] );

		if ( empty( $upcoming ) ) {
			echo '<p>' . esc_html__( 'No classes scheduled in the next 14 days.', 'dish-events' ) . '</p>';
			return;
		}

		$class_ids  = array_map( fn( $p ) => $p->ID, $upcoming );
		$booked_map = ClassRepository::get_booked_counts_batch( $class_ids );

		// Pre-load ticket type capacity per format (sum of all active ticket types).
		$format_capacity_cache  = [];
		$template_cache         = [];

		$total_booked   = 0;
		$total_capacity = 0;

		$rows = [];

		foreach ( $upcoming as $class ) {
			$template_id = (int) get_post_meta( $class->ID, 'dish_template_id', true );
			$start       = (int) get_post_meta( $class->ID, 'dish_start_datetime', true );
			$booked      = $booked_map[ $class->ID ] ?? 0;

			if ( ! isset( $template_cache[ $template_id ] ) ) {
				$template_cache[ $template_id ] = get_post( $template_id );
			}
			$template     = $template_cache[ $template_id ];
			$name         = $template ? $template->post_title : __( '(No Template)', 'dish-events' );
			$format_id    = $template ? (int) get_post_meta( $template->ID, 'dish_format_id', true ) : 0;

			// Capacity = sum of all active ticket types for this format.
			if ( $format_id && ! isset( $format_capacity_cache[ $format_id ] ) ) {
				$ticket_types = TicketTypeRepository::get_by_format( $format_id );
				$cap          = 0;
				foreach ( $ticket_types as $tt ) {
					$cap += (int) $tt->capacity;
				}
				$format_capacity_cache[ $format_id ] = $cap;
			}
			$capacity = $format_id ? ( $format_capacity_cache[ $format_id ] ?? 0 ) : 0;

			$total_booked   += $booked;
			$total_capacity += $capacity;

			$rows[] = [
				'name'     => $name,
				'date'     => $start ? wp_date( 'j M', $start ) : '—',
				'booked'   => $booked,
				'capacity' => $capacity,
				'edit_url' => get_edit_post_link( $class->ID ),
			];
		}

		// Summary strip.
		$remaining = max( 0, $total_capacity - $total_booked );
		echo '<div class="dish-capacity-summary">';
		echo '<span class="dish-cap-stat"><strong>' . esc_html( (string) $total_booked )   . '</strong> ' . esc_html__( 'seats booked', 'dish-events' )     . '</span>';
		echo '<span class="dish-cap-sep">·</span>';
		echo '<span class="dish-cap-stat"><strong>' . esc_html( (string) $remaining )      . '</strong> ' . esc_html__( 'remaining', 'dish-events' )         . '</span>';
		echo '<span class="dish-cap-sep">·</span>';
		echo '<span class="dish-cap-stat"><strong>' . esc_html( (string) count( $rows ) ) . '</strong> ' . esc_html__( 'upcoming classes', 'dish-events' )   . '</span>';
		echo '</div>';

		echo '<table class="widefat striped dish-dashboard-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Class', 'dish-events' )    . '</th>';
		echo '<th>' . esc_html__( 'Date', 'dish-events' )     . '</th>';
		echo '<th>' . esc_html__( 'Booked', 'dish-events' )   . '</th>';
		echo '<th>' . esc_html__( 'Capacity', 'dish-events' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$pct       = ( $row['capacity'] > 0 ) ? round( ( $row['booked'] / $row['capacity'] ) * 100 ) : 0;
			$bar_class = $pct >= 90 ? 'dish-bar-full' : ( $pct >= 60 ? 'dish-bar-mid' : 'dish-bar-low' );

			echo '<tr>';
			echo '<td><a href="' . esc_url( $row['edit_url'] ) . '">' . esc_html( $row['name'] ) . '</a></td>';
			echo '<td>' . esc_html( $row['date'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['booked'] ) . '</td>';
			echo '<td>';
			if ( $row['capacity'] > 0 ) {
				echo '<div class="dish-bar-wrap"><div class="dish-bar ' . esc_attr( $bar_class ) . '" style="width:' . esc_attr( (string) $pct ) . '%"></div></div>';
				echo '<small>' . esc_html( (string) $row['booked'] . '/' . $row['capacity'] ) . '</small>';
			} else {
				echo '—';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	// -------------------------------------------------------------------------
	// Widget 4 — Classes by Format
	// -------------------------------------------------------------------------

	/**
	 * Render the Classes by Format breakdown widget.
	 *
	 * Groups all upcoming published dish_class instances by their parent
	 * dish_format label and shows a count for each. Private formats are
	 * included (admin context).
	 */
	public function render_classes_by_format(): void {
		$upcoming = ClassRepository::query( [
			'start_after' => time(),
			'order'       => 'ASC',
			'limit'       => -1,
		] );

		if ( empty( $upcoming ) ) {
			echo '<p>' . esc_html__( 'No upcoming classes.', 'dish-events' ) . '</p>';
			return;
		}

		$formats         = FormatRepository::get_all_published();
		$format_names    = [];
		$format_colors   = [];

		foreach ( $formats as $f ) {
			$format_names[ $f->ID ]  = $f->post_title;
			$format_colors[ $f->ID ] = get_post_meta( $f->ID, 'dish_format_colour', true ) ?: '#cccccc';
		}

		$counts         = [];
		$template_cache = [];

		foreach ( $upcoming as $class ) {
			$template_id = (int) get_post_meta( $class->ID, 'dish_template_id', true );
			if ( ! isset( $template_cache[ $template_id ] ) ) {
				$template_cache[ $template_id ] = get_post( $template_id );
			}
			$template  = $template_cache[ $template_id ];
			$format_id = $template ? (int) get_post_meta( $template->ID, 'dish_format_id', true ) : 0;

			if ( ! isset( $counts[ $format_id ] ) ) {
				$counts[ $format_id ] = 0;
			}
			$counts[ $format_id ]++;
		}

		// Sort descending by count.
		arsort( $counts );

		$total = count( $upcoming );

		echo '<ul class="dish-format-breakdown">';

		foreach ( $counts as $format_id => $count ) {
			$label = $format_id && isset( $format_names[ $format_id ] )
				? $format_names[ $format_id ]
				: __( 'Uncategorised', 'dish-events' );
			$color = $format_id && isset( $format_colors[ $format_id ] )
				? $format_colors[ $format_id ]
				: '#cccccc';
			$pct   = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;

			echo '<li class="dish-format-row">';
			echo '<span class="dish-format-swatch" style="background:' . esc_attr( $color ) . '"></span>';
			echo '<span class="dish-format-label">' . esc_html( $label ) . '</span>';
			echo '<span class="dish-format-bar-wrap">';
			echo '<span class="dish-format-bar" style="width:' . esc_attr( (string) $pct ) . '%;background:' . esc_attr( $color ) . '"></span>';
			echo '</span>';
			echo '<span class="dish-format-count">' . esc_html( (string) $count ) . '</span>';
			echo '</li>';
		}

		echo '</ul>';

		$new_url = admin_url( 'post-new.php?post_type=dish_class' );
		echo '<p class="dish-dashboard-footer"><a href="' . esc_url( $new_url ) . '">' . esc_html__( 'Add new class →', 'dish-events' ) . '</a></p>';
	}

	// -------------------------------------------------------------------------
	// Widget 5 — Monthly Revenue Snapshot
	// -------------------------------------------------------------------------

	/**
	 * Render the Monthly Revenue Snapshot widget.
	 *
	 * Shows headline stats for the current calendar month plus a pure-CSS
	 * bar chart of revenue for the trailing 6 months (oldest left, newest right).
	 */
	public function render_revenue_snapshot(): void {
		$tz  = new \DateTimeZone( wp_timezone_string() );
		$now = new \DateTimeImmutable( 'now', $tz );

		// Current month bounds.
		$month_start = $now->format( 'Y-m-01' );
		$month_end   = $now->format( 'Y-m-t' );
		$current     = ReportsRepository::get_summary( $month_start, $month_end );

		// Trailing 6 months (current + 5 previous), oldest first.
		$months = [];
		for ( $i = 5; $i >= 0; $i-- ) {
			$dt       = $now->modify( "-{$i} months" );
			$stats    = ReportsRepository::get_summary( $dt->format( 'Y-m-01' ), $dt->format( 'Y-m-t' ) );
			$months[] = [
				'label'   => $dt->format( 'M' ),
				'revenue' => $stats['total_revenue'],
				'current' => ( $i === 0 ),
			];
		}

		$max_revenue = max( array_column( $months, 'revenue' ) );

		// Headline stats.
		echo '<div class="dish-rev-headline">';
		foreach ( [
			[ MoneyHelper::cents_to_display( $current['total_revenue'] ), __( 'Revenue this month', 'dish-events' ) ],
			[ (string) $current['total_bookings'],                         __( 'Bookings',           'dish-events' ) ],
			[ (string) $current['total_tickets'],                          __( 'Tickets sold',       'dish-events' ) ],
			[ MoneyHelper::cents_to_display( $current['avg_per_day'] ),    __( 'Avg / day',          'dish-events' ) ],
		] as [ $val, $lbl ] ) {
			echo '<div class="dish-rev-stat">';
			echo '<span class="dish-rev-value">' . esc_html( $val ) . '</span>';
			echo '<span class="dish-rev-label">' . esc_html( $lbl ) . '</span>';
			echo '</div>';
		}
		echo '</div>';

		// 6-month bar chart.
		echo '<div class="dish-rev-chart">';
		foreach ( $months as $month ) {
			$pct = $max_revenue > 0 ? round( ( $month['revenue'] / $max_revenue ) * 100 ) : 0;
			$tip = $month['label'] . ': ' . MoneyHelper::cents_to_display( $month['revenue'] );
			$cls = $month['current'] ? ' dish-bar-current' : '';

			echo '<div class="dish-rev-col' . esc_attr( $cls ) . '" title="' . esc_attr( $tip ) . '">';
			echo '<div class="dish-rev-bar-wrap"><div class="dish-rev-bar" style="height:' . esc_attr( (string) $pct ) . '%"></div></div>';
			echo '<span class="dish-rev-month">' . esc_html( $month['label'] ) . '</span>';
			echo '</div>';
		}
		echo '</div>';

		$url = admin_url( 'edit.php?post_type=dish_class&page=dish-events-reports&tab=revenue' );
		echo '<p class="dish-dashboard-footer"><a href="' . esc_url( $url ) . '">' . esc_html__( 'Full revenue report →', 'dish-events' ) . '</a></p>';
	}

	// -------------------------------------------------------------------------
	// Inline styles
	// -------------------------------------------------------------------------

	/**
	 * Output lightweight inline CSS for all four widgets.
	 * Hooked to admin_head on the dashboard page only.
	 */
	public function inline_styles(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'dashboard' !== $screen->id ) {
			return;
		}
		?>
		<style>
		/* ---- Shared table styles ---- */
		.dish-dashboard-table { margin-top: 8px; font-size: 13px; }
		.dish-dashboard-table th { font-weight: 600; }
		.dish-dashboard-footer { margin: 8px 0 0; font-size: 12px; text-align: right; }

		/* ---- Booking status badges ---- */
		.dish-status-badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
		.dish-status-completed { background: #d1fae5; color: #065f46; }
		.dish-status-pending   { background: #fef9c3; color: #92400e; }
		.dish-status-cancelled { background: #fee2e2; color: #991b1b; }
		.dish-status-failed    { background: #fce7f3; color: #9d174d; }
		.dish-status-refunded  { background: #ede9fe; color: #4c1d95; }
		.dish-date-hint { color: #999; font-size: 11px; }

		/* ---- Capacity bar ---- */
		.dish-capacity-summary { display: flex; gap: 10px; flex-wrap: wrap; padding: 8px 0 12px; font-size: 13px; }
		.dish-cap-stat strong  { font-size: 18px; }
		.dish-cap-sep          { color: #ccc; }
		.dish-bar-wrap { background: #e5e7eb; border-radius: 3px; height: 6px; margin-bottom: 2px; overflow: hidden; }
		.dish-bar      { height: 6px; border-radius: 3px; transition: width .3s; }
		.dish-bar-low  { background: #34d399; }
		.dish-bar-mid  { background: #fbbf24; }
		.dish-bar-full { background: #f87171; }

		/* ---- Format breakdown ---- */
		.dish-format-breakdown { margin: 8px 0 0; padding: 0; list-style: none; }
		.dish-format-row { display: flex; align-items: center; gap: 8px; padding: 5px 0; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
		.dish-format-row:last-child { border-bottom: none; }
		.dish-format-swatch { flex-shrink: 0; width: 10px; height: 10px; border-radius: 50%; }
		.dish-format-label  { flex: 0 0 120px; font-weight: 500; }
		.dish-format-bar-wrap { flex: 1; background: #e5e7eb; border-radius: 3px; height: 6px; overflow: hidden; }
		.dish-format-bar    { display: block; height: 6px; border-radius: 3px; }
		.dish-format-count  { flex-shrink: 0; font-weight: 600; width: 24px; text-align: right; }

		/* ---- Revenue snapshot ---- */
		.dish-rev-headline  { display: flex; gap: 6px; flex-wrap: wrap; padding: 8px 0 14px; border-bottom: 1px solid #f0f0f0; margin-bottom: 12px; }
		.dish-rev-stat      { flex: 1; min-width: 80px; background: #f9f9f9; border-radius: 4px; padding: 8px 10px; text-align: center; }
		.dish-rev-value     { display: block; font-size: 18px; font-weight: 700; color: #1d2327; line-height: 1.2; }
		.dish-rev-label     { display: block; font-size: 11px; color: #888; margin-top: 2px; }
		.dish-rev-chart     { display: flex; align-items: flex-end; gap: 6px; height: 80px; padding-bottom: 18px; }
		.dish-rev-col       { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; cursor: default; }
		.dish-rev-bar-wrap  { flex: 1; width: 100%; display: flex; align-items: flex-end; }
		.dish-rev-bar       { width: 100%; background: #c3d9f5; border-radius: 3px 3px 0 0; min-height: 2px; }
		.dish-rev-col.dish-bar-current .dish-rev-bar   { background: #2271b1; }
		.dish-rev-month     { font-size: 10px; color: #999; margin-top: 3px; }
		.dish-rev-col.dish-bar-current .dish-rev-month { color: #2271b1; font-weight: 600; }
		</style>
		<?php
	}
}
