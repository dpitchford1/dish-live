<?php
/**
 * DB Migration Runner.
 *
 * Compares the stored dish_db_version against DISH_EVENTS_VERSION and runs
 * any outstanding migration methods in sequential version order.
 *
 * Migration methods are named migrate_{major}_{minor}_{patch}() and are
 * idempotent — safe to run on a DB that is already up to date.
 *
 * Called synchronously at the top of Plugin::wire_hooks() so tables are
 * always current before any module tries to use them.
 *
 * @package Dish\Events\Core
 */

declare( strict_types=1 );

namespace Dish\Events\Core;

/**
 * Class Updater
 */
final class Updater {

	/**
	 * Run outstanding migrations if the installed DB version is behind
	 * the current plugin version.
	 */
	public function run(): void {
		$installed = (string) get_option( 'dish_db_version', '0.0.0' );

		if ( version_compare( $installed, DISH_EVENTS_VERSION, '>=' ) ) {
			// Already up to date.
			return;
		}

		$this->run_migrations( $installed );

		update_option( 'dish_db_version', DISH_EVENTS_VERSION );
	}

	// -------------------------------------------------------------------------
	// Migration runner
	// -------------------------------------------------------------------------

	/**
	 * Run every migration whose version is greater than $from_version.
	 *
	 * @param string $from_version The currently-installed DB schema version.
	 */
	private function run_migrations( string $from_version ): void {
		/**
		 * List of available migrations in ascending version order.
		 * Add new entries here as the schema evolves:
		 *
		 *   '1.1.0' => [ $this, 'migrate_1_1_0' ],
		 *   '2.0.0' => [ $this, 'migrate_2_0_0' ],
		 */
		$migrations = [
			'1.0.0' => [ $this, 'migrate_1_0_0' ],
			'1.0.1' => [ $this, 'migrate_1_0_1' ],
		];

		foreach ( $migrations as $version => $callable ) {
			if ( version_compare( $from_version, $version, '<' ) ) {
				call_user_func( $callable );
			}
		}
	}

	// -------------------------------------------------------------------------
	// Migrations
	// -------------------------------------------------------------------------

	/**
	 * v1.0.0 — Initial schema.
	 *
	 * Delegates to Activator::create_tables() which uses dbDelta() and is
	 * safe to run on an already-current schema.
	 */
	private function migrate_1_0_0(): void {
		Activator::create_tables();
	}

	/**
	 * v1.0.1 — Rename dish_tickets → dish_ticket_types (global templates).
	 *
	 * Drops the old per-class dish_tickets table (wrong schema / name) and
	 * calls create_tables() to apply the full corrected schema via dbDelta.
	 */
	private function migrate_1_0_1(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dish_tickets" );
		Activator::create_tables();
	}
}
