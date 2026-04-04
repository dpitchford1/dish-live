# Copilot Instructions — dish-live

## Agent Guidelines

- You are operating as a **senior-level developer** with specialties in PHP, WordPress, and plugin development
- This is an **opinionated, intentionally simple codebase** — simple, clean, efficient, and understandable is the goal; do not over-engineer
- Only **clean, modular code** is acceptable — every change must consider performance and security
- No CSS frameworks — all styles are hand-coded custom CSS
- **No JavaScript active on the frontend yet** — `base.js` exists as a starter template but is not wired to any page. Do not add frontend JS unless explicitly asked
- No jQuery on the **frontend**; jQuery is acceptable in the WP Admin only
- **FullCalendar.js** is used inside the plugin (`dish-events`) and is enqueued via `wp_enqueue_script` — it is the only active frontend JS at this stage
- HTML must be **semantic, accessible, and bloat-free** — follow the existing pattern in templates
- **No manual minification** — CSS and JS minification is handled automatically by VS Code extensions (Live Sass Compiler, Auto-Minify). Never write or commit manually minified code
- No ACF — field data is stored as standard post meta via custom admin UI
- **Do not touch** `wp-content/plugins/_dish-events/` — it is a backup folder, not active code

## Important Directories

Only these three directories are in scope for this project:
```
/assets/                          CSS/JS/fonts/images (repo root, not inside theme)
/wp-content/plugins/dish-events/  Dish Events plugin
/wp-content/themes/dish/          Dish theme (built on Basecamp starter)
```

## Developer Docs

Extremely detailed reference docs exist — **read them before making architectural decisions**:
- **Theme**: `wp-content/themes/dish/Docs/developer/` — covers architecture, code style, SCSS, images, SEO, settings
- **Plugin**: `wp-content/plugins/dish-events/docs/`

---

## Project Overview

WordPress site for **Dish Cooking Studio** — a cooking events company. The site is old-style WordPress (no block editor usage). Nearly all default WP frontend output is disabled via `inc/frontend/remove-bloat.php`.

- **Theme `dish`** — built on top of **Basecamp**, a custom-built, performance-focused WP starter theme. The theme slug is `dish` but the codebase is Basecamp under the hood
- **Plugin `dish-events`** — provides all cooking class, chef, and booking functionality; the theme's frontend is largely a presentation layer over this plugin's data

---

## Theme Architecture (`wp-content/themes/dish/`)

### Module System
`functions.php` is the sole bootstrap. Every feature is a `require_once` in dependency order:
> Core → Settings → Frontend → Admin → SEO → Theme Functions → WebP → REST → Cron → Dev → WooCommerce

Toggle a feature by commenting out its line — nothing else needs to change.

### Namespaces & Aliases
All theme classes use `Basecamp\<Area>` namespaces. Back-compat aliases are declared in `functions.php` for templates that call static methods directly:
```php
class_alias( 'Basecamp\Admin\Settings', 'Basecamp_Settings' );  // templates use Basecamp_Settings::get()
class_alias( 'Basecamp\Frontend\Frontend', 'Basecamp_Frontend' );
```

### Theme Settings
Read anywhere with `Basecamp_Settings::get( 'key' )`. Stored as a single serialized option `basecamp_theme_settings`. Keys: `ga_id`, `cookie_compliance`, `gsc_verification`, `schema_output`, `webp_optimization`. The GA ID can be overridden without touching the DB: `define( 'BASECAMP_GA_MEASUREMENT_ID', 'G-XXXXXXXXXX' )` in `wp-config.php`.

### Dish-specific Settings
Venue contact/address data lives in the `dish_settings` option. Read and piped into Schema.org filters in `functions.php` (the `init` closure at line ~67).

### Asset Pipeline — CSS
Assets are at `/assets/` (repo root), **not** inside the theme. CSS is loaded via root-relative `<link>` tags in `header.php` — **not** `wp_enqueue_style`. This is intentional: raw `<link>` tags give per-template control over what loads and when, maximizing frontend performance. Critical CSS is inlined via `file_get_contents()`.

| Bundle | Purpose |
|---|---|
| `assets/css/build/critical-css.min.css` | Above-fold, PHP-inlined |
| `assets/css/build/basecamp-base-layout.min.css` | Layout, header, nav |
| `assets/css/build/basecamp-global-layout.min.css` | Page components |

**No build pipeline** — SCSS is compiled automatically by the Live Sass Compiler VS Code extension on save. Commit both `.scss` source and `.min.css` output. Never run a manual build step.

### SCSS Conventions
- Uses Dart Sass `@use` / `@forward` (not `@import`)
- Barrel files namespace partials: `@forward "header/global-header" as basecamp-header-*;`
- **All breakpoint mixin calls are centralized** in `assets/css/scss/basecamp-base-layout/_responsive.scss` — never add `@include bp-*()` inside component files
- Breakpoints are max-width: `bp-480`, `bp-600`, `bp-768`, `bp-920`, `bp-1024`, `bp-1280`, `bp-1440`

---

## Dish Events Plugin (`wp-content/plugins/dish-events/`)

