# Code Review Prompt — dish-live

A comprehensive, reusable prompt for periodic code reviews of the dish-live project. Built from the project's `copilot-instructions.md` and all documentation in `/wp-content/plugins/dish-events/docs/`.

---

## How to Use This

| Approach | Instructions |
|---|---|
| **Full review in one pass** | Copy everything between the **Start of Prompt** and **End of Prompt** anchors and paste into Copilot Chat scoped to the repo. |
| **Iterative by part** | Run Part A (plugin) first, then Part B (theme), then Part C (assets), then Part D (hygiene). Keeps responses focused and avoids truncation. |
| **Iterative by section** | Run individual sections (e.g. "Review A4 — Security across the entire plugin") for targeted deep dives. |
| **PR review checklist** | Use the section headers as a manual checklist when reviewing any pull request. |
| **Per-file review** | Prefix with "Review `{file path}` against the following standards:" and paste the relevant sections only. |

---

## Severity Legend

| Icon | Meaning |
|---|---|
| 🔴 | **Must fix** — violates a documented standard, introduces a bug, or is a security risk |
| 🟡 | **Should fix** — deviates from convention, may cause confusion or maintenance burden |
| 🟢 | **Suggestion** — improvement opportunity, not a violation |

---

<!-- ============================================================ -->
<!-- =================== START OF PROMPT ======================== -->
<!-- ============================================================ -->

**Perform a thorough code review of the `dpitchford1/dish-live` repository. Only the following three directories are in scope — ignore everything else (especially `wp-content/plugins/_dish-events/` which is a backup folder):**

```
/assets/                          ← CSS/JS/fonts/images (repo root)
/wp-content/plugins/dish-events/  ← Dish Events plugin
/wp-content/themes/dish/          ← Dish theme (Basecamp starter)
```

**Evaluate every file against the project standards documented below. Report findings grouped by category. For each finding include:**

- **Severity:** 🔴 Must fix · 🟡 Should fix · 🟢 Suggestion
- **File path and line number(s)**
- **What the issue is**
- **What the standard requires**
- **Suggested fix (with code snippet where applicable)**

---

## PART A — DISH EVENTS PLUGIN (`wp-content/plugins/dish-events/`)

### A1. PHP Strict Typing & Class Structure

- Every `.php` file must have `declare(strict_types=1);` at the top.
- All classes must be `final` unless inheritance is explicitly required.
- All classes must be namespaced under `Dish\Events\*` following the namespace map:
  - `Core` → Plugin, Loader, Activator, Deactivator, Updater
  - `CPT` → ClassPost, ClassTemplatePost, ChefPost, BookingPost, FormatPost
  - `Admin` → Meta boxes, list tables, settings, reports, docs (+ `Admin\Panels` for tab panel renderers)
  - `Data` → Stateless repository classes (all DB queries)
  - `Frontend` → Shortcodes, template loader, public assets, public AJAX
  - `Booking` → BookingManager, CapacityManager, CheckoutTimer
  - `Recurrence` → RecurrenceManager
  - `Notifications` → NotificationService, EmailTemplate
  - `REST` → ClassesEndpoint
  - `Helpers` → DateHelper, MoneyHelper
- Verify the custom PSR-4 autoloader mapping: class names are CamelCase → `class-kebab-case.php`; interfaces use `interface-` prefix. Adding a new class at the correct path should autoload with no registration step.

---

### A2. Loader Pattern & Bootstrap Order

- All hook registrations in stateful classes **must** go through the `Loader` class — never call `add_action()`/`add_filter()` directly. Direct calls are acceptable **only** in static, self-contained modules with no state.
- `Loader::run()` must fire once at the end of `Plugin::run()`.
- New hooks must be added inside `Plugin::wire_hooks()`.
- Verify the bootstrap flow matches the documented phase order:
  1. Constants → Autoloader → activation/deactivation hooks
  2. `plugins_loaded` → `Plugin::run()` → `Updater::run()` (synchronous DB migrations)
  3. CPT registrations (Phase 2) → Frontend (Phase 2.5) → Admin (Phase 3, `is_admin()` gated) → Assets (Phase 7) → Shortcodes (Phase 7) → REST (Phase 8) → PublicAjax (Phase 9) → NotificationService (Phase 11)
  4. `Loader::run()` → `do_action('dish_events_loaded')`

---

### A3. Data Integrity — Money & Timestamps

- **All monetary values must be integer cents** — never floats. Flag any `floatval()`, `(float)`, or arithmetic that produces float intermediaries on money. `MoneyHelper::format()` must be used for all display formatting.
- **All timestamps must be UTC Unix integers.** `DateHelper::format_date()`, `format_time()`, `format_datetime()` must be used in all templates and admin views — never raw `date()`, `gmdate()`, or direct formatting.
- No hardcoded currency symbols, date formats, or timezone offsets — all must come from Settings or Helpers.

