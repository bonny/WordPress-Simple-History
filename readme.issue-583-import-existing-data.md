# Issue #583: Generate history based on existing WP data

**Status**: In Progress
**Labels**: Experimental feature, Feature
**Project Board**: Simple History kanban (In Progress)

## Overview

When the plugin is installed it contains no history at all. This is a sad and boring empty state. Can we after install pull in any data from the WordPress installation and populate the log?

The information available in WordPress for historical events are very limited, but perhaps we can pull in post and page changes.

## Requirements

- Issue #584 needs to be implemented first (or events imported after a while will come up way wrong in the log)

## Todo

- [x] Add tools page with backfill information for core users and backfill functionality for premium users
- [x] Add "Backfill" tab under Tools menu with GUI for historical data import
- [ ] Pre-fill log by importing 60 days back when plugin is installed

## Progress

### Step 1: Tools Menu Architecture (Completed)

Successfully refactored the Export menu into a comprehensive "Export & Tools" menu with tabbed interface to support multiple tools including the planned Import functionality.

**Implementation Details:**

1. **Created `Tools_Menu_Dropin`** (`dropins/class-tools-menu-dropin.php`)
   - Manages the "Export & Tools" parent menu
   - Provides tabbed interface with proper navigation
   - Handles backwards compatibility redirect from old Export URLs
   - Implements location-aware tab structure:
     - When location is 'top' or 'bottom': Creates intermediate "Tools" tab with subtabs
     - When location is 'inside_dashboard' or 'inside_tools': Adds subtabs directly to Tools page

2. **Refactored `Export_Dropin`** (`dropins/class-export-dropin.php`)
   - Changed from standalone menu to subtab under Tools
   - Updated parent reference to work with both menu locations
   - Maintained all export functionality (CSV, JSON, HTML)

3. **Menu Structure:**
   - **Top/Bottom location:**
     ```
     Export & Tools (menu item)
     └── Tools (main tab with redirect)
         ├── Overview (subtab with dashboard icon)
         └── Export (subtab with download icon)
     ```
   - **Inside Dashboard/Tools location:**
     ```
     Settings (WP menu)
     └── Simple History (settings page)
         └── Export & Tools (tab)
             ├── Overview (subtab)
             └── Export (subtab)
     ```

4. **Key Features:**
   - Overview page with welcome text and list of available tools
   - Clean navigation using existing tab system (sh-PageNav and sh-SettingsTabs)
   - Dashboard icon for Overview page
   - Modular architecture - future tools can be added as separate dropins
   - Full backwards compatibility for old Export menu URLs

**Files Modified:**
- Created: `dropins/class-tools-menu-dropin.php`
- Modified: `dropins/class-export-dropin.php`
- Added icon: `css/icons/grid_view_FILL0_wght400_GRAD0_opsz48.svg` (not used, switched to dashboard icon)
- Updated: `css/icons.css` (added grid_view and verified dashboard icon exists)

**Next Steps:**
The Tools menu infrastructure is now ready to support the Import functionality. Future import tool can be implemented as a separate dropin that registers as a subtab under Tools, following the same pattern as Export.

### Step 2: Backfill Tab Implementation (Completed)

Added a new "Backfill" tab under the Tools menu that provides the GUI for generating history entries from existing WordPress data.

**Implementation Details:**

1. **Created `Import_Dropin`** (`dropins/class-import-dropin.php`)
   - Registers as subtab under Tools menu (order 3, after Overview and Export)
   - Contains complete backfill GUI with:
     - Success notices for backfill/delete operations
     - Preview section showing counts per post type and users
     - Options (post types, users, limit)
     - "Run Backfill" and "Delete Backfilled Data" buttons
   - Uses `history` icon in the page title

2. **Updated `Import_Handler`** (`inc/services/class-import-handler.php`)
   - Removed GUI rendering (moved to dropin)
   - Updated redirects to point to new Backfill tab instead of Experimental Features page

3. **Updated `Tools_Menu_Dropin`** (`dropins/class-tools-menu-dropin.php`)
   - Added Backfill to the "Available Tools" list on Overview page

4. **Menu Structure:**
   ```
   Export & Tools (menu item)
   └── Tools (main tab with redirect)
       ├── Overview (subtab)
       ├── Export (subtab)
       └── Backfill (subtab) ← NEW
   ```

5. **UX Copy Updates:**
   - Used "Backfill" terminology instead of "Import" to clarify that it generates history from existing WordPress data (not external file imports)
   - Clear descriptions explaining the feature scans posts, pages, and users to create log entries

**Files Created:**
- `dropins/class-import-dropin.php`

**Files Modified:**
- `inc/services/class-import-handler.php` (removed GUI, updated redirects)
- `dropins/class-tools-menu-dropin.php` (added Backfill to tools list)
