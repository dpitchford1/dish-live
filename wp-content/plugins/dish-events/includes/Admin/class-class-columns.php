<?php
/**
 * Custom columns, filters, and bulk actions for the dish_class list table.
 *
 * Columns added
 * -------------
 *  - dish_date     : formatted start date/time (sortable)
 *  - dish_chefs    : linked chef names
 *  - dish_format   : class format taxonomy terms
 *  - dish_status   : custom post status badge
 *
 * Filters
 * -------
 *  - Format dropdown (restrict_manage_posts)
 *
 * Bulk Actions
 * ------------
 *  - duplicate : creates a copy of the class without any bookings
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\ClassTemplateRepository;
use Dish\Events\Data\TicketTypeRepository;
use Dish\Events\Helpers\MoneyHelper;

/**
 * Class ClassColumns
 */
final class ClassColumns {

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Wire all list-table hooks.
	 * Called from Admin::register_hooks().
	 */
	public function register_hooks( \Dish\Events\Core\Loader $loader ): void {
		$loader->add_filter( 'manage_dish_class_posts_columns',       $this, 'add_columns' );
		$loader->add_action( 'manage_dish_class_posts_custom_column', $this, 'render_column', 10, 2 );
		$loader->add_filter( 'manage_edit-dish_class_sortable_columns', $this, 'sortable_columns' );
		$loader->add_action( 'pre_get_posts',                          $this, 'handle_sort' );
		$loader->add_action( 'restrict_manage_posts',                  $this, 'render_format_filter' );
		$loader->add_filter( 'bulk_actions-edit-dish_class',           $this, 'add_bulk_actions' );
		$loader->add_filter( 'handle_bulk_actions-edit-dish_class',    $this, 'handle_bulk_actions', 10, 3 );
		$loader->add_action( 'admin_notices',                          $this, 'bulk_action_notice' );
	}

	// -------------------------------------------------------------------------
	// Columns
	// -------------------------------------------------------------------------

	/**
	 * Define the column set for the dish_class list table.
	 *
	 * @param  array<string,string> $columns Default columns.
	 * @return array<string,string>
	 */
	public function add_columns( array $columns ): array {
		// Keep cb and title; discard the rest; append ours in a logical order.
		$new = [
			'cb'          => $columns['cb'],
			'dish_thumb'  => '',
			'title'       => $columns['title'],
			'dish_series'     => __( 'Series', 'dish-events' ),
			'dish_date'   => __( 'Class Date', 'dish-events' ),
			'dish_chefs'  => __( 'Chef(s)', 'dish-events' ),
			'dish_format'     => __( 'Format', 'dish-events' ),
			'dish_price'      => __( 'Price', 'dish-events' ),
			'dish_status'     => __( 'Status', 'dish-events' ),
			'dish_visibility' => __( 'Visibility', 'dish-events' ),
			'dish_capacity'   => __( 'Booked', 'dish-events' ),
		];

		// Re-append the native published date at the end if present.
		if ( isset( $columns['date'] ) ) {
			$new['date'] = $columns['date'];
		}

		return $new;
	}

