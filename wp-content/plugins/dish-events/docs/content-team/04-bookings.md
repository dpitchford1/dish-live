# Bookings — Reading and Managing Booking Records

This document covers how bookings are created, what the different statuses mean, and what actions are available to admins.

---

## How a Booking Is Created

A booking record is created automatically when a customer begins the checkout process. Here's the lifecycle from the customer's perspective:

1. Customer visits a class page → clicks **Book Now** → lands on the checkout page
2. They fill in their details, choose a quantity, and submit the form
3. The PayPal payment window opens — a booking record is created at this point with status **Pending**
4. Customer completes payment in PayPal → the booking status updates to **Completed**
5. Confirmation email is sent to the customer; admin notification email is sent to the studio
6. Customer is redirected to their booking details page

If the customer closes the PayPal window without paying, or if their session expires, the booking remains **Pending** and is eventually treated as abandoned.

---

## Booking Statuses

| Status | Colour | What it means |
|---|---|---|
| **Pending** | 🟡 Yellow | Checkout started — payment not yet confirmed. Could be mid-checkout or abandoned. |
| **Completed** | 🟢 Green | Payment confirmed. The seat is reserved. |
| **Cancelled** | 🔴 Red | Booking was cancelled — by admin or automatically after timer expiry. The seat is released back to capacity. |
| **Failed** | 🔴 Red | Payment attempt failed (gateway error). No seat reserved. |
| **Refunded** | ⚫ Grey | Admin has marked the booking as refunded. Seat is released. |

---

## The Bookings List

Go to **Dish Events → Bookings** to see all bookings.

**Columns shown:**

- **Booking ID** — unique reference number (e.g. #1042)
- **Class** — the class instance that was booked (with date)
- **Customer** — name and email address
- **Guests** — number of seats/attendees booked
- **Total** — amount charged
- **Status** — colour-coded status badge
- **Date** — when the booking was created

**Filtering the list:**

Use the filters at the top of the list to narrow results by:
- **Class** — show bookings for a specific class
- **Status** — show only Pending, Completed, Cancelled, etc.
- **Date range** — show bookings within a date window

**Exporting:**

Click **Export CSV** (above the list) to download the current filtered view as a spreadsheet. Use this for attendee lists, revenue reporting, or sharing with the studio.

---

## Viewing a Single Booking

Click any booking's title or ID to open the full booking detail screen. This shows:

- **General Info** — booking ID, class, date, customer details, attendee count, total, payment method, transaction reference
- **Transaction Log** — a timestamped record of every payment event (useful for troubleshooting PayPal issues)
- **Customer Details** — name, email, phone
- **Custom Fields** — any additional information collected at checkout (dietary requirements, special requests, etc.)
- **Admin Notes** — internal notes visible only to admins
- **Booking Actions** — buttons to change the booking status

---

## Admin Actions

From the **Booking Actions** panel on a booking's detail screen, you can manually update the status:

### Mark as Completed

Use this when:
- A customer paid outside the normal online flow (e.g. cash, bank transfer, or corporate invoice) and you need to mark their booking confirmed
- A payment was stuck in Pending and you've verified it manually

**Effect:** Status changes to Completed → a booking confirmation email is sent to the customer.

### Cancel

Use this when:
- A customer has requested a cancellation and your policy allows it
- You need to free up a seat (e.g. the class is moved)

**Effect:** Status changes to Cancelled → the seat is released back to available capacity → a cancellation email is sent to the customer.

> Cancellation policy and whether customers can self-cancel is not yet finalised. Until self-service cancellation is enabled, all cancellations are handled manually from this screen.

### Mark as Refunded

Use this after you have processed a refund via PayPal (or other means) and want to record it in the system.

**Effect:** Status changes to Refunded → the seat is released → a refund notification email is sent to the customer.

> The plugin does **not** automatically issue a refund to PayPal — this must be done manually through the PayPal dashboard first. Marking Refunded here is purely a record-keeping step.

---

## Adding Internal Notes

The **Admin Notes** section at the bottom of a booking lets you leave internal notes that are never visible to the customer. Use this for:

- Recording phone conversations about a booking
- Noting special arrangements (e.g. "dietary requirement passed to chef team")
- Flagging issues for follow-up

Click **Add Note**, type your note, and save. Notes are timestamped with the date and admin user who added them.

---

## Capacity and Bookings

The plugin tracks capacity automatically. When a booking is **Completed**, the attendee count is deducted from the ticket type's available capacity. When a booking is **Cancelled** or **Refunded**, those seats are released back.

To check remaining capacity for a class:
- Open the class instance from **Dish Events → All Classes**
- The Summary sidebar shows: capacity, bookings, and remaining spots

---

## Common Gotchas

**"A customer says they paid but their booking shows Pending"**
This usually means the PayPal callback didn't complete (browser closed early, network issue). Check the Transaction Log on the booking for clues. If payment was confirmed in PayPal, use **Mark as Completed** to update the status manually and send the confirmation email.

**"A booking shows Completed but the customer didn't receive an email"**
The confirmation email fires when the status transitions to Completed. If you manually mark a Pending booking as Completed, the email will send. Check the studio's spam filter too — the email sends from whatever address is set in **Dish Events → Settings → Emails**.

**"I need to see all bookings for a specific class"**
Use the **Class** filter on the Bookings list, or open the class instance directly — the bookings count in the Summary sidebar links to a pre-filtered list.

**"I cancelled a booking but capacity didn't go back up"**
Capacity is released when the status is **Cancelled** or **Refunded**. If you trashed a booking directly without changing its status first, capacity may not have updated — check the class instance's remaining count and manually adjust if needed.
