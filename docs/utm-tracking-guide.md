# UTM Tracking Guide - Simple History

Quick reference for creating tracking links and viewing them in Google Analytics 4.

## Why We Track Links

Track which premium features generate user interest by adding UTM parameters to external links. This helps understand what features users want most.

## Creating Tracking Links

### PHP - Use the Helper Function

```php
use Simple_History\Helpers;

// Standard usage
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_dashboard_sidebar'  // campaign identifier
);
```

### JavaScript - Use the Helper Function

```javascript
import { getTrackingUrl } from './functions';

// Standard usage
const url = getTrackingUrl(
    'https://simple-history.com/add-ons/premium/',
    'premium_stats_daterange'  // campaign identifier
);
```

## Campaign Naming Convention

Use format: `{category}_{location}_{action}`

### Common Examples:
- `premium_dashboard_sidebar` - Sidebar promo on main dashboard
- `premium_stats_daterange` - Date range feature in stats
- `premium_export_banner` - Export page banner promo
- `premium_events_ipaddress` - IP address Google Maps feature
- `docs_settings_help` - Documentation link from settings
- `support_error_page` - Support link from error page

### Categories:
- **Category**: `premium`, `docs`, `support`, `blog`
- **Location**: `dashboard`, `stats`, `export`, `settings`, `events`, `sidebar`, `banner`, `modal`
- **Action**: `daterange`, `ipaddress`, `maps`, `help`, `upgrade`

## What Gets Added to the URL

The helper functions add these UTM parameters:

```
?utm_source=wpadmin
&utm_medium=plugin
&utm_campaign=premium_dashboard_sidebar
```

**Why utm_campaign?**
- ✅ Shows in standard GA4 Traffic Acquisition reports (easy access!)
- ✅ No Custom Exploration needed
- ✅ Perfect for tracking different features

## Viewing Data in Google Analytics 4

### Recommended: Traffic Acquisition Report

**Best for:** Tracking performance over time (weekly/monthly reviews)

1. Go to **Reports** → **Acquisition** → **Traffic acquisition**
2. Click the dimension dropdown (says "Session default channel group")
3. Select **"Session campaign"**
4. You'll see your campaigns:
   - `premium_dashboard_sidebar` - 13 sessions
   - `premium_stats_daterange` - 2 sessions
   - `premium_events_ipaddress` - 1 session
   - etc.

**Change date range** (top right) to view any period: 7 days, 30 days, 90 days, etc.

**Sort by Sessions** to see which features generate the most interest.

### Alternative: Realtime (For Testing)

**Best for:** Immediate verification that tracking works

1. Go to **Reports** → **Realtime**
2. Click a tracking link
3. Within 30 seconds, you'll see the visit appear
4. Click through to see the campaign parameter

### Key Metrics to Watch

In Traffic Acquisition, view these columns:
- **Sessions** - How many visits from this campaign
- **Engaged sessions** - Quality visits (stayed more than 10 seconds)
- **Engagement rate** - % of visits that were engaged
- **Conversions** - If e-commerce is set up, shows purchases
- **Revenue** - Total revenue from this campaign

## Data Timing

- **Realtime reports**: Data appears in ~30 seconds
- **Traffic Acquisition**: Data appears in 24-48 hours

For immediate testing, use Realtime. For trend analysis, use Traffic Acquisition.

## Common Use Cases

### Track sidebar promo performance
```php
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_dashboard_sidebar'
);
```

### Track feature-specific links
```php
// Date range picker
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_stats_daterange'
);

// IP address maps
$url = Helpers::get_tracking_url(
    'https://simple-history.com/add-ons/premium/',
    'premium_events_ipaddress'
);
```

### Track documentation links
```php
$url = Helpers::get_tracking_url(
    'https://simple-history.com/docs/filters/',
    'docs_events_help'
);
```

## Quick Reference

| Task | Solution |
|------|----------|
| Create tracking link (PHP) | `Helpers::get_tracking_url($url, $campaign)` |
| Create tracking link (JS) | `getTrackingUrl(url, campaign)` |
| View performance over time | Traffic Acquisition → "Session campaign" dimension |
| Test immediately | Realtime reports (30 sec delay) |
| Compare time periods | Traffic Acquisition → change date range + toggle comparison |
| See which features convert | Traffic Acquisition → "Session campaign" → Conversions column |
