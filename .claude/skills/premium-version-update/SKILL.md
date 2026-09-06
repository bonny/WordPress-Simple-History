---
name: premium-version-update
description: Updates the Simple History Premium plugin version in all required locations, then builds and archives the distributable release zip. Use when bumping the premium version or cutting a premium release.
argument-hint: <new-version>
allowed-tools: Read, Edit, Glob, Bash
disable-model-invocation: false
---

# Update Premium Plugin Version

Update the Simple History Premium plugin version in all required locations, then build and archive the distributable release zip.

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

## Building and Distributing the Release

Once the version is bumped, committed, and tagged, build the distributable zip and add it to the release archive. Run these from the premium add-on path.

1. **Build production assets.** Always use `npm run build` (it compiles all entry points), not `npx wp-scripts build`:

    ```bash
    npm run build
    ```

2. **Create the zip:**

    ```bash
    npm run plugin-zip
    ```

    This produces `simple-history-premium.zip` in the plugin root, wrapping everything in a top-level `simple-history-premium/` folder (the structure WordPress expects).

3. **Rename and archive it.** Move the zip to the release archive, renamed with the version number:

    ```bash
    mv simple-history-premium.zip \
      "../releases (zip archives)/simple-history-premium-<version>.zip"
    ```

    - The archive lives at `simple-history-add-ons/releases (zip archives)/` — one level up from the plugin dir — and holds one zip per released version (`simple-history-premium-1.12.0.zip`, `simple-history-premium-1.13.0.zip`, …).
    - Both the root zip and the archive dir are gitignored (`*.zip`), so these files are never committed. They're distribution artifacts only.

4. **Upload the renamed zip to Lemon Squeezy.** This is a manual step in the Lemon Squeezy dashboard and it's what actually ships the release to customers — Lemon Squeezy serves the plugin update, so until the zip is uploaded there the release isn't live no matter what's tagged in git.

**Sanity check before uploading:** the `Version:` inside the zip should match the release. Re-building an already-tagged release should produce assets byte-identical to the committed `build/` output — if `git status` shows changes under `build/` after `npm run build`, the tagged artifacts were stale and the tag needs revisiting.

## Examples

-   `/premium-version-update 1.10.0` — Update version to 1.10.0
-   `/premium-version-update 2.0.0` — Update version to 2.0.0
