---
name: changelog
description: Add changelog entries to readme.txt. Use when updating Unreleased section, documenting changes for release, or when user says "add changelog", "update changelog", "log this change".
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
- Begin with category verb: "Fixed...", "Added...", "Changed..."
- Link GitHub issue/PR if available
- End with period

## Writing Guidelines

**Do:**
- Be specific: "Fixed timezone handling in email reports"
- User-focused: explain what users notice
- Active voice: "Fixed X" not "X was fixed"

**Don't:**
- "Bug fixes" (too vague)
- "Various improvements" (meaningless)
- Technical jargon users won't understand

## Examples

```
✅ Fixed post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created.
✅ Added developer mode badge to improve debugging workflow.
❌ Bug fixes
❌ Updated code
```

## Location

- File: `readme.txt` (project root)
- Section: `## Changelog` → `### Unreleased`
- If Unreleased doesn't exist, create it after `## Changelog`
