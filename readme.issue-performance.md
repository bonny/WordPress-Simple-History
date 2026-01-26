# Simple History Plugin Performance Analysis

## Overview

Profiling was done using PHP-SPX on a WordPress installation with the Simple History plugin. The goal is to identify optimization opportunities within the plugin code, especially when it comes to loading files (autoloading).

## Final Results

After extensive testing, we implemented targeted optimizations that simplify code without adding complexity:

### Optimizations Kept

1. **Early return for non-Simple_History classes in autoloader** - Avoids processing unrelated classes from other plugins
2. **`strtr()` for faster string conversion** - Single-pass underscore-to-hyphen conversion in `load_mapped_file()`
3. **Hardcoded arrays replacing `glob()` calls** - `get_services()` and `get_core_dropins()` now use static `::class` syntax
4. **`Helpers::interpolate()` in Channels Manager** - Uses efficient `strtr()` instead of loop with `str_replace()`

### Classmap Autoloader - Removed

The classmap autoloader was implemented and tested but ultimately **removed** because:

- Apache Bench testing showed <1% improvement with OPcache enabled
- Added complexity (regeneration after code changes, case-insensitive lookup)
- Extra build step and npm script not justified for marginal gains

**Key insight**: OPcache makes the classmap's benefit negligible. The filesystem overhead that PHP-SPX measured (~1.75ms) gets absorbed into overall latency when OPcache is warm.

---

## Developer Testing and Results

### PHP-SPX Micro-level Results

```
get_core_dropins()
before optimization: inc: 0.55% - 0.85%, 389 ms - 446 ms
after optimization: inc: 0%, 1 ms

get_services()
before optimization: inc: 1.08% - 1.02%, 761 ms - 537 ms
after optimization: inc: 0%, 248 ms - 397 ns (nano seconds!)
```

### Apache Bench Results Summary

| Configuration | Requests/sec | Mean Time | Notes |
|--------------|--------------|-----------|-------|
| Main branch (baseline) | 10.57 | 94.6ms | 1000 requests |
| Without classmap | 10.71 | 93.4ms | ~1.3% faster |
| With classmap | 10.78 | 92.8ms | ~2% faster |

**Conclusion**: The difference is within measurement noise. OPcache dominates performance.

### Lesson Learned: Debug Logging Overhead

During testing, we discovered a **40% performance regression** caused by `sh_error_log()` debug calls in the autoloader. Each call writes to the PHP error log (disk I/O), and the early return log was triggered for EVERY non-Simple_History class (hundreds per request).

**Fix**: Removed all debug logging from the autoloader. Performance immediately returned to baseline.

---

## Apache Bench Test Results (Full Data)

Running: `ab -n100 -c3 http://wordpress-stable-docker-mariadb.test:8282/`

### Before all optimizations (main branch):

```
Requests per second:    22.08 [#/sec] (mean)
Time per request:       135.876 [ms] (mean)

Connection Times (ms)
              min  mean[+/-sd] median   max
Processing:   113  132  12.5    129     183
```

### After optimizations (with debug logging - BAD):

```
Requests per second:    15.61 [#/sec] (mean)
Time per request:       192.214 [ms] (mean)

Connection Times (ms)
              min  mean[+/-sd] median   max
Processing:   146  189  20.3    181     241
```

### After removing debug logging (GOOD):

```
Requests per second:    21.63 [#/sec] (mean)
Time per request:       138.708 [ms] (mean)

Connection Times (ms)
              min  mean[+/-sd] median   max
Processing:   110  135  15.1    134     204
```

### With 1000 requests (more stable results):

**Main branch:**
```
Requests per second:    10.57 [#/sec] (mean)
Time per request:       94.638 [ms] (mean)
```

**Performance branch (final):**
```
Requests per second:    10.71 [#/sec] (mean)
Time per request:       93.412 [ms] (mean)
```

---

## Simple History Performance Impact

The plugin accounts for approximately **3.5-4% of total page load time** (before optimizations):

| Function | Exclusive Time | Duration | Calls |
|----------|----------------|----------|-------|
| `Autoloader::require_file` | 2.47% | 1.75ms | 110 |
| `get_services` | 1.08% | 761µs | 1 |
| `get_core_dropins` | 0.54% | 385µs | 1 |
| Plugin index.php | 0.49% | 346µs | 1 |

---

## Implemented Optimizations

### 1. Early Return for Non-Simple_History Classes

Added at the start of `load_class()`:

```php
if ( ! str_starts_with( $class_name, 'Simple_History\\' )
     && ! str_starts_with( $class_name, 'SimpleHistory' )
     && ! str_starts_with( $class_name, 'SimpleLogger' ) ) {
    return false;
}
```

This ensures other plugins' classes (VaultPress, ActionScheduler, etc.) don't trigger unnecessary processing.

### 2. Hardcoded Class Arrays (Replacing glob())

**Before:**
```php
$service_files = glob( $services_dir . '/*.php' );
foreach ( $service_files as $file ) {
    $class_name = str_replace( 'class-', '', basename( $file, '.php' ) );
    $class_name = ucwords( $class_name, '_' );  // REST_API → Rest_Api (wrong!)
}
```

**After:**
```php
private function get_services() {
    $services = array(
        Services\AddOns_Licences::class,
        Services\REST_API::class,           // Exact class name
        Services\Setup_Purge_DB_Cron::class,
        // ...
    );
    return apply_filters( 'simple_history/core_services', $services );
}
```

**Benefits:**
- Zero filesystem I/O (was 2 directory scans)
- Zero string manipulation (was ~40 operations)
- Correct class names (no more ucwords case issues)
- Opcache friendly (pre-compiled)

### 3. Efficient String Replacement in Autoloader

Changed `load_mapped_file()` to use `strtr()`:

```php
$path_lowercased = strtolower( strtr( $path_and_file, '_', '-' ) );
```

### 4. Helpers::interpolate() in Channels Manager

Changed from loop with `str_replace()` to single `strtr()` call via existing helper.

---

## What Was Tried and Discarded

### Classmap Autoloader

A static classmap was generated mapping class names to file paths. While PHP-SPX showed micro-level improvement (~1.75ms → ~0.1ms), Apache Bench showed <1% real-world improvement.

**Why it didn't help as expected:**
1. OPcache already caches file lookups
2. The classmap added complexity (regeneration, case-insensitive lookup)
3. Build step overhead wasn't justified

**Files removed:**
- `inc/class-classmap-generator.php`
- `inc/classmap-generated.php`
- `scripts/generate-classmap.php`
- `npm run classmap:generate` script

---

## Maintenance Notes

When adding new services or dropins, update the hardcoded arrays in:
- `get_services()` for new services
- `get_core_dropins()` for new dropins

This is a minor trade-off for eliminating filesystem operations.

---

## Lessons Learned

1. **Debug logging is expensive** - Never leave debug logs in hot paths
2. **Micro-benchmarks don't reflect real-world** - PHP-SPX showed 94% improvement, Apache Bench showed <1%
3. **OPcache changes everything** - Many optimizations become irrelevant with warm cache
4. **Simpler is better** - The hardcoded arrays are easier to maintain than classmap generation
5. **Test with realistic load** - 1000 requests gives more stable results than 100
