<?php
/**
 * Ticket Type admin screens.
 *
 * Registers the "Ticketing" submenu parent item (this page is the landing) and
 * provides a WP_List_Table list + full add/edit form for dish_ticket_types.
 *
 * All price and fee amounts are displayed as dollars in the UI and stored as
 * integer cents in the DB. Repeater rows (per_ticket_fees, per_booking_fees)
 * and the booking_starts mode toggle are handled with inline vanilla JS.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Data\FormatRepository;

// =============================================================================
// Admin Controller
// =============================================================================

/**
 * Ticket Type admin controller.
 */
final class TicketTypeAdmin {

	const PAGE_SLUG    = 'dish-ticket-types';
	const NONCE_SAVE   = 'dish_ticket_type_save';
	const NONCE_EXPORT = 'dish_ticket_type_export';
	const NONCE_IMPORT = 'dish_ticket_type_import';

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function add_pages(): void {
		// "Ticketing" parent — this page is the default landing.
		// TicketCategoryAdmin registers the "Categories" child below this.
		add_submenu_page(
			'edit.php?post_type=dish_class',
			__( 'Ticketing', 'dish-events' ),
			__( 'Ticketing', 'dish-events' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ],
		);
	}

	// -------------------------------------------------------------------------
	// Request handler (admin_init — fires before any output)
	// -------------------------------------------------------------------------

	/**
	 * Handle all state-changing requests (POST save, toggle, bulk).
	 * Must be hooked to admin_init so redirects fire before headers are sent.
	 */
	public function handle_request(): void {
		if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::PAGE_SLUG ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( $_GET['action'] ?? '' );
		$id     = absint( $_GET['id'] ?? 0 );

		// POST — add/edit form submission or import.
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( 'import' === $action ) {
				$this->handle_import();
				return;
			}
			$this->handle_save();
			return;
		}

		// GET: export JSON download — streams and exits.
		if ( 'export' === $action ) {
			$this->handle_export();
			return;
		}

		// GET: toggle single row active/inactive.
		if ( 'toggle' === $action && $id ) {
			$this->handle_toggle( $id );
			return;
		}

		// GET: duplicate a ticket type.
		if ( 'duplicate' === $action && $id ) {
			$this->handle_duplicate( $id );
			return;
		}

		// GET: delete a ticket type.
		if ( 'delete' === $action && $id ) {
			$this->handle_delete( $id );
			return;
		}

