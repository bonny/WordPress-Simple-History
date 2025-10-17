# Issue #583: Generate history based on existing WP data

**Status**: In Progress
**Branch**: `issue-583-import-existing-data`
**Issue URL**: https://github.com/bonny/WordPress-Simple-History/issues/583

## Overview

When the plugin is installed it contains no history at all - an empty state that's not very useful. This feature aims to populate the log with historical data from the WordPress installation after the plugin is activated.

The information available in WordPress for historical events is limited, but we can pull in:
- Post and page changes (modification dates, authors)
- Possibly other historical data available in WordPress core

## Goals

- Import existing post/page data into Simple History on first activation
- Provide a better initial experience for new users
- Show historical context even for events that occurred before plugin installation

## Implementation Considerations

### Data Sources

Available WordPress data to import:
- Posts/Pages: `post_date`, `post_modified`, `post_author`, `post_status`
- Comments: `comment_date`, `comment_approved`
- Users: `user_registered`
- Options: Limited historical data
- Media: Upload dates and modifications

### Technical Approach

- Detect first-time installation vs. existing installation
- Run import process on plugin activation or as admin action
- Import in batches to avoid timeouts
- Consider performance impact on large sites
- Provide UI feedback during import

### Questions to Answer

- Should this run automatically on activation or require user action?
- How far back should we import data?
- Should users be able to configure what gets imported?
- How to handle large sites with thousands of posts?
- Should this be a one-time import or repeatable?

## Progress

### Completed
- [x] Initial issue analysis
- [x] Research WordPress data structures for historical events
- [x] Design database import strategy
- [x] Implement post/page history import
- [x] Create admin UI for manual import trigger (Experimental Features page)
- [x] Create importer class

### In Progress
- Testing with different WordPress setups

### To Do
- [ ] Add batch processing for large datasets (for very large sites)
- [ ] Add progress indicator for import process (AJAX/background processing)
- [ ] Test with large datasets
- [ ] Handle edge cases (missing authors, deleted content, etc.)
- [ ] Update documentation
- [ ] Consider adding import for other data types (comments, users, media)

## Implementation Details

### Architecture

The import functionality has been implemented as an **experimental feature** accessible through an admin page, rather than running automatically on activation. This approach:

- Allows users to test the functionality on different sites
- Avoids potential performance issues on large sites during activation
- Provides transparency about what data is being imported
- Gives users control over when and what to import

### Files Created

1. **`inc/class-existing-data-importer.php`**
   - Core importer class responsible for importing historical data
   - Handles posts, pages, and users
   - Uses Simple History's logger infrastructure to create entries with historical dates
   - Supports configurable limits and post types

2. **`inc/services/class-experimental-features-page.php`**
   - Service class that adds an "Experimental Features" admin page
   - Auto-discovered by Simple History (placed in `/inc/services/`)
   - Provides UI for triggering imports
   - Handles form submission and displays results

### Key Features

- **Post Type Selection**: Users can choose which post types to import
- **Configurable Limits**: Import limit (1-1000 items per type) to prevent timeouts
- **User Import**: Optional import of user registration dates
- **Historical Dates**: Uses original `post_date_gmt` and `post_modified_gmt` for accurate history
- **Proper Initiator**: Sets initiator to `OTHER` to distinguish imported events from real-time events

### Technical Approach

The importer:
1. Queries WordPress for existing posts/users
2. Uses the appropriate logger (Post_Logger, User_Logger)
3. Logs entries with custom dates using the `_date` context key
4. Respects post status (publish, draft, pending, private)
5. Logs both creation and modification events if dates differ

## Findings

### WordPress Data Available for Import

- **Posts/Pages**:
  - `post_date_gmt`: Creation date
  - `post_modified_gmt`: Last modification date
  - Both dates are available and accurate
  - Can distinguish between creation and updates

- **Users**:
  - `user_registered`: Registration timestamp
  - Available for all users

- **Limitations**:
  - No historical data for who made changes
  - No detailed change information (what was changed)
  - Cannot determine the number of times a post was edited
  - Authors in `post_author` field may no longer exist

### Menu Page System

- Simple History uses a sophisticated Menu Manager + Menu Page pattern
- Services in `/inc/services/` are auto-discovered
- Menu pages can be placed in different locations based on settings
- The `Menu_Page` class provides a fluent API for page creation

## Related Code

- **Importer**: `inc/class-existing-data-importer.php:1`
- **Service**: `inc/services/class-experimental-features-page.php:1`
- **Post Logger**: `loggers/class-post-logger.php` (used for logging post events)
- **Logger Base**: `loggers/class-logger.php` (base class with `log()` method)
- **Menu System**: `inc/class-menu-manager.php`, `inc/class-menu-page.php`

## Testing Notes

### Test Scenarios

1. **Basic Import**
   - [ ] Import posts and pages on a fresh WordPress install
   - [ ] Verify entries appear in history log
   - [ ] Verify dates match original post dates

2. **Different Post Types**
   - [ ] Import custom post types
   - [ ] Import only specific post types

3. **Large Datasets**
   - [ ] Test with 100+ posts
   - [ ] Test with 1000+ posts
   - [ ] Verify no timeouts occur
   - [ ] Check performance impact

4. **Edge Cases**
   - [ ] Posts with deleted authors
   - [ ] Posts never modified (creation date = modification date)
   - [ ] Posts with future dates
   - [ ] Private/draft posts
   - [ ] Empty site (no posts)

5. **User Import**
   - [ ] Import user registrations
   - [ ] Verify registration dates are correct
   - [ ] Test with deleted users

### How to Test

1. Navigate to **Simple History > Experimental** (or **Tools > Simple History > Experimental** depending on settings)
2. Select post types to import
3. Configure import limit
4. Click "Import Data"
5. Review results and check history log
