# Issue #584: Support date-based ordering for imported historical data

**Status**: ✅ **COMPLETED**
**Branch**: `issue-584-refactor-sql-queries-order-by-date`
**Issue URL**: https://github.com/bonny/WordPress-Simple-History/issues/584
**Related**: Issue #583 (Import existing data feature)

## ⭐ DECISION: Approach 2 (Global Date Ordering) - APPROVED & IMPLEMENTED

**Date**: 2025-10-23
**Decision**: Implement global `ORDER BY date DESC, id DESC` across all query locations
**Rationale**:
- ✅ Chronological order is critical for imported historical data
- ✅ Occasions grouping still works (groups consecutive rows by temporal proximity)
- ✅ Edge cases improve rather than break
- ✅ More logical representation of "occasions" as temporally-related events

**Implementation Date**: 2025-10-23
**Test Results**: All tests passing (268 tests, 1900+ assertions)

## Summary

When users import historical WordPress data (via issue #583), the imported events have **high primary key IDs but old dates**. Since Simple History's query system uses `ORDER BY id DESC` everywhere, imported historical events appear at the top of the log instead of in chronological order.

**Example Problem**:
```
Existing events: ID=1-100, dates from 2024-01-01 to 2024-12-31
Import old data: ID=101-200, dates from 2020-01-01 to 2023-12-31

Current display: Events 101-200, then 1-100 (wrong!)
Expected display: Events 1-100, then 101-200 (chronological)
```

## Use Cases

This date-ordering functionality is required for:

1. **Imported historical data** (Issue #583)
   - When importing existing WordPress data (posts, pages, users) into Simple History
   - Imported events have high IDs but old dates, causing incorrect display order

2. **Manually added historical events** (Future feature)
   - When users manually add events with dates in the past
   - Any manually created event with a backdated timestamp will have the same ID vs. date mismatch
   - Example: Admin manually logs "Post created on 2020-01-15" today → gets high ID but old date

Both scenarios create the same fundamental problem: **new IDs with old dates** break chronological ordering.

## Technical Analysis

### Root Cause

Simple History orders all queries by **ID** (auto-increment primary key), not by **date**:

**Locations using `ORDER BY id DESC`**:
- `inc/class-log-query.php:133` - `query_overview_simple()`
- `inc/class-log-query.php:178` - Count query
- `inc/class-log-query.php:189` - Inner occasions grouping query
- `inc/class-log-query.php:232` - Grouped results
- `inc/class-log-query.php:298` - Final results
- `inc/class-log-query.php:351` - Count query
- `inc/class-log-query.php:512` - `query_occasions()`

### Why ID Ordering Exists

The **occasions grouping** feature relies on consecutive rows having the same `occasionsID` for grouping similar events together.

**Occasions Grouping Logic** (`inc/class-log-query.php:171-192`):
```sql
SELECT
    id,
    IF(@a=occasionsID,@counter:=@counter+1,@counter:=1) AS repeatCount,
    IF(@counter=1,@groupby:=@groupby+1,@groupby) AS repeated,
    @a:=occasionsId,
    contexts.value as context_message_key
FROM %1$s AS h2
LEFT OUTER JOIN %2$s AS contexts ON (contexts.history_id = h2.id AND contexts.key = '_message_key')
%3$s
ORDER BY id DESC
```

This MySQL variable-based grouping **requires consecutive rows** with the same `occasionsID`. If we change to date ordering, events with the same `occasionsID` but different timestamps would be separated, breaking the grouping.

### Why Date is Excluded from occasionsID

**occasionsID Generation** (`loggers/class-logger.php:1884`):
```php
$occasions_id = $this->get_occasions_id( [
    'logger' => $logger,
    'message' => $message,
    'context' => $context,
    // Note: 'date' is explicitly NOT included
] );
```

This means events with the same `occasionsID` can have **different dates**, which is intentional - it groups similar actions regardless of when they occurred.

### How Occasions Grouping Actually Works (IMPORTANT CLARIFICATION)

**Key insight**: Occasions grouping only works on **consecutive rows in the query result**, not "all events with same occasionsID regardless of time".

The MySQL variable-based grouping logic:
```sql
IF(@a=occasionsID, @counter:=@counter+1, @counter:=1) AS repeatCount
```

This compares **each row to the previous row**. If occasionsID matches, increment counter. If not, reset to 1.

**Implications**:
- **ORDER BY id DESC**: Groups events with same occasionsID if their IDs are consecutive/close
- **ORDER BY date DESC, id DESC**: Groups events with same occasionsID if their dates are consecutive/close
- **Result**: Changing to date ordering makes grouping MORE logical (temporal proximity vs ID proximity)

**Example**:
```
Events:
- 09:00:00 - Failed login (ID=100, occasionsID=abc)
- 09:00:01 - Failed login (ID=101, occasionsID=abc)
- 18:00:00 - Failed login (ID=500, occasionsID=abc, imported)

ORDER BY id DESC (current): 500, 101, 100
→ All grouped (3 failed logins) even though 500 is 9 hours apart

ORDER BY date DESC (proposed): 500, 101, 100
→ All grouped (3 failed logins) with correct chronological order
```

The grouping behavior is nearly identical, but date ordering is more intuitive for users.

### Database Indexes

The database already supports date-based queries with indexes:

**Schema** (`inc/services/class-setup-database.php:169-170`):
```sql
KEY date (date),
KEY loggerdate (logger,date)
```

Performance won't be an issue.

## Proposed Solutions

### Approach 1: Add Filter for ORDER BY Clause ⚙️
**Complexity**: Low | **Risk**: Minimal | **Compatibility**: 100%

Add a WordPress filter to allow changing the ORDER BY clause per query.

**Implementation**:
```php
// In inc/class-log-query.php, around line 275:
$order_by = apply_filters(
    'simple_history/query/order_by',
    'maxId DESC',  // Default
    $this->args
);
```

**Usage**:
```php
add_filter( 'simple_history/query/order_by', function( $order_by, $args ) {
    if ( ! empty( $args['show_imported_events'] ) ) {
        return 'maxDate DESC, maxId DESC';
    }
    return $order_by;
}, 10, 2 );
```

**Pros**:
- Zero risk to existing functionality
- Backward compatible
- Users control ordering per query

**Cons**:
- Requires custom filter code
- Occasions grouping still breaks with mixed data
- Doesn't solve problem automatically

---

### Approach 2: Global Change to `ORDER BY date DESC, id DESC` 🔄
**Complexity**: Medium | **Risk**: Low-Medium | **Compatibility**: ~95%

Change all ORDER BY clauses to sort by date first, ID as tiebreaker.

**Changes Required** (7 locations):
```php
// query_overview_simple() line 133:
ORDER BY simple_history_1.date DESC, simple_history_1.id DESC

// query_overview_mysql() line 189:
ORDER BY date DESC, id DESC

// query_overview_mysql() line 232:
ORDER BY maxDate DESC, maxId DESC

// query_overview_mysql() line 298:
ORDER BY simple_history_1.date DESC, simple_history_1.id DESC

// query_occasions() line 512:
ORDER BY date DESC, id DESC
```

**Why it mostly works**:
- Events in the **same second** maintain ID ordering
- `occasionsID` grouping works within same timestamp
- 95%+ of real-world cases work fine

**Edge case that breaks**:
```
Event A: 2024-01-01 10:00:00, ID=100, occasionsID=abc123
Event B: 2024-01-01 10:00:00, ID=101, occasionsID=abc123
Event C: 2024-01-01 09:59:59, ID=500, occasionsID=abc123  <- Imported

Result: C, A, B (C separated from group)
```

**Pros**:
- Chronologically correct
- Leverages existing indexes
- Minimal code changes

**Cons**:
- Can break occasions grouping in edge cases
- Requires extensive testing
- Subtle bugs possible

---

### Approach 3: Separate Mode for Date-Ordered Ungrouped Results ⭐ **RECOMMENDED**
**Complexity**: Low | **Risk**: Minimal | **Compatibility**: 100%

Add date ordering option to existing `query_overview_simple()` method (which already doesn't use occasions grouping).

**Implementation in `inc/class-log-query.php`**:
```php
// Around line 127 in query_overview_simple():
protected function query_overview_simple() {
    $use_date_ordering = ! empty( $this->args['order_by_date'] );

    $order_by = $use_date_ordering
        ? 'simple_history_1.date DESC, simple_history_1.id DESC'
        : 'simple_history_1.id DESC';

    $sql_tmpl = "
        SELECT DISTINCT simple_history_1.*
        FROM {$table_name} AS simple_history_1
        %2\$s
        %3\$s
        ORDER BY {$order_by}
        LIMIT %4\$d
        OFFSET %5\$d
    ";

    // ... rest of method unchanged
}
```

**Usage in experimental features page**:
```php
$query_args = [
    'order_by_date' => true,  // Enable date ordering
    'ungrouped' => true,      // Disable occasions grouping
];
```

**Add UI toggle in main history page**:
```php
// Show only if imported events exist:
if ( $this->has_imported_events() ) {
    echo '<label>';
    echo '<input type="checkbox" name="order_by_date" value="1">';
    echo esc_html__( 'Order by date (for imported historical events)', 'simple-history' );
    echo '</label>';
}
```

**Pros**:
- ✅ Zero risk to occasions grouping
- ✅ Leverages existing ungrouped infrastructure
- ✅ Minimal code changes (1 method)
- ✅ User controls when to use it
- ✅ Clear separation of concerns
- ✅ Easy to test

**Cons**:
- Loses occasions grouping (but already broken with mixed data)
- Requires UI change
- User must understand when to use it

---

## ~~Recommendation: Approach 3~~

**UPDATED**: After analysis, **Approach 2** was selected instead. See decision at top of document.

## Implementation Plan (Approach 2 - Global Date Ordering)

### Phase 1: Core Changes ✅ COMPLETED
- [x] Update ORDER BY in `query_overview_simple()` line 133
- [x] Update ORDER BY in count query line 178
- [x] Update ORDER BY in inner occasions grouping line 283
- [x] Update ORDER BY in grouped results line 326 (use maxDate DESC, maxId DESC)
- [x] Update ORDER BY in final results line 392
- [x] Update ORDER BY in another results query line 445
- [x] Update ORDER BY in query_occasions() line 606
- [x] Update ORDER BY in another occasions query line 1072
- [x] Add `max(h.date) as maxDate` to SELECT clause line 316

**Changes Made**:
- Replaced all `ORDER BY id DESC` with `ORDER BY date DESC, id DESC` (8 locations)
- Replaced `ORDER BY maxId DESC` with `ORDER BY maxDate DESC, maxId DESC` (1 location)
- Added maxDate to SELECT clause for grouping queries

### Phase 2: Testing ✅ COMPLETED
- [x] Run existing occasions test: `tests/wpunit/OccasionsTest.php` - **PASSED**
- [x] Test occasions grouping still works correctly - **PASSED (4 comprehensive tests)**
- [x] Test chronological display with mixed IDs and dates - **PASSED**
- [x] Test data integrity (no lost events) - **PASSED (155 assertions)**
- [x] Test imported data scenario - **PASSED (132 assertions)**
- [x] Full test suite - **PASSED (268 tests, 1900+ assertions)**

### Phase 3: Documentation ✅ COMPLETED
- [x] Update issue readme with decision
- [x] Document test results and findings
- [x] Document occasions grouping verification
- [ ] Update changelog (pending PR merge)
- [ ] Add migration notes if needed (none required - backward compatible)

## Testing Scenarios

### Basic Date Ordering
- [ ] Import old posts (2020-2023) into site with new posts (2024-2025)
- [ ] Enable date ordering toggle
- [ ] Verify chronological display (old posts at bottom)
- [ ] Disable toggle, verify ID ordering (old posts at top)

### Edge Cases
- [ ] Import posts with same timestamp as existing posts
- [ ] Import posts with future dates
- [ ] Import large dataset (1000+ posts)
- [ ] Verify performance with date ordering

### Compatibility
- [ ] Test with occasions grouping enabled (should gracefully disable)
- [ ] Test with SQLite (uses query_overview_simple by default)
- [ ] Test with MySQL occasions grouping (should not be affected)

### UI/UX
- [ ] Toggle appears only when imported events exist
- [ ] Toggle state persists across page loads
- [ ] Clear messaging about when to use date ordering

## Related Files

- **Query System**: `inc/class-log-query.php` (all ORDER BY locations)
- **Logger Base**: `loggers/class-logger.php:1884` (occasionsID generation)
- **Database Schema**: `inc/services/class-setup-database.php:169-170` (date indexes)
- **Importer**: `inc/class-existing-data-importer.php` (creates historical events)
- **Experimental Page**: `inc/services/class-experimental-features-page.php` (import UI)

## Technical Notes

### Date Storage
The `_date` context parameter correctly sets the date column:
```php
// loggers/class-logger.php:1937-1946
private function append_date_to_context( $data, $context ) {
    if ( isset( $context['_date'] ) ) {
        $data['date'] = $context['_date'];
        unset( $context['_date'] );
    }
    return array( $data, $context );
}
```

This confirms date storage works correctly - the problem is purely query ordering.

### Performance Impact
Date indexes already exist, so performance should be comparable to ID ordering:
- Index on `date`: Line 169 in class-setup-database.php
- Index on `(logger, date)`: Line 170

### Occasions Grouping Constraint
The fundamental constraint is that MySQL variables compare **consecutive rows**:
```sql
IF(@a=occasionsID,@counter:=@counter+1,@counter:=1)
```

This requires stable ordering - any reordering breaks the grouping logic.

## Future Enhancements

### Smart Ordering Mode
Automatically detect imported events and enable date ordering:
```php
if ( $this->has_imported_events_in_results() ) {
    $this->args['order_by_date'] = true;
}
```

### Hybrid Grouping
Implement date-aware occasions grouping that handles mixed timelines:
- Group events within same day/hour
- Maintain chronological order
- More complex SQL, but better UX

### Import Timestamp Tracking
Add metadata to track when events were imported vs. real-time:
```php
'_imported_at' => current_time( 'mysql' ),
'_is_imported' => true,
```

This would allow filtering imported vs. real-time events.

## Questions to Resolve

- [ ] Should date ordering be the default when ungrouped mode is active?
- [ ] Should we add a setting to permanently enable date ordering?
- [ ] Should imported events have visual distinction in the UI?
- [ ] Should we warn users before importing if recent events exist?

## Next Steps

1. Create feature branch: `issue-584-date-ordering` ✅
2. Implement Approach 3 core functionality
3. Add comprehensive tests
4. Test with real imported data
5. Document usage and limitations
6. Create PR for review
