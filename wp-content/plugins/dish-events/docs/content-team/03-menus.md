# Class Menus — Adding and Managing Menus

This document covers how to add a menu to a class, what the dietary flags mean, and how the Upcoming Menus page works.

---

## Where Menus Live

Menus are stored on the **Class Template** — not on the Class Instance.

This means every time that class runs (every dated instance), it shows the same menu. If a class has a recurring menu that changes week to week, the recommended approach is either:

1. Update the menu on the template before each run, or
2. Create separate templates for classes with meaningfully different menus (e.g. "German Beer Garden — Summer Menu" vs "German Beer Garden — Winter Menu")

---

## Adding a Menu to a Class Template

1. Go to **Dish Events → Class Templates**
2. Open the template you want to add a menu to (or create a new one)
3. Scroll down to the **Class Menu** meta box
4. Fill in the fields:

### Menu Items

Type each dish on its own line in the **Menu Items** text area.

**Example:**
```
Grilled sourdough with burrata
Charcuterie selection with house-made pickles
Braised short rib with roasted root vegetables
Warm apple tart with crème anglaise
```

There's no limit on the number of items. Leave the field blank if the class doesn't have a set menu.

### Dietary Flags — Allergens Present

Check the allergens that are **present** in the menu. These appear on the class page and the upcoming menus listing as an "allergen" notice to help guests self-screen.

| Flag | When to check it |
|---|---|
| **Seafood** | Any fish (not shellfish) is used |
| **Shellfish** | Any shellfish (shrimp, crab, oysters, etc.) is used |
| **Gluten** | Wheat, barley, rye, or gluten-containing flour is used |
| **Dairy** | Butter, cream, milk, cheese, or other dairy is used |
| **Eggs** | Eggs used as an ingredient (not just incidental) |
| **Nuts** | Peanuts or ground nuts used |
| **Tree Nuts** | Almonds, walnuts, pecans, cashews, pistachios, etc. |
| **Pork** | Pork, bacon, lard, pancetta, or pork-derived products used |

> Check all that apply — multiple flags can be selected at once.

### Friendly For

Check the options that genuinely apply to the whole menu. These appear as positive "suitable for" tags on the class page.

| Flag | When to check it |
|---|---|
| **Pescatarian** | No meat — fish and shellfish may be present, no poultry or red meat |
| **Gluten Free** | The full class menu is free of gluten-containing ingredients |

> Be conservative with these flags. Only check "Gluten Free" if the **entire menu** is gluten free — not just most of it.

5. Click **Update** to save

---

## The Upcoming Menus Page

The `[dish_upcoming_menus]` shortcode generates a list of all upcoming classes that have a menu entered. It's sorted by date (soonest first).

**To add this shortcode to a page:**

1. Go to **Pages → [your menus page]**
2. In the page content, add: `[dish_upcoming_menus]`
3. Optionally, limit the number of results: `[dish_upcoming_menus limit="20"]`
4. Update/publish the page

**What the listing shows for each entry:**

- Class date and time
- Class title (linking to the class template page)
- Format and chef (with links)
- Menu items (the full list from the template)
- Dietary flags (allergens present)
- Friendly for tags

> Only classes with at least one menu item entered will appear in this listing. Classes with an empty menu field are excluded.

---

## Editing a Menu

To update a menu:

1. Open the **Class Template**
2. Scroll to **Class Menu**
3. Update the menu items and/or flags
4. Click **Update**

The changes take effect immediately on the template page, the class instance pages, and the upcoming menus listing.

---

## Common Gotchas

**"My class isn't appearing on the upcoming menus page"**
The menus listing only shows classes that have at least one item in the Menu Items field. Open the class template and make sure the menu items text area isn't blank.

**"I accidentally marked Gluten Free but there's dairy — should both be checked?"**
Yes — Dietary Flags (allergens) and Friendly For are independent. A class can be both Gluten Free and contain Dairy. Check Gluten Free under "Friendly For" and Dairy under "Dietary Flags".

**"The menu is showing on the listing page but not on the class template page"**
Check that the class template's featured image and description are set — the menu section appears below the main class description, so if the template page layout isn't rendering you may be looking at a template/theme issue rather than a plugin issue.
