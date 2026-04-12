---
name: premium-translate
description: Translates Simple History Premium plugin strings for one or multiple locales. Use when translating or updating premium plugin PO files.
argument-hint: '[locales]'
allowed-tools: Read, Edit, Write, Bash(cat:*), Bash(ls:*), Bash(npm run:*), Bash(npm:*), Bash(wp:*), Bash(msgfmt:*), Glob, Agent
---

# Translate Premium Plugin Strings

Translate the Simple History Premium plugin's PO files from English to specified locales.

## Premium Add-on Path

See `CLAUDE.local.md` for the premium add-on path. Read it first to determine the correct path. All commands below should be run from that directory.

## Workflow

### Setup

1. Read `CLAUDE.local.md` to find the premium add-on path
2. Read `translation-config.json` in the premium add-on to get configured locales
3. Check existing PO/POT files in the `languages/` directory
4. Check available npm scripts with `npm run`

### Determine Locales

Break up $ARGUMENTS into parts — each part is a locale.

If no locales are provided, use all locales defined in the translation config.

Confirm that each locale is valid and defined in the translation config. If not, show an error and exit.

Show the locales to be translated for confirmation.

### Step 1: Update Source Strings (once)

Run from the premium add-on path:

1. `npm run i18n:make-pot` — Update the POT file with latest strings from source code
2. `npm run i18n:update-po` — Update PO files for all locales

### Step 2: Translate Each Locale

**If translating 1-2 locales:** Translate sequentially in the main conversation.

**If translating 3+ locales:** Use batched parallel agents:

-   Process locales in batches of 5 at a time
-   For each batch, spawn parallel agents (one per locale) using the Agent tool
-   Wait for the batch to complete before starting the next batch

**Translation rules for each locale:**

1. Read the PO file for the locale
2. For each `msgid` (English string), provide an accurate translation in the corresponding `msgstr`
3. Keep WordPress-specific terms, HTML tags, and placeholders (`%s`, `%d`, `%1$s`) unchanged
4. Maintain proper PO file format
5. Use formal tone appropriate for software interface
6. Only translate user-facing strings, not developer strings or code
7. Be consistent with WordPress core translations for common terms

### Step 3: Compile (once, after all locales)

Run from the premium add-on path:

1. `npm run i18n:make-mo` — Compile MO files
2. `npm run i18n:make-php` — Compile PHP files

## Examples

-   `/premium-translate de_DE` — Translate to German
-   `/premium-translate sv_SE da_DK` — Translate to Swedish and Danish
-   `/premium-translate` — Translate all configured locales
