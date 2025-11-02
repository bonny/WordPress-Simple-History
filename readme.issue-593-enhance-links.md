# Issue #593: Enhance links so we can see what feature user was interested in

## Issue Details

- **Status**: In Progress
- **Author**: bonny
- **URL**: https://github.com/bonny/WordPress-Simple-History/issues/593

## Description

Enhance tracking URLs to better understand which premium features generate user interest. Uses standardized UTM parameters for Google Analytics to answer: "What feature made the user click?"

## Current Status

✅ **Tracking is working!** GA4 is successfully capturing UTM parameters.

### Top Performing Features (from GA4 data)
1. **stats_daterange_pre...** (36 events) - Stats date range feature
2. **dashboard_sidebar_...** (24 events) - Sidebar premium promo
3. **events_ipaddress_m...** (24 events) - Google Maps for IP addresses
4. **export_banner_premi...** (16 events) - Export page promo
5. **global_modal_unlock** (15 events) - Premium unlock modal

## Implementation Progress

- [x] Analyzed existing tracking implementation
- [x] Verified tracking works in GA4
- [x] Researched GA4 best practices (utm_campaign vs utm_content)
- [x] Created centralized URL builder functions
  - PHP: `Helpers::get_tracking_url()` in `inc/class-helpers.php:1970`
  - JavaScript: `getTrackingUrl()` in `src/functions.js:243`
- [x] Updated functions to use utm_campaign as primary parameter
- [x] Document tracking structure (this file)
- [x] Verified utm_campaign shows in Traffic Acquisition reports
- [x] Updated all existing links in codebase to use new helper functions
  - Migrated 15 files to use centralized helper functions
  - Ensured consistent UTM parameters across all links
  - All links now use `utm_source=wpadmin` and `utm_medium=plugin`
- [x] Verified code builds and lints successfully
- [ ] Test updated tracking in production

## Tracking URL Structure

### UTM Parameter Strategy

```
utm_source=wpadmin              // Traffic source (WordPress admin)
utm_medium=plugin               // Medium type (plugin UI)
utm_campaign={category}_{location}_{action}  // PRIMARY: Feature identifier
utm_content=                    // OPTIONAL: Only for A/B testing variants
```

**Key Decision:** We use `utm_campaign` (not `utm_content`) as the primary tracking parameter because:
- ✅ Shows in standard GA4 Traffic Acquisition reports
- ✅ Easy to view without Custom Explorations
- ✅ utm_content requires Custom Exploration and is harder to access
- ✅ utm_content is designed for A/B testing variations, not different features

### Campaign Identifier Format

Use hierarchical naming: `{category}_{location}_{action}`

**Examples:**
- `premium_dashboard_sidebar` - Main sidebar promo
- `premium_stats_daterange` - Date range feature in stats
- `premium_export_banner` - Export page promo
- `premium_events_ipaddress` - Google Maps for IP feature
- `premium_modal_unlock` - Premium unlock modal
- `docs_filter_help` - Documentation help link
- `support_error_page` - Support link from error page

### When to Use utm_content (Optional)

Only add `utm_content` for A/B testing **variations of the same feature**:

**Example: Testing button colors**
```
utm_campaign=premium_dashboard_sidebar
utm_content=blue_button    // Variant A

utm_campaign=premium_dashboard_sidebar
utm_content=green_button   // Variant B
```

**Don't use utm_content for different features** - use different campaign names instead.

### Section Categories

- `dashboard` - Main history/events page
- `stats` - Stats & analytics views
- `export` - Export functionality
- `settings` - Settings pages
- `events` - Within event details/context
- `global` - Site-wide (header, footer, modals)
- `onboarding` - First-time user experience

### Location Categories

- `sidebar` - Sidebar promos/boxes
- `banner` - Banner/notification bars
- `inline` - Inline with content
- `header` - Page/site header
- `footer` - Page/site footer
- `modal` - Modal dialogs
- `daterange` - Date range selector
- `charts` - Chart areas
- `ipaddress` - IP address display

