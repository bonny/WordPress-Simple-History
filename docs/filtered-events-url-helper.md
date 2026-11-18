# Filtered Events URL Helper Function

The `Helpers::get_filtered_events_url()` helper function generates URLs to the events log with filters applied.

## Location

File: `inc/class-helpers.php`
Function: `Helpers::get_filtered_events_url( $args )`

## Usage

```php
use Simple_History\Helpers;

// Generate URL with filters
$url = Helpers::get_filtered_events_url( $args );

// IMPORTANT: Always use esc_url() when outputting in HTML
echo '<a href="' . esc_url( $url ) . '">Link</a>';
```

## Security Note

**You MUST use `esc_url()` when outputting the URL in HTML.** This is required by WordPress coding standards for defense-in-depth security, even though the function returns a properly encoded URL.

```php
// ✅ CORRECT - Always escape output
echo '<a href="' . esc_url( $url ) . '">Link</a>';

// ❌ WRONG - Never output unescaped URLs
echo '<a href="' . $url . '">Link</a>';
```

## Parameters

The function accepts an array of filter arguments. Each one is written to the URL under the query parameter shown in brackets.

- **`users`** (array|int) - Filter by user(s) (`users`)
  - Single user ID as integer. It must be a real `int`: a numeric string such as `'14'` is ignored.
  - Single user array with keys: `id`, `display_name`, `user_email` (`display_name` is required, `user_email` is optional)
  - Array of user arrays for multiple users

- **`date`** (string) - Date range filter (`date`)
  - Values the date dropdown understands: `'lastdays:1'` (today), `'yesterday'`, `'lastdays:7'`, `'lastdays:14'`, `'lastdays:30'`, `'lastdays:60'`, `'month:2025-04'`, `'allDates'`
  - `'customRange'` needs `from` and `to`, which this function does not support. Use `get_filtered_history_url()` for that.

- **`search`** (string) - Search text filter (`search`)
  - See "Known limitations" below: the events page reads search text from `q`, not `search`.

- **`loglevels`** (array) - Log level filter (`loglevels`, JSON)
  - Array of log level identifiers, such as `'info'` or `'warning'`
  - See "Known limitations" below.

- **`messages`** (array) - Message type filter (`messages`, JSON)
  - Array of objects with `value` (label for the filter chip) and `search_options` (array of `"LoggerSlug:message_key"` strings), the same shape the message type filter uses

- **`initiators`** (array) - Initiator filter (`initiators`, JSON)
  - Array of initiator identifiers
  - See "Known limitations" below.

- **`context`** (string) - Context filter (`context`)
  - Context key-value pairs (e.g., `"_user_id:1"`). Separate several pairs with newlines.

### Exclusion parameters

These hide events that match, instead of showing only events that match. The events page reads them from the URL, but there are no controls for most of them in the filter panel yet.

- **`exclude_search`** (string) - Hide events containing these words (`exclude-search`)
- **`exclude_loglevels`** (array) - Hide these log levels (`exclude-levels`, JSON). See "Known limitations" below.
- **`exclude_loggers`** (array) - Hide events from these loggers, by logger slug (`exclude-loggers`, JSON). See "Known limitations" below.
- **`exclude_messages`** (array) - Hide these message types (`exclude-messages`, JSON). Same shape as `messages`.
- **`exclude_users`** (array) - Hide events by these users (`exclude-users`, JSON). Array of `{ "id": "1", "value": "Name (email)" }` objects. Unlike `users`, this value is JSON-encoded as given, so pass the final `id`/`value` shape, not a user ID or `display_name`/`user_email`.
- **`exclude_initiators`** (array) - Hide events by these initiators (`exclude-initiator`, JSON)
- **`exclude_context`** (string) - Hide events with these context key-value pairs (`exclude-context`)

### Known limitations

Some parameter names written by this function do not match what the events page (`src/components/EventsGui.jsx`) reads, so those filters are dropped when the page loads:

| Argument            | Written as                  | Events page reads                                  |
| ------------------- | --------------------------- | -------------------------------------------------- |
| `search`            | `search`                    | `q`                                                |
| `loglevels`         | `loglevels` (JSON array)    | `levels` (comma-separated, e.g. `info,warning`)    |
| `initiators`        | `initiators`                | `initiator`                                        |
| `exclude_loglevels` | `exclude-levels` (JSON)     | `exclude-levels` (comma-separated)                 |
| `exclude_loggers`   | `exclude-loggers` (JSON)    | `exclude-loggers` (comma-separated)                |

The callers in the plugin today only pass `users` and `date`, which work.

## Examples

### Example 1: Filter by single user ID

```php
// Simple: just pass a user ID
$url = Helpers::get_filtered_events_url([
    'users' => 14
]);

// Result:
// admin.php?page=simple_history_admin_menu_page&users=[{"id":"14","value":"User Name (email@example.com)"}]
```

### Example 2: Filter by single user with data

```php
// Pass user data directly
$url = Helpers::get_filtered_events_url([
    'users' => [
        'id' => '14',
        'display_name' => 'Pär Thernström',
        'user_email' => 'par@example.com'
    ]
]);
```

### Example 3: Filter by multiple users

```php
$url = Helpers::get_filtered_events_url([
    'users' => [
        [
            'id' => '1',
            'display_name' => 'John Doe',
            'user_email' => 'john@example.com'
        ],
        [
            'id' => '14',
            'display_name' => 'Jane Smith',
            'user_email' => 'jane@example.com'
        ]
    ]
]);
```

### Example 4: Filter by date range

