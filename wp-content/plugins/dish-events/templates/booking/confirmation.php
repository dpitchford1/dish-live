<?php
/**
 * Template: Booking confirmation page.
 *
 * Served when the WordPress page assigned to `booking_details_page` in plugin
 * settings is requested with `booking_id` and `key` query parameters.
 *
 * The `key` parameter is verified via PublicAjax::verify_booking_key() so that
 * random visitors cannot enumerate other people's bookings.
 *
 * Displays:
 *   – Booking reference and status badge
 *   – Class name, date, time, and chef names
 *   – Customer name and number of tickets
 *   – Total amount (payment note: payable at studio)
 *   – Add-to-calendar links (Google Calendar + plain .ics stub)
 *   – "What happens next" guidance
 *
 * Theme override: {theme}/dish-events/booking/confirmation.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Data\BookingRepository;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Admin\Settings;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

// ── Resolve and verify the booking ───────────────────────────────────────────
$booking_id = absint( $_GET['booking_id'] ?? 0 );
$key        = preg_replace( '/[^a-f0-9]/', '', (string) ( $_GET['key'] ?? '' ) );

if ( ! $booking_id || ! BookingRepository::verify_booking_key( $booking_id, $key ) ) {
	wp_redirect( home_url( '/' ) );
	exit;
}

$booking = BookingRepository::get( $booking_id );

if ( ! $booking ) {
	wp_redirect( home_url( '/' ) );
	exit;
}

// ── Booking meta ──────────────────────────────────────────────────────────────
$customer_name  = (string) get_post_meta( $booking_id, 'dish_customer_name',      true );
$customer_email = (string) get_post_meta( $booking_id, 'dish_customer_email',     true );
$ticket_qty     = (int)    get_post_meta( $booking_id, 'dish_ticket_qty',         true );
$total_cents    = (int)    get_post_meta( $booking_id, 'dish_ticket_total_cents', true );
$class_id       = (int)    get_post_meta( $booking_id, 'dish_class_id',           true );

// ── Class metadata ────────────────────────────────────────────────────────────
$class       = $class_id ? get_post( $class_id ) : null;
$template_id = $class ? (int) get_post_meta( $class_id, 'dish_template_id', true ) : 0;
$template    = $template_id ? get_post( $template_id ) : null;
$class_title = $template ? $template->post_title : ( $class ? $class->post_title : '' );

$start_epoch = $class_id ? (int) get_post_meta( $class_id, 'dish_start_datetime', true ) : 0;
$end_epoch   = $class_id ? (int) get_post_meta( $class_id, 'dish_end_datetime',   true ) : 0;

$dt_fmt = Settings::get( 'date_format' ) ?: get_option( 'date_format' );
$tm_fmt = Settings::get( 'time_format' ) ?: get_option( 'time_format' );

$date_label = '';
$time_label = '';

if ( $start_epoch ) {
	$date_label = DateHelper::format( $start_epoch, $dt_fmt );
	$time_label = DateHelper::format( $start_epoch, $tm_fmt );
	if ( $end_epoch && $end_epoch > $start_epoch ) {
		$time_label .= ' – ' . DateHelper::format( $end_epoch, $tm_fmt );
	}
} else {
	$date_label = esc_html__( 'Date unavailable', 'dish-events' );
}

// ── Chefs ─────────────────────────────────────────────────────────────────────
$chefs = [];
if ( $class_id ) {
	foreach ( ClassRepository::get_chef_ids( $class_id ) as $cid ) {
		$chef = get_post( $cid );
		if ( $chef && 'dish_chef' === $chef->post_type && 'publish' === $chef->post_status ) {
			$chefs[] = $chef;
		}
	}
}

// ── Add to Google Calendar URL ────────────────────────────────────────────────
$gcal_url = '';
if ( $start_epoch && $class_title ) {
	$venue_name    = (string) Settings::get( 'venue_name', '' );
	$venue_address = (string) Settings::get( 'venue_address', '' );
	$location      = $venue_name ? trim( $venue_name . ( $venue_address ? ', ' . $venue_address : '' ) ) : '';

	$gcal_start = gmdate( 'Ymd\THis\Z', $start_epoch );
	$gcal_end   = $end_epoch ? gmdate( 'Ymd\THis\Z', $end_epoch ) : gmdate( 'Ymd\THis\Z', $start_epoch + 2 * HOUR_IN_SECONDS );

	$gcal_url = add_query_arg(
		array_map( 'rawurlencode', [
			'action'   => 'TEMPLATE',
			'text'     => $class_title,
			'dates'    => $gcal_start . '/' . $gcal_end,
			'location' => $location,
			'details'  => sprintf(
				__( 'Booking reference: #%d — Dish Cooking Studio', 'dish-events' ),
				$booking_id
			),
		] ),
		'https://www.google.com/calendar/render'
	);
}

get_header();
?>

<main id="primary" class="site-main dish-confirmation-page">
	<div class="dish-container dish-confirmation">

		<!-- ── Success header ──────────────────────────────────────────────── -->
		<div class="dish-confirmation__hero">
			<div class="dish-confirmation__icon" aria-hidden="true">✓</div>
			<h1 class="dish-confirmation__title">
				<?php esc_html_e( "You're booked!", 'dish-events' ); ?>
			</h1>
			<p class="dish-confirmation__subtitle">
				<?php
				printf(
					/* translators: %s: customer first name */
					esc_html__( 'Thanks %s — we look forward to cooking with you.', 'dish-events' ),
					esc_html( strtok( $customer_name, ' ' ) ?: $customer_name )
				);
				?>
			</p>
		</div>

		<!-- ── Booking card ────────────────────────────────────────────────── -->
		<div class="dish-confirmation__card">

			<div class="dish-confirmation__ref">
				<span class="dish-confirmation__ref-label"><?php esc_html_e( 'Booking reference', 'dish-events' ); ?></span>
				<span class="dish-confirmation__ref-value">#<?php echo esc_html( $booking_id ); ?></span>
			</div>

			<h2 class="dish-confirmation__class-name"><?php echo esc_html( $class_title ); ?></h2>

			<ul class="dish-confirmation__meta">

				<?php if ( $date_label ) : ?>
					<li class="dish-confirmation__meta-item">
						<span class="dish-confirmation__meta-icon" aria-hidden="true">📅</span>
										<time datetime="<?php echo esc_attr( DateHelper::format( $start_epoch, 'c' ) ); ?>">
							<?php echo esc_html( $date_label ); ?>
						</time>
					</li>
				<?php endif; ?>

				<?php if ( $time_label ) : ?>
					<li class="dish-confirmation__meta-item">
						<span class="dish-confirmation__meta-icon" aria-hidden="true">🕐</span>
						<?php echo esc_html( $time_label ); ?>
					</li>
				<?php endif; ?>

				<?php if ( ! empty( $chefs ) ) : ?>
					<li class="dish-confirmation__meta-item">
						<span class="dish-confirmation__meta-icon" aria-hidden="true">👨‍🍳</span>
						<?php echo esc_html( implode( ', ', array_map( fn( $c ) => $c->post_title, $chefs ) ) ); ?>
					</li>
				<?php endif; ?>

				<?php if ( ! empty( $settings['venue_name'] ) ) : ?>
					<li class="dish-confirmation__meta-item">
						<span class="dish-confirmation__meta-icon" aria-hidden="true">📍</span>
						<?php echo esc_html( $settings['venue_name'] ); ?>
						<?php if ( ! empty( $settings['venue_address'] ) ) : ?>
							— <?php echo esc_html( $settings['venue_address'] ); ?>
						<?php endif; ?>
					</li>
				<?php endif; ?>

				<li class="dish-confirmation__meta-item">
					<span class="dish-confirmation__meta-icon" aria-hidden="true">🎟</span>
					<?php echo esc_html( sprintf(
						/* translators: %d: number of tickets */
						_n( '%d ticket', '%d tickets', $ticket_qty, 'dish-events' ),
						$ticket_qty
					) ); ?>
					<?php if ( $total_cents ) : ?>
						— <?php echo esc_html( MoneyHelper::cents_to_display( $total_cents ) ); ?>
					<?php endif; ?>
				</li>

			</ul>

			<?php if ( $total_cents ) : ?>
				<p class="dish-confirmation__payment-note">
					<?php esc_html_e( 'Payment is due at the studio on the day of your class.', 'dish-events' ); ?>
				</p>
			<?php endif; ?>

		</div><!-- .dish-confirmation__card -->

		<!-- ── Calendar links ──────────────────────────────────────────────── -->
		<?php if ( $gcal_url ) : ?>
			<div class="dish-confirmation__calendar">
				<h3 class="dish-confirmation__calendar-title">
					<?php esc_html_e( "Don't forget to add it to your calendar", 'dish-events' ); ?>
				</h3>
				<div class="dish-confirmation__calendar-actions">
					<a
						href="<?php echo esc_url( $gcal_url ); ?>"
						class="dish-confirmation__cal-link button button--outline"
						target="_blank"
						rel="noopener noreferrer"
					>
						<?php esc_html_e( '+ Add to Google Calendar', 'dish-events' ); ?>
					</a>
				</div>
			</div>
		<?php endif; ?>

		<!-- ── What happens next ───────────────────────────────────────────── -->
		<div class="dish-confirmation__next-steps">
			<h3 class="dish-confirmation__next-title">
				<?php esc_html_e( 'What happens next?', 'dish-events' ); ?>
			</h3>
			<ol class="dish-confirmation__steps">
				<li><?php esc_html_e( 'A confirmation email is on its way to you.', 'dish-events' ); ?></li>
				<li><?php esc_html_e( 'Arrive 10 minutes before your class starts — aprons are provided.', 'dish-events' ); ?></li>
				<li><?php esc_html_e( 'Bring your appetite! All food and equipment is included.', 'dish-events' ); ?></li>
				<?php if ( $total_cents ) : ?>
					<li>
						<?php echo esc_html( sprintf(
							/* translators: %s: amount */
							__( 'Payment of %s is due at the studio.', 'dish-events' ),
							MoneyHelper::cents_to_display( $total_cents )
						) ); ?>
					</li>
				<?php endif; ?>
			</ol>
		</div>

		<!-- ── CTA ─────────────────────────────────────────────────────────── -->
		<div class="dish-confirmation__cta">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button">
				<?php esc_html_e( '← Back to home', 'dish-events' ); ?>
			</a>
		</div>

	</div><!-- .dish-container -->
</main>

<?php get_footer(); ?>
