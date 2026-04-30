<?php
/**
 * Core plugin bootstrap.
 *
 * Instantiates the Loader, wires all module hooks, then fires
 * the dish_events_loaded action once everything is registered.
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

/**
 * Class Plugin
 *
 * Singleton — call Plugin::run() once on plugins_loaded.
 * Do not instantiate directly.
 */
final class Plugin {

	private Loader $loader;

	/**
	 * Private constructor — use Plugin::run().
	 */
	private function __construct() {
		$this->loader = new Loader();
		$this->wire_hooks();
	}

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	/**
	 * Entry point. Called once via add_action( 'plugins_loaded', ... ) in
	 * dish-events.php. Subsequent calls are no-ops.
	 */
	public static function run(): void {
		static $instance = null;

		if ( null !== $instance ) {
			return;
		}

		$instance = new self();
		$instance->loader->run();

		/**
		 * Fires after Dish Events has fully bootstrapped and all hooks are registered.
		 *
		 * @since 1.0.0
		 */
		do_action( 'dish_events_loaded' );
	}

	// -------------------------------------------------------------------------
	// Hook wiring
	// -------------------------------------------------------------------------

	/**
	 * Register all module hooks with the Loader.
	 *
	 * Modules are added here phase by phase as the plugin is built.
	 * The Loader collects all add_action / add_filter calls and
	 * registers them all at once in Loader::run().
	 */
	private function wire_hooks(): void {

		// Run DB migrations immediately (synchronous — must happen before
		// any module tries to use the tables).
		( new Updater() )->run();

		// Pipe dish_settings into Basecamp's basecamp_schema_* filters and
		// boot the DishSchema graph extension. Kept here (not in the theme)
		// because all data originates from plugin-owned options.
		ThemeIntegration::init();

		// Register custom cron schedule intervals.
		$this->loader->add_filter( 'cron_schedules', $this, 'register_cron_schedules' );

		// Extend post-thumbnail support to plugin CPTs.
		// The Basecamp theme limits thumbnails to 'post' and 'page' only;
		// WP merges arrays on repeated add_theme_support() calls, so this
		// correctly appends our types without clobbering the theme's list.
		$this->loader->add_action( 'after_setup_theme', $this, 'add_thumbnail_support', 11 );

		// Phase 9: checkout-timer cleanup cron — fires every 15 minutes.
		$checkout_timer = new \Dish\Events\Booking\CheckoutTimer();
		$this->loader->add_action( 'dish_cleanup_expired_bookings', $checkout_timer, 'cleanup_expired' );

		// Flush rewrite rules once after activation (when CPTs are registered in Phase 2+).
		$this->loader->add_action( 'admin_init', $this, 'maybe_flush_rewrite_rules' );

		// -------------------------------------------------------------------------
		// Phase 2 — CPTs, Taxonomy, Post Statuses
		// -------------------------------------------------------------------------

		$class_post = new \Dish\Events\CPT\ClassPost();
		$this->loader->add_action( 'init', $class_post, 'register' );

		$class_template_post = new \Dish\Events\CPT\ClassTemplatePost();
		$this->loader->add_action( 'init', $class_template_post, 'register' );

		// Trash / delete recurrence children — not gated to admin, can be
		// triggered programmatically or via REST.
		$rec_mgr = new \Dish\Events\Recurrence\RecurrenceManager();
		$this->loader->add_action( 'wp_trash_post',      $rec_mgr, 'delete_series' );
		$this->loader->add_action( 'before_delete_post', $rec_mgr, 'delete_series' );

		$chef_post = new \Dish\Events\CPT\ChefPost();
		$this->loader->add_action( 'init', $chef_post, 'register' );

		$booking_post = new \Dish\Events\CPT\BookingPost();
		$this->loader->add_action( 'init', $booking_post, 'register' );

		$format_post = new \Dish\Events\CPT\FormatPost();
		$this->loader->add_action( 'init', $format_post, 'register' );

		// Class template permalink hooks — must run on BOTH frontend and admin so
		// that get_permalink() resolves to /classes/{format-slug}/{template-slug}/
		// everywhere (archives, chef pages, sitemaps, etc.).
		$ct_permalink = new \Dish\Events\Admin\ClassTemplateAdmin();
		$this->loader->add_action( 'init',           $ct_permalink, 'register_rewrite_rule', 20 );
		$this->loader->add_filter( 'post_type_link', $ct_permalink, 'filter_post_type_link', 10, 2 );

		// -------------------------------------------------------------------------
		// Phase 2.5 — Frontend template loader
		// -------------------------------------------------------------------------
		$frontend = new \Dish\Events\Frontend\Frontend();
		$frontend->register_hooks( $this->loader );

		// -------------------------------------------------------------------------
		// Phase 3 — Settings + Admin
		// -------------------------------------------------------------------------
		if ( is_admin() ) {
			$admin = new \Dish\Events\Admin\Admin( $this->loader );
			$admin->register_hooks();
		}

		// -------------------------------------------------------------------------
		// Phase 7 — Frontend Templates & Shortcodes
		// -------------------------------------------------------------------------

		// Conditionally enqueues dish-events.css on CPT singles and shortcode pages.
		$assets = new \Dish\Events\Frontend\Assets();
		$assets->register_hooks( $this->loader );

		// Registers [dish_classes], [dish_chefs], [dish_login], [dish_register], [dish_profile].
		$shortcodes = new \Dish\Events\Frontend\Shortcodes();
		$shortcodes->register_hooks( $this->loader );

		// Google Reviews — server-side transient fetch + [dish_reviews] shortcode.
		// The cron refresh fires twice daily; the API is never called from the browser.
		$this->loader->add_action( \Dish\Events\Core\GoogleReviews::CRON_HOOK, new \Dish\Events\Core\GoogleReviews(), 'refresh' );

		// View classes (ClassView, ChefView, FormatView) are static — no instantiation needed.
		// They are autoloaded by the PSR-4 loader on first use.

		// -------------------------------------------------------------------------
		// Phase 6 — Data Layer
		// Repositories and helpers are stateless static classes; they require no
		// hook registration. They are autoloaded on first use via the PSR-4
		// autoloader in dish-events.php. Listed here for discoverability only.
		//
		// Dish\Events\Data\ClassRepository
		// Dish\Events\Data\ClassTemplateRepository
		// Dish\Events\Data\BookingRepository
		// Dish\Events\Data\ChefRepository
		// Dish\Events\Data\TicketTypeRepository
		// Dish\Events\Data\CheckoutFieldRepository
		// Dish\Events\Helpers\DateHelper
		// Dish\Events\Helpers\MoneyHelper
		// -------------------------------------------------------------------------

		// -------------------------------------------------------------------------
		// Phase 8 — Calendar (FullCalendar + REST)
		// -------------------------------------------------------------------------

		// Public REST endpoint: GET /wp-json/dish/v1/classes
		// Returns FullCalendar-compatible event objects for a given date range.
		$classes_endpoint = new \Dish\Events\REST\ClassesEndpoint();
		$classes_endpoint->register_hooks( $this->loader );

		// Calendar::render() / Calendar::enqueue() are called lazily from
		// ClassView when [dish_classes view="calendar"] is used — no hook
		// registration needed at bootstrap time.

		// -------------------------------------------------------------------------
		// Phase 9 — Booking & Checkout (guest-first, payment stub)
		// -------------------------------------------------------------------------

		// Public AJAX: dish_process_booking (complete checkout) + dish_release_hold.
		// Both are nopriv so guests can submit without being logged in.
		$public_ajax = new \Dish\Events\Frontend\PublicAjax();
		$public_ajax->register_hooks( $this->loader );

		// -------------------------------------------------------------------------
		// Phase 11 — Notifications
		// -------------------------------------------------------------------------

		// Listens on dish_booking_created and transition_post_status.
		// Dispatches studio + customer emails for confirmed and cancelled bookings.
		// Reminder, waitlist, and payment-receipt emails are wired in later phases.
		$notifications = new \Dish\Events\Notifications\NotificationService();
		$notifications->register_hooks( $this->loader );
	}

