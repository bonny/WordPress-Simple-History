---
name: code-quality
description: WordPress coding standards and linting tools (phpcs, phpstan, rector, phpcbf). ALWAYS use after writing significant PHP/CSS/JS code to verify compliance. Triggers: "run phpcs", "run phpstan", "lint", "check code", "fix code style", "coding standards", or when user reports lint/phpcs/phpstan errors.
---

# Code Quality Standards for Simple History

This skill provides code quality guidelines for the Simple History WordPress plugin, including PHP, CSS, and JavaScript standards, tooling commands, and best practices.

## When to Use This Skill

**ALWAYS invoke this skill:**
- After writing or editing significant PHP/CSS/JS code (run phpcs/phpstan to verify)
- When user says: "lint", "phpcs", "phpstan", "check code", "fix style", "coding standards"
- When user reports lint errors or code style issues
- Before committing code changes (verify with phpcs)

**Trigger phrases:** "run phpcs", "run phpstan", "lint my code", "check code quality", "fix code style", "coding standards"

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

## Design Principles

### DRY - Don't Repeat Yourself

Extract shared logic when you have **actual** duplication (3+ occurrences). But don't preemptively create abstractions.

**When to refactor for DRY:**

1. **Same logic in 3+ places** - Time to extract
2. **Factory methods** (e.g., `get_sender()`) - Should be in one place
3. **Configuration data** (e.g., presets, type definitions) - Single source of truth
4. **Type labels/mappings** - Centralize to avoid sync issues

**How to refactor:**

```php
// BEFORE: Duplicated in REST controller, CLI command, and module
private function get_sender( string $type ) {
    switch ( $type ) {
        case 'email': return new Email_Sender();
        case 'slack': return new Slack_Sender();
    }
}

// AFTER: Single public static method in the main module
public static function get_sender( string $type ): ?Sender {
    // ... implementation
}

// Other classes call:
$sender = Main_Module::get_sender( $type );
```

**Checklist when adding new code:**
- [ ] Does similar logic exist elsewhere? Search before writing.
- [ ] Will multiple classes need this? Make it `public static` in a central location.
- [ ] Is this configuration/mapping data? Put it in one place.

### YAGNI - You Aren't Gonna Need It

Don't implement functionality until it's actually needed. Avoid:
- Creating abstractions for hypothetical future use cases
- Building helper functions for one-time operations
- Adding configurability "just in case"
- Designing for requirements that don't exist yet

**Together**: DRY says extract when you have real duplication. YAGNI says wait until you actually need it. Three similar lines of code is often better than a premature abstraction.

### Proactive DRY Review

When creating new classes (CLI commands, REST controllers, etc.) that work with existing functionality:

1. **Check existing classes** for methods that could be shared
2. **Look for these patterns** that indicate duplication:
   - Factory methods (`get_sender()`, `get_formatter()`)
   - Configuration getters (`get_presets()`, `get_types()`)
   - Label/mapping functions (`get_type_label()`, `get_status_text()`)
3. **Refactor existing code** if needed - make private methods public static
4. **Use the shared method** instead of copying code

## Related Files

- `phpcs.xml.dist` - PHP_CodeSniffer configuration
- `phpstan.neon` - PHPStan configuration
- `package.json` - npm scripts for code quality
