# Testing Negative Filters via URL

This document shows how to test negative (exclusion) filters by adding them to the Simple History admin page URL.

## Base URL

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page
```

## Available Negative Filter URL Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `exclude-search` | string | Exclude events containing this text | `&exclude-search=cron` |
| `exclude-levels` | JSON array | Exclude specific log levels | `&exclude-levels=["debug","info"]` |
| `exclude-loggers` | JSON array | Exclude specific loggers | `&exclude-loggers=["SimpleUserLogger"]` |
| `exclude-messages` | JSON array | Exclude message types | See complex examples below |
| `exclude-users` | JSON array | Exclude specific user IDs | See complex examples below |
| `exclude-initiator` | JSON array | Exclude specific initiators | `&exclude-initiator=[{"value":"wp"}]` |
| `exclude-context` | string | Exclude by context filters | `&exclude-context=_user_id:1` |

## Simple Test URLs

### 1. Exclude Debug Events

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug"]
```

**Expected Result:** No debug level events shown

### 2. Exclude Events Containing "cron"

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-search=cron
```

**Expected Result:** No events with "cron" in the message

### 3. Exclude Events Containing "action_scheduler"

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-search=action_scheduler
```

**Expected Result:** No Action Scheduler events shown

### 4. Exclude WordPress System Events

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-initiator=[{"value":"wp"}]
```

**Expected Result:** No WordPress-initiated events (cron jobs, automatic updates)

### 5. Exclude SimpleUserLogger

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-loggers=["SimpleUserLogger"]
```

**Expected Result:** No user-related events shown

### 6. Exclude Events from User ID 1

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-context=_user_id:1
```

**Expected Result:** No events from user with ID 1

## Combined Filter Test URLs

### 7. Exclude Debug AND WordPress System Events

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug"]&exclude-initiator=[{"value":"wp"}]
```

**Expected Result:** No debug events AND no WordPress system events

### 8. Show Info Events, But Exclude "action_scheduler"

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&levels=["info"]&exclude-search=action_scheduler
```

**Expected Result:** Only info level events, excluding Action Scheduler

### 9. Exclude Multiple Log Levels

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug","info"]
```

**Expected Result:** Only warning, error, critical, alert, emergency events

### 10. Clean Event Log (Exclude Debug, System Events, and Cron)

```
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&exclude-levels=["debug"]&exclude-initiator=[{"value":"wp"},{"value":"wp_cli"}]&exclude-search=action_scheduler
```

**Expected Result:** Clean log without noise

## Using PHP Helper Function

You can generate these URLs programmatically in PHP:

```php
use Simple_History\Helpers;

// Exclude debug events
$url = Helpers::get_filtered_events_url([
    'exclude_loglevels' => ['debug'],
]);

// Exclude events containing "cron"
$url = Helpers::get_filtered_events_url([
    'exclude_search' => 'cron',
]);

// Exclude user ID 1
$url = Helpers::get_filtered_events_url([
    'exclude_context' => '_user_id:1',
]);

// Exclude WordPress system events
$url = Helpers::get_filtered_events_url([
    'exclude_initiators' => [
        ['value' => 'wp'],
    ],
]);

// Combined: Show errors but exclude user 1
$url = Helpers::get_filtered_events_url([
    'loglevels' => ['error', 'warning'],
    'exclude_context' => '_user_id:1',
]);

// Must escape before output
echo '<a href="' . esc_url( $url ) . '">View Filtered Events</a>';
```

## JSON Array Format Examples

### Exclude Initiators (WordPress System)

```json
[{"value":"wp"}]
```

URL-encoded:
```
exclude-initiator=[{"value":"wp"}]
```

### Exclude Multiple Initiators

```json
[{"value":"wp"},{"value":"wp_cli"}]
```

URL-encoded:
```
exclude-initiator=[{"value":"wp"},{"value":"wp_cli"}]
```

### Exclude Multiple Log Levels

```json
["debug","info"]
```

URL-encoded:
```
exclude-levels=["debug","info"]
```

### Exclude Multiple Loggers

```json
["SimpleUserLogger","SimplePluginLogger"]
```

URL-encoded:
```
exclude-loggers=["SimpleUserLogger","SimplePluginLogger"]
```

## Context Filter Format

Context filters use a simple `key:value` format:

```
exclude-context=_user_id:1
```

Multiple context filters (newline-separated):
```
exclude-context=_user_id:1%0A_sticky:1
```

Where `%0A` is the URL-encoded newline character.

## Browser Console Testing

You can also test by opening the browser console and navigating:

```javascript
// Exclude debug events
window.location.href = window.location.origin + window.location.pathname + '?page=simple_history_admin_menu_page&exclude-levels=["debug"]';

// Exclude events with "cron"
window.location.href = window.location.origin + window.location.pathname + '?page=simple_history_admin_menu_page&exclude-search=cron';
```

## Verifying It Works

After applying a filter:

1. **Check the URL** - Make sure the parameter is in the address bar
2. **Check the REST API call** - Open DevTools Network tab and look for the `/simple-history/v1/events` request
3. **Verify the query params** - The REST API call should include the `exclude_*` parameters
4. **Check the results** - Verify events matching the exclusion criteria are not shown

## Example REST API Call Generated

When you use:
```
?exclude-levels=["debug"]&exclude-search=cron
```

The React app will call:
```
/wp-json/simple-history/v1/events?per_page=15&page=1&exclude_loglevels[]=debug&exclude_search=cron
```

## Troubleshooting

**Filter not working?**
1. Clear browser cache and reload
2. Check browser console for JavaScript errors
3. Verify the REST API endpoint returns filtered results using the Network tab
4. Make sure you've rebuilt JavaScript with `npm run build`

**URL too complex?**
- Use the PHP helper function to generate URLs programmatically
- Build the URL step by step, testing each parameter individually
