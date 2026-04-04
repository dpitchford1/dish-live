<?php

declare(strict_types=1);
/**
 * SEO — Master loader for Basecamp theme.
 *
 * Loads and boots all SEO modules. Each module defers automatically
 * to Yoast SEO or Rank Math if either plugin is active.
 *
 * @package basecamp
 */

use Basecamp\Admin\Settings;
use Basecamp\SEO\Schema;

require_once __DIR__ . '/basecamp-title-functions.php';

// Dish Events SEO extensions — only load when the plugin is active.
if ( class_exists( 'Dish\\Events\\Plugin' ) ) {
	require_once __DIR__ . '/basecamp-dish-title.php';
	require_once __DIR__ . '/basecamp-dish-schema.php';
}
require_once __DIR__ . '/basecamp-meta-description-functions.php';
require_once __DIR__ . '/basecamp-social-meta-functions.php';
require_once __DIR__ . '/class-basecamp-schema.php';

if ( Settings::get( 'schema_output', '1' ) ) {
	Schema::init();
}
