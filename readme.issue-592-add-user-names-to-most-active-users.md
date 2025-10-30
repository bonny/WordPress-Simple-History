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

3. **inc/class-stats-view.php:395-412**
   - Wrapped avatar images in clickable links to filtered event log
   - Links use `Helpers::get_filtered_events_url()` helper function
   - Both avatars and user names now link to same filtered results

4. **inc/class-stats-view.php:423-430**
   - Updated user name links to use `Helpers::get_filtered_events_url()`
   - Simplified from ~25 lines of inline URL construction to clean helper call

5. **css/simple-history-stats.css:144-154**
   - Added styles for `.sh-StatsDashboard-userLink` to remove default link styling
   - Updated hover states to work with link wrapper

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
- ✓ Chart click functionality tested and working

## Additional Enhancement: Clickable Daily Activity Chart

While working on this issue, also implemented clickable functionality for the "Daily activity over last 30 days" chart in the History Insights sidebar.

### Changes Made

1. **dropins/class-sidebar-stats-dropin.php:116-152**
   - Updated chart click handler to dispatch custom browser event
   - Changed click detection from `'nearest'` with `intersect: true` to `'index'` with `intersect: false`
   - This matches the tooltip behavior - clicking anywhere in the vertical area of a day selects that day
   - Dispatches `SimpleHistory:chartDateClick` event with selected date

2. **src/components/EventsGui.jsx:407-433**
   - Added event listener to handle chart date click events
   - Sets date filter to "customRange"
   - Sets both from/to dates to the clicked date (showing only that day's events)
   - Triggers automatic reload of events with new filter

3. **css/styles.css:1684-1687**
   - Added pointer cursor on chart hover to indicate clickability
   - Improves discoverability of the interactive feature

### User Experience

- Users can now click any day in the sidebar chart to filter events for that specific date
- Click detection is forgiving - anywhere in the day's vertical area works (same as tooltip)
- Pointer cursor indicates the chart is interactive
- Date filter automatically updates to show only events from the clicked day

## Additional Enhancement: Event Counts in Username List

Added inline event counts to the "Most active users" username list for better visibility without requiring hover interaction.

### Changes Made

1. **inc/class-stats-view.php:436**
   - Added event counts in parentheses after each username
   - Event counts placed outside `<a>` tags to prevent underline inheritance
   - Uses `number_format_i18n()` for locale-aware number formatting
   - Changed from `wp_sprintf()` with `%l` format to simple `implode()` for data list formatting

2. **css/simple-history-stats.css:162-168**
   - Added `.sh-StatsDashboard-userEventCount` styles
   - Smaller font size (0.9em) for visual hierarchy
   - Muted gray color (#666) to de-emphasize counts
   - Normal font weight to prevent link-like appearance

### User Experience

**Before:** "Pär, Hubert Blaine, erik editor, Sally Admin, and claude"
**After:** "Pär (225), Hubert Blaine (10), erik editor (4), Sally Admin (3), claude (2)"

- Event counts immediately visible without hover
- Muted styling maintains focus on usernames
- Treated as data list (removed "and" before last user) rather than natural language
- No underlines on counts or spaces before counts
- Maintains clickability of usernames to filter events

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
