<?php
/**
 * Plugin settings page + WP Settings API registration.
 *
 * All 10 tabs are rendered via query-param tab switching (?tab=<slug>).
 * No JavaScript is required for tab navigation.
 *
 * All values live in a single serialised option: `dish_settings`.
 * Read:  Settings::get( 'key', 'optional_default' )
 * Write: Settings::set( 'key', $value )   ← for programmatic one-off writes.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

use Dish\Events\Core\Activator;
use Dish\Events\Data\CheckoutFieldRepository;

/**
 * Class Settings
 */
final class Settings {

	/** Option name that stores the entire settings array. */
	const OPTION = 'dish_settings';

	/** Menu slug used for register_setting / do_settings_sections. */
	const MENU_SLUG = 'dish-events-settings';

	/** Ordered list of tabs: [ slug => label ] */
	private const TABS = [
		'general'  => 'General',
		'venue'    => 'Venue',
		'studio'   => 'Studio',
		'pages'    => 'Pages',
		'calendar' => 'Calendar',
		'checkout' => 'Checkout',
		'payments' => 'Payments',
		'emails'   => 'Emails',
		'urls'     => 'URLs',
		'features' => 'Features',
	];

	// =========================================================================
	// Static API
	// =========================================================================

	/**
	 * Read a single setting value.
	 *
	 * Falls back to the canonical defaults from Activator, then to $default.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when key is missing entirely.
	 * @return mixed
	 */
	public static function get( string $key, mixed $default = null ): mixed {
		$all      = get_option( self::OPTION, [] );
		$defaults = Activator::default_settings();

		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}

