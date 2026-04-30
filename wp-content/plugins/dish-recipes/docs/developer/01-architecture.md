# Plugin Architecture

A reference for developers working on or extending the Dish Recipes plugin.

---

## At a Glance

| Property | Value |
|---|---|
| Plugin file | `dish-recipes/dish-recipes.php` |
| PHP namespace root | `Dish\Recipes\` |
| Text domain | `dish-recipes` |
| Min PHP | 8.0 |
| Min WordPress | 6.4 |
| Version | 1.0.0 |
| No Composer | Autoloaded via custom PSR-4 loader in the plugin entry file |
| No jQuery | All frontend JS is vanilla |
| No Gutenberg | No block registration anywhere |
| No ACF | All fields are native post meta via a custom meta box |

---

## Directory Structure

```
dish-recipes/
├── dish-recipes.php             ← Entry file: constants, autoloader, activation hooks, bootstrap
├── assets/
│   ├── css/
│   │   ├── dish-recipes-admin.css     ← Admin-only styles (meta box tabs, repeater UI)
│   │   └── dish-recipes.css           ← Frontend styles (recipe single, archive, cards)
│   └── js/
│       └── dish-recipes-admin.js      ← Admin JS (tab switching, ingredient/method repeaters)
├── docs/                        ← This documentation (Markdown files)
│   ├── content-team/
│   ├── usage/
│   └── developer/
├── includes/
│   ├── Admin/                   ← Admin UI (meta box, list table columns)
│   ├── CPT/                     ← CPT and taxonomy registration
│   ├── Core/                    ← Bootstrap, Loader, Activator, Deactivator
│   ├── Data/                    ← Stateless repository class (all DB queries)
│   ├── Frontend/                ← Template loader, shortcodes, assets, related recipes
│   └── SEO/                     ← Recipe schema JSON-LD output
└── templates/                   ← PHP template files (theme-overridable)
    ├── single.php
    ├── archive.php
    ├── card.php
    └── related-recipes.php
```

---

## Autoloader

Custom PSR-4 autoloader — no Composer. Lives in `dish-recipes.php`, runs via `spl_autoload_register()`.

**Mapping rules:**

```
Namespace                            →  File path
────────────────────────────────────────────────────────────────────────
Dish\Recipes\Core\Plugin             →  includes/Core/class-plugin.php
Dish\Recipes\CPT\RecipePost          →  includes/CPT/class-recipe-post.php
Dish\Recipes\Data\RecipeRepository   →  includes/Data/class-recipe-repository.php
Dish\Recipes\SEO\RecipeSchema        →  includes/SEO/class-recipe-schema.php
```

**Rules in plain terms:**
1. Strip the `Dish\Recipes\` prefix
2. Split remaining namespace segments into a directory path
3. Convert the class name from CamelCase to `kebab-case`
4. Prepend `class-` (or `interface-` for interfaces)
5. Resolve against `includes/`

New classes are picked up automatically — no registration step required.

---

## Bootstrap

`dish-recipes.php` calls `add_action( 'plugins_loaded', [ 'Dish\\Recipes\\Core\\Plugin', 'run' ] )`.

`Plugin::run()` is a singleton that:
1. Instantiates the `Loader`
2. Calls `wire_hooks()` — instantiates all modules and registers their hooks with the Loader
3. Calls `Loader::run()` — bulk-registers all collected hooks with WordPress
4. Fires `do_action( 'dish_recipes_loaded' )`

To add a new module: instantiate it in `Plugin::wire_hooks()` and call its `register_hooks( $this->loader )` method (or add its hooks directly with `$this->loader->add_action()`).

---

## CPT and Taxonomy

Registered in `CPT\RecipePost::register()`, hooked to `init`.

| Type | Slug | Notes |
|---|---|---|
| Post type | `dish_recipe` | Public, has archive, supports title / editor / excerpt / thumbnail |
| Taxonomy | `dish_recipe_category` | Hierarchical, rewrite slug `recipes` — nests term archives under `/recipes/` |

**URL structure:**

```
/recipes/                              CPT archive
/recipes/mains/                        Taxonomy term archive
/recipes/mains/lemongrass-chicken/     Single recipe
```

Two explicit `top`-priority rewrite rules are registered in `add_archive_rewrite_rule()`:

```php
// Archive
'^recipes/?$'  →  index.php?post_type=dish_recipe

