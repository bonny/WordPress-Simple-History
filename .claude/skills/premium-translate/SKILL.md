---
name: premium-translate
description: Translates Simple History Premium plugin strings for one or multiple locales. Use when translating or updating premium plugin PO files.
argument-hint: '[locales]'
allowed-tools: Read, Edit, Write, Bash(cat:*), Bash(ls:*), Bash(grep:*), Bash(echo:*), Bash(npm:*), Bash(wp:*), Bash(msgfmt:*), Bash(msgunfmt:*), Bash(msgattrib:*), Bash(unzip:*), Bash(for:*), Glob, Agent
disable-model-invocation: true
---

# Translate Premium Plugin Strings

Translate the Simple History Premium plugin's PO files from English to specified locales.

## Premium Add-on Path

See `CLAUDE.local.md` for the premium add-on path. Read it first to determine
the correct path.

**Do not `cd` into it.** This skill runs from the core plugin repository, and
every command below takes the premium path explicitly. Set it once and reuse
it:

```bash
PREMIUM=/path/from/CLAUDE.local.md/simple-history-premium
```

Two reasons this matters. `cd` is not in this skill's `allowed-tools`, so
changing directory triggers permission prompts on every step. More importantly,
`i18n:make-pot` runs `wp i18n make-pot .` — that `.` is resolved against the
working directory, so running it from the wrong place scans the wrong plugin's
source. `npm --prefix` sets the script's working directory to the prefix, which
removes the ambiguity entirely.

All the tooling is on the host — `wp`, `msgfmt`, `msgunfmt` — so no container
is needed.

## Workflow

### Setup

1. Read `CLAUDE.local.md` to find the premium add-on path and set `$PREMIUM`
2. Read `$PREMIUM/translation-config.json` to get configured locales
3. Check existing PO/POT files in `$PREMIUM/languages/`
4. Check available npm scripts with `npm --prefix "$PREMIUM" run`

### Determine Locales

Break up $ARGUMENTS into parts — each part is a locale.

If no locales are provided, use all locales defined in the translation config.

Confirm that each locale is valid and defined in the translation config. If not, show an error and exit.

Show the locales to be translated for confirmation.

### Step 1: Update Source Strings (once)

Record the string count **before** regenerating, so there is something to
compare against:

```bash
POT="$PREMIUM/languages/simple-history-add-on.pot"
BEFORE=$(grep -c '^msgid "' "$POT")

# Update the POT file with the latest strings from source code.
npm --prefix "$PREMIUM" run i18n:make-pot

echo "msgids: $BEFORE -> $(grep -c '^msgid "' "$POT")"
```

The count must not go _down_, and if strings were added to the source it must
go up. An unchanged or shrinking count means `make-pot` scanned the wrong tree
— check `$PREMIUM` before going further, because the next command merges the
result into all 22 catalogues.

Match `'^msgid "'` and not `'^msgid'`: the latter also counts every
`msgid_plural` line and inflates the number.

Then merge the new strings into every locale's PO file:

```bash
npm --prefix "$PREMIUM" run i18n:update-po
```

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

#### Escaping inside `msgstr`

Double quotes and backslashes must be backslash-escaped. An unescaped quote
terminates the string early and makes the whole file unparseable from that
line on.

```po
# Wrong — syntax error, and everything after it fails to compile.
msgstr "Miejsce docelowe "%s" nie znalezione."

# Right.
msgstr "Miejsce docelowe \"%s\" nie znalezione."
```

This is not hypothetical: `pl_PL.po:2327` shipped exactly this defect.

#### Plural forms

Every locale needs a correct `Plural-Forms:` header, and every entry with a
`msgid_plural` needs all `msgstr[0..N-1]` filled in for that locale's
`nplurals`. Leaving the header off is the single most common failure here —
10 of 22 catalogues were missing it, so WordPress silently fell back to
`nplurals=2; plural=(n != 1);`, which is wrong for every CJK locale.

