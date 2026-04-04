# Class Lifecycle — How Everything Fits Together

This document walks the content team through the complete lifecycle of publishing a class — from initial setup through to a live, bookable event on the site.

---

## The Big Picture

Every class on the site is built from **five interlocking pieces**. Understanding how they relate to each other makes the whole system click.

| Piece | What it is | Lives in admin under |
|---|---|---|
| **Format** | The type of class — e.g. Hands On, Workshop, Demonstration | Dish Events → Formats |
| **Ticket Type** | Price, capacity, and booking rules | Dish Events → Ticketing |
| **Chef** | The instructor teaching the class | Dish Events → Chefs |
| **Class Template** | The canonical class — title, description, photos, menu | Dish Events → Class Templates |
| **Class Instance** | A dated session of that template | Dish Events → All Classes |

**The relationship in plain English:**

> A **Format** groups related classes (e.g. all "Hands On" classes). A **Ticket Type** sets the price and capacity for a Format. A **Class Template** is the "what" — what the class is about, what's on the menu. A **Class Instance** is the "when" — a specific date and time when that template runs, taught by a specific **Chef**.

---

## Step 1 — Set Up Formats

Formats are the top-level categories for all classes. They appear in the breadcrumb on every class page and drive the URL structure.

**Examples:** Hands On · Workshop · Demonstration · Kids Class

**To create a Format:**

1. Go to **Dish Events → Formats**
2. Click **Add New**
3. Enter the format name (e.g. "Hands On")
4. Add a description — this appears on the format landing page (e.g. `/classes/hands-on/`)
5. Set a featured image — used as the format banner
6. **Publish**

> The format landing page at `/classes/hands-on/` automatically lists all published Class Templates in that format. No extra setup needed.

---

## Step 2 — Set Up Ticket Types

Ticket Types define the price, capacity, and booking window for a group of classes. They live under a Format.

**To create a Ticket Type:**

1. Go to **Dish Events → Ticketing**
2. Click **Add Ticket Type**
3. Fill in:
   - **Name** — descriptive, e.g. "Hands On — Standard" (internal use only)
   - **Format** — pick the parent Format from the dropdown
   - **Price** — per person in dollars (e.g. `95.00`)
   - **Sale Price** — leave blank if no sale is running
   - **Capacity** — total seats available per class (e.g. `12`)
   - **Min per booking** — minimum seats a customer must book (usually `1`)
   - **Booking starts** — either "right away" or "N days before the class"
4. Leave **Active** checked
5. Click **Save**

> One Ticket Type can be shared across many Class Templates. For example, all standard Hands On classes at $95/person can share a single "Hands On — Standard" ticket type.

---

## Step 3 — Set Up Chefs

Chefs are the public profiles of anyone teaching a class. They each get their own page on the site (e.g. `/chef/peter-sanagan/`).

**To create a Chef:**

1. Go to **Dish Events → Chefs**
2. Click **Add New**
3. Enter the chef's **name** as the post title
4. Write their **bio** in the main editor
5. Set a **featured image** (headshot)
6. Fill in the **Chef Details** meta box:
   - Role (e.g. "Head Butcher, Sanagan's Meat Locker")
   - Website, Instagram, LinkedIn, TikTok links
   - Gallery images (optional — shown on their profile page)
7. **Team Member checkbox** — if this person is kitchen staff, a manager, or support (not a teaching chef), check **"Team member (not a teaching chef)"**. They will appear in a separate "The Team" section on the chefs archive page and will not appear in the chef picker when creating classes.
8. **Publish**

---

## Step 4 — Create a Class Template

The Class Template is the heart of the system. It holds everything that stays the same every time the class runs: the description, photos, menu, pricing, and format.

**To create a Class Template:**

1. Go to **Dish Events → Class Templates**
2. Click **Add New**
3. Enter the class **title** (e.g. "Whole Hog Butchery")
4. Write the class **description** in the main editor — this is the full public description shown on the class page
5. Add an **excerpt** — a short teaser shown on cards and listings
6. Set a **featured image** — the hero image for the class page
7. Fill in the **Class Template** meta box:
   - **Booking Type** — "Online" for standard checkout, "By Request" for enquiry-only classes
   - **Ticket Type** — pick the relevant ticket type (this auto-sets the Format, price, and capacity)
   - **Gallery** — add additional photos
