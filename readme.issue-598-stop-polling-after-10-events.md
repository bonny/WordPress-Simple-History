# Issue #598: Stop Polling After 10+ New Events

## Issue Summary
Stop checking for new events when the number of new events reaches 10+ to reduce server resource consumption from inactive browser tabs.

## Problem
The event log polls for updates every 30 seconds indefinitely. If users leave browser tabs open (even multiple tabs), this creates unnecessary server load. When 10+ events are available, the user is likely not actively monitoring the log.

## Solution
- Stop background polling when 10+ new events are detected
- Display "10+ new events" instead of exact count
- Resume polling when user manually clicks the refresh button

## Files Modified
- `src/components/NewEventsNotifier.jsx` - Main implementation

## Implementation Details
1. Added `shouldPoll` state to control whether polling continues
2. Modified polling logic to stop when `newEventsCount >= 10`
3. Updated display text to show "10+" when limit reached
4. Reset polling on manual refresh
5. Updated page title to show "10+" appropriately

## Testing Checklist
- [ ] Verify polling stops after 10 events detected
- [ ] Verify "10+ new events" displays correctly
- [ ] Verify clicking refresh button restarts polling
- [ ] Check page title updates properly (shows "10+" in title)
- [ ] Verify no API calls made after limit reached (check browser console)
- [ ] Test with multiple browser tabs to ensure behavior is consistent

## Notes
- Polling interval remains 30 seconds (UPDATE_CHECK_INTERVAL constant)
- The "10" threshold is hardcoded but could be made configurable in future
- Existing TODO comment (line 75) suggests polling interval could be made filterable
