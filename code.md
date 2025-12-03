# Simple History code standard

## Code Quality

For comprehensive code quality guidelines, see the **code-quality** skill which covers:
-   PHP standards and style guide
-   CSS naming conventions (SuitCSS)
-   Tooling (phpcs, phpstan, rector)
-   IDE integration

## Quick Reference

-   Code standards is WordPress own
-   **phpcodesniffer** is used to format code
-   **phpstan** is used to check for bugs
-   **rector** is used to update code

### Common Commands

```bash
# Lint PHP
npm run php:lint

# Fix PHP issues
npm run php:lint-fix

# Static analysis
npm run php:phpstan
```

## Changelog

-   Try to use format from https://keepachangelog.com
-   Also read and try to follow https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/
-   Use the **changelog** skill to add entries to readme.txt

## Git

-   Will try to follow OneFlow:
    https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow
-   Run phpstan after making php changes in many files or making a larger change in a single file.
