# Composer patches

This directory holds patches that are automatically applied to vendor packages
after `composer install` or `composer update`. We use the
[`cweagans/composer-patches`](https://github.com/cweagans/composer-patches)
plugin to drive this — it's a dev dependency.

## Why we patch vendor code at all

Some vendor packages have known issues that the upstream maintainer won't fix
(usually because the package is in maintenance mode or pinned to an old PHP
version). Forking the package is heavyweight. Patching it is the in-between:
the original code stays untouched on disk, but Composer re-applies the diff
every time the package is installed, so the fix survives `composer install`.

## How it works

1. `cweagans/composer-patches` is listed in `composer.json` under `require-dev`.
2. It's allow-listed in `composer.json` `config.allow-plugins` (modern Composer
   refuses to run plugins that aren't explicitly trusted).
3. `composer.json` `extra.patches` maps **package name** → { **description** →
   **patch file path** }.
4. After Composer extracts a patched package into `vendor/`, the plugin runs
   `patch -p1 < path/to/patch` from the package's root directory.

If a patch fails to apply (usually because the package was upgraded and the
surrounding code shifted), `composer install` aborts with the failed hunk
listed. That's the signal to refresh the patch.

## Current patches

### `wp-browser-php84-nullable.patch`

**Package:** `lucatume/wp-browser` (currently at 3.7.19, pinned to PHP < 8.0
upstream)

**Problem:** PHP 8.4 deprecated implicitly marking a parameter as nullable via
`= null` without an explicit `?` type prefix:

```php
// Deprecated in PHP 8.4:
function db(string $dbName = null) { ... }

// Required:
function db(?string $dbName = null) { ... }
```

The file `src/Deprecated/deprecated-functions.php` is autoloaded via
Composer's `files` autoload, so the deprecations fire on every autoloader
load — including every `vendor/bin/phpcs` run. That's the source of the IDE's
"PHP Sniffer & Beautifier" notification cascade.

**Fix:** Add the explicit `?` prefix to the 6 affected parameters.

**Why we don't just upgrade wp-browser:** The 3.x branch is end-of-life and
declares `"php": ">=7.1 <8.0"`. wp-browser 4.x is a major rewrite that would
require restructuring our Codeception test setup. Patching is cheaper.

## When/how to refresh a patch

If you bump `lucatume/wp-browser` and `composer install` fails with a hunk
rejection:

1. Manually re-apply the same intent (explicit nullable types) to the new
   version of the file in `vendor/lucatume/wp-browser/`.
2. Regenerate the patch:
    ```bash
    cd vendor/lucatume/wp-browser
    git diff --no-index --no-prefix \
      -- /tmp/original-deprecated-functions.php src/Deprecated/deprecated-functions.php \
      > /Users/bonny/Projects/Personal/WordPress-Simple-History/patches/wp-browser-php84-nullable.patch
    ```
    (or hand-edit the existing patch's `@@` line numbers and context).
3. Verify with `patch -p1 --dry-run < ../../../patches/wp-browser-php84-nullable.patch`.

If you _upgrade_ wp-browser to a version that fixes this upstream (4.x+),
delete the patch file and remove the `extra.patches.lucatume/wp-browser`
entry from `composer.json`.

## Adding a new patch

1. Make the fix directly in `vendor/<package>/`.
2. Generate the patch from the package root:
    ```bash
    cd vendor/<vendor>/<package>
    diff -u <original> <fixed> > /path/to/patches/short-name.patch
    ```
    Or capture it via `git diff` if you've staged the change in a scratch repo.
3. Add an entry under `composer.json` `extra.patches.<package-name>`.
4. Run `composer install` to verify it applies. If the patch is malformed,
   composer aborts with the offending hunk.

## Running it

The plugin runs automatically during `composer install`/`update`. Patches are
only re-applied when a package is freshly extracted, so if you only want to
re-apply a patch after editing it, run `composer reinstall <package>`.

### IMPORTANT: use the official `composer:2` Docker image, not the Alpine variant

CLAUDE.local.md previously recommended `ghcr.io/devgine/composer-php:v2-php7.4-alpine`
to dodge wp-browser's `php: ">=7.1 <8.0"` constraint. **That image ships without
the `patch` binary**, which makes `cweagans/composer-patches` log a silent
`Could not apply patch` and move on — your patches never get applied.

Use the **official `composer:2`** image instead. It bakes in GNU patch 2.8,
git, bash, zip/unzip, openssh, etc. (the maintainers treat `cweagans/composer-patches`
support as a first-class concern, so `patch` is part of the standard apk list).

```bash
docker run --rm -v $(pwd):/app -w /app composer:2 \
  composer --ignore-platform-req=ext-mysqli \
           --ignore-platform-req=ext-zip \
           install
```

The image ships PHP 8.5, but `composer.json` pins
`config.platform.php = "7.4.33"`, so Composer's resolver treats the platform
as PHP 7.4 regardless of the runtime version. That's what keeps us safe from
accidentally installing packages that require PHP 8+ — without that pin,
running Composer on PHP 8 would happily resolve incompatible deps and break
the plugin's PHP 7.4 production support.

Running Composer on the host also works (with the same flags). The Docker
route is preferred for reproducibility in CI / clean checkouts.

> **Coordinating with the minimum supported PHP version.** Two settings have
> to move together when the plugin's supported PHP floor changes:
>
> -   `composer.json` `require.php` (currently `^7.4|^8.0`) — declared to
>     downstream consumers and WordPress.org.
> -   `composer.json` `config.platform.php` (currently `7.4.33`) — the
>     resolver target inside this repo.
>
> If you ever drop PHP 7.4 support, update both. Updating only one creates
> drift: the resolver could let in deps that the declared `require.php`
> claims to support but actually can't run.

### Verifying a patch actually applied

```bash
# Should print zero deprecation warnings:
php -r "require 'vendor/autoload.php'; echo 'loaded';"

# For the wp-browser case specifically, check the patched signatures:
grep -n '?string\|?array\|?bool' \
  vendor/lucatume/wp-browser/src/Deprecated/deprecated-functions.php
```

### Why we moved off the devgine image

| Image                                           | `patch`          | PHP | Notes                                                                                                         |
| ----------------------------------------------- | ---------------- | --- | ------------------------------------------------------------------------------------------------------------- |
| `ghcr.io/devgine/composer-php:v2-php7.4-alpine` | ❌ missing       | 7.4 | Bundles xdebug/phpunit/phpstan/etc., but no `patch`. Silent breakage with composer-patches.                   |
| `composer:2` (official)                         | ✅ GNU patch 2.8 | 8.5 | Minimal, maintained by Composer team. We use `vendor/bin/phpcs` etc. so the missing extra tools don't matter. |
| `composer:lts` (official)                       | ✅ GNU patch     | 8.x | Same as above but pinned to a long-term Composer version.                                                     |