| Locales                                                                              | Header                                                                                                            |
| ------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| `ja` `ko_KR` `zh_CN` `zh_TW`                                                         | `nplurals=1; plural=0;`                                                                                           |
| `de_DE` `es_ES` `it_IT` `pt_PT` `nl_NL` `sv_SE` `da_DK` `nb_NO` `nn_NO` `fi` `hi_IN` | `nplurals=2; plural=(n != 1);`                                                                                    |
| `fr_FR` `pt_BR` `tr_TR`                                                              | `nplurals=2; plural=(n > 1);`                                                                                     |
| `ro_RO`                                                                              | `nplurals=3; plural=n==1 ? 0 : (n==0 \|\| (n%100 > 0 && n%100 < 20)) ? 1 : 2;`                                    |
| `pl_PL`                                                                              | `nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<12 \|\| n%100>14) ? 1 : 2);`                         |
| `ru_RU`                                                                              | `nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 \|\| n%100>=20) ? 1 : 2);`        |
| `ar`                                                                                 | `nplurals=6; plural=n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 && n%100<=99 ? 4 : 5;` |

`(n != 1)` and `(n > 1)` are both two-form rules but disagree on zero, so do
not assume a language uses the first just because it has two forms. Every rule
above was taken from the catalogues in this repo that already pass `msgfmt -c`.

Also preserve any `#.` translator comments and `#, php-format` flags — they
carry context the translator needs and the format check relies on.

### Step 3: Validate before compiling

Never compile an unvalidated catalogue.

```bash
fail=0
for f in "$PREMIUM"/languages/*.po; do
    msgfmt -c -o /dev/null "$f" || { echo "FAIL: $f"; fail=1; }
done
[ "$fail" -eq 0 ] && echo "all catalogues valid"
```

The trailing `[ "$fail" -eq 0 ]` matters. Without it the loop's exit status is
whatever the last `echo` returned, so the whole command reports success even
when catalogues are broken and the `FAIL:` lines scroll past unnoticed. With
it, a red validation is a non-zero exit you cannot miss.

Validate each file separately, as above. Do **not** collapse this into
`msgfmt -c -o /dev/null "$PREMIUM"/languages/*.po` — passing several PO files
to one `msgfmt` invocation merges them into a single catalogue and reports
every shared msgid as a "duplicate message definition", which is noise, not a
real error.

`-c` checks the header and verifies that plural translations match the
declared `nplurals`. Fix every reported file before continuing — `wp i18n
make-mo` is more lenient than `msgfmt` and will happily emit a `.mo` with a
missing plural rule, which is how the defect above reached customers.

### Step 4: Compile (once, after all locales)

```bash
# Compile MO files, used by WordPress 6.3-6.4.
npm --prefix "$PREMIUM" run i18n:make-mo

# Compile .l10n.php files, used by the WP 6.5+ translation controller.
npm --prefix "$PREMIUM" run i18n:make-php
```

Then confirm the compiled output really carries the plural rule:

```bash
miss=0
for f in "$PREMIUM"/languages/*.mo; do
    msgunfmt "$f" | grep -q "Plural-Forms" || { echo "no plural rule: $f"; miss=1; }
done
[ "$miss" -eq 0 ] && echo "all compiled catalogues carry a plural rule"
```

### Step 5: Verify the artifacts actually ship

Both runtime artifacts must reach the distributed zip: `.mo` for WordPress
6.3–6.4, `.l10n.php` for 6.5+. Premium is not on wordpress.org, so there are
no language packs — if it is not bundled, the translation does not exist.

`plugin-zip` packs the `files` allowlist in `package.json`, so `languages/*.mo`
and `languages/*.l10n.php` must both be listed there. Verify:

```bash
npm --prefix "$PREMIUM" run plugin-zip
unzip -l "$PREMIUM/simple-history-premium.zip" | grep -c "languages/"
```

A zero here means the work in this skill reached nobody. That was the actual
state of every release up to 1.14.0 — the catalogues were built and committed
for months while the plugin shipped English-only.

## Examples

-   `/premium-translate de_DE` — Translate to German
-   `/premium-translate sv_SE da_DK` — Translate to Swedish and Danish
-   `/premium-translate` — Translate all configured locales
