<?php
/**
 * Custom columns and filters for the dish_booking list table.
 *
 * Columns
 * -------
 *  - dish_bk_class      : linked class title
 *  - dish_bk_customer   : customer name + e-mail
 *  - dish_bk_ticket     : ticket type + quantity
 *  - dish_bk_total      : formatted dollar total
 *  - dish_bk_status     : colour-coded status badge
 *  - dish_bk_visibility : Public / Private badge
 *  - dish_bk_date       : booking date (sortable)
 *
 * Filters
 * -------
 *  - Status dropdown (restrict_manage_posts)
 *
 * The title column is kept as the Booking ID (#post_id) to make individual
 * bookings easy to reference.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class BookingColumns
 */
final class BookingColumns {

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Wire all list-table hooks via the Loader.
	 * Called from Admin::register_hooks().
	 *
	 * @param Loader $loader The plugin hook loader.
	 */
	public function register_hooks( Loader $loader ): void {
		$loader->add_filter( 'manage_dish_booking_posts_columns',        $this, 'add_columns' );
		$loader->add_action( 'manage_dish_booking_posts_custom_column',  $this, 'render_column', 10, 2 );
		$loader->add_filter( 'manage_edit-dish_booking_sortable_columns', $this, 'sortable_columns' );
		$loader->add_action( 'pre_get_posts',                            $this, 'handle_sort_and_filter' );
		$loader->add_action( 'restrict_manage_posts',                    $this, 'render_status_filter' );
		$loader->add_filter( 'post_row_actions',                         $this, 'simplify_row_actions', 10, 2 );
		// Remove the native "Published" view link — bookings should never have publish status.
		$loader->add_filter( 'views_edit-dish_booking',                  $this, 'filter_list_views' );
	}

	// -------------------------------------------------------------------------
	// Columns
	// -------------------------------------------------------------------------

	/**
	 * Replace the default column set with booking-specific columns.
	 *
	 * @param  array<string,string> $columns Default columns.
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		return [
			'cb'               => $columns['cb'],
			'title'            => __( 'Booking ID', 'dish-events' ),
			'dish_bk_class'    => __( 'Class', 'dish-events' ),
			'dish_bk_customer' => __( 'Customer', 'dish-events' ),
			'dish_bk_ticket'   => __( 'Ticket', 'dish-events' ),
			'dish_bk_total'    => __( 'Total', 'dish-events' ),
			'dish_bk_status'   => __( 'Status', 'dish-events' ),
			'dish_bk_visibility' => __( 'Visibility', 'dish-events' ),
			'dish_bk_date'     => __( 'Date Booked', 'dish-events' ),
		];
	}

	/**
	 * Render each custom column cell.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {

			case 'dish_bk_class':
				$class_id = (int) get_post_meta( $post_id, 'dish_class_id', true );
				if ( $class_id ) {
					$link = get_edit_post_link( $class_id );
					printf(
						'<a href="%s">%s</a>',
						esc_url( $link ?? '' ),
						esc_html( get_the_title( $class_id ) ?: sprintf( __( 'Class #%d', 'dish-events' ), $class_id ) )
					);
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_bk_customer':
				$name  = (string) get_post_meta( $post_id, 'dish_customer_name',  true );
				$email = (string) get_post_meta( $post_id, 'dish_customer_email', true );
				if ( $name || $email ) {
					if ( $name ) {
						echo esc_html( $name ) . '<br>';
					}
					if ( $email ) {
						printf(
							'<span style="color:#646970"><a href="mailto:%s">%s</a></span>',
							esc_attr( $email ),
							esc_html( $email )
						);
					}
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_bk_ticket':
				$type_id     = (int) get_post_meta( $post_id, 'dish_ticket_type_id', true );
				$qty         = (int) get_post_meta( $post_id, 'dish_ticket_qty',     true );
				$ticket_type = $type_id ? \Dish\Events\Data\TicketTypeRepository::get( $type_id ) : null;
				$name        = $ticket_type ? $ticket_type->name : '—';
				if ( $name || $qty ) {
					echo esc_html( $name );
					if ( $qty > 1 ) {
						printf( ' <span style="color:#646970">× %d</span>', $qty );
					}
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_bk_status':
				$post   = get_post( $post_id );
				$this->status_badge( $post ? $post->post_status : '' );
				break;

			case 'dish_bk_visibility':
				$class_id   = (int) get_post_meta( $post_id, 'dish_class_id', true );
				$is_private = $class_id && get_post_meta( $class_id, 'dish_is_private', true );
				if ( $is_private ) {
					echo '<span style="color:#8b0000;background:#fff0f0;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">'
						. esc_html__( 'Private', 'dish-events' )
						. '</span>';
				} else {
					echo '<span style="color:#0a7742;background:#eafaf1;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">'
						. esc_html__( 'Public', 'dish-events' )
						. '</span>';
				}
				break;

			case 'dish_bk_date':
				// get_post_time( 'U', true ) returns a UTC Unix timestamp;
				// wp_date() then converts to site timezone for display.
				$ts = get_post_time( 'U', true, $post_id );
				if ( $ts ) {
					echo esc_html( wp_date( 'M j, Y', $ts ) );
					echo '<br><span style="color:#646970">' . esc_html( wp_date( 'g:i a', $ts ) ) . '</span>';
				}
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Sortable columns
	// -------------------------------------------------------------------------

	/**
	 * Declare the date column as sortable.
	 *
	 * @param  array<string,mixed> $columns
	 * @return array<string,mixed>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['dish_bk_date']  = 'date';
		$columns['dish_bk_total'] = 'dish_bk_total';
		return $columns;
	}

	/**
	 * Handle custom sort + status filter in WP_Query.
	 *
	 * @param \WP_Query $query Current query.
	 */
	public function handle_sort_and_filter( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== 'dish_booking' ) {
			return;
		}

