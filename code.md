# Simple History code standard

Since I always forget what standards I use in different projects this file is here to remind me about the standards I use in this project:

-   Code standards is WordPress own.
-   **phpcodesniffer** is used to format code.
-   **phpstan** is used to check for bugs.
-   **rector** is used to update code.

## PHP Code Style

### Control Structures

**Always use curly brace syntax for control structures.** Do not use alternative syntax (colon + end statements).

✅ **Correct:**

```php
foreach ( $post_types as $post_type ) {
    // ...
}

if ( $condition ) {
    // ...
}
```

❌ **Incorrect:**

```php
foreach ( $post_types as $post_type ) :
    // ...
endforeach;

if ( $condition ) :
    // ...
endif;
```

## phpcodesniffer

Uses composer package `dealerdirect/phpcodesniffer-composer-installer` to find PHP_CodeSniffer rules automagically. Run `composer install` and then `vendor/bin/phpcs`.

-   `phpcs.xml.dist` is the config used.
-   `vendor/bin/phpcs` to lint PHP from command line after editing.
-   Formatting:
    `vendor/bin/phpcbf` to fix (write to disk) errors and warning.

## phpstan

**PHPStan** is used to analyze code.

Config is in `phpstan.neon`.

-   `vendor/bin/phpstan analyse --memory-limit 2048M`

## Rector

-   **Rector** is used to update code to 7.4 and to refactor code to better quality.
    -   Dry run with `vendor/bin/rector process --dry-run`
    -   Run without `--dry-run` to write changes.
    -   Run with docker using `docker run -it --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:7.4-cli php vendor/bin/rector process --dry-run`
    -   After processing code with rector run phpcs to fix formatting.

## Changelog

-   Try to use format from https://keepachangelog.com.
-   Also read and try to follow https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/

## How to use in Visual Studio Code

-   Run `composer install`
-   Install plugin https://marketplace.visualstudio.com/items?itemName=ValeryanM.vscode-phpsab

## How to use php codesniffer

List errors and warnings:

```bash
phpcs /path/to/code/myfile.php # lint specific file
phpcs # be in plugin root and all files will be linted
npm run lint-php # or use npm script
```

Fix things:

```bash
phpcbf /path/to/code
```

## Git

-   Will try to follow OneFlow:
    https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow
-   Run phpstan after making php changes in many files or making a larger change in a single file.