### PSR-4 Autoloader (no Composer)
`Dish\Events\` → `includes/{SubNamespace}/class-{kebab-name}.php`
CamelCase → kebab-case: `ClassRepository` → `class-class-repository.php`
Interfaces: `GatewayInterface` → `interface-gateway.php`

### CPT Data Model
| CPT slug | Role |
|---|---|
| `dish_class_template` | Public-facing class page; URL `/classes/{format-slug}/{template-slug}/` |
| `dish_class` | Dated instance of a template (non-public, used for bookings) |
| `dish_chef` | Chef profile |
| `dish_format` | Class format (e.g. "Hands On") |
| `dish_booking` | Booking record |

`dish_class_template` has the public URL; `dish_class` instances are never directly browsable.

### Data Layer
Stateless static repository classes in `includes/Data/`. No business logic — data retrieval only. Use `$wpdb->prepare()` for raw joins. Key classes: `ClassRepository`, `ClassTemplateRepository`, `BookingRepository`, `ChefRepository`.

### Bootstrap Pattern
`Plugin::run()` singleton fires on `plugins_loaded`. Hooks are collected by `Loader` and registered in bulk via `Loader::run()`. New hooks go in `Plugin::wire_hooks()`.

### REST & Shortcodes
- Calendar API: `GET /wp-json/dish/v1/classes` (FullCalendar-compatible)
- Shortcodes: `[dish_classes]`, `[dish_chefs]`, `[dish_login]`, `[dish_register]`, `[dish_profile]`

---

## Cross-Component Integration

The theme's SEO module (`DishSchema`, `TitleDishEvents`) extends Basecamp classes to inject dish CPT data:
- Schema graphs added via `add_filter( 'basecamp_schema_graphs', ... )` — `DishSchema::init()` is called from `Dish\Events\Core\ThemeIntegration` only when the class exists
- SEO titles for dish CPTs handled by `Basecamp\SEO\TitleDishEvents` (registered as a `TitleManager` extension)
- Venue data flows: `dish_settings` option → `Dish\Events\Core\ThemeIntegration::register_schema_filters()` → `basecamp_schema_*` filters → `Basecamp\SEO\Schema`
- All theme/plugin bridge code lives in the **plugin** (`includes/Core/class-theme-integration.php`), not in the theme's `functions.php`

---

## Naming Conventions

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

## Developer Workflow

### Environment
- **Editor**: VS Code
- **Stack**: MAMP (local)
- **Local URL**: `http://dishes.local`
- **Version control**: Git — commit both `.scss` source and compiled `.min.css`; no external server at this stage
- **Environment**: Set `BASECAMP_ENV=local` in `wp-config.php` to enable debug constants, disable SSL enforcement, and adjust cron

### Common Tasks
- **SCSS**: Save any `.scss` file → Live Sass Compiler auto-compiles to `assets/css/build/*.min.css`
- **New image size**: Add `add_image_size()` in `inc/class-basecamp.php`, then run `wp media regenerate --yes`
- **New theme module**: Create file in `inc/<area>/`, declare `namespace Basecamp\<Area>;`, add `require_once` in `functions.php` in the correct section
- **New plugin class**: Create `includes/<SubNamespace>/class-<kebab-name>.php`, declare `namespace Dish\Events\<SubNamespace>;` — the autoloader picks it up automatically
- **WooCommerce**: Disabled by default; uncomment the `require_once` line near the bottom of `functions.php`
- **Development tools**: `inc/development/class-basecamp-development.php` only loads for `127.0.0.1` / `::1`

### JavaScript Conventions

The starter template is `assets/js/core/base.js`. Follow every pattern in that file when writing new JS:

- **Module pattern**: IIFE-based namespace — `var dish = dish || {}; window.dish = (function(window, document){ ... })(window, document);` — no ES modules, no bundler, no transpilation
- **`"use strict"`** at the top of every IIFE
- **DOM caching**: cache all queried elements once with `const` at the top of the module; never re-query inside loops or event handlers
- **Guard clauses**: call `elementExists(el)` before every DOM interaction; use `console.warn` when skipping non-critical setup
- **Event listeners**: use the `addEventListenerWithOptions` wrapper — it handles touch events, passive flags, and feature detection automatically
- **Init pattern**: check `document.readyState` before calling `init()`, fall back to `DOMContentLoaded` — do not use bare `DOMContentLoaded` wrappers
- **Progressive enhancement**: feature-detect before using modern APIs (e.g. `IntersectionObserver`, `scrollBehavior`) and always provide a fallback
- **Accessibility**: manage `aria-expanded`, `aria-hidden`, focus trapping, and keyboard navigation (`Escape`, `ArrowUp`/`ArrowDown`) on any interactive widget
- **Performance utilities**: use the provided `throttle()` for scroll/resize handlers and `debounce()` for input/search handlers
- **Batched DOM creation**: use `document.createDocumentFragment()` when building multiple elements
- **JSDoc**: every function gets a `/** ... */` JSDoc block with `@param` and `@returns`
- **File output**: source `.js` alongside `.min.js` — Auto-Minify (VS Code) generates the minified file on save; never write or commit a manually minified file
- **FullCalendar.js** lives in the plugin (`wp-content/plugins/dish-events/assets/`) and is enqueued via `wp_enqueue_script`; do not duplicate or move it

### Current Build Status
- **Early-stage build** — single admin user only; no custom roles beyond `subscriber` / `administrator`
- **Payments**: stubbed inside `dish-events` — the booking flow reaches confirmation but no gateway is active; payment work is paused and should not be touched unless explicitly requested
- **JavaScript**: not yet in use on the frontend; do not introduce JS without an explicit task
- **Environment**: Set `BASECAMP_ENV=local` (or `staging`/`production`) to control debug constants, SSL, and cron behavior