	/**
	 * Render each custom column cell.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( string $column, int $post_id ): void {
		 switch ( $column ) {

			case 'dish_series':
				$parent_id   = (int) ClassRepository::get_meta( $post_id, 'dish_recurrence_parent_id', 0 );
				$rule        = json_decode( (string) ClassRepository::get_meta( $post_id, 'dish_recurrence' ), true );
				$child_ids   = is_array( $rule['child_ids'] ?? null ) ? $rule['child_ids'] : [];
				if ( $parent_id ) {
					printf(
						'<a href="%s" style="color:#646970;font-size:11px;white-space:nowrap">&#8629; Child of #%d</a>',
						esc_url( get_edit_post_link( $parent_id ) ?? '' ),
						$parent_id
					);
				} elseif ( ! empty( $child_ids ) ) {
					printf(
						'<span style="color:#2271b1;font-size:11px;font-weight:600;white-space:nowrap">&#8645; Parent &middot; %d instances</span>',
						count( $child_ids )
					);
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

		case 'dish_thumb':
			$thumb = get_the_post_thumbnail( $post_id, [ 60, 60 ] );
			if ( $thumb ) {
				printf(
					'<a href="%s" style="display:block;line-height:0">%s</a>',
					esc_url( get_edit_post_link( $post_id ) ?? '' ),
					$thumb // phpcs:ignore WordPress.Security.EscapeOutput -- WP-generated markup
				);
			} else {
				echo '<span style="color:#999">—</span>';
			}
			break;

		case 'dish_date':
					$start = (int) ClassRepository::get_meta( $post_id, 'dish_start_datetime', 0 );
					$end   = (int) ClassRepository::get_meta( $post_id, 'dish_end_datetime', 0 );
				if ( $start > 0 ) {
					$tz  = new \DateTimeZone( wp_timezone_string() );
					$dt  = ( new \DateTimeImmutable( '@' . $start ) )->setTimezone( $tz );
					echo esc_html( $dt->format( 'D, M j Y' ) );
					$time_str = $dt->format( 'g:i a' );
					if ( $end > 0 ) {
						$dt_end    = ( new \DateTimeImmutable( '@' . $end ) )->setTimezone( $tz );
						$time_str .= ' – ' . $dt_end->format( 'g:i a' );
					}
					echo '<br><span style="color:#646970">' . esc_html( $time_str ) . '</span>';
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_chefs':
				$ids  = json_decode( (string) ClassRepository::get_meta( $post_id, 'dish_chef_ids' ), true );
				$ids  = is_array( $ids ) ? array_filter( array_map( 'intval', $ids ) ) : [];
				if ( empty( $ids ) ) {
					echo '<span style="color:#999">—</span>';
					break;
				}
				$links = [];
				foreach ( $ids as $chef_id ) {
					$chef = get_post( $chef_id );
					if ( $chef instanceof \WP_Post ) {
						$links[] = '<a href="' . esc_url( get_edit_post_link( $chef_id ) ?? '' ) . '">'
							. esc_html( $chef->post_title ) . '</a>';
					}
				}
				echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- already escaped above
				break;

			case 'dish_price':
				$tpl_id  = (int) ClassRepository::get_meta( $post_id, 'dish_template_id', 0 );
				$type_id = $tpl_id ? (int) ClassTemplateRepository::get_meta( $tpl_id, 'dish_ticket_type_id', 0 ) : 0;
				if ( $type_id ) {
					$ticket = TicketTypeRepository::get( $type_id );
					if ( $ticket && isset( $ticket->price_cents ) ) {
						echo esc_html( MoneyHelper::cents_to_display( (int) $ticket->price_cents ) );
						break;
					}
				}
				echo '<span style="color:#999">—</span>';
				break;

			case 'dish_format':
				// Format lives on the template, not directly on the class instance.
				$tpl_id    = (int) ClassRepository::get_meta( $post_id, 'dish_template_id', 0 );
				$fmt_id    = $tpl_id ? (int) ClassTemplateRepository::get_meta( $tpl_id, 'dish_format_id', 0 ) : 0;
				$fmt_post  = $fmt_id ? get_post( $fmt_id ) : null;
				if ( $fmt_post instanceof \WP_Post ) {
					echo esc_html( $fmt_post->post_title );
				} else {
					echo '<span style="color:#999">—</span>';
				}
				break;

			case 'dish_status':
				$status = get_post_status( $post_id );
				$labels = [
					'publish'        => [ __( 'Published', 'dish-events' ),  '#0a5' ],
					'draft'          => [ __( 'Draft', 'dish-events' ),       '#888' ],
					'dish_expired'   => [ __( 'Expired', 'dish-events' ),     '#b00' ],
					'dish_cancelled' => [ __( 'Cancelled', 'dish-events' ),   '#c60' ],
					'pending'        => [ __( 'Pending', 'dish-events' ),     '#06a' ],
					'private'        => [ __( 'Private', 'dish-events' ),     '#888' ],
				];
				[ $label, $colour ] = $labels[ $status ] ?? [ ucfirst( (string) $status ), '#888' ];
				printf(
					'<span style="color:%s;font-weight:600">%s</span>',
					esc_attr( $colour ),
					esc_html( $label )
				);
				break;

			case 'dish_visibility':
				$is_private = (bool) ClassRepository::get_meta( $post_id, 'dish_is_private' );
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

			case 'dish_capacity':
				// dish_ticket_type_id lives on the template, not the class instance.
				$tpl_id   = (int) ClassRepository::get_meta( $post_id, 'dish_template_id', 0 );
				$type_id  = $tpl_id ? (int) ClassTemplateRepository::get_meta( $tpl_id, 'dish_ticket_type_id', 0 ) : 0;
				$booked   = ClassRepository::get_booked_count( $post_id );
				$capacity = null;
				if ( $type_id ) {
					$ticket   = TicketTypeRepository::get( $type_id );
					$capacity = $ticket ? ( isset( $ticket->capacity ) ? (int) $ticket->capacity : null ) : null;
				}
				if ( $capacity && $booked >= $capacity ) {
					echo '<span style="color:#b00;font-weight:600">' . esc_html__( 'Sold out', 'dish-events' ) . '</span>';
				} elseif ( $capacity ) {
					printf( '%d / %d', $booked, $capacity );
				} else {
					printf( '%d / —', $booked );
				}
				break;
		}
	}

	// -------------------------------------------------------------------------
	// Sortable date column
	// -------------------------------------------------------------------------

	/**
	 * Declare the date column as sortable.
	 *
	 * @param  array<string,string|array<int,string|bool>> $columns
	 * @return array<string,string|array<int,string|bool>>
	 */
	public function sortable_columns( array $columns ): array {
		$columns['dish_date'] = 'dish_date';
		return $columns;
	}

