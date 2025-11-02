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

The function accepts an array of filter arguments:

- **`users`** (array|int) - Filter by user(s)
  - Single user ID as integer
  - Single user array with keys: `id`, `display_name`, `user_email`
  - Array of user arrays for multiple users

- **`date`** (string) - Date range filter
  - Examples: `'lastdays:30'`, `'lastdays:7'`, `'today'`

- **`search`** (string) - Search text filter
  - Filters events containing the search words

- **`loglevels`** (array) - Log level filter
  - Array of log level identifiers

- **`messages`** (array) - Message type filter
  - Array of message type identifiers

- **`initiators`** (array) - Initiator filter
  - Array of initiator identifiers

- **`context`** (string) - Context filter
  - Context key-value pairs (e.g., `"_user_id:1"`)

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
    'date' => 'today'
]);
```

### Example 5: Filter by search term

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

// Result: events by user 14, in last 30 days, containing "post"
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

## Output

The function returns a properly URL-encoded string suitable for use in links:

```
http://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page&users=%5B%7B%22id%22%3A%2214%22%2C%22value%22%3A%22Name+%28email%29%22%7D%5D
```

## Notes

- JSON parameters (users, loglevels, messages, initiators) are encoded using `rawurlencode()` to preserve structure
- User IDs are always converted to strings for consistency with the filter UI
- If a user ID is provided, the function automatically fetches user data via `get_userdata()`
- The function handles both single values and arrays appropriately
- All URLs include the base `page=simple_history_admin_menu_page` parameter

## Why This Helper Exists

Before this helper, each place that needed to link to filtered events had to:
1. Manually construct the JSON structure
2. Handle proper encoding
3. Build the URL correctly

This led to code duplication and potential bugs. The helper function centralizes this logic and ensures consistent URL generation throughout the plugin.