8. Fill in the **Class Menu** meta box:
   - **Menu Items** — type each dish on its own line
   - **Dietary Flags** — check all allergens that apply (Seafood, Shellfish, Gluten, Dairy, Eggs, Nuts, Tree Nuts, Pork)
   - **Friendly For** — check if the class is Pescatarian or Gluten Free friendly
9. **Guest Chef** — if this is a guest chef class, check the "Guest Chef class" box and enter the chef's name and title/role
10. **Publish**

> As soon as the template is published, its page is live at `/classes/{format-slug}/{template-slug}/`. It will show the description and menu but no upcoming dates yet — those appear once Class Instances are created.

---

## Step 5 — Create Class Instances

A Class Instance is a dated session of a Class Template. This is where you set the actual date, time, and chef.

**To create a single instance:**

1. Go to **Dish Events → All Classes**
2. Click **Add New**
3. In the **Date & Time** tab:
   - Set **Start** date and time
   - Set **End** date and time
   - Leave recurrence set to "None" for a one-off class
4. In the **Template** tab:
   - Pick the **Class Template** from the dropdown
   - The summary card will populate with the format, ticket type, price, and capacity
5. In the **Details** tab:
   - Assign one or more **Chefs** using the checkboxes
6. **Publish**

> Once published, this instance will appear in the "Upcoming Dates" list on the template's page, on the calendar, and on the class archive.

---

## Step 5b — Creating Recurring Instances

For classes that run on a regular schedule (e.g. every Thursday), use recurrence instead of creating instances one by one.

1. Follow Steps 1–4 above
2. In the **Date & Time** tab, set the **start date** to the first occurrence
3. Set the **Recurrence** field:
   - **Weekly** — pick the day(s) of the week (e.g. every Thursday)
   - **Monthly** — choose a specific date (e.g. 15th) or a weekday pattern (e.g. 2nd Saturday)
4. Set **Ends** — either on a specific date or after a set number of occurrences
5. **Publish** — the plugin generates all child instances automatically

> To cancel a single occurrence without affecting the rest of the series, open that specific instance and trash it. The rest of the series is unaffected.

---

## Step 6 — "By Request" Classes

Some classes don't have standard online booking — customers need to enquire first.

When creating a Class Template:

1. Set **Booking Type** to **"By Request — contact to book"**
2. The price is hidden on the frontend; an **"Enquire to Book"** button appears instead
3. You can still set a **Ticket Type** to display capacity on the class page

On the Class Instance:
- Assign the chef as normal
- No checkout link is shown — the enquiry button links to your contact/enquiry page

---

## What Appears Where

Once everything is set up, here's where your content surfaces automatically:

| Content | Where it appears |
|---|---|
| Template title, description, menu | Class Template single page (`/classes/hands-on/german-beer-garden/`) |
| Upcoming dated instances | Listed on the template page + calendar |
| Chef profile | Linked from the template page and class detail pages |
| Format | Format landing page (`/classes/hands-on/`) lists all templates |
| Upcoming Menus | `[dish_upcoming_menus]` shortcode page — auto-populated, sorted by date |
| Calendar | `[dish_classes]` shortcode with `view="calendar"` |

---

## Quick Reference — What Requires What

```
Format
  └── Ticket Type (references a Format)
        └── Class Template (references a Ticket Type → auto-sets Format)
              ├── Class Instance (references a Template, assigns Chef + date)
              └── Menu (entered directly on the Template)
```

---

## Common Gotchas

**"My class isn't showing on the calendar"**
Check that both the Class Template and the Class Instance are **Published** (not Draft). Also confirm the instance has a start datetime set.

**"The upcoming dates list is empty"**
The list only shows instances with a start date **in the future**. Past instances are hidden automatically.

**"The menu isn't showing on the class page"**
The menu is entered on the **Class Template**, not the instance. Open the template, scroll to the **Class Menu** meta box, and add the menu items there.

**"I want to change a single date in a recurring series"**
Open that specific instance, change the date, and save. Only that instance is affected — the rest of the series is unchanged.

**"A chef isn't appearing in the chef picker"**
They may be marked as a **Team Member**. Open their chef profile and uncheck "Team member (not a teaching chef)" if they should be assignable to classes.
