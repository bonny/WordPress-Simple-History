# Simple History Plugin Performance Analysis

## Overview

Profiling was done using PHP-SPX on a WordPress installation with the Simple History plugin. The goal is to identify optimization opportunities within the plugin code, especially when it comes to loading files (autoloading).

This is a summary imported from Claude Desktop, which does not have access to the plugin source code. Be critical of the findings!

## Simple History Performance Impact

The plugin currently accounts for approximately **3.5-4% of total page load time**:

| Function                                          | Exclusive Time | Duration | Calls |
| ------------------------------------------------- | -------------- | -------- | ----- |
| `Simple_History\Autoloader::require_file@1`       | 2.47%          | 1.75ms   | 110   |
| `Simple_History\Simple_History::get_services`     | 1.08%          | 761µs    | 1     |
| `Simple_History\Simple_History::get_core_dropins` | 0.54%          | 385µs    | 1     |
| Plugin index.php                                  | 0.49%          | 346µs    | 1     |

## Key Finding: Autoloader is the Biggest Opportunity

The autoloader loads **110 files** on every request, taking 1.75ms. This is the primary optimization target.

## Recommended Optimizations

### 1. Lazy-load Loggers and Services

Instead of loading all loggers at initialization, defer loading until they're actually needed:

-   Only instantiate loggers when logging actually occurs
-   Use a registry pattern that loads logger classes on-demand
-   Consider which loggers are needed on frontend vs admin

### 2. Optimize the Autoloader

Options to explore:

-   If using Composer: run `composer dump-autoload --optimize --classmap-authoritative`
-   Consider switching from PSR-4 to classmap autoloading for production
-   Reduce the number of files that need to be autoloaded at bootstrap

### 3. Cache `get_core_dropins` and `get_services` Results

If these methods do filesystem lookups or reflection:

-   Cache results in a transient or object cache
-   Invalidate cache only when plugins are activated/deactivated

### 4. Defer Initialization

Consider hooking initialization to a later hook if possible:

-   Move heavy initialization from `plugins_loaded` to `init` or later
-   Only initialize admin-specific code when `is_admin()` is true
-   Skip unnecessary initialization on AJAX/REST requests if not needed

## Files to Investigate

1. **Autoloader class** — Look for opportunities to reduce file loading
2. **`get_core_dropins()` method** — Check if it does filesystem operations that could be cached
3. **`get_services()` method** — Evaluate if all services need to load on every request
4. **Logger initialization** — Determine if loggers can be lazy-loaded

## Context: Overall WordPress Performance

For reference, the biggest time consumers in the full profile were WordPress core functions:

-   `wp_json_file_decode` — 8.49% (JSON file parsing)
-   `wpdb::_do_query` — 7.87% (database queries)
-   `apply_filters` — 3.59% (13K filter calls)

Simple History's 3.5-4% is comparable to these core operations, so optimization here will have meaningful impact.

---

## Implementation: Classmap Autoloader (January 2026)

### What Was Done

Implemented an optional classmap-based autoloader that eliminates filesystem checks during class loading.

**Problem**: The original autoloader tries up to 4 file patterns per class lookup (`class-`, `interface-`, `trait-`, direct), each requiring a `file_exists()` call. With ~140 classes loaded, this means ~440+ filesystem operations per request.

**Solution**: Generate a static PHP array mapping class names directly to file paths. A single `isset()` check replaces all the `file_exists()` calls.

### Files Created

| File                               | Purpose                                                   |
| ---------------------------------- | --------------------------------------------------------- |
| `inc/class-classmap-generator.php` | Scans codebase using PHP tokenizer and generates classmap |
| `inc/classmap-generated.php`       | Generated class→file mapping (142 classes, gitignored)    |
| `scripts/generate-classmap.php`    | Standalone build script                                   |

### Files Modified

| File                       | Changes                                                |
| -------------------------- | ------------------------------------------------------ |
| `inc/class-autoloader.php` | Added classmap lookup before filesystem checks         |
| `index.php`                | Enable classmap based on `SIMPLE_HISTORY_USE_CLASSMAP` |
| `package.json`             | Added `npm run classmap:generate` script               |
| `.gitignore`               | Added `inc/classmap-generated.php`                     |

### Usage

```bash
# Generate the classmap (uses Docker for PHP 7.4 compatibility)
npm run classmap:generate
```

```php
// Enable the optimized autoloader by adding to wp-config.php:
define( 'SIMPLE_HISTORY_USE_CLASSMAP', true );

// To disable, remove or set to false:
define( 'SIMPLE_HISTORY_USE_CLASSMAP', false );
```

### Technical Details

-   Uses PHP's `token_get_all()` for reliable class/namespace extraction (not regex)
-   Uses `var_export()` for generating valid PHP array syntax
-   Compatible with PHP 7.4+ (handles `T_NAME_QUALIFIED` token conditionally)
-   Classmap is checked first; falls back to standard autoloader for unknown classes
-   Feature flag allows A/B testing performance impact

### Expected Improvement

| Metric                | Before           | After (classmap enabled)  |
| --------------------- | ---------------- | ------------------------- |
| `file_exists()` calls | ~440 per request | 1 (loading classmap file) |
| Autoloader time       | 1.75ms           | ~0.1ms (estimated)        |
| Page load impact      | 2.47%            | ~0.15% (estimated)        |

### Next Steps

1. Profile with PHP-SPX to measure actual improvement
2. Consider making classmap the default for production releases
3. Investigate `get_services()` and `get_core_dropins()` glob() calls (items 3 & 4 in original recommendations)

