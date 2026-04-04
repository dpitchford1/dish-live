# Hooks, REST, and Extending

Reference for all actions, filters, REST endpoints, and AJAX handlers registered by the plugin, plus patterns for extending without modifying core files.

---

## Actions Fired by the Plugin

These are custom actions you can hook into from a child theme, mu-plugin, or companion plugin.

### `dish_events_loaded`

Fires after the plugin has fully bootstrapped and all hooks are registered with WordPress. The earliest safe point to call plugin APIs from external code.

```php
add_action( 'dish_events_loaded', function() {
    // Safe to use Dish\Events\* classes here.
} );
```

---

### `dish_booking_created`

Fires immediately after a new `dish_booking` post is created (status: `dish_pending`, pre-payment).

```php
/**
 * @param int      $booking_id  Post ID of the new dish_booking.
 * @param int      $class_id    Post ID of the booked dish_class.
 * @param int      $qty         Number of seats booked.
 * @param int      $total_cents Total amount in cents.
 */
add_action( 'dish_booking_created', function( int $booking_id, int $class_id, int $qty, int $total_cents ) {
    // e.g. push to a CRM, log to external service.
}, 10, 4 );
```

---

### `transition_post_status` (booking lifecycle)

WordPress's native status transition hook is how booking state changes are detected. The plugin's `NotificationService` uses it to dispatch emails. Use the same pattern to react to booking status changes:

```php
add_action( 'transition_post_status', function( string $new_status, string $old_status, \WP_Post $post ) {
    if ( 'dish_booking' !== $post->post_type ) return;

    if ( 'dish_completed' === $new_status && 'dish_completed' !== $old_status ) {
        // Booking just confirmed — do something.
    }

    if ( 'dish_cancelled' === $new_status ) {
        // Booking cancelled.
    }

    if ( 'dish_refunded' === $new_status ) {
        // Booking refunded.
    }
}, 10, 3 );
```

---

### `dish_cleanup_expired_bookings`

Cron action — fires every 15 minutes. Handled internally by `Booking\CheckoutTimer::cleanup_expired()`. Hook in here to react to timer-expired bookings before they are cancelled:

```php
add_action( 'dish_cleanup_expired_bookings', function() {
    // Fires just before expired pending bookings are cancelled.
} );
```

---

## Filters

### `dish_class_card_data`

*(Planned — not yet implemented.)* Will allow modification of the data array passed to `templates/classes/card.php` before render.

---

### `post_type_link` (class template URLs)

The plugin filters `post_type_link` to build the `/classes/{format-slug}/{template-slug}/` URL for `dish_class_template`. If you need to modify the URL structure, wrap the plugin's filter rather than replacing it:

```php
add_filter( 'post_type_link', function( string $url, \WP_Post $post ): string {
    if ( 'dish_class_template' !== $post->post_type ) return $url;
    // Modify $url as needed.
    return $url;
}, 20, 2 ); // Priority 20 — runs after the plugin's priority-10 filter.
```

---

## REST Endpoints

### `GET /wp-json/dish/v1/classes`

Returns FullCalendar-compatible event objects for all published class instances within a date range. Used by the `[dish_classes view="calendar"]` shortcode.

**Query parameters:**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `start` | ISO 8601 string | Yes | Inclusive lower bound on `dish_start_datetime` |
| `end` | ISO 8601 string | Yes | Exclusive upper bound on `dish_start_datetime` |
| `format_id` | integer | No | Filter to a specific `dish_format` post ID |

**Response shape (per event):**

```json
{
  "id": 123,
  "title": "German Beer Garden",
  "start": "2026-04-18T14:00:00Z",
  "end":   "2026-04-18T17:00:00Z",
  "url": "https://example.com/classes/hands-on/german-beer-garden/?class_id=123",
  "backgroundColor": "#c0392b",
  "borderColor": "#c0392b",
  "extendedProps": {
    "is_private": false,
    "format": { "id": 5, "title": "Hands On", "color": "#c0392b" },
    "spots_remaining": 4
  }
}
```

Private class instances return `"title": "Private Event"` and `"url": null`.

**Caching:** Responses are cached in the WP object cache for 5 minutes per unique `start/end/format_id` combination, in cache group `dish_events`.

---

### `GET /wp-json/dish/v1/ping`

Health check. Returns `{"status": "ok"}`. Used to verify the plugin's REST namespace is registered after deployment.

```
curl https://yoursite.com/wp-json/dish/v1/ping
```

---

## Public AJAX Endpoints

All public (nopriv) AJAX actions are handled by `Frontend\PublicAjax`. All are nonce-verified.

