# Settings Reference

All plugin settings live under **Dish Events → Settings**. The page is organised into 10 tabs. Changes on each tab are saved independently.

---

## General

Core display and checkout behaviour.

| Setting | Description |
|---|---|
| **Currency** | The currency used for all prices. Options: CAD, USD, AUD, GBP, EUR, NZD |
| **Currency symbol** | The symbol displayed alongside prices (e.g. `$`, `£`, `€`). Set to match your chosen currency. |
| **Symbol position** | Whether the symbol appears before (`$45.00`) or after (`45.00$`) the price |
| **Time format** | 12-hour (`6:30 pm`) or 24-hour (`18:30`) — applied across all class date/time displays |
| **Date format** | How dates are presented to customers — DD/MM/YYYY, MM/DD/YYYY, YYYY-MM-DD, or natural (e.g. "28 March 2026") |
| **Show timezone to attendees** | When checked, the site's timezone is shown alongside all class date/time fields (recommended for studios that may attract out-of-town customers) |
| **Checkout timer (minutes)** | How long seats are held for a customer during checkout before being released. Range: 5–60 minutes |

---

## Venue

The single studio venue. This information appears on class pages, schema.org structured data, and may be used in email templates.

| Setting | Description |
|---|---|
| **Venue name** | The studio's name (e.g. "Dish Cooking Studio") |
| **Street address** | Full street address |
| **City** | City name |
| **Province / Territory** | Province or territory (e.g. ON, BC) |
| **Postal code** | Postal code |
| **Google Maps URL** | A direct Google Maps link to the studio address — used for the "View Map" button on class pages |
| **Latitude / Longitude** | Co-ordinates for the embedded Google Maps tile (optional) |
| **Google Maps API key** | Required for the embedded map on class detail pages. Obtain from Google Cloud Console. |
| **Hours of operation** | One line per day range (e.g. `Monday–Friday 09:00–17:00`). Used in schema.org LocalBusiness structured data. |

---

## Studio

Studio contact and social details. Used in email footers and schema.org structured data.

| Setting | Description |
|---|---|
| **Studio name** | Studio display name |
| **Contact email** | The studio's main contact email |
| **Phone number** | Studio phone number |
| **Website URL** | Studio website |
| **Instagram URL** | Instagram profile link |
| **Facebook URL** | Facebook page link |

---

## Pages

Assign the WordPress pages that host each plugin shortcode. The plugin uses these assignments to generate correct URLs for "Book Now" buttons, post-booking redirects, and navigation links.

| Setting | What it does |
|---|---|
| **Classes listing page** | The page with `[dish_classes]`. "View all classes" links point here. |
| **Checkout page** | The page with `[dish_booking]`. "Book Now" buttons redirect here with class parameters. |
| **Booking details page** | The page with `[dish_booking_details]`. Customers land here after a completed payment. |
| **Enquiry / Contact page** | Your existing contact or enquiry page. "Enquire to Book" buttons on By Request classes link here. |
| **My account page** | The page with `[dish_profile]`. Booking confirmation emails link here for repeat customers. |
| **Login page** | The page with `[dish_login]`. Non-logged-in visitors accessing the profile page are redirected here. |
| **Register page** | The page with `[dish_register]`. |
| **Chefs listing page** | The page with `[dish_chefs]`. |

> **Important:** After assigning pages, visit **Settings → Permalinks** and click Save Changes to flush WordPress rewrite rules.

---

## Calendar

Controls the behaviour of the `[dish_classes view="calendar"]` FullCalendar view.

| Setting | Description |
|---|---|
| **Default calendar view** | Which view loads first: Month, Week, Day, or List |
| **Available views** | Which view switcher tabs appear on the calendar. Uncheck any you don't want to offer. |
| **Hide past classes** | When checked, classes with a start date in the past are excluded from all listings and the calendar |
| **Classes per page (list view)** | How many classes appear per page in the list/grid view before pagination |
| **Show class format on calendar** | When checked, the format label is displayed on each calendar event card |
| **Max visible per day (month view)** | In month view, how many class events are shown per day cell before a "+N more" link appears |
| **Open class in new tab** | Calendar event links open in a new browser tab |
| **"Spots left" label threshold** | Show a "N spots left!" badge on calendar events when remaining capacity is at or below this number. Set to `0` to disable. |

---

## Checkout

Controls the checkout form behaviour and any global custom fields collected at checkout.

