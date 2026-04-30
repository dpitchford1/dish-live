# Data Model

Reference for all post meta fields, taxonomy data, and structured data formats used by the `dish_recipe` CPT.

---

## Post Type: `dish_recipe`

| Property | Value |
|---|---|
| Slug | `dish_recipe` |
| Public | `true` |
| Has archive | `true` — at `/recipes/` |
| Supports | `title`, `editor`, `excerpt`, `thumbnail`, `revisions` |
| Rewrite slug | `recipes/%dish_recipe_category%` |

---

## Post Meta Fields

| Key | Type | Description |
|---|---|---|
| `dish_recipe_yield` | `string` | e.g. "Serves 4", "Makes 24 pieces" |
| `dish_recipe_prep_time` | `int` | Preparation time in minutes |
| `dish_recipe_cook_time` | `int` | Cooking time in minutes |
| `dish_recipe_total_time` | `int` | Total time in minutes — auto-derived as prep+cook if `0` |
| `dish_recipe_difficulty` | `string` | `easy`, `medium`, or `advanced` |
| `dish_recipe_cuisine` | `string` | e.g. "Italian", "French" |
| `dish_recipe_course` | `string` | e.g. "Main", "Starter", "Dessert" |
| `dish_recipe_notes` | `string` | Free-text tips, variations, wine pairing |
| `dish_recipe_ingredients` | `JSON` | Sectioned ingredient list (see structure below) |
| `dish_recipe_method` | `JSON` | Sectioned method steps (see structure below) |
| `dish_recipe_dietary_flags` | `JSON` | Array of dietary flag keys (see flags table below) |
| `dish_recipe_template_ids` | `JSON` | Array of related `dish_class_template` post IDs (string-cast ints) |
| `dish_recipe_pdf_id` | `int` | Attachment ID of legacy PDF — absent if no PDF |

---

## Ingredient Structure

Stored as a JSON-encoded array of sections. Each section has an optional heading and an array of ingredient rows.

```json
[
  {
    "heading": "For the sauce",
    "items": [
      { "qty": "200", "unit": "ml",   "item": "fish stock",       "note": "warm" },
      { "qty": "2",   "unit": "tbsp", "item": "unsalted butter",  "note": "" }
    ]
  },
  {
    "heading": "",
    "items": [
      { "qty": "3",   "unit": "",     "item": "eggs",             "note": "room temperature" }
    ]
  }
]
```

**Field notes:**
- `heading` — empty string for unsectioned recipes; the template omits the heading element when empty
- `unit` — always one of the pre-defined unit keys (see unit table below), or empty string for whole-item quantities
- `note` — optional clarification; rendered in parentheses or as a secondary label in templates

**Flat representation for schema output** — `RecipeRepository::get_ingredients_flat()` returns a `string[]`:

```
"200ml fish stock, warm"
"2 tbsp unsalted butter"
"3 eggs, room temperature"
```

Format: `"{qty}{unit} {item}"` with note appended as `", {note}"` when present. Unit label (e.g. "tbsp") is used rather than the key. Google's `recipeIngredient` schema property expects this flat format.

---

## Method Structure

Stored as a JSON-encoded array of sections. Each section has an optional heading and an array of steps. Steps are numbered per-section (not globally).

```json
[
  {
    "heading": "Make the pasta",
    "steps": [
      { "step": 1, "text": "Combine flour and eggs in a bowl. Mix to a rough dough." },
      { "step": 2, "text": "Knead for 8 minutes until smooth and elastic. Wrap and rest for 30 minutes." }
    ]
  },
  {
    "heading": "Make the sauce",
    "steps": [
      { "step": 1, "text": "Melt butter in a wide pan over medium heat until foaming." },
      { "step": 2, "text": "Add marsala and reduce by half." }
    ]
  }
]
```

**Schema output:** Each section maps to a `HowToSection` containing `HowToStep` objects. When only one section exists (regardless of heading), a flat `HowToStep` array is output directly — no `HowToSection` wrapper.

---

## Dietary Flags

Stored as a JSON-encoded string array, e.g. `["gluten-free","vegan"]`.

| Key | Label |
|---|---|
| `gluten-free` | Gluten Free |
| `dairy-free` | Dairy Free |
| `vegetarian` | Vegetarian |
| `vegan` | Vegan |
| `nut-free` | Nut Free |
| `egg-free` | Egg Free |
| `shellfish` | Contains Shellfish |
| `halal` | Halal |

These keys are intentionally kept in sync with `Dish\Events\Admin\MenuMetaBox::DIETARY_FLAGS`. If flags are added or changed, update both plugins.

**Schema mapping** — dietary flags that have a `schema.org` diet equivalent are output as `suitableForDiet`:

| Key | Schema.org value |
|---|---|
| `gluten-free` | `https://schema.org/GlutenFreeDiet` |
| `dairy-free` | `https://schema.org/DiabeticDiet` *(closest available)* |
| `vegetarian` | `https://schema.org/VegetarianDiet` |
| `vegan` | `https://schema.org/VeganDiet` |
| `halal` | `https://schema.org/HalalDiet` |

---

## Unit Keys

Defined as `RecipeMetaBox::UNIT_OPTIONS`. Stored as keys; labels are used in template and schema output.

| Group | Key | Label |
|---|---|---|
| Weight | `g` | g |
| | `kg` | kg |
| | `oz` | oz |
| | `lb` | lb |
| Volume | `ml` | ml |
| | `l` | L |
| | `tsp` | tsp |
| | `tbsp` | tbsp |
| | `cup` | cup |
| | `fl_oz` | fl oz |
| Length | `cm` | cm |
| | `mm` | mm |
| | `inch` | inch |
| Temperature | `c` | °C |
| | `f` | °F |
| Count / Other | *(empty string)* | (none) |
| | `pinch` | pinch |
| | `handful` | handful |
| | `bunch` | bunch |
| | `slice` | slice |
| | `sheet` | sheet |
| | `sprig` | sprig |
| | `clove` | clove |

---

## Taxonomy: `dish_recipe_category`

| Property | Value |
|---|---|
| Hierarchical | `true` (like categories) |
| Rewrite slug | `recipes` |
| Archive URL | `/recipes/{term-slug}/` |
| Show in REST | `false` |
| Show admin column | `true` |

Category slugs become the first URL segment after `/recipes/` in single recipe permalinks. A recipe without a category falls back to the slug `uncategorised`.

---

## Recipe ↔ Class Template Relationship

The relationship is stored exclusively on the recipe as `dish_recipe_template_ids`. `dish-events` stores nothing on the class template side.

```
dish_recipe (post 240)
  └── dish_recipe_template_ids = ["211", "345"]
                                    │        │
                                    ▼        ▼
                           dish_class_template  dish_class_template
                                (post 211)           (post 345)
```

**Looking up recipes for a class template** (`RecipeRepository::get_by_template_id( 211 )`):
- Queries `dish_recipe` posts where `dish_recipe_template_ids LIKE '"211"'`
- The LIKE match finds `"211"` as an element within the JSON array string

**Looking up class templates for a recipe** (`RecipeRepository::get_template_ids( 240 )`):
- Decodes the JSON array from the recipe's meta
- Filters to IDs that still resolve to a published `dish_class_template` post
- Orphaned IDs (deleted templates) are silently dropped