| `action` value | Purpose |
|---|---|
| `dish_initiate_booking` | Start a checkout session — validates availability, places a capacity hold, starts the timer |
| `dish_process_booking` | Complete checkout — saves customer details, creates the `dish_booking` post |
| `dish_release_hold` | Explicitly release a capacity hold (e.g. customer navigates away) |
| `dish_update_booking_status` | Admin: change booking status (Complete / Cancel / Refund) |
| `dish_add_booking_note` | Admin: add an internal note to a booking |

---

## Template Overrides

Any template in `templates/` can be overridden in a theme without modifying plugin files.

**Override path:** `{active-theme}/dish-events/{template-path}`

**Examples:**

| Plugin template | Override path in theme |
|---|---|
| `templates/classes/card.php` | `{theme}/dish-events/classes/card.php` |
| `templates/chefs/single.php` | `{theme}/dish-events/chefs/single.php` |
| `templates/menus/upcoming.php` | `{theme}/dish-events/menus/upcoming.php` |

The `Frontend::locate()` helper checks the theme path first. If found, the theme file is loaded. If not, the plugin default is used.

Variables available in each template are documented in the file's docblock at the top of each template file.

---

## Adding a New Shortcode

1. Create a view class in `includes/Frontend/` (e.g. `class-my-view.php`, namespace `Dish\Events\Frontend`)
2. Add a static `render_*()` method that returns an HTML string
3. Register the shortcode in `Shortcodes::register()`:

```php
add_shortcode( 'dish_my_shortcode', [ MyView::class, 'render_archive' ] );
```

4. The autoloader handles the class automatically — no `require_once` needed.

---

## Adding a New Admin Meta Box

1. Create the class in `includes/Admin/` (e.g. `class-my-meta-box.php`, namespace `Dish\Events\Admin`)
2. Implement `register()` and `save()` methods following the pattern in `ChefMetaBox` or `MenuMetaBox`
3. Wire it in `Admin::register_hooks()`:

```php
$my_meta_box = new MyMetaBox();
$this->loader->add_action( 'add_meta_boxes',          $my_meta_box, 'register' );
$this->loader->add_action( 'save_post_dish_class',    $my_meta_box, 'save', 10, 2 );
```

---

## Adding a New REST Endpoint

1. Create the class in `includes/REST/` (e.g. `class-my-endpoint.php`, namespace `Dish\Events\REST`)
2. Implement `register_hooks( Loader $loader )` and `register_routes()`
3. Register under the `dish/v1` namespace:

```php
register_rest_route( 'dish/v1', '/my-route', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [ $this, 'handle' ],
    'permission_callback' => '__return_true', // or a capability check for protected routes
] );
```

4. Wire in `Plugin::wire_hooks()`:

```php
$my_endpoint = new \Dish\Events\REST\MyEndpoint();
$my_endpoint->register_hooks( $this->loader );
```

---

## Reading Settings from Code

Use `Settings::get()` anywhere in the plugin or in a theme override:

```php
use Dish\Events\Admin\Settings;

$currency = Settings::get( 'currency', 'CAD' );
$timer    = Settings::get( 'checkout_timer_minutes', 10 );
```

The second argument is the fallback if the key hasn't been saved yet. Defaults from `Activator::default_settings()` are tried first before falling back to the provided value.

---

## Money and Date Helpers

**All monetary values are stored and passed as integer cents.** Use `MoneyHelper` to format for display:

```php
use Dish\Events\Helpers\MoneyHelper;

echo MoneyHelper::format( 9500 ); // "$95.00" (respects currency/symbol settings)
```

**All timestamps are UTC Unix integers.** Use `DateHelper` to format for display in the site's timezone:

```php
use Dish\Events\Helpers\DateHelper;

echo DateHelper::format_date( $timestamp );      // Uses date_format setting
echo DateHelper::format_time( $timestamp );      // Uses time_format setting
echo DateHelper::format_datetime( $timestamp );  // Combined
```

Never format timestamps directly in templates — always go through `DateHelper` so format settings and timezone are respected consistently.

---

## Database Version and Migrations

The current DB schema version is stored in `dish_db_version`. `Updater::run()` fires on every page load (synchronously, early in bootstrap) and compares this version against the known migration list. If the stored version is behind, migrations are run in sequence.

To add a migration:
1. Add a new version entry to `Updater::$migrations`
2. Each migration is a callable that uses `dbDelta()` for schema changes or raw `$wpdb` for data changes
3. The `dish_db_version` option is updated after each migration runs

Never modify `Activator::create_tables()` for a schema change on an existing install — write a migration instead.
