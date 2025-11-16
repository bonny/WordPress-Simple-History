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
