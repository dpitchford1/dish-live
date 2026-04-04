# Reports — Bookings, Revenue, and Attendees

The Reports page is at **Dish Events → Reports**. It has three tabs: Bookings, Revenue, and Attendees.

---

## Bookings Tab

The Bookings tab is the main overview. It shows summary stats for the current filter, followed by a paginated list of all matching bookings.

### Summary Cards

Four headline numbers appear at the top, updating whenever you apply a filter:

| Card | What it shows |
|---|---|
| **Total Bookings** | Number of bookings in the current filtered view |
| **Total Revenue** | Sum of all booking totals in the current filtered view |
| **Avg Revenue / Day** | Total revenue divided by the number of days in the filtered period |
| **Total Tickets** | Total number of individual seats/tickets sold |

> Stats count all active booking statuses — Pending, Completed, and Refunded. Cancelled and Failed bookings are excluded.

### Filtering

Use the filter bar to narrow the results:

| Filter | Description |
|---|---|
| **From / To** | Date range — filters by when the booking was created |
| **Status** | Show only bookings of a specific status (Pending, Completed, Failed, Refunded, Cancelled) |
| **Search** | Filter by customer name or email address |

Click **Filter** to apply. Click **Reset** to clear all filters and return to the full list.

### Bookings Table

Each row shows:

| Column | Description |
|---|---|
| **ID** | Booking reference number — links to the booking detail screen |
| **Date** | When the booking was created |
| **Class** | The class template name — links to the class template edit screen |
| **Customer** | Customer name and email |
| **Tickets** | Number of seats booked |
| **Total** | Amount charged for this booking |
| **Status** | Colour-coded status badge |
| **Gateway** | Payment method used (e.g. PayPal) |

The list is paginated at 30 bookings per page.

### Exporting Bookings

Click **⬇ Export CSV** (top right of the table) to download the current filtered view as a CSV file. The export respects all active filters — date range, status, and search are all applied to the export.

The CSV includes: Booking ID, Created Date, Class, Customer Name, Email, Phone, Tickets, Total, Status, Gateway, Transaction ID.

---

## Revenue Tab

The Revenue tab breaks down total revenue by class, showing which classes are generating the most income.

### Summary Cards

| Card | Description |
|---|---|
| **Total Revenue** | Sum across all classes in the filtered period |
| **Total Bookings** | Booking count across all classes |
| **Avg Revenue / Day** | Total revenue ÷ days in filtered period |

### Revenue Table

One row per class, sorted by class:

| Column | Description |
|---|---|
| **Class** | Class template name — links to the class template edit screen |
| **Bookings** | Number of completed bookings for this class |
| **Tickets** | Total tickets sold for this class |
| **Revenue** | Total revenue from this class |

A **Total** row at the bottom sums the Revenue column.

### Filtering

Filter by date range (From / To) — same as the Bookings tab.

---

## Attendees Tab

The Attendees tab lets you pull a full attendee list for any individual class. Use this to prepare for a class, check who's coming, or share with the chef team.

### Selecting a Class

Use the **Select class** dropdown to choose a class. Classes are listed as "Template Name — Date" (e.g. "German Beer Garden — Apr 18, 2026"). Click **View Attendees**.

### Attendee Summary Cards

Once a class is selected, three summary cards appear:

| Card | Description |
|---|---|
| **Bookings** | Number of booking records for this class |
| **Tickets Sold** | Total seats sold across all bookings |
| **Revenue** | Total revenue from this class's bookings |

### Attendee Table

One row per booking:

| Column | Description |
|---|---|
| **Booking** | Booking ID — links to the booking detail screen |
| **Name** | Customer name |
| **Email** | Customer email (click to open mail client) |
| **Phone** | Customer phone number |
| **Tickets** | Seats in this booking |
| **Total** | Amount charged for this booking |
| **Status** | Booking status badge |
| **Booked** | Date the booking was created |

### Exporting Attendees

Click **⬇ Export CSV** (top right) to download the attendee list for the selected class. The CSV includes: Booking ID, Name, Email, Phone, Tickets, Total, Status, Booked Date.

Use this to:
- Print a sign-in sheet for a class
- Share the attendee list with the chef
- Record attendance

---

## Common Gotchas

**"The revenue numbers don't match what I'd expect"**
Revenue only counts bookings with status Pending, Completed, or Refunded. Cancelled and Failed bookings are excluded. The date filter applies to when the **booking was created**, not when the class runs.

**"A class isn't appearing in the Attendees dropdown"**
The dropdown shows all published class instances. If a class was recently created it should appear immediately. Very old classes may scroll past the visible area — the dropdown loads up to 200 recent classes sorted by date.

**"The CSV export is blank except for the header row"**
All active filters are applied to the export. Check that your date range and status filter aren't too narrow. Resetting the filters and re-exporting will include all bookings.
