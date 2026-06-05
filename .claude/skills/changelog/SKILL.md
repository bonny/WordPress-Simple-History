---
name: changelog
description: Adds changelog entries to readme.txt following keepachangelog format. Use when updating the Unreleased section or documenting changes for a release.
allowed-tools: Read, Edit
---

# Add Changelog Entry

Add entries to the Simple History plugin's `readme.txt` changelog.

## Workflow

1. Ask user for the change description (if not provided)
2. Determine category: Added, Changed, Fixed, Deprecated, Removed, Security
3. Add entry under `## Changelog` → `### Unreleased`
4. Confirm with user

## Format

```
-   Fixed post creation via Gutenberg autosave not being logged. [#599](https://github.com/bonny/WordPress-Simple-History/issues/599)
```

-   Start with `-   ` (hyphen + 3 spaces)
-   Do NOT repeat the category verb — the heading already says Added/Changed/Fixed, so don't start entries with "Added...", "Fixed...", etc.
-   Link GitHub issue/PR if available
-   End with period

## Writing Guidelines

Changelogs are for **humans, not machines**. Write for both technical and non-technical WordPress users.

**Write for the user:**

-   Explain what changed from the user's perspective, not what you did in the code
-   Name the outcome, not the mechanism: instead of "Optimized query" write "Improved performance on sites with large activity logs" — but keep it to that one clause, not a paragraph of scope
-   Replace jargon with clarity: avoid acronyms, internal class names, or hook names unless the audience is developers
-   Be specific: "Fixed timezone handling in email reports" not "Bug fixes"
-   Active voice: "Fixed X" not "X was fixed"

**Be honest and complete:**

-   Never hide breaking changes, deprecations, or security fixes
-   Be upfront about what changed and why — users trust changelogs that are transparent
-   Include all notable user-facing changes; selective entries undermine credibility
-   Mark experimental features with the `🧪 **Experimental** —` prefix (see "Experimental features" section below)

**Keep it concise — one line per entry:**