---

### A4. Security — Input, Output, Nonces, Capabilities

- **Output escaping:** Every `echo`, `printf`, and template variable must use `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`. Check all HTML attributes and dynamic output.
- **Input sanitisation:** Every `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE` access must be sanitised before use with `sanitize_text_field()`, `absint()`, `wp_unslash()`, etc.
- **AJAX endpoints:** All `wp_ajax_` and `wp_ajax_nopriv_` handlers must be nonce-verified (`wp_verify_nonce()` or `check_ajax_referer()`).
- **REST endpoints:** Every endpoint must have a proper `permission_callback`. Public endpoints use `__return_true` explicitly; admin endpoints must check capabilities.
- **Account deletion:** Must perform timing-safe `hash_equals()` email comparison and reject requests from users with `edit_posts` capability (both client-side and server-side guard).

---

### A5. Repository Pattern & Database Access

- **All DB queries must go through stateless repository classes** in `Dish\Events\Data\`. Flag any direct `$wpdb`, `WP_Query`, `get_posts()`, or `get_post_meta()` calls found outside repository classes.
- Repository classes must be stateless — only `static` methods, no constructor, no instance state.
- Raw joins must use `$wpdb->prepare()`.
- Verify all 7 repositories exist and cover their documented scope:
  - `ClassRepository` → `dish_class` posts and meta
  - `ClassTemplateRepository` → `dish_class_template` posts and meta
  - `ChefRepository` → `dish_chef` posts and meta (with `exclude_team` / `team_only` support)
  - `BookingRepository` → `dish_booking` posts and meta
  - `TicketTypeRepository` → `dish_ticket_types` custom table
  - `CheckoutFieldRepository` → `dish_checkout_fields` custom table
  - `ReportsRepository` → aggregate queries for the Reports admin page

---

### A6. Custom Post Types, Taxonomy & Statuses

- Verify all 5 CPTs are registered with correct arguments:
  - `dish_class` — not public, no archive, show in UI
  - `dish_class_template` — public, no archive, supports title/editor/excerpt/thumbnail, rewrite slug `classes/%dish_class_format%`
  - `dish_chef` — public, no archive, supports title/editor/thumbnail, configurable URL slug
  - `dish_booking` — not public, show in admin, capability type `dish_booking`
  - `dish_format` — public, no WP archive (handled via rewrite rules), supports title/editor/thumbnail
- `dish_class` instances are never directly browsable; `dish_class_template` has the public URL.
- Verify `dish_class_format` taxonomy: registered on `dish_class_template`, hierarchical=false, public=true, rewrite slug configurable.
- Verify the auto-derive rule: saving a template reads `dish_ticket_type_id → format_id` → resolves matching `dish_format` → assigns taxonomy term.
- Verify all 6 custom post statuses: `dish_expired`, `dish_cancelled`, `dish_pending`, `dish_completed`, `dish_failed`, `dish_refunded`.

---

### A7. Template System

- `Frontend::locate()` must check `{theme}/dish-events/{path}` before falling back to `dish-events/templates/{path}`.
- Every template file must have a docblock documenting available variables.
- Templates must not contain direct DB queries — all data must be prepared before the template is loaded.
- Verify all expected template directories and files exist:
  - `account/` → login.php, register.php, profile.php
  - `booking/` → checkout.php, confirmation.php
  - `chefs/` → archive.php, single.php, card.php
  - `class-templates/` → single.php
  - `classes/` → archive.php, card.php
  - `formats/` → archive.php, card.php
  - `menus/` → upcoming.php

---

### A8. Booking & Checkout Flow

- Verify lifecycle: `dish_initiate_booking` → capacity hold + timer start → `dish_process_booking` → `dish_booking` post created with `dish_pending` → payment callback → `dish_completed`.
- `CheckoutTimer::cleanup_expired()` must fire via `dish_cleanup_expired_bookings` cron (every 15 min).
- `dish_release_hold` AJAX handler must properly release capacity holds.
- `dish_booking_created` action must fire with 4 params: `$booking_id`, `$class_id`, `$qty`, `$total_cents`.
- `NotificationService` must hook `transition_post_status` for email dispatch on booking state changes.
- **Payments are paused** — the booking flow reaches confirmation but no gateway is active. Flag any active payment gateway code that shouldn't be running yet, but do **not** suggest adding payment functionality.
- Verify all 17 documented booking meta keys match the schema.

---

### A9. Recurrence System

- Verify `RecurrenceManager` generates child instances from parent's `dish_recurrence` JSON.
- `dish_recurrence_parent_id` must be set on all children.
- `RecurrenceManager::delete_series()` must cascade to all `child_ids` on parent trash/delete.
- Verify JSON schema: `type`, `interval`, `days`, `ends`, `end_date`, `end_after`, `child_ids`.

---

### A10. REST API & Caching

- `GET /wp-json/dish/v1/classes` — must accept `start`, `end` (ISO 8601, required), `format_id` (optional). Response must be FullCalendar-compatible with `id`, `title`, `start`, `end`, `url`, `backgroundColor`, `borderColor`, `extendedProps`.
- Private classes must return `"title": "Private Event"` and `"url": null`.
- Responses must be cached in WP object cache for 5 min per unique `start/end/format_id` key, cache group `dish_events`.
- `GET /wp-json/dish/v1/ping` must return `{"status": "ok"}`.

---

### A11. Settings System

- `Settings::get()` must check saved options → `Activator::default_settings()` → caller's fallback.
- All 10 settings tabs must render and save independently: General, Venue, Studio, Pages, Calendar, Checkout, Payments, Emails, URLs, Features.
- Email templates must support all documented tokens (`{{booking_id}}`, `{{customer_name}}`, `{{customer_email}}`, `{{customer_phone}}`, `{{class_title}}`, `{{class_date}}`, `{{class_time}}`, `{{class_location}}`, `{{ticket_type}}`, `{{quantity}}`, `{{amount}}`, `{{booking_details_url}}`, `{{studio_name}}`, `{{studio_email}}`, `{{studio_phone}}`).

---

### A12. Activation, Deactivation & Uninstall

- **Activator::activate()** → `dbDelta()` for custom tables, seed default options, schedule `dish_cleanup_expired_bookings` cron, set flush-rewrite and redirect flags.
- **Deactivator::deactivate()** → clear cron jobs only.
- **uninstall.php** → drop custom tables, delete all `dish_*` wp_options, remove all plugin CPT posts.
- `Updater::run()` must fire on every load, compare `dish_db_version`, run migrations sequentially. Schema changes go in migrations — **never** modify `Activator::create_tables()` for existing installs.

---

### A13. Registration & Account Management

- `[dish_login]` → login form for guests, "you are logged in" notice for authenticated users.
- `[dish_register]` → must check `get_option('users_can_register')`; show notice when disabled.
- `[dish_profile]` → must query bookings by **email first, then user ID** so guest bookings surface after account creation.
- Account deletion must: clear `dish_customer_name` and `dish_customer_phone`, reset `dish_customer_user_id` to 0, **retain** `dish_customer_email`, retain booking posts/statuses/revenue. Must enforce `edit_posts` capability guard both client-side and server-side.
- Checkout account creation: booking created first (non-fatal), `wp_create_user()`, link user ID to booking, set auth cookie.

---

## PART B — DISH THEME (`wp-content/themes/dish/`)

### B1. Module System & Bootstrap

- `functions.php` is the sole bootstrap. Every feature is a `require_once` in dependency order: Core → Settings → Frontend → Admin → SEO → Theme Functions → WebP → REST → Cron → Dev → WooCommerce.
- Toggling a feature = commenting out its `require_once` line; nothing else should need to change.
- WooCommerce is disabled by default — verify its `require_once` is commented out.
- Development tools (`class-basecamp-development.php`) must only load for `127.0.0.1` / `::1`.

---

### B2. Namespaces & Aliases

- All theme classes must use `Basecamp\<Area>` namespaces with PascalCase.
- Back-compat aliases must be declared in `functions.php` for templates using static method calls (e.g. `class_alias('Basecamp\Admin\Settings', 'Basecamp_Settings')`).
- WP core classes inside a namespace must be prefixed with `\` (e.g. `\WP_Query`, `\WP_Post`).

---

### B3. Theme Settings

- `Basecamp_Settings::get('key')` must be the single read path. Stored as serialized option `basecamp_theme_settings`.
- Verify documented keys: `ga_id`, `cookie_compliance`, `gsc_verification`, `schema_output`, `webp_optimization`.
- GA ID override via `BASECAMP_GA_MEASUREMENT_ID` constant must work.
- Venue/contact data must come from `dish_settings` option, piped into Schema.org filters.

---

### B4. SEO & Cross-Component Integration

- Schema graphs must be added via `add_filter('basecamp_schema_graphs', ...)`.
- `DishSchema::init()` must be called from `Dish\Events\Core\ThemeIntegration` only when the class exists.
- SEO titles for dish CPTs must be handled by `Basecamp\SEO\TitleDishEvents`.
- Venue data flow: `dish_settings` → `ThemeIntegration::register_schema_filters()` → `basecamp_schema_*` filters → `Basecamp\SEO\Schema`.
- **All theme/plugin bridge code must live in the plugin** (`includes/Core/class-theme-integration.php`), not in the theme's `functions.php`.

---

### B5. Template HTML Quality

- HTML must be semantic, accessible, and bloat-free. Verify proper use of landmarks, headings hierarchy, alt attributes, ARIA attributes where needed.
- BEM naming for CSS classes (e.g. `card__picture`, `hero__img`).
- Verify nearly all default WP frontend output is disabled via `inc/frontend/remove-bloat.php`.

---

## PART C — ASSETS (`/assets/`)

### C1. CSS / SCSS

- SCSS uses Dart Sass `@use` / `@forward` — **not** `@import`. Flag any `@import` usage.
- Barrel files must namespace partials: `@forward "header/global-header" as basecamp-header-*;`.
- **All breakpoint mixin calls must be centralized** in `assets/css/scss/basecamp-base-layout/_responsive.scss`. Flag any `@include bp-*()` found inside component files.
- Breakpoints are max-width: `bp-480`, `bp-600`, `bp-768`, `bp-920`, `bp-1024`, `bp-1280`, `bp-1440`.
- No CSS frameworks — all styles are hand-coded.
- Both `.scss` source and compiled `.min.css` output must be committed. No build pipeline — Live Sass Compiler handles compilation.
- Never commit manually minified code.
- CSS is loaded via raw `<link>` tags in `header.php` — **not** `wp_enqueue_style`. This is intentional for per-template performance control.
- Critical CSS (`critical-css.min.css`) must be inlined via `file_get_contents()`.
- Verify the three documented bundles exist: `critical-css.min.css`, `basecamp-base-layout.min.css`, `basecamp-global-layout.min.css`.

---

### C2. JavaScript

- **No JS is active on the frontend yet.** `base.js` exists as a starter template but must not be wired to any page. Flag any frontend JS enqueue outside of the plugin's FullCalendar.
- FullCalendar.js lives in the **plugin** (`dish-events/assets/`) and is enqueued via `wp_enqueue_script` — verify it's not duplicated or moved to the theme.
- jQuery is acceptable in **WP Admin only** — flag any jQuery usage on the frontend.
- When JS is eventually written, it must follow the documented conventions in `base.js`:
  - IIFE module pattern: `var dish = dish || {}; window.dish = (function(window, document){ ... })(window, document);`
  - `"use strict"` at the top of every IIFE
  - DOM caching with `const` at module top — no re-querying in loops or handlers
  - `elementExists(el)` guard before every DOM interaction
  - `addEventListenerWithOptions` wrapper for event listeners
  - `document.readyState` check before `init()`, fallback to `DOMContentLoaded`
  - Progressive enhancement with feature detection and fallbacks
  - Accessibility: `aria-expanded`, `aria-hidden`, focus trapping, keyboard navigation
  - `throttle()` for scroll/resize handlers, `debounce()` for input/search handlers
  - `document.createDocumentFragment()` for batched DOM creation
  - JSDoc blocks on every function
  - Source `.js` alongside `.min.js` — Auto-Minify generates the minified file on save

---

## PART D — CODE HYGIENE (applies to all in-scope directories)

### D1. Forbidden Patterns

- No `error_log()` calls in committed code
- No `var_dump()`, `print_r()`, or `dd()` debug output
- No manually minified files (minification is handled by VS Code extensions)
- No ACF — field data is stored as standard post meta via custom admin UI
- No block editor / Gutenberg registration (`register_block_type`, `@wordpress/blocks`)
- No CSS frameworks
- No ES modules, bundlers, or transpilation
- No touches to `wp-content/plugins/_dish-events/` (backup folder)

---

### D2. Naming Conventions

| Thing | Convention | Example |
|---|---|---|
| Theme PHP classes | `Basecamp\<Area>` namespace, PascalCase | `Basecamp\SEO\Schema` |
| Plugin PHP classes | `Dish\Events\<Area>` namespace, PascalCase | `Dish\Events\Data\ClassRepository` |
| Global functions | `basecamp_*` | `basecamp_get_link_list()` |
| Hooks | `basecamp_*` (theme), `dish_*` (plugin) | `basecamp_schema_graphs`, `dish_events_loaded` |
| Text domains | `basecamp` (theme), `dish-events` (plugin) | |
| CSS classes | BEM | `card__picture`, `hero__img` |
| WP core classes inside a namespace | Prefix with `\` | `\WP_Query`, `\WP_Post` |

---

### D3. Build Status Awareness

- This is an **early-stage build** — single admin user, no custom roles beyond `subscriber` / `administrator`.
- Payments are **stubbed/paused** — the booking flow reaches confirmation but no gateway is active. Do not suggest adding payment gateway functionality.
- Frontend JS is **not active** — do not suggest wiring JS unless explicitly requested.
- The goal is **simple, clean, efficient, understandable code** — flag any over-engineering.

<!-- ============================================================ -->
<!-- ==================== END OF PROMPT ========================= -->
<!-- ============================================================ -->