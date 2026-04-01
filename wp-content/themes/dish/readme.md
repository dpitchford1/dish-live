# Basecamp

A performance-focused, modular WordPress starter theme for developers.

Basecamp ships with a comprehensive PHP module system, no-plugin SEO, automatic WebP conversion, responsive image helpers, and a Dart Sass CSS pipeline — everything you need to start a production project without ripping out framework opinions.

---

## Requirements

- WordPress 6.4+
- PHP 7.4+ (8.x recommended)
- [Live Sass Compiler](https://marketplace.visualstudio.com/items?itemName=glenn2223.live-sass) (VS Code) — or any Dart Sass compiler

---

## Install

```bash
# In your WordPress themes directory
git clone https://github.com/your-org/basecamp.git
wp theme activate basecamp
```

Or download as a ZIP and upload via **Appearance → Themes → Add New → Upload Theme**.

---

## Environment Setup

Copy `wp-config-sample.php` to `wp-config.php` and set the environment variable before starting:

```bash
export BASECAMP_ENV=local   # local | staging | production (default)
```

Debug constants, `DISALLOW_FILE_MODS`, `FORCE_SSL_ADMIN`, and cron settings are all driven by this single variable.

---

## Module System

Every feature is a self-contained file loaded via `require_once` in `functions.php`. Enable or disable any module by commenting its load line in and out — nothing else needs to change.

```php
// functions.php — example toggles
require_once __DIR__ . '/inc/frontend/class-basecamp-frontend.php'; // always on
// require_once __DIR__ . '/inc/woocommerce/woocommerce-functions.php'; // uncomment when WooCommerce is active
// require_once __DIR__ . '/inc/theme-functions/basecamp-cpt-scaffold.php'; // uncomment when using custom post types
```

Modules are grouped in `functions.php` by area:

| Group | Path |
|---|---|
| Frontend helpers | `inc/frontend/` |
| Admin customisations | `inc/admin/` |
| SEO (titles, meta, social) | `inc/seo/` |
| REST API endpoints | `inc/rest/` |
| Scheduled events (cron) | `inc/core/` |
| Development tools | `inc/development/` |
| WooCommerce | `inc/woocommerce/` |
| Custom post types | `inc/theme-functions/` |

---

## Directory Structure

```
basecamp/
  functions.php               Bootstrap — all require_once calls live here
  inc/
    class-basecamp.php         Theme setup, image sizes, menus, body classes
    frontend/
      class-basecamp-frontend.php   picture(), page_navi(), related_posts(), etc.
      class-basecamp-svg-icons.php  Centralised SVG icon registry
      remove-bloat.php              Strips unused WordPress default output
    seo/
      basecamp-title-functions.php  Context-aware <title> via extension classes
      basecamp-meta-description-functions.php
      basecamp-social-meta-functions.php   Open Graph + Twitter Card
    admin/
      class-basecamp-admin.php      Login branding, dashboard, editor tweaks
      basecamp-admin-helpers.php    Sanitisers, Customizer helpers
    img-optimization/
      basecamp-webp-functions.php   Frontend WebP URL substitution
      basecamp-webp-conversion.php  Upload-time JPEG/PNG → WebP conversion
    core/
      basecamp-scheduled-events.php  Cron intervals, scheduling, callbacks
    rest/
      basecamp-rest-endpoints.php    REST routes under basecamp/v1
    woocommerce/
      woocommerce-functions.php      WooCommerce theme support scaffold
    theme-functions/
      basecamp-cpt-scaffold.php      Example CPT + taxonomy (commented out by default)
    development/
      class-basecamp-development.php  DevPilot local debug bar
  assets/
    css/
      scss/                   Dart Sass source
      build/                  Compiled .min.css (committed)
    js/
    img/
  Docs/
    developer/                Module-level reference docs
    planning/                 Roadmap, todo, overview
```

---

## Conventions

### Naming

| Thing | Convention | Example |
|---|---|---|
| Classes | `Basecamp_*` | `Basecamp_Frontend` |
| Functions | `basecamp_*` | `basecamp_daily_maintenance_callback` |
| Hooks (actions/filters) | `basecamp_*` | `basecamp_body_page_classes` |
| Text domain | `basecamp` | `__( 'Text', 'basecamp' )` |
| Image size handles | `basecamp-img-*` | `basecamp-img-xl` |
| CSS classes | BEM | `card__picture`, `hero__img` |

### File placement

- New PHP modules → `inc/` subdirectory matching the area, loaded in `functions.php`
- New page templates → theme root (alongside `page-home.php`)
- New template parts → `template-parts/`
- New SCSS components → `assets/css/scss/basecamp-global-layout/components/`

### Escaping

Follow WordPress patterns already present — `esc_html()`, `esc_url()`, `esc_attr()`, `wp_kses_post()`. Never echo raw user data or unescaped option values.

---

## Extending

### Body classes

Add page-specific body classes via the filter — never hardcode page slugs in the theme:

```php
add_filter( 'basecamp_body_page_classes', function( $map ) {
    $map['contact'] = 'is--contact';
    $map['shop']    = 'has--breadcrumb';
    return $map;
} );
```

### SEO title extensions

Extend for a new CPT or plugin by adding a class to `Basecamp_Title_Manager::$extensions` in `basecamp-title-functions.php`:

```php
class Basecamp_Title_My_CPT extends Basecamp_Title_Extension {
    public function maybe_title(): ?string {
        if ( is_singular( 'my-cpt' ) ) {
            return get_the_title() . ' — My CPT';
        }
        return null;
    }
}
```

### Navigation menus

Register additional menu locations via the `basecamp_register_nav_menus` filter:

```php
add_filter( 'basecamp_register_nav_menus', function( $menus ) {
    $menus['my-location'] = __( 'My Location', 'basecamp' );
    return $menus;
} );
```

### Image sizes

Add custom sizes in `inc/class-basecamp.php` alongside the existing `add_image_size()` calls. Run `wp media regenerate --yes` after adding a new size.

---

## SCSS Build

Source lives in `assets/css/scss/`. The Live Sass Compiler extension compiles to `assets/css/build/*.min.css` on save. Commit both source and compiled files — the compiled file is what the browser loads.

See [Docs/developer/04-scss-system.md](Docs/developer/04-scss-system.md) for breakpoints, barrel file conventions, and the responsive coordinator pattern.

---

## WooCommerce

WooCommerce support is included but disabled by default. To activate:

1. Install and activate the WooCommerce plugin.
2. In `functions.php`, uncomment the WooCommerce load line:
   ```php
   require_once __DIR__ . '/inc/woocommerce/woocommerce-functions.php';
   ```
3. `woocommerce-functions.php` handles `add_theme_support( 'woocommerce' )`, sidebar removal, and WooCommerce-specific hooks automatically once loaded.

---

## Developer Docs

Full module-level reference is in [`Docs/developer/`](Docs/developer/):

| File | Covers |
|---|---|
| `00-setup.md` | Install, plugins, first-run checklist |
| `01-architecture.md` | Module system, load order, hook inventory |
| `02-code-style.md` | Naming, escaping, class patterns |
| `03-metaboxes.md` | Link list and video carousel meta boxes |
| `04-scss-system.md` | Dart Sass, breakpoints, responsive coordinator |
| `05-images-media.md` | Image sizes, WebP pipeline, `picture()` helper |
| `06-seo.md` | Title manager, meta descriptions, Open Graph |

---

## License

WTFPL — do whatever you want with it.