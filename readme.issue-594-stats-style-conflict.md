# Issue #594: Stats style between core and premium conflict

## Issue Summary

The new CSS styles for History Insights sidebar is overwritten by the styles from the Premium add-on when it is enabled. It looks like the premium completely removes CSS and JS from core to use its own instead.

## Problem

- History Insights sidebar CSS from core plugin is being overridden
- Premium add-on appears to completely remove CSS and JS from core and uses its own
- This causes styling conflicts when premium is enabled

## Status

- **Branch**: issue-594-stats-style-conflict
- **Project Board**: In progress
- **Labels**: bug, Simple History PREMIUM

## Findings

### Root Cause Identified

The premium add-on's **Stats Module** completely unhooks and replaces the core stats functionality, including CSS/JS:

**File**: `simple-history-premium/inc/modules/class-stats-module.php`

```php
protected function unhook_core_stats_page_contents() {
    $core_stats_service = $this->simple_history->get_service( Stats_Service::class );

    // Removes core stats page output
    remove_action( 'simple_history/stats/output_page_contents', [ $core_stats_service, 'output_page_contents' ] );

    // THIS IS THE PROBLEM: Removes core stats CSS/JS enqueuing
    remove_action( 'simple_history/enqueue_admin_scripts', [ $core_stats_service, 'enqueue_scripts_and_styles' ] );
}
```

**Impact:**
1. Core's `simple-history-stats.css` is NOT loaded when premium is active
2. Premium's replacement CSS at `simple-history-premium/inc/css/simple-history-stats.css` doesn't include styles for the sidebar "History Insights" widget
3. The sidebar widget (from `dropins/class-sidebar-stats-dropin.php`) continues to render but loses its styling

### Why This Approach Was Used

The premium add-on was designed to completely replace the stats dashboard page with an enhanced version. However, it didn't account for the fact that:
- Core stats CSS is shared between the stats page AND the sidebar widget
- Unhooking the entire stats service removes styling for both locations
- The sidebar widget is NOT replaced by premium, only the full stats page is

### The Problem in Detail

**Core Plugin Behavior:**
- `inc/services/class-stats-service.php` enqueues `css/simple-history-stats.css`
- This CSS file contains styles for BOTH:
  - Stats dashboard page (`.sh-StatsDashboard-*`)
  - Sidebar stats widget (`.sh-SidebarStats-*`)

**Premium Plugin Behavior:**
- Removes the core stats service's enqueue action
- Enqueues its own `simple-history-premium/inc/css/simple-history-stats.css`
- Premium CSS focuses on the stats dashboard page only
- Premium CSS is missing the `.sh-SidebarStats-*` styles needed by the sidebar widget

## Solution Options

### Option 1: Split Core CSS (Recommended)
Split core's stats CSS into two files:
- `css/simple-history-stats-dashboard.css` - Stats page only
- `css/simple-history-stats-sidebar.css` - Sidebar widget only

Then:
- Stats service enqueues dashboard CSS (premium can safely remove this)
- Sidebar dropin enqueues its own sidebar CSS (always loads, even with premium)

### Option 2: Add Missing Styles to Premium
Copy sidebar-specific styles (`.sh-SidebarStats-*`) from core to premium's stats CSS file.

**Pros:** Quick fix, minimal changes
**Cons:** Style duplication, harder to maintain

### Option 3: Selective Unhooking
Change premium to only unhook the stats page output, not the CSS enqueuing.
Add logic to conditionally load appropriate styles.

**Pros:** Less file reorganization
**Cons:** More complex logic, potential for other conflicts

## Implementation: Created Dedicated Service

We implemented a variation of Option 1 - but went further by creating a dedicated service instead of just splitting CSS.

### What Was Done

#### 1. Created New `History_Insights_Sidebar_Service`
**File**: `inc/services/class-history-insights-sidebar-service.php` ✅ CREATED

A new service class that encapsulates ALL sidebar widget functionality:
- Manages sidebar widget HTML output
- Enqueues Chart.js library
- Enqueues sidebar-specific CSS
- Outputs Chart.js initialization script
- Handles data fetching and caching
- Independent of `Stats_Service` - cannot be affected by premium removing it

#### 2. Created Dedicated Sidebar CSS
**File**: `css/simple-history-insights-sidebar.css` ✅ CREATED

Completely self-contained styling for the sidebar widget:
- Added `.sh-SidebarStats` class to widget container
- Includes ALL necessary styles (box, title, button, stats items)
- No dependencies on `styles.css` or `simple-history-stats.css`
- Widget will look identical whether premium is active or not
- Premium cannot accidentally change sidebar appearance by replacing CSS

#### 3. Created Dedicated Sidebar JavaScript
**File**: `js/simple-history-insights-sidebar.js` ✅ CREATED

Moved Chart.js initialization code from inline `<script>` tag to external JS file:
- Better code organization and maintainability
- Easier to debug and test
- Follows best practices (no inline scripts)
- Properly enqueued with dependencies

#### 4. Updated Main Stats CSS
**File**: `css/simple-history-stats.css` ✅ MODIFIED

Removed sidebar-specific styles (lines 567-597). Now only contains dashboard page styles.

#### 5. Deprecated Old Dropin
**File**: `dropins/class-sidebar-stats-dropin.php` ✅ MODIFIED

Properly deprecated following WordPress core patterns:
- All methods kept with same signatures for backward compatibility
- Each method calls `_deprecated_function()` with version and replacement
- All method bodies removed - only return empty values
- Class-level and method-level `@deprecated` PHPDoc tags added
- File kept for backward compatibility (prevents fatal errors if referenced)

### Benefits of This Approach

✅ **Better Organization**: All sidebar code (PHP, CSS, JS) now lives together in one service
✅ **Complete Independence**: Sidebar service cannot be broken by premium removing Stats Service
✅ **Follows Architecture**: Uses plugin's service pattern instead of dropin
✅ **Clean Separation**: Dashboard vs Sidebar are completely separate concerns
✅ **No Premium Changes**: Premium add-on works without any modifications
✅ **Maintainable**: All related code in one place, easier to update

### How It Works Now

**Without Premium:**
- `Stats_Service` - Loads dashboard CSS/JS and page
- `History_Insights_Sidebar_Service` - Loads sidebar CSS/JS and widget
- Both work independently

**With Premium:**
- Premium removes `Stats_Service` hooks (dashboard only)
- `History_Insights_Sidebar_Service` continues working (unaffected!)
- Sidebar keeps its styling ✅ THIS FIXES THE BUG

## Related Files

### Core Plugin - Modified
- `inc/services/class-history-insights-sidebar-service.php` - NEW service for sidebar widget
- `css/simple-history-insights-sidebar.css` - NEW CSS for sidebar only
- `js/simple-history-insights-sidebar.js` - NEW JavaScript for Chart.js initialization
- `css/simple-history-stats.css` - MODIFIED - removed sidebar styles
- `dropins/class-sidebar-stats-dropin.php` - DEPRECATED - now does nothing

### Core Plugin - Unchanged
- `inc/services/class-stats-service.php` - Stats service for dashboard page
- `dropins/class-sidebar-add-ons-dropin.php` - Promo boxes

### Premium Add-on - Unchanged
- `simple-history-premium/inc/modules/class-stats-module.php` - Module that unhooks core stats
- `simple-history-premium/inc/css/simple-history-stats.css` - Premium stats CSS

## Testing Status

- ✅ PHP linting passed
- ✅ PHPStan analysis passed
- ⏳ Manual testing without premium - TODO
- ⏳ Manual testing with premium - TODO
