# Shortcodes Reference

All plugin shortcodes are prefixed `dish_`. They can be placed on any WordPress page or post. Each shortcode handles its own output — no wrapper markup is needed.

---

## `[dish_classes]`

Renders the classes listing. Default output is a card grid of upcoming class instances, sorted by date (soonest first).

**Attributes:**

| Attribute | Default | Options | Description |
|---|---|---|---|
| `limit` | `12` | any integer | Max number of classes to show |
| `format_id` | *(all formats)* | post ID of a `dish_format` | Filter to a single format |
| `view` | `grid` | `grid`, `calendar` | Switch to FullCalendar view |

**Examples:**

```
[dish_classes]
```

```
[dish_classes limit="6"]
```

```
[dish_classes view="calendar"]
```

```
[dish_classes format_id="42" limit="8"]
```

> This shortcode should be placed on the page assigned as **Classes listing page** in Settings → Pages. That assignment is what makes "Book Now" buttons redirect here correctly.

---

## `[dish_chefs]`

Renders a card grid of published chefs. Team members are automatically excluded — only teaching chefs appear.

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `limit` | `12` | Max number of chefs to show |

**Examples:**

```
[dish_chefs]
```

```
[dish_chefs limit="6"]
```

> This shortcode should be placed on the page assigned as **Chefs listing page** in Settings → Pages.

---

## `[dish_formats]`

Renders a card grid of all published class formats (e.g. Hands On, Workshop, Demonstration). Useful for a homepage or "Browse by format" section.

**Attributes:**

| Attribute | Default | Options | Description |
|---|---|---|---|
| `limit` | `-1` (all) | any integer | Max number of formats to show |
| `order` | `ASC` | `ASC`, `DESC` | Sort order (by menu order) |

**Examples:**

```
[dish_formats]
```

```
[dish_formats limit="4"]
```

---

## `[dish_upcoming_menus]`

Renders a list of upcoming classes that have a menu entered, sorted by date. Each entry shows the class date, title, format, chef, menu items, dietary flags, and friendly-for tags.

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `limit` | `50` | Max number of classes to include |

**Examples:**

```
[dish_upcoming_menus]
```

```
[dish_upcoming_menus limit="20"]
```

> Only classes with at least one item in the Menu Items field are included. Classes with empty menus are automatically excluded.

---

## `[dish_login]`

Renders a login form. If the visitor is already logged in, shows a "you are already logged in" message with a link to their account page.

No configurable attributes.

```
[dish_login]
```

> This shortcode should be placed on the page assigned as **Login page** in Settings → Pages.

---

## `[dish_register]`

Renders a registration form for creating a new customer account. If the visitor is already logged in, they are shown a notice. If WordPress registration is disabled site-wide (`Settings → General → Anyone can register` is off), the form is hidden.

No configurable attributes.

```
[dish_register]
```

> This shortcode should be placed on the page assigned as **Register page** in Settings → Pages.

---

## `[dish_profile]`

Renders the logged-in customer's booking history — a list of past and upcoming bookings with links to each booking's detail view. Non-logged-in visitors are redirected to the login page.

No configurable attributes.

```
[dish_profile]
```

> This shortcode should be placed on the page assigned as **My account page** in Settings → Pages.

---

## Coming Soon

These shortcodes are defined in the plugin's roadmap and will be activated in a future release:

| Shortcode | Description |
|---|---|
| `[dish_booking]` | The full checkout flow — ticket quantity selector, customer details form, PayPal payment button. Place on the Checkout page. |
| `[dish_booking_details]` | Booking confirmation and detail view. Shows booking summary, QR code, iCal/Google Calendar links. Place on the Booking Details page. |

---

## Tips

**Scripts only load where needed** — the plugin conditionally enqueues its JavaScript and CSS only on pages that contain a `[dish_*]` shortcode. There's no global performance impact on other pages.

**Shortcodes in templates** — all shortcodes work inside WordPress page templates and in text widgets, not just the page editor.

**Multiple shortcodes on one page** — you can combine shortcodes on a single page if needed (e.g. `[dish_formats]` followed by `[dish_classes]` on a homepage), but generally each shortcode works best on its own dedicated page.
