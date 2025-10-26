---
allowed-tools: Bash(git diff:*), Bash(git log:*), Bash(git branch:*), Read, Grep, Glob
description: Perform a comprehensive code review of the current issue/branch focusing on security, performance, and code style.
---

## Context

-   Current branch: !`git branch --show-current`
-   Current git status: !`git status`
-   Issue number: $ARGUMENTS

## Your task

You are an experienced WordPress developer conducting a professional code review. Analyze all changes in the current issue/branch with focus on:
1. **Security vulnerabilities**
2. **Performance issues**
3. **Code style compliance**
4. **WordPress best practices**

### Step 1: Determine the scope

**If an issue number is provided as `$ARGUMENTS`:**
- Use that issue number
- Check if we need to switch branches first

**Otherwise:**
- Extract the issue number from the current branch name (e.g., `issue-584-...` → issue #584)
- Review changes in the current branch

### Step 2: Identify all changed files

Get a comprehensive view of changes:

```bash
# Get the main branch name
git branch --show-current

# Show all files changed compared to main
git diff main...HEAD --name-status

# Get full diff for review
git diff main...HEAD
```

### Step 3: Review each changed file with professional expertise

For EACH changed file, examine:

#### Security Review

**Input Validation & Sanitization:**
- Are all user inputs validated and sanitized?
- Is output properly escaped (esc_html, esc_attr, esc_url, wp_kses)?
- Are SQL queries using prepared statements with $wpdb->prepare()?
- Check for SQL injection vulnerabilities
- Are nonces used for form submissions and AJAX requests?
- Are capability checks (current_user_can) in place for privileged operations?

**WordPress Security:**
- Direct file access prevented with defined('ABSPATH') checks?
- Are file uploads validated (type, size, content)?
- Is sensitive data exposed in debug logs or error messages?
- Are API endpoints properly authenticated?
- Check for XSS vulnerabilities
- Check for CSRF vulnerabilities

**Data Handling:**
- Is sensitive data (passwords, tokens) properly handled?
- Are file permissions considered for created files?
- Are REST API endpoints secured with permission callbacks?

#### Performance Review

**Database Queries:**
- Are queries optimized? Check for N+1 query problems
- Are proper indexes likely being used?
- Are query results cached where appropriate?
- Avoid using SELECT *; specify needed columns
- Check for slow queries (JOINs on large tables, missing WHERE clauses)

**WordPress Performance:**
- Are transients used for caching expensive operations?
- Are assets (JS/CSS) properly enqueued and minified?
- Are hooks used efficiently (avoid expensive operations in frequently-called hooks)?
- Are large datasets paginated?
- Check for memory-intensive operations

**Code Efficiency:**
- Avoid unnecessary loops or computations
- Use WordPress helper functions (wp_list_pluck, wp_cache_*)
- Check for repeated database queries that could be batched
- Are large arrays or objects handled efficiently?

#### Code Style Review

**WordPress Coding Standards:**
- Follow WordPress PHP Coding Standards
- Proper indentation and spacing
- Curly brackets usage (always use, even for single statements)
- Naming conventions (snake_case for functions/variables)
- Proper prefixing (sh_, simplehistory_, simple_history_)

**Project-Specific Standards (from AGENTS.md):**
- Happy path last (errors first, success last)
- Avoid else - use early returns
- Separate conditions (multiple if statements vs compound)
- Multi-line ternary operators (unless very short)
- Active tone for logger messages ("Activated plugin" not "Plugin was activated")

**Code Quality:**
- Is code DRY (Don't Repeat Yourself)?
- Are functions focused and single-purpose?
- Are magic numbers avoided (use constants)?
- Is code well-documented with PHPDoc?
- Are variable names descriptive?
- No commented-out code blocks
- Proper error handling

**PHP Compatibility:**
- PHP 7.4+ compatibility maintained
- No mb_* string functions used
- Short array syntax ([] not array())

**JavaScript/CSS (if applicable):**
- Follow @wordpress/scripts conventions
- SuitCSS naming for CSS with 'sh' prefix
- Proper escaping in inline scripts

#### WordPress Best Practices

**Plugin Standards:**
- Text domain 'simple-history' used consistently
- Translations ready (__(), _e(), etc.)
- WordPress hooks properly prefixed
- Use WordPress APIs over direct implementations
- Follow WordPress action/filter patterns
- Proper use of wp_enqueue_* for assets

**Data Storage:**
- Custom tables vs options vs post meta - appropriate choice?
- Proper use of WordPress schema
- Database migrations handled correctly

### Step 4: Check for test coverage

- Do modified files have corresponding tests?
- Are new features covered by tests?
- Suggest test cases for untested critical paths

### Step 5: Run automated checks

If time permits, run:
```bash
# PHP linting
npm run php:lint

# PHPStan static analysis
npm run php:phpstan

# JavaScript linting
npm run lint:js
```

### Step 6: Provide comprehensive review report

Structure your findings as:

## Code Review Report for Issue #X

### Overview
[Brief summary of what changed and overall code quality]

### Critical Issues (Must Fix)
[Security vulnerabilities, major performance issues, breaking changes]

### Recommended Improvements
[Performance optimizations, code style issues, best practice violations]

### Minor Suggestions
[Nice-to-haves, refactoring opportunities]

### Positive Observations
[What was done well - good patterns, clean code, proper security measures]

### Testing Recommendations
[Suggested test cases or areas needing test coverage]

### Summary
[Overall assessment and recommendation: approve, approve with changes, or request changes]

---

Be thorough but constructive. Provide specific file locations and line references (file_path:line_number). Suggest concrete solutions, not just problems. Remember: your goal is to help ship secure, performant, maintainable WordPress code.
