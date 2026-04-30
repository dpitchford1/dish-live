<?php
/**
 * Hook Loader.
 *
 * Collects all add_action() and add_filter() registrations made by plugin
 * modules and registers them all with WordPress in a single Loader::run()
 * call. Keeps hook registration centralised and easy to audit.
 *
 * @package Dish\Recipes\Core
 */

declare( strict_types=1 );

namespace Dish\Recipes\Core;

/**
 * Class Loader
 */
final class Loader {

	/**
	 * Accumulated action hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $actions = [];

	/**
	 * Accumulated filter hooks.
	 *
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	private array $filters = [];

	// -------------------------------------------------------------------------
	// Accumulate
	// -------------------------------------------------------------------------

	/**
	 * Add an action hook to the collection.
	 *
	 * @param string $hook          The WordPress action hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      The method name to call on $component.
	 * @param int    $priority      Hook priority (default 10).
	 * @param int    $accepted_args Number of arguments the callback accepts (default 1).
	 */
	public function add_action(
		string $hook,
		object $component,
		string $callback,
		int    $priority      = 10,
		int    $accepted_args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Add a filter hook to the collection.
	 *
	 * @param string $hook          The WordPress filter hook name.
	 * @param object $component     The object that owns the callback.
	 * @param string $callback      The method name to call on $component.
	 * @param int    $priority      Hook priority (default 10).
	 * @param int    $accepted_args Number of arguments the callback accepts (default 1).
	 */
	public function add_filter(
		string $hook,
		object $component,
		string $callback,
		int    $priority      = 10,
		int    $accepted_args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'accepted_args' );
	}

	// -------------------------------------------------------------------------
	// Register
	// -------------------------------------------------------------------------

	/**
	 * Register all accumulated hooks with WordPress.
	 * Called once by Plugin::run() after all modules have been wired.
	 */
	public function run(): void {
		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				[ $action['component'], $action['callback'] ],
				$action['priority'],
				$action['accepted_args']
			);
		}

		foreach ( $this->filters as $filter ) {
			add_filter(
				$filter['hook'],
				[ $filter['component'], $filter['callback'] ],
				$filter['priority'],
				$filter['accepted_args']
			);
		}
	}
}
