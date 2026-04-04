# Data Model

Custom post types, taxonomy, database tables, meta keys, and post statuses — all in one place.

---

## Custom Post Types

### `dish_class` — Class Instances

The dated, teachable session. Thin record — all rich content resolves via `dish_template_id`.

| Property | Value |
|---|---|
| Public | `false` — no frontend URL |
| Show in UI | `true` |
| Has archive | `false` |
| Registered in | `CPT\ClassPost` |

**Post meta:**

| Key | Type | Description |
|---|---|---|
| `dish_template_id` | `int` | FK → `dish_class_template` post ID (required) |
| `dish_start_datetime` | `int` | UTC timestamp — instance start |
| `dish_end_datetime` | `int` | UTC timestamp — instance end |
| `dish_chef_ids` | `JSON int[]` | `dish_chef` post IDs assigned to this instance |
| `dish_booking_opens` | `int` | UTC timestamp — per-instance booking open override. NULL = use ticket type rule. |
| `dish_recurrence` | `JSON object` | Recurrence rule (see Recurrence section below) |
| `dish_recurrence_parent_id` | `int` | Post ID of the parent recurring instance |
| `dish_is_featured` | `bool` | Featured flag |
| `dish_class_type` | `string` | `public` or `corporate` |
| `dish_min_attendees` | `int` | Minimum booking size (corporate) |
| `dish_max_attendees` | `int` | Maximum booking size (corporate) |
| `dish_show_qr` | `bool` | Per-instance QR code override |
| `dish_external_booking_url` | `string` | External booking redirect (bypasses checkout) |

---

### `dish_class_template` — Class Templates

The canonical class definition. One per class subtype. Instances reference a template via `dish_template_id`.

| Property | Value |
|---|---|
| Public | `true` |
| Has archive | `false` |
| Supports | `title`, `editor`, `excerpt`, `thumbnail` |
| Rewrite slug | `classes/%dish_class_format%` (resolved via `post_type_link` filter in `ClassTemplateAdmin`) |
| Registered in | `CPT\ClassTemplatePost` |

Example URL: `/classes/hands-on/german-beer-garden/`

**Post meta:**

| Key | Type | Description |
|---|---|---|
| `dish_ticket_type_id` | `int` | FK → `dish_ticket_types.id` — drives price, capacity, and format |
| `dish_booking_type` | `string` | `online` or `enquiry` |
| `dish_gallery_ids` | `JSON int[]` | Attachment IDs for the gallery |
| `dish_is_guest_chef` | `bool` | Marks this as a guest chef class |
| `dish_guest_chef_name` | `string` | Guest chef display name |
| `dish_guest_chef_title` | `string` | Guest chef role/title |
| `dish_menu_items` | `string` | Newline-separated menu item list |
| `dish_menu_dietary_flags` | `JSON string[]` | Allergen keys from `MenuMetaBox::DIETARY_FLAGS` |
| `dish_menu_friendly_for` | `JSON string[]` | Keys from `MenuMetaBox::FRIENDLY_FOR` |

> **Auto-derive rule:** When a template is saved, the plugin reads `dish_ticket_type_id → format_id`, finds the matching `dish_format` post, and assigns it as the template's taxonomy term. Admins never set the format directly.

---

### `dish_chef` — Chefs

Instructor profiles with public pages.

| Property | Value |
|---|---|
| Public | `true` |
| Has archive | `false` |
| Supports | `title`, `editor`, `thumbnail` |
| Rewrite slug | Configurable via Settings → URLs (default: `chef`) |
| Registered in | `CPT\ChefPost` |

**Post meta:**

| Key | Type | Description |
|---|---|---|
| `dish_chef_title` | `string` | Professional role/title |
| `dish_chef_website` | `string` | Personal site URL |
| `dish_chef_instagram` | `string` | Instagram URL |
| `dish_chef_linkedin` | `string` | LinkedIn URL |
| `dish_chef_tiktok` | `string` | TikTok URL |
| `dish_chef_gallery_ids` | `JSON int[]` | Gallery attachment IDs |
| `dish_is_team_member` | `bool` | When true: excluded from class picker and `[dish_chefs]` listing; shown in "The Team" on the archive page |

---

### `dish_booking` — Bookings

One post per customer booking. Created programmatically — no "Add New" in admin.

| Property | Value |
|---|---|
| Public | `false` |
| Show in admin | `true` |
| Capability type | `dish_booking` |
| Registered in | `CPT\BookingPost` |

**Post meta:**

| Key | Type | Description |
|---|---|---|
| `dish_class_id` | `int` | FK → `dish_class` post ID |
| `dish_class_date` | `int` | UTC timestamp of the booked session |
| `dish_created_at` | `int` | UTC timestamp of booking creation |
| `dish_attendee_count` | `int` | Number of seats booked |
| `dish_total_cents` | `int` | Total charged in cents |
| `dish_payment_method` | `string` | Gateway slug (e.g. `paypal`) |
| `dish_payment_status` | `string` | `pending`, `completed`, `failed` |
| `dish_transaction_id` | `string` | Gateway transaction reference |
| `dish_transaction_log` | `JSON array` | Timestamped payment event log |
| `dish_customer_name` | `string` | Customer name |
| `dish_customer_email` | `string` | Customer email |
| `dish_customer_phone` | `string` | Customer phone |
| `dish_customer_user_id` | `int` | WP user ID — `0` for guest bookings |
| `dish_checkout_fields_data` | `JSON` | Submitted custom checkout field values |
| `dish_session_key` | `string` | Checkout session token (cleared after completion) |
| `dish_session_expires` | `int` | UTC timestamp when the checkout timer expires |
| `dish_notes` | `string` | Admin-only notes |

---

