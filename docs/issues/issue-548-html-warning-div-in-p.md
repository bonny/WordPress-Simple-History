# Issue #548: HTML warning from DefaultFilters.jsx: `<div> cannot appear as a descendant of <p>`

**Status:** In Progress

## Problem

Browser console shows warning: `Warning: validateDOMNesting(...): <div> cannot appear as a descendant of <p>.`

The issue is in `src/components/DefaultFilters.jsx:104` where the HTML structure is `p > div`, which is invalid HTML.

## Proposed Solution

Change the structure from `<p>` → `<div>` to use WordPress components `Flex` → `FlexItem` structure instead.

## Steps to Reproduce

1. Go to '/admin.php?page=simple_history_admin_menu_page'
2. Open browser console
3. See warning

## Expected Behavior

No DOM nesting warning should appear, and the HTML should be valid.

## Files to Review

- `src/components/DefaultFilters.jsx` - Line 104 and surrounding structure

## Progress

- [x] Review current DefaultFilters.jsx structure
- [x] Replace `<p>` with valid structure (div)
- [x] Install and configure `eslint-plugin-validate-jsx-nesting` to prevent future issues
- [ ] Test in browser to verify warning is gone
- [ ] Verify visual appearance is unchanged

## Solution Implemented

### Code Changes
- Changed `<p>` wrapper elements to `<div>` on lines 110 and 128
- This fixes 3 ESLint errors that were detected by the new linting rule

### Automation Added
- Installed `eslint-plugin-validate-jsx-nesting` package
- Updated `.eslintrc` with new rule: `validate-jsx-nesting/no-invalid-jsx-nesting`
- This will automatically catch invalid HTML nesting in all JSX files going forward

### Files Modified
- `src/components/DefaultFilters.jsx` - Fixed invalid nesting
- `.eslintrc` - Added new validation rule
- `package.json` - Added new dev dependency
