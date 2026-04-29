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
-   Provide context and scope: instead of "Optimized query" write "Improved performance on sites with large activity logs"
-   Replace jargon with clarity: avoid acronyms, internal class names, or hook names unless the audience is developers
-   Be specific: "Fixed timezone handling in email reports" not "Bug fixes"
-   Active voice: "Fixed X" not "X was fixed"

**Be honest and complete:**

-   Never hide breaking changes, deprecations, or security fixes
-   Be upfront about what changed and why — users trust changelogs that are transparent
-   Include all notable user-facing changes; selective entries undermine credibility
-   Mark experimental features with the `🧪 **Experimental** —` prefix (see "Experimental features" section below)

**Keep it concise:**

-   One bullet per change, one or two sentences max
-   Don't duplicate commit messages — curate and translate them into user-facing language
-   Group related small changes into a single entry rather than listing each separately
-   Omit internal refactors, code cleanup, and dev tooling changes unless they affect users
-   Omit new PHP/JS functions, helpers, or APIs — these are internal and not user-facing (e.g., don't list `Helpers::get_filtered_history_url()`)

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
