<?php

declare(strict_types=1);
/**
 * Scheduled events for Basecamp theme.
 *
 * Wire up recurring background tasks here. Keep each task isolated:
 * one scheduling function, one callback, one cleanup hook.
 *
 * Activation / deactivation hooks must be called from functions.php (or a
 * plugin file) — they cannot be called from inside a require_once'd theme
 * file during a normal request. Use after_switch_theme / switch_theme instead
 * when scheduling from a theme.
 *
 * Quick reference:
 *   - wp_schedule_event( $timestamp, $recurrence, $hook )  — schedule a recurring event
 *   - wp_next_scheduled( $hook )                           — check if already scheduled
 *   - wp_unschedule_event( $timestamp, $hook )             — remove a single occurrence
 *   - wp_clear_scheduled_hook( $hook )                     — remove all occurrences
 *   - wp_get_schedules()                                   — list available intervals
 *
 * @package basecamp
 */

namespace Basecamp\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WP-Cron schedules for the Basecamp theme.
 */
final class ScheduledEvents {

	// =============================================================================
	// Bootstrap
	// =============================================================================

	/**
	 * Register all hooks.
	 */
	public static function init(): void {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_intervals' ] );
		add_action( 'after_switch_theme', [ __CLASS__, 'schedule_events' ] );
		add_action( 'switch_theme',       [ __CLASS__, 'unschedule_events' ] );
		add_action( 'basecamp_daily_maintenance', [ __CLASS__, 'daily_maintenance_callback' ] );
	}

	// =============================================================================
	// Custom cron intervals
	// =============================================================================

	/**
	 * Register custom cron recurrence intervals.
	 *
	 * 'weekly' is not built into WordPress — add it (and any other custom intervals
	 * your tasks need) here so wp_schedule_event() accepts them.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function add_cron_intervals( array $schedules ): array {
		$schedules['weekly'] = [
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'basecamp' ),
		];
		return $schedules;
	}

	// =============================================================================
	// Schedule events on theme activation
	// =============================================================================

	/**
	 * Schedule recurring events when the theme is activated.
	 *
	 * after_switch_theme fires once on activation. Always guard with
	 * wp_next_scheduled() so re-activating the theme doesn't stack duplicates.
	 */
	public static function schedule_events(): void {
		// Example: run a maintenance task every day at the next natural tick.
		// Replace 'basecamp_daily_maintenance' with your actual hook name.
		if ( ! wp_next_scheduled( 'basecamp_daily_maintenance' ) ) {
			wp_schedule_event( time(), 'daily', 'basecamp_daily_maintenance' );
		}

		// Add further wp_schedule_event() calls here for additional tasks.
		// Each task should have a unique hook name to avoid collisions.
	}

	// =============================================================================
	// Unschedule events on theme deactivation
	// =============================================================================

	/**
	 * Clear all theme-owned scheduled events when the theme is deactivated.
	 *
	 * wp_clear_scheduled_hook() removes every queued occurrence of the given hook,
	 * including future recurrences — cleaner than wp_unschedule_event().
	 */
	public static function unschedule_events(): void {
		wp_clear_scheduled_hook( 'basecamp_daily_maintenance' );

		// Mirror every hook registered in schedule_events() above.
	}

	// =============================================================================
	// Callbacks
	// =============================================================================

	/**
	 * Daily maintenance callback.
	 *
	 * Called by WP-Cron on the 'basecamp_daily_maintenance' hook.
	 * Replace the body with real work — e.g. deleting expired transients,
	 * pruning stale option rows, or syncing data from an external API.
	 *
	 * Keep callbacks fast: hand off slow work to a separate scheduled hook
	 * or process it in small batches using a transient-guarded loop.
	 */
	public static function daily_maintenance_callback(): void {
		// Example: delete a specific transient that should refresh daily.
		// delete_transient( 'basecamp_something_cached' );
	}
}

ScheduledEvents::init();
