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
- [x] Created centralized URL builder functions
  - PHP: `Helpers::get_tracking_url()` in `inc/class-helpers.php:1991`
  - JavaScript: `getTrackingUrl()` in `src/functions.js:262`
- [ ] Document tracking structure
- [ ] Update existing links to use consistent naming
- [ ] Add tracking to missing locations
- [ ] Test updated tracking in GA4

## Tracking URL Structure

### UTM Parameter Strategy

```
utm_source=wpadmin              // Traffic source (WordPress admin)
utm_medium=plugin               // Medium type (plugin UI)
utm_campaign=premium            // Campaign (standardized to 'premium')
utm_content={section}_{location}_{action}  // Specific feature identifier
```

### Content Identifier Format

Use hierarchical naming: `{section}_{location}_{action}`

**Examples:**
- `dashboard_sidebar_premium` - Main sidebar promo
- `stats_daterange_premium` - Date range feature in stats
- `export_banner_premium` - Export page promo
- `events_ipaddress_maps` - Google Maps for IP feature
- `global_modal_unlock` - Premium unlock modal

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

// Basic usage
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'dashboard_sidebar_premium'
);

// Custom campaign
$url = Helpers::get_tracking_url(
    'https://simple-history.com/support/',
    'settings_help_support',
    'support'  // campaign
);
```

### JavaScript/React Usage

```javascript
import { getTrackingUrl } from './functions';

// Basic usage
const url = getTrackingUrl(
    'https://simple-history.com/add-ons/premium/',
    'dashboard_sidebar_premium'
);

// Custom campaign
const url = getTrackingUrl(
    'https://simple-history.com/docs/',
    'filter_help_documentation',
    'documentation'  // campaign
);
```

## Tracking Coverage

### Currently Tracked Locations

✅ Sidebar premium promos
✅ Stats dashboard features (date ranges, charts)
✅ Premium unlock modal
✅ IP address Google Maps feature
✅ Login attempts limiting
✅ Footer links
✅ Header add-ons link

### Missing or Needs Improvement

- [ ] Export dropin page (no tracking currently)
- [ ] Settings page premium features
- [ ] Individual logger premium prompts
- [ ] Notification bar links (commented out)
- [ ] Inconsistent utm_campaign values across codebase

## Files Modified

- `inc/class-helpers.php` - Added `get_tracking_url()` method
- `src/functions.js` - Added `getTrackingUrl()` function

## Files to Update

### PHP Files
- `dropins/class-sidebar-add-ons-dropin.php` - Update sidebar promos
- `inc/class-stats-view.php` - Update stats feature links
- `inc/services/class-setup-settings-page.php` - Update settings links
- `inc/services/class-admin-page-premium-promo.php` - Update promo page

### JavaScript Files
- `src/components/PremiumFeaturesUnlockModal.jsx` - Update modal link
- `src/components/EventOccasions.jsx` - Update login limit link
- `src/components/EventIPAddresses.jsx` - Update IP maps link
- `src/components/DashboardFooter.jsx` - Update footer links

## Viewing Tracking Data in GA4

### Real-Time View
1. Go to GA4 → **Reports** → **Realtime**
2. Scroll to **Event count by Event name**
3. Click `page_view` event
4. Click **content** parameter to see breakdown

### Campaign Reports (after 24 hours)
1. **Reports** → **Acquisition** → **Traffic acquisition**
2. Find `wpadmin / plugin` row
3. Click to drill down
4. Change dimension to **Session manual ad content**

### Custom Exploration (Recommended)
1. **Explore** → Create new exploration
2. Add dimensions: Session campaign, Session manual ad content
3. Add metrics: Sessions, Engaged sessions
4. Filter: Session source = wpadmin
5. Result: Ranked list of which features drive clicks!

## Testing

Test URLs to verify GA4 tracking:

```bash
# Test 1: Dashboard sidebar
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium&utm_content=dashboard_sidebar_premium

# Test 2: Stats date range
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium&utm_content=stats_daterange_premium

# Test 3: Export banner
https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_medium=plugin&utm_campaign=premium&utm_content=export_banner_premium
```

## Notes

- GA4 property ID: `GT-5N5QNLF` (installed via Google Site Kit)
- UTM parameters are successfully captured by GA4
- Realtime reports show "(direct) / (none)" for returning visitors due to "First user source" attribution
- Session-level attribution in Traffic Acquisition report shows correct UTM data
- Data appears in reports within 24-48 hours

## Privacy & Best Practices

- UTM tracking is privacy-friendly (no personal data collected)
- Parameters are visible in URLs (transparent to users)
- Follows WordPress plugin guidelines
- Maintains non-intrusive upsell philosophy
