# Issue: Gutenberg New Post Save Not Logged

**Issue Number:** Related to email reports showing "0 posts created"
**Status:** ✅ Resolved
**Date:** 2025-11-25

## Problem Summary

Email reports were showing "0 posts created" even when posts were actually created via the Gutenberg editor. Investigation revealed two related issues:

1. **Post creation via Gutenberg autosave wasn't being logged at all**
2. **Even if logged, no initial content was captured, creating an information gap in the audit trail**

## Root Cause Analysis

### Issue 1: Autosave Posts Not Logged

When a user clicks "Add New" in WordPress, the Gutenberg editor:

1. Creates an `auto-draft` post with an ID
2. On first keystroke, autosave fires and transitions the post from `auto-draft` → `draft`
3. This autosave uses the endpoint `/wp/v2/posts/{id}/autosaves` via `WP_REST_Autosaves_Controller`
4. The controller calls `wp_update_post()` directly, which fires `transition_post_status` hook
5. **However**, Simple History's `on_transition_post_status` was blocking ALL REST requests (line 805)
6. This meant autosave-created posts were never logged

**Key Discovery:** The autosave endpoint does NOT fire `rest_after_insert` hooks - it uses a different controller and only fires `transition_post_status`.

### Issue 2: Information Gap - No Initial Content Captured

Even after fixing the logging issue, the initial post content was never captured:

-   Post created: No details shown
-   First manual update: Shows diff from autosave state, but the **initial autosave content was lost**
-   Users had no way to see what content was originally written in the post

This created a critical gap in the audit trail.

## Solution Implemented

### 1. Allow Autosave Post Creation Through (lines 802-812)

Modified `on_transition_post_status` in [class-post-logger.php](loggers/class-post-logger.php:802-812):

```php
$isRestApiRequest = defined( 'REST_REQUEST' ) && REST_REQUEST;
$isAutosave = defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE;

// Bail if this is a REST API request, EXCEPT for autosaves that create posts.
$isAutosaveCreatingPost = $isAutosave && 'auto-draft' === $old_status && 'draft' === $new_status;

if ( $isRestApiRequest && ! $isAutosaveCreatingPost ) {
    return;
}
```

**Result:** Autosave post creation now passes through and gets logged.

### 2. Add Auto-Created Context Flag (lines 723-740)

Added `post_auto_created` flag to distinguish autosave vs manual creation:

```php
if ( $is_post_created ) {
    // Add context to indicate if this was auto-created by WordPress (auto-save)
    if ( 'draft' === $new_status && $is_autosave ) {
        $context['post_auto_created'] = true;
    }

    // Capture initial post content...
    $this->info_message( 'post_created', $context );
}
```

**Result:** Post creation events can be identified as autosave vs manual.

### 3. Capture Initial Post Content (lines 734-737)

Added initial content capture to prevent information gaps:

```php
// Capture initial post content so there's no information gap in the audit trail.
// This is especially important for autosaved posts where the initial content
// would otherwise be lost (first update would only show diff from autosave state).
$context['post_new_post_content'] = $post->post_content;
$context['post_new_post_excerpt'] = $post->post_excerpt;
$context['post_prev_status'] = $old_status;
$context['post_new_status']  = $new_status;
```

**Key naming convention:** Uses `post_new_*` pattern (not just `post_*`) to match codebase conventions like `post_new_post_title`, `post_prev_post_title`, etc.

**Result:** Initial content is now captured and visible in the log.

### 4. Display Initial Content with Modern Event_Details Classes (lines 1485-1508)

Implemented modern `Event_Details_Group` pattern for displaying post creation details:

```php
} elseif ( 'post_created' == $message_key ) {
    // Show initial post content for created posts using Event_Details classes.
    // The Event Details system will automatically read values from context.
    // Using diff table formatter for consistency with post_updated display.
    $event_details_group = new Event_Details_Group();
    $event_details_group->set_formatter( new Event_Details_Group_Diff_Table_Formatter() );
    $event_details_group->add_items(
        [
            new Event_Details_Item(
                'post_new_post_content',
                __( 'Content', 'simple-history' )
            ),
            new Event_Details_Item(
                'post_new_post_excerpt',
                __( 'Excerpt', 'simple-history' )
            ),
            new Event_Details_Item(
                'post_new_status',
                __( 'Status', 'simple-history' )
            ),
        ]
    );

    return $event_details_group;
}
```

**Benefits:**

-   Automatic context value reading (no manual `isset()` checks needed)
-   Automatic empty value handling (items without values are removed)
-   Consistent styling with post updates via `Event_Details_Group_Diff_Table_Formatter`
-   Shows only current status (not confusing "changed from auto-draft to draft")

### 5. Added Required Imports (lines 5-8)

```php
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Diff_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;
```

## Files Modified

1. **[loggers/class-post-logger.php](loggers/class-post-logger.php)**

    - Lines 5-8: Added Event_Details imports
    - Lines 802-812: Modified `on_transition_post_status` to allow autosave post creation
    - Lines 723-740: Added `post_auto_created` flag and initial content capture
    - Lines 1485-1508: Added `get_log_row_details_output` section for `post_created` using Event_Details classes