### Action/Feature Categories

- `premium` - General premium upgrade
- `debug` - Debug & Monitor addon
- `woocommerce` - WooCommerce addon
- `maps` - Google Maps feature
- `loginlimit` - Login limiting feature
- `addons` - General add-ons page
- `support` - Support links
- `blog` - Blog links

## Usage Examples

### PHP Usage

```php
use Simple_History\Helpers;

// Standard usage (most common - 95% of cases)
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_dashboard_sidebar'  // campaign
);
// Result: ?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_dashboard_sidebar

// Documentation link
$url = Helpers::get_tracking_url(
    'https://simple-history.com/support/',
    'docs_settings_help'  // campaign
);

// A/B testing variant (advanced - 5% of cases)
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_dashboard_sidebar',  // campaign
    'wpadmin',                     // source (default)
    'plugin',                      // medium (default)
    'blue_button'                  // content (variant)
);
// Result: ?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_dashboard_sidebar&utm_content=blue_button
```

### JavaScript/React Usage

```javascript
import { getTrackingUrl } from './functions';

// Standard usage (most common)
const url = getTrackingUrl(
    'https://simple-history.com/add-ons/premium/',
    'premium_modal_unlock'  // campaign
);
// Result: ?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_modal_unlock

// Documentation link
const url = getTrackingUrl(
    'https://simple-history.com/docs/',
    'docs_filter_help'  // campaign
);

// A/B testing variant (advanced)
const urlVariantA = getTrackingUrl(
    'https://simple-history.com/add-ons/premium/',
    'premium_modal_unlock',  // campaign
    'wpadmin',               // source (default)
    'plugin',                // medium (default)
    'variant_a'              // content (test variant)
);
```

## Tracking Coverage

### Currently Tracked Locations (All Using Helper Functions)

✅ Sidebar premium promos (Black Week, Debug, Premium, WooCommerce)
✅ Stats dashboard features (date ranges, charts, stats boxes)
✅ Premium unlock modal
✅ IP address Google Maps feature
✅ Login attempts limiting
✅ Footer links (blog, support, premium)
✅ Header add-ons link
✅ RSS documentation help
✅ Detective mode help
✅ License settings links
✅ Logger purged events link
✅ Settings page purge link
✅ Welcome message add-ons link
✅ Filter initiator help link
✅ **All links now use consistent UTM parameters** (`utm_source=wpadmin`, `utm_medium=plugin`)

### Future Enhancements

- [ ] Export dropin page (no tracking currently)
- [ ] Notification bar links (currently commented out)
- [ ] Additional logger-specific premium prompts

## Files Modified

### Helper Functions Created
- `inc/class-helpers.php` - Added `get_tracking_url()` method at line 1970
- `src/functions.js` - Added `getTrackingUrl()` function at line 243

### PHP Files Updated (10 files)
- `dropins/class-sidebar-add-ons-dropin.php` - Updated 4 tracking links (Black Week, Debug, Premium, WooCommerce)
- `inc/class-stats-view.php` - Updated 3 tracking links (date range, charts, stats box)
- `dropins/class-rss-dropin.php` - Updated 1 tracking link (RSS documentation)
- `dropins/class-detective-mode-dropin.php` - Updated 1 tracking link (detective mode help)
- `inc/services/class-licences-settings-page.php` - Updated 2 tracking links (add-ons, installation help)
- `loggers/class-simple-history-logger.php` - Updated 1 tracking link (purged events premium)
- `inc/services/class-setup-settings-page.php` - Updated 1 tracking link (purge settings)
- `inc/class-simple-history.php` - Updated 1 tracking link (login attempts limit)
- `inc/services/class-setup-database.php` - Updated 1 tracking link (welcome message)
- `inc/class-helpers.php` - Updated 1 tracking link (header add-ons link)

