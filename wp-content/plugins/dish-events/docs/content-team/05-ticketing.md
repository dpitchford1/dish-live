# Ticketing — Ticket Types and Pricing

This document covers how the ticketing system works, how to create and manage ticket types, and how fees and booking windows are configured.

---

## How Ticketing Works

Ticket types are **global, reusable templates**. Instead of setting a price on each individual class, you create a ticket type once and attach it to as many class templates as you like.

```
Ticket Type  (price, capacity, booking window)
     └── Class Template  (references a ticket type)
              └── Class Instance  (dated session — inherits price/capacity from template → ticket type)
```

**What a ticket type defines:**
- The price per person
- Whether there's a sale price active
- The total capacity (seats per class)
- Any additional fees (per-ticket or per-booking)
- When bookings open (immediately, or N days before the class)

**What a ticket type belongs to:**
- A **Format** (e.g. "Hands On", "Workshop"). This is how the format is auto-assigned to class templates — when you set a ticket type on a template, the format is set automatically.

---

## Ticket Types List

Go to **Dish Events → Ticketing** to see all ticket types.

Columns shown:

| Column | Description |
|---|---|
| **Name** | Internal name — not shown to customers |
| **Format** | The format (class category) this ticket type belongs to |
| **Price** | Price per person |
| **Sale Price** | Current sale price (blank = no sale) |
| **Capacity** | Total seats per class |
| **Status** | Active or Inactive |

Ticket types can be sorted by Name, Price, or Status. Bulk actions: **Activate** or **Deactivate** selected rows.

---

## Creating a Ticket Type

1. Go to **Dish Events → Ticketing**
2. Click **Add New**
3. Fill in the form:

### Name *(required)*

An internal label — customers never see this. Use something descriptive enough that it's unambiguous when selecting from a dropdown. Good examples:

- "Hands On — Standard ($95)"
- "Knife Skills Intensive"
- "Kids Class — Weekend"

### Format *(required)*

Select the format (class category) this ticket type belongs to. When this ticket type is assigned to a class template, that template's format is automatically set to match.

### Description

Optional internal note — useful for explaining any special rules or context about this ticket type.

### Price *(required)*

The per-person price in dollars (e.g. `95.00`). This is the amount charged to customers at checkout.

### Sale Price

Leave blank for no sale. When a sale price is entered, it silently replaces the regular price on the frontend — customers see only the sale price. No "was/now" label is shown automatically.

### Capacity

Total seats available per class. Leave blank for unlimited capacity.

When capacity is set, the plugin tracks bookings against it in real time. Once a class is full, the "Book Now" button is replaced with a "Sold Out" notice.

**Show remaining count** — check this to display a "X spots left!" badge on the class when the remaining capacity drops to or below the threshold set in Settings → Calendar.

### Min Per Booking

The minimum number of tickets a customer must book in a single transaction. Default is `1`. Set to `2` for couples-only or pairs classes.

---

## Fees

Fees are added to the order total at checkout. Two types are supported:

### Per-Ticket Fees

Multiplied by the ticket quantity. Use for charges that scale with the number of people (e.g. a kitchen supply fee of $5 per person).

**Example:** Ticket price $95 + Kitchen Supply Fee $5 = $100 per person × 2 tickets = $200 total.

### Per-Booking Fees

A flat charge applied once per booking, regardless of ticket quantity. Use for charges that don't scale (e.g. a corkage fee of $20 per booking).

**Example:** 2 tickets at $95 + Corkage Fee $20 = $210 total.

Both fee types have a **Label** (shown to the customer at checkout) and an **Amount**.

Click **+ Add Fee** to add a row. Click **✕** to remove a row. You can add multiple fees of each type.

---

## Booking Window

Controls when customers can start booking a class that uses this ticket type.

### Right away

Bookings open as soon as the class instance is published. Use this for most classes.

### N days before the class starts

Bookings open automatically N days before the class date. For example, setting `30` means a class on 15 April becomes bookable on 16 March. Use this to create a drip-release effect or to align with marketing campaigns.

**Show booking dates** — check this to display the booking open and close dates to customers on the frontend (e.g. "Bookings open 16 March").

> **Per-instance override:** For a one-off exception on a specific class instance (e.g. a popular class you want to open early), set the **Booking Opens** date directly on the class instance. This overrides the ticket type rule for that class only.

Bookings always close automatically at the class start time — there's no separate close configuration.

---

## Activating and Deactivating Ticket Types

Ticket types support soft delete via the **Active** toggle on each row.

- **Active** — available for selection when creating or editing class templates
- **Inactive** — hidden from all dropdowns; any templates already using this ticket type continue to work, but no new templates can be assigned to it

Use deactivation rather than deletion when a pricing tier is retired — this preserves the historical data of all classes and bookings that used it.

---

## Common Gotchas

**"The format didn't update on my class template after I changed the ticket type"**
The format auto-derives when the class template is saved. After changing the ticket type on a template, click Update/Save on the template to trigger the re-derive.

**"I need different prices for the same class on different dates"**
Create separate ticket types with different prices. Then create separate class templates for each price tier and assign the matching ticket type to each.

**"I want to run a sale on one class but not others that share the same ticket type"**
The sale price field applies to all classes using that ticket type. To run a sale on a subset of classes, create a duplicate ticket type with the sale price set and assign it only to the relevant templates, then switch it back when the sale ends.