2. **[readme.txt](readme.txt)**
    - Added changelog entry: "Fixed post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created."
    - Added changelog entry: "Changed post creation events to display initial content and status in event details."

## Technical Details

### WordPress Autosave Flow

1. User clicks "Add New" → WordPress creates `auto-draft` post with ID
2. User types first character → Gutenberg autosave fires
3. Autosave endpoint: `POST /wp/v2/posts/{id}/autosaves`
4. Controller: `WP_REST_Autosaves_Controller::create_item()`
5. For draft posts by same author without post lock, it calls `wp_update_post()` directly (not `rest_after_insert`)
6. This transitions post from `auto-draft` → `draft` and fires `transition_post_status` hook
7. WordPress sets `DOING_AUTOSAVE` constant to true during this process

### Event_Details System Benefits

The modern Event_Details system (introduced in Simple History) provides:

-   **Automatic context reading:** Values are fetched from context automatically using key names
-   **Smart item preservation:** Items with custom formatters are always kept; empty items are removed
-   **Consistent formatting:** Diff table formatter matches post update styling
-   **Clean code:** No manual `isset()` or `empty()` checks needed
-   **HTML + JSON support:** Same code produces both HTML and JSON output

### Context Key Naming Convention

The codebase uses a consistent pattern for field changes:

-   `post_prev_{field}` for old values (e.g., `post_prev_post_title`)
-   `post_new_{field}` for new values (e.g., `post_new_post_title`)

This pattern is used in:

-   `add_post_data_diff_to_context()` method (lines 887-888)
-   Page template changes (lines 950-951)
-   All post field updates throughout the logger

## Testing

### Test Case 1: Create Post via Gutenberg Autosave

1. Navigate to Posts → Add New
2. Type content in Gutenberg editor
3. Wait for autosave (no manual save)
4. Check Simple History log
5. **Expected:** Post creation event appears with initial content

### Test Case 2: Verify Initial Content Display

1. Create new post with content via autosave
2. View event details in Simple History
3. **Expected:** Content, excerpt, and status are displayed
4. Make first manual edit
5. **Expected:** Update event shows diff from initial autosave content (not lost)

### Test Case 3: Manual Post Creation

1. Create post manually without autosave
2. **Expected:** `post_auto_created` flag is NOT set
3. Initial content is still captured

### Test Case 4: Email Reports

1. Create multiple posts via Gutenberg
2. Wait for weekly email report
3. **Expected:** Correct post count in email (not 0)

## Debugging Tools Used

### WP-CLI Commands

```bash
# View latest events (faster than browser)
docker compose run --rm wpcli_mariadb simple-history list

# View specific event details
docker compose run --rm wpcli_mariadb simple-history list --format=json --number=1
```

### Debug Log Monitoring

```bash
# Monitor WordPress debug log
tail -f <wordpress-root>/wp-content/debug.log
```

### OPcache Management

When editing WordPress core files for debugging:

```bash
# Reset OPcache to see changes
docker compose exec wordpress_mariadb php -r "opcache_reset();"
```

## Related Documentation

-   **Event_Details System:** [docs/architecture/event-details.md](docs/architecture/event-details.md)
-   **WordPress Core Autosave Controller:** `/wp-includes/rest-api/endpoints/class-wp-rest-autosaves-controller.php`
-   **Post Logger:** [loggers/class-post-logger.php](loggers/class-post-logger.php)

## Changelog Entries Added

### Fixed

-   Fixed post creation via Gutenberg autosave not being logged, causing email reports to show 0 posts created.

### Changed

-   Changed post creation events to display initial content and status in event details.

## Future Considerations

### Potential Enhancements

1. **Distinguish autosave vs manual creation in UI:** Use `post_auto_created` flag to add a visual indicator
2. **Test with other block editors:** Verify behavior with third-party Gutenberg-compatible editors
3. **Performance monitoring:** Track if capturing full post content affects performance on large posts

### Edge Cases to Monitor

1. **Very large posts:** May need content truncation in context storage
2. **Custom post types:** Verify autosave behavior is consistent across all post types
3. **Multisite:** Test behavior in multisite environments
4. **User permissions:** Verify logging respects user capabilities

## Lessons Learned

1. **REST API hooks vary by controller:** Different controllers fire different hooks. Autosave controller doesn't fire `rest_after_insert`.
2. **Information gaps matter:** Not capturing initial state creates confusion in audit trails.
3. **Modern Event_Details is powerful:** Automatic context reading eliminates boilerplate code.
4. **Naming conventions matter:** Following `post_prev_*` / `post_new_*` pattern ensures consistency.
5. **User-focused thinking wins:** "What would users want to see?" led to capturing initial content.

## References

-   WordPress Developer Blog: [The Importance of a Good Changelog](https://developer.wordpress.org/news/2025/11/the-importance-of-a-good-changelog/)
-   Keep a Changelog: [https://keepachangelog.com](https://keepachangelog.com)
-   WordPress REST API: [https://developer.wordpress.org/rest-api/](https://developer.wordpress.org/rest-api/)
