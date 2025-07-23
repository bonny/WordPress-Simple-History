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

-   WordPress Coding Standards with modifications (see phpcs.xml.dist)
-   PHP: 7.4+ compatibility
-   Prefixes: 'sh', 'simplehistory', 'simple_history'
-   JS: WordPress scripts (@wordpress/scripts) conventions
-   WordPress hooks must use prefixes
-   Short array syntax preferred (`[]` instead of `array()`)
-   Proper escaping required for all output
-   No mb\_\* string functions allowed
-   Text domain: 'simple-history'

# CSS rules

-   Use Suit CSS for naming.
-   Prefix is "sh", so example classes are:
    -   `sh-HelpSection`, `sh-LogEntry` for main components.
    -   `sh-HelpSection-subpart`, `sh-LogEntry-author` for parts.

## Docker/WP-CLI Commands

-   Test WP-CLI commands on local website: `cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && docker compose run --rm wpcli_mariadb help simple-history list`

## Git Workflow

-   Check out a new branch before working on changes for a github issue or any other larger code
-   Don't commit without asking first.

## GitHub Commands

-   Use github cli to fetch github issues