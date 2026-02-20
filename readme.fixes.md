# Fixes branch

Collection of small bug fixes, improvements, and features (patch-level changes).

## Bug Fixes

### 1. Database Space Not Freed After Clear

-   **Issue:** [[Database Space Not Freed After Clear]]
-   **Files:** `inc/class-helpers.php`, `inc/services/class-setup-purge-db-cron.php`
-   **Fix:** Added `OPTIMIZE TABLE` after both manual clear (TRUNCATE) and cron purge (DELETE) to reclaim disk space on InnoDB.
-   **Safeguard:** Cron purge skips `OPTIMIZE TABLE` when the table still has >100K rows (checked via `SHOW TABLE STATUS`), to avoid long table locks on sites with brute-force-inflated tables. Manual clear is unguarded since tables are empty after TRUNCATE.

### 2. Date Pill Repeated on Same Day

-   **Issue:** [[Date Pill Repeated on Same Day]]
-   **Files:** `src/components/EventOccasionsList.jsx`, `src/components/EventOccasions.jsx`
-   **Fix:** Pass `prevEvent` prop to occasion Event components so the date separator can compare dates between siblings. First occasion uses `parentEvent` as its previous event.

### 3. Gutenberg Link Page Shown as Updated Not Created

-   **Issue:** [[Gutenberg Link Page Shown as Updated Not Created]]
-   **Status:** Already fixed by commit `a0574909`. No code changes needed.

### 4. SH Settings menu_page_location Not Displayed

-   **Issue:** [[SH Settings menu_page_location Not Displayed]]
-   **Files:** `loggers/class-simple-history-logger.php`
-   **Fix:** Added missing `Event_Details_Item` entries for `menu_page_location` and `show_in_admin_bar` settings.

### 5. Need to Unescape Texts (double-escaped HTML entities)

-   **Issue:** [[Need to Unescape Texts]]
-   **Files:** `loggers/class-logger.php`, `loggers/class-post-logger.php`, `loggers/class-media-logger.php`, `loggers/class-plugin-duplicate-post-logger.php`, `loggers/class-options-logger.php`
-   **Fix:** Added `html_entity_decode()` before `esc_html()` to normalize pre-existing entities (e.g. from `wptexturize`) before escaping. Also removed duplicate `esc_html()` on `duplicated_post_title` in the duplicate post logger.

### 6. Link to Settings When Premium Active

-   **Issue:** [[Link to Settings When Premium Active]]
-   **Status:** Already fixed by commit `44edb9e8`. No code changes needed.

### 7. Publish for Review Not Shown in Log

-   **Issue:** [[Publish for Review Not Shown in Log]]
-   **Files:** `loggers/class-post-logger.php`
-   **Fix:** Added `post_pending` message type. Detects `draft`/`auto-draft` → `pending` transitions and logs "Submitted {post_type} for review". Added search filter and linked HTML output.

### 8. Image Crop Not Logged

-   **Issue:** [[Image Crop Not Logged]]
-   **Files:** `loggers/class-media-logger.php`
-   **Fix:** Added `attachment_image_edited` message type via `wp_save_image_editor_file` filter. Detects crop, rotate, flip, and scale operations from the request history. Shows operations performed in event details.

### 9. Code Profiler Pro Missing Name in Log

-   **Issue:** [[Code Profiler Pro Missing Name in Log]]
-   **Files:** `loggers/class-plugin-logger.php`
-   **Fix:** Fall back to pre-update stored plugin data when `get_plugin_data()` returns empty Name (custom updaters may not have the file in place yet). Added `file_exists()` guard and null coalescing on all array keys.

### 10. File Integrity Checker False Positive on Localized Installs

-   **Issue:** [[File Integrity Checker False Positive]]
-   **Files:** `loggers/class-core-files-logger.php`, `inc/services/wp-cli-commands/class-wp-cli-core-files-command.php`
-   **Fix:** Use `$wp_local_package` instead of hardcoded `'en_US'` for checksum locale. Eliminates false positives on files like `version.php` that contain locale-specific content.

