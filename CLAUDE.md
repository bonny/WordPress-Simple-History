# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build/Test Commands

-   Install: `composer install` && `npm install`
-   Build: `npm run build`
-   Dev: `npm run start`
-   Lint PHP: `npm run php:lint` (or fix: `npm run php:lint-fix`)
-   Lint JS/CSS: `npm run lint:js` / `npm run lint:css`
-   Static analysis: `npm run php:phpstan`
-   Run tests: `npm run test` (all) or specific:
    -   Unit: `docker compose run --rm php-cli vendor/bin/codecept run wpunit:TestName`
    -   Functional: `docker compose run --rm php-cli vendor/bin/codecept run functional:TestName`
    -   Acceptance: `docker compose run --rm php-cli vendor/bin/codecept run acceptance:TestName`
-   Run "npm run php:phpstan" to check for PHP errors

## Code Style Guidelines

-   Prefixes: 'sh', 'simplehistory', 'simple_history'
-   JS: WordPress scripts (@wordpress/scripts) conventions

-   Proper escaping required for all output
-   Text domain: 'simple-history'
-   **Logger messages**: Use active tone that reads like someone telling you what happened. Messages should start with action verbs and be easily understood by regular users, not just developers (e.g., "Activated plugin", "Created menu", "Detected modifications" - as if someone is saying "WordPress/User did this thing")

### PHP coding guidelines

-   No mb\_\* string functions allowed
-   Use short array syntax (`[]` and NOT `array()`)
-   WordPress hooks must use prefixes
-   WordPress Coding Standards with modifications (see phpcs.xml.dist)
-   PHP: 7.4+ compatibility
-   **Happy path last**: Handle error conditions first, success case last
-   **Avoid else**: Use early returns instead of nested conditions
-   **Separate conditions**: Prefer multiple if statements over compound conditions
-   **Always use curly brackets** even for single statements
-   **Ternary operators**: Each part on own line unless very short

```php
// Happy path last
if (! $user) {
    return null;
}

if (! $user->isActive()) {
    return null;
}

// Process active user...

// Short ternary
$name = $isFoo ? 'foo' : 'bar';

// Multi-line ternary
$result = $object instanceof Model ?
    $object->name :
    'A default value';

// Ternary instead of else
$condition
    ? $this->doSomething()
    : $this->doSomethingElse();
```

# CSS rules

-   Use Suit CSS for naming.
-   Prefix is "sh", so example classes are:
    -   `sh-HelpSection`, `sh-LogEntry` for main components.
    -   `sh-HelpSection-subpart`, `sh-LogEntry-author` for parts.

## Docker/WP-CLI Commands

-   Test WP-CLI commands on local website: `cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && docker compose run --rm wpcli_mariadb help simple-history list`

## Git Workflow

-   Check out a new branch before working on changes for a github issue or any other larger code

## GitHub Commands

-   Use github cli to fetch github issues
