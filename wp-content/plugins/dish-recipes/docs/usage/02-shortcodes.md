# Shortcodes Reference

All plugin shortcodes are prefixed `dish_`. They can be placed on any WordPress page or post. Each shortcode handles its own output — no wrapper markup is needed.

---

## `[dish_recipes]`

Renders the recipe archive grid with an optional category filter bar. This is the shortcode to place on the `/recipes/` page.

**Attributes:**

| Attribute | Default | Options | Description |
|---|---|---|---|
| `limit` | `-1` (all) | any integer | Max number of recipes to show |
| `category` | *(all)* | term slug | Filter to a single category (e.g. `mains`) |
| `orderby` | `title` | `title`, `date` | Sort recipes by title or publish date |
| `order` | `ASC` | `ASC`, `DESC` | Sort direction |

**Examples:**

```
[dish_recipes]
```

```
[dish_recipes category="mains"]
```

```
[dish_recipes limit="6" orderby="date" order="DESC"]
```

> Place this shortcode on the page with the slug `recipes`. That page acts as the recipe archive and must exist for category filter links to resolve correctly.

---

## `[dish_recipe id=""]`

Embeds a single recipe inline on any page. Useful for featuring a recipe on the homepage, a class page, or a blog post.

**Attributes:**

| Attribute | Required | Description |
|---|---|---|
| `id` | Yes | The post ID of the `dish_recipe` to embed |

**Example:**

```
[dish_recipe id="240"]
```

**Finding a recipe's ID:**
1. Go to **Recipes** in the admin
2. Hover over the recipe title — the post ID appears in the URL at the bottom of the browser (`post=240`)

> The embedded recipe renders using the same card template as the archive. It does not render the full single-recipe layout — for the full page, link to `/recipes/category/recipe-slug/` directly.
