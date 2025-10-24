# Issue #585: Fix has-updates API to use date-based logic

**Status**: ✅ COMPLETED
**Related**: Issue #584 (Date-based ordering)
**Branch**: issue-584-refactor-sql-queries-order-by-date
**Completed**: 2025-10-23

## Summary

Fixed the "new events" notification bug that was incorrectly counting backdated events as new. The fix changes from ID-only to date+ID-based detection, ensuring that manually backdated entries (high ID, old date) are no longer shown as "new events" in the notification.

**Key Changes:**
- Backend: Added `since_date` parameter and date+ID WHERE clause logic
- REST API: Added `X-SimpleHistory-MaxId` and `X-SimpleHistory-MaxDate` response headers
- Frontend: Updated to track and pass both `maxDate` and `maxId` for accurate detection
- Tests: 4 new tests added, all passing (100% success rate)

## Problem

After implementing date-based ordering in issue #584, the "new events" notification is broken for manually backdated events.

### Current Behavior (Broken)

1. User views history page, sees events ordered by date DESC
2. Frontend tracks `eventsMaxId` (highest ID from current results)
3. Every 30 seconds, calls `/wp-json/simple-history/v1/events/has-updates?since_id={maxId}`
4. Backend checks `WHERE id > since_id`
5. Someone manually adds an event with backdated timestamp:
   - Gets ID = 1000 (new, high)
   - Has date = 2020-01-01 (old, backdated)
6. **Bug**: `id > maxId` returns this event as "new"
7. User sees "90 new events" notification
8. But these events appear at the BOTTOM of the list (old dates), not top!

### Root Cause

**File**: `inc/class-log-query.php` lines 1209-1214

```php
// Add where clause for since_id,
// to include rows with id greater than since_id, i.e. more recent than since_id.
if ( isset( $args['since_id'] ) ) {
    $inner_where[] = sprintf(
        'id > %1$d',
        (int) $args['since_id'],
    );
}
```

The comment even says "more recent than since_id" - but with date ordering, higher ID ≠ more recent!

## Solution

Change from ID-based to DATE+ID-based new event detection.

### Backend Changes

**File**: `inc/class-log-query.php`

#### 1. Add `since_date` parameter

Around line 691, add after `since_id`:

```php
'since_id' => null,
'since_date' => null,  // NEW: for date-based "new events" detection
```

#### 2. Validate `since_date` parameter

Around line 814, add after `since_id` validation:

```php
// "since_date" must be valid date string.
if ( isset( $args['since_date'] ) && ! is_string( $args['since_date'] ) ) {
    throw new \InvalidArgumentException( 'Invalid since_date' );
}
```

#### 3. Update WHERE clause logic

Replace lines 1207-1214 with:

```php
// Add where clause for since_id and since_date.
// When both are provided, we want events that would appear ABOVE the current view.
// With ORDER BY date DESC, id DESC, that means:
//   - Events with date > since_date (newer date)
//   - OR events with date = since_date AND id > since_id (same date but higher ID)
if ( isset( $args['since_date'] ) && isset( $args['since_id'] ) ) {
    $inner_where[] = sprintf(
        '(date > %1$s OR (date = %1$s AND id > %2$d))',
        $wpdb->prepare( '%s', $args['since_date'] ),
        (int) $args['since_id']
    );
} elseif ( isset( $args['since_id'] ) ) {
    // Fallback to ID-only for backward compatibility
    // (though this is now less accurate with date ordering)
    $inner_where[] = sprintf(
        'id > %1$d',
        (int) $args['since_id'],
    );
}
```

#### 4. Update REST API params

**File**: `inc/class-wp-rest-events-controller.php`

Around line 637, add `since_date` to parameter mappings:

```php
'since_id'                => 'since_id',
'since_date'              => 'since_date',  // NEW
'date_from'               => 'date_from',
```

Add to `get_collection_params()` method around line 300:

```php
$query_params['since_date'] = array(
    'description' => __( 'Limit results to events with date greater than since_date (or same date with id greater than since_id). Use with since_id for accurate new event detection with date ordering.', 'simple-history' ),
    'type'        => 'string',
    'format'      => 'date-time',
);
```

