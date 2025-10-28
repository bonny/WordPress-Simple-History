# Issue #592: Add user names to Most active users in last 30 days

## Issue Description

The current solution/design looks good - but only when users actually have avatars. Many users does not have, I've seen after running this function on live client sites, so to make it useful in those cases add a list of the preferred usernames below the list of avatars.

## Status

- Branch created: issue-592-add-user-names-to-most-active-users
- Project board: In progress
- Created: 2025-10-28
- **Completed: 2025-10-28** ✓

## Task

The username list already existed in the code (in `sh-StatsDashboard-userNamesList`), but the user filter links were not working properly.

## Solution

Fixed the user name links to properly filter the event log by user:

### Changes Made

1. **inc/class-events-stats.php:269-273**
   - Added `user_email` to the SQL query in `get_top_users()`
   - Updated return array to include `user_email` field

2. **inc/class-stats-view.php:371**
   - Updated docblock to include `user_email` in the user array shape

3. **inc/class-stats-view.php:427-445**
   - Updated user filter value format to include email: `"Display Name (email@example.com)"`
   - Changed from `add_query_arg()` to manual URL construction with `rawurlencode()`
   - Cast user ID to string for proper JSON encoding

### Key Fix

The main issue was that `add_query_arg()` was mangling the JSON structure. By manually constructing the URL and using `rawurlencode()`, the JSON is now properly preserved in the URL parameter.

### Final URL Format

```
?page=simple_history_admin_menu_page&users=[{"id":"14","value":"Display Name (email@example.com)"}]
```

## Testing

- ✓ Code passes phpcs linting
- ✓ Code passes phpstan analysis
- ✓ User confirmed links are working correctly

## Bonus: Created Reusable Helper Function

Created `Helpers::get_filtered_events_url()` helper function for generating filtered event log URLs throughout the plugin.

### Location
- File: `inc/class-helpers.php:1844-1963`
- Documentation: `docs/filtered-events-url-helper.md`

### Purpose
Centralizes the logic for creating filtered event log URLs. Instead of manually constructing JSON and encoding URLs in multiple places, any code can now use:

```php
$url = Helpers::get_filtered_events_url([
    'users' => 14,
    'date' => 'lastdays:30',
    'search' => 'login'
]);
```

### Supported Filters
- Users (single ID, user array, or multiple users)
- Date ranges
- Search text
- Log levels
- Message types
- Initiators
- Context

### Benefits
1. **Consistency** - All filtered URLs use the same encoding logic
2. **Maintainability** - Changes to URL structure only need to happen in one place
3. **Simplicity** - Reduces complex URL construction to a simple function call
4. **Type safety** - Handles type conversions (e.g., user ID to string) automatically
5. **Future-proof** - Easy to add new filter types as the plugin evolves

See `docs/filtered-events-url-helper.md` for detailed usage examples.
