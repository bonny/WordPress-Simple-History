# Issue #599: Add Logger for Notes Feature in WordPress 6.9

## Issue Details

- **Title**: Add new logger for the Notes-feature that are new in WP 6.9
- **Status**: In progress
- **Author**: bonny
- **Labels**: feature

## Description

Add new logger for the [Notes feature new in WordPress 6.9](https://make.wordpress.org/core/2025/11/15/notes-feature-in-wordpress-6-9/).

Notes are added in blocks like this:
`<!-- wp:paragraph {"metadata":{"noteId":2}} -->`

User actions to track:
- Add note
- Edit note
- Remove/delete note
- Mark note (thread?) as resolved
- Reopen resolved note/thread

All notes activity is added using comments and the WordPress REST API.

## Tasks

- [ ] Add new logger for Notes feature
- [ ] Add message for adding notes
- [ ] Add message for editing notes
- [ ] Add message for removing/deleting notes
- [ ] Add message for resolving notes
- [ ] Add message for reopening notes

## Progress

Branch created: `issue-599-logger-for-notes-feature`

### Implementation Completed ✅

**Files Created/Modified:**
1. `loggers/class-notes-logger.php` - New Notes Logger implementation
2. `inc/class-simple-history.php` - Registered Notes_Logger in core loggers array

**Features Implemented:**
- ✅ Block-aware logging (shows block type and content preview)
- ✅ Note creation tracking
- ✅ Note reply detection (distinguishes between new notes and replies)
- ✅ Note editing tracking
- ✅ Note resolution tracking
- ✅ Note reopening tracking
- ✅ Note deletion tracking
- ✅ Graceful fallback when block information unavailable

**Code Quality:**
- ✅ PHP linting passed (no violations)
- ✅ PHPStan static analysis passed (no errors)
- ✅ Follows WordPress coding standards
- ✅ Uses active voice in messages ("Added note" not "Note was added")
- ✅ Proper namespacing and type hints

**Testing:**
- ✅ Tested on WordPress 6.9-RC1 (nightly)
- ✅ Successfully added note via block editor
- ✅ Notes feature confirmed working
- ⚠️  Logger hooks are in place and ready

**Note:** The logger uses WordPress core hooks (`wp_insert_comment`, `edit_comment`, `updated_comment_meta`, `delete_comment`) which fire when notes are created/modified through the REST API. The implementation follows the same patterns as other loggers in the codebase.

### Code Refinements ✅

**Changes Made:**
- ✅ Converted all `array()` syntax to short array syntax `[]`
- ✅ Added search configuration in `get_info()` with labels for filtering note events
- ✅ Changed slug from `Notes_Logger` to `NotesLogger`
- ✅ All code quality checks still passing

### Bug Fixes ✅

**Issue 1: Duplicate Logging on Resolve/Reopen**
- **Problem**: When marking a note as resolved/reopened, WordPress creates an empty comment with `_wp_note_status` meta, triggering both `on_wp_insert_comment` (logging "Replied to a note" with empty content) and `on_updated_comment_meta` (logging "Marked as resolved")
- **Root Cause**: WordPress uses empty comments with meta to track resolution status
- **Solution**: Added check in `on_wp_insert_comment` to skip logging when comment is empty AND has `_wp_note_status` meta set to 'resolved' or 'reopen'
- **Location**: `loggers/class-notes-logger.php:100-107`

**Issue 2: Delete Not Working**
- **Problem**: Deleting notes via REST API (POST with `x-http-method-override: DELETE`) wasn't logging deletion
- **Root Cause**: REST API uses `wp_trash_comment()` by default (not `wp_delete_comment()`), which fires `trash_comment` hook instead of `delete_comment` hook
- **Solution**: Added `trash_comment` hook alongside existing `delete_comment` hook
- **Location**: `loggers/class-notes-logger.php:87`
- **Note**: Both hooks kept because `delete_comment` fires on permanent deletion (force=true or emptying trash)

**Documentation Improvements:**
- ✅ Updated `on_updated_comment_meta` docblock to clarify it handles resolve/reopen with `_wp_note_status` meta
- ✅ Updated `on_delete_comment` docblock to clarify it handles both trash and permanent deletion
- ✅ Removed debug statement from `on_delete_comment`