The model is the [Claude Code changelog](https://github.com/anthropics/claude-code/blob/main/CHANGELOG.md): one short line per change that states the new behavior and stops. Aim for a single sentence; add a short `—` clause only when the consequence isn't obvious from the change itself. The changelog is the index — the release-post link is where the full story lives.

-   One bullet, one change, one line. If an entry runs to two or three sentences, it's a release-post paragraph wearing a changelog costume — cut it down.
-   Lead with the user-visible change. Stop there.
-   Drop "Previously…" contrast clauses. "Now logged" already implies it wasn't before — don't narrate the old behavior.
-   Cut inline examples, slug lists, and scope caveats (which events it does/doesn't fire on, edge cases, redaction rules, "existing installs unchanged"). A short location hint in parentheses is fine ("Tools → Export Personal Data"); a full enumeration is not.
-   Push the detail to the release post. Anything a curious user might want but most won't read belongs behind the "[Read more…]" link, not in the bullet.
-   Don't duplicate commit messages — curate and translate them into user-facing language.
-   Group related small changes into a single entry rather than listing each separately.
-   Omit internal refactors, code cleanup, and dev tooling changes unless they affect users.
-   Omit new PHP/JS functions, helpers, or APIs — these are internal and not user-facing (e.g., don't list `Helpers::get_filtered_history_url()`).
-   Cut implementation detail. Filter names, fallback chains, caching strategy, byte limits, index prefix lengths — these belong in the PR description, not the changelog.

We borrow Claude Code's **brevity**, not its structure: it uses a flat list so every line starts with "Added/Fixed", while Simple History keeps category headings — so don't add the verb prefix here (see the "Do NOT repeat the category verb" rule above).

**Too long → tightened (real examples):**

These are all from the 5.29.0 changelog — each ran 2–3 sentences with "Previously…" clauses, enumerations, and scope caveats. Tightened to one line:

```
❌ Overview action links on user, plugin, post, and media events — "All users", "All plugins",
   "All posts" / "All pages" / "All `<custom-post-type>`", "All media". Also shown on delete
   events where the per-item link would dead-end. The "All users" link shows only on
   user-management events (profile updated, user created, user deleted), not on login, logout,
   failed-login, or session-destroy events.

✅ Overview action links ("All users", "All plugins", "All posts", "All media") on user,
   plugin, post, and media events.
```

```
❌ Simple History's activity log is now included in WordPress's personal-data export
   (Tools → Export Personal Data): the events a person performed are exported automatically.
   Previously the activity log was left out of export requests entirely.

✅ Activity log is now included in WordPress's personal-data export (Tools → Export Personal Data).
```

```
❌ New installs now create the history tables with `$wpdb->get_charset_collate()` (matching
   WordPress core's pattern since 4.2) instead of a hardcoded `CHARSET=utf8`. On modern hosts
   this means tables are created as `utf8mb4`, so they can store 4-byte UTF-8 characters
   like emoji in event context — previously a post title with an emoji could silently drop
   the entire context row, leaving log entries like `Updated ""` with no user attribution.

✅ New installs create history tables as `utf8mb4`, so emoji and other 4-byte characters in
   event context are preserved.
```

The pattern in every case: keep the user-visible change and a short location hint, cut the "Previously…" framing, the per-item enumerations, the edge-case scoping, and the mechanism. If a caveat genuinely matters to users (e.g. "existing installs unchanged"), it goes in the release post, not the bullet.

**Don't write:**

-   "Bug fixes" or "Various improvements" (too vague, tells users nothing)
-   "Updated code" or "Minor changes" (meaningless)
-   Raw commit messages or git log dumps
-   Internal hook/filter names in user-facing entries (put in developer docs instead)

## Categories

Use these standard categories from [Keep a Changelog](https://keepachangelog.com):

-   **Added** — New features and capabilities
-   **Changed** — Modifications to existing functionality
-   **Deprecated** — Features that will be removed in a future release
-   **Removed** — Features that have been eliminated
-   **Fixed** — Bug fixes
-   **Security** — Vulnerability patches (always include these, never hide them)

## Experimental features

Features gated behind the experimental features setting use a consistent format that signals the gating _and_ invites curiosity.

**Format:**

```
-   🧪 **Experimental** — Description of the feature, written like any other entry.
```

**Rules:**

-   Lead with `🧪 **Experimental** — ` (test-tube emoji + bold label + em-dash + space).
-   Don't add "Requires experimental features to be enabled" or trailing "(experimental)" — the prefix already says it.
-   Place experimental entries at the **bottom** of their subsection (Added/Changed/Fixed/Security). Stable items first, experimental opt-ins after.
-   If a feature also has a developer-facing filter or hook to toggle it, mention that in the body of the entry, not as boilerplate.
-   Don't repeat the marker on continuation entries — every experimental bullet stands alone.

**Preamble in Unreleased:**

The Unreleased section starts with a one-line blockquote that explains what the marker means. This lives once at the top of Unreleased — don't duplicate it in older releases:

```
> 🧪 **Experimental** entries are gated behind the experimental features setting (Settings → Simple History → Experimental). Enable it to try them, then share feedback so we know what to ship for everyone.
```

**Why this format:**

-   The 🧪 emoji reads as "try this, it's new" — a curiosity hook, not a warning.
-   Leading the line (rather than trailing) makes it scannable: a reader skimming the changelog can spot experimental items immediately.
-   Placing them last in each section means readers focused on stable shipping changes can stop scanning at the first 🧪.
-   The format matches the style already used for headings in the readme description (🔍 ✨ 🚀 💚).

**Examples:**

```
✅ 🧪 **Experimental** — Failed application password authentication on REST API and XML-RPC requests is now logged as a warning…
✅ 🧪 **Experimental** — "History" column on post and page list tables showing recent activity at a glance.
❌ "History" column on post and page list tables… (experimental)            (trailing tag — old format)
❌ "History" column on post and page list tables… Requires experimental features to be enabled.   (boilerplate phrase — superseded by the 🧪 prefix)
❌ 🧪 History column…                                                        (missing **Experimental** label)
```

## Unreleased Section

Always maintain an `### Unreleased` section at the top of the changelog. This lets users see what's coming and makes it easy to promote entries into a versioned release.

When releasing, move Unreleased entries into a new versioned section with the release date.

## Release: the "What's new" update notice

At release time, besides the changelog, add the **in-plugin "Highlights in this version"** notice that users see when they update. It's separate from `readme.txt`:

-   File: `inc/services/class-simple-history-updates.php`
-   Register `add_filter( 'simple_history/pluginlogger/plugin_updated_details/simple-history/X.Y.Z', [ $this, 'on_plugin_updated_details_X_Y_Z' ] );` in `loaded()`
-   Add a matching `on_plugin_updated_details_X_Y_Z()` method returning 3 short highlight bullets + the release-post link (copy the previous version's method)

**Preview it** with the dev WP-CLI command (needs `SIMPLE_HISTORY_DEV`). Note the subcommand uses **underscores**, not dashes:

```bash
docker compose run --rm wpcli_mariadb \
  simple-history dev add_plugin_update_message --prev-version=<PREV> --version=<NEW>
```

This logs a fake "plugin updated" event; the highlights render in its event-details panel in wp-admin. `--version` defaults to the installed version. Premium has its own `on_plugin_updated_details_*` in the premium plugin — preview with `--plugin=simple-history-premium/simple-history-premium.php`.

## Examples

```
✅ Post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created.
✅ Developer mode badge to improve debugging workflow.
✅ Performance on sites with large activity logs improved by optimizing database queries.
✅ `simple_history_log()` function — use `SimpleHistory\log()` instead. Will be removed in 6.0.
❌ Added developer mode badge (redundant — heading already says "Added")
❌ Fixed post creation (redundant — heading already says "Fixed")
❌ Bug fixes
❌ Updated code
❌ Refactored SimpleHistoryLogQuery class
❌ Various improvements and optimizations
```

## References

-   WordPress Developer Blog: [The Importance of a Good Changelog](https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/)
-   Keep a Changelog: [https://keepachangelog.com](https://keepachangelog.com)

## Location

-   File: `readme.txt` (project root)
-   Section: `## Changelog` → `### Unreleased`
-   If Unreleased doesn't exist, create it after `## Changelog`
