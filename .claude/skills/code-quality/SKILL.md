---
name: code-quality
description: Enforces WordPress coding standards using phpcs, phpstan, and rector. Runs linting and static analysis on PHP/CSS/JS code. Triggers: "run phpcs", "run phpstan", "lint", "check code", "fix code style", "coding standards", or when lint errors occur.
allowed-tools: Read, Grep, Glob, Bash
---

# Code Quality Standards

## Quick Commands

```bash
npm run php:lint        # Check PHP style
npm run php:lint-fix    # Auto-fix PHP issues
npm run php:phpstan     # Static analysis
npm run lint:js         # JavaScript
npm run lint:css        # CSS
```

## Project-Specific Rules

| Area | Standard |
|------|----------|
| PHP | 7.4+, WordPress Coding Standards |
| Prefixes | `sh`, `simplehistory`, `simple_history` |
| Text domain | `simple-history` |
| CSS naming | SuitCSS: `sh-ComponentName-subpart` |
| Array syntax | Short `[]` not `array()` |
| Control structures | Always use braces `{}`, never colon syntax |

## Essential Principles

1. **Always escape output** - Use WordPress escaping functions
2. **Prefix everything** - All hooks, functions, classes
3. **Run tools after changes** - phpcs/phpstan before committing

## Detailed Guidelines

- [php-standards.md](php-standards.md) - PHP style, happy path, early returns
- [css-standards.md](css-standards.md) - SuitCSS naming conventions
- [js-standards.md](js-standards.md) - JavaScript conventions
- [tooling.md](tooling.md) - phpcs, phpstan, rector usage

## Related Files

- `phpcs.xml.dist` - PHP_CodeSniffer config
- `phpstan.neon` - PHPStan config
