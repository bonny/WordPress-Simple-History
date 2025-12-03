---
name: code-quality
description: Provides WordPress coding standards, PHP/CSS style guide, code linting and static analysis (phpcs, phpstan, rector, phpcbf). Use before writing or editing PHP or CSS code, when fixing lint errors, running code quality tools, or reviewing code style compliance.
---

# Code Quality Standards for Simple History

This skill provides comprehensive code quality guidelines for the Simple History WordPress plugin, including PHP and CSS standards, tooling commands, and best practices.

## When to Use This Skill

**IMPORTANT: Invoke this skill proactively BEFORE writing any PHP or CSS code to ensure compliance with project standards.**

Invoke this skill:
- **Before** writing or editing PHP code (to know the style rules)
- **Before** writing or editing CSS code (to know naming conventions)
- When running code quality tools (phpcs, phpstan, rector)
- When fixing lint errors or reviewing code style compliance

## Quick Reference

### PHP Standards
- **PHP Version**: 7.4+ compatibility required
- **WordPress Coding Standards**: Official WordPress standards (see phpcs.xml.dist)
- **No mb_* functions**: Use standard string functions
- **Array syntax**: Short syntax `[]` not `array()`
- **Control structures**: Always use curly braces `{}`, never colon syntax
- **Hook prefixes**: Always use `sh`, `simplehistory`, or `simple_history`

### CSS Standards
- **Naming Convention**: SuitCSS
- **Prefix**: `sh`
- **Components**: `sh-ComponentName` (e.g., `sh-HelpSection`, `sh-LogEntry`)
- **Subparts**: `sh-ComponentName-subpart` (e.g., `sh-LogEntry-author`)

### JavaScript
- Follow @wordpress/scripts conventions
- Text domain: `simple-history`
- Always use braces for if/else/for/while (no single-line statements)

## Detailed Guidelines

### PHP Code Style

See [php-standards.md](php-standards.md) for detailed PHP style guide including:
- Happy path last pattern
- Early returns over else
- Ternary operator formatting
- Control structure syntax

### Tooling Commands

See [tooling.md](tooling.md) for:
- phpcs (PHP_CodeSniffer) usage
- phpstan (static analysis) usage
- rector (code modernization) usage
- npm scripts for code quality

### CSS Guidelines

See [css-standards.md](css-standards.md) for SuitCSS naming conventions and examples.

### JavaScript Guidelines

See [js-standards.md](js-standards.md) for JavaScript code style.

## Essential Principles

1. **Always escape output** - Use WordPress escaping functions
2. **Prefix everything** - Use `sh`, `simplehistory`, or `simple_history`
3. **Follow WordPress conventions** - The "WordPress Way"
4. **Run quality tools** - Use phpcs, phpstan after significant changes

## Related Files

- `phpcs.xml.dist` - PHP_CodeSniffer configuration
- `phpstan.neon` - PHPStan configuration
- `package.json` - npm scripts for code quality
