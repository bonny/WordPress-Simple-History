# PHP-SPX Profiling Reference

This document provides reference information for using PHP-SPX with Simple History development.

## Overview

PHP-SPX is a PHP profiling extension with a built-in web UI. It's available in wp-env environments.

-   **GitHub**: https://github.com/NoiseByNorthwest/php-spx
-   **wp-env docs**: https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-spx-profiling

## Accessing SPX

### Web UI

Access the SPX web UI at:

```
http://localhost:8888/?SPX_UI_URI=
```

Required cookies:

-   `SPX_KEY=dev` - Authentication key
-   `SPX_ENABLED=1` - Enable profiling

### Enabling Profiling

Set these cookies in your browser or API requests:

```
SPX_KEY=dev
SPX_ENABLED=1
SPX_AUTO_START=1
```

## CLI Profiling with WP-CLI

SPX can profile WP-CLI commands, but there's a catch: the wp-env `cli` container does **not** have SPX installed. SPX is only available in the `wordpress` and `tests-wordpress` containers.

### Setup

Download WP-CLI to the WordPress container (persists until container recreation):

```bash
npx wp-env run wordpress -- sh -c "curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar"
```

### Running Profiled Commands

Use environment variables to enable SPX:

```bash
# Flat profile (outputs to stderr) - best for quick analysis
npx wp-env run wordpress -- sh -c "SPX_ENABLED=1 SPX_REPORT=flat php wp-cli.phar plugin list 2>&1"

# Full profile (stored for web UI viewing)
npx wp-env run wordpress -- sh -c "SPX_ENABLED=1 SPX_REPORT=full php wp-cli.phar simple-history list --count=10"
```

### SPX Environment Variables for CLI

| Variable          | Values                  | Description                           |
| ----------------- | ----------------------- | ------------------------------------- |
| `SPX_ENABLED`     | `1`                     | Enable profiling (required)           |
| `SPX_REPORT`      | `flat`, `full`, `trace` | Output format (see below)             |
| `SPX_FP_LIMIT`    | Number                  | Limit flat profile rows (default: 10) |
| `SPX_BUILTINS`    | `1`                     | Include internal PHP functions        |
| `SPX_FP_FOCUS`    | `wt`, `ct`, `zm`, etc.  | Focus metric (wall time, cpu, memory) |
| `SPX_FP_INC`      | `0`                     | Show self time only (not inclusive)   |
| `SPX_TRACE_DEPTH` | Number                  | Max call depth for trace (default: 0) |

**Report types:**

-   `flat` - Outputs summary table to stderr (best for quick CLI analysis)
-   `full` - Stores profile in SPX data directory for web UI viewing
-   `trace` - Creates `spx_trace.txt.gz` file in current directory

### Example: Profile Simple History Command

```bash
npx wp-env run wordpress -- sh -c "SPX_ENABLED=1 SPX_REPORT=flat SPX_FP_LIMIT=15 php wp-cli.phar simple-history list --count=5 2>&1"
```

Sample output:

```
*** SPX Report ***

Global stats:

  Called functions    :    56.8K
  Distinct functions  :     2.0K

  Wall time           :  469.9ms
  ZE memory usage     :   52.5MB

Flat profile:

 Wall time           | ZE memory usage     |
 Inc.     | *Exc.    | Inc.     | Exc.     | Called   | Function
----------+----------+----------+----------+----------+----------
  300.2ms |  156.8ms |   34.3MB |   21.6MB |        1 | /var/www/html/wp-settings.php
   67.9ms |   67.5ms |    5.1MB |    5.1MB |      152 | 1@Simple_History\Autoloader::require_file
   20.0ms |   19.1ms |    2.6MB |    2.5MB |        1 | /var/www/html/wp-admin/includes/admin.php
```

### Why Not Use `wp-env run cli`?

The `cli` container is a separate image optimized for WP-CLI but without SPX:

```bash
# This shows SPX is NOT available in the cli container
npx wp-env run cli php -m | grep SPX  # (no output)

# SPX is available in the wordpress container
npx wp-env run wordpress -- sh -c "php -m | grep SPX"  # SPX
```

## Web Request Profiling

For web requests, SPX stores profiles for viewing in the web UI rather than outputting text to the browser.

### Enable Profiling in Browser

Set these cookies in your browser (using DevTools or a cookie extension):

```
SPX_KEY=dev
SPX_ENABLED=1
```

Then browse the site normally. Each page load creates a profile.

### View Profiles in Web UI

Open the SPX web UI to see flame graphs, timelines, and function tables:

```
http://localhost:8888/?SPX_UI_URI=
```

The web UI provides:

-   **Flame graph** - Visual call stack with time proportions
-   **Timeline** - Function execution over time
-   **Flat profile** - Sortable table of functions by time/memory
-   **Call tree** - Hierarchical view of function calls

### Quick Profile Summary via Terminal

List recent profiles with timing info:

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/reports/metadata' -b 'SPX_KEY=dev' | \
  python3 -c "
import sys, json
for r in json.load(sys.stdin)['results'][:10]:
    wt = r['wall_time_ms'] / 1000
    mem = r['peak_memory_usage'] / 1024 / 1024
    uri = r.get('http_request_uri', 'CLI')[:40]
    cli = 'CLI' if r['cli'] else 'WEB'
    print(f'{cli} | {wt:>7.1f}ms | {mem:>5.1f}MB | {uri}')
