# Composer in this repo

How to run Composer here, and the one setting that keeps PHP 7.4 support honest.

## Running Composer

Composer runs through the official `composer:2` Docker image:

```bash
docker run --rm -v $(pwd):/app -w /app composer:2 \
  composer --ignore-platform-req=ext-mysqli \
           --ignore-platform-req=ext-zip \
           install
```

Running Composer on the host works too, with the same flags. The Docker route
is preferred for reproducibility in CI and on clean checkouts.

## Never add `--ignore-platform-req=php`

The `composer:2` image ships a modern PHP, but `composer.json` pins
`config.platform.php = "7.4.33"`, so Composer's resolver treats the platform as
PHP 7.4 regardless of what the container actually runs. That pin is the only
thing stopping a PHP 8-only dependency from being resolved into a plugin that
has to run on PHP 7.4.

So if you see "your php version (8.x) does not satisfy...", the pin is not being
read — investigate that. Silencing it with `--ignore-platform-req=php` throws
away the protection.

The two `ext-` flags above are fine: they only say the _container_ lacks those
extensions, which has nothing to do with the resolver's PHP version.

## Coordinating with the minimum supported PHP version

Two settings have to move together when the plugin's supported PHP floor
changes:

-   `composer.json` `require.php` (currently `^7.4|^8.0`) — declared to
    downstream consumers and WordPress.org.
-   `composer.json` `config.platform.php` (currently `7.4.33`) — the resolver
    target inside this repo.

If you ever drop PHP 7.4 support, update both. Updating only one creates drift:
the resolver could let in deps that the declared `require.php` claims to support
but actually can't run.

## Vendor patching (retired)

Between 2026-05 and 2026-09 this repo patched vendor code via
`cweagans/composer-patches`, with the patches in a `patches/` directory applied
automatically on `composer install`.

There was ever only one patch. It added explicit `?` nullable-type prefixes to
six signatures in `lucatume/wp-browser`'s `deprecated-functions.php`, because
PHP 8.4 deprecated implicit nullables (`string $x = null` without the `?`) and
that file is in Composer's `files` autoload — so the deprecations fired on every
autoloader load, including every `vendor/bin/phpcs` run.

wp-browser 3.8.0 fixed it upstream and moved the file, so the patch was retired
on 2026-09-09 along with the plugin, the `extra.patches` block, its
`config.allow-plugins` entry, and the `patches/` directory.

Two things worth remembering from it:

-   **Check whether upstream already fixed it before assuming a patch is
    permanent.** The old notes claimed wp-browser's 3.x branch was end-of-life
    and that escaping the patch meant the 4.x rewrite. Neither was true: 3.8.x
    is the 4.x codebase transpiled back to the old PHP line, `src/Module/WPLoader.php`
    is byte-identical between 3.7.19 and 3.8.2, and both declare
    `"php": ">=7.1 <8.0"`. The bump changed no test-facing API and did not touch
    the PHP 7.4 floor.
-   **A patch that silently fails to apply looks exactly like no patch at all.**
    The Alpine `ghcr.io/devgine/composer-php` image ships without the `patch`
    binary, which made `cweagans/composer-patches` log `Could not apply patch`
    and carry on. That is the original reason this repo moved to `composer:2`,
    which bundles GNU patch. The reproducibility argument for `composer:2`
    stands on its own now.

If vendor patching is ever needed again, re-add `cweagans/composer-patches` to
`require-dev`, allow-list it under `config.allow-plugins`, and map package →
patch file under `extra.patches`.