### `dish_format` — Class Formats

| Property | Value |
|---|---|
| Public | `true` |
| Has archive | `false` (format archive URLs are handled via rewrite rules, not WP archive) |
| Supports | `title`, `editor`, `thumbnail` |
| Registered in | `CPT\FormatPost` |

**Post meta:**

| Key | Type | Description |
|---|---|---|
| `dish_format_color` | `string` | Hex colour — used on FullCalendar event chips |

---

## Custom Taxonomy

### `dish_class_format` (attached to `dish_class_template`)

This is a legacy taxonomy used for URL routing and filtering. Its terms mirror the `dish_format` CPT — the assignment is automatic (see the auto-derive rule above). Admins do not interact with it directly.

| Property | Value |
|---|---|
| Hierarchical | `false` |
| Public | `true` |
| Publicly queryable | `true` |
| Rewrite slug | `classes` (configurable via Settings → URLs) |

Format archive URL: `/classes/hands-on/` — lists all published templates in that format.

---

## Custom Post Statuses

| Slug | Label | Used on |
|---|---|---|
| `dish_expired` | Expired | `dish_class` |
| `dish_cancelled` | Cancelled | `dish_class`, `dish_booking` |
| `dish_pending` | Pending | `dish_booking` |
| `dish_completed` | Completed | `dish_booking` |
| `dish_failed` | Failed | `dish_booking` |
| `dish_refunded` | Refunded | `dish_booking` |

---

## Custom DB Tables

### `{prefix}dish_ticket_types`

Global reusable ticket templates. A `dish_class_template` stores a single `dish_ticket_type_id` FK. Pricing, capacity, and booking window are managed here — not per-class.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint PK` | |
| `format_id` | `bigint` | FK → `dish_format` post ID |
| `name` | `varchar(255)` | Internal name (e.g. "Hands On — Standard") |
| `description` | `text` | Optional internal note |
| `price_cents` | `int` | Ticket price in cents |
| `sale_price_cents` | `int\|NULL` | Sale price — NULL = no sale |
| `capacity` | `int\|NULL` | Total seats; NULL = unlimited |
| `show_remaining` | `tinyint(1)` | Show "X spots left" on frontend |
| `min_per_booking` | `int` | Min quantity per checkout |
| `per_ticket_fees` | `longtext\|NULL` | JSON: `[{"label": "", "amount_cents": 0}]` |
| `per_booking_fees` | `longtext\|NULL` | JSON: `[{"label": "", "amount_cents": 0}]` |
| `booking_starts` | `longtext\|NULL` | JSON availability rule (see below) |
| `show_booking_dates` | `tinyint(1)` | Show availability dates on frontend |
| `is_active` | `tinyint(1)` | Soft delete |

**`booking_starts` JSON:**

```json
{"mode": "immediate"}
// or
{"mode": "days_before", "days": 30}
```

> **Per-instance override:** Set `dish_booking_opens` on the class instance to bypass the ticket type rule for that specific class. Booking always closes at `dish_start_datetime`.

---

### `{prefix}dish_ticket_categories`

Organisational grouping for ticket types. No pricing at this level.

| Column | Type |
|---|---|
| `id` | `bigint PK` |
| `name` | `varchar(255)` |
| `description` | `text` |
| `is_active` | `tinyint(1)` |

---

### `{prefix}dish_checkout_fields`

Admin-configured custom fields shown on the checkout form.

| Column | Type | Notes |
|---|---|---|
| `id` | `bigint PK` | |
| `field_type` | `varchar(50)` | `text`, `select`, `checkbox`, `textarea`, `radio` |
| `label` | `varchar(255)` | |
| `options` | `text\|NULL` | JSON options array for select/radio |
| `is_required` | `tinyint(1)` | |
| `apply_per_attendee` | `tinyint(1)` | Repeat per attendee vs once per booking |
| `sort_order` | `int` | Display order |
| `is_active` | `tinyint(1)` | Soft delete |

---

## `wp_options` Keys

| Key | Contents |
|---|---|
| `dish_settings` | Serialised settings array — all plugin settings |
| `dish_db_version` | Current DB schema version string |
| `dish_activation_redirect` | Transient flag — redirects to Settings once after activation |
| `dish_flush_rewrite_rules` | Transient flag — flushes rewrite rules once on next `admin_init` |

---

## Recurrence Rule Structure

Stored as JSON in `dish_recurrence` post meta on the parent class instance:

```json
{
  "type": "weekly",
  "interval": 1,
  "days": ["thursday"],
  "ends": "on",
  "end_date": "2026-12-31",
  "end_after": null,
  "child_ids": [123, 124, 125]
}
```

| Field | Values |
|---|---|
| `type` | `daily`, `weekly`, `monthly`, `yearly` |
| `ends` | `on` (specific date), `after` (N occurrences), `never` |
| `child_ids` | Post IDs of generated child instances — maintained by `RecurrenceManager` |

Trashing or deleting the parent cascades to all `child_ids` via `RecurrenceManager::delete_series()`.

---

## Repository Classes

All DB queries go through stateless repository classes in `Dish\Events\Data\`. Never query posts or custom tables directly from templates or admin callbacks.

| Class | Reads / writes |
|---|---|
| `ClassRepository` | `dish_class` posts and meta |
| `ClassTemplateRepository` | `dish_class_template` posts and meta |
| `ChefRepository` | `dish_chef` posts and meta; supports `exclude_team` and `team_only` query args |
| `BookingRepository` | `dish_booking` posts and meta |
| `TicketTypeRepository` | `dish_ticket_types` table |
| `CheckoutFieldRepository` | `dish_checkout_fields` table |
| `ReportsRepository` | Aggregate queries for the Reports admin page |
