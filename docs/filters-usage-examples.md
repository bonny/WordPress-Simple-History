# Filter Usage Examples

This document demonstrates how to use both positive (inclusion) and negative (exclusion) filters in Simple History.

## Table of Contents

- [PHP Log_Query Examples](#php-log_query-examples)
- [REST API Examples](#rest-api-examples)
- [WP-CLI Examples](#wp-cli-examples)
- [Filter Combinations](#filter-combinations)
- [Available Filters Reference](#available-filters-reference)

## PHP Log_Query Examples

### Basic Positive Filtering (Inclusion)

```php
// Get only info and warning level events
$log_query = new \Simple_History\Log_Query();
$results = $log_query->query([
    'posts_per_page' => 50,
    'loglevels' => ['info', 'warning'],
]);

// Get events from specific loggers
$results = $log_query->query([
    'posts_per_page' => 50,
    'loggers' => ['SimpleUserLogger', 'SimplePluginLogger'],
]);

// Search for events containing "updated"
$results = $log_query->query([
    'posts_per_page' => 50,
    'search' => 'updated',
]);

// Get events from user ID 5
$results = $log_query->query([
    'posts_per_page' => 50,
    'user' => 5,
]);
```

### Basic Negative Filtering (Exclusion)

```php
// Exclude debug level events
$log_query = new \Simple_History\Log_Query();
$results = $log_query->query([
    'posts_per_page' => 50,
    'exclude_loglevels' => ['debug'],
]);

// Exclude events from SimpleUserLogger
$results = $log_query->query([
    'posts_per_page' => 50,
    'exclude_loggers' => ['SimpleUserLogger'],
]);

// Exclude events containing "cron"
$results = $log_query->query([
    'posts_per_page' => 50,
    'exclude_search' => 'cron',
]);

// Exclude events from user ID 1 (admin)
$results = $log_query->query([
    'posts_per_page' => 50,
    'exclude_user' => 1,
]);

// Exclude WordPress-initiated events (cron jobs, automatic updates)
$results = $log_query->query([
    'posts_per_page' => 50,
    'exclude_initiator' => 'wp',
]);
```

### Combining Positive and Negative Filters

```php
// Get info events, but exclude those containing "cron"
$log_query = new \Simple_History\Log_Query();
$results = $log_query->query([
    'posts_per_page' => 50,
    'loglevels' => ['info'],
    'exclude_search' => 'cron',
]);

// Get plugin events, but exclude SimpleUserLogger
$results = $log_query->query([
    'posts_per_page' => 50,
    'loggers' => ['SimplePluginLogger', 'SimpleUserLogger'],
    'exclude_loggers' => ['SimpleUserLogger'], // Exclusion wins!
]);
// Result: Only SimplePluginLogger events

// Get all events except debug and WordPress system events
$results = $log_query->query([
    'posts_per_page' => 100,
    'exclude_loglevels' => ['debug'],
    'exclude_initiator' => 'wp',
]);
```

### Advanced Examples

```php
// Clean event log: exclude debug, CLI, and WordPress system events
$log_query = new \Simple_History\Log_Query();
$results = $log_query->query([
    'posts_per_page' => 100,
    'exclude_loglevels' => ['debug'],
    'exclude_initiator' => ['wp_cli', 'wp'],
    'exclude_search' => 'action_scheduler',
]);

// User activity only (exclude system events)
$results = $log_query->query([
    'posts_per_page' => 50,
    'initiator' => 'wp_user',
    'exclude_loglevels' => ['debug'],
]);

// Important events only (errors and warnings, no debug/info)
$results = $log_query->query([
    'posts_per_page' => 50,
    'loglevels' => ['error', 'warning', 'critical', 'alert', 'emergency'],
    // Or use exclusion:
    // 'exclude_loglevels' => ['debug', 'info'],
]);

// Date range with exclusions
$results = $log_query->query([
    'posts_per_page' => 100,
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'exclude_search' => 'cron',
    'exclude_loglevels' => ['debug'],
]);

// Exclude multiple users
$results = $log_query->query([
    'posts_per_page' => 100,
    'exclude_users' => [1, 2, 3], // Exclude admin users
]);

// Exclude specific logger messages
$results = $log_query->query([
    'posts_per_page' => 100,
    'exclude_messages' => [
        'SimplePluginLogger:plugin_activated',
        'SimplePluginLogger:plugin_deactivated',
    ],
]);
```

## REST API Examples

### Basic GET Requests

```bash
# Base endpoint
BASE_URL="http://your-site.com/wp-json/simple-history/v1/events"

# Authentication (use Application Password)
AUTH="username:application-password"
```

### Positive Filtering

```bash
# Get only info level events
curl -u "$AUTH" "$BASE_URL?loglevels[]=info&per_page=50"

# Get events from specific loggers
curl -u "$AUTH" "$BASE_URL?loggers[]=SimpleUserLogger&loggers[]=SimplePluginLogger&per_page=50"

# Search for "updated"
curl -u "$AUTH" "$BASE_URL?search=updated&per_page=50"

# Get events from user ID 5
curl -u "$AUTH" "$BASE_URL?user=5&per_page=50"

# Get events from specific initiators
curl -u "$AUTH" "$BASE_URL?initiator[]=wp_user&per_page=50"
```

### Negative Filtering

```bash
# Exclude debug events
curl -u "$AUTH" "$BASE_URL?exclude_loglevels[]=debug&per_page=50"

# Exclude multiple log levels
curl -u "$AUTH" "$BASE_URL?exclude_loglevels[]=debug&exclude_loglevels[]=info&per_page=50"

# Exclude events from specific loggers
curl -u "$AUTH" "$BASE_URL?exclude_loggers[]=SimpleUserLogger&per_page=50"

# Exclude events containing "cron"
curl -u "$AUTH" "$BASE_URL?exclude_search=cron&per_page=50"

# Exclude events from user ID 1
curl -u "$AUTH" "$BASE_URL?exclude_user=1&per_page=50"

# Exclude multiple users
curl -u "$AUTH" "$BASE_URL?exclude_users[]=1&exclude_users[]=2&per_page=50"

# Exclude WordPress-initiated events (cron jobs, automatic updates)
curl -u "$AUTH" "$BASE_URL?exclude_initiator=wp&per_page=50"

# Exclude multiple initiators
curl -u "$AUTH" "$BASE_URL?exclude_initiator[]=wp&exclude_initiator[]=wp_cli&per_page=50"
```

### Combined Filtering

```bash
# Info events without cron
curl -u "$AUTH" "$BASE_URL?loglevels[]=info&exclude_search=cron&per_page=50"

# All events except debug and CLI
curl -u "$AUTH" "$BASE_URL?exclude_loglevels[]=debug&exclude_initiator=wp_cli&per_page=50"

# Date range with exclusions
curl -u "$AUTH" "$BASE_URL?date_from=2024-01-01&date_to=2024-12-31&exclude_search=cron&exclude_loglevels[]=debug&per_page=100"

# Complex filter: user events only, no debug, no cron
curl -u "$AUTH" "$BASE_URL?initiator=wp_user&exclude_loglevels[]=debug&exclude_search=cron&per_page=100"
```

### JavaScript/Fetch Examples

```javascript
// Using fetch API with authentication
const baseURL = 'https://your-site.com/wp-json/simple-history/v1/events';
const auth = btoa('username:application-password');

// Exclude debug events
fetch(`${baseURL}?exclude_loglevels[]=debug&per_page=50`, {
    headers: {
        'Authorization': `Basic ${auth}`
    }
})
.then(response => response.json())
.then(events => {
    events.forEach(event => {
        console.log(event.message, event.level);
    });
});

// Complex filtering
const params = new URLSearchParams({
    per_page: 100,
    'exclude_loglevels[]': 'debug',
    'exclude_search': 'cron',
    'initiator': 'wp_user'
});

fetch(`${baseURL}?${params}`, {
    headers: {
        'Authorization': `Basic ${auth}`
    }
})
.then(response => response.json())
.then(events => console.log(events));
```

## WP-CLI Examples

The Simple History WP-CLI integration supports all positive and negative filters through the `wp simple-history list` command.

### Basic Commands

```bash
# List recent events
wp simple-history list

# List with custom format
wp simple-history list --format=json

# List with specific count
wp simple-history list --count=20
```

### Positive Filtering (Inclusion)

```bash
# Show only info level events
wp simple-history list --log_level=info --count=50

# Show events from specific loggers
wp simple-history list --logger=SimpleUserLogger,SimplePluginLogger --count=50

# Search for specific text
wp simple-history list --search=updated --count=50

# Show events from user ID 5
wp simple-history list --user=5 --count=50

# Show only WP user events
wp simple-history list --initiator=wp_user --count=50

# Date range filtering
wp simple-history list --date_from="2024-01-01" --date_to="2024-12-31" --count=100
```

### Negative Filtering (Exclusion)

```bash
# Exclude debug level events
wp simple-history list --exclude_log_level=debug --count=50

# Exclude multiple log levels
wp simple-history list --exclude_log_level=debug,info --count=50

# Exclude events from specific loggers
wp simple-history list --exclude_logger=SimpleUserLogger --count=50

# Exclude events containing "cron"
wp simple-history list --exclude_search=cron --count=50

# Exclude events from user ID 1 (admin)
wp simple-history list --exclude_user=1 --count=50

# Exclude WordPress-initiated events (cron jobs, automatic updates)
wp simple-history list --exclude_initiator=wp --count=50

# Exclude multiple initiators (WordPress system + CLI)
wp simple-history list --exclude_initiator=wp,wp_cli --count=50
```

### Combined Filtering

```bash
# Info events without cron
wp simple-history list --log_level=info --exclude_search=cron --count=50

# All events except debug and CLI
wp simple-history list --exclude_log_level=debug --exclude_initiator=wp_cli --count=50

# User events only, no debug
wp simple-history list --initiator=wp_user --exclude_log_level=debug --count=50

# Clean event log: exclude debug, CLI, and WordPress system events
wp simple-history list --exclude_log_level=debug --exclude_initiator=wp,wp_cli --exclude_search=action_scheduler --count=100

# Important events only (errors/warnings, no WordPress system/CLI)
wp simple-history list --log_level=error,warning --exclude_initiator=wp,wp_cli --count=50

# Date range with exclusions
wp simple-history list --date_from="2024-01-01" --exclude_search=cron --exclude_log_level=debug --count=100
```

### Output Formats

```bash
# JSON format for scripting
wp simple-history list --exclude_log_level=debug --format=json --count=50

# CSV format for spreadsheets
wp simple-history list --exclude_search=cron --format=csv --count=50

# YAML format
wp simple-history list --exclude_initiator=wp --format=yaml --count=50
```

### Using Custom WP-CLI Commands

If you create custom WP-CLI commands that use Log_Query, you can pass filter arguments:

```php
// In your custom WP-CLI command file
class My_History_Commands {
    /**
     * List events excluding debug level.
     *
     * @when after_wp_load
     */
    public function clean_list( $args, $assoc_args ) {
        $log_query = new \Simple_History\Log_Query();

        $results = $log_query->query([
            'posts_per_page' => 50,
            'exclude_loglevels' => ['debug'],
            'exclude_search' => 'cron',
        ]);

        foreach ( $results['log_rows'] as $row ) {
            WP_CLI::line( sprintf(
                '[%s] %s: %s',
                $row->date,
                $row->level,
                $row->message
            ));
        }
    }
}

WP_CLI::add_command( 'history clean-list', ['My_History_Commands', 'clean_list'] );
```

Then use it:
```bash
wp history clean-list
```

## Filter Combinations

### Conflict Resolution

When the same value appears in both inclusion and exclusion filters, **exclusion takes precedence**:

```php
// Example: Request both inclusion and exclusion
$results = $log_query->query([
    'loggers' => ['SimplePluginLogger', 'SimpleUserLogger'],
    'exclude_loggers' => ['SimpleUserLogger'],
]);
// Result: Only SimplePluginLogger events (exclusion wins)
```

### Best Practices

1. **Use exclusions for cleaner queries**
   ```php
   // Instead of listing all levels to include:
   'loglevels' => ['info', 'warning', 'error', 'critical'],

   // Just exclude what you don't want:
   'exclude_loglevels' => ['debug'],
   ```

2. **Combine search with level filtering**
   ```php
   // Find errors, but not from cron jobs
   $results = $log_query->query([
       'loglevels' => ['error', 'warning'],
       'exclude_search' => 'cron',
   ]);
   ```

3. **Filter out noise**
   ```php
   // Production monitoring: important events only
   $results = $log_query->query([
       'exclude_loglevels' => ['debug', 'info'],
       'exclude_initiator' => ['wp', 'wp_cli'],
       'exclude_search' => 'action_scheduler',
   ]);
   ```

## Available Filters Reference

### Positive Filters (Inclusion)

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `search` | string | Text to search for | `'updated'` |
| `loglevels` | array/string | Log levels to include | `['info', 'warning']` |
| `loggers` | array/string | Logger classes to include | `['SimpleUserLogger']` |
| `messages` | array/string | Specific messages | `['SimplePluginLogger:plugin_activated']` |
| `user` | int | Single user ID | `5` |
| `users` | array/string | Multiple user IDs | `[1, 2, 3]` |
| `initiator` | string/array | Event initiator | `'wp_user'` or `['wp_user', 'wp_cli']` |
| `date_from` | string | Start date | `'2024-01-01'` |
| `date_to` | string | End date | `'2024-12-31'` |

### Negative Filters (Exclusion)

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `exclude_search` | string | Text to exclude | `'cron'` |
| `exclude_loglevels` | array/string | Log levels to exclude | `['debug', 'info']` |
| `exclude_loggers` | array/string | Loggers to exclude | `['SimpleUserLogger']` |
| `exclude_messages` | array/string | Messages to exclude | `['SimplePluginLogger:plugin_activated']` |
| `exclude_user` | int | Single user ID to exclude | `1` |
| `exclude_users` | array/string | User IDs to exclude | `[1, 2, 3]` |
| `exclude_initiator` | string/array | Initiators to exclude | `'wp'` or `['wp', 'wp_cli']` |

### Valid Initiator Values

- `wp_user` - Regular WordPress user
- `wp_cli` - WP-CLI command
- `wp` - WordPress system (cron jobs, automatic updates)
- `web_user` - Non-logged-in web user
- `other` - Other sources

### Common Log Levels

- `debug` - Detailed debug information
- `info` - Informational messages
- `notice` - Normal but significant
- `warning` - Warning messages
- `error` - Error conditions
- `critical` - Critical conditions
- `alert` - Action must be taken immediately
- `emergency` - System is unusable

## Performance Tips

1. **Use indexes**: Filters on `level`, `logger`, and `initiator` use database indexes
2. **Limit results**: Always set reasonable `posts_per_page` values
3. **Date ranges**: Use `date_from`/`date_to` for better query performance
4. **Avoid wildcards**: Specific filters perform better than broad searches

## Examples by Use Case

### Security Monitoring

```php
// Monitor failed login attempts and errors
$results = $log_query->query([
    'posts_per_page' => 100,
    'loglevels' => ['error', 'warning'],
    'exclude_search' => 'cron',
    'exclude_initiator' => 'wp',
]);
```

### Development Debugging

```php
// Everything except noisy WordPress system events
$results = $log_query->query([
    'posts_per_page' => 200,
    'exclude_initiator' => 'wp',
    'exclude_search' => 'action_scheduler',
]);
```

### User Activity Audit

```php
// Real user actions only
$results = $log_query->query([
    'posts_per_page' => 100,
    'initiator' => 'wp_user',
    'exclude_loglevels' => ['debug'],
]);
```

### Production Logs

```php
// Important events only, no debug noise
$results = $log_query->query([
    'posts_per_page' => 100,
    'exclude_loglevels' => ['debug', 'info'],
    'exclude_initiator' => ['wp', 'wp_cli'],
]);
```

## Need Help?

- Check the [Simple History documentation](https://simple-history.com/docs/)
- Report issues on [GitHub](https://github.com/bonny/WordPress-Simple-History/issues)
- Visit the [WordPress plugin page](https://wordpress.org/plugins/simple-history/)
