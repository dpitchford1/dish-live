#!/usr/bin/env python3
"""
Generate a recipe-descriptions.md file from recipes-extracted.json.

Each recipe gets a template-built description drawn from its title,
category, yield, and ingredients. The output Markdown file is designed
to be handed off for manual rewriting — paste it into ChatGPT, Claude,
or any AI tool, ask it to rewrite every description in a consistent
voice, then run import-descriptions.php to push the text back into WP.

Usage:
    python3 tools/generate-descriptions.py

Output:
    tools/recipe-descriptions.md
"""

import json
import re
import os
import textwrap
from pathlib import Path

# ── Paths ────────────────────────────────────────────────────────────────────
SCRIPT_DIR  = Path(__file__).parent
JSON_PATH   = SCRIPT_DIR / "recipes-extracted.json"
OUTPUT_PATH = SCRIPT_DIR / "recipe-descriptions.md"

# ── Helpers ──────────────────────────────────────────────────────────────────

# Words to ignore when picking "key" ingredients for the description
FILLER_WORDS = {
    "salt", "pepper", "water", "oil", "olive oil", "butter", "sugar",
    "flour", "garlic", "onion", "egg", "eggs", "milk", "cream",
    "vegetable oil", "canola oil", "black pepper", "white pepper",
    "kosher salt", "sea salt", "baking powder", "baking soda",
}

CATEGORY_PHRASES = {
    "Mains":      "main course",
    "Starters":   "starter",
    "Desserts":   "dessert",
    "Sides":      "side dish",
    "Cocktails":  "cocktail",
    "Sauces":     "sauce",
    "Salads":     "salad",
    "Soups":      "soup",
    "Brunch":     "brunch dish",
    "Pasta":      "pasta dish",
    "Seafood":    "seafood dish",
    "Breakfast":  "breakfast",
}


def flatten_ingredients(ingredient_groups: list) -> list[str]:
    """Return a flat list of ingredient item strings from all groups."""
    items = []
    for group in ingredient_groups:
        for ing in group.get("items", []):
            raw = ing.get("item", "").strip()
            if raw:
                items.append(raw)
    return items


def is_real_ingredient(text: str) -> bool:
    """Return True if the line looks like an actual ingredient rather than
    a method step, contact info, or noise."""
    # Skip long lines (likely method text that leaked into ingredients)
    if len(text) > 80:
        return False
    # Skip lines that look like sentences / method steps
    if re.search(r'\b(in a|heat|add|stir|combine|remove|serve|fill|strain|mash)\b', text, re.I):
        return False
    # Skip address / contact info
    if re.search(r'(street|college|toronto|416|dish\s?cooking)', text, re.I):
        return False
    return True


def pick_key_ingredients(items: list[str], limit: int = 4) -> list[str]:
    """Pick the most interesting ingredients for the description snippet."""
    picked = []
    for raw in items:
        if not is_real_ingredient(raw):
            continue
        # Strip leading quantities so we get just the ingredient name
        name = re.sub(r'^[\d/¼½¾⅓⅔⅛⅜⅝⅞\s\.\-]+', '', raw)
        name = re.sub(r'\(.*?\)', '', name).strip().lower()
        name = re.sub(r',.*$', '', name).strip()   # drop "chicken, skinless"
        if not name or name in FILLER_WORDS:
            continue
        if name not in picked:
            picked.append(name)
        if len(picked) >= limit:
            break
    return picked


def build_description(recipe: dict) -> str:
    """Build a template description string from recipe data."""
    title    = recipe.get("title", "").strip()
    category = recipe.get("category", "").strip()
    yield_   = recipe.get("yield", "").strip()
    ings     = flatten_ingredients(recipe.get("ingredients", []))

    cat_phrase = CATEGORY_PHRASES.get(category, "dish")
    key_ings   = pick_key_ingredients(ings)

    # Opening clause — vary slightly by category
    if category == "Cocktails":
        opener = f"A refreshing {cat_phrase}"
    elif category == "Desserts":
        opener = f"An indulgent {cat_phrase}"
    elif category in ("Soups", "Starters"):
        opener = f"A delicious {cat_phrase}"
    else:
        opener = f"A classic {cat_phrase}"

    # Ingredient clause
    if key_ings:
        if len(key_ings) == 1:
            ing_clause = f"featuring {key_ings[0]}"
        elif len(key_ings) == 2:
            ing_clause = f"featuring {key_ings[0]} and {key_ings[1]}"
        else:
            ing_clause = f"featuring {', '.join(key_ings[:-1])}, and {key_ings[-1]}"
    else:
        ing_clause = "from the Dish Cooking Studio kitchen"

    # Yield clause
    yield_clause = f" Serves {yield_}." if yield_ else ""

    return f"{opener} {ing_clause}.{yield_clause}"


# ── Main ─────────────────────────────────────────────────────────────────────

def main():
    with open(JSON_PATH, encoding="utf-8") as f:
        recipes = json.load(f)

    recipes.sort(key=lambda r: (r.get("category", ""), r.get("title", "")))

    lines = [
        "# Recipe Descriptions",
        "",
        "Each recipe below has a template-generated description.",
        "**Rewrite every description** in an engaging, appetising voice (1–2 sentences).",
        "Keep the `### Title` headings exactly as-is — they are used as the import key.",
        "Do not add or remove any `---` separators.",
        "",
        "---",
        "",
    ]

    for recipe in recipes:
        title = recipe.get("title", "Untitled").strip()
        desc  = build_description(recipe)

        lines.append(f"### {title}")
        lines.append("")
        lines.append(desc)
        lines.append("")
        lines.append("---")
        lines.append("")

    output = "\n".join(lines)
    OUTPUT_PATH.write_text(output, encoding="utf-8")

    print(f"✓  Written {len(recipes)} descriptions → {OUTPUT_PATH}")
    print()
    print("Next steps:")
    print("  1. Open recipe-descriptions.md and rewrite the descriptions")
    print("     (or paste into ChatGPT: 'Rewrite each description in 1–2")
    print("      engaging sentences. Keep ### headings and --- separators.')")
    print("  2. Save the file.")
    print("  3. Run:  wp eval-file wp-content/plugins/dish-recipes/tools/import-descriptions.php")


if __name__ == "__main__":
    main()
