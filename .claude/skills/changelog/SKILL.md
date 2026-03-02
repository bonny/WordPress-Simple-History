---
name: changelog
description: Adds changelog entries to readme.txt following keepachangelog format. Triggered when updating Unreleased section, documenting changes for release, or when user says "add changelog", "update changelog", "log this change".
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

- Start with `-   ` (hyphen + 3 spaces)
- Do NOT repeat the category verb — the heading already says Added/Changed/Fixed, so don't start entries with "Added...", "Fixed...", etc.
- Link GitHub issue/PR if available
- End with period

## Writing Guidelines

Changelogs are for **humans, not machines**. Write for both technical and non-technical WordPress users.

**Write for the user:**
- Explain what changed from the user's perspective, not what you did in the code
- Provide context and scope: instead of "Optimized query" write "Improved performance on sites with large activity logs"
- Replace jargon with clarity: avoid acronyms, internal class names, or hook names unless the audience is developers
- Be specific: "Fixed timezone handling in email reports" not "Bug fixes"
- Active voice: "Fixed X" not "X was fixed"

**Be honest and complete:**
- Never hide breaking changes, deprecations, or security fixes
- Be upfront about what changed and why — users trust changelogs that are transparent
- Include all notable user-facing changes; selective entries undermine credibility
- Mark experimental features with a trailing "(experimental)" tag, not as a prefix

**Keep it concise:**
- One bullet per change, one or two sentences max
- Don't duplicate commit messages — curate and translate them into user-facing language
- Group related small changes into a single entry rather than listing each separately
- Omit internal refactors, code cleanup, and dev tooling changes unless they affect users

**Don't write:**
- "Bug fixes" or "Various improvements" (too vague, tells users nothing)
- "Updated code" or "Minor changes" (meaningless)
- Raw commit messages or git log dumps
- Internal hook/filter names in user-facing entries (put in developer docs instead)

## Categories

Use these standard categories from [Keep a Changelog](https://keepachangelog.com):

- **Added** — New features and capabilities
- **Changed** — Modifications to existing functionality
- **Deprecated** — Features that will be removed in a future release
- **Removed** — Features that have been eliminated
- **Fixed** — Bug fixes
- **Security** — Vulnerability patches (always include these, never hide them)

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

- File: `readme.txt` (project root)
- Section: `## Changelog` → `### Unreleased`
- If Unreleased doesn't exist, create it after `## Changelog`
