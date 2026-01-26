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
| `inc/classmap-generated.php`       | Generated class→file mapping (committed to repo)          |
| `scripts/generate-classmap.php`    | Standalone build script                                   |

### Files Modified

| File                       | Changes                                                |
| -------------------------- | ------------------------------------------------------ |
| `inc/class-autoloader.php` | Added classmap lookup before filesystem checks         |
| `index.php`                | Enable classmap based on `SIMPLE_HISTORY_USE_CLASSMAP` |
| `package.json`             | Added `npm run classmap:generate` script               |

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

### Additional Optimizations

#### Early Return for Non-Simple_History Classes

**Problem discovered**: Every plugin's classes (VaultPress, ActionScheduler, etc.) were hitting the Simple History autoloader, causing unnecessary processing even when the classmap was enabled.

**Solution**: Added early return at the start of `load_class()` to immediately skip classes that aren't in the Simple_History namespace:

```php
if ( ! str_starts_with( $class_name, 'Simple_History\\' )
     && ! str_starts_with( $class_name, 'SimpleHistory' )
     && ! str_starts_with( $class_name, 'SimpleLogger' ) ) {
    return false;
}
```

This ensures other autoloaders handle their own classes without any overhead from Simple History.

#### Case-Insensitive Class Name Lookup

**Problem discovered**: PHP class names are case-insensitive, but array keys are not. The `get_services()` method dynamically generates class names from filenames using `ucwords()`, which converts `REST_API` to `Rest_Api`. The classmap lookup failed because the actual class name is `REST_API`.

**Solution**: Added a lowercase lookup map that maps `strtolower(class_name)` to actual class names:

```php
$this->classmap_lowercase = array_combine(
    array_map( 'strtolower', array_keys( $classmap ) ),
    array_keys( $classmap )
);
```

The autoloader now does:
1. Direct lookup (exact case match) - fastest path
2. Case-insensitive lookup - handles dynamically generated names
3. Filesystem fallback - for classes not in classmap (e.g., add-on plugins)

### Verification

Debug logging confirms the optimization is working correctly:

```
# Core plugin classes load from classmap (direct match)
Loading class from classmap: Simple_History\Autoloader -> .../inc/class-autoloader.php
Loading class from classmap: Simple_History\Simple_History -> .../inc/class-simple-history.php

# Dynamic class names load via case-insensitive lookup
Loading class from classmap (case-insensitive): Simple_History\Services\Rest_Api -> .../inc/services/class-rest-api.php

# Add-on classes fall through to filesystem (expected - separate plugins)
Performance warning: Loading class from filesystem: Simple_History\Debug_And_Monitor
```

External plugin classes (VaultPress, ActionScheduler, etc.) no longer appear in the logs because the early return prevents them from being processed.

### Expected Improvement

| Metric                | Before           | After (classmap enabled)  |
| --------------------- | ---------------- | ------------------------- |
| `file_exists()` calls | ~440 per request | 1 (loading classmap file) |
| Autoloader time       | 1.75ms           | ~0.1ms (estimated)        |
| Page load impact      | 2.47%            | ~0.15% (estimated)        |

### Status

✅ **Implementation complete** - The classmap autoloader is working correctly with:
- Direct classmap lookup for exact case matches
- Case-insensitive lookup for dynamically generated class names
- Early return for non-Simple_History classes
- Filesystem fallback for add-on plugin classes

### Next Steps

1. Profile with PHP-SPX to measure actual improvement
2. Consider making classmap the default for production releases (remove feature flag)
3. Investigate `get_services()` and `get_core_dropins()` glob() calls (items 3 & 4 in original recommendations)