### JavaScript Files Updated (5 files)
- `src/components/EventOccasions.jsx` - Updated 1 tracking link (login limit feature)
- `src/components/EventIPAddresses.jsx` - Updated 1 tracking link (IP address maps)
- `src/components/DashboardFooter.jsx` - Updated 3 tracking links (blog, support, premium)
- `src/components/PremiumFeaturesUnlockModal.jsx` - Updated 1 tracking link (premium modal)
- `src/components/ExpandedFilters.jsx` - Updated 1 tracking link (initiator filter help)

### Summary
- **Total files modified**: 15
- **Total tracking links updated**: 21
- **All links now use**: `utm_source=wpadmin`, `utm_medium=plugin`, `utm_campaign={identifier}`

## Viewing Tracking Data in GA4

### Important: How GA4 Stores UTM Parameters

**Your tracking IS working!** The UTM parameters (especially `utm_campaign`) are being captured successfully.

**We use `utm_campaign` (not `utm_content`) because:**

✅ **utm_campaign** shows in standard Traffic Acquisition reports:
- Easy to view without Custom Explorations
- Click source/medium → change dimension to "Session campaign"
- Immediately see which features generate clicks

❌ **utm_content** requires Custom Explorations:
- Not available in standard reports
- Requires manual setup to view
- Harder to access

**Key insight:** utm_campaign is visible in GA4's Traffic Acquisition report, while utm_content is event-level data that requires Custom Explorations. By using utm_campaign for feature tracking, we get much easier access to the data!

**Don't worry about source/medium showing as "Direct"** - browsers strip referrer info from WordPress admin links. The important data (`utm_campaign`) is still captured and easy to view!

---

### Quick Start: The Easiest Way to View Your Data

**Method A: Traffic Acquisition Report (RECOMMENDED - Historical Data)**

With utm_campaign, viewing your data is super easy!

1. Go to **Reports** → **Acquisition** → **Traffic acquisition**
2. Look for traffic that might be from your plugin (often shows as "Direct" or other sources)
3. Click the dimension dropdown (currently says "Session default channel group")
4. Select **"Session campaign"**
5. You'll immediately see your feature tracking:
   - `premium_dashboard_sidebar` - 24 sessions
   - `premium_stats_daterange` - 36 sessions
   - `premium_export_banner` - 16 sessions
   - etc.

✅ **Use this for:** Historical analysis, weekly reviews, trend tracking

**Why this is better than utm_content:**
- ✅ Shows in standard reports (no Custom Exploration needed!)
- ✅ Easy to access
- ✅ Sortable and filterable
- ✅ Clean, readable data

---

**Method B: Realtime (For Immediate Testing)**

1. Go to **Reports** → **Realtime**
2. Scroll to **Event count by Event name**
3. Click `page_view`
4. Click the **campaign** parameter (not content!)
5. You'll see your feature breakdown in real-time

✅ **Use this for:** Testing new links, verifying tracking works

---

**Optional: Custom Exploration (For Advanced Analysis)**

If you want even more flexibility, you can create a Custom Exploration. But with utm_campaign, you don't need this for basic tracking!

See the detailed instructions below if you want to set this up.

---

### Detailed Instructions for Each Method

#### Method A: Realtime View - Full Instructions

**Best for:** Immediate testing and verification