	/**
	 * Modify the query when sorting by dish_date.
	 *
	 * @param \WP_Query $query
	 */
	public function handle_sort( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( $query->get( 'post_type' ) !== 'dish_class' ) {
			return;
		}
		if ( $query->get( 'orderby' ) === 'dish_date' ) {
			$query->set( 'meta_key', 'dish_start_datetime' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	// -------------------------------------------------------------------------
	// Format filter
	// -------------------------------------------------------------------------

	/**
	 * Render the format dropdown above the list table.
	 *
	 * @param string $post_type Current post type.
	 */
	public function render_format_filter( string $post_type ): void {
		if ( $post_type !== 'dish_class' ) {
			return;
		}

		$terms = get_terms( [
			'taxonomy'   => 'dish_class_format',
			'hide_empty' => true,
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}

		$selected = sanitize_key( $_GET['dish_class_format'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		?>
		<select name="dish_class_format" id="filter-by-dish-format">
			<option value=""><?php esc_html_e( 'All formats', 'dish-events' ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>>
					<?php echo esc_html( $term->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	// -------------------------------------------------------------------------
	// Bulk actions
	// -------------------------------------------------------------------------

	/**
	 * Add the Duplicate bulk action.
	 *
	 * @param  array<string,string> $actions
	 * @return array<string,string>
	 */
	public function add_bulk_actions( array $actions ): array {
		$actions['dish_duplicate'] = __( 'Duplicate', 'dish-events' );
		return $actions;
	}

	/**
	 * Handle the Duplicate bulk action.
	 *
	 * @param  string        $redirect_url URL to redirect to after action.
	 * @param  string        $action       Action slug.
	 * @param  array<int>    $post_ids     Selected post IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( string $redirect_url, string $action, array $post_ids ): string {
		if ( $action !== 'dish_duplicate' ) {
			return $redirect_url;
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			if ( $this->duplicate_class( $post_id ) ) {
				$count++;
			}
		}

		$redirect_url = add_query_arg( 'dish_duplicated', $count, $redirect_url );
		return $redirect_url;
	}

	/**
	 * Show the admin notice after a duplicate bulk action.
	 */
	public function bulk_action_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'dish_class' ) {
			return;
		}
		if ( empty( $_GET['dish_duplicated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$count = absint( $_GET['dish_duplicated'] ); // phpcs:ignore WordPress.Security.NonceVerification
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				/* translators: %d: number of duplicated classes */
				sprintf( _n( '%d class duplicated.', '%d classes duplicated.', $count, 'dish-events' ), $count )
			)
		);
	}

	// -------------------------------------------------------------------------
	// Duplicate helper
	// -------------------------------------------------------------------------

	/**
	 * Duplicate a dish_class post: copy post fields + all meta.
	 * The duplicate is saved as a draft with "(Copy)" appended to the title.
	 * Booking-related meta (dish_chef_ids is kept; booking counts are live queries
	 * so nothing to copy). Recurrence child_ids are NOT copied.
	 *
	 * @param  int $post_id Original post ID.
	 * @return int|false New post ID or false on failure.
	 */
	private function duplicate_class( int $post_id ): int|false {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$new_id = wp_insert_post( [
			'post_title'   => $post->post_title . ' ' . __( '(Copy)', 'dish-events' ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => 'draft',
			'post_type'    => 'dish_class',
			'post_author'  => $post->post_author,
		] );

		if ( is_wp_error( $new_id ) || $new_id === 0 ) {
			return false;
		}

		// Copy all meta except recurrence parent/child links.
		$skip_meta = [ 'dish_recurrence_parent_id' ];
		$all_meta  = ClassRepository::get_all_meta( $post_id );

		foreach ( $all_meta as $key => $values ) {
			if ( in_array( $key, $skip_meta, true ) ) {
				continue;
			}
			if ( $key === 'dish_recurrence' ) {
				// Strip child_ids from the copied rule so it starts fresh.
				$rule = json_decode( (string) ( $values[0] ?? '' ), true );
				if ( is_array( $rule ) ) {
					$rule['child_ids'] = [];
					ClassRepository::set_meta( $new_id, $key, wp_json_encode( $rule ) );
				}
				continue;
			}
			foreach ( $values as $value ) {
				add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
			}
		}

		// Copy taxonomy terms.
		$taxonomies = get_object_taxonomies( 'dish_class' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				wp_set_object_terms( $new_id, $terms, $taxonomy );
			}
		}

		// Copy featured image.
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			set_post_thumbnail( $new_id, $thumb_id );
		}

		return $new_id;
	}
}
