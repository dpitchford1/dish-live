<?php

declare(strict_types=1);
/**
 * REST API Endpoints
 *
 * Register custom REST routes here. The namespace pattern is:
 *   basecamp/v1/<route>
 *
 * Example endpoint below returns a simple health-check response.
 * Duplicate the register_rest_route() call to add new routes.
 *
 * Usage: GET /wp-json/basecamp/v1/ping
 *
 * @package basecamp
 */

add_action( 'rest_api_init', function () {

	register_rest_route( 'basecamp/v1', '/ping', [
		'methods'             => 'GET',
		'callback'            => function () {
			return [ 'status' => 'ok' ];
		},
		'permission_callback' => '__return_true',
	] );

} );
