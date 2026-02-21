---
name: release
description: Release checklist for core and premium plugins. Covers version bumping, changelog finalization, tagging, and deployment. Triggers when user says "release", "publish", "deploy", "bump version", "prepare release".
disable-model-invocation: true
allowed-tools: Bash, Read, Edit
hooks:
    PreToolUse:
        - matcher: Bash
          hooks:
              - type: command
                command: '$CLAUDE_PROJECT_DIR/.claude/hooks/block-git-push.sh'
---

# Release Process

Release checklist for Simple History core plugin and add-ons.

Uses [OneFlow](https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow) branching model and [semver](https://semver.org/) versioning.

## Release Order

**Always release core first, then premium/add-ons.**

Premium declares a minimum core version. While reversing the order won't crash (premium shows a graceful admin notice), releasing core first ensures compatibility.

## Core Plugin Release

### 1. Create Release Branch

```bash
git checkout main
git pull
git checkout -b release-X.Y.Z
```

### 2. Bump Version

```bash
npm run bump:patch   # or bump:minor / bump:major
```

This updates version in all three locations:

-   `index.php` (plugin header `Version:` and `SIMPLE_HISTORY_VERSION` constant)
-   `readme.txt` (`Stable tag:`)
-   `package.json` (`version`)

### 3. Update Changelog

In `readme.txt`, move items from `### Unreleased` to a new versioned section:

```
### X.Y.Z (Month Year)
```

Remove the `### Unreleased` heading entirely so the readme is clean. Do **not** keep an empty Unreleased section.

**Do not update `CHANGELOG.md`** during this step — it is maintained separately.

### 4. Add Update Details

If the release has significant changes, add update details to `class-simple-history-updates.php`. This is shown to users in the WordPress update screen. Show the user the changes for review (confirmed in the pre-tag checklist).

### 5. Run QA

```bash
npm run php:lint
npm run php:phpstan
npm run build
```

Run the three test suites in parallel using three separate Bash tool calls (with `run_in_background: true`) in a single message:

-   `PHP_CLI_VERSION=81 PHP_VERSION=8.1 npm run test:wpunit`
-   `PHP_CLI_VERSION=81 PHP_VERSION=8.1 npm run test:functional`
-   `PHP_CLI_VERSION=81 PHP_VERSION=8.1 npm run test:acceptance`

Wait for all three to finish, then check results. Each suite is independent — they share the database container but don't conflict.

### 6. Write Blog Post

Write a blog post on simple-history.com as a **draft** (do not publish):

-   Detailed changelog
-   Screenshots where relevant
-   Links to updated documentation
-   Tagged with _releases_ and _changelog_

The user will review and publish the blog post manually.

### 6b. Link Blog Post from readme.txt

Add a link to the blog post in the `readme.txt` changelog entry for this version. Place it right after the version heading, before the first changelog category. Use this format:

```
### X.Y.Z (Month Year)

Brief summary of the release highlights.
[Read more about it in the release post](https://simple-history.com/YYYY/simple-history-X-Y-Z-released/)

**Added**
```

The URL pattern is `https://simple-history.com/YYYY/simple-history-X-Y-Z-released/` where dots in the version are replaced with hyphens.

### 7. Tag

**Before tagging, ask the user to confirm ALL of these.** Do not proceed until confirmed:

-   [ ] Changelog in `readme.txt` looks correct
-   [ ] Update details in `class-simple-history-updates.php` look good
-   [ ] All tests pass (wpunit, functional, acceptance)
-   [ ] Blog post is written on simple-history.com and linked from `readme.txt` changelog entry (with "Read more" link after version heading)

```bash
git tag X.Y.Z
```

### 8. Merge to Main

```bash
git checkout main
git merge release-X.Y.Z
git branch -d release-X.Y.Z
```

### 9. Push (MANUAL — do not run automatically)

**STOP: Do not execute git push.** Pushing the tag triggers deployment to WordPress.org and is irreversible. Remind the user to run these commands themselves:

```bash
git push origin main
git push origin X.Y.Z
```

Pushing the tag triggers the GitHub Actions workflow (`.github/workflows/deploy.yml`) which builds and deploys to WordPress.org via SVN.

### 10. Post-Release

-   Wait for [GitHub Actions](https://github.com/bonny/WordPress-Simple-History/actions) to finish deploying
-   Verify the new version appears on https://wordpress.org/plugins/simple-history/
-   Test updating the plugin on:
    -   [Local test site with non-symlinked plugins](http://wordpress-add-ons-testing-docker.test:8288/wp-admin/update-core.php)
    -   https://eskapism.se/wp-admin/
    -   https://simple-history.com
-   Follow up [issues](https://github.com/bonny/WordPress-Simple-History/issues) and [support threads](https://wordpress.org/support/plugin/simple-history/active/) — answer and close resolved ones

## Add-on Release (Premium and Others)

Add-ons live in a separate monorepo. See `CLAUDE.local.md` for the local path.

### Branch and Tag Naming

Each add-on uses its own prefix:

| Add-on             | Branch example                     | Tag example                |
| ------------------ | ---------------------------------- | -------------------------- |
| Premium            | `premium/release-1.8.0`            | `premium/1.8.0`            |
| Debug and Monitor  | `debug-and-monitor/release-1.0.1`  | `debug-and-monitor/1.0.1`  |
| WooCommerce Logger | `woocommerce-logger/release-1.0.4` | `woocommerce-logger/1.0.4` |

### 1. Test

Run tests and manual smoke testing. Verify features work with the latest core version.

### 2. Create Release Branch

```bash
git checkout main
git pull
git checkout -b <prefix>/release-X.Y.Z
```

### 3. Update Changelog

Update changelog in the add-on's `readme.txt`. Move items from `### Unreleased` to a versioned section. Remove the `### Unreleased` heading entirely so the readme is clean.

### 4. Update Translations

If texts have been added or modified, update translations using Claude's translate command.

### 5. Update Version

**For Premium:** Run Claude command `version-update`, which updates:

-   `simple-history-premium.php` — plugin header (`Version: X.Y.Z`)
-   `simple-history-premium.php` — `Config::init()` array (`'version' => 'X.Y.Z'`)
-   `readme.txt` — `Stable tag: X.Y.Z`

**For other add-ons:** Manually update:

-   `readme.txt`
-   `index.php` (or similar main file, 2 places — header and constant)

### 6. Update Minimum Core Version (Premium Only)

**Both of these must be updated together — keep them in sync!**

In `inc/functions.php`:

-   The `version_compare()` check (the actual version gate)
-   The admin notice message string (the user-facing text)

### 7. Build (Premium Only)

```bash
npm run build
```

Build artifacts are committed for the premium plugin.

### 8. Tag

```bash
git tag <prefix>/X.Y.Z
```

### 9. Merge to Main

```bash
git checkout main
git merge <prefix>/release-X.Y.Z
git branch -d <prefix>/release-X.Y.Z
```

### 10. Push (MANUAL — do not run automatically)

**STOP: Do not execute git push.** Remind the user to run these commands themselves:

```bash
git push origin main
git push origin <prefix>/X.Y.Z
```

### 11. Create Distribution Zip

**Premium:**

```bash
npm run plugin-zip
```

**Other add-ons:** Use Keka to zip the plugin folder and add to releases folder.

Rename zip to include version: `simple-history-premium-1.8.0.zip`

### 12. Upload to Lemon Squeezy

-   Go to https://app.lemonsqueezy.com/products/
-   Upload zip to each variant
-   Add new version number to each file uploaded

### 13. Update simple-history.com

-   Update [plugin info releases](https://simple-history.com/wp/wp-admin/edit.php?post_type=plugin_releases):
    -   Update version
    -   Update changelog
    -   Update date on add-on product page at `https://simple-history.com/add-ons/<plugin-slug>`

### 14. Verify Update Delivery

Test that the plugin update is detected on:

-   [eskapism.se](https://eskapism.se/wp-admin/network/update-core.php) (has premium with license)
-   [Local test site](http://wordpress-add-ons-testing-docker.test:8288/wp-admin/) (non-symlinked plugins, with license entered)
-   [simple-history.com](https://simple-history.com/) (has the plugin but needs manual zip upload to update)

If no update is found, check Lemon Squeezy API errors and debug.

### 15. Blog Post

Write a [blog post on simple-history.com](https://simple-history.com/wp/wp-admin/edit.php) describing the release. Add categories like _premium_ and _releases_.

## Version Locations Reference

### Core Plugin

| File           | Location                          | Example                                        |
| -------------- | --------------------------------- | ---------------------------------------------- |
| `index.php`    | Plugin header `Version:`          | `Version: 5.22.0`                              |
| `index.php`    | `SIMPLE_HISTORY_VERSION` constant | `define( 'SIMPLE_HISTORY_VERSION', '5.22.0' )` |
| `readme.txt`   | `Stable tag:`                     | `Stable tag: 5.22.0`                           |
| `package.json` | `version` field                   | `"version": "5.22.0"`                          |

### Premium Plugin

| File                         | Location                  | Example                    |
| ---------------------------- | ------------------------- | -------------------------- |
| `simple-history-premium.php` | Plugin header `Version:`  | `Version: 1.8.0`           |
| `simple-history-premium.php` | `Config::init()` version  | `'version' => '1.8.0'`     |
| `readme.txt`                 | `Stable tag:`             | `Stable tag: 1.8.0`        |
| `inc/functions.php`          | `version_compare()` check | Minimum core version gate  |
| `inc/functions.php`          | Admin notice message      | User-facing version string |
