# Chefs & The Team — Managing Instructor Profiles

This document covers how to create and manage chef profiles, the difference between chefs and team members, and how to handle guest instructors.

---

## Two Types of People

The plugin distinguishes between two types of people on the **Chefs** post type:

| Type | What they are | Where they appear |
|---|---|---|
| **Chef** | A teaching instructor who leads classes | Chef archive page ("The Chefs"), class pages, the chef picker when creating class instances, the upcoming menus listing |
| **Team Member** | Studio staff, managers, or support crew — not teaching | Chef archive page ("The Team" section only) — excluded from class picker and menus listing |

The distinction is controlled by a single checkbox on each chef's profile.

---

## Creating a Chef Profile

1. Go to **Dish Events → Chefs**
2. Click **Add New**
3. Enter the chef's **full name** as the post title
4. Write their **biography** in the main editor — this is shown in full on their profile page (`/chef/name/`)
5. Set a **featured image** — this is their headshot, used on the archive grid and class pages
6. Fill in the **Chef Details** meta box:

| Field | Notes |
|---|---|
| **Role / Title** | Professional title, e.g. "Chef & Food Stylist" or "Head Butcher, Sanagan's Meat Locker" |
| **Website** | Their personal site or business URL (optional) |
| **Instagram** | Full URL or handle — displayed as a link on their profile |
| **LinkedIn** | Full URL (optional) |
| **TikTok** | Full URL (optional) |
| **Gallery** | Additional photos shown on their profile page — add via the media library |

7. **Publish**

Once published, the chef's profile page is live at `/chef/{chef-slug}/` and they appear on the chefs archive page.

---

## The Team Member Flag

If this person is kitchen staff, front-of-house, a manager, or anyone else who isn't teaching classes, check the **"Team member (not a teaching chef)"** box before publishing.

**What this does:**

- They appear in the **"The Team"** section on the chefs archive page, visually separate from the teaching chefs
- They are **excluded from the chef picker** when creating or editing a class instance — you won't accidentally assign a team member to teach a class
- They are **excluded from the upcoming menus listing** so only teaching chefs are shown alongside classes

> The "The Chefs" / "The Team" split only appears on the archive page when **both groups have at least one published profile**. If everyone is a teaching chef (no team members), the page shows one unified list with no headings.

---

## Guest Chefs

A guest chef is someone who teaches a one-off or occasional class but doesn't have a full profile page on the site.

Guest chefs are configured on the **Class Template**, not as a separate Chefs post:

1. Open (or create) the **Class Template**
2. In the **Class Template** meta box, check **"Guest Chef class"**
3. Two new fields appear:
   - **Guest Chef Name** — their full name (displayed on the class page)
   - **Guest Chef Title / Role** — their role or bio line (e.g. "Pastry Chef, Patisserie Rolland")
4. Save

> Guest chef classes display the guest chef's name and title on the class page in the same position as a linked chef profile, but without a clickable link (no profile page exists). On the upcoming menus listing, the name appears as plain text rather than a link.

---

## Updating a Chef Profile

Chef profiles can be updated at any time. Changes take effect immediately:

- **Bio and headshot** changes appear on their profile page and on any class pages they're linked to
- **Gallery** changes appear on their profile page only
- **Social links** are updated site-wide wherever their profile is referenced

Updating a chef's profile does **not** affect historical booking records — bookings store the class instance data, not the chef profile directly.

---

## Deleting or Retiring a Chef

**Trashing a chef** removes them from:
- The chef archive page
- The chef picker on class instances
- Future class listings

It does **not** remove them from already-published class instances — those instances will simply show no chef link on the frontend until they are reassigned or until the class date passes.

If a chef is taking a long time off but will return, consider leaving them published and simply not assigning them to new classes, rather than trashing their profile.

---

## Where Chefs Appear on the Site

| Location | What's shown |
|---|---|
| `/chef/{slug}/` — their profile page | Full bio, headshot, gallery, social links, classes they teach |
| Class template page | Headshot thumbnail + name linking to their profile |
| Upcoming menus listing | Name linking to their profile beside each class entry |
| Chefs archive page (`[dish_chefs]`) | Card grid with headshot, name, and role |

---

## Common Gotchas

**"A chef isn't showing in the picker when I create a class"**
They may be marked as a Team Member. Open their profile and uncheck "Team member (not a teaching chef)."

**"I can only see 'The Team' section, not 'The Chefs' section"**
The split only appears when both groups are non-empty. If all published chefs are marked as team members, the page shows one list with no headings — check that at least one chef is published without the team member flag.

**"A guest chef isn't linking to a profile page"**
Guest chefs entered directly on a class template don't have profile pages — by design. If they need a full profile, create a Chef post for them instead and assign them as a regular chef on the class instance.
