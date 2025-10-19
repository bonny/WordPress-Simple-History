# Issue #585: Add context search to GUI

**Status**: In Progress
**Branch**: `issue-585-add-context-search-to-gui`
**GitHub Issue**: https://github.com/bonny/WordPress-Simple-History/issues/585
**Project Board**: Simple History kanban

## Issue Description

The log query has support for querying context, but there is no GUI for this.

**Goal**: Add a context input to the current GUI filters.

## Labels
- GUI

## Analysis & Findings

### Current State
✅ **Backend is fully implemented!** The context filtering infrastructure already exists:
- Log query supports context querying via `context_filters` parameter
- REST API endpoint accepts `context_filters` as object with key-value pairs
- Database structure uses `wp_simple_history_contexts` table with history_id, key, value columns
- GUI filters do NOT expose this functionality (this is what we need to add)

### Key Discovery
The backend is **completely ready** - we only need to add the frontend UI component and wire it up!

### Investigation Completed
- ✅ Located current GUI filter implementation
- ✅ Reviewed how context querying works in the backend
- ✅ Determined appropriate UI pattern for context search
- ✅ Found existing filter UI components that can be reused

## Progress

### Done
- ✅ Created branch `issue-585-add-context-search-to-gui`
- ✅ Created issue document
- ✅ Explored codebase architecture
- ✅ Documented complete implementation path
- ✅ Implemented context filters state in EventsGui.jsx
- ✅ Added context search UI to ExpandedFilters.jsx (plain text input)
- ✅ Updated generateAPIQueryParams to convert context filters
- ✅ Built project successfully (npm run build)
- ✅ Passed PHP linting (npm run php:lint)
- ✅ Changed from FormTokenField → TextControl → native HTML textarea
- ✅ Changed state from array to plain string (newlines preserved)
- ✅ Moved parsing logic from component to API request builder
- ✅ Added auto-grow with CSS field-sizing property
- ✅ **Gated behind experimental features flag**

## Technical Notes

### Architecture Overview

**Frontend (React/JSX)**
- Filter components in `src/components/`:
  - `EventsGui.jsx` - Main component with filter state management
  - `DefaultFilters.jsx` - Always visible filters (date, search text)
  - `ExpandedFilters.jsx` - Collapsible filters (levels, messages, users, initiators)
  - `EventsSearchFilters.jsx` - Wrapper combining both filter groups

**State Management**
- Uses `useQueryState` from `nuqs` library (preserves filters in URL)
- All filter states defined in EventsGui.jsx:118-217
- Example: `const [enteredSearchText, setEnteredSearchText] = useQueryState('q', ...)`

**API Integration**
- `generateAPIQueryParams()` in `src/functions.js:21-163` converts UI state to API params
- Calls `GET /wp-json/simple-history/v1/events` with query parameters
- API endpoint: `inc/class-wp-rest-events-controller.php`

**Backend Query**
- `WP_REST_Events_Controller::get_items()` (lines 679-784) handles API requests
- Maps parameters to `Log_Query::query()` (inc/class-log-query.php:41-54)
- `context_filters` parameter already registered and mapped (lines 649, 726)
- Query builder in `get_inner_where()` (lines 1410-1420) handles context filtering

### Context Filters Structure

**Format**: Object with key-value pairs
```javascript
// Example:
{
  "_user_id": "1",
  "_sticky": "1",
  "_message_key": "user_updated"
}
```

**SQL Pattern Used**:
```sql
id IN (
  SELECT history_id
  FROM wp_simple_history_contexts
  WHERE key = 'context_key' AND value = 'context_value'
)
```

**Common Context Keys**:
- `_user_id` - User ID who performed action
- `_user_login` - Username
- `_user_email` - User email
- `_sticky` - Whether event is sticky
- `_message_key` - Message type identifier
- `_server_remote_addr` - IP address
- Logger-specific custom keys (plugin data, post IDs, etc.)

### Files to Modify

1. **src/components/EventsGui.jsx** (lines 118-217)
   - Add state: `const [selectedContextFilters, setSelectedContextFilters] = useQueryState('context', ...)`

2. **src/components/ExpandedFilters.jsx** (after line 427)
   - Add context search UI component
   - Use FormTokenField pattern (consistent with other filters)

