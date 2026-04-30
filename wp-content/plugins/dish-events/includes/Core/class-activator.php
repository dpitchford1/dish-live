<?php
/**
 * Plugin Activator.
 *
 * Runs on register_activation_hook(). Responsible for:
 *   - Creating custom DB tables via dbDelta()
 *   - Seeding default plugin options
 *   - Scheduling the cron cleanup job
 *   - Setting flags consumed by Plugin (rewrite flush, activation redirect)
 *
 * IMPORTANT: flush_rewrite_rules() is NOT called here directly.
 * CPTs must be registered before the flush is meaningful, so we set the
 * dish_flush_rewrite_rules flag and let Plugin::maybe_flush_rewrite_rules()
 * handle it on the next admin_init (after CPTs are registered in Phase 2+).
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

/**
 * Class Activator
 */
final class Activator {

	/**
	 * Run all activation routines.
	 * Called via register_activation_hook() in dish-events.php.
	 */
	public static function activate(): void {
		self::create_tables();
		self::seed_options();
		self::schedule_cron();

		// Signal that rewrite rules need flushing on next admin load
		// (once CPTs have been registered).
		update_option( 'dish_flush_rewrite_rules', '1' );

		// Signal the post-activation redirect to the Settings page.
		// Consumed by Admin module in Phase 3.
		update_option( 'dish_activation_redirect', '1' );
	}

	// -------------------------------------------------------------------------
	// Tables
	// -------------------------------------------------------------------------

	/**
	 * Create (or update) the three custom DB tables using dbDelta().
	 *
	 * Safe to call multiple times — dbDelta only adds missing columns/indexes.
	 * Called by activate() and by Updater migrations.
	 *
	 * @global \wpdb $wpdb
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// Note: dbDelta requires two spaces before PRIMARY KEY and KEY lines.
		$sql = "CREATE TABLE {$wpdb->prefix}dish_ticket_types (
  id                   bigint(20)    NOT NULL AUTO_INCREMENT,
  format_id            bigint(20)    NOT NULL DEFAULT 0,
  name                 varchar(255)  NOT NULL DEFAULT '',
  description          text,
  price_cents          int(11)       NOT NULL DEFAULT 0,
  sale_price_cents     int(11)                DEFAULT NULL,
  capacity             int(11)                DEFAULT NULL,
  show_remaining       tinyint(1)    NOT NULL DEFAULT 0,
  min_per_booking      int(11)       NOT NULL DEFAULT 1,
  per_ticket_fees      longtext               DEFAULT NULL,
  per_booking_fees     longtext               DEFAULT NULL,
  booking_starts       longtext               DEFAULT NULL,
  show_booking_dates   tinyint(1)    NOT NULL DEFAULT 0,
  is_active            tinyint(1)    NOT NULL DEFAULT 1,
  created_at           datetime      NOT NULL,
  updated_at           datetime               DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY format_id (format_id)
) $charset_collate;

CREATE TABLE {$wpdb->prefix}dish_checkout_fields (
  id                  bigint(20)    NOT NULL AUTO_INCREMENT,
  field_type          varchar(50)   NOT NULL DEFAULT 'text',
  label               varchar(255)  NOT NULL DEFAULT '',
  options             text                   DEFAULT NULL,
  is_required         tinyint(1)    NOT NULL DEFAULT 0,
  apply_per_attendee  tinyint(1)    NOT NULL DEFAULT 0,
  is_active           tinyint(1)    NOT NULL DEFAULT 1,
  created_at          datetime      NOT NULL,
  updated_at          datetime               DEFAULT NULL,
  PRIMARY KEY  (id)
) $charset_collate;";

		dbDelta( $sql );

		update_option( 'dish_db_version', DISH_EVENTS_VERSION );
	}

	/**
	 * Seed default plugin options on first activation.
	 * Uses add_option() so existing values are never overwritten.
	 */
	private static function seed_options(): void {
		// Full settings array — only added if the option doesn't exist yet.
		if ( false === get_option( 'dish_settings' ) ) {
			add_option( 'dish_settings', self::default_settings(), '', false );
		}

		// Encryption key — generated once, never overwritten.
		if ( false === get_option( 'dish_encrypt_key' ) ) {
			add_option( 'dish_encrypt_key', wp_generate_password( 64, true, true ), '', false );
		}
	}

