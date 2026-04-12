---
name: premium-version-update
description: Updates the Simple History Premium plugin version in all required locations. Use when bumping or updating the premium version number.
argument-hint: <new-version>
allowed-tools: Read, Edit, Glob
---

# Update Premium Plugin Version

Update the Simple History Premium plugin version in all required locations.

## Premium Add-on Path

See `CLAUDE.local.md` for the premium add-on path. Read it first to determine the correct path.

## Workflow

### Validation

1. Read `CLAUDE.local.md` to find the premium add-on path
2. If no version is provided in $ARGUMENTS, show the current version and ask for the new version
3. Validate the version format follows semantic versioning (e.g., 1.10.0, 2.0.0, 1.9.1)
4. Show the current version and the new version for confirmation before making changes

### Files to Update

Update the version in these three locations (all relative to the premium add-on path):

1. **readme.txt** — Update the `Stable tag:` line
2. **simple-history-premium.php** — Update the `Version:` line in the plugin header comment
3. **simple-history-premium.php** — Update the `'version'` value in the `Config::init()` array

### After Updates

Show a summary of all changes made with before/after values.

## Examples

-   `/premium-version-update 1.10.0` — Update version to 1.10.0
-   `/premium-version-update 2.0.0` — Update version to 2.0.0
