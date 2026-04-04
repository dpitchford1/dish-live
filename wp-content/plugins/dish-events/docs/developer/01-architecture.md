# Plugin Architecture

A reference for developers working on or extending the Dish Events plugin.

---

## At a Glance

| Property | Value |
|---|---|
| Plugin file | `dish-events/dish-events.php` |
| PHP namespace root | `Dish\Events\` |
| Text domain | `dish-events` |
| Min PHP | 8.0 |
| Min WordPress | 6.4 |
| Version | 1.0.2 |
| No Composer | Autoloaded via custom PSR-4 loader in the plugin entry file |
| No jQuery | All frontend JS is vanilla |
| No Gutenberg | No block registration anywhere |

---

## Directory Structure

```
dish-events/
├── dish-events.php          ← Entry file: constants, autoloader, activation hooks, bootstrap
├── uninstall.php            ← Runs on plugin deletion (not deactivation)
├── assets/
│   ├── css/
│   │   ├── dish-admin.css       ← Admin-only styles (meta boxes, settings, reports)
│   │   └── dish-events.css      ← Frontend styles (class cards, booking, calendar)
│   └── js/
│       └── dish-admin.js        ← Admin JS (meta box tabs, recurrence UI, date pickers)
├── docs/                    ← This documentation (Markdown files)
│   ├── content-team/
│   ├── usage/
│   └── developer/
├── includes/
│   ├── Admin/               ← All wp-admin UI (meta boxes, list tables, settings, reports)
│   │   └── Panels/          ← Tab panel renderers for the class meta box
│   ├── Booking/             ← Checkout orchestration, capacity management, timer
│   ├── CPT/                 ← Custom post type and taxonomy registrations
│   ├── Core/                ← Bootstrap, Loader, Activator, Deactivator, Updater
│   ├── Data/                ← Stateless repository classes (all DB queries)
│   ├── Frontend/            ← Shortcodes, template loader, assets, public AJAX
│   ├── Helpers/             ← DateHelper, MoneyHelper (stateless utility classes)
│   ├── Notifications/       ← Email dispatch (NotificationService, EmailTemplate)
│   ├── Recurrence/          ← RecurrenceManager (generates/deletes child instances)
│   ├── REST/                ← REST API endpoints
│   └── Taxonomy/            ← (Reserved for taxonomy extras beyond CPT registration)
└── templates/               ← PHP template files (theme-overridable)
    ├── account/             ← login.php, register.php, profile.php
    ├── booking/             ← checkout.php, confirmation.php
    ├── chefs/               ← archive.php, single.php, card.php
    ├── class-templates/     ← single.php (the class detail page)
    ├── classes/             ← archive.php, card.php (grid listing)
    ├── formats/             ← archive.php, card.php
    └── menus/               ← upcoming.php
```

---

## Autoloader

The plugin uses a custom PSR-4 autoloader — no Composer dependency. It lives in `dish-events.php` and runs via `spl_autoload_register()`.

**Mapping rules:**

```
Namespace               →  File path
──────────────────────────────────────────────────────────────────
Dish\Events\Core\Plugin          →  includes/Core/class-plugin.php
Dish\Events\CPT\ClassPost        →  includes/CPT/class-class-post.php
Dish\Events\Admin\Settings       →  includes/Admin/class-settings.php
Dish\Events\Payments\GatewayInterface  →  includes/Payments/interface-gateway.php
```

**Rules in plain terms:**
1. Strip the `Dish\Events\` prefix
2. The remaining namespace segments become directory names (`Core/`, `Admin/`, etc.)
3. The class name is converted from CamelCase to kebab-case
4. Prefix is `class-` unless the class name ends in `Interface`, in which case it's `interface-`

**Adding a new class:** Create the file at the correct path and it's autoloaded immediately — no registration step needed.

---

## Bootstrap Flow

```
disk-events.php
  ├── Constants defined (DISH_EVENTS_VERSION, DISH_EVENTS_PATH, DISH_EVENTS_URL, DISH_EVENTS_FILE)
  ├── Autoloader registered
  ├── register_activation_hook   → Activator::activate()
  ├── register_deactivation_hook → Deactivator::deactivate()
  └── add_action('plugins_loaded') → Plugin::run()