	// -------------------------------------------------------------------------
	// Theme supports
	// -------------------------------------------------------------------------

	/**
	 * Extend post-thumbnail support to plugin CPTs.
	 *
	 * The Basecamp theme enables thumbnails only for 'post' and 'page'.
	 * WordPress merges arrays on repeated add_theme_support() calls, so
	 * calling this at priority 11 (after the theme's 10) safely appends
	 * our post types without overwriting the theme's list.
	 */
	public function add_thumbnail_support(): void {
		add_theme_support( 'post-thumbnails', [ 'dish_class', 'dish_class_template', 'dish_chef', 'dish_format' ] );
	}

	// -------------------------------------------------------------------------
	// Cron schedules
	// -------------------------------------------------------------------------

	/**
	 * Register the every-15-minutes cron interval used by the booking
	 * timer cleanup job.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function register_cron_schedules( array $schedules ): array {
		if ( ! isset( $schedules['dish_every_15_minutes'] ) ) {
			$schedules['dish_every_15_minutes'] = [
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (Dish Events)', 'dish-events' ),
			];
		}

		return $schedules;
	}

	// -------------------------------------------------------------------------
	// Rewrite rules flush
	// -------------------------------------------------------------------------

	/**
	 * Flush rewrite rules once after activation, when CPTs are already registered.
	 * The flag is set by Activator::activate() and cleared here after flushing.
	 */
	public function maybe_flush_rewrite_rules(): void {
		if ( get_option( 'dish_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_option( 'dish_flush_rewrite_rules' );
		}
	}
}
