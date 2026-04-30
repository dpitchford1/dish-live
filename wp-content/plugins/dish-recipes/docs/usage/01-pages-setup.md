# Pages Setup

This document covers the WordPress pages required by the Dish Recipes plugin and how to configure them.

---

## Required Pages

The plugin needs one page created manually in **Pages → Add New**. It does not create pages automatically on activation.

| Page | Suggested title | Suggested slug | Shortcode to place |
|---|---|---|---|
| Recipe archive | Recipes | `/recipes` | `[dish_recipes]` |

> **Important:** The page slug must be `recipes` to match the plugin's URL structure. If you use a different slug, the archive page and the recipe URLs will conflict.

---

## Setting Up the Recipes Page

1. Go to **Pages → Add New**
2. Set the title to **Recipes**
3. Set the slug to `recipes` (it will auto-fill from the title — leave it as-is)
4. In the page content area, add the shortcode: `[dish_recipes]`
5. Set the page template to **Default** (no special template needed)
6. **Publish**

That's it. The shortcode handles all output — the page itself needs no other content.

---

## Navigation

Add the Recipes page to your navigation menu via **Appearance → Menus** the same way as any other page. The recipe archive and category pages are also available as menu items under **Custom Links** if you want to link to a specific category directly (e.g. `/recipes/mains/`).

---

## URL Structure Reference

| URL | What it shows |
|---|---|
| `/recipes/` | All published recipes (archive grid) |
| `/recipes/mains/` | Recipes filtered to the "Mains" category |
| `/recipes/pasta/` | Recipes filtered to the "Pasta" category |
| `/recipes/mains/chicken-marsala/` | A single recipe page |

Category archive URLs are created automatically when a Recipe Category is added and a recipe is published in it. No page needs to be created for each category.