Plugin::run()   (singleton, subsequent calls are no-ops)
  ├── new Loader()
  ├── wire_hooks()
  │   ├── Updater::run()               (DB migrations — synchronous)
  │   ├── CPT registrations            (Phase 2)
  │   ├── Admin::register_hooks()      (Phase 3 — only if is_admin())
  │   ├── Frontend::register_hooks()   (Phase 2.5)
  │   ├── Assets::register_hooks()     (Phase 7)
  │   ├── Shortcodes::register_hooks() (Phase 7)
  │   ├── ClassesEndpoint              (Phase 8 — REST)
  │   ├── PublicAjax::register_hooks() (Phase 9)
  │   └── NotificationService          (Phase 11)
  ├── Loader::run()   (registers all collected hooks with WordPress)
  └── do_action('dish_events_loaded')
```

---

## The Loader Pattern

All hook registrations in the plugin go through the `Loader` class rather than calling `add_action()` / `add_filter()` directly. This keeps every hook in one auditable collection and prevents accidental double-registration.

```php
// Module registers hooks via the loader passed to it:
$loader->add_action( 'init', $my_object, 'my_method' );
$loader->add_filter( 'post_type_link', $my_object, 'filter_link', 10, 2 );

// Loader::run() fires once at the end of Plugin::run(), registering everything.
```

Static classes that use their own `init()` pattern (e.g. `DishDocs`) call `add_action()` directly — this is acceptable only for simple, self-contained modules with no state.

---

## Namespace Map

| Namespace | Purpose |
|---|---|
| `Dish\Events\Core` | Plugin, Loader, Activator, Deactivator, Updater |
| `Dish\Events\CPT` | ClassPost, ClassTemplatePost, ChefPost, BookingPost, FormatPost |
| `Dish\Events\Admin` | All wp-admin UI — meta boxes, list tables, settings, reports, docs |
| `Dish\Events\Data` | Repository classes — all DB reads/writes |
| `Dish\Events\Frontend` | Shortcodes, template loader, public assets, public AJAX |
| `Dish\Events\Booking` | BookingManager, CapacityManager, CheckoutTimer |
| `Dish\Events\Recurrence` | RecurrenceManager |
| `Dish\Events\Notifications` | NotificationService, EmailTemplate |
| `Dish\Events\REST` | ClassesEndpoint |
| `Dish\Events\Helpers` | DateHelper, MoneyHelper |

---

## Activation / Deactivation

**`Activator::activate()`** runs once on activation:
- Creates custom DB tables via `dbDelta()` (safe to run multiple times)
- Seeds default plugin options (`dish_settings`)
- Schedules the `dish_cleanup_expired_bookings` cron job
- Sets `dish_flush_rewrite_rules` flag (consumed on next `admin_init` once CPTs are registered)
- Sets `dish_activation_redirect` flag (redirects to Settings page once)

**`Deactivator::deactivate()`** runs on deactivation:
- Clears the scheduled cron jobs

**`uninstall.php`** runs on plugin deletion:
- Drops custom DB tables
- Deletes all `dish_*` `wp_options` entries
- Removes all plugin CPT posts

---

## Template Loading and Overrides

Frontend templates live in `templates/`. The `Frontend` class exposes a `locate()` helper that checks for a theme override before loading the plugin default:

```php
// Looks for: {theme}/dish-events/classes/card.php first,
//            then falls back to: dish-events/templates/classes/card.php
Frontend::locate( 'classes/card.php' );
```

To override a template in a child or custom theme, create the matching path under `{theme}/dish-events/`.

---

## DB Tables

Three custom tables are created on activation:

| Table | Purpose |
|---|---|
| `{prefix}dish_ticket_types` | Global ticket templates — price, capacity, booking window |
| `{prefix}dish_ticket_categories` | Organisational groupings for ticket types |
| `{prefix}dish_checkout_fields` | Admin-configured checkout form fields |

Schema is managed via `Activator::create_tables()` (called by both `activate()` and `Updater` migrations). Always use `dbDelta()` for schema changes — never raw `CREATE TABLE` without it.

---

## Coding Conventions

- `declare(strict_types=1)` at the top of every file
- All classes are `final` unless inheritance is explicitly required
- All monetary values are **integer cents** — never floats
- All timestamps are **UTC Unix timestamps** — formatting happens at render time via `DateHelper`
- Repository classes are stateless — only `static` methods, no constructor, no instance state
- No `error_log()` calls in committed code
- All output escaped (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`)
- All input sanitised before use (`sanitize_text_field`, `absint`, `wp_unslash`, etc.)
- All AJAX and REST endpoints nonce-verified or capability-checked
