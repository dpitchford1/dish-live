<?php
/**
 * Template: Checkout page.
 *
 * Served when the WordPress page assigned to `booking_page` in plugin settings
 * is requested with a `class_id` query parameter pointing to a published
 * dish_class instance.
 *
 * Flow:
 *   1. Validate the class_id parameter.
 *   2. Resume an existing live checkout session (cookie) or initiate a new one.
 *   3. Render a countdown timer, class summary, quantity selector, customer
 *      fields, attendee fields (from checkout field config), and a payment-stub
 *      "Reserve My Spot" button.
 *   4. Localise `dishBooking` for dish-booking.js (timer expiry, AJAX data).
 *
 * Payment stub: clicking "Reserve My Spot" submits the form via AJAX.
 * BookingManager::complete() creates a dish_pending booking.
 * On success the JS redirects to the confirmation page.
 *
 * Theme override: {theme}/dish-events/booking/checkout.php
 *
 * @package Dish\Events\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dish\Events\Booking\BookingManager;
use Dish\Events\Booking\CapacityManager;
use Dish\Events\Admin\Settings;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Data\CheckoutFieldRepository;
use Dish\Events\Helpers\DateHelper;
use Dish\Events\Helpers\MoneyHelper;

// ── Resolve class instance ────────────────────────────────────────────────────
$class_id      = absint( $_GET['class_id'] ?? 0 );
$requested_qty = max( 1, min( 8, absint( $_GET['qty'] ?? 1 ) ) );
$class         = $class_id ? get_post( $class_id ) : null;

if ( ! $class || 'dish_class' !== $class->post_type || 'publish' !== $class->post_status ) {
	wp_redirect( home_url( '/' ) );
	exit;
}

// ── Session management ────────────────────────────────────────────────────────
// Sanitise the session key from the cookie — UUIDs are hex + hyphens only.
$session_key = isset( $_COOKIE['dish_checkout_session'] )
	? preg_replace( '/[^a-f0-9\-]/', '', $_COOKIE['dish_checkout_session'] )
	: '';

$session = null;
$error   = '';

// Try to resume an existing live session for this exact class.
if ( $session_key ) {
	$session = BookingManager::resume( $session_key, $class_id );
}

// Start a fresh session when none exists or the existing one is stale.
if ( ! $session ) {
	$initiate = BookingManager::initiate( $class_id, $requested_qty );

	if ( is_wp_error( $initiate ) ) {
		$error = $initiate->get_error_message();
	} else {
		$session     = $initiate;
		$session_key = $session['session_key'];

		// Set the session cookie.  httponly + SameSite=Lax keeps it safe from
		// XSS while still working on same-origin navigations.
		setcookie(
			'dish_checkout_session',
			$session_key,
			[
				'expires'  => $session['expires_at'] + 600,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}
}

// ── Class metadata ────────────────────────────────────────────────────────────
$template_id  = (int) get_post_meta( $class_id, 'dish_template_id', true );
$template     = $template_id ? get_post( $template_id ) : null;
$class_title  = $template ? $template->post_title : get_the_title( $class_id );

$start_epoch  = (int) get_post_meta( $class_id, 'dish_start_datetime', true );
$end_epoch    = (int) get_post_meta( $class_id, 'dish_end_datetime',   true );

// Plugin settings take precedence over WP core date/time formats; both fall
// through to DateHelper::format() which uses wp_date() and respects the WP
// timezone configuration (named zone, UTC offset, or empty string).
$dt_fmt = Settings::get( 'date_format' ) ?: get_option( 'date_format' );
$tm_fmt = Settings::get( 'time_format' ) ?: get_option( 'time_format' );
if ( $start_epoch ) {
	$date_label = DateHelper::format( $start_epoch, $dt_fmt );
	$time_label = DateHelper::format( $start_epoch, $tm_fmt );
	if ( $end_epoch && $end_epoch > $start_epoch ) {
		$time_label .= ' – ' . DateHelper::format( $end_epoch, $tm_fmt );
	}
} else {
	$date_label = esc_html__( 'Date unavailable', 'dish-events' );
	$time_label = '';
}

// ── Chefs ─────────────────────────────────────────────────────────────────────
$chef_ids = ClassRepository::get_chef_ids( $class_id );
$chefs    = array_filter(
	array_map( 'get_post', $chef_ids ),
	static fn( $p ) => $p && 'dish_chef' === $p->post_type && 'publish' === $p->post_status
);

// ── Pricing ───────────────────────────────────────────────────────────────────
$price_cents    = $session ? (int) $session['price_cents']    : 0;
$total_cents    = $session ? (int) $session['total_cents']    : 0;
$ticket_type_id = $session ? (int) $session['ticket_type_id'] : 0;
$expires_at     = $session ? (int) $session['expires_at']     : 0;
$fee_lines      = $session ? ( $session['fee_lines'] ?? [] )  : [];

// ── Checkout fields ───────────────────────────────────────────────────────────
// Per-class override takes precedence; fall back to global active fields.
$checkout_override = (bool) get_post_meta( $template_id, 'dish_checkout_override', true );
$checkout_fields   = [];

if ( $checkout_override && $template_id ) {
	$raw_json       = (string) get_post_meta( $template_id, 'dish_checkout_fields_json', true );
	$decoded        = $raw_json ? json_decode( $raw_json, true ) : null;
	$checkout_fields = is_array( $decoded ) ? $decoded : [];
	// Normalise key names (class-override format uses 'type', 'required', 'per_attendee').
	$checkout_fields = array_map( static function ( array $f ): array {
		return [
			'label'             => $f['label']        ?? '',
			'field_type'        => $f['type']         ?? 'text',
			'is_required'       => ! empty( $f['required'] ),
			'apply_per_attendee' => ! empty( $f['per_attendee'] ),
		];
	}, $checkout_fields );
} else {
	$global_fields  = CheckoutFieldRepository::get_active();
	$checkout_fields = array_map( static fn( object $f ): array => [
		'label'              => $f->label,
		'field_type'         => $f->field_type,
		'is_required'        => (bool) $f->is_required,
		'apply_per_attendee' => (bool) $f->apply_per_attendee,
	], $global_fields );
}

$qty             = $session ? max( 1, (int) $session['qty'] ) : $requested_qty;
$available_spots = CapacityManager::get_available( $class_id );
// Add back the qty already held in this session so the selector shows the
// realistic ceiling (e.g. 3 available + 1 already held = 4 max).
$max_qty         = $available_spots === PHP_INT_MAX ? 8 : min( 8, $available_spots + $qty );

$terms_enabled = (bool) Settings::get( 'terms_enabled', false );
$terms_label   = Settings::get( 'terms_label' ) ?: __( 'I agree to the Terms &amp; Conditions.', 'dish-events' );

// ── Inline JS config ─────────────────────────────────────────────────────────
// Must be registered via a late wp_enqueue_scripts hook (priority 20) so that
// Assets::enqueue() has already registered the 'dish-booking' handle before
// wp_add_inline_script() is called. Calling wp_script_is() here — before
// get_header() — always returns false because wp_enqueue_scripts hasn't fired.
if ( $session ) {
	$dish_js_config = wp_json_encode( [
		'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
		'nonce'        => wp_create_nonce( 'dish_checkout' ),
		'sessionKey'   => $session_key,
		'expiresAt'    => $expires_at,
		'classId'      => $class_id,
		'ticketTypeId' => $ticket_type_id,
		'priceCents'   => $price_cents,
		'feeLines'     => array_map( function ( array $fl ): array {
			return [
				'label'       => $fl['label'],
				'amountCents' => (int) $fl['amount_cents'],
				'perTicket'   => (bool) $fl['per_ticket'],
			];
		}, $fee_lines ),
		'classUrl'     => $template_id ? ( get_permalink( $template_id ) ?: home_url( '/' ) ) : home_url( '/' ),
		'currency'     => Settings::get( 'currency_symbol', '$' ),
		'i18n'         => [
			'expired'    => __( 'Your reservation has expired.', 'dish-events' ),
			'submitting' => __( 'Reserving…', 'dish-events' ),
			'error'      => __( 'Something went wrong. Please try again.', 'dish-events' ),
		],
	] );

	add_action( 'wp_enqueue_scripts', static function () use ( $dish_js_config ) {
		wp_add_inline_script( 'dish-booking', 'var dishBooking = ' . $dish_js_config . ';', 'before' );
	}, 20 );
}

get_header();
?>
<div class="content--region has--aside fluid-content">
<?php /* ── Main Content ─────────────────────────────────────────── */ ?>
<main id="main-content" class="main--content inner--content">

    <?php if ( $error ) : ?>
        <div class="dish-checkout__error dish-notice dish-notice--error">
            <p><?php echo esc_html( $error ); ?></p>
            <p><a href="<?php echo esc_url( wp_get_referer() ?: home_url( '/' ) ); ?>" class="button">
                    <?php esc_html_e( '← Go back', 'dish-events' ); ?></a></p>
        </div>
    <?php else : ?>

    <?php /* ── Countdown timer ──────────────────────────────────────────── */ ?>
    <div class="dish-checkout__timer" id="dish-timer" aria-live="polite" aria-atomic="true">
        <span class="dish-checkout__timer-icon" aria-hidden="true">⏱</span>
        <span class="dish-checkout__timer-label"><?php esc_html_e( 'Spot held for', 'dish-events' ); ?></span>
        <span class="dish-checkout__timer-countdown" id="dish-timer-countdown">--:--</span>
    </div>

    <?php /* ── Checkout form ────────────────────────────────────────── */ ?>
    <div class="checkout-form--wrapper">
        <form id="dish-checkout-form" class="checkout--form" method="post" novalidate>

            <?php wp_nonce_field( 'dish_checkout', 'nonce' ); ?>
            <input type="hidden" name="session_key"    value="<?php echo esc_attr( $session_key ); ?>">
            <input type="hidden" name="ticket_type_id" value="<?php echo esc_attr( $ticket_type_id ); ?>">
            <input type="hidden" name="action"         value="dish_process_booking">

        <?php /* ── Number of tickets ──────────────────────── */ ?>
        <?php if ( $max_qty > 1 ) : ?>
            <fieldset class="checkout--fieldset">
                <legend class="checkout--legend"><?php esc_html_e( 'Number of tickets', 'dish-events' ); ?></legend>
                <div class="form--row">
                    <label for="dish-qty" class="form--label"><?php esc_html_e( 'Tickets', 'dish-events' ); ?> </label>
                    <?php /* Changing qty reloads the page with ?qty=N so BookingManager
                    initiates a fresh hold with the correct quantity. */ ?>
                    <select id="dish-qty" name="qty" class="form--select">
                        <?php for ( $i = 1; $i <= $max_qty; $i++ ) : ?>
                            <option value="<?php echo esc_attr( $i ); ?>" <?php selected( $qty, $i ); ?>>
                                <?php echo esc_html( $i ); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </fieldset>
        <?php else : ?>
            <input type="hidden" name="qty" value="1">
        <?php endif; ?>

            <?php /* ── Your details ───────────────────────────────── */ ?>
            <fieldset class="checkout--fieldset">
                <legend class="checkout--legend">
                    <?php esc_html_e( 'Your details', 'dish-events' ); ?>
                </legend>

                <div class="form--row">
                    <label for="dish-customer-name" class="form--label">
                        <?php esc_html_e( 'Full name', 'dish-events' ); ?>
                        <span class="form--required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="dish-customer-name"
                        name="customer_name"
                        class="form--input"
                        autocomplete="name"
                        required
                        value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->display_name : '' ); ?>"
                    >
                </div>

                <div class="form--row">
                    <label for="dish-customer-email" class="form--label">
                        <?php esc_html_e( 'Email address', 'dish-events' ); ?>
                        <span class="form--required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="email"
                        id="dish-customer-email"
                        name="customer_email"
                        class="form--input"
                        autocomplete="email"
                        required
                        value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->user_email : '' ); ?>"
                    >
                </div>

                <div class="form--row">
                    <label for="dish-customer-phone" class="form--label">
                        <?php esc_html_e( 'Phone number', 'dish-events' ); ?>
                        <span class="form--optional">(<?php esc_html_e( 'optional', 'dish-events' ); ?>)</span>
                    </label>
                    <input
                        type="tel"
                        id="dish-customer-phone"
                        name="customer_phone"
                        class="form--input"
                        autocomplete="tel"
                    >
                </div>
            </fieldset>

            <?php if ( ! is_user_logged_in() ) : ?>
            <?php /* ── Create an account ──────────────────────── */ ?>
            <div class="form--row form--row--create-account">
                <label class="form--label form--label--checkbox">
                    <input
                        type="checkbox"
                        id="dish-create-account"
                        name="create_account"
                        value="1"
                    >
                    <?php esc_html_e( 'Create an account for easier future bookings', 'dish-events' ); ?>
                </label>
            </div>

            <fieldset id="dish-account-fields" class="checkout--fieldset checkout-fieldset--account" hidden>
                <legend class="checkout--legend">
                    <?php esc_html_e( 'Account details', 'dish-events' ); ?>
                </legend>

                <div class="form--row">
                    <label for="dish-account-username" class="form--label">
                        <?php esc_html_e( 'Username', 'dish-events' ); ?>
                        <span class="form--required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="dish-account-username"
                        name="account_username"
                        class="form--input"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                    >
                </div>

                <div class="form--row">
                    <label for="dish-account-password" class="form--label">
                        <?php esc_html_e( 'Password', 'dish-events' ); ?>
                        <span class="form--required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="password"
                        id="dish-account-password"
                        name="account_password"
                        class="form--input"
                        autocomplete="new-password"
                        minlength="8"
                    >
                    <p class="description"><?php esc_html_e( 'Minimum 8 characters.', 'dish-events' ); ?></p>
                </div>
            </fieldset>
            <?php endif; ?>

            <?php /* ── Attendee fields ────────────────────────────── */ ?>
            <?php if ( ! empty( $checkout_fields ) ) :
                // Separate per-booking and per-attendee fields.
                $per_booking  = array_filter( $checkout_fields, fn( $f ) => empty( $f['apply_per_attendee'] ) );
                $per_attendee = array_filter( $checkout_fields, fn( $f ) => ! empty( $f['apply_per_attendee'] ) );
            ?>

                <?php if ( $per_booking ) : ?>
                    <fieldset class="checkout--fieldset">
                        <legend class="checkout--legend">
                            <?php esc_html_e( 'Additional information', 'dish-events' ); ?>
                        </legend>
                        <?php foreach ( $per_booking as $idx => $field ) :
                            $fid   = 'dish-field-' . $idx;
                            $fname = 'attendees[0][field_' . $idx . ']';
                        ?>
                            <div class="form--row">
                                <label for="<?php echo esc_attr( $fid ); ?>" class="form--label">
                                    <?php echo esc_html( $field['label'] ); ?>
                                    <?php if ( $field['is_required'] ) : ?>
                                        <span class="form--required" aria-hidden="true">*</span>
                                    <?php endif; ?>
                                </label>
                                <?php if ( 'textarea' === $field['field_type'] ) : ?>
                                    <textarea
                                        id="<?php echo esc_attr( $fid ); ?>"
                                        name="<?php echo esc_attr( $fname ); ?>"
                                        class="form--textarea"
                                        <?php echo $field['is_required'] ? 'required' : ''; ?>
                                    ></textarea>
                                <?php else : ?>
                                    <input
                                        type="text"
                                        id="<?php echo esc_attr( $fid ); ?>"
                                        name="<?php echo esc_attr( $fname ); ?>"
                                        class="form--input"
                                        <?php echo $field['is_required'] ? 'required' : ''; ?>
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>

                <?php if ( $per_attendee ) : ?>
                    <?php for ( $t = 0; $t < $qty; $t++ ) : ?>
                        <fieldset class="checkout--fieldset dish-checkout__attendee-fieldset" data-ticket="<?php echo esc_attr( $t ); ?>">
                            <legend class="checkout--legend">
                                <?php
                                if ( $qty > 1 ) {
                                    echo esc_html( sprintf(
                                        /* translators: %d: ticket number */
                                        __( 'Ticket %d – attendee details', 'dish-events' ),
                                        $t + 1
                                    ) );
                                } else {
                                    esc_html_e( 'Attendee details', 'dish-events' );
                                }
                                ?>
                            </legend>
                            <?php foreach ( $per_attendee as $idx => $field ) :
                                $fid   = 'dish-attendee-' . $t . '-field-' . $idx;
                                $fname = 'attendees[' . $t . '][field_' . $idx . ']';
                            ?>
                                <div class="form--row">
                                    <label for="<?php echo esc_attr( $fid ); ?>" class="form--label">
                                        <?php echo esc_html( $field['label'] ); ?>
                                        <?php if ( $field['is_required'] ) : ?>
                                            <span class="form--required" aria-hidden="true">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input
                                        type="text"
                                        id="<?php echo esc_attr( $fid ); ?>"
                                        name="<?php echo esc_attr( $fname ); ?>"
                                        class="form--input"
                                        <?php echo $field['is_required'] ? 'required' : ''; ?>
                                    >
                                </div>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endfor; ?>
                <?php endif; ?>

            <?php endif; ?>
            <?php /* checkout_fields */ ?>

            <?php if ( $terms_enabled ) : ?>
                <?php /* ── Terms & Conditions ──────────────────── */ ?>
                <div class="form--row form--row--terms">
                    <label class="form--label form--label--checkbox">
                        <input type="checkbox" id="dish-terms" name="terms_accepted" value="1" required>
                        <span class="form--terms-label"><?php echo wp_kses( $terms_label, [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ], 'strong' => [], 'em' => [] ] ); ?></span>
                        <span class="form--required" aria-hidden="true">*</span>
                    </label>
                </div>
            <?php endif; ?>

            <?php /* ── Payment stub ───────────────────────────────── */ ?>
            <div class="dish-checkout__payment">
                <p class="dish-checkout__payment-note"><?php esc_html_e( 'Payment will be collected at the studio. Your spot is reserved when you submit this form.', 'dish-events' ); ?></p>
            </div>

            <?php /* ── Submit ─────────────────────────────────────── */ ?>
            <div class="dish-checkout__submit-row" id="dish-submit-area">
                <div class="dish-checkout__form-error" id="form--error" hidden></div>

                <button type="submit" class="dish-checkout__submit button button--primary" id="dish-submit-btn">
                    <?php if ( $price_cents ) : ?>
                        <?php
                        echo esc_html( sprintf(
                            /* translators: %s: total price */
                            __( 'Reserve my spot — %s', 'dish-events' ),
                            MoneyHelper::cents_to_display( $total_cents )
                        ) );
                        ?>
                    <?php else : ?>
                        <?php esc_html_e( 'Reserve my spot', 'dish-events' ); ?>
                    <?php endif; ?>
                </button>

                <p class="dish-checkout__back"><a href="<?php echo esc_url( wp_get_referer() ?: home_url( '/' ) ); ?>"><?php esc_html_e( '← Back to class details', 'dish-events' ); ?></a></p>
            </div>

        </form>
    </div><!-- .dish-checkout__form-wrap -->

		<?php endif; ?>
