# Issue 604: Add "Hide my own events" checkbox to filters

## Summary

Added a simple checkbox in the event log filters that allows users to hide their own events from the log view. This is useful for administrators or teachers who want to see only other users' activity.

## Use Case

A teacher using Simple History to monitor student activity wants to exclude their own events to focus on what students are doing.

## Implementation

When checked, adds the current user's ID to the `exclude-users` URL parameter. This means:
- The URL is updated and can be bookmarked
- Refreshing the page preserves the filter state
- The filter can be shared via URL

## Files Changed

| File | Change |
|------|--------|
| `inc/class-wp-rest-searchoptions-controller.php` | Added `current_user_id` to REST response |
| `src/components/EventsGui.jsx` | Added state and computed `effectiveExcludeUsers` |
| `src/components/EventsSearchFilters.jsx` | Pass props to ExpandedFilters |
| `src/components/ExpandedFilters.jsx` | Added checkbox UI |

## Testing

1. Go to Simple History admin page
2. Click "Show search options"
3. Check "Hide my own events" checkbox
4. Your events are filtered out from the list
5. Uncheck to see your events again

## Related

- Issue: https://github.com/bonny/WordPress-Simple-History/issues/604
- Branch: `issue-604-hide-own-events`