		return $defaults[ $key ] ?? $default;
	}

	/**
	 * Write a single setting value.
	 * Merges into the existing option so other keys are not clobbered.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public static function set( string $key, mixed $value ): void {
		$all         = get_option( self::OPTION, [] );
		$all[ $key ] = $value;
		update_option( self::OPTION, $all, false );
	}

	// =========================================================================
	// Hook callbacks
	// =========================================================================

	/**
	 * Register settings page under the Classes CPT menu.
	 */
	public function add_pages(): void {
		add_submenu_page(
			'edit.php?post_type=dish_class',
			__( 'Dish Events Settings', 'dish-events' ),
			__( 'Settings', 'dish-events' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ],
		);
	}

	/**
	 * Register the single `dish_settings` option + all sections/fields for
	 * each tab.
	 */
	public function register_settings(): void {
		register_setting(
			self::MENU_SLUG,
			self::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
			]
		);

		$this->register_general_tab();
		$this->register_venue_tab();
		$this->register_studio_tab();
		$this->register_pages_tab();
		$this->register_calendar_tab();
		$this->register_checkout_tab();
		$this->register_payments_tab();
		$this->register_emails_tab();
		$this->register_urls_tab();
		$this->register_features_tab();
	}

	/**
	 * Enqueue inline styles needed for the settings page only.
	 * Relies entirely on WP-native classes; we only add minor layout tweaks.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		$css = '
			.dish-settings-wrap .form-table th { width: 220px; }
			.dish-settings-wrap .description   { color: #646970; }
			.dish-gcf-table th,
			.dish-gcf-table td                 { vertical-align: middle; }
		';
		wp_add_inline_style( 'wp-admin', $css );

		// Field-builder JS — only needed on the Checkout tab.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $current_tab === 'checkout' ) {
			wp_add_inline_script( 'jquery', $this->checkout_fields_js() );
		}
	}

	// =========================================================================
	// Page renderer
	// =========================================================================

	/**
	 * Render the full settings page HTML.
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! array_key_exists( $current_tab, self::TABS ) ) {
			$current_tab = 'general';
		}

		?>
		<div class="wrap dish-settings-wrap">
			<h1><?php esc_html_e( 'Dish Events Settings', 'dish-events' ); ?></h1>

			<?php settings_errors( self::OPTION ); ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( self::TABS as $slug => $label ) : ?>
					<a
						href="<?php echo esc_url( admin_url( 'edit.php?post_type=dish_class&page=' . self::MENU_SLUG . '&tab=' . $slug ) ); ?>"
						class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php // Form must post to options.php — WP Settings API processes saves there.
			// settings_fields() outputs the option_group, action=update, nonce, and
			// _wp_http_referer (pointing back to the current URL with ?tab= intact),
			// so the post-save redirect lands on the correct tab automatically. ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( self::MENU_SLUG );
				// Pass the active tab so sanitize() knows which checkboxes were rendered.
				printf(
					'<input type="hidden" name="%s[_tab]" value="%s">',
					esc_attr( self::OPTION ),
					esc_attr( $current_tab )
				);
				do_settings_sections( 'dish-settings-' . $current_tab );
				submit_button( __( 'Save Settings', 'dish-events' ) );
				?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	// Sanitization
	// =========================================================================

	/**
	 * Sanitize the full settings array submitted via the form.
	 *
	 * We start from the stored values (so tabs not currently rendered keep
	 * their values), then merge in the incoming POST data after sanitizing.
	 *
	 * @param array<string, mixed> $raw Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( array $raw ): array {
		$current = get_option( self::OPTION, Activator::default_settings() );

		// Text / URL fields — keys that receive plain text.
		$text_keys = [
			'time_format', 'date_format',
			'currency', 'currency_symbol', 'currency_position',
			'venue_name', 'venue_address', 'venue_city', 'venue_province', 'venue_postal_code',
			'venue_gmap_api_key', 'venue_lat', 'venue_lng',
			'studio_name', 'studio_email', 'studio_phone',
			'default_cal_view',
			'active_gateway', 'paypal_mode', 'paypal_client_id',
			'email_from_name', 'email_from_address', 'email_admin_to',
			'class_slug', 'chef_slug', 'class_format_slug',
			'email_booking_confirmation_subject', 'email_booking_confirmation_cc',
			'email_booking_cancelled_subject', 'email_booking_cancelled_cc',
			'email_booking_reminder_subject', 'email_booking_reminder_cc',
			'email_waitlist_available_subject', 'email_waitlist_available_cc',
			'email_payment_receipt_subject', 'email_payment_receipt_cc',
			'email_admin_new_booking_subject', 'email_admin_new_booking_cc',
			'email_admin_cancellation_subject', 'email_admin_cancellation_cc',
		];

		// URL fields.
		$url_keys = [
			'venue_google_maps_url', 'studio_website', 'studio_instagram', 'studio_facebook',
		];

		// Textarea / rich-text fields (wp_kses_post strip-only).
		$textarea_keys = [
			'venue_hours',
			'terms_label',
			'email_booking_confirmation_body', 'email_booking_cancelled_body',
			'email_booking_reminder_body', 'email_waitlist_available_body',
			'email_payment_receipt_body', 'email_admin_new_booking_body',
			'email_admin_cancellation_body',
		];

		// Integer fields.
		$int_keys = [
			'checkout_timer_minutes', 'classes_per_page', 'max_classes_per_day',
			'spots_left_threshold',
			'classes_page', 'booking_page', 'booking_details_page', 'enquiry_page', 'profile_page',
			'login_page', 'register_page', 'chefs_page',
		];

		// Checkbox / boolean fields (present = true, absent = false).
		$checkbox_keys = [
			'timezone_display', 'hide_past_classes', 'show_class_type_on_calendar',
			'open_class_in_new_tab', 'google_calendar_link',
			'ical_download', 'show_qr_on_booking', 'guest_checkout',
			'allow_account_creation',
			'terms_enabled',
			'email_booking_confirmation_enabled', 'email_booking_cancelled_enabled',
			'email_booking_reminder_enabled', 'email_waitlist_available_enabled',
			'email_payment_receipt_enabled', 'email_admin_new_booking_enabled',
			'email_admin_cancellation_enabled',
		];

		foreach ( $text_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$current[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}

		foreach ( $url_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$current[ $key ] = esc_url_raw( $raw[ $key ] );
			}
		}

		foreach ( $textarea_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$current[ $key ] = wp_kses_post( $raw[ $key ] );
			}
		}

		foreach ( $int_keys as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$current[ $key ] = absint( $raw[ $key ] );
			}
		}

		// Checkbox / boolean fields.
		// Checkboxes absent from POST = unchecked, but we only know that for the
		// tab that was actually submitted. Map each tab's checkboxes so we only
		// reset the ones that were rendered — all others keep their stored value.
		$tab_checkboxes = [
			'general'  => [ 'timezone_display' ],
			'calendar' => [ 'hide_past_classes', 'show_class_type_on_calendar', 'open_class_in_new_tab' ],
			'checkout' => [ 'terms_enabled' ],
			'emails'   => [
				'email_booking_confirmation_enabled', 'email_booking_cancelled_enabled',
				'email_booking_reminder_enabled',     'email_waitlist_available_enabled',
				'email_payment_receipt_enabled',      'email_admin_new_booking_enabled',
				'email_admin_cancellation_enabled',
			],
			'features' => [ 'google_calendar_link', 'ical_download', 'show_qr_on_booking', 'guest_checkout', 'allow_account_creation' ],
		];

		$submitted_tab  = sanitize_key( $raw['_tab'] ?? '' );
		$active_cb_keys = $tab_checkboxes[ $submitted_tab ] ?? [];

		foreach ( $checkbox_keys as $key ) {
			if ( in_array( $key, $active_cb_keys, true ) ) {
				$current[ $key ] = ! empty( $raw[ $key ] );
			}
		}

		// Sync global checkout fields table when the Checkout tab is saved.
		if ( $submitted_tab === 'checkout' ) {
			$this->save_global_checkout_fields();
		}

		// Strip the internal _tab marker — never store it.
		unset( $current['_tab'] );

		// Multiselect — available_views.
		if ( isset( $raw['available_views'] ) && is_array( $raw['available_views'] ) ) {
			$allowed              = [ 'month', 'week', 'day', 'list' ];
			$current['available_views'] = array_values(
				array_intersect( array_map( 'sanitize_key', $raw['available_views'] ), $allowed )
			);
		}

		// Detect URL slug changes → schedule rewrite flush.
		$slug_keys = [ 'class_slug', 'chef_slug', 'class_format_slug' ];
		$stored    = get_option( self::OPTION, [] );
		foreach ( $slug_keys as $k ) {
			if ( isset( $current[ $k ], $stored[ $k ] ) && $current[ $k ] !== $stored[ $k ] ) {
				update_option( 'dish_flush_rewrite_rules', 1, false );
				break;
			}
		}

		return $current;
	}

	// =========================================================================
	// Tab: General
	// =========================================================================

	private function register_general_tab(): void {
		$page = 'dish-settings-general';

		add_settings_section( 'dish_general', '', '__return_null', $page );

		$this->field( $page, 'dish_general', 'currency', __( 'Currency', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'CAD' => 'CAD — Canadian Dollar',
				'USD' => 'USD — US Dollar',
				'AUD' => 'AUD — Australian Dollar',
				'GBP' => 'GBP — British Pound',
				'EUR' => 'EUR — Euro',
				'NZD' => 'NZD — New Zealand Dollar',
			],
		] );

		$this->field( $page, 'dish_general', 'currency_symbol', __( 'Currency symbol', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'Displayed alongside prices. e.g. $, £, €', 'dish-events' ),
		] );

		$this->field( $page, 'dish_general', 'currency_position', __( 'Symbol position', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'before' => __( 'Before price ($45.00)', 'dish-events' ),
				'after'  => __( 'After price (45.00$)', 'dish-events' ),
			],
		] );

		$this->field( $page, 'dish_general', 'time_format', __( 'Time format', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [ 'g:i a' => '12-hour (e.g. 6:30 pm)', 'H:i' => '24-hour (e.g. 18:30)' ],
		] );

		$this->field( $page, 'dish_general', 'date_format', __( 'Date format', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'd/m/Y' => 'DD/MM/YYYY',
				'm/d/Y' => 'MM/DD/YYYY',
				'Y-m-d' => 'YYYY-MM-DD',
				'j F Y' => 'D Month YYYY',
			],
		] );

		$this->field( $page, 'dish_general', 'timezone_display', __( 'Show timezone to attendees', 'dish-events' ), [ $this, 'render_checkbox' ], [
			'label' => __( 'Display the site timezone alongside date/time fields', 'dish-events' ),
		] );

		$this->field( $page, 'dish_general', 'checkout_timer_minutes', __( 'Checkout timer (minutes)', 'dish-events' ), [ $this, 'render_number' ], [
			'min'  => 5,
			'max'  => 60,
			'desc' => __( 'Seats are held for this many minutes during checkout.', 'dish-events' ),
		] );
	}

	// =========================================================================
	// Tab: Venue
	// =========================================================================

	private function register_venue_tab(): void {
		$page = 'dish-settings-venue';

		add_settings_section( 'dish_venue', '', '__return_null', $page );

		foreach ( [
			'venue_name'        => __( 'Venue name', 'dish-events' ),
			'venue_address'     => __( 'Street address', 'dish-events' ),
			'venue_city'        => __( 'City', 'dish-events' ),
			'venue_province'    => __( 'Province / Territory', 'dish-events' ),
			'venue_postal_code' => __( 'Postal code', 'dish-events' ),
		] as $key => $label ) {
			$this->field( $page, 'dish_venue', $key, $label, [ $this, 'render_text' ], [] );
		}

		$this->field( $page, 'dish_venue', 'venue_google_maps_url', __( 'Google Maps URL', 'dish-events' ), [ $this, 'render_url' ], [] );
		$this->field( $page, 'dish_venue', 'venue_lat', __( 'Latitude', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'e.g. 43.6532', 'dish-events' ),
		] );
		$this->field( $page, 'dish_venue', 'venue_lng', __( 'Longitude', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'e.g. -79.3832', 'dish-events' ),
		] );
		$this->field( $page, 'dish_venue', 'venue_gmap_api_key', __( 'Google Maps API key', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'Used for the embedded map on the class detail page.', 'dish-events' ),
		] );
		$this->field( $page, 'dish_venue', 'venue_hours', __( 'Hours of operation', 'dish-events' ), [ $this, 'render_textarea' ], [
			'desc' => __( 'One line per day range, e.g.<br><code>Monday–Friday 09:00–17:00</code><br><code>Saturday 10:00–15:00</code><br>Used for schema.org LocalBusiness structured data.', 'dish-events' ),
		] );
	}

	// =========================================================================
	// Tab: Studio
	// =========================================================================

	private function register_studio_tab(): void {
		$page = 'dish-settings-studio';

		add_settings_section( 'dish_studio', '', '__return_null', $page );

		foreach ( [
			'studio_name'    => __( 'Studio name', 'dish-events' ),
			'studio_email'   => __( 'Contact email', 'dish-events' ),
			'studio_phone'   => __( 'Phone number', 'dish-events' ),
		] as $key => $label ) {
			$this->field( $page, 'dish_studio', $key, $label, [ $this, 'render_text' ], [] );
		}

		$this->field( $page, 'dish_studio', 'studio_website',  __( 'Website URL', 'dish-events' ),   [ $this, 'render_url' ], [] );
		$this->field( $page, 'dish_studio', 'studio_instagram', __( 'Instagram URL', 'dish-events' ), [ $this, 'render_url' ], [] );
		$this->field( $page, 'dish_studio', 'studio_facebook',  __( 'Facebook URL', 'dish-events' ),  [ $this, 'render_url' ], [] );
	}

	// =========================================================================
	// Tab: Pages
	// =========================================================================

	private function register_pages_tab(): void {
		$page = 'dish-settings-pages';

		add_settings_section( 'dish_pages', '', '__return_null', $page );

		$page_labels = [
			'classes_page'         => __( 'Classes listing page', 'dish-events' ),
			'booking_page'         => __( 'Checkout page', 'dish-events' ),
			'booking_details_page' => __( 'Booking details page', 'dish-events' ),
			'enquiry_page'         => __( 'Enquiry / Contact page', 'dish-events' ),
			'profile_page'         => __( 'My account page', 'dish-events' ),
			'login_page'           => __( 'Login page', 'dish-events' ),
			'register_page'        => __( 'Register page', 'dish-events' ),
			'chefs_page'           => __( 'Chefs listing page', 'dish-events' ),
		];

		foreach ( $page_labels as $key => $label ) {
			$this->field( $page, 'dish_pages', $key, $label, [ $this, 'render_page_dropdown' ], [] );
		}
	}

	// =========================================================================
	// Tab: Calendar
	// =========================================================================

	private function register_calendar_tab(): void {
		$page = 'dish-settings-calendar';

		add_settings_section( 'dish_calendar', '', '__return_null', $page );

		$this->field( $page, 'dish_calendar', 'default_cal_view', __( 'Default calendar view', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'month' => __( 'Month', 'dish-events' ),
				'week'  => __( 'Week', 'dish-events' ),
				'day'   => __( 'Day', 'dish-events' ),
				'list'  => __( 'List', 'dish-events' ),
			],
		] );

		$this->field( $page, 'dish_calendar', 'available_views', __( 'Available views', 'dish-events' ), [ $this, 'render_checkbox_group' ], [
			'options' => [
				'month' => __( 'Month', 'dish-events' ),
				'week'  => __( 'Week', 'dish-events' ),
				'day'   => __( 'Day', 'dish-events' ),
				'list'  => __( 'List', 'dish-events' ),
			],
		] );

		$this->field( $page, 'dish_calendar', 'hide_past_classes', __( 'Hide past classes', 'dish-events' ), [ $this, 'render_checkbox' ], [
			'label' => __( 'Do not display classes with a start date in the past', 'dish-events' ),
		] );

		$this->field( $page, 'dish_calendar', 'classes_per_page', __( 'Classes per page (list view)', 'dish-events' ), [ $this, 'render_number' ], [
			'min' => 1, 'max' => 100,
		] );

		$this->field( $page, 'dish_calendar', 'show_class_type_on_calendar', __( 'Show class format on calendar', 'dish-events' ), [ $this, 'render_checkbox' ], [
			'label' => __( 'Display the class format label on each calendar event', 'dish-events' ),
		] );

		$this->field( $page, 'dish_calendar', 'max_classes_per_day', __( 'Max visible per day (month view)', 'dish-events' ), [ $this, 'render_number' ], [
			'min' => 1, 'max' => 20,
			'desc' => __( 'Additional classes are shown in a "+N more" link.', 'dish-events' ),
		] );

		$this->field( $page, 'dish_calendar', 'open_class_in_new_tab', __( 'Open class in new tab', 'dish-events' ), [ $this, 'render_checkbox' ], [
			'label' => __( 'Calendar event links open in a new browser tab', 'dish-events' ),
		] );

		$this->field( $page, 'dish_calendar', 'spots_left_threshold', __( '"Spots left" label threshold', 'dish-events' ), [ $this, 'render_number' ], [
			'min'  => 0,
			'max'  => 50,
			'desc' => __( 'Show a "N spots left!" badge on calendar events when spots remaining is at or below this number. Set to 0 to disable.', 'dish-events' ),
		] );
	}

	// =========================================================================
	// Tab: Payments
	// =========================================================================

	private function register_payments_tab(): void {
		$page = 'dish-settings-payments';

		add_settings_section( 'dish_payments', '', '__return_null', $page );

		$this->field( $page, 'dish_payments', 'active_gateway', __( 'Active gateway', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'paypal' => 'PayPal',
				'stripe' => 'Stripe',
				'none'   => __( 'None (free classes only)', 'dish-events' ),
			],
		] );

		add_settings_section(
			'dish_payments_paypal',
			'<hr><h2>' . esc_html__( 'PayPal', 'dish-events' ) . '</h2>',
			'__return_null',
			$page
		);

		$this->field( $page, 'dish_payments_paypal', 'paypal_mode', __( 'Mode', 'dish-events' ), [ $this, 'render_select' ], [
			'options' => [
				'sandbox' => __( 'Sandbox (testing)', 'dish-events' ),
				'live'    => __( 'Live', 'dish-events' ),
			],
		] );

		$this->field( $page, 'dish_payments_paypal', 'paypal_client_id', __( 'Client ID', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'From PayPal Developer Dashboard → My Apps & Credentials.', 'dish-events' ),
		] );
	}

	// =========================================================================
	// Tab: Emails
	// =========================================================================

	private function register_emails_tab(): void {
		$page = 'dish-settings-emails';

		add_settings_section( 'dish_emails_from', '<h2>' . esc_html__( 'Sender', 'dish-events' ) . '</h2>', '__return_null', $page );

		$this->field( $page, 'dish_emails_from', 'email_from_name',    __( 'From name', 'dish-events' ),    [ $this, 'render_text' ], [] );
		$this->field( $page, 'dish_emails_from', 'email_from_address', __( 'From address', 'dish-events' ), [ $this, 'render_text' ], [] );
		$this->field( $page, 'dish_emails_from', 'email_admin_to',     __( 'Admin notify address', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'Admin notification emails are sent here.', 'dish-events' ),
		] );

		$email_templates = [
			'email_booking_confirmation' => __( 'Booking confirmation', 'dish-events' ),
			'email_booking_cancelled'    => __( 'Booking cancelled', 'dish-events' ),
			'email_booking_reminder'     => __( 'Booking reminder', 'dish-events' ),
			'email_waitlist_available'   => __( 'Waitlist spot available', 'dish-events' ),
			'email_payment_receipt'      => __( 'Payment receipt', 'dish-events' ),
			'email_admin_new_booking'    => __( 'Admin: new booking', 'dish-events' ),
			'email_admin_cancellation'   => __( 'Admin: cancellation', 'dish-events' ),
		];

		foreach ( $email_templates as $prefix => $title ) {
			add_settings_section(
				'dish_' . $prefix,
				'<hr><h3>' . esc_html( $title ) . '</h3>',
				'__return_null',
				$page
			);

			$this->field( $page, 'dish_' . $prefix, $prefix . '_enabled', __( 'Enabled', 'dish-events' ), [ $this, 'render_checkbox' ], [
				'label' => __( 'Send this email', 'dish-events' ),
			] );
			$this->field( $page, 'dish_' . $prefix, $prefix . '_subject', __( 'Subject', 'dish-events' ), [ $this, 'render_text' ], [] );
			$this->field( $page, 'dish_' . $prefix, $prefix . '_cc', __( 'CC', 'dish-events' ), [ $this, 'render_text' ], [
				'desc' => __( 'Optional. Comma-separated addresses.', 'dish-events' ),
			] );
			$this->field( $page, 'dish_' . $prefix, $prefix . '_body', __( 'Body', 'dish-events' ), [ $this, 'render_textarea' ], [
				'desc' => __( 'Available tokens: {{booking_id}}, {{customer_name}}, {{customer_email}}, {{customer_phone}}, {{class_title}}, {{class_date}}, {{class_time}}, {{class_location}}, {{ticket_type}}, {{quantity}}, {{amount}}, {{booking_details_url}}, {{studio_name}}, {{studio_email}}, {{studio_phone}}. Leave blank to use the built-in template.', 'dish-events' ),
			] );
		}
	}

	// =========================================================================
	// Tab: URLs
	// =========================================================================

	private function register_urls_tab(): void {
		$page = 'dish-settings-urls';

		add_settings_section( 'dish_urls', '', '__return_null', $page );

		$this->field( $page, 'dish_urls', 'class_slug', __( 'Class URL slug', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'e.g. "cooking-class" → /cooking-class/my-class/', 'dish-events' ),
		] );
		$this->field( $page, 'dish_urls', 'chef_slug', __( 'Chef URL slug', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'e.g. "chef"', 'dish-events' ),
		] );
		$this->field( $page, 'dish_urls', 'class_format_slug', __( 'Class format taxonomy slug', 'dish-events' ), [ $this, 'render_text' ], [
			'desc' => __( 'e.g. "class-format". Changing this will flush rewrite rules.', 'dish-events' ),
		] );

		add_settings_section(
			'dish_urls_note',
			'',
			static function (): void {
				echo '<p class="description">' . esc_html__( 'Saving any URL slug change will automatically flush WordPress rewrite rules.', 'dish-events' ) . '</p>';
			},
			$page
		);
	}

	// =========================================================================
	// Tab: Checkout
	// =========================================================================

	private function register_checkout_tab(): void {
		$page = 'dish-settings-checkout';

		add_settings_section( 'dish_checkout', '', '__return_null', $page );

		$this->field(
			$page, 'dish_checkout', 'terms_enabled',
			__( 'Terms checkbox', 'dish-events' ),
			[ $this, 'render_checkbox' ],
			[ 'label' => __( 'Require customers to accept terms before completing a booking', 'dish-events' ) ]
		);

		$this->field(
			$page, 'dish_checkout', 'terms_label',
			__( 'Checkbox label', 'dish-events' ),
			[ $this, 'render_textarea' ],
			[
				'rows' => 3,
				'desc' => __( 'HTML is allowed. Use an &lt;a&gt; tag to link to your terms page. e.g. <code>I agree to the &lt;a href="/terms" target="_blank"&gt;Terms &amp; Conditions&lt;/a&gt;.</code>', 'dish-events' ),
			]
		);

		// Global checkout fields builder — rendered entirely via section callback.
		add_settings_section(
			'dish_checkout_fields',
			'<hr><h3>' . esc_html__( 'Global Checkout Fields', 'dish-events' ) . '</h3>',
			[ $this, 'render_checkout_fields_section' ],
			$page
		);
	}

	// =========================================================================
	// Tab: Checkout — field builder
	// =========================================================================

	/**
	 * Section callback: renders the global checkout fields repeater table.
	 * No WP settings fields are registered to this section — all output
	 * is produced here so the builder sits outside the form-table wrapper.
	 */
	public function render_checkout_fields_section(): void {
		$fields      = CheckoutFieldRepository::get_active();
		$field_types = [
			'text'     => __( 'Text',     'dish-events' ),
			'textarea' => __( 'Textarea', 'dish-events' ),
			'select'   => __( 'Select',   'dish-events' ),
			'checkbox' => __( 'Checkbox', 'dish-events' ),
			'radio'    => __( 'Radio',    'dish-events' ),
		];
		?>
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Fields shown at checkout for every class. Per-attendee fields repeat once per ticket. Individual classes can override these with their own field set.', 'dish-events' ); ?>
		</p>
		<table class="widefat striped dish-gcf-table" style="table-layout:fixed;max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Label', 'dish-events' ); ?></th>
					<th style="width:130px;"><?php esc_html_e( 'Type', 'dish-events' ); ?></th>
					<th style="width:90px;text-align:center;"><?php esc_html_e( 'Required', 'dish-events' ); ?></th>
					<th style="width:120px;text-align:center;"><?php esc_html_e( 'Per Attendee', 'dish-events' ); ?></th>
					<th style="width:42px;"></th>
				</tr>
			</thead>
			<tbody id="dish-gcf-body">
				<?php foreach ( $fields as $i => $field ) : ?>
					<?php $this->render_checkout_field_row( $i, $field, $field_types ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p style="margin-top:10px;">
			<button type="button" class="button" id="dish-gcf-add">
				<span class="dashicons dashicons-plus-alt2" style="margin-top:3px;vertical-align:middle;"></span>
				<?php esc_html_e( 'Add Field', 'dish-events' ); ?>
			</button>
		</p>
		<?php // Hidden template row for JS cloning (never submitted — no name/value on the table itself). ?>
		<table id="dish-gcf-tpl" style="display:none;"><tbody>
			<?php $this->render_checkout_field_row( '__IDX__', null, $field_types ); ?>
		</tbody></table>
		<?php
	}

	/**
	 * Render a single global checkout field table row.
	 *
	 * @param int|string           $idx         Row index or '__IDX__' for the JS template.
	 * @param object|null          $field       DB stdClass row; null = blank template.
	 * @param array<string,string> $field_types Type => label map.
	 */
	private function render_checkout_field_row( int|string $idx, ?object $field, array $field_types ): void {
		$id           = $field ? (int) $field->id : 0;
		$label        = $field ? esc_attr( (string) $field->label ) : '';
		$type         = $field ? (string) $field->field_type : 'text';
		$is_required  = $field && (bool) $field->is_required;
		$per_attendee = $field && (bool) $field->apply_per_attendee;
		$options_val  = $field ? esc_attr( (string) ( $field->options ?? '' ) ) : '';
		$needs_opts   = in_array( $type, [ 'select', 'radio' ], true );
		$idx_attr     = esc_attr( (string) $idx );
		?>
		<tr class="dish-gcf-row">
			<td>
				<input type="hidden" name="dish_global_cf[<?php echo $idx_attr; ?>][id]" value="<?php echo $id; ?>">
				<input type="text" class="widefat"
					name="dish_global_cf[<?php echo $idx_attr; ?>][label]"
					value="<?php echo $label; ?>"
					placeholder="<?php esc_attr_e( 'Field label', 'dish-events' ); ?>">
				<span class="dish-gcf-opts" style="display:<?php echo $needs_opts ? 'block' : 'none'; ?>;margin-top:4px;">
					<input type="text" class="widefat"
						name="dish_global_cf[<?php echo $idx_attr; ?>][options]"
						value="<?php echo $options_val; ?>"
						placeholder="<?php esc_attr_e( 'Options — comma-separated', 'dish-events' ); ?>">
				</span>
			</td>
			<td>
				<select name="dish_global_cf[<?php echo $idx_attr; ?>][field_type]" class="widefat dish-gcf-type">
					<?php foreach ( $field_types as $val => $text ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $type, $val ); ?>>
							<?php echo esc_html( $text ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td style="text-align:center;">
				<input type="checkbox"
					name="dish_global_cf[<?php echo $idx_attr; ?>][is_required]"
					value="1" <?php checked( $is_required ); ?>>
			</td>
			<td style="text-align:center;">
				<input type="checkbox"
					name="dish_global_cf[<?php echo $idx_attr; ?>][apply_per_attendee]"
					value="1" <?php checked( $per_attendee ); ?>>
			</td>
			<td>
				<button type="button" class="button-link dish-gcf-remove"
					title="<?php esc_attr_e( 'Remove field', 'dish-events' ); ?>">
					<span class="dashicons dashicons-trash" style="color:#b32d2e;font-size:18px;width:18px;height:18px;"></span>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Sync the global checkout fields DB table from POST data.
	 *
	 * Called as a side-effect from sanitize() when the Checkout tab is saved.
	 * options.php has already verified the nonce before invoking sanitize().
	 */
	private function save_global_checkout_fields(): void {
		// phpcs:ignore WordPress.Security.NonceVerification
		$rows = isset( $_POST['dish_global_cf'] ) && is_array( $_POST['dish_global_cf'] )
			? $_POST['dish_global_cf']
			: [];

		$allowed_types = [ 'text', 'textarea', 'select', 'checkbox', 'radio' ];
		$active_ids    = array_map( 'intval', array_column( CheckoutFieldRepository::get_active(), 'id' ) );
		$saved_ids     = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$label = sanitize_text_field( $row['label'] ?? '' );
			if ( $label === '' ) {
				continue;
			}

			$field_type = sanitize_key( $row['field_type'] ?? 'text' );
			if ( ! in_array( $field_type, $allowed_types, true ) ) {
				$field_type = 'text';
			}

			$data = [
				'label'              => $label,
				'field_type'         => $field_type,
				'options'            => sanitize_text_field( $row['options'] ?? '' ) ?: null,
				'is_required'        => ! empty( $row['is_required'] ),
				'apply_per_attendee' => ! empty( $row['apply_per_attendee'] ),
				'is_active'          => true,
			];

			if ( ! empty( $row['id'] ) && (int) $row['id'] > 0 ) {
				$data['id'] = absint( $row['id'] );
			}

			$saved_id = CheckoutFieldRepository::save( $data );
			if ( null !== $saved_id ) {
				$saved_ids[] = $saved_id;
			}
		}

		// Soft-delete any active fields that were removed from the form.
		foreach ( $active_ids as $id ) {
			if ( ! in_array( $id, $saved_ids, true ) ) {
				CheckoutFieldRepository::delete( $id );
			}
		}
	}

	/** Inline JS for the global checkout fields repeater. */
	private function checkout_fields_js(): string {
		return <<<'JS'
jQuery(function($){
	var idx = $('#dish-gcf-body tr').length;

	$('#dish-gcf-add').on('click', function () {
		var tpl = $('#dish-gcf-tpl tbody tr:first').prop('outerHTML');
		if ( ! tpl ) { return; }
		$('#dish-gcf-body').append( tpl.replace(/__IDX__/g, String(idx++)) );
	});

	$(document).on('click', '.dish-gcf-remove', function () {
		$(this).closest('tr').remove();
	});

	$(document).on('change', '.dish-gcf-type', function () {
		var need = this.value === 'select' || this.value === 'radio';
		$(this).closest('tr').find('.dish-gcf-opts').toggle(need);
	});
});
JS;
	}

	// =========================================================================
	// Tab: Features
	// =========================================================================

	private function register_features_tab(): void {
		$page = 'dish-settings-features';

		add_settings_section( 'dish_features', '', '__return_null', $page );

		$toggles = [
			'google_calendar_link' => [
				'label' => __( 'Show "Add to Google Calendar" link after booking', 'dish-events' ),
				'title' => __( 'Google Calendar link', 'dish-events' ),
			],
			'ical_download' => [
				'label' => __( 'Allow .ics download from booking confirmation', 'dish-events' ),
				'title' => __( 'iCal download', 'dish-events' ),
			],
			'show_qr_on_booking' => [
				'label' => __( 'Display a QR code on the booking confirmation page', 'dish-events' ),
				'title' => __( 'QR code on booking', 'dish-events' ),
			],
			'guest_checkout' => [
				'label' => __( 'Allow bookings without an account', 'dish-events' ),
				'title' => __( 'Guest checkout', 'dish-events' ),
			],
			'allow_account_creation' => [
				'label' => __( 'Offer account creation during checkout', 'dish-events' ),
				'title' => __( 'Account creation at checkout', 'dish-events' ),
			],
		];

		foreach ( $toggles as $key => $args ) {
			$this->field( $page, 'dish_features', $key, $args['title'], [ $this, 'render_checkbox' ], [
				'label' => $args['label'],
			] );
		}
	}

	// =========================================================================
	// Field registration helper
	// =========================================================================

	/**
	 * Sugar: add_settings_field() with args automatically prepended.
	 *
	 * @param string   $page     Settings page slug (do_settings_sections target).
	 * @param string   $section  Section ID.
	 * @param string   $key      Setting key (also the field ID).
	 * @param string   $title    Label shown in the th column.
	 * @param callable $callback Render callback.
	 * @param array<string, mixed> $extra   Extra args passed through to the renderer.
	 */
	private function field(
		string $page,
		string $section,
		string $key,
		string $title,
		callable $callback,
		array $extra
	): void {
		add_settings_field(
			'dish_' . $key,
			$title,
			$callback,
			$page,
			$section,
			array_merge( [ 'key' => $key, 'label_for' => 'dish_' . $key ], $extra )
		);
	}

	// =========================================================================
	// Render helpers
	// =========================================================================

	/** @param array<string, mixed> $args */
	public function render_text( array $args ): void {
		$key   = $args['key'];
		$value = self::get( $key, '' );
		$desc  = $args['desc'] ?? '';
		printf(
			'<input type="text" id="dish_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
			esc_attr( $key ),
			esc_attr( self::OPTION ),
			esc_attr( (string) $value )
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/** @param array<string, mixed> $args */
	public function render_url( array $args ): void {
		$key   = $args['key'];
		$value = self::get( $key, '' );
		$desc  = $args['desc'] ?? '';
		printf(
			'<input type="url" id="dish_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
			esc_attr( $key ),
			esc_attr( self::OPTION ),
			esc_attr( (string) $value )
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/** @param array<string, mixed> $args */
	public function render_number( array $args ): void {
		$key  = $args['key'];
		$val  = (int) self::get( $key, 0 );
		$desc = $args['desc'] ?? '';
		printf(
			'<input type="number" id="dish_%1$s" name="%2$s[%1$s]" value="%3$d" min="%4$d" max="%5$d" class="small-text">',
			esc_attr( $key ),
			esc_attr( self::OPTION ),
			$val,
			(int) ( $args['min'] ?? 0 ),
			(int) ( $args['max'] ?? 9999 )
		);
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/** @param array<string, mixed> $args */
	public function render_checkbox( array $args ): void {
		$key     = $args['key'];
		$checked = (bool) self::get( $key, false );
		$label   = $args['label'] ?? '';
		printf(
			'<label><input type="checkbox" id="dish_%1$s" name="%2$s[%1$s]" value="1" %3$s> %4$s</label>',
			esc_attr( $key ),
			esc_attr( self::OPTION ),
			checked( $checked, true, false ),
			esc_html( $label )
		);
	}

	/** @param array<string, mixed> $args */
	public function render_select( array $args ): void {
		$key     = $args['key'];
		$current = (string) self::get( $key, '' );
		$options = $args['options'] ?? [];

		printf( '<select id="dish_%s" name="%s[%s]">', esc_attr( $key ), esc_attr( self::OPTION ), esc_attr( $key ) );
		foreach ( $options as $val => $text ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( (string) $val ),
				selected( $current, (string) $val, false ),
				esc_html( $text )
			);
		}
		echo '</select>';
	}

	/** @param array<string, mixed> $args */
	public function render_textarea( array $args ): void {
		$key   = $args['key'];
		$value = (string) self::get( $key, '' );
		$desc  = $args['desc'] ?? '';
		$rows  = isset( $args['rows'] ) ? (int) $args['rows'] : 6;
		printf(
			'<textarea id="dish_%1$s" name="%2$s[%1$s]" rows="%4$d" class="large-text">%3$s</textarea>',
			esc_attr( $key ),
			esc_attr( self::OPTION ),
			esc_textarea( $value ),
			$rows
		);
		if ( $desc ) {
			echo '<p class="description">' . wp_kses( $desc, [ 'a' => [ 'href' => [], 'target' => [] ], 'code' => [], 'br' => [] ] ) . '</p>';
		}
	}

	/** @param array<string, mixed> $args */
	public function render_page_dropdown( array $args ): void {
		$key      = $args['key'];
		$selected = (int) self::get( $key, 0 );
		wp_dropdown_pages( [
			'id'               => 'dish_' . $key,
			'name'             => self::OPTION . '[' . $key . ']',
			'selected'         => $selected,
			'show_option_none' => __( '— Select page —', 'dish-events' ),
			'option_none_value' => '0',
		] );
	}

	/** @param array<string, mixed> $args */
	public function render_checkbox_group( array $args ): void {
		$key     = $args['key'];
		$current = (array) self::get( $key, [] );
		$options = $args['options'] ?? [];

		foreach ( $options as $val => $text ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s> %5$s</label>',
				esc_attr( self::OPTION ),
				esc_attr( $key ),
				esc_attr( (string) $val ),
				checked( in_array( $val, $current, true ), true, false ),
				esc_html( $text )
			);
		}
	}
}