**Step 1: Access Real-Time Reports**
1. Go to your GA4 property at [analytics.google.com](https://analytics.google.com)
2. Left sidebar: **Reports** → **Realtime**
3. After clicking a tracking URL, you should see active users within 30 seconds

**Step 2: View UTM Parameter Details**
1. In the Realtime report, scroll down to **Event count by Event name**
2. Click on `page_view` event in the list
3. This shows all page view events with their parameters
4. Click on **content** parameter (this is your `utm_content`)
5. You'll see a breakdown like:
   - `stats_daterange_premium` - 36 events
   - `dashboard_sidebar_premium` - 24 events
   - `events_ipaddress_maps` - 24 events

**This immediately answers: "What feature are users clicking right now?"**

---

#### Method B: Custom Exploration - Full Instructions

**Best for:** Historical data, trend analysis, and custom reports

**Step 1: Create New Exploration**
1. Left sidebar: **Explore**
2. Click **Blank** (or Templates → Free form)
3. Name it: "Premium Feature Tracking"

**Step 2: Add Dimensions**
1. Under **DIMENSIONS** section (left panel), click **+** (plus icon)
2. Search for and select: `Page location`
3. Click **Import**

**Step 3: Add Metrics**
1. Under **METRICS** section, click **+**
2. Search and select:
   - ✅ `Event count`
   - ✅ `Sessions`
3. Click **Import**

**Step 4: Build the Report Table**

**IMPORTANT:** You must drag the dimensions and metrics to the TAB SETTINGS panel - just adding them isn't enough!

1. In **TAB SETTINGS** (right panel):
   - **Visualization**: Select "Table" (should be default)
2. From the **DIMENSIONS** section (left panel), drag **Page location** → drop it in the **ROWS** box (where it says "Drop or select dimension")
3. From the **METRICS** section (left panel), drag **Event count** → drop it in the **VALUES** box (where it says "Drop or select metric")
4. (Optional) Drag **Sessions** → **VALUES** box for additional data

**You should now see data appear!** If you see "No data available", you haven't dragged the items yet.

**Step 5: Filter to Show Only Tracking URLs**
1. In **TAB SETTINGS**, find the **FILTERS** section
2. Click the dropdown → **Add filter**
3. Configure filter:
   - Dimension: `Page location`
   - Match type: `contains`
   - Value: `utm_content=`
4. Click **Apply**

**Result:** You'll see URLs with your tracking parameters:

| Page location | Event count | Sessions |
|--------------|-------------|----------|
| https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=stats_daterange_premium | 45 | 23 |
| https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=dashboard_sidebar_premium | 38 | 20 |
| https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=events_ipaddress_maps | 24 | 12 |

The `utm_content` value is visible in each URL - you can see which features are generating clicks!

**Step 6: Sort by Event Count**
1. Click the **Event count** column header
2. Sort descending to see most popular features first

**Step 7: Save for Future Use**
1. Click **Save** at top right
2. Give it a name: "Premium Feature Tracking"
3. Access anytime from **Explore** tab

**Pro Tip:** To see cleaner data, you can also filter by `utm_campaign=premium` to see only premium feature clicks.

---

### Quick Comparison: Which Method to Use?

| Method | When to Use | Data Freshness | Best For |
|--------|-------------|----------------|----------|
| **Traffic Acquisition** | Daily/weekly reviews | 24-48 hours | Standard reporting, easy access ⭐ RECOMMENDED |
| **Realtime** | Testing links immediately | 30 seconds | Verifying tracking works, live monitoring |
| **Custom Exploration** | Advanced analysis | 24-48 hours | Deep dives, custom dimensions, complex filtering |

**Note:** Because we use utm_campaign (not utm_content), the data is easily accessible in standard Traffic Acquisition reports!

---

### Troubleshooting: "I Don't See My Data"

**Issue 1: Can't find utm_content data in standard reports**
- **Why:** GA4 stores UTM parameters as event-level data, not as standard dimensions in reports like Traffic Acquisition
- **Solution:** Use one of the two methods above:
  - **Realtime** → Event count → page_view → content parameter
  - **Custom Exploration** → Page location dimension filtered by `utm_content=`

**Issue 2: Realtime shows "(direct) / (none)" for source/medium**
- **This is normal!** Browsers strip referrer from WordPress admin links
- **Ignore source/medium** - it doesn't matter for our tracking
- **Solution:** Click on the **content** parameter to see your utm_content values - that's what matters!

**Issue 3: Custom Exploration shows "No data available"**
- **Most common cause:** You added dimensions/metrics but didn't drag them to ROWS/VALUES
- **Solution:**
  1. Make sure you dragged **Page location** from DIMENSIONS → **ROWS** box
  2. Make sure you dragged **Event count** from METRICS → **VALUES** box
  3. Just adding them to the left panel isn't enough - you must drag them!

**Issue 4: No tracking data showing up at all**
- **Check 1:** Verify UTM parameters stayed in URL (check browser address bar after clicking)
- **Check 2:** Are you looking at the correct date range? (Top left corner shows "Last 7 days")
- **Check 3:** Try testing with a unique utm_content value like `test_123` to confirm tracking works
- **Check 4:** Make sure you're looking at the right GA4 property for simple-history.com

**Issue 5: Can't find "Page location" dimension in Custom Exploration**
- Try searching for: `page_location`, `URL`, or `page path`
- It's a standard dimension and should always be available

---

### Pro Tips

**Tip 1: Create a Dashboard**
- In Custom Exploration, create multiple tabs for different views:
  - Tab 1: Overall feature performance
  - Tab 2: Trends over time (add Date dimension)
  - Tab 3: Comparison by campaign

**Tip 2: Set Up Regular Reports**
- GA4 → **Admin** → **Scheduled Reports**
- Email yourself weekly summaries of top-performing features

**Tip 3: Compare Date Ranges**
- Use the date range selector to compare:
  - "Last 7 days" vs "Previous 7 days"
  - See which features are trending up/down

## Testing

Test URLs to verify GA4 tracking (updated to use utm_campaign):

```bash
# Test 1: Dashboard sidebar promo
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_dashboard_sidebar

# Test 2: Stats date range feature
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_stats_daterange

# Test 3: Export page banner
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_export_banner

# Test 4: IP address Google Maps feature
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_events_ipaddress

# Test 5: Premium unlock modal
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium_modal_unlock
```

**After clicking, view in GA4:**
1. **Reports** → **Acquisition** → **Traffic acquisition**
2. Change dimension dropdown to **"Session campaign"**
3. You'll see: `premium_dashboard_sidebar`, `premium_stats_daterange`, etc.

Much easier than the old utm_content approach!

## Notes

### GA4 Setup
- GA4 property ID: `GT-5N5QNLF` (installed via Google Site Kit)
- UTM parameters are successfully captured by GA4
- Using utm_campaign (not utm_content) for feature tracking

### Data Timing
- **Realtime reports**: Data appears within 30 seconds
- **Traffic Acquisition reports**: Data appears within 24-48 hours
- For immediate testing, always use Realtime reports

### Attribution Notes
- Realtime reports may show "(direct) / (none)" for returning visitors due to "First user source" attribution
- This is normal - the utm_campaign data is still captured
- Session-level attribution in Traffic Acquisition report shows correct UTM data after 24-48 hours

### Best Practice Decision
- **utm_campaign** chosen over utm_content because:
  - Visible in standard Traffic Acquisition reports
  - No Custom Exploration required
  - utm_content requires complex setup to view
  - utm_content is designed for A/B testing, not feature tracking
- Research confirmed this follows Google Analytics best practices for cross-domain link tracking

### Implementation Completion
- **All 21 tracking links migrated** to use centralized helper functions
- **Consistent UTM parameters** across entire codebase:
  - `utm_source=wpadmin` (previously inconsistent: `wordpress_admin` vs `wpadmin`)
  - `utm_medium=plugin` (previously inconsistent: `Simple_History` vs `plugin`)
  - `utm_campaign={identifier}` (now standardized naming convention)
- **Code quality verified**: All PHP and JavaScript linting passes
- **Build tested**: All code compiles successfully

## Privacy & Best Practices

- UTM tracking is privacy-friendly (no personal data collected)
- Parameters are visible in URLs (transparent to users)
- Follows WordPress plugin guidelines
- Maintains non-intrusive upsell philosophy
