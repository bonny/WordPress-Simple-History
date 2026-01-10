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

## Comments

### Placement: Above the Code

Place comments on their own line above the code they explain, not as trailing comments on the same line. This follows WordPress coding standards and improves readability.

```php
// Good: Comment above the code.
// Return early if user is not authorized.
return $result;

// Avoid: Trailing comment.
return $result; // Return because user is not authorized
```

**Why comments above are preferred:**
- More visible and easier to scan
- Avoids pushing lines beyond character limits
- Easier to maintain consistent formatting
- Git diffs show comment changes separately from code changes

### Minimize Comments (Clean Code)

Prefer self-documenting code over explanatory comments. As Robert Martin's "Clean Code" advises: comments often compensate for failure to express intent in code.

```php
// Avoid: Comment explains unclear code.
// Check if user can edit posts.
if ( $user->cap & 0x04 ) { ... }

// Better: Self-documenting code needs no comment.
if ( $user->can_edit_posts() ) { ... }
```

**When comments are appropriate:**
- Explaining intent or "why" (not "what")
- Warning of consequences
- Clarifying complex algorithms
- TODO markers for future work

## Frontend Development

### Prefer Web Standards Over JavaScript

Use native HTML elements and CSS before reaching for JavaScript:

-   **`<details>`/`<summary>`** for expand/collapse instead of JS toggles
-   **`<dialog>`** for modals instead of custom JS implementations
-   **CSS `:focus-visible`** for focus states instead of JS focus management
-   **Form validation attributes** (`required`, `pattern`, `type="email"`) before JS validation
-   **CSS Grid/Flexbox** for layouts instead of JS-based positioning

**Why?**
- Works without JavaScript (progressive enhancement)
- Accessible by default (screen readers, keyboard navigation)
- Less code to maintain
- Better performance
- Browser handles edge cases

**Example:**
```html
<!-- Good: Native HTML -->
<details>
  <summary>Show more</summary>
  <p>Hidden content</p>
</details>

<!-- Avoid: JavaScript-dependent -->
<button onclick="toggle()">Show more</button>
<div id="content" hidden>Hidden content</div>
```

## Changelog

-   Try to use format from https://keepachangelog.com
-   Also read and try to follow https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/
-   Use the **changelog** skill to add entries to readme.txt

## Git

-   Will try to follow OneFlow:
    https://www.endoflineblog.com/oneflow-a-git-branching-model-and-workflow
-   Run phpstan after making php changes in many files or making a larger change in a single file.
