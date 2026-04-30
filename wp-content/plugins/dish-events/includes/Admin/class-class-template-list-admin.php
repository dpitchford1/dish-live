<?php
/**
 * Class Template list admin page.
 *
 * Replaces the default WP CPT list table for dish_class_template with a
 * custom admin page that groups templates by Format — mirroring the layout
 * of the Ticketing (TicketTypeAdmin) list.
 *
 * Row actions link to WP's native post editor (post.php / post-new.php) so
 * all existing meta boxes continue to work without any changes.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

/**
 * Class ClassTemplateListAdmin
 */
final class ClassTemplateListAdmin {

	const PAGE_SLUG = 'dish-class-templates';

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	public function add_pages(): void {
		add_submenu_page(
			'edit.php?post_type=dish_class',
			__( 'Class Templates', 'dish-events' ),
			__( 'Class Templates', 'dish-events' ),
			'edit_posts',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);

		// Remove the auto-generated WP CPT list from the nav — the custom grouped
		// page above replaces it. The underlying URL still works for direct access.
		remove_submenu_page( 'edit.php?post_type=dish_class', 'edit.php?post_type=dish_class_template' );
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		echo '<div class="wrap">';
		$this->render_list();
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// List renderer
	// -------------------------------------------------------------------------

	private function render_list(): void {
		$add_new_url = admin_url( 'post-new.php?post_type=dish_class_template' );

		// All published formats ordered by menu_order then title.
		$formats = get_posts( [
			'post_type'   => 'dish_format',
			'post_status' => 'publish',
			'numberposts' => -1,
			'orderby'     => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		// All class templates — one query, PHP grouping.
		$all_templates = get_posts( [
			'post_type'              => 'dish_class_template',
			'post_status'            => [ 'publish', 'draft', 'pending' ],
			'numberposts'            => -1,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		// Index by format_id.
		$by_format = [];
		foreach ( $all_templates as $tpl ) {
			$fid = (int) get_post_meta( $tpl->ID, 'dish_format_id', true );
			$by_format[ $fid ][] = $tpl;
		}
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Class Templates', 'dish-events' ); ?></h1>
		<a href="<?php echo esc_url( $add_new_url ); ?>" class="page-title-action">
			<?php esc_html_e( 'Add New', 'dish-events' ); ?>
		</a>
		<hr class="wp-header-end">

		<?php if ( ! empty( $_GET['trashed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Template moved to Trash.', 'dish-events' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $all_templates ) ) : ?>
			<p><?php esc_html_e( 'No class templates found. Add one to get started.', 'dish-events' ); ?></p>
			<?php return; ?>
		<?php endif; ?>

		<?php
		$rendered_ids = [];

		foreach ( $formats as $format ) {
			$rows = $by_format[ $format->ID ] ?? [];
			if ( empty( $rows ) ) {
				continue;
			}
			$color = (string) get_post_meta( $format->ID, 'dish_format_color', true ) ?: '#c0392b';
			foreach ( $rows as $r ) {
				$rendered_ids[] = $r->ID;
			}
			$this->render_format_section( $format->post_title, $color, $rows );
		}

		// Templates not assigned to any published format.
		$unassigned = array_filter( $all_templates, fn( $r ) => ! in_array( $r->ID, $rendered_ids, true ) );
		if ( ! empty( $unassigned ) ) {
			$this->render_format_section( __( 'Unassigned', 'dish-events' ), '#787c82', array_values( $unassigned ) );
		}
	}

	/**
	 * Render one format section heading + templates table.
	 *
	 * @param string    $heading  Format display name.
	 * @param string    $color    Hex colour for the left-border accent.
	 * @param \WP_Post[] $rows    Template posts in this section.
	 */
	private function render_format_section( string $heading, string $color, array $rows ): void {
		global $wpdb;

		// Pre-fetch ticket type names for all templates in this section in one query.
		$ticket_ids = array_filter( array_map(
			fn( $t ) => (int) get_post_meta( $t->ID, 'dish_ticket_type_id', true ),
			$rows
		) );

		$ticket_names = [];
		if ( ! empty( $ticket_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $ticket_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT id, name FROM {$wpdb->prefix}dish_ticket_types WHERE id IN ($placeholders)",
					...$ticket_ids
				)
			);
			foreach ( $results as $r ) {
				$ticket_names[ (int) $r->id ] = $r->name;
			}
		}
		?>
		<div style="margin-top:2em">
			<h2 style="border-left:4px solid <?php echo esc_attr( $color ); ?>;padding-left:10px;margin-bottom:.5em">
				<?php echo esc_html( $heading ); ?>
				<span style="font-size:13px;font-weight:400;color:#787c82;margin-left:8px">(<?php echo count( $rows ); ?>)</span>
			</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
					<th style="width:35%"><?php esc_html_e( 'Title', 'dish-events' ); ?></th>
						<th style="width:20%"><?php esc_html_e( 'Ticket Type', 'dish-events' ); ?></th>
						<th style="width:10%"><?php esc_html_e( 'Featured', 'dish-events' ); ?></th>
						<th style="width:12%"><?php esc_html_e( 'Status', 'dish-events' ); ?></th>
						<th style="width:15%"><?php esc_html_e( 'Date', 'dish-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $rows as $tpl ) :
					$edit_url  = get_edit_post_link( $tpl->ID );
					$view_url  = get_permalink( $tpl->ID );
					$trash_url = get_delete_post_link( $tpl->ID );

					$tid          = (int) get_post_meta( $tpl->ID, 'dish_ticket_type_id', true );
					$ticket_label = $tid && isset( $ticket_names[ $tid ] ) ? $ticket_names[ $tid ] : '—';

					$booking_type = (string) get_post_meta( $tpl->ID, 'dish_booking_type', true );
					if ( $booking_type === 'enquiry' ) {
						$ticket_label = __( 'By Request', 'dish-events' );
					}

					$status_label = [
						'publish' => __( 'Published', 'dish-events' ),
						'draft'   => __( 'Draft',     'dish-events' ),
						'pending' => __( 'Pending',   'dish-events' ),
					][ $tpl->post_status ] ?? esc_html( $tpl->post_status );

					$status_color = $tpl->post_status === 'publish' ? '#00a32a' : '#787c82';
					$is_featured  = (bool) get_post_meta( $tpl->ID, 'dish_is_featured', true );
				?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( $edit_url ); ?>">
									<?php echo esc_html( $tpl->post_title ?: __( '(no title)', 'dish-events' ) ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'dish-events' ); ?></a>
								</span>
								<?php if ( $tpl->post_status === 'publish' && $view_url ) : ?>
									&nbsp;|&nbsp;
									<span class="view">
										<a href="<?php echo esc_url( $view_url ); ?>" target="_blank" rel="noopener">
											<?php esc_html_e( 'View', 'dish-events' ); ?>
										</a>
									</span>
								<?php endif; ?>
								<?php if ( $trash_url ) : ?>
									&nbsp;|&nbsp;
									<span class="trash">
										<a href="<?php echo esc_url( $trash_url ); ?>"
										   style="color:#b32d2e"
										   onclick="return confirm('<?php echo esc_js( __( 'Move this template to Trash?', 'dish-events' ) ); ?>')">
											<?php esc_html_e( 'Trash', 'dish-events' ); ?>
										</a>
									</span>
								<?php endif; ?>
							</div>
						</td>
						<td><?php echo esc_html( $ticket_label ); ?></td>
						<td>
							<?php if ( $is_featured ) : ?>
								<span style="color:#92400e;background:#fef9c3;padding:2px 8px;border-radius:3px;font-size:11px;font-weight:600;">&#9733; <?php esc_html_e( 'Featured', 'dish-events' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<span style="color:<?php echo esc_attr( $status_color ); ?>">
								&#9679; <?php echo esc_html( $status_label ); ?>
							</span>
						</td>
						<td>
							<?php echo esc_html( get_the_date( 'j M Y', $tpl ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
