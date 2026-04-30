# Managing Recipes

This document walks the content team through creating and publishing recipes — from entering the content through to a live recipe page on the site.

---

## The Big Picture

Recipes live in their own dedicated area of the site under `/recipes/`. Each recipe is a standalone page with full structured content: ingredients, method steps, dietary information, and an optional link back to any classes that feature it.

Recipes are **supplementary to the classes** — they're content marketing, not booking content. A recipe page can exist without any class attached to it, and a class can run without a recipe attached.

| Piece | What it is | Lives in admin under |
|---|---|---|
| **Recipe Category** | Groups recipes by type (e.g. Mains, Pasta, Desserts) | Recipes → Categories |
| **Recipe** | The full recipe — ingredients, method, dietary info | Recipes |

---

## Creating a Recipe

1. Go to **Recipes → Add New**
2. Enter the **recipe title** as the post title (e.g. "Chicken Marsala with Handmade Tagliatelle")
3. Write a short **excerpt** — this appears on recipe cards and in search results. One or two sentences about the dish.
4. Write the **main body content** in the editor if you want introductory copy above the ingredients (optional — the recipe fields themselves handle the structured content)
5. Set a **featured image** — used as the hero on the recipe page and on the card in the archive
6. Assign a **Recipe Category** in the right-hand panel (e.g. "Mains")
7. Fill in the **Recipe Details** meta box (see tabs below)
8. **Publish**

---

## Recipe Details — Tab by Tab

The Recipe Details meta box sits below the main editor and contains six tabs.

---

### Tab 1 — Overview

The at-a-glance information shown at the top of the recipe page.

| Field | What to enter | Example |
|---|---|---|
| **Yield** | How much the recipe makes | "Serves 4", "Makes 24 pieces" |
| **Prep Time** | Minutes of preparation | `20` |
| **Cook Time** | Minutes of active cooking | `25` |
| **Total Time** | Total time including any resting/marinating | `60` (if it differs from prep + cook) |
| **Difficulty** | Easy / Medium / Advanced | Medium |
| **Cuisine** | Country or style of cuisine | "Italian", "French", "Japanese" |
| **Course** | Meal course | "Main", "Starter", "Dessert" |

> If **Total Time** is left at zero, the site auto-derives it as prep + cook. Only fill it in when there's significant resting, marinating, or chilling time that would otherwise be missed.

---

### Tab 2 — Ingredients

Ingredients are entered in a **sectioned list**. For simple recipes with no sections, leave the section heading blank — it won't appear on the page.

**To add an ingredient:**
1. Click **+ Add Ingredient** within the section
2. Fill in: **Qty** (amount), **Unit** (select from dropdown), **Ingredient** (name), **Note** (optional — e.g. "room temperature", "finely sliced")
3. Repeat for each ingredient

**Units available:** g, kg, oz, lb, ml, L, tsp, tbsp, cup, fl oz, cm, mm, inch, °C, °F — or **(none)** for whole items like eggs, vanilla beans, garlic cloves.

**For recipes with distinct sections** (e.g. "For the pasta dough" and "For the sauce"):
1. Click **+ Add Section**
2. Enter a section heading
3. Add ingredients to that section

**To reorder:** Drag the ≡ handle on the left of any row or section.

**To remove:** Click the **✕** on the right of any row or section.

> Ingredients are stored in structured fields — not free text. This is what powers the Recipe rich results in Google Search (ingredient list visible directly in search results).

---

### Tab 3 — Method

Method steps follow the same sectioned structure as ingredients. Steps are **automatically numbered within each section** — you don't need to type numbers.

**To add a step:**
1. Click **+ Add Step** within the section
2. Type the step text
3. Repeat

**For multi-section methods** (e.g. "Make the pasta" and "Make the sauce"):
1. Click **+ Add Section**
2. Enter a section heading
3. Add steps to that section

> Section numbers reset at 1 for each section — this matches how printed cookbooks number steps and is what Google's recipe schema expects.

---

### Tab 4 — Dietary

A checkbox grid of dietary flags. Tick all that apply.

| Flag | Meaning |
|---|---|
| Gluten Free | No gluten-containing ingredients |
| Dairy Free | No dairy |
| Vegetarian | No meat or fish |
| Vegan | No animal products at all |
| Nut Free | Safe for nut allergies |
| Egg Free | No eggs |
| Contains Shellfish | Contains shellfish (allergy alert) |
| Halal | Halal-certified ingredients |

These flags appear as badges on the recipe page and are included in the schema output.

---

### Tab 5 — Related Classes

Select any **Class Templates** that feature this recipe. This creates the link between the recipe and the class.

- Hold **Ctrl** (Windows) or **Cmd** (Mac) to select multiple classes
- Only published Class Templates are listed

**What this does:**
- On the **recipe page** — a "Featured In" block shows the linked classes with links to book
- On the **class page** — a "Recipes From This Class" block shows this recipe automatically

> The relationship is stored on the recipe, not on the class. You never need to touch the class to add a recipe to it — just save the recipe with the class selected here.

---

### Tab 6 — PDF

If a legacy PDF recipe exists, attach it here. A download link will appear at the bottom of the recipe page.

1. Click **Upload / Select PDF**
2. Choose the file from the Media Library (or upload a new one)
3. Save the recipe

This is optional and intended for the migration period while recipes are being entered in full.

---

## Recipe Categories

Categories group recipes and create filtered archive pages (e.g. `/recipes/mains/`).

**To create a category:**
1. Go to **Recipes → Categories**
2. Enter a **Name** (e.g. "Mains")
3. The **Slug** auto-fills — leave it as-is unless you need a custom URL segment
4. Click **Add New Recipe Category**

Assign categories to recipes from the **Categories** panel on the recipe edit screen (right-hand side).

> Every recipe should have at least one category. A recipe without a category will fall back to `/recipes/uncategorised/` in its URL.

---

## Recipe URLs

Recipe URLs follow this structure:

```
/recipes/                              All recipes (archive)
/recipes/mains/                        All recipes in the "Mains" category
/recipes/mains/chicken-marsala/        A single recipe
```

The URL is set automatically from the recipe title and its category. No manual input needed.

---

## Tips

- **Excerpt matters** — write a good one. It appears on cards, in Google search results, and in the recipe schema description.
- **Featured image matters too** — Google can show recipe images directly in search results. Use a good food photo.
- **Order is preserved** — ingredient and method order is saved exactly as entered. Drag to reorder before saving.
- **You can reorder sections** — drag the section header to move an entire section up or down, including all its rows.
- **Difficulty** — use "Easy" for recipes a home cook can follow comfortably, "Medium" for some technique required, "Advanced" for professional-level skill.