// Single — must be top priority to beat the taxonomy catch-all
'^recipes/([^/]+)/([^/]+)/?$'  →  index.php?dish_recipe_category=$matches[1]&dish_recipe=$matches[2]
```

The explicit single-recipe rule is required because the taxonomy catch-all `recipes/(.+?)/?$` would otherwise match two-segment paths and resolve them as term archives.

**Permalink filter:** `filter_post_type_link()` replaces `%dish_recipe_category%` in single permalinks with the recipe's primary category slug. Falls back to `uncategorised` if no category is assigned.

**Flush:** `Activator::activate()` sets an option flag; `Plugin::maybe_flush_rewrite_rules()` on `admin_init` performs the flush and clears the flag.

---

## Data Layer

`Data\RecipeRepository` — stateless static class. All DB queries live here. No business logic.

| Method | Description |
|---|---|
| `get( int $recipe_id )` | Single recipe by ID, returns `WP_Post\|null` |
| `get_by_template_id( int $template_id )` | All published recipes that reference a given class template ID |
| `get_by_category( string $slug, int $limit )` | Recipes filtered by category slug |
| `get_recent( int $limit )` | Most recently published recipes |
| `get_all( int $limit )` | All published recipes, ordered by title |
| `get_ingredients( int $recipe_id )` | Decoded ingredient sections array |
| `get_method( int $recipe_id )` | Decoded method sections array |
| `get_template_ids( int $recipe_id )` | Related class template IDs (filtered to existing published posts) |
| `get_ingredients_flat( int $recipe_id )` | Flat string array of formatted ingredients — used for schema output |

**Recipe ↔ class template relationship:**

The relationship is stored on the recipe only — `dish_recipe_template_ids` (JSON int array). `dish-events` stores nothing. `get_by_template_id()` queries by this meta field using a `LIKE` match against the JSON string (e.g. `"211"` within `["211","345"]`). `get_template_ids()` filters returned IDs to only those that still resolve to a published `dish_class_template` — orphaned IDs are silently dropped.

---

## Admin Meta Box

`Admin\RecipeMetaBox` — tabbed meta box on the `dish_recipe` edit screen.

| Tab | Fields |
|---|---|
| Overview | Yield, prep time, cook time, total time, difficulty, cuisine, course, notes |
| Ingredients | Sectioned repeater — sections with optional heading, each containing rows of qty / unit / item / note |
| Method | Sectioned step list — sections with optional heading, each containing ordered step rows |
| Dietary | Checkbox grid — 8 flags (gluten-free, dairy-free, vegetarian, vegan, nut-free, egg-free, shellfish, halal) |
| Related Classes | Multi-select of published `dish_class_template` posts — stores as `dish_recipe_template_ids` |
| PDF | Media library picker for legacy PDF attachment |

All data is saved to standard post meta on `save_post_dish_recipe`. Structured data (ingredients, method, dietary flags) is serialised as JSON strings.

---

## SEO — Schema Output

`SEO\RecipeSchema::output()` is hooked to `wp_head`. It outputs a `Recipe` JSON-LD block only on `is_singular('dish_recipe')` pages.

**Key schema properties:**

| Schema property | Source |
|---|---|
| `name` | Post title |
| `description` | Post excerpt |
| `image` | Featured image URL |
| `prepTime` / `cookTime` / `totalTime` | Post meta — formatted as ISO 8601 duration (`PT20M`) |
| `recipeYield` | `dish_recipe_yield` meta |
| `recipeCategory` | Primary `dish_recipe_category` term name |
| `recipeCuisine` | `dish_recipe_cuisine` meta |
| `recipeIngredient` | Flat string array from `RecipeRepository::get_ingredients_flat()` |
| `recipeInstructions` | `HowToSection` → `HowToStep` array from method meta |
| `suitableForDiet` | Mapped from dietary flags to `schema.org` diet URLs |

**`recipeInstructions` format:** When the recipe has a single method section (no heading), a flat array of `HowToStep` objects is emitted. When multiple sections exist, `HowToSection` wrappers are used. This matches Google's preferred format for both simple and multi-part recipes.

---

## Frontend

### Template Loader

`Frontend\TemplateLoader` hooks into `template_include` and serves plugin templates for `dish_recipe` requests. Theme overrides take precedence.

| Request type | Plugin default | Theme override location |
|---|---|---|
| Single recipe | `templates/single.php` | `{theme}/dish-recipes/single.php` |
| Archive / category | `templates/archive.php` | `{theme}/dish-recipes/archive.php` |

`load_template( string $filename, array $data )` is the public method used by all templates to include partials (`card.php`, `related-recipes.php`). Variables in `$data` are extracted into template scope.

### Assets

`Frontend\Assets` enqueues `dish-recipes.css` on all `dish_recipe` page types (single, archive, taxonomy). Admin assets are enqueued separately by `RecipeMetaBox::enqueue_assets()` on the recipe edit screen only.

### Shortcodes

`Frontend\Shortcodes` registers `[dish_recipes]` and `[dish_recipe id=""]`. See `docs/usage/02-shortcodes.md` for attribute reference.

---

## Cross-Plugin Integration

The recipe–class relationship is entirely owned by `dish-recipes`. `dish-events` has no knowledge of recipes.

**Recipe single page → class template pages:**
- `RecipeRepository::get_template_ids( $recipe_id )` returns the linked class template IDs
- The single template renders a "Featured In" block with links to each class
- If `dish-events` is not active or a linked template has been deleted, those entries are silently skipped

**Class template page → recipe:**
- `dish-events` fires `do_action( 'dish_after_class_template_content' )` in its class template single
- `Plugin::wire_hooks()` attaches `Frontend\RelatedRecipes::render()` to this hook — but **only** when `DISH_EVENTS_VERSION` is defined
- `render()` calls `RecipeRepository::get_by_template_id( get_the_ID() )` and loads `related-recipes.php`
- If `dish-recipes` is deactivated, the action fires with no listeners and the class page is unaffected

**Dietary flags:**
- `RecipeMetaBox::DIETARY_FLAGS` maintains its own copy of the flag map
- Keys are intentionally kept in sync with `Dish\Events\Admin\MenuMetaBox::DIETARY_FLAGS` — update both if flags change

---

## Extending

### Adding a hook listener from another plugin

```php
// Fire after dish-recipes is fully loaded
add_action( 'dish_recipes_loaded', function() {
    // dish-recipes classes and hooks are available here
} );
```

### Overriding templates in the theme

Copy any template from `dish-recipes/templates/` to `{theme}/dish-recipes/` and edit freely. The template loader checks the theme path first on every request. No other configuration needed.

### Adding a new repository method

Add a `public static function` to `Data\RecipeRepository`. No registration required — it's autoloaded and called statically wherever needed.