	/**
	 * Returns the full default settings array.
	 *
	 * This is the canonical registry of every plugin setting and its default.
	 * Settings::get() falls back to these values when a key is missing.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return [

			// -----------------------------------------------------------------
			// General
			// -----------------------------------------------------------------
			'time_format'                  => 'g:i a',
			'date_format'                  => 'j F Y',
			'timezone_display'             => true,
			'timezone_message'             => 'All times are in {timezone}.',
			'currency'                     => 'CAD',
			'currency_symbol'              => '$',
			'currency_position'            => 'before',
			'checkout_timer_minutes'       => 10,

			// -----------------------------------------------------------------
			// Venue (single venue — stored as settings, not a CPT/taxonomy)
			// -----------------------------------------------------------------
			'venue_name'                   => '',
			'venue_address'                => '',
			'venue_city'                   => '',
			'venue_province'               => '',
			'venue_postal_code'            => '',
			'venue_google_maps_url'        => '',
			'venue_lat'                    => '',
			'venue_lng'                    => '',
			'venue_gmap_api_key'           => '',
			'google_reviews_place_id'      => '',
			'google_reviews_api_key'       => '',

			// -----------------------------------------------------------------
			// Studio / Organizer (single — stored as settings, not a CPT/taxonomy)
			// -----------------------------------------------------------------
			'studio_name'                  => '',
			'studio_email'                 => '',
			'studio_phone'                 => '',
			'studio_website'               => '',
			'studio_instagram'             => '',
			'studio_facebook'              => '',

			// -----------------------------------------------------------------
			// Pages — stores WP page IDs for shortcode pages
			// -----------------------------------------------------------------
			'classes_page'                 => 0,
			'formats_page'                 => 0,
			'chefs_page'                   => 0,
			'booking_page'                 => 0,
			'booking_details_page'         => 0,
			'profile_page'                 => 0,
			'login_page'                   => 0,
			'register_page'                => 0,

			// -----------------------------------------------------------------
			// Calendar & Views
			// -----------------------------------------------------------------
			'default_cal_view'             => 'dayGridMonth',
			'available_views'              => [ 'dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listWeek', 'grid' ],
			'hide_past_classes'            => false,
			'classes_per_page'             => 10,
			'show_class_type_on_calendar'  => true,
			'calendar_title_format'        => 'MMMM YYYY',
			'max_classes_per_day'          => 3,
			'open_class_in_new_tab'        => false,

			// -----------------------------------------------------------------
			// Payments
			// -----------------------------------------------------------------
			'active_gateway'               => 'paypal',
			'paypal_mode'                  => 'sandbox',
			'paypal_client_id'             => '',

			// -----------------------------------------------------------------
			// Emails
			// -----------------------------------------------------------------
			'email_from_name'    => '',
			'email_from_address' => '',
			'email_admin_to'     => '',

			// Booking confirmed (customer)
			'email_booking_confirmation_enabled' => true,
			'email_booking_confirmation_subject' => 'Your booking is confirmed — {{class_title}}',
			'email_booking_confirmation_cc'      => '',
			'email_booking_confirmation_body'    => '',

			// Booking cancelled (customer)
			'email_booking_cancelled_enabled' => true,
			'email_booking_cancelled_subject' => 'Your booking has been cancelled — {{class_title}}',
			'email_booking_cancelled_cc'      => '',
			'email_booking_cancelled_body'    => '',

			// Booking reminder (customer)
			'email_booking_reminder_enabled' => true,
			'email_booking_reminder_subject' => 'Reminder: {{class_title}} is coming up',
			'email_booking_reminder_cc'      => '',
			'email_booking_reminder_body'    => '',

			// Waitlist spot available (customer)
			'email_waitlist_available_enabled' => true,
			'email_waitlist_available_subject' => 'A spot is available — {{class_title}}',
			'email_waitlist_available_cc'      => '',
			'email_waitlist_available_body'    => '',

			// Payment receipt (customer)
			'email_payment_receipt_enabled' => true,
			'email_payment_receipt_subject' => 'Payment received — {{class_title}}',
			'email_payment_receipt_cc'      => '',
			'email_payment_receipt_body'    => '',

			// New booking (admin/studio)
			'email_admin_new_booking_enabled' => true,
			'email_admin_new_booking_subject' => 'New booking: {{class_title}} — {{customer_name}}',
			'email_admin_new_booking_cc'      => '',
			'email_admin_new_booking_body'    => '',

			// Cancellation (admin/studio)
			'email_admin_cancellation_enabled' => true,
			'email_admin_cancellation_subject' => 'Booking cancelled: {{class_title}} — {{customer_name}}',
			'email_admin_cancellation_cc'      => '',
			'email_admin_cancellation_body'    => '',

			// -----------------------------------------------------------------
			// URLs / Rewrite slugs
			// -----------------------------------------------------------------
			'class_slug'                   => 'class',
			'chef_slug'                    => 'chef',
			'class_format_slug'            => 'classes/formats', // Base for format archives: /classes/formats/{format}/

			// -----------------------------------------------------------------
			// Features (toggles)
			// -----------------------------------------------------------------
			'google_calendar_link'         => true,
			'ical_download'                => true,
			'show_qr_on_booking'           => true,
			'guest_checkout'               => true,
			'allow_account_creation'       => true,

			// -----------------------------------------------------------------
			// Checkout
			// -----------------------------------------------------------------
			'terms_enabled'                => false,
			'terms_label'                  => 'I agree to the <a href="/terms" target="_blank">Terms &amp; Conditions</a>.',
		];
	}

	// -------------------------------------------------------------------------
	// Cron
	// -------------------------------------------------------------------------

	/**
	 * Schedule the booking timer cleanup cron job if not already scheduled.
	 */
	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'dish_cleanup_expired_bookings' ) ) {
			wp_schedule_event( time(), 'dish_every_15_minutes', 'dish_cleanup_expired_bookings' );
		}

		GoogleReviews::schedule();
	}
}