## UX Improvements

### 11. Link Go to Anchor with Premium Indicator

-   **Issue:** [[Link Go to Anchor with Premium Indicator]]
-   **Files:** `loggers/class-simple-history-logger.php`
-   **Fix:** Added `#simple_history_clear_log_info` anchor to the settings URL in the purged_events detail output so the link jumps directly to the retention setting. Updated non-premium text to "Set number of days the log is kept (Premium)." for clearer labeling.

### 12. Other Initiator Needs Avatar

-   **Issue:** [[Other Initiator Needs Avatar]]
-   **Files:** `css/styles.css`
-   **Fix:** Added gear icon (`\f107`) for "other" initiator type. Removed from modal zero-margin rule so the icon gets proper spacing.

### 13. Background Color for Sticky Events

-   **Issue:** [[Background Color for Sticky Events]]
-   **Files:** `css/styles.css`
-   **Fix:** Enabled `background-color: var(--sh-color-cream)` for sticky events (was previously commented out).

### 14. Welcome Message on First Install

-   **Issue:** [[Welcome Message on First Install]]
-   **Files:** `inc/services/class-welcome-message-service.php` (new), `inc/services/class-setup-database.php`, `inc/class-simple-history.php`, `uninstall.php`
-   **Fix:** Dismissible admin notice shown once after first install, pointing users to the event log page. Auto-dismissed on next page load. Requires WordPress 6.4+.

### 15. Oldest Backfilled Event Note and Upsell

-   **Issue:** [[Oldest Backfilled Event Note and Upsell]]
-   **Files:** `src/components/EventsList.jsx`
-   **Fix:** Shows info notice on the last page when the last event is backfilled and premium is not active. Non-intrusive upsell with tracked link.

## Features

### 16. Search Events by IP Address

-   **Issue:** [[Search Events by IP Address]]
-   **Files:** `inc/class-log-query.php`, `inc/class-wp-rest-events-controller.php`, `src/components/EventIPAddresses.jsx`, `src/components/EventsGui.jsx`, `src/components/EventsSearchFilters.jsx`, `src/components/ExpandedFilters.jsx`, `src/functions.js`
-   **Fix:** Full-stack IP address filtering. REST API parameter with validation, Log_Query support with LIKE matching for anonymized IPs (`.x` suffix), UI filter input in expanded filters, and "Show all events from this IP address" button in the IP popover.

### 17. Command Palette Page History

-   **Issue:** [[Command Palette Page History]]
-   **Files:** `inc/services/class-command-palette.php` (new), `src/index-command-palette.js` (new), `inc/class-simple-history.php`, `package.json`
-   **Fix:** Registers a "Simple History for [post title]" command in the WordPress Cmd+K palette. Navigates to the event log filtered by the current post. Capability-gated. Requires WordPress 6.3+.

### 18. Clear Button for Search Filters

-   **Issue:** [[Clear Button for Search Filters]]
-   **Files:** `src/components/EventsSearchFilters.jsx`, `css/styles.css`
-   **Fix:** Added "Clear filters" button that appears when any filter has a non-default value. Resets all filters (date, search text, log levels, message types, users, initiators, IP address, context filters) to defaults.

### 19. Email Digest Include User Events

-   **Issue:** [[Email Digest Include User Events]]
-   **Files:** `inc/services/class-email-report-service.php`, `templates/email-summary-report.php`
-   **Fix:** Added "Users created" and "Profile updates" counts to the email summary report, following existing layout pattern.

## TODO: Verify All Fixes

All fixes above need manual verification/testing before merging. Go through each fix and confirm it works as expected in the local dev environment.

## Deferred (needs own branch)

-   Different Premium Icons Teaser vs Active — reclassified as `branch` complexity (13+ locations across PHP and JSX)