### Frontend Changes

**File**: `src/components/NewEventsNotifier.jsx`

The frontend needs to track and pass BOTH `maxDate` and `maxId`:

Around line 47, update:

```jsx
const eventsQueryParamsWithSinceId = {
    ...eventsQueryParams,
    since_id: eventsMaxId,
    since_date: eventsMaxDate,  // NEW: pass max date too
    _fields: null,
};
```

**Note**: We need to check how `eventsMaxDate` is provided. It should come from the main events query response.

## Testing

### Test Scenarios

1. **Normal new event** (current date, high ID)
   - Should appear in "new events" ✅

2. **Backdated event** (old date, high ID)
   - Should NOT appear in "new events" ✅

3. **Event at same timestamp** (same date as maxDate, higher ID)
   - Should appear in "new events" ✅

4. **Event at same timestamp** (same date as maxDate, lower ID)
   - Should NOT appear in "new events" ✅

### Test Implementation

Tests were added to existing file `tests/wpunit/RestAPITest.php`:

✅ **Implemented 4 comprehensive tests:**

1. `test_has_updates_backdated_event_not_counted()` - Tests backdated entries are NOT counted
2. `test_has_updates_future_event_counted()` - Tests new events ARE counted
3. `test_has_updates_same_date_higher_id_counted()` - Tests same date/higher ID counted
4. `test_has_updates_backward_compatibility_id_only()` - Tests fallback ID-only logic

All tests use the REST API `/events/has-updates` endpoint directly to verify end-to-end functionality.

## Backward Compatibility

### Concern

Older clients might only send `since_id` without `since_date`.

### Solution

The fallback logic handles this:

```php
} elseif ( isset( $args['since_id'] ) ) {
    // Fallback to ID-only for backward compatibility
    $inner_where[] = sprintf( 'id > %1$d', (int) $args['since_id'] );
}
```

This means:
- ✅ New clients (updated JS): Send both params → accurate detection
- ⚠️ Old clients (cached JS): Send only `since_id` → less accurate but not broken
- After JS cache clears, all clients will use new accurate method

## Benefits

1. ✅ Fixes false "new events" notifications for backdated entries
2. ✅ Works correctly with date-based ordering
3. ✅ Maintains accuracy for events at same timestamp
4. ✅ Backward compatible with old clients
5. ✅ No breaking changes

## Implementation Results

### Backend Implementation

**File: `inc/class-log-query.php`**

1. ✅ Added `since_date` parameter at line 703
2. ✅ Added `since_date` validation after line 819
3. ✅ Updated WHERE clause logic at lines 1230-1243:
   ```php
   if ( isset( $args['since_date'] ) && isset( $args['since_id'] ) ) {
       $inner_where[] = sprintf(
           '(date > %s OR (date = %s AND id > %d))',
           $wpdb->prepare( '%s', $args['since_date'] ),
           $wpdb->prepare( '%s', $args['since_date'] ),
           (int) $args['since_id']
       );
   } elseif ( isset( $args['since_id'] ) ) {
       // Fallback to ID-only for backward compatibility
       $inner_where[] = sprintf(
           'id > %1$d',
           (int) $args['since_id'],
       );
   }
   ```
4. ✅ Added `max_date` to query_overview_simple() return array (line 220)
5. ✅ Added `max_date` to query_overview_mysql() return array (line 526)
6. ✅ Added `global $wpdb;` to get_inner_where() method (line 1199)

**File: `inc/class-wp-rest-events-controller.php`**

1. ✅ Added `since_date` to get_has_updates() parameter mappings (line 645)
2. ✅ Added `since_date` to get_items() parameter mappings (line 714)
3. ✅ Added `since_date` to get_collection_params() schema (after line 323)
4. ✅ Added response headers for maxId and maxDate (lines 774-780):
   ```php
   if ( isset( $query_result['max_id'] ) ) {
       $response->header( 'X-SimpleHistory-MaxId', (int) $query_result['max_id'] );
   }

   if ( isset( $query_result['max_date'] ) ) {
       $response->header( 'X-SimpleHistory-MaxDate', $query_result['max_date'] );
   }
   ```

### Frontend Implementation

**File: `src/components/EventsGui.jsx`**