"
```

Sample output:

```
WEB |   196.2ms |   9.6MB | /?test=spx
WEB |    98.4ms |   8.9MB | /index.php?rest_route=%2Felementor%2Fv1%2F
WEB |   228.1ms |   8.9MB | /index.php?rest_route=%2Fwp%2Fv2%2Fusers%2F
WEB |    77.5ms |   5.8MB | /wp-admin/admin-ajax.php
WEB |    22.2ms |   3.0MB | /wp-admin/
```

### Profile via curl

```bash
# Make a profiled request
curl -s 'http://localhost:8888/wp-admin/' -b 'SPX_KEY=dev;SPX_ENABLED=1' > /dev/null

# Then view in web UI or fetch via API
```

### CLI vs Web Profiling Comparison

| Feature     | CLI (`SPX_REPORT=flat`) | Web Requests      |
| ----------- | ----------------------- | ----------------- |
| Output      | stderr (terminal)       | Stored for web UI |
| View method | Immediate in terminal   | Web UI or API     |
| Flame graph | No                      | Yes               |
| Best for    | Quick checks            | Detailed analysis |

## API Endpoints

All endpoints require the `SPX_KEY=dev` cookie.

### List Available Metrics

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/metrics' -b 'SPX_KEY=dev'
```

Returns JSON array of available metrics:

-   `wt` - Wall time
-   `ct` - CPU time
-   `it` - Idle time
-   `zm` - Zend Engine memory usage
-   `zmac` - ZE allocation count
-   `zo` - ZE object count
-   And more...

### List All Profiling Reports

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/reports/metadata' -b 'SPX_KEY=dev'
```

Returns JSON array of captured profiles with:

-   `key` - Unique report identifier
-   `exec_ts` - Execution timestamp
-   `http_request_uri` - Request URI
-   `wall_time_ms` - Total wall time in milliseconds
-   `peak_memory_usage` - Peak memory in bytes
-   `called_function_count` - Number of unique functions
-   `call_count` - Total function calls

### Get Report Metadata

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/reports/metadata/{key}' -b 'SPX_KEY=dev'
```

### Get Full Profile Data

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/reports/get/{key}' -b 'SPX_KEY=dev' | gzip -d
```

**Note**: Profile data is gzip-compressed.

## Profile Data Format

The decompressed profile data is a text format with two sections:

### Events Section

```
[events]
func_id event_type timestamp memory
0 1 0 0
1 1 244594 304
2 1 275022 720
...
```

Columns:

1. `func_id` - Index into the functions list
2. `event_type` - `1` = function enter, `0` = function exit
3. `timestamp` - Nanoseconds since request start
4. `memory` - Current memory usage in bytes

### Functions Section

```
[functions]
/var/www/html/index.php
/var/www/html/wp-blog-header.php
wpdb::query
WP_Hook::apply_filters
...
```

Function names are listed by index (0-based). Can be:

-   File paths (for file includes)
-   `Class::method` (for methods)
-   `function_name` (for functions)

## Analysis Scripts

### Python Script: Top Functions by Inclusive Time

```python
import sys

events = []
functions = []
in_events = False
in_functions = False

with open('/tmp/spx_profile.txt', 'r') as f:
    for line in f:
        line = line.strip()
        if line == '[events]':
            in_events = True
            in_functions = False
            continue
        elif line == '[functions]':
            in_events = False
            in_functions = True
            continue

        if in_events and line:
            parts = line.split()
            if len(parts) == 4:
                func_id, event_type, timestamp, memory = int(parts[0]), int(parts[1]), int(parts[2]), int(parts[3])
                events.append((func_id, event_type, timestamp, memory))
        elif in_functions and line:
            functions.append(line)

# Calculate inclusive time per function
func_times = {}
call_stack = []

for func_id, event_type, timestamp, memory in events:
    if event_type == 1:  # Enter
        call_stack.append((func_id, timestamp))
    elif event_type == 0:  # Exit
        if call_stack and call_stack[-1][0] == func_id:
            start_time = call_stack.pop()[1]
            duration = timestamp - start_time
            if func_id not in func_times:
                func_times[func_id] = {'inclusive': 0, 'count': 0}
            func_times[func_id]['inclusive'] += duration
            func_times[func_id]['count'] += 1

# Sort and display
sorted_funcs = sorted(func_times.items(), key=lambda x: x[1]['inclusive'], reverse=True)

for func_id, data in sorted_funcs[:30]:
    if func_id < len(functions):
        func_name = functions[func_id]
        time_ms = data['inclusive'] / 1_000_000
        print(f"{func_name}: {time_ms:.2f}ms ({data['count']} calls)")
```

### Bash One-liner: Fetch and Save Profile

```bash
curl -s 'http://localhost:8888/?SPX_UI_URI=/data/reports/get/{key}' \
  -b 'SPX_KEY=dev' | gzip -d > /tmp/spx_profile.txt
```

## Typical Profile Results (Simple History)

From homepage load analysis (118ms total):

| Function                              | Time (ms) | Calls | Notes              |
| ------------------------------------- | --------- | ----- | ------------------ |
| WP_Block::render                      | 67.67     | 74    | Block rendering    |
| WP_Hook::apply_filters                | 56.71     | 1127  | WordPress hooks    |
| do_action                             | 44.49     | 223   | Action hooks       |
| do_blocks                             | 30.78     | 8     | Block processing   |
| Simple_History\Autoloader::load_class | 12.77     | 94    | Plugin autoloading |
| wpdb::query                           | 10.74     | 32    | Database queries   |

Simple History accounts for ~3-4% of total page load when OPcache is warm.

## Tips

1. **Warm OPcache first** - Load the page 2-3 times before profiling
2. **Use the web UI** - The flame graph is excellent for visual analysis
3. **Compare profiles** - Save baseline profiles before making changes
4. **Focus on exclusive time** - High inclusive time might be due to children

## See Also

-   [Performance Analysis](../readme.issue-performance.md) - Detailed optimization findings
-   [wp-env SPX docs](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/#using-spx-profiling)
