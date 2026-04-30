#!/usr/bin/env python3
"""
PDF Recipe Extractor — Dish Recipes
====================================
Reads all recipe PDFs from the Recipes docs folder, parses them into structured
JSON, and writes recipes-extracted.json for the WP-CLI importer to consume.

Usage:
    python3 tools/extract-recipes.py

Output:
    tools/recipes-extracted.json

Requirements:
    pip3 install pdfminer.six --break-system-packages
"""

import os
import re
import json
from pathlib import Path
from pdfminer.high_level import extract_text

# ---------------------------------------------------------------------------
# Paths
# ---------------------------------------------------------------------------

SCRIPT_DIR  = Path(__file__).parent
PLUGIN_DIR  = SCRIPT_DIR.parent
WC_DIR      = PLUGIN_DIR.parent          # wp-content/plugins  → wp-content
WC_DIR      = WC_DIR.parent             # wp-content
RECIPES_DIR = WC_DIR / 'themes' / 'dish' / 'docs' / 'Recipes'
OUTPUT_FILE = SCRIPT_DIR / 'recipes-extracted.json'

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

FOOTER_RE   = re.compile(r'587 College Street', re.IGNORECASE)
YIELD_RE    = re.compile(r'^(Serves?\s+\S.*|Makes?\s+\S.*)$', re.IGNORECASE)
HEADING_RE       = re.compile(r'^[A-Za-z\s\+\&,\'\-]+:$')   # "For the pasta:", "Carrot Cake:"
NUMBERED_RE      = re.compile(r'^(\d+)\.\s+(.+)')   # "1. Step text"
NUMBERED_LONE_RE = re.compile(r'^(\d+)\.$')           # "1." alone on a line


def clean(text: str) -> str:
    """Normalise whitespace and encoding artefacts."""
    text = text.replace('\xa0', ' ')
    text = text.replace('\r\n', '\n').replace('\r', '\n')
    # Collapse runs of spaces but preserve newlines
    text = re.sub(r'[^\S\n]+', ' ', text)

    # Some PDFs use spaced/kerned characters for headers, e.g. "INGRE D IENTS".
    # Collapse those specific patterns to their clean equivalents.
    kerned = [
        ( r'I\s*N\s*G\s*R\s*E\s*D?\s*I?\s*E?\s*N?\s*T?\s*S',  'INGREDIENTS' ),
        ( r'M\s*E\s*T\s*H\s*O\s*D',                             'METHOD'      ),
        ( r'C\s*L\s*A\s*S\s*S\s+R\s*E\s*C\s*I\s*P?\s*E',       'CLASS RECIPE' ),
    ]
    for pattern, replacement in kerned:
        text = re.sub(pattern, replacement, text, flags=re.IGNORECASE)

    return text


def is_heading(line: str) -> bool:
    """True if the line looks like a section heading (ends with colon, short)."""
    s = line.strip()
    return (
        s.endswith(':')
        and len(s) <= 80
        and not re.match(r'^[\d½¼¾⅓⅔\-]', s)
        and not re.match(r'^\d+\.', s)
    )


def is_method_content(line: str) -> bool:
    """True if the line is definitely method (numbered step or long prose)."""
    s = line.strip()
    if NUMBERED_RE.match(s):
        return True
    if len(s) > 90:
        return True
    return False


# ---------------------------------------------------------------------------
# Content splitter — ingredients vs method
# ---------------------------------------------------------------------------

