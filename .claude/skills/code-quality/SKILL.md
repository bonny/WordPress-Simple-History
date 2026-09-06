---
name: code-quality
description: Runs linting and static analysis on PHP/CSS/JS using phpcs, phpstan, and rector. Use when checking code style, fixing lint errors, or running static analysis.
allowed-tools: Read, Grep, Glob, Bash
---

# Code Quality Standards

## Quick Commands

```bash
composer lint           # Check PHP style (local, fastest)
composer lint-fix       # Auto-fix PHP issues (local)
npm run php:lint        # Same check, in Docker
npm run php:lint-fix    # Same fix, in Docker
npm run php:phpstan     # Static analysis
npm run lint:js         # JavaScript
npm run lint:css        # CSS
```

Always run phpstan via `npm run php:phpstan`. Bare `vendor/bin/phpstan analyse`
crashes a worker at the default 128M and still crashes at 1G; the npm script
passes the 2048M that actually works.

## Project-Specific Rules

| Area               | Standard                                   |
| ------------------ | ------------------------------------------ |
| PHP                | 7.4+, WordPress Coding Standards           |
| Prefixes           | `sh`, `simplehistory`, `simple_history`    |
| Text domain        | `simple-history`                           |
| CSS naming         | SuitCSS: `sh-ComponentName-subpart`        |
| Array syntax       | Short `[]` not `array()`                   |
| Control structures | Always use braces `{}`, never colon syntax |

## Essential Principles

1. **Always escape output** - Use WordPress escaping functions
2. **Prefix everything** - All hooks, functions, classes
3. **Run tools after changes** - phpcs/phpstan before committing

## Detailed Guidelines

-   [php-standards.md](php-standards.md) - PHP style, happy path, early returns
-   [css-standards.md](css-standards.md) - SuitCSS naming conventions
-   [js-standards.md](js-standards.md) - JavaScript conventions
-   [tooling.md](tooling.md) - phpcs, phpstan, rector usage
-   [design-principles.md](design-principles.md) - DRY, YAGNI, refactoring patterns

## Related Files

-   `phpcs.xml.dist` - PHP_CodeSniffer config
-   `phpstan.neon` - PHPStan config