</main>
<?php /* ── Class summary (sidebar) ──────────────────────────────── */ ?>
<aside class="checkout--summary"> 
    <h2 class="card-title"><?php esc_html_e( 'Your booking', 'dish-events' ); ?></h2>

    <?php if ( $template && has_post_thumbnail( $template_id ) ) : ?>
        <div class="dish-checkout__summary-image">
            <?php echo get_the_post_thumbnail( $template_id, 'medium', [ 'class' => 'dish-checkout__summary-thumb' ] ); ?>
        </div>
    <?php endif; ?>
    
    <div class="checkout--meta">
    <h3 class=""><?php echo esc_html( $class_title ); ?></h3>

    <ul class="icon-list--default">
        <?php if ( $date_label ) : ?>
            <li class="ico--date">
                <time datetime="<?php echo esc_attr( DateHelper::format( $start_epoch, 'c' ) ); ?>">
                    <?php echo esc_html( $date_label ); ?>
                </time>
            </li>
        <?php endif; ?>

        <?php if ( $time_label ) : ?>
            <li class="ico--time">
                <?php echo esc_html( $time_label ); ?>
            </li>
        <?php endif; ?>

        <?php if ( ! empty( $chefs ) ) : ?>
            <li class="ico--person">
                Chef: <?php echo esc_html( implode( ', ', array_map( fn( $c ) => $c->post_title, $chefs ) ) ); ?>
            </li>
        <?php endif; ?>
    </ul>
    </div>
    <?php if ( $price_cents ) : ?>
    <?php if ( ! empty( $fee_lines ) ) : ?>
        <div class="checkout-price--row">
            <span class="label" id="dish-ticket-label">
                <?php echo esc_html(
                    sprintf(
                        /* translators: %d: quantity */
                        _n( '%d ticket', '%d tickets', $qty, 'dish-events' ),
                        $qty
                    )
                ); ?>
            </span>
            <span class="subtotal" id="dish-ticket-subtotal">
                <?php echo esc_html( MoneyHelper::cents_to_display( $price_cents * $qty ) ); ?>
            </span>
        </div>
        <?php foreach ( $fee_lines as $i => $fee ) : ?>
        <div class="checkout-price--row"
            data-fee-index="<?php echo $i; ?>"
            data-fee-amount="<?php echo (int) $fee['amount_cents']; ?>"
            data-per-ticket="<?php echo $fee['per_ticket'] ? '1' : '0'; ?>">
            <span class="label"><?php echo esc_html( $fee['label'] ); ?></span>
            <span class="subtotal"><?php echo esc_html(
                MoneyHelper::cents_to_display(
                    $fee['per_ticket'] ? $fee['amount_cents'] * $qty : $fee['amount_cents']
                )
            ); ?></span>
        </div>
        <?php endforeach; ?>
        <div class="checkout-price--row">
            <span class="label"><?php esc_html_e( 'Total', 'dish-events' ); ?></span>
            <span class="total" id="dish-total-display">
                <?php echo esc_html( MoneyHelper::cents_to_display( $total_cents ) ); ?>
            </span>
        </div>
    <?php else : ?>
        <div class="checkout-price--row">
            <span class="label" id="dish-ticket-label">
                <?php echo esc_html(
                    sprintf(
                        /* translators: %d: quantity */
                        _n( '%d ticket', '%d tickets', $qty, 'dish-events' ),
                        $qty
                    )
                ); ?>
            </span>
            <span class="total" id="dish-total-display">
                <?php echo esc_html( MoneyHelper::cents_to_display( $total_cents ) ); ?>
            </span>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</aside><!-- .dish-checkout__summary -->
</div>
<?php get_footer(); ?>