3. **src/functions.js** (in generateAPIQueryParams, around line 140)
   - Add mapping: `if (selectedContextFilters) { eventsQueryParams.context_filters = selectedContextFilters }`

### UI Design Decision

**Recommended**: Add to ExpandedFilters.jsx (collapsible section)
- Consistent with other advanced filters (log levels, message types, users, initiators)
- Won't clutter the default UI
- Follows existing pattern with FormTokenField

**Alternative**: Could add to DefaultFilters.jsx if context search is considered a primary feature

### Questions (Answered)
- ✅ What format does context search expect? **Object with key-value pairs**
- ✅ Should it be a simple text input or something more complex? **FormTokenField for key-value pairs**
- ✅ Are there existing patterns in the codebase for similar filters? **Yes, FormTokenField pattern used throughout**

## Testing Checklist
- [ ] Context search returns correct results
- [ ] UI is consistent with existing filters
- [ ] Empty context search doesn't break functionality
- [ ] Special characters in context are handled properly

## Implementation Details

### Changes Made

#### 1. EventsGui.jsx (src/components/EventsGui.jsx)
- Added `selectedContextFilters` state using `useQueryState` as **plain string** (lines 224-230)
- State stores newline-separated string (e.g., `"_user_id:1\n_sticky:1"`)
- Added to `generateAPIQueryParams` call (line 246)
- Added to `useMemo` dependencies (line 261)
- Added to `useEffect` dependencies for page reset (line 277)
- Passed to `EventsSearchFilters` component (lines 418-419)

#### 2. EventsSearchFilters.jsx (src/components/EventsSearchFilters.jsx)
- Added `selectedContextFilters` and `setSelectedContextFilters` to props (lines 36-37)
- Updated `hasActiveFilters` to check `.trim().length > 0` for string (line 57)
- Added to `hasActiveFilters` dependencies (line 64)
- Passed to `ExpandedFilters` component (lines 183-184)

#### 3. ExpandedFilters.jsx (src/components/ExpandedFilters.jsx)
- Added `selectedContextFilters`, `setSelectedContextFilters`, and `isExperimentalFeaturesEnabled` to props (lines 32-34)
- Removed TextareaControl import (uses plain HTML textarea instead)
- Added new Context filter UI section (lines 433-480)
- **Wrapped in `isExperimentalFeaturesEnabled` conditional** - only shows when experimental features are enabled
- Uses **plain native HTML `<textarea>`** element
- Direct value binding with `event.target.value`
- Auto-grows with `fieldSizing: 'content'` CSS property
- Monospace font, 2 starting rows, no height constraints
- No special Enter key handling - works like any normal textarea

#### 4. functions.js (src/functions.js)
- Added `selectedContextFilters` to function params (line 13, 28)
- **Conversion logic moved here** - splits newline-separated string into array (lines 153-176)
- Trims each line, filters empty lines, then parses "key:value" format
- Converts to object format for API: `{"key": "value", "key2": "value2"}`
- Added to `hasSearchOptions` check with `.trim().length > 0` (line 186)

### How It Works

**User Input Format**: `key:value` (one per line)
- Single filter:
  ```
  _user_id:1
  ```
- Multiple filters:
  ```
  _user_id:1
  _sticky:1
  ```
- Complex values (JSON, commas, etc.):
  ```
  _imported_event:true
  _some_json:{"foo":"bar","baz":"qux"}
  ```

**Input Component**: Plain native HTML `<textarea>`
- No React wrapper - direct HTML element
- Placeholder shows example with newlines
- One filter per line
- Handles any value content (JSON, commas, colons, etc.)
- Auto-grows with `field-sizing: content` CSS property
- Monospace font for better readability
- Enter key works naturally (no special handling)

**Conversion Process**:
1. User enters "key:value" strings in textarea (one per line)
2. Component stores **plain string** with newlines in state
3. Stored as string in URL: `?context=_user_id%3A1%0A_sticky%3A1` (URL-encoded)
4. `generateAPIQueryParams` splits by newline, trims each line, filters empty lines
5. Converts to object: `{"_user_id": "1", "_sticky": "1"}`
6. Sent to API as `context_filters` parameter
7. Backend filters events matching ALL specified context values (AND logic)