def split_ingredients_method(lines: list) -> tuple:
    """
    Split content lines into (ingredient_lines, method_lines).

    Strategy:
      Pass 1 — find the first numbered step (1.) and walk back to its
               preceding section heading. That heading starts method.
      Pass 2 — find the first long prose line (>90 chars) after a heading;
               that heading starts method.
      Fallback — treat everything as ingredients (warns in output).
    """
    split_at = None

    # Pass 1: numbered step — handles both "1. Text" and "1." alone on a line
    for i, line in enumerate(lines):
        s = line.strip()
        if NUMBERED_RE.match(s) or NUMBERED_LONE_RE.match(s):
            # Walk back to find the nearest preceding heading
            split_at = i
            for j in range(i - 1, -1, -1):
                if lines[j].strip():
                    if is_heading(lines[j]):
                        split_at = j
                    break
            break

    # Pass 2: long prose following a heading
    if split_at is None:
        last_heading_idx = None
        for i, line in enumerate(lines):
            s = line.strip()
            if is_heading(s):
                last_heading_idx = i
            elif len(s) > 90 and last_heading_idx is not None:
                split_at = last_heading_idx
                break

    if split_at is None:
        return lines, []

    return lines[:split_at], lines[split_at:]


# ---------------------------------------------------------------------------
# Ingredient parser
# ---------------------------------------------------------------------------

def parse_ingredients(lines: list) -> list:
    """
    Parse ingredient lines into sections.
    Each section: { heading, items: [ {qty, unit, item, note} ] }
    Ingredient text is kept as-is in the `item` field for reliable import.
    """
    sections   = []
    heading    = ''
    items      = []
    buf        = []   # accumulates wrapped ingredient lines

    def flush_item():
        nonlocal buf
        text = ' '.join(buf).strip()
        buf  = []
        if text:
            items.append({'qty': '', 'unit': '', 'item': text, 'note': ''})

    def flush_section():
        nonlocal heading, items
        flush_item()
        if items:
            sections.append({'heading': heading, 'items': items})
        heading = ''
        items   = []

    for line in lines:
        s = line.strip()
        if not s:
            flush_item()
            continue

        if is_heading(s):
            flush_section()
            heading = s.rstrip(':').strip()
            continue

        # Ingredient item — may be wrapped across lines.
        # A new item starts when the line begins with a quantity token
        # (digit, fraction char, or common words like "pinch", "handful").
        starts_new = bool(re.match(
            r'^[\d½¼¾⅓⅔]|^(pinch|handful|bunch|slice|sheet|sprig|clove|dash)\b',
            s, re.IGNORECASE
        ))

        if starts_new and buf:
            flush_item()

        buf.append(s)

    flush_section()
    return sections


# ---------------------------------------------------------------------------
# Method parser
# ---------------------------------------------------------------------------

def parse_method(lines: list) -> list:
    """
    Parse method lines into sections.
    Each section: { heading, steps: [ {step, text} ] }
    Handles both numbered steps and prose paragraphs.
    """
    sections = []
    heading  = ''
    steps    = []
    step_n   = 1
    buf      = []   # accumulates current step/paragraph text

    def flush_step():
        nonlocal buf, step_n
        text = ' '.join(buf).strip()
        buf  = []
        if text:
            steps.append({'step': step_n, 'text': text})
            step_n += 1

    def flush_section():
        nonlocal heading, steps, step_n
        flush_step()
        if steps:
            sections.append({'heading': heading, 'steps': steps})
        heading = ''
        steps   = []
        step_n  = 1

    for line in lines:
        s = line.strip()
        if not s:
            flush_step()
            continue

        if is_heading(s):
            flush_section()
            heading = s.rstrip(':').strip()
            continue

        m = NUMBERED_RE.match(s)
        if m:
            flush_step()
            buf.append(m.group(2).strip())
            continue

        # Lone step number ("1." with content on the next line) — flush and start new step
        if NUMBERED_LONE_RE.match(s):
            flush_step()
            continue

        # Prose line — accumulate into current step
        buf.append(s)

    flush_section()
    return sections


# ---------------------------------------------------------------------------
# Single PDF parser
# ---------------------------------------------------------------------------

