<?php
/**
 * Template: [dish_profile] — logged-in user profile & booking history.
 *
 * Shows the current user's upcoming and past bookings. Redirects (or shows a
 * login notice) when the visitor is not authenticated.
 *
 * Phase 9 note: this template will be expanded to include:
 *   – Full booking details (ticket type, amount paid, QR / reference)
 *   – Cancellation links
 *   – Downloadable invoice / receipt
 *
 * Theme override: {theme}/dish-events/account/profile.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\BookingRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

if ( ! is_user_logged_in() ) :
	$login_page = (int) \Dish\Events\Admin\Settings::get( 'login_page' );
	$login_url  = $login_page ? get_permalink( $login_page ) : wp_login_url( get_permalink() );
	?>
	<div class="dish-account dish-account--profile">
		<p class="dish-account__notice">
			<?php
			printf(
				wp_kses(
					/* translators: %s: login URL */
					__( 'Please <a href="%s">sign in</a> to view your profile.', 'dish-events' ),
					[ 'a' => [ 'href' => [] ] ]
				),
				esc_url( $login_url )
			);
			?>
		</p>
	</div>
	<?php
	return;
endif;

$user          = wp_get_current_user();
$email         = $user->user_email;
$dish_settings = (array) get_option( 'dish_settings', [] );
// Pass user ID so bookings linked via dish_customer_user_id are also included.
$bookings = BookingRepository::get_for_customer( $email, 'any', (int) $user->ID );
?>
<div class="dish-account dish-account--profile">

	<header class="dish-account__header">
		<h2 class="dish-account__title">
			<?php
			printf(
				/* translators: %s: user display name */
				esc_html__( 'Welcome, %s', 'dish-events' ),
				esc_html( $user->display_name )
			);
			?>
		</h2>
	</header>

	<?php if ( empty( $bookings ) ) : ?>

		<p class="dish-no-results">
			<?php esc_html_e( "You don't have any bookings yet.", 'dish-events' ); ?>
		</p>

	<?php else : ?>

		<section class="dish-profile-bookings">
			<h3 class="dish-profile-bookings__heading">
				<?php esc_html_e( 'Your Bookings', 'dish-events' ); ?>
			</h3>

			<ul class="dish-booking-list">
				<?php foreach ( $bookings as $booking ) :
					$class_id  = (int) get_post_meta( $booking->ID, 'dish_class_id', true );
					$class_obj = $class_id ? get_post( $class_id ) : null;
					$start     = $class_id ? (int) get_post_meta( $class_id, 'dish_start_datetime', true ) : 0;

					// Template title for the class.
					$template_id  = $class_id ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
					$template_obj = $template_id ? get_post( $template_id ) : null;
					$class_title  = $template_obj
						? $template_obj->post_title
						: ( $class_obj ? $class_obj->post_title : __( 'Class', 'dish-events' ) );

					$status      = get_post_status( $booking->ID );
					$qty         = (int) get_post_meta( $booking->ID, 'dish_ticket_qty', true );
					$total_cents = (int) get_post_meta( $booking->ID, 'dish_ticket_total_cents', true );

					// Build the booking details URL.
					$details_page_id = (int) ( $dish_settings['booking_details_page'] ?? 0 );
					$booking_key     = BookingRepository::ensure_booking_key( $booking->ID );
					$details_url     = $details_page_id
						? add_query_arg(
							[ 'booking_id' => $booking->ID, 'key' => $booking_key ],
							get_permalink( $details_page_id )
						)
						: '';
				?>
					<li class="dish-booking-list__item dish-booking-list__item--<?php echo esc_attr( $status ); ?>">

						<span class="dish-booking-list__title">
							<?php
							// dish_class instances are non-public; link to the public template instead.
							$link_obj = $template_obj ?? $class_obj;
							?>
							<?php if ( $link_obj ) : ?>
								<a href="<?php echo esc_url( get_permalink( $link_obj->ID ) ); ?>">
									<?php echo esc_html( $class_title ); ?>
								</a>
							<?php else : ?>
								<?php echo esc_html( $class_title ); ?>
							<?php endif; ?>
						</span>

						<?php if ( $start ) : ?>
							<time class="dish-booking-list__date" datetime="<?php echo esc_attr( DateHelper::format( $start, 'c' ) ); ?>">
								<?php echo esc_html( DateHelper::to_display( $start ) ); ?>
							</time>
						<?php endif; ?>

						<span class="dish-booking-list__status dish-booking-status--<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( ucfirst( str_replace( [ 'dish_', '_' ], [ '', ' ' ], $status ) ) ); ?>
						</span>

						<?php if ( $qty ) : ?>
							<span class="dish-booking-list__qty">
								<?php echo esc_html( sprintf(
									/* translators: %d: number of tickets */
									_n( '%d ticket', '%d tickets', $qty, 'dish-events' ),
									$qty
								) ); ?>
							</span>
						<?php endif; ?>

						<?php if ( $total_cents ) : ?>
							<span class="dish-booking-list__total">
								<?php echo esc_html( MoneyHelper::cents_to_display( $total_cents ) ); ?>
							</span>
						<?php endif; ?>

						<?php if ( $details_url ) : ?>
							<a href="<?php echo esc_url( $details_url ); ?>" class="dish-booking-list__details-link">
								<?php esc_html_e( 'View booking', 'dish-events' ); ?>
							</a>
						<?php endif; ?>

					</li>
				<?php endforeach; ?>
			</ul>
		</section>

	<?php endif; ?>

	<p class="dish-account__logout">
		<a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>">
			<?php esc_html_e( 'Sign out', 'dish-events' ); ?>
		</a>
	</p>

	<?php if ( ! user_can( $user->ID, 'edit_posts' ) ) : ?>
	<section class="dish-delete-account" id="dish-delete-account">

		<details class="dish-delete-account__details">
			<summary class="dish-delete-account__summary">
				<?php esc_html_e( 'Delete my account', 'dish-events' ); ?>
			</summary>

			<div class="dish-delete-account__body">
				<p class="dish-delete-account__warning">
					<?php esc_html_e( 'This will permanently delete your account. Your booking history will be retained for our records but your personal details (name, phone number) will be removed. This cannot be undone.', 'dish-events' ); ?>
				</p>

				<div class="dish-delete-account__form">
					<label for="dish-delete-confirm-email" class="dish-delete-account__label">
						<?php esc_html_e( 'Type your email address to confirm:', 'dish-events' ); ?>
					</label>
					<input
						type="email"
						id="dish-delete-confirm-email"
						class="dish-delete-account__input"
						autocomplete="off"
						placeholder="<?php echo esc_attr( $user->user_email ); ?>"
					>
					<p class="dish-delete-account__error" id="dish-delete-error" style="display:none;color:#b32d2e;margin:.5em 0 0;"></p>
					<button type="button" id="dish-delete-account-btn" class="button button--danger" style="margin-top:.75em;">
						<?php esc_html_e( 'Permanently delete my account', 'dish-events' ); ?>
					</button>
				</div>
			</div>
		</details>

	</section>

	<script>
	( function () {
		var btn      = document.getElementById( 'dish-delete-account-btn' );
		var emailIn  = document.getElementById( 'dish-delete-confirm-email' );
		var errorEl  = document.getElementById( 'dish-delete-error' );

		if ( ! btn ) return;

		btn.addEventListener( 'click', function () {
			errorEl.style.display = 'none';
			errorEl.textContent   = '';

			var confirmEmail = emailIn.value.trim();
			if ( ! confirmEmail ) {
				errorEl.textContent   = <?php echo wp_json_encode( __( 'Please enter your email address to confirm.', 'dish-events' ) ); ?>;
				errorEl.style.display = 'block';
				emailIn.focus();
				return;
			}

			btn.disabled    = true;
			btn.textContent = <?php echo wp_json_encode( __( 'Deleting\u2026', 'dish-events' ) ); ?>;

			var data = new FormData();
			data.append( 'action',        'dish_delete_account' );
			data.append( 'nonce',         <?php echo wp_json_encode( wp_create_nonce( 'dish_delete_account' ) ); ?> );
			data.append( 'confirm_email', confirmEmail );

			fetch( <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, {
				method: 'POST',
				body:   data,
			} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json.success ) {
					window.location.href = json.data.redirect_url;
				} else {
					errorEl.textContent   = json.data.message || <?php echo wp_json_encode( __( 'Something went wrong. Please try again.', 'dish-events' ) ); ?>;
					errorEl.style.display = 'block';
					btn.disabled          = false;
					btn.textContent       = <?php echo wp_json_encode( __( 'Permanently delete my account', 'dish-events' ) ); ?>;
				}
			} )
			.catch( function () {
				errorEl.textContent   = <?php echo wp_json_encode( __( 'A network error occurred. Please try again.', 'dish-events' ) ); ?>;
				errorEl.style.display = 'block';
				btn.disabled          = false;
				btn.textContent       = <?php echo wp_json_encode( __( 'Permanently delete my account', 'dish-events' ) ); ?>;
			} );
		} );
	} )();
	</script>
	<?php endif; ?>

</div>