## Testing Instructions

### Prerequisites

⚠️ **Enable Experimental Features First!**

The context search feature is **only available when experimental features are enabled**.

**To enable experimental features:**
1. Go to Settings → Simple History
2. Enable "Experimental Features"
3. Save settings

### Manual Testing Steps

1. **Navigate to Simple History admin page**
   - URL: http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page

2. **Verify experimental features are enabled**
   - If enabled, you should see the Context filter when expanding search options
   - If not enabled, the Context filter will not appear

3. **Open search options**
   - Click "Show search options" button
   - You should now see a "Context" textarea field (only visible with experimental features enabled)

4. **Test basic context filter**
   - **Important**: Set date filter to "All dates" (context searches often need wider date ranges)
   - In the Context textarea, type: `_sticky:1`
   - Click "Search events"
   - Should show only sticky events

5. **Test multiple context filters**
   - Clear previous filter
   - Type on separate lines:
     ```
     _user_id:1
     _sticky:1
     ```
   - Click "Search events"
   - Should show sticky events by user ID 1

6. **Test complex values (JSON)**
   - Try a filter with JSON value (if such context exists)
   - The newline separator handles commas in values correctly

7. **Test URL persistence**
   - Apply context filter
   - Copy URL
   - Open in new tab
   - Filter should still be applied

8. **Test auto-expand**
   - Navigate to page without filters
   - Add `?context=["_sticky:1"]` to URL
   - Page should load with search options auto-expanded
   - Context filter should be visible

9. **Test with other filters**
   - Combine context filter with:
     - Date filter
     - Search text
     - Log levels
     - Message types
   - All should work together

10. **Test invalid formats**
   - Try entering values without colon (should be ignored gracefully)
   - Try empty key or value (should be ignored)

### Expected Behavior

✅ **Should work**:
- Valid "key:value" pairs filter results
- Multiple context filters work together (AND logic)
- Filters persist in URL
- Auto-expands when context filters in URL
- Disables sticky events when active
- Page resets to 1 when filter changes

❌ **Should not break**:
- Empty context filter (no filters applied)
- Invalid format (ignored, no error)
- Special characters in values (should work)

⚠️ **Common Gotchas**:
- **Experimental features required!** Context search only appears when experimental features are enabled in Settings → Simple History
- **Date filters still apply!** If searching for `_imported_event:true`, remember to set date to "All dates" since imported events have historical dates

## Implementation Summary

### Feature Gate

**Experimental Features Only**
- Context search is **hidden by default**
- Only appears when user enables "Experimental Features" in Settings
- Allows testing and gathering feedback before making it a standard feature
- Prevents confusion for users who don't need advanced filtering

### Final Design Decisions

**Why Plain Textarea?**
1. ✅ **Simplicity** - Native HTML behavior everyone understands
2. ✅ **No Enter key issues** - Works naturally without event handling
3. ✅ **Complex values** - Handles JSON strings with commas perfectly
4. ✅ **Auto-grow** - Modern CSS `field-sizing: content` property
5. ✅ **Better UX** - Paste, select, edit all work as expected

**Why String State Instead of Array?**
1. ✅ **Simpler component** - No conversion in React component
2. ✅ **Natural editing** - User sees exactly what's stored
3. ✅ **Parsing at API layer** - Conversion happens where it's needed
4. ✅ **Cleaner URL** - Plain string instead of JSON array

**Why One Filter Per Line?**
1. ✅ **Handles commas** - JSON values with commas work fine
2. ✅ **More readable** - Easy to see and edit multiple filters
3. ✅ **Natural separation** - Newline is unambiguous delimiter

## Next Steps

1. ✅ Implementation complete
2. ✅ Manual testing in local WordPress environment - **WORKING!**
3. Create pull request
4. Request review

## Known Working Examples

**Search for imported events:**
```
_imported_event:true
```
(Remember to set date to "All dates")

**Search for sticky events:**
```
_sticky:1
```
or
```
_sticky:{}
```

**Multiple filters:**
```
_imported_event:true
_user_id:1
```

**Complex JSON values:**
```
_some_config:{"enabled":true,"data":["a","b","c"]}
```