| Setting | Description |
|---|---|
| **Terms checkbox** | When enabled, customers must tick a checkbox before completing a booking |
| **Checkbox label** | The text shown beside the terms checkbox. HTML is allowed — use an `<a>` tag to link to your Terms & Conditions page. |

**Global Checkout Fields** — a drag-and-drop field builder below the settings. Add custom fields that appear on every checkout form (e.g. "Dietary restrictions", "How did you hear about us?"). Each field can be:
- A text input, textarea, dropdown, checkbox, or radio group
- Required or optional
- Applied once per booking or repeated per attendee

---

## Payments

Configure the payment gateway.

| Setting | Description |
|---|---|
| **Active gateway** | Select the payment gateway: PayPal, Stripe (future), or None (for free classes) |

**PayPal section:**

| Setting | Description |
|---|---|
| **Mode** | Sandbox (testing) or Live. Use Sandbox while testing; switch to Live when ready to take real payments. |
| **Client ID** | Your PayPal App client ID. Found in PayPal Developer Dashboard → My Apps & Credentials → your app. |

> **Testing payments:** With Mode set to Sandbox, no real money moves. Use PayPal's sandbox test accounts to simulate successful and failed payments.

---

## Emails

Controls all notification emails sent by the plugin. Emails are organised by type — each has its own Subject, CC, and Body fields.

**Sender settings** (at the top):

| Setting | Description |
|---|---|
| **From name** | The sender name on all plugin emails (e.g. "Dish Cooking Studio") |
| **From address** | The email address emails are sent from |
| **Admin notify address** | Where admin notification emails are sent. Can be different from the From address. |

**Email templates:**

Each email below has three fields: **Enabled** (on/off toggle), **Subject**, **CC** (optional), and **Body**.

| Email | When it sends | Goes to |
|---|---|---|
| **Booking confirmation** | Payment completed | Customer |
| **Booking cancelled** | Admin cancels a booking | Customer |
| **Booking reminder** | Configurable days before class date | Customer |
| **Waitlist spot available** | *(future feature)* | Customer |
| **Payment receipt** | Payment completed | Customer |
| **Admin: new booking** | Any new booking is created | Admin notify address |
| **Admin: cancellation** | A booking is cancelled | Admin notify address |

**Available tokens** for email bodies:

| Token | Replaced with |
|---|---|
| `{{booking_id}}` | Booking reference number |
| `{{customer_name}}` | Customer's full name |
| `{{customer_email}}` | Customer's email address |
| `{{customer_phone}}` | Customer's phone number |
| `{{class_title}}` | Class template title |
| `{{class_date}}` | Formatted class date |
| `{{class_time}}` | Formatted class time |
| `{{class_location}}` | Venue name and address |
| `{{ticket_type}}` | Ticket type name |
| `{{quantity}}` | Number of seats booked |
| `{{amount}}` | Total charged |
| `{{booking_details_url}}` | Link to the booking details page |
| `{{studio_name}}` | Studio name (from Studio settings) |
| `{{studio_email}}` | Studio email (from Studio settings) |
| `{{studio_phone}}` | Studio phone (from Studio settings) |

> Leave the Body field blank to use the built-in default template for that email.

---

## URLs

Controls the URL slugs used by the plugin's custom post types and taxonomy. **Changing these will break any existing links** — only change them on a fresh install or if you haven't launched yet.

| Setting | Default | Description |
|---|---|---|
| **Class URL slug** | `class` | The base segment in class template URLs (e.g. `/class/pasta-night/`) |
| **Chef URL slug** | `chef` | The base segment in chef profile URLs (e.g. `/chef/peter-sanagan/`) |
| **Class format taxonomy slug** | `class-format` | The base segment for format archive URLs |

> After saving any URL change, WordPress rewrite rules are automatically flushed. Verify your URLs work after making changes.

---

## Features

Toggle optional site features on or off.

| Setting | Description |
|---|---|
| **Google Calendar link** | Show an "Add to Google Calendar" link on booking confirmation pages |
| **iCal download** | Allow customers to download a `.ics` calendar file from their booking confirmation |
| **QR code on booking** | Display a QR code on the booking confirmation page (useful for check-in at the door) |
| **Guest checkout** | Allow customers to complete a booking without creating an account. If disabled, customers must log in before booking. |
| **Account creation at checkout** | Offer customers the option to create an account during checkout. When enabled, a "Create an account?" checkbox appears (unchecked by default). If disabled, guest checkout only. |