		// GET: WP_List_Table bulk action.
		$bulk = sanitize_key( $_REQUEST['action'] ?? '' );
		if ( in_array( $bulk, [ 'activate', 'deactivate' ], true ) && ! empty( $_REQUEST['ids'] ) ) {
			$this->handle_bulk( $bulk );
			return;
		}
	}

	// -------------------------------------------------------------------------
	// Export / Import
	// -------------------------------------------------------------------------

	/**
	 * Stream all ticket types as a JSON file download.
	 * format_id is replaced with the format post_name (slug) for portability.
	 */
	private function handle_export(): void {
		check_admin_referer( self::NONCE_EXPORT );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}dish_ticket_types ORDER BY format_id ASC, name ASC",
			ARRAY_A
		);

		// Swap format_id (DB int) → format_slug (portable).
		$slug_cache = [];
		foreach ( $rows as &$row ) {
			$fid = (int) $row['format_id'];
			if ( $fid && ! isset( $slug_cache[ $fid ] ) ) {
				$post = get_post( $fid );
				$slug_cache[ $fid ] = $post instanceof \WP_Post ? $post->post_name : '';
			}
			$row['format_slug'] = $fid ? ( $slug_cache[ $fid ] ?? '' ) : '';
			unset( $row['id'], $row['format_id'] ); // strip non-portable fields
		}
		unset( $row );

		$json     = wp_json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$filename = 'dish-ticket-types-' . gmdate( 'Y-m-d' ) . '.json';

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( (string) $json ) );
		header( 'Pragma: no-cache' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Handle JSON file upload and insert ticket types.
	 * Existing types with the same name + format are skipped (no duplicates).
	 */
	private function handle_import(): void {
		check_admin_referer( self::NONCE_IMPORT );

		global $wpdb;

		$base = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG );

		if ( empty( $_FILES['dish_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', 'no_file', $base ) );
			exit;
		}

		$raw = file_get_contents( sanitize_text_field( $_FILES['dish_import_file']['tmp_name'] ) );
		$data = $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $data ) || empty( $data ) ) {
			wp_safe_redirect( add_query_arg( 'import_error', 'invalid_json', $base ) );
			exit;
		}

		// Build format slug → ID map.
		$format_posts = get_posts( [ 'post_type' => 'dish_format', 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => true ] );
		$format_map   = [];
		foreach ( $format_posts as $fp ) {
			$format_map[ $fp->post_name ] = $fp->ID;
		}

		$now      = current_time( 'mysql' );
		$inserted = 0;
		$skipped  = 0;

		foreach ( $data as $row ) {
			if ( ! is_array( $row ) || empty( $row['name'] ) ) {
				$skipped++;
				continue;
			}

			$format_slug = sanitize_key( $row['format_slug'] ?? '' );
			$format_id   = $format_slug && isset( $format_map[ $format_slug ] ) ? (int) $format_map[ $format_slug ] : 0;

			// Skip exact duplicates (same name + format).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}dish_ticket_types WHERE name = %s AND format_id = %d LIMIT 1",
				sanitize_text_field( $row['name'] ),
				$format_id
			) );
			if ( $exists ) {
				$skipped++;
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'dish_ticket_types',
				[
					'format_id'          => $format_id,
					'name'               => sanitize_text_field( $row['name'] ),
					'description'        => wp_kses_post( $row['description'] ?? '' ),
					'price_cents'        => (int) ( $row['price_cents'] ?? 0 ),
					'sale_price_cents'   => isset( $row['sale_price_cents'] ) ? (int) $row['sale_price_cents'] : null,
					'capacity'           => isset( $row['capacity'] ) ? (int) $row['capacity'] : null,
					'show_remaining'     => (int) ( $row['show_remaining'] ?? 0 ),
					'min_per_booking'    => (int) ( $row['min_per_booking'] ?? 1 ),
					'per_ticket_fees'    => $row['per_ticket_fees'] ?? null,
					'per_booking_fees'   => $row['per_booking_fees'] ?? null,
					'booking_starts'     => $row['booking_starts'] ?? null,
					'show_booking_dates' => (int) ( $row['show_booking_dates'] ?? 0 ),
					'is_active'          => (int) ( $row['is_active'] ?? 1 ),
					'created_at'         => $now,
					'updated_at'         => $now,
				]
			);
			$inserted++;
		}

		wp_safe_redirect( add_query_arg( [ 'imported' => $inserted, 'skipped' => $skipped ], $base ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$id     = absint( $_GET['id'] ?? 0 );

		echo '<div class="wrap">';

		if ( in_array( $action, [ 'add', 'edit' ], true ) ) {
			$this->render_form( $id );
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	private function render_list(): void {
		global $wpdb;

		$base = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG );

		// All published formats, ordered by menu_order then title.
			$formats = FormatRepository::get_all_published();
		// All ticket types — one query, grouped in PHP.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$all_rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}dish_ticket_types ORDER BY name ASC"
		);

		// Index by format_id for fast lookup.
		$by_format = [];
		foreach ( $all_rows as $row ) {
			$by_format[ (int) $row->format_id ][] = $row;
		}
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Ticket Types', 'dish-events' ); ?></h1>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'add', $base ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'dish-events' ); ?>
		</a>
		<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'export', $base ), self::NONCE_EXPORT ) ); ?>" class="page-title-action">
			<?php esc_html_e( 'Export JSON', 'dish-events' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ticket type saved.', 'dish-events' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET['deleted'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Ticket type deleted.', 'dish-events' ); ?></p></div>
		<?php endif; ?>

		<?php if ( ! empty( $_GET['error'] ) && $_GET['error'] === 'in_use' ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'This ticket type cannot be deleted because it is linked to one or more Class Templates or Bookings.', 'dish-events' ); ?></p></div>
		<?php endif; ?>

		<?php // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_GET['imported'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
				<?php printf(
					esc_html__( '%1$d ticket type(s) imported, %2$d skipped (already exist).', 'dish-events' ),
					(int) $_GET['imported'], // phpcs:ignore WordPress.Security.NonceVerification
					(int) ( $_GET['skipped'] ?? 0 ) // phpcs:ignore WordPress.Security.NonceVerification
				); ?>
			</p></div>
		<?php endif; ?>

		<?php // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! empty( $_GET['import_error'] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p>
				<?php echo 'no_file' === $_GET['import_error'] // phpcs:ignore WordPress.Security.NonceVerification
					? esc_html__( 'Import failed: no file uploaded.', 'dish-events' )
					: esc_html__( 'Import failed: invalid JSON file.', 'dish-events' ); ?>
			</p></div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" style="margin-bottom:1.5em;display:flex;align-items:center;gap:.75em;">
			<?php wp_nonce_field( self::NONCE_IMPORT ); ?>
			<input type="hidden" name="action" value="import">
			<input type="file" name="dish_import_file" accept=".json" required style="font-size:13px;">
			<?php submit_button( __( 'Import JSON', 'dish-events' ), 'secondary', 'dish_import_submit', false ); ?>
		</form>

		<?php if ( empty( $all_rows ) ) : ?>
			<p><?php esc_html_e( 'No ticket types found. Add one to get started.', 'dish-events' ); ?></p>
		<?php
			return;
		endif;
		?>

		<?php
		// Render one section per format, then an Unassigned section.
		$rendered_ids = [];

		foreach ( $formats as $format ) {
			$rows = $by_format[ $format->ID ] ?? [];
			if ( empty( $rows ) ) {
				continue;
			}
				$color = (string) FormatRepository::get_meta( $format->ID, 'dish_format_color' ) ?: '#c0392b';
			foreach ( $rows as $r ) {
				$rendered_ids[] = (int) $r->id;
			}
			$this->render_format_section( $format->post_title, $color, $rows, $base );
		}

		// Anything not matched to a published format.
		$unassigned = array_filter( $all_rows, fn( $r ) => ! in_array( (int) $r->id, $rendered_ids, true ) );
		if ( ! empty( $unassigned ) ) {
			$this->render_format_section( __( 'Unassigned', 'dish-events' ), '#787c82', array_values( $unassigned ), $base );
		}
	}

	/**
	 * Render a single format section heading + ticket types table.
	 *
	 * @param string   $heading  Format name.
	 * @param string   $color    Hex colour for the left border accent.
	 * @param object[] $rows     Ticket type DB rows.
	 * @param string   $base     Base admin URL for row action links.
	 */
	private function render_format_section( string $heading, string $color, array $rows, string $base ): void {
		?>
		<div style="margin-top:2em">
			<h2 style="border-left:4px solid <?php echo esc_attr( $color ); ?>;padding-left:10px;margin-bottom:.5em">
				<?php echo esc_html( $heading ); ?>
				<span style="font-size:13px;font-weight:400;color:#787c82;margin-left:8px">(<?php echo count( $rows ); ?>)</span>
			</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:30%"><?php esc_html_e( 'Name', 'dish-events' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Price', 'dish-events' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Sale Price', 'dish-events' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Capacity', 'dish-events' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Status', 'dish-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $row ) :
					$edit_url      = add_query_arg( [ 'action' => 'edit', 'id' => $row->id ], $base );
					$toggle_url    = wp_nonce_url(
						add_query_arg( [ 'action' => 'toggle', 'id' => $row->id ], $base ),
						'dish_type_toggle_' . $row->id
					);
					$duplicate_url = wp_nonce_url(
						add_query_arg( [ 'action' => 'duplicate', 'id' => $row->id ], $base ),
						'dish_type_duplicate_' . $row->id
					);
					$delete_url    = wp_nonce_url(
						add_query_arg( [ 'action' => 'delete', 'id' => $row->id ], $base ),
						'dish_type_delete_' . $row->id
					);
					$toggle_label  = $row->is_active
						? __( 'Deactivate', 'dish-events' )
						: __( 'Activate', 'dish-events' );
				?>
					<tr>
						<td>
							<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $row->name ); ?></a></strong>
							<div class="row-actions">
								<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'dish-events' ); ?></a></span>
								&nbsp;|&nbsp;
								<span class="copy"><a href="<?php echo esc_url( $duplicate_url ); ?>"><?php esc_html_e( 'Duplicate', 'dish-events' ); ?></a></span>
								&nbsp;|&nbsp;
								<span class="inline"><a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo esc_html( $toggle_label ); ?></a></span>
								&nbsp;|&nbsp;
								<span class="delete"><a href="<?php echo esc_url( $delete_url ); ?>" style="color:#b32d2e" onclick="return confirm('<?php echo esc_js( __( 'Permanently delete this ticket type? This cannot be undone.', 'dish-events' ) ); ?>')"><?php esc_html_e( 'Delete', 'dish-events' ); ?></a></span>
							</div>
						</td>
						<td><?php echo esc_html( self::cents_to_display( (int) $row->price_cents ) ); ?></td>
						<td><?php echo $row->sale_price_cents !== null ? esc_html( self::cents_to_display( (int) $row->sale_price_cents ) ) : '<span style="color:#787c82">—</span>'; ?></td>
						<td><?php echo $row->capacity !== null ? esc_html( (string) (int) $row->capacity ) : '<span style="color:#787c82">—</span>'; ?></td>
						<td>
							<?php if ( $row->is_active ) : ?>
								<span style="color:#00a32a">&#9679; <?php esc_html_e( 'Active', 'dish-events' ); ?></span>
							<?php else : ?>
								<span style="color:#787c82">&#9675; <?php esc_html_e( 'Inactive', 'dish-events' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private function render_form( int $id = 0 ): void {
		global $wpdb;

		$row = $id
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d", $id ) )
			: null;

		if ( $id && ! $row ) {
			wp_die( esc_html__( 'Ticket type not found.', 'dish-events' ) );
		}

		// Current field values.
		$name               = $row->name              ?? '';
		$format_id          = (int) ( $row->format_id ?? 0 );
		$price_display      = $row ? number_format( (int) $row->price_cents / 100, 2, '.', '' ) : '';
		$sale_display       = ( $row && $row->sale_price_cents !== null )
			? number_format( (int) $row->sale_price_cents / 100, 2, '.', '' )
			: '';
		$capacity           = $row->capacity          ?? '';
		$show_remaining     = $row !== null ? (bool) $row->show_remaining : true;
		$min_per_booking    = (int) ( $row->min_per_booking   ?? 1 );
		$show_booking_dates = (bool) ( $row->show_booking_dates ?? false );
		$is_active          = isset( $row->is_active ) ? (bool) $row->is_active : true;

		$bs           = json_decode( $row->booking_starts ?? '{"mode":"immediate"}', true );
		$bs_mode      = $bs['mode']  ?? 'immediate';
		$bs_days      = (int) ( $bs['days'] ?? 14 );

		$decoded      = json_decode( $row->per_ticket_fees  ?? 'null', true );
		$ticket_fees  = is_array( $decoded ) ? $decoded : [];
		$decoded      = json_decode( $row->per_booking_fees ?? 'null', true );
		$booking_fees = is_array( $decoded ) ? $decoded : [];

		// Published dish_format posts for the dropdown.
			$categories = FormatRepository::get_all_published();
		$base     = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG );
		$list_url = $base;
		$currency = Settings::get( 'currency_symbol', '$' );
		?>
		<h1><?php echo $id ? esc_html__( 'Edit Ticket Type', 'dish-events' ) : esc_html__( 'Add Ticket Type', 'dish-events' ); ?></h1>
		<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Back to Ticket Types', 'dish-events' ); ?></a></p>
		<hr class="wp-header-end">

		<?php if ( ! empty( $_GET['duplicated'] ) ) : ?>
			<div class="notice notice-warning is-dismissible">
				<p><?php esc_html_e( 'This is a duplicate. Update the name, make any other changes, then save.', 'dish-events' ); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( $base ); ?>">
			<?php wp_nonce_field( self::NONCE_SAVE ); ?>
			<input type="hidden" name="type_id" value="<?php echo absint( $id ); ?>">

			<table class="form-table">

				<!-- Name -->
				<tr>
					<th><label for="type_name"><?php esc_html_e( 'Name', 'dish-events' ); ?> <span aria-hidden="true" style="color:red">*</span></label></th>
					<td>
						<input type="text" id="type_name" name="type_name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'e.g. "German Beer Garden", "Knife Skills Intensive"', 'dish-events' ); ?></p>
					</td>
				</tr>

				<!-- Category -->
				<tr>
					<th><label for="type_category_id"><?php esc_html_e( 'Format', 'dish-events' ); ?> <span aria-hidden="true" style="color:red">*</span></label></th>
					<td>
						<select id="type_category_id" name="type_category_id" required>
						<option value=""><?php esc_html_e( '— Select format —', 'dish-events' ); ?></option>
					<?php foreach ( $categories as $cat ) :
						$default_cap = (int) FormatRepository::get_meta( $cat->ID, 'dish_default_capacity', 0 );
					?>
						<option
							value="<?php echo absint( $cat->ID ); ?>"
							data-default-capacity="<?php echo $default_cap > 0 ? absint( $default_cap ) : ''; ?>"
							<?php selected( $format_id, (int) $cat->ID ); ?>>
							<?php echo esc_html( $cat->post_title ); ?>
						</option>
					<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<!-- Price -->
				<tr>
					<th><label for="type_price"><?php esc_html_e( 'Price', 'dish-events' ); ?> <span aria-hidden="true" style="color:red">*</span></label></th>
					<td>
						<span style="display:inline-flex;align-items:center;gap:4px;">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="type_price" name="type_price" value="<?php echo esc_attr( $price_display ); ?>" min="0" step="0.01" class="small-text" required>
						</span>
					</td>
				</tr>

				<!-- Sale Price -->
				<tr>
					<th><label for="type_sale_price"><?php esc_html_e( 'Sale price', 'dish-events' ); ?></label></th>
					<td>
						<span style="display:inline-flex;align-items:center;gap:4px;">
							<span><?php echo esc_html( $currency ); ?></span>
							<input type="number" id="type_sale_price" name="type_sale_price" value="<?php echo esc_attr( $sale_display ); ?>" min="0" step="0.01" class="small-text">
						</span>
						<p class="description"><?php esc_html_e( 'Leave blank for no sale. Silently replaces the regular price on frontend when set.', 'dish-events' ); ?></p>
					</td>
				</tr>

				<!-- Capacity -->
				<tr>
					<th><label for="type_capacity"><?php esc_html_e( 'Capacity', 'dish-events' ); ?></label></th>
					<td>
						<input type="number" id="type_capacity" name="type_capacity" value="<?php echo esc_attr( (string) $capacity ); ?>" min="1" class="small-text">
						<p class="description"><?php esc_html_e( 'Total seats. Leave blank for unlimited.', 'dish-events' ); ?></p>
					</td>
				</tr>

				<!-- Show Remaining -->
				<tr>
					<th><?php esc_html_e( 'Show remaining count', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="type_show_remaining" value="1" <?php checked( $show_remaining ); ?>>
							<?php esc_html_e( 'Show "X spots left" on frontend when low', 'dish-events' ); ?>
						</label>
					</td>
				</tr>

				<!-- Min per booking -->
				<tr>
					<th><label for="type_min_per_booking"><?php esc_html_e( 'Min per booking', 'dish-events' ); ?></label></th>
					<td>
						<input type="number" id="type_min_per_booking" name="type_min_per_booking" value="<?php echo absint( $min_per_booking ); ?>" min="1" class="small-text">
						<p class="description"><?php esc_html_e( 'Minimum tickets per booking. Use 2 for couples-only classes.', 'dish-events' ); ?></p>
					</td>
				</tr>

			</table>

			<!-- Per-ticket fees -->
			<h2><?php esc_html_e( 'Per-Ticket Fees', 'dish-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Multiplied by ticket quantity at checkout (e.g. Kitchen Supply Fee).', 'dish-events' ); ?></p>
			<table id="dish-per-ticket-fees" class="widefat striped" style="max-width:600px;margin-bottom:8px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Label', 'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'dish-events' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ticket_fees as $fee ) : ?>
						<tr class="dish-fee-row">
							<td><input type="text" name="per_ticket_fee_label[]" value="<?php echo esc_attr( $fee['label'] ?? '' ); ?>" class="regular-text" placeholder="e.g. Kitchen Supply Fee"></td>
							<td>
								<span style="display:inline-flex;align-items:center;gap:4px;">
									<?php echo esc_html( $currency ); ?>
									<input type="number" name="per_ticket_fee_amount[]" value="<?php echo esc_attr( number_format( ( $fee['amount_cents'] ?? 0 ) / 100, 2, '.', '' ) ); ?>" min="0" step="0.01" style="width:90px">
								</span>
							</td>
							<td><button type="button" class="button dish-remove-fee-row">✕</button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<button type="button" id="dish-add-ticket-fee" class="button"><?php esc_html_e( '+ Add Fee', 'dish-events' ); ?></button>

			<!-- Per-booking fees -->
			<h2><?php esc_html_e( 'Per-Booking Fees', 'dish-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Flat charge once per booking regardless of quantity (e.g. Corkage Fee).', 'dish-events' ); ?></p>
			<table id="dish-per-booking-fees" class="widefat striped" style="max-width:600px;margin-bottom:8px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Label', 'dish-events' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'dish-events' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $booking_fees as $fee ) : ?>
						<tr class="dish-fee-row">
							<td><input type="text" name="per_booking_fee_label[]" value="<?php echo esc_attr( $fee['label'] ?? '' ); ?>" class="regular-text" placeholder="e.g. Corkage Fee"></td>
							<td>
								<span style="display:inline-flex;align-items:center;gap:4px;">
									<?php echo esc_html( $currency ); ?>
									<input type="number" name="per_booking_fee_amount[]" value="<?php echo esc_attr( number_format( ( $fee['amount_cents'] ?? 0 ) / 100, 2, '.', '' ) ); ?>" min="0" step="0.01" style="width:90px">
								</span>
							</td>
							<td><button type="button" class="button dish-remove-fee-row">✕</button></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<button type="button" id="dish-add-booking-fee" class="button"><?php esc_html_e( '+ Add Fee', 'dish-events' ); ?></button>

			<!-- Booking window -->
			<h2><?php esc_html_e( 'Booking Window', 'dish-events' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Bookings open', 'dish-events' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:6px">
							<input type="radio" name="booking_starts_mode" value="immediate" <?php checked( $bs_mode, 'immediate' ); ?>>
							<?php esc_html_e( 'Right away (as soon as the class is published)', 'dish-events' ); ?>
						</label>
						<label style="display:block">
							<input type="radio" name="booking_starts_mode" value="days_before" <?php checked( $bs_mode, 'days_before' ); ?>>
							<?php esc_html_e( 'N days before the class starts', 'dish-events' ); ?>
						</label>
						<p id="booking-starts-days-row" style="margin-top:8px;<?php echo $bs_mode !== 'days_before' ? 'display:none' : ''; ?>">
							<input type="number" name="booking_starts_days" value="<?php echo absint( $bs_days ); ?>" min="1" max="365" class="small-text">
							<?php esc_html_e( 'days before start', 'dish-events' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Show booking dates', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="type_show_booking_dates" value="1" <?php checked( $show_booking_dates ); ?>>
							<?php esc_html_e( 'Display booking open/close dates to customers on frontend', 'dish-events' ); ?>
						</label>
					</td>
				</tr>

				<!-- Status -->
				<tr>
					<th><?php esc_html_e( 'Status', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="type_is_active" value="1" <?php checked( $is_active ); ?>>
							<?php esc_html_e( 'Active — available in class template dropdowns', 'dish-events' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php
			// Show the auto-create option on create, OR on edit when no template is linked yet.
			$has_linked_template = $id > 0 && ! empty( get_posts( [
				'post_type'      => 'dish_class_template',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => [ [
					'key'   => 'dish_ticket_type_id',
					'value' => $id,
					'type'  => 'NUMERIC',
				] ],
			] ) );
			?>
			<?php if ( ! $has_linked_template ) : ?>
			<h2><?php esc_html_e( 'Class Template', 'dish-events' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Auto-create template', 'dish-events' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="type_create_template" value="1" checked>
							<?php esc_html_e( 'Also create a matching Class Template (draft) using this name', 'dish-events' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Skipped automatically if a template linked to this ticket type already exists.', 'dish-events' ); ?></p>
					</td>
				</tr>
			</table>
			<?php endif; ?>

			<?php submit_button( $id ? __( 'Update Ticket Type', 'dish-events' ) : __( 'Add Ticket Type', 'dish-events' ) ); ?>
		</form>

		<script>
		(function () {
			// Repeater helper.
			function initRepeater(tableId, addBtnId, labelName, amountName, currency) {
				var tbody  = document.querySelector('#' + tableId + ' tbody');
				var addBtn = document.getElementById(addBtnId);
				if (!tbody || !addBtn) return;

				addBtn.addEventListener('click', function () {
					var tr = document.createElement('tr');
					tr.className = 'dish-fee-row';
					tr.innerHTML =
						'<td><input type="text" name="' + labelName + '[]" class="regular-text" placeholder="Label"></td>' +
						'<td><span style="display:inline-flex;align-items:center;gap:4px;">' + currency +
						'<input type="number" name="' + amountName + '[]" min="0" step="0.01" style="width:90px" value="0.00">' +
						'</span></td>' +
						'<td><button type="button" class="button dish-remove-fee-row">\u2715</button></td>';
					tbody.appendChild(tr);
				});

				tbody.addEventListener('click', function (e) {
					if (e.target.classList.contains('dish-remove-fee-row')) {
						e.target.closest('tr').remove();
					}
				});
			}

			initRepeater(
				'dish-per-ticket-fees', 'dish-add-ticket-fee',
				'per_ticket_fee_label', 'per_ticket_fee_amount',
				<?php echo wp_json_encode( Settings::get( 'currency_symbol', '$' ) ); ?>
			);
			initRepeater(
				'dish-per-booking-fees', 'dish-add-booking-fee',
				'per_booking_fee_label', 'per_booking_fee_amount',
				<?php echo wp_json_encode( Settings::get( 'currency_symbol', '$' ) ); ?>
			);

			// Format → default capacity pre-fill.
			var formatSel = document.getElementById('type_category_id');
			var capInput  = document.getElementById('type_capacity');
			if (formatSel && capInput) {
				formatSel.addEventListener('change', function () {
					var opt = this.options[this.selectedIndex];
					var cap = opt ? opt.getAttribute('data-default-capacity') : '';
					if (cap) capInput.value = cap;
				});
			}

			// booking_starts mode toggle.
			document.querySelectorAll('[name="booking_starts_mode"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					var row = document.getElementById('booking-starts-days-row');
					if (row) row.style.display = this.value === 'days_before' ? '' : 'none';
				});
			});
		})();
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save handlers
	// -------------------------------------------------------------------------

	private function handle_save(): void {
		check_admin_referer( self::NONCE_SAVE );

		$id              = absint( $_POST['type_id'] ?? 0 );
		$name            = sanitize_text_field( wp_unslash( $_POST['type_name'] ?? '' ) );
		$format_term_id  = absint( $_POST['type_category_id'] ?? 0 );
		$price_cents  = (int) round( (float) ( $_POST['type_price'] ?? 0 ) * 100 );
		$sale_raw     = trim( (string) ( $_POST['type_sale_price'] ?? '' ) );
		$sale_cents   = $sale_raw !== '' ? (int) round( (float) $sale_raw * 100 ) : null;
		$capacity     = isset( $_POST['type_capacity'] ) && $_POST['type_capacity'] !== ''
			? absint( $_POST['type_capacity'] )
			: null;
		$show_remaining     = ! empty( $_POST['type_show_remaining'] ) ? 1 : 0;
		$min_per_booking    = max( 1, absint( $_POST['type_min_per_booking'] ?? 1 ) );
		$show_booking_dates = ! empty( $_POST['type_show_booking_dates'] ) ? 1 : 0;
		$is_active          = ! empty( $_POST['type_is_active'] ) ? 1 : 0;
		$now                = current_time( 'mysql', true );
		$base               = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG );

		if ( empty( $name ) || ! $format_term_id ) {
			wp_safe_redirect( add_query_arg( [
				'action' => $id ? 'edit' : 'add',
				'id'     => $id ?: '',
				'error'  => 'missing_fields',
			], $base ) );
			exit;
		}

		// Build per-ticket fees JSON.
		$per_ticket_fees = [];
		$pt_labels  = array_map( 'sanitize_text_field', (array) ( $_POST['per_ticket_fee_label'] ?? [] ) );
		$pt_amounts = (array) ( $_POST['per_ticket_fee_amount'] ?? [] );
		foreach ( $pt_labels as $i => $label ) {
			if ( '' === $label ) continue;
			$per_ticket_fees[] = [
				'label'        => $label,
				'amount_cents' => (int) round( (float) ( $pt_amounts[ $i ] ?? 0 ) * 100 ),
			];
		}

		// Build per-booking fees JSON.
		$per_booking_fees = [];
		$pb_labels  = array_map( 'sanitize_text_field', (array) ( $_POST['per_booking_fee_label'] ?? [] ) );
		$pb_amounts = (array) ( $_POST['per_booking_fee_amount'] ?? [] );
		foreach ( $pb_labels as $i => $label ) {
			if ( '' === $label ) continue;
			$per_booking_fees[] = [
				'label'        => $label,
				'amount_cents' => (int) round( (float) ( $pb_amounts[ $i ] ?? 0 ) * 100 ),
			];
		}

		// Build booking_starts JSON.
		$bs_mode = sanitize_key( $_POST['booking_starts_mode'] ?? 'immediate' );
		if ( 'days_before' === $bs_mode ) {
			$booking_starts = wp_json_encode( [
				'mode' => 'days_before',
				'days' => max( 1, absint( $_POST['booking_starts_days'] ?? 14 ) ),
			] );
		} else {
			$booking_starts = wp_json_encode( [ 'mode' => 'immediate' ] );
		}

		$data = [
			'format_id'          => $format_term_id,
			'name'               => $name,
			'price_cents'        => $price_cents,
			'sale_price_cents'   => $sale_cents,
			'capacity'           => $capacity,
			'show_remaining'     => $show_remaining,
			'min_per_booking'    => $min_per_booking,
			'per_ticket_fees'    => $per_ticket_fees ? wp_json_encode( $per_ticket_fees ) : null,
			'per_booking_fees'   => $per_booking_fees ? wp_json_encode( $per_booking_fees ) : null,
			'booking_starts'     => $booking_starts,
			'show_booking_dates' => $show_booking_dates,
			'is_active'          => $is_active,
			'updated_at'         => $now,
		];

		$formats = [ '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s' ];

		global $wpdb;

		if ( $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $wpdb->prefix . 'dish_ticket_types', $data, [ 'id' => $id ], $formats, [ '%d' ] );

			// Optionally auto-create a matching Class Template draft (edit path — no template linked yet).
			if ( ! empty( $_POST['type_create_template'] ) ) {
				$existing = get_posts( [
					'post_type'      => 'dish_class_template',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [ [
						'key'   => 'dish_ticket_type_id',
						'value' => $id,
						'type'  => 'NUMERIC',
					] ],
				] );

				if ( empty( $existing ) ) {
					$template_id = wp_insert_post( [
						'post_type'   => 'dish_class_template',
						'post_title'  => $name,
						'post_status' => 'draft',
					] );

					if ( $template_id && ! is_wp_error( $template_id ) ) {
						ClassTemplateRepository::set_meta( $template_id, 'dish_ticket_type_id', $id );
						ClassTemplateRepository::set_meta( $template_id, 'dish_format_id', $format_term_id );

						wp_safe_redirect( add_query_arg( 'dish_from_ticket_type', '1', get_edit_post_link( $template_id, 'url' ) ) );
						exit;
					}
				}
			}
		} else {
			$data['created_at'] = $now;
			$formats[]          = '%s';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert( $wpdb->prefix . 'dish_ticket_types', $data, $formats );
			$new_type_id = (int) $wpdb->insert_id;

			// Optionally auto-create a matching Class Template draft.
			if ( $new_type_id > 0 && ! empty( $_POST['type_create_template'] ) ) {
				$existing = get_posts( [
					'post_type'      => 'dish_class_template',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => [ [
						'key'   => 'dish_ticket_type_id',
						'value' => $new_type_id,
						'type'  => 'NUMERIC',
					] ],
				] );

				if ( empty( $existing ) ) {
					$template_id = wp_insert_post( [
						'post_type'   => 'dish_class_template',
						'post_title'  => $name,
						'post_status' => 'draft',
					] );

					if ( $template_id && ! is_wp_error( $template_id ) ) {
						ClassTemplateRepository::set_meta( $template_id, 'dish_ticket_type_id', $new_type_id );
						ClassTemplateRepository::set_meta( $template_id, 'dish_format_id', $format_term_id );

						// Drop straight into the new template so the admin can complete it.
						wp_safe_redirect( add_query_arg( 'dish_from_ticket_type', '1', get_edit_post_link( $template_id, 'url' ) ) );
						exit;
					}
				}
			}
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', $base ) );
		exit;
	}

	private function handle_toggle( int $id ): void {
		check_admin_referer( 'dish_type_toggle_' . $id );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT is_active FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d",
			$id
		) );

		if ( $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'dish_ticket_types',
				[ 'is_active' => $row->is_active ? 0 : 1, 'updated_at' => current_time( 'mysql', true ) ],
				[ 'id' => $id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG . '&updated=1' ) );
		exit;
	}

	private function handle_duplicate( int $id ): void {
		check_admin_referer( 'dish_type_duplicate_' . $id );

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}dish_ticket_types WHERE id = %d",
			$id
		) );

		if ( ! $row ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG ) );
			exit;
		}

		$now  = current_time( 'mysql', true );
		$data = [
			'format_id'          => $row->format_id,
			'name'               => sprintf(
				/* translators: %s: original ticket type name */
				__( 'Copy of %s', 'dish-events' ),
				wp_unslash( $row->name )
			),
			'price_cents'        => $row->price_cents,
			'sale_price_cents'   => $row->sale_price_cents,
			'capacity'           => $row->capacity,
			'show_remaining'     => $row->show_remaining,
			'min_per_booking'    => $row->min_per_booking,
			'per_ticket_fees'    => $row->per_ticket_fees,
			'per_booking_fees'   => $row->per_booking_fees,
			'booking_starts'     => $row->booking_starts,
			'show_booking_dates' => $row->show_booking_dates,
			'is_active'          => 0, // Inactive until the admin renames and activates.
			'created_at'         => $now,
			'updated_at'         => $now,
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert( $wpdb->prefix . 'dish_ticket_types', $data, [
			'%d', // format_id
			'%s', // name
			'%d', // price_cents
			'%d', // sale_price_cents
			'%d', // capacity
			'%d', // show_remaining
			'%d', // min_per_booking
			'%s', // per_ticket_fees
			'%s', // per_booking_fees
			'%s', // booking_starts
			'%d', // show_booking_dates
			'%d', // is_active
			'%s', // created_at
			'%s', // updated_at
		] );
		$new_id = (int) $wpdb->insert_id;

		// Drop straight into the edit form so the admin can rename it.
		$edit_url = add_query_arg(
			[ 'action' => 'edit', 'id' => $new_id, 'duplicated' => '1' ],
			admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG )
		);

		wp_safe_redirect( $edit_url );
		exit;
	}

	private function handle_delete( int $id ): void {
		check_admin_referer( 'dish_type_delete_' . $id );

		$base = admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG );

		// Block deletion if any Class Template references this ticket type.
		$linked_templates = get_posts( [
			'post_type'      => 'dish_class_template',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'   => 'dish_ticket_type_id',
				'value' => $id,
				'type'  => 'NUMERIC',
			] ],
		] );

		// Block deletion if any Booking references this ticket type.
		$linked_bookings = get_posts( [
			'post_type'      => 'dish_booking',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'   => 'dish_ticket_type_id',
				'value' => $id,
				'type'  => 'NUMERIC',
			] ],
		] );

		if ( ! empty( $linked_templates ) || ! empty( $linked_bookings ) ) {
			wp_safe_redirect( add_query_arg( 'error', 'in_use', $base ) );
			exit;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $wpdb->prefix . 'dish_ticket_types', [ 'id' => $id ], [ '%d' ] );

		wp_safe_redirect( add_query_arg( 'deleted', '1', $base ) );
		exit;
	}

	private function handle_bulk( string $action ): void {
		check_admin_referer( 'bulk-ticket_types' );

		$ids        = array_map( 'absint', (array) ( $_REQUEST['ids'] ?? [] ) );
		$new_active = ( 'activate' === $action ) ? 1 : 0;

		if ( empty( $ids ) ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG ) );
			exit;
		}

		global $wpdb;
		$now = current_time( 'mysql', true );

		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'dish_ticket_types',
				[ 'is_active' => $new_active, 'updated_at' => $now ],
				[ 'id' => $id ],
				[ '%d', '%s' ],
				[ '%d' ]
			);
		}

		wp_safe_redirect( admin_url( 'edit.php?post_type=dish_class&page=' . self::PAGE_SLUG . '&updated=1' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Utilities
	// -------------------------------------------------------------------------

	/**
	 * Format an integer cent value as a display price string.
	 * Uses the currency symbol from plugin settings.
	 *
	 * @param int $cents Amount in cents.
	 * @return string e.g. "$45.00"
	 */
	public static function cents_to_display( int $cents ): string {
		$symbol   = Settings::get( 'currency_symbol', '$' );
		$position = Settings::get( 'currency_position', 'before' );
		$amount   = number_format( $cents / 100, 2 );

		return 'before' === $position ? $symbol . $amount : $amount . $symbol;
	}
}
