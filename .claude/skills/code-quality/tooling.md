# Code Quality Tooling

This document covers the tools used to maintain code quality in the Simple History plugin.

## Overview

- **phpcs/phpcbf** - PHP_CodeSniffer for linting and auto-fixing PHP code
- **phpstan** - Static analysis to catch bugs before runtime
- **rector** - Automated code modernization and refactoring
- **npm scripts** - Convenient wrappers for all quality tools

## PHP_CodeSniffer (phpcs)

### Configuration

- Config file: `phpcs.xml.dist` in project root
- Uses `dealerdirect/phpcodesniffer-composer-installer` to auto-discover WordPress Coding Standards
- Run `composer install` to set up

### Linting PHP Code

```bash
# Lint all files (from project root)
vendor/bin/phpcs

# Lint specific file
vendor/bin/phpcs path/to/file.php

# Lint with npm script (recommended)
npm run php:lint
```

### Auto-Fixing Issues

```bash
# Fix all auto-fixable issues
vendor/bin/phpcbf

# Fix specific file
vendor/bin/phpcbf path/to/file.php

# Fix with npm script (recommended)
npm run php:lint-fix
```

### Common phpcs Issues

**Indentation**: Use tabs, not spaces (WordPress standard)
**Spacing**: Space after control structures: `if ( $condition )`
**Array syntax**: Use `[]` not `array()`
**Line length**: Keep lines under 120 characters when possible

## PHPStan (Static Analysis)

### Configuration

- Config file: `phpstan.neon` in project root
- Set to analyze for bugs, type errors, and potential issues

### Running PHPStan

```bash
# Run analysis (recommended after significant changes)
vendor/bin/phpstan analyse --memory-limit 2048M

# Run with npm script
npm run php:phpstan
```

### When to Run PHPStan

- After making changes to multiple PHP files
- After making a larger change in a single file
- Before committing significant PHP refactoring
- When adding new classes or methods

### Common PHPStan Issues

- **Type mismatches**: Ensure function return types match declarations
- **Null safety**: Check for null before accessing properties/methods
- **Unused variables**: Remove or use variables that are assigned but never used
- **Incorrect doc blocks**: Update @param and @return annotations to match code

## Rector (Code Modernization)

Rector is used to update code to PHP 7.4 standards and refactor to better quality.

### Running Rector

```bash
# Dry run (preview changes without writing)
vendor/bin/rector process --dry-run

# Apply changes
vendor/bin/rector process

# Run with Docker (if local PHP version conflicts)
docker run -it --rm -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:7.4-cli php vendor/bin/rector process --dry-run
```

### After Running Rector

Always run phpcs after rector to fix formatting:

```bash
vendor/bin/rector process && vendor/bin/phpcbf
```

## NPM Scripts (Recommended)

The project provides convenient npm scripts for all quality tools:

```bash
# PHP Linting
npm run php:lint          # Run phpcs
npm run php:lint-fix      # Run phpcbf (auto-fix)

# Static Analysis
npm run php:phpstan       # Run PHPStan

# JavaScript/CSS
npm run lint:js           # Lint JavaScript
npm run lint:css          # Lint CSS

# All Tests
npm run test              # Run all tests
```

## Development Workflow

### Before Committing

1. **Run linter**: `npm run php:lint` or `vendor/bin/phpcs`
2. **Fix issues**: `npm run php:lint-fix` or `vendor/bin/phpcbf`
3. **Check for bugs**: `npm run php:phpstan` (for significant changes)
4. **Manual review**: Check the diff for unintended changes

### During Development

- Keep phpcs running in your IDE (VS Code plugin: `vscode-phpsab`)
- Fix linting issues as you code
- Run phpstan periodically on files you're actively editing

### Continuous Integration

All pull requests should pass:
- phpcs (no coding standard violations)
- phpstan (no static analysis errors)
- All tests passing

## IDE Integration

### Visual Studio Code

1. Install plugin: [PHP Sniffer & Beautifier](https://marketplace.visualstudio.com/items?itemName=ValeryanM.vscode-phpsab)
2. Run `composer install` to set up phpcs
3. Plugin will automatically use project's `phpcs.xml.dist`

### Other IDEs

Most IDEs support phpcs integration:
- **PhpStorm**: Built-in PHP_CodeSniffer support
- **Sublime Text**: PHP_CodeSniffer plugin available
- **Vim/Neovim**: ALE or CoC plugins support phpcs

## Troubleshooting

### "Command not found: vendor/bin/phpcs"

Run `composer install` first to install dependencies.

### "Memory exhausted" when running phpstan

Use the `--memory-limit` flag:
```bash
vendor/bin/phpstan analyse --memory-limit 2048M
```

### phpcs reports errors in vendor/ or node_modules/

These directories should be excluded in `phpcs.xml.dist`. Check the config file.

### Conflicting PHP versions

If local PHP version conflicts with project requirements, use Docker:
```bash
docker run --rm -v $(pwd):/var/www/composer ghcr.io/devgine/composer-php:v2-php7.4-alpine composer <command>
```

## Summary

**Quick commands for daily use:**

```bash
# Check code quality
npm run php:lint

# Fix issues automatically
npm run php:lint-fix

# Deep analysis (after major changes)
npm run php:phpstan
```

Always run these before committing significant PHP changes!