1. ✅ Added `eventsMaxDate` state (line 91)
2. ✅ Updated loadEvents() to extract maxDate from headers (lines 304-312)
3. ✅ Passed `eventsMaxDate` prop to NewEventsNotifier (line 436)

**File: `src/components/NewEventsNotifier.jsx`**

1. ✅ Added `eventsMaxDate` to component props (line 37)
2. ✅ Added `since_date` to API request parameters (line 50)
3. ✅ Updated useEffect dependencies (line 81)

### Test Results

**File: `tests/wpunit/RestAPITest.php`**

Added 4 comprehensive tests for has-updates logic:

```
✅ test_has_updates_backdated_event_not_counted
   - Verifies backdated events (high ID, old date) are NOT counted as new
   - Result: PASSED

✅ test_has_updates_future_event_counted
   - Verifies new events (high ID, new date) ARE counted as new
   - Result: PASSED

✅ test_has_updates_same_date_higher_id_counted
   - Verifies events with same date but higher ID ARE counted as new
   - Result: PASSED

✅ test_has_updates_backward_compatibility_id_only
   - Verifies fallback to ID-only works for backward compatibility
   - Result: PASSED
```

**Test Summary**: All 7 tests passed (3 existing + 4 new), 9 assertions

### Code Quality

- ✅ PHP linting passed (vendor/bin/phpcs)
- ✅ All coding standards issues auto-fixed (vendor/bin/phpcbf)
- ✅ Frontend build successful (npm run build)

### API Verification

Tested REST API response headers:
```bash
curl -i 'http://wordpress-stable-docker-mariadb.test:8282/wp-json/simple-history/v1/events?per_page=5'

Headers returned:
X-SimpleHistory-MaxId: 66859
X-SimpleHistory-MaxDate: 2025-10-23 12:07:13
```

## Implementation Checklist

- [x] Add `since_date` parameter to Log_Query
- [x] Update WHERE clause logic for date+ID checking
- [x] Add `since_date` to REST API parameter mappings
- [x] Add `maxDate` response headers to REST API
- [x] Update frontend to extract and pass both `maxDate` and `maxId`
- [x] Add tests for various scenarios
- [x] Test with manually backdated events
- [x] Verify backward compatibility
- [x] Run PHP linting and fix issues
- [x] Build frontend successfully

## Related Files

### Modified Files

- `inc/class-log-query.php`
  - Lines 703: Added `since_date` parameter
  - Lines 820-823: Added `since_date` validation
  - Lines 1199: Added `global $wpdb;`
  - Lines 1230-1243: Updated WHERE clause for date+ID logic
  - Line 220: Added `max_date` to query_overview_simple()
  - Line 526: Added `max_date` to query_overview_mysql()

- `inc/class-wp-rest-events-controller.php`
  - Lines 323-327: Added `since_date` to collection params schema
  - Line 645: Added `since_date` parameter mapping (has-updates)
  - Line 714: Added `since_date` parameter mapping (get-items)
  - Lines 774-780: Added response headers for maxId and maxDate

- `src/components/EventsGui.jsx`
  - Line 91: Added `eventsMaxDate` state
  - Lines 304-312: Extract maxDate from headers
  - Line 436: Pass `eventsMaxDate` to NewEventsNotifier

- `src/components/NewEventsNotifier.jsx`
  - Line 37: Added `eventsMaxDate` to props
  - Line 50: Added `since_date` to API request
  - Line 81: Updated useEffect dependencies

- `tests/wpunit/RestAPITest.php`
  - Lines 46-191: Added 4 comprehensive has-updates tests

### How The Fix Works

**Before (Broken):**
```
Frontend: "Has anything new since ID 1000?"
Backend: "SELECT * FROM events WHERE id > 1000"
Result: Returns backdated events (high ID, old date) ❌
```

**After (Fixed):**
```
Frontend: "Has anything new since date '2025-10-23 12:00:00' and ID 1000?"
Backend: "SELECT * FROM events WHERE (date > '2025-10-23 12:00:00' OR (date = '2025-10-23 12:00:00' AND id > 1000))"
Result: Only returns truly newer events (by date first, then ID) ✅
```
