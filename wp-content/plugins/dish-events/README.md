# Dish Events

A bespoke WordPress plugin for Dish Cooking Studio. Manages cooking classes, chef
profiles, ticket types, bookings, and customer accounts in a single self-contained
package that travels with the theme.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.4 |
| PHP | 8.0 |
| Theme | Basecamp (for Parsedown in the docs viewer) |

No Composer. No npm. No build step.

---

## Installation

1. Copy the `dish-events/` folder into `wp-content/plugins/`.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. On first activation you are redirected to **Dish Events → Settings** automatically.
4. Run through Settings (at minimum: configure the Pages tab and set up your
   Checkout and Payments tabs before going live).
5. Flush permalinks — **Settings → Permalinks → Save Changes** — so CPT URLs resolve.

---

## Plugin structure

```
dish-events/
├── dish-events.php          # Plugin header, constants, PSR-4 autoloader, bootstrap
├── uninstall.php            # Full teardown — CPT posts, DB tables, options
├── assets/
│   ├── css/                 # Admin and frontend stylesheets
│   └── js/                  # Admin and frontend scripts
├── docs/                    # In-plugin Markdown documentation (3 sections)
│   ├── content-team/        # For the people managing content day-to-day
│   ├── usage/               # Site setup, shortcodes, settings reference
│   └── developer/           # Architecture, data model, hooks & extending
├── includes/
│   ├── Admin/               # Settings, meta boxes, list tables, reports, docs viewer
│   ├── Booking/             # BookingManager, CheckoutTimer, checkout hold logic
│   ├── Core/                # Plugin bootstrap, Loader, Activator, Deactivator
│   ├── CPT/                 # Post type and taxonomy registration
│   ├── Data/                # Repository classes (read/write layer over WP APIs)
│   ├── Frontend/            # Shortcodes, public AJAX, template rendering
│   ├── Helpers/             # DateHelper, MoneyHelper
│   └── Notifications/       # NotificationService (email dispatch)
└── templates/               # Front-end PHP templates (theme-overridable)
    ├── account/             # login.php, register.php, profile.php
    ├── archive/             # classes.php, chefs.php, formats.php
    ├── menus/               # upcoming.php ([dish_upcoming_menus])
    └── single/              # class.php, chef.php
```

---

## Custom post types

| Post type | Label | Notes |
|---|---|---|
| `dish_class` | Class | Individual scheduled instance; not public |
| `dish_class_template` | Class Template | Public-facing page; holds all editorial content |
| `dish_format` | Format | e.g. "Date Night", "Kids Class"; has archive |
| `dish_chef` | Chef | Public profile pages and archive |
| `dish_booking` | Booking | One post per booking; private |

**Taxonomy:** `dish_class_format` — connects templates to formats.

---

## Database tables

| Table | Purpose |
|---|---|
| `{prefix}_dish_checkout_sessions` | Active checkout holds (TTL-based) |
| `{prefix}_dish_ticket_types` | Reusable ticket type definitions |
| `{prefix}_dish_ticket_type_fees` | Per-ticket and per-booking fees |

---

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[dish_classes]` | Upcoming class listing |
| `[dish_chefs]` | Chef grid/listing |
| `[dish_formats]` | Format archive |
| `[dish_upcoming_menus]` | Next upcoming menu per class (one per template) |
| `[dish_login]` | Customer login form |
| `[dish_register]` | Customer registration form |
| `[dish_profile]` | Booking history for logged-in customers |

Full attribute reference: **Dish Events → Documentation → Usage → Shortcodes**.

---

## Required pages

Eight WordPress pages must be created and their IDs registered in
**Dish Events → Settings → Pages**:

Classes, Chefs, Checkout, Booking Details, Login, Register, Profile, Upcoming Menus.

Full setup walkthrough: **Dish Events → Documentation → Usage → Pages Setup**.

---

## Documentation

Full documentation is built into the plugin and readable at
**Dish Events → Documentation** in the WordPress admin. Three sections:

- **Content Team** — class lifecycle, chefs & team, menus, bookings, ticketing, reports
- **Usage** — pages setup, shortcodes, settings reference, registration & accounts
- **Developer** — architecture, data model, hooks & extending

---

## Uninstalling

Deactivating the plugin leaves all data intact. Using **Plugins → Delete** triggers
`uninstall.php` which removes all CPT posts, custom DB tables, and plugin options.
This is irreversible.

---

## Version history

### 1.0.2 — current
- Menu reorder to match workflow sequence
- Upcoming menus deduplicated to one-per-template (next date only)
- Account deletion from customer profile page
- Documentation system with 12 documents across 3 sections
- Docs active state fix for admin menu

### 1.0.1
- Capacity enforcement and booking status management
- Chef Team Member flag
- Menu CPT and dietary flag system
- Ticket types with per-ticket and per-booking fee repeaters

### 1.0.0
- Initial release: CPTs, shortcodes, checkout flow, reports