		// Sort by total.
		if ( $query->get( 'orderby' ) === 'dish_bk_total' ) {
			$query->set( 'meta_key', 'dish_ticket_total_cents' );
			$query->set( 'orderby', 'meta_value_num' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$url_status    = sanitize_key( $_GET['post_status'] ?? '' );
		// phpcs:ignore WordPress.Security.NonceVerification
		$status_filter = sanitize_key( $_GET['dish_booking_status'] ?? '' );

		if ( $status_filter && str_starts_with( $status_filter, 'dish_' ) ) {
			// Our custom dropdown is active — filter to that specific status.
			$query->set( 'post_status', $status_filter );
		} elseif ( ! $url_status ) {
			// No ?post_status= in the URL = "All" view.
			// WP pre-sets post_status to 'publish' before pre_get_posts fires, so we must
			// explicitly override here. Dynamically include every registered status except
			// trash and auto-draft so posts that accidentally landed on a native WP status
			// (publish, pending, draft) are still visible.
			$all_stati = array_values(
				array_diff(
					array_keys( get_post_stati() ),
					[ 'trash', 'auto-draft' ]
				)
			);
			$query->set( 'post_status', $all_stati );
		}
		// If $url_status is set (e.g. 'trash'), let WP handle it natively.
	}

	// -------------------------------------------------------------------------
	// Status filter
	// -------------------------------------------------------------------------

	/**
	 * Render the status filter dropdown above the list table.
	 *
	 * @param string $post_type Current post type.
	 */
	public function render_status_filter( string $post_type ): void {
		if ( $post_type !== 'dish_booking' ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification
		$selected = sanitize_key( $_GET['dish_booking_status'] ?? '' );

		$statuses = [
			''               => __( 'All statuses', 'dish-events' ),
			'dish_pending'   => __( 'Pending',       'dish-events' ),
			'dish_completed' => __( 'Completed',     'dish-events' ),
			'dish_failed'    => __( 'Failed',        'dish-events' ),
			'dish_refunded'  => __( 'Refunded',      'dish-events' ),
			'dish_cancelled' => __( 'Cancelled',     'dish-events' ),
		];
		?>
		<select name="dish_booking_status" id="filter-by-dish-booking-status">
			<?php foreach ( $statuses as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	// -------------------------------------------------------------------------
	// Row actions
	// -------------------------------------------------------------------------

	/**
	 * Remove "Quick Edit" and "Trash" from booking row actions;
	 * keep only "Edit" (View Booking).
	 *
	 * @param  array<string,string> $actions  Default row actions.
	 * @param  \WP_Post             $post     Current post.
	 * @return array<string,string>
	 */
	public function simplify_row_actions( array $actions, \WP_Post $post ): array {
		if ( $post->post_type !== 'dish_booking' ) {
			return $actions;
		}
		// Keep only the edit link; everything else is noise for a read-mostly record.
		return array_intersect_key( $actions, [ 'edit' => true, 'trash' => true ] );
	}

	/**
	 * Remove the native "Published" view link from the list table header.
	 * Bookings should never have `publish` status; if they do, they'll still
	 * appear under "All" (the default query includes `publish` as a safety net).
	 *
	 * @param  array<string,string> $views Associative array of view links.
	 * @return array<string,string>
	 */
	public function filter_list_views( array $views ): array {
		// Keep only All, our custom dish_* status links, and Trash.
		// Native WP statuses (publish, pending, draft, future, private) are not
		// valid for bookings — strip them so the header is clean.
		$keep = [ 'all', 'trash' ];
		foreach ( array_keys( $views ) as $key ) {
			if ( str_starts_with( $key, 'dish_' ) ) {
				$keep[] = $key;
			}
		}
		return array_intersect_key( $views, array_flip( $keep ) );
	}

	// -------------------------------------------------------------------------
	// Shared UI helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a colour-coded booking status badge.
	 *
	 * Mirrors Reports::status_badge() so badge colours stay consistent across
	 * the list table and the Reports screen without a shared CSS file.
	 *
	 * @param string $status  Post status slug (e.g. 'dish_pending').
	 */
	private function status_badge( string $status ): void {
		$map = [
			'dish_pending'   => [ __( 'Pending',   'dish-events' ), '#c60',    '#fff8ee' ],
			'dish_completed' => [ __( 'Completed', 'dish-events' ), '#0a7742', '#eafaf1' ],
			'dish_failed'    => [ __( 'Failed',    'dish-events' ), '#b00',    '#fff0f0' ],
			'dish_refunded'  => [ __( 'Refunded',  'dish-events' ), '#666',    '#f5f5f5' ],
			'dish_cancelled' => [ __( 'Cancelled', 'dish-events' ), '#8b0000', '#fff0f0' ],
		];
		[ $label, $color, $bg ] = $map[ $status ] ?? [
			ucfirst( str_replace( [ 'dish_', '_' ], [ '', ' ' ], $status ) ),
			'#888',
			'#f5f5f5',
		];
		printf(
			'<span style="color:%s;background:%s;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;">%s</span>',
			esc_attr( $color ),
			esc_attr( $bg ),
			esc_html( $label )
		);
	}
}
