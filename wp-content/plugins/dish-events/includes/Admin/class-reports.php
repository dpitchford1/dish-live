<?php
/**
 * Reports admin page.
 *
 * Registers a "Reports" submenu under Dish Events (the dish_class post-type
 * parent menu) and renders a three-tab interface:
 *
 *   Bookings  — summary stats + filterable/paginated bookings list + CSV export
 *   Revenue   — revenue breakdown by class
 *   Attendees — per-class attendee list + CSV export
 *
 * CSV downloads are handled by GET requests with an `action` parameter so
 * they work without JavaScript and don't require a separate AJAX handler.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Loader;
use Dish\Events\Data\ReportsRepository;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class Reports
 */
final class Reports {

	const PAGE_SLUG = 'dish-events-reports';

	private const TABS = [
		'bookings'  => 'Bookings',
		'revenue'   => 'Revenue',
		'attendees' => 'Attendees',
	];

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	public function register_hooks( Loader $loader ): void {
		$loader->add_action( 'admin_menu', $this, 'add_page' );
		// Handle CSV downloads early — before any output is sent.
		$loader->add_action( 'admin_init', $this, 'maybe_export_csv' );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=dish_class',
			__( 'Reports', 'dish-events' ),
			__( 'Reports', 'dish-events' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// CSV export (fires on admin_init, before any HTML output)
	// -------------------------------------------------------------------------

	public function maybe_export_csv(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		if ( ( $_GET['page'] ?? '' ) !== self::PAGE_SLUG ) {
			return;
		}

		$action = sanitize_key( $_GET['dish_export'] ?? '' );
		if ( ! in_array( $action, [ 'bookings', 'attendees' ], true ) ) {
			return;
		}
		// phpcs:enable

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export reports.', 'dish-events' ) );
		}

		check_admin_referer( 'dish_reports_export' );

		if ( 'bookings' === $action ) {
			$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$status    = sanitize_key( $_GET['status'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
			$search    = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$rows      = ReportsRepository::export_bookings_csv( $date_from, $date_to, $status, $search );
			$filename  = 'bookings-' . gmdate( 'Y-m-d' ) . '.csv';
		} else {
			$class_id = absint( $_GET['class_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( ! $class_id ) {
				wp_die( esc_html__( 'No class selected for export.', 'dish-events' ) );
			}
			$rows     = ReportsRepository::export_attendees_csv( $class_id );
			$filename = 'attendees-class-' . $class_id . '-' . gmdate( 'Y-m-d' ) . '.csv';
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$fh = fopen( 'php://output', 'wb' );
		if ( $fh ) {
			foreach ( $rows as $row ) {
				fputcsv( $fh, $row );
			}
			fclose( $fh );
		}
		exit;
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'bookings';
		if ( ! array_key_exists( $tab, self::TABS ) ) {
			$tab = 'bookings';
		}
		// phpcs:enable

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Reports', 'dish-events' ); ?></h1>

			<nav class="nav-tab-wrapper" style="margin-bottom:0">
				<?php foreach ( self::TABS as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>"
					   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:20px 24px;margin-bottom:20px">
				<?php
				match ( $tab ) {
					'revenue'   => $this->render_revenue_tab(),
					'attendees' => $this->render_attendees_tab(),
					default     => $this->render_bookings_tab(),
				};
				?>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Bookings tab
	// -------------------------------------------------------------------------

	private function render_bookings_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) );
		$status    = sanitize_key( $_GET['status'] ?? '' );
		$search    = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
		$paged     = max( 1, absint( $_GET['paged'] ?? 1 ) );
		// phpcs:enable

		$per_page = 30;
		$summary  = ReportsRepository::get_summary( $date_from, $date_to, $status );
		$list     = ReportsRepository::get_bookings_list( $date_from, $date_to, $status, $search, $per_page, $paged );
		$total    = $list['total'];
		$rows     = $list['rows'];
		$pages    = max( 1, (int) ceil( $total / $per_page ) );

		$base_url = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG . '&tab=bookings' );

		// ── Stat cards ────────────────────────────────────────────────────────
		?>
		<div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap">
			<?php
			$this->stat_card(
				__( 'Total Bookings', 'dish-events' ),
				number_format_i18n( $summary['total_bookings'] )
			);
			$this->stat_card(
				__( 'Total Revenue', 'dish-events' ),
				MoneyHelper::cents_to_display( $summary['total_revenue'] )
			);
			$this->stat_card(
				__( 'Avg Revenue / Day', 'dish-events' ),
				MoneyHelper::cents_to_display( $summary['avg_per_day'] )
			);
			$this->stat_card(
				__( 'Total Tickets', 'dish-events' ),
				number_format_i18n( $summary['total_tickets'] )
			);
			?>
		</div>

		<?php // ── Filter form ────────────────────────────────────────────────
		?>
		<form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
			<input type="hidden" name="post_type" value="dish_class">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="bookings">

			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'From', 'dish-events' ); ?><br>
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="height:30px">
			</label>

			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'To', 'dish-events' ); ?><br>
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="height:30px">
			</label>

			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'Status', 'dish-events' ); ?><br>
				<select name="status" style="height:30px">
					<option value=""><?php esc_html_e( 'All active', 'dish-events' ); ?></option>
					<?php foreach ( self::booking_statuses() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'Search', 'dish-events' ); ?><br>
				<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>"
				       placeholder="<?php esc_attr_e( 'Name or email…', 'dish-events' ); ?>"
				       style="height:30px;width:180px">
			</label>

			<div>
				<?php submit_button( __( 'Filter', 'dish-events' ), 'secondary', '', false, [ 'style' => 'margin-top:20px' ] ); ?>
			</div>

			<?php if ( $date_from || $date_to || $status || $search ) : ?>
				<div style="margin-top:20px">
					<a href="<?php echo esc_url( $base_url ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'dish-events' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</form>

		<?php // ── Export + row count ──────────────────────────────────────── ?>
		<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:8px">
			<span style="color:#646970;font-size:13px">
				<?php
				printf(
					/* translators: %d: number of bookings */
					esc_html( _n( '%d booking', '%d bookings', $total, 'dish-events' ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</span>

			<a href="<?php echo esc_url( wp_nonce_url(
				add_query_arg( [
					'post_type'   => 'dish_class',
					'page'        => self::PAGE_SLUG,
					'tab'         => 'bookings',
					'dish_export' => 'bookings',
					'date_from'   => $date_from,
					'date_to'     => $date_to,
					'status'      => $status,
					'search'      => $search,
				], admin_url( 'edit.php' ) ),
				'dish_reports_export'
			) ); ?>" class="button">
				⬇ <?php esc_html_e( 'Export CSV', 'dish-events' ); ?>
			</a>
		</div>

		<?php // ── Table ──────────────────────────────────────────────────── ?>
		<?php if ( empty( $rows ) ) : ?>
			<p style="color:#646970"><?php esc_html_e( 'No bookings found.', 'dish-events' ); ?></p>
		<?php else : ?>
		<table class="widefat striped" style="font-size:13px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID',         'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Date',       'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Class',      'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Customer',   'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Tickets',    'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Total',      'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Status',     'dish-events' ); ?></th>
					<th><?php esc_html_e( 'Gateway',    'dish-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$class_id    = (int) $row['class_id'];
					$template_id = $class_id ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
					$class_title = $template_id ? get_the_title( $template_id ) : ( $class_id ? get_the_title( $class_id ) : '—' );
					$edit_link   = get_edit_post_link( (int) $row['ID'] );
					$total_disp  = MoneyHelper::cents_to_display( (int) $row['total_cents'] );
					$dt          = wp_date( 'M j, Y g:i a', strtotime( $row['post_date'] ) );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $edit_link ?? '#' ); ?>">#<?php echo esc_html( $row['ID'] ); ?></a>
						</td>
						<td style="white-space:nowrap"><?php echo esc_html( $dt ); ?></td>
						<td>
							<?php if ( $class_id && $template_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $template_id ) ?? '#' ); ?>">
									<?php echo esc_html( $class_title ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $class_title ); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $row['customer_name'] ); ?><br>
							<a href="mailto:<?php echo esc_attr( $row['customer_email'] ); ?>" style="color:#646970;font-size:12px">
								<?php echo esc_html( $row['customer_email'] ); ?>
							</a>
						</td>
						<td style="text-align:center"><?php echo esc_html( $row['ticket_qty'] ); ?></td>
						<td style="white-space:nowrap"><?php echo esc_html( $total_disp ); ?></td>
						<td><?php $this->status_badge( $row['post_status'] ); ?></td>
						<td style="color:#646970"><?php echo esc_html( $row['gateway'] ?: '—' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php // ── Pagination ─────────────────────────────────────────────── ?>
		<?php if ( $pages > 1 ) : ?>
			<div style="margin-top:12px;display:flex;gap:6px;align-items:center;flex-wrap:wrap">
				<?php for ( $p = 1; $p <= $pages; $p++ ) : ?>
					<?php
					$page_url = add_query_arg( array_filter( [
						'post_type' => 'dish_class',
						'page'      => self::PAGE_SLUG,
						'tab'       => 'bookings',
						'date_from' => $date_from,
						'date_to'   => $date_to,
						'status'    => $status,
						'search'    => $search,
						'paged'     => $p > 1 ? $p : null,
					] ), admin_url( 'edit.php' ) );
					?>
					<a href="<?php echo esc_url( $page_url ); ?>"
					   class="button <?php echo $p === $paged ? 'button-primary' : ''; ?>"
					   style="min-width:32px;text-align:center">
						<?php echo esc_html( (string) $p ); ?>
					</a>
				<?php endfor; ?>
			</div>
		<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Revenue tab
	// -------------------------------------------------------------------------

	private function render_revenue_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		$date_from = sanitize_text_field( wp_unslash( $_GET['date_from'] ?? '' ) );
		$date_to   = sanitize_text_field( wp_unslash( $_GET['date_to']   ?? '' ) );
		// phpcs:enable

		$rows    = ReportsRepository::get_revenue_by_class( $date_from, $date_to );
		$summary = ReportsRepository::get_summary( $date_from, $date_to );

		?>
		<div style="display:flex;gap:16px;margin-bottom:24px;flex-wrap:wrap">
			<?php
			$this->stat_card( __( 'Total Revenue', 'dish-events' ), MoneyHelper::cents_to_display( $summary['total_revenue'] ) );
			$this->stat_card( __( 'Total Bookings', 'dish-events' ), number_format_i18n( $summary['total_bookings'] ) );
			$this->stat_card( __( 'Avg Revenue / Day', 'dish-events' ), MoneyHelper::cents_to_display( $summary['avg_per_day'] ) );
			?>
		</div>

		<form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px">
			<input type="hidden" name="post_type" value="dish_class">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="revenue">
			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'From', 'dish-events' ); ?><br>
				<input type="date" name="date_from" value="<?php echo esc_attr( $date_from ); ?>" style="height:30px">
			</label>
			<label style="font-weight:600;font-size:13px">
				<?php esc_html_e( 'To', 'dish-events' ); ?><br>
				<input type="date" name="date_to" value="<?php echo esc_attr( $date_to ); ?>" style="height:30px">
			</label>
			<?php submit_button( __( 'Filter', 'dish-events' ), 'secondary', '', false, [ 'style' => 'margin-top:20px' ] ); ?>
			<?php if ( $date_from || $date_to ) : ?>
				<div style="margin-top:20px">
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG . '&tab=revenue' ) ); ?>" class="button">
						<?php esc_html_e( 'Reset', 'dish-events' ); ?>
					</a>
				</div>
			<?php endif; ?>
		</form>

		<?php if ( empty( $rows ) ) : ?>
			<p style="color:#646970"><?php esc_html_e( 'No revenue data found.', 'dish-events' ); ?></p>
		<?php else : ?>
		<table class="widefat striped" style="font-size:13px">
			<thead>
				<tr>
					<th style="width:50%"><?php esc_html_e( 'Class',    'dish-events' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Bookings', 'dish-events' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Tickets',  'dish-events' ); ?></th>
					<th style="text-align:right"><?php esc_html_e( 'Revenue',  'dish-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$grand_total = 0;
				foreach ( $rows as $row ) :
					$grand_total += (int) $row['revenue'];
					$class_id     = (int) $row['class_id'];
					$template_id  = $class_id ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
					$edit_link    = $template_id ? get_edit_post_link( $template_id ) : get_edit_post_link( $class_id );
					?>
					<tr>
						<td>
							<a href="<?php echo esc_url( $edit_link ?? '#' ); ?>">
								<?php echo esc_html( $row['class_title'] ); ?>
							</a>
						</td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $row['bookings'] ) ); ?></td>
						<td style="text-align:right"><?php echo esc_html( number_format_i18n( (int) $row['tickets'] ) ); ?></td>
						<td style="text-align:right;font-weight:600"><?php echo esc_html( MoneyHelper::cents_to_display( (int) $row['revenue'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr style="font-weight:700;border-top:2px solid #c3c4c7">
					<td><?php esc_html_e( 'Total', 'dish-events' ); ?></td>
					<td></td>
					<td></td>
					<td style="text-align:right"><?php echo esc_html( MoneyHelper::cents_to_display( $grand_total ) ); ?></td>
				</tr>
			</tfoot>
		</table>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Attendees tab
	// -------------------------------------------------------------------------

	private function render_attendees_tab(): void {
		// phpcs:disable WordPress.Security.NonceVerification
		$class_id = absint( $_GET['class_id'] ?? 0 );
		// phpcs:enable

		// ── Class selector ────────────────────────────────────────────────────
		?>
		<form method="get" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px">
			<input type="hidden" name="post_type" value="dish_class">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
			<input type="hidden" name="tab" value="attendees">

			<label style="font-weight:600;font-size:13px;flex:1;min-width:260px">
				<?php esc_html_e( 'Select class', 'dish-events' ); ?><br>
				<select name="class_id" style="height:30px;width:100%;max-width:400px">
					<option value=""><?php esc_html_e( '— choose a class —', 'dish-events' ); ?></option>
					<?php
					$classes = get_posts( [
						'post_type'      => 'dish_class',
						'post_status'    => [ 'publish', 'future' ],
						'posts_per_page' => 200,
						'orderby'        => 'date',
						'order'          => 'DESC',
						'fields'         => 'all',
					] );
					foreach ( $classes as $cls ) :
						$tpl_id = (int) get_post_meta( $cls->ID, 'dish_template_id', true );
						$label  = $tpl_id ? get_the_title( $tpl_id ) . ' — ' . wp_date( 'M j, Y', strtotime( $cls->post_date ) ) : $cls->post_title;
						?>
						<option value="<?php echo esc_attr( (string) $cls->ID ); ?>" <?php selected( $class_id, $cls->ID ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>

			<?php submit_button( __( 'View Attendees', 'dish-events' ), 'secondary', '', false, [ 'style' => 'margin-top:20px' ] ); ?>
		</form>

		<?php if ( ! $class_id ) : ?>
			<p style="color:#646970"><?php esc_html_e( 'Select a class above to view its attendee list.', 'dish-events' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<?php
		$attendees = ReportsRepository::get_attendees_for_class( $class_id );

		// Class heading.
		$tpl_id      = (int) get_post_meta( $class_id, 'dish_template_id', true );
		$class_title = $tpl_id ? get_the_title( $tpl_id ) : get_the_title( $class_id );
		$class_date  = wp_date( 'l, F j, Y', (int) get_post_meta( $class_id, 'dish_start_datetime', true ) );
		?>
		<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px;flex-wrap:wrap">
			<div>
				<h3 style="margin:0 0 2px"><?php echo esc_html( $class_title ); ?></h3>
				<?php if ( $class_date ) : ?>
					<p style="margin:0;color:#646970;font-size:13px"><?php echo esc_html( $class_date ); ?></p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $attendees ) ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url(
					add_query_arg( [
						'post_type'   => 'dish_class',
						'page'        => self::PAGE_SLUG,
						'tab'         => 'attendees',
						'dish_export' => 'attendees',
						'class_id'    => $class_id,
					], admin_url( 'edit.php' ) ),
					'dish_reports_export'
				) ); ?>" class="button">
					⬇ <?php esc_html_e( 'Export CSV', 'dish-events' ); ?>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( empty( $attendees ) ) : ?>
			<p style="color:#646970"><?php esc_html_e( 'No attendees found for this class.', 'dish-events' ); ?></p>
		<?php else : ?>
			<?php
			// Summary pills.
			$total_tickets = array_sum( array_column( $attendees, 'ticket_qty' ) );
			$total_revenue = array_sum( array_column( $attendees, 'total_cents' ) );
			?>
			<div style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap">
				<?php
				$this->stat_card( __( 'Bookings', 'dish-events' ), number_format_i18n( count( $attendees ) ) );
				$this->stat_card( __( 'Tickets Sold', 'dish-events' ), number_format_i18n( (int) $total_tickets ) );
				$this->stat_card( __( 'Revenue', 'dish-events' ), MoneyHelper::cents_to_display( (int) $total_revenue ) );
				?>
			</div>

			<table class="widefat striped" style="font-size:13px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Booking', 'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Name',    'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Email',   'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Phone',   'dish-events' ); ?></th>
						<th style="text-align:center"><?php esc_html_e( 'Tickets', 'dish-events' ); ?></th>
						<th style="text-align:right"><?php esc_html_e( 'Total',  'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Status',  'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Booked',  'dish-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $attendees as $bk ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $bk['ID'] ) ?? '#' ); ?>">
									#<?php echo esc_html( $bk['ID'] ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $bk['customer_name'] ); ?></td>
							<td>
								<a href="mailto:<?php echo esc_attr( $bk['customer_email'] ); ?>" style="color:#646970">
									<?php echo esc_html( $bk['customer_email'] ); ?>
								</a>
							</td>
							<td style="color:#646970"><?php echo esc_html( $bk['customer_phone'] ?: '—' ); ?></td>
							<td style="text-align:center"><?php echo esc_html( $bk['ticket_qty'] ); ?></td>
							<td style="text-align:right;font-weight:600"><?php echo esc_html( MoneyHelper::cents_to_display( (int) $bk['total_cents'] ) ); ?></td>
							<td><?php $this->status_badge( $bk['post_status'] ); ?></td>
							<td style="white-space:nowrap;color:#646970;font-size:12px">
								<?php echo esc_html( wp_date( 'M j, Y', strtotime( $bk['post_date'] ) ) ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Shared UI helpers
	// -------------------------------------------------------------------------

	/**
	 * Render a stat summary card.
	 */
	private function stat_card( string $label, string $value ): void {
		printf(
			'<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:14px 20px;min-width:140px;flex:1">
			    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#646970;margin-bottom:4px">%s</div>
			    <div style="font-size:22px;font-weight:700;color:#1d2327">%s</div>
			</div>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/**
	 * Render a colour-coded status badge (inline styles, no extra CSS file needed).
	 */
	private function status_badge( string $status ): void {
		$map = [
			'dish_pending'   => [ __( 'Pending',   'dish-events' ), '#c60',     '#fff8ee' ],
			'dish_completed' => [ __( 'Completed', 'dish-events' ), '#0a7742',  '#eafaf1' ],
			'dish_failed'    => [ __( 'Failed',    'dish-events' ), '#b00',     '#fff0f0' ],
			'dish_refunded'  => [ __( 'Refunded',  'dish-events' ), '#666',     '#f5f5f5' ],
			'dish_cancelled' => [ __( 'Cancelled', 'dish-events' ), '#8b0000',  '#fff0f0' ],
		];
		[ $label, $color, $bg ] = $map[ $status ] ?? [
			ucfirst( str_replace( [ 'dish_', '_' ], [ '', ' ' ], $status ) ),
			'#888',
			'#f5f5f5',
		];
		printf(
			'<span style="color:%s;background:%s;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap">%s</span>',
			esc_attr( $color ),
			esc_attr( $bg ),
			esc_html( $label )
		);
	}

	/**
	 * Returns the booking status map for filter dropdowns.
	 *
	 * @return array<string, string>
	 */
	private static function booking_statuses(): array {
		return [
			'dish_pending'   => __( 'Pending',   'dish-events' ),
			'dish_completed' => __( 'Completed', 'dish-events' ),
			'dish_failed'    => __( 'Failed',    'dish-events' ),
			'dish_refunded'  => __( 'Refunded',  'dish-events' ),
			'dish_cancelled' => __( 'Cancelled', 'dish-events' ),
		];
	}
}