```php
// Last 30 days
$url = Helpers::get_filtered_events_url([
    'date' => 'lastdays:30'
]);

// Last 7 days
$url = Helpers::get_filtered_events_url([
    'date' => 'lastdays:7'
]);

// Today
$url = Helpers::get_filtered_events_url([
    'date' => 'lastdays:1'
]);
```

### Example 5: Filter by search term

The URL gets `search=login%20failed`, but the events page reads search text from `q`, so this filter is currently not applied (see "Known limitations").

```php
$url = Helpers::get_filtered_events_url([
    'search' => 'login failed'
]);
```

### Example 6: Combine multiple filters

```php
$url = Helpers::get_filtered_events_url([
    'users' => 14,
    'date' => 'lastdays:30',
    'search' => 'post'
]);

// Result: events by user 14, in last 30 days.
// The "post" search text is in the URL but not applied (see "Known limitations").
```

### Example 7: Filter by context

```php
$url = Helpers::get_filtered_events_url([
    'context' => '_user_id:1'
]);
```

### Example 8: Real-world usage - Link to user's events

```php
// In a logger or display function
foreach ( $top_users as $user ) {
    $url = Helpers::get_filtered_events_url([
        'users' => $user
    ]);

    echo '<a href="' . esc_url( $url ) . '">' .
         esc_html( $user['display_name'] ) .
         '</a>';
}
```

### Example 9: Hide events from one user

```php
$url = Helpers::get_filtered_events_url([
    'date'          => 'lastdays:30',
    'exclude_users' => [
        [
            'id'    => '1',
            'value' => 'John Doe (john@example.com)',
        ],
    ],
]);
```

## Output

The function returns a properly URL-encoded string suitable for use in links:

```
http://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page&users=%5B%7B%22id%22%3A%2214%22%2C%22value%22%3A%22Name%20%28email%29%22%7D%5D
```

## Notes

- Every value is encoded with `rawurlencode()` (spaces become `%20`). JSON parameters are built with `wp_json_encode()` first. The URL is assembled by hand instead of with `add_query_arg()`, which would double-encode the JSON.
- User IDs are always converted to strings for consistency with the filter UI
- If a user ID is provided, the function automatically fetches user data via `get_userdata()`. An unknown user ID gives no `users` parameter.
- The function handles both single values and arrays appropriately
- All URLs point at `admin.php?page=simple_history_admin_menu_page`, whatever the "menu page location" setting is. `get_filtered_history_url()` builds the URL for the actual location (`tools.php` or `index.php` when the page is moved under Tools or Dashboard).
- Empty values are skipped, so no parameter is written for them.

## Related: `Helpers::get_filtered_history_url()`

`Helpers::get_filtered_history_url( $args )` (also in `inc/class-helpers.php`) builds a filtered URL to the same page. The two overlap, but they are not interchangeable:

|                         | `get_filtered_events_url()`                                     | `get_filtered_history_url()`                                          |
| ----------------------- | --------------------------------------------------------------- | --------------------------------------------------------------------- |
| Base URL                | Always `admin.php?page=simple_history_admin_menu_page`          | `Helpers::get_history_admin_url()`, which follows the menu location setting |
| Arguments               | `users`, `date`, `search`, `loglevels`, `messages`, `initiators`, `context`, `exclude_*` | `date`, `from`, `to`, `context`, `show_filters`, `messages`           |
| Custom date range       | No                                                              | Yes: `date => 'customRange'` with `from` and `to` as `Y-m-d`          |
| Open the filter panel   | No                                                              | Yes: `show_filters => true` writes `show-filters=1`                   |
| Used by                 | Stats dashboard user links, History Insights sidebar            | Weekly email report, `get_post_history_url()`                         |

Its arguments:

- **`date`** (string) - Same values as above, for example `'allDates'`, `'lastdays:30'`, `'month:2025-04'` or `'customRange'`.
- **`from`** (string) - Start date as `Y-m-d`. Only used with `date` set to `'customRange'`.
- **`to`** (string) - End date as `Y-m-d`. Only used with `date` set to `'customRange'`.
- **`context`** (string) - Context filter, for example `'post_id:123'`.
- **`show_filters`** (bool) - Expand the filter panel when the page opens. Default false.
- **`messages`** (array) - Message type filter objects with `value` and `search_options`, same shape as above. Appended by hand with `rawurlencode( wp_json_encode() )` because `add_query_arg()` would double-encode it.

```php
$url = Helpers::get_filtered_history_url([
    'date'         => 'customRange',
    'from'         => '2025-04-01',
    'to'           => '2025-04-07',
    'show_filters' => true,
    'messages'     => [
        [
            'value'          => 'Posts',
            'search_options' => [ 'SimplePostLogger:post_created' ],
        ],
    ],
]);
```

The other parameters go through `add_query_arg()`, which does not encode values, so the same `esc_url()` rule applies on output.

`Helpers::get_post_history_url( $post_id, $args )` is a wrapper around `get_filtered_history_url()` for one post: it sets `context` to `post_id:<id>`, defaults to all dates with the filter panel open, and filters on the created, updated, trashed, deleted and restored post messages unless you pass your own `search_options` and `label`.

Pick `get_filtered_history_url()` when you need a custom date range, the open filter panel, or a link that respects the menu location. Pick `get_filtered_events_url()` when you need the users filter or the exclusion filters.

## Why This Helper Exists

Before this helper, each place that needed to link to filtered events had to:
1. Manually construct the JSON structure
2. Handle proper encoding
3. Build the URL correctly

This led to code duplication and potential bugs. The helper function centralizes this logic and ensures consistent URL generation throughout the plugin.
