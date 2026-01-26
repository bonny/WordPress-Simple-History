# Issue #582: Add "Hosted by Oderland" Sponsorship Acknowledgment

## Background

Oderland (Swedish web hosting company) sponsors the Simple History website hosting and would like a small acknowledgment in the plugin. The request is for a discreet "Hosted by Oderland" mention somewhere in the plugin admin interface.

From the sponsor:

> It would be nice with a small, small mention (just "hosted by") somewhere on the plugin itself where obviously the majority of all traffic is. Just a short mention like "hosted by Oderland" where the users are, if possible.

## Requirements

-   ✅ Discreet placement (not intrusive)
-   ✅ In plugin admin interface (where users are)
-   ✅ WordPress.org compliant (no public-facing links without permission)
-   ✅ Translatable (support multiple languages)
-   ✅ Text: "Hosted by Oderland"
-   ✅ Clear context (users should understand what's hosted)

## WordPress.org Guidelines Compliance

**Status**: ✅ COMPLIANT

WordPress.org guidelines prohibit external links/credits on **public-facing website** without user permission, but **admin area links are allowed**.

Key findings:

-   ❌ Not allowed: "Powered by" links on front-end without opt-in
-   ✅ Allowed: Links and credits in WordPress admin interface
-   ✅ Allowed: Developer links in plugin settings/dashboard

All proposed solutions are in admin area only → fully compliant.

**Source**: [WordPress.org Plugin Directory Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)

## Explored Placement Options

### Option 1: Sidebar Postbox ⭐ RECOMMENDED

**File**: `dropins/class-sidebar-dropin.php`

**Implementation**:

-   New postbox in sidebar (after review box, before support box)
-   Title: "Website Hosting" / "Webbhotell"
-   Content: Explanation text + Oderland logo + link
-   Style: Follow existing `.sh-PremiumFeaturesPostbox` pattern

**Pros**:

-   Clear context (explains what's hosted)
-   Professional presentation
-   Room for logo
-   Always visible in sidebar
-   Follows established UI pattern

**Cons**:

-   More prominent (but requested to be visible)
-   Takes sidebar space

**Example layout**:

```
┌─────────────────────────────┐
│ Website Hosting             │
├─────────────────────────────┤
│ [Oderland Logo]             │
│                             │
│ The Simple History website  │
│ is kindly hosted by         │
│ Oderland.                   │
│                             │
│ Visit Oderland →            │
└─────────────────────────────┘
```

---

### Option 2: Dashboard Footer (React)

**File**: `src/components/DashboardFooter.jsx`

**Implementation**:

-   Add 4th link in existing HStack (Blog | Support | Get Premium | **Hosted by Oderland**)
-   Text: "SimpleHistory.com hosted by Oderland" (clearer than just "Hosted by")
-   Only visible on main dashboard view

**Pros**:

-   Very discreet
-   Fits naturally with existing links
-   Only on dashboard (not settings pages)

**Cons**:

-   Less context without additional explanation
-   Lower visibility
-   Mixed in with other links

---

### Option 3: Admin Footer Text

**File**: `dropins/class-donate-dropin.php`

**Implementation**:

-   Append to existing WordPress admin footer text
-   Text: "| Hosting sponsored by Oderland"

**Pros**:

-   Extremely discreet
-   Standard WordPress pattern
-   Visible on all Simple History admin pages

**Cons**:

-   Very low visibility (footer text often ignored)
-   Less professional presentation

---

### Option 4: Combination Approach

Multiple subtle placements:

1. Sidebar postbox (primary)
2. Dashboard footer (secondary)

**Pros**:

-   Multiple touchpoints
-   Better coverage of different user paths

**Cons**:

-   More implementation work
-   Risk of being too repetitive

---

## Recommended Solution

**Sidebar Postbox (Option 1)** for these reasons:

1. ✅ Clear context - users understand what's being hosted
2. ✅ Professional presentation with logo
3. ✅ Visible but not intrusive
4. ✅ Follows plugin's established UI patterns
5. ✅ WordPress.org compliant
6. ✅ Room for proper acknowledgment

If too prominent, fall back to **Dashboard Footer (Option 2)** with clearer text.

## Implementation Details

### Text Options

**English**:

-   Heading: "Website Hosting"
-   Body: "The Simple History website is kindly hosted by Oderland, a Swedish web hosting provider."
-   Link: "Visit Oderland"

**Swedish**:

-   Heading: "Webbhotell"
-   Body: "Simple Historys webbplats driftas med sponsring från Oderland, en svensk webbhotellsleverantör."
-   Link: "Besök Oderland"

### Required Assets

-   [ ] Oderland logo (SVG or PNG, ~120-150px wide recommended)
-   [ ] Oderland website URL (https://www.oderland.se)

### CSS Styling

Use existing postbox classes:

-   `.postbox` - WordPress standard meta box
-   `.sh-PremiumFeaturesPostbox` - Plugin's promotional box styling
-   Consider cream/light background to distinguish from core plugin content

### Translation

All text must use translation functions:

```php
__('Website Hosting', 'simple-history')
```

Text domain: `simple-history`

## Technical Implementation

### Files to Modify

**Primary (Sidebar Box)**:

-   `dropins/class-sidebar-dropin.php` - Add new postbox method

**Alternative (Dashboard Footer)**:

-   `src/components/DashboardFooter.jsx` - Add ExternalLink component
-   Run `npm run build` after changes

### Code Standards

-   PHP 7.4+ compatibility
-   WordPress Coding Standards
-   Proper escaping: `esc_url()`, `esc_html()`
-   Translation ready: `__()`, `_e()`
-   External links: `target="_blank"` with `rel="noopener noreferrer"`

## Testing Checklist

-   [ ] Verify box appears in sidebar on main Simple History page
-   [ ] Check text is properly translated (if using Swedish)
-   [ ] Confirm link opens Oderland website in new tab
-   [ ] Test responsive layout (small screens)
-   [ ] Verify CSS matches existing postbox styling
-   [ ] Confirm WordPress.org compliance (admin area only)
-   [ ] Check appearance with/without other sidebar boxes

## Next Steps

1. **Decision needed**: Sidebar box or Dashboard footer?
2. **Asset needed**: Oderland logo file
3. **Language decision**: Primary language (English/Swedish)?
4. Implement chosen solution
5. Test locally
6. Submit for review/merge

## Links

-   GitHub Issue: #582
-   Branch: `issue-582-add-oderland-hosting-acknowledgment`
-   Oderland: https://www.oderland.se
-   WordPress.org Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

## Notes

-   Keep it simple and professional
-   Don't be pushy or sales-y
-   Make it clear what's being hosted (the website, not the user's WP installation)
-   Follow plugin's existing design patterns
-   Ensure WordPress.org compliance

## Progress Log

-   2025-01-19: Initial research and planning
-   2025-01-19: Created branch and issue readme
-   [ ] Pending: Get Oderland logo
-   [ ] Pending: Finalize placement decision
-   [ ] Pending: Implementation
-   [ ] Pending: Testing
-   [ ] Pending: Review and merge