def parse_pdf(pdf_path: Path, category: str) -> dict | None:
    raw  = extract_text(str(pdf_path))
    text = clean(raw)

    # Strip footer
    text = FOOTER_RE.split(text)[0]

    lines = text.split('\n')
    lines = [l.rstrip() for l in lines]

    # --- Title ---
    title    = ''
    title_idx = 0
    for i, line in enumerate(lines):
        if line.strip().upper() == 'CLASS RECIPE':
            for j in range(i + 1, len(lines)):
                if lines[j].strip():
                    title     = lines[j].strip()
                    title_idx = j
                    break
            break

    if not title:
        # Fallback: first non-empty, non-header line
        for line in lines:
            s = line.strip()
            if s and s.upper() not in ('CLASS RECIPE', 'INGREDIENTS', 'METHOD'):
                title = s
                break

    if not title:
        print(f'    ✗ Could not extract title from {pdf_path.name}')
        return None

    # --- Yield ---
    yield_str = ''
    for line in lines:
        m = YIELD_RE.match(line.strip())
        if m:
            yield_str = m.group(0)
            break

    # --- Find INGREDIENTS / METHOD header positions ---
    ing_idx    = None
    method_idx = None
    for i, line in enumerate(lines):
        s = line.strip().upper().rstrip()
        if s == 'INGREDIENTS':
            ing_idx = i
        elif s in ('METHOD', 'METHOD '):
            method_idx = i

    if ing_idx is None:
        print(f'    ✗ No INGREDIENTS header in {pdf_path.name}')
        return None

    # Content starts after the last of the two headers
    start = max(x for x in [ing_idx, method_idx] if x is not None) + 1
    content_lines = lines[start:]

    # --- Split & parse ---
    ing_lines, meth_lines = split_ingredients_method(content_lines)
    ingredients = parse_ingredients(ing_lines)
    method      = parse_method(meth_lines)

    return {
        'title':       title,
        'category':    category,
        'yield':       yield_str,
        'ingredients': ingredients,
        'method':      method,
        'source_file': f'{category}/{pdf_path.name}',
    }


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    if not RECIPES_DIR.exists():
        print(f'ERROR: Recipes directory not found:\n  {RECIPES_DIR}')
        return

    all_recipes = []
    errors      = []
    seen_titles = set()   # deduplicate by title

    for cat_dir in sorted(RECIPES_DIR.iterdir()):
        if not cat_dir.is_dir() or cat_dir.name.startswith('.'):
            continue

        category = cat_dir.name
        pdfs     = sorted(cat_dir.glob('*.pdf'))

        print(f'\n{category} ({len(pdfs)} PDFs)')

        for pdf_path in pdfs:
            print(f'  Parsing: {pdf_path.name}')
            try:
                recipe = parse_pdf(pdf_path, category)
                if recipe is None:
                    errors.append(str(pdf_path))
                    continue

                if recipe['title'] in seen_titles:
                    print(f'    — DUPLICATE title, skipping: {recipe["title"]}')
                    continue

                seen_titles.add(recipe['title'])
                all_recipes.append(recipe)
                ing_count = sum(len(s['items']) for s in recipe['ingredients'])
                stp_count = sum(len(s['steps']) for s in recipe['method'])
                print(f'    ✓ {recipe["title"]}  '
                      f'({ing_count} ingredients, {stp_count} steps)')

            except Exception as e:
                print(f'    ✗ ERROR: {e}')
                errors.append(str(pdf_path))

    with open(OUTPUT_FILE, 'w', encoding='utf-8') as f:
        json.dump(all_recipes, f, ensure_ascii=False, indent=2)

    print(f'\n{"=" * 60}')
    print(f'Extracted : {len(all_recipes)} recipes')
    print(f'Errors    : {len(errors)}')
    print(f'Output    : {OUTPUT_FILE}')

    if errors:
        print('\nFailed files:')
        for e in errors:
            print(f'  {e}')

    print('\nReview recipes-extracted.json before running the importer.')


if __name__ == '__main__':
    main()
