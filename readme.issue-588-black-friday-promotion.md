# Issue #588: Add Black Friday/Week discount promotion in the plugin GUI

## Issue Details

- **Issue Number:** #588
- **Status:** In progress
- **URL:** https://github.com/bonny/WordPress-Simple-History/issues/588

## Description

Add a Black Friday/Week discount promotion in the plugin GUI to encourage users to upgrade to the premium version during this promotional period.

## Existing Implementation

There is already a promotional system in place:

**Location:** `/Users/bonny/Projects/Personal/WordPress-Simple-History/dropins/class-sidebar-add-ons-dropin.php:24-62`

The `on_sidebar_html_sale_promo()` method currently shows a New Year's sale promotion:
- Message: "Our New Year's Sale is Here - 50% Off All Add-Ons 🙀"
- Date check: Hides after January 31, 2025 (line 31)
- Display: Pink box in the sidebar
- Link: Points to https://simple-history.com/add-ons/ with UTM parameters

**The GUI already exists!** We just need to update:
1. The date range to match Black Friday week
2. The promotional message
3. The discount percentage (if different from 50%)
4. The UTM parameters in the link

## Tasks / Progress

- [x] **Create discount code in Lemon Squeezy** - Code `BLACKWEEK30` created (30% off, Nov 24 - Dec 1, 2025)
- [x] Determine Black Week date range: **November 24 - December 1, 2025**
- [x] Determine discount percentage: **30% Off**
- [x] Update the date check to use WordPress timezone-aware functions (`current_datetime()` + `wp_timezone()`)
- [x] Write compelling, value-focused promotional copy
- [x] Focus promotion on Premium add-on only
- [x] Add promo code display (BLACKWEEK30)
- [x] Update UTM parameters to `black-week-sale-sidebar`
- [x] Test the implementation (PHP linting passed)
- [x] Ensure it follows the non-intrusive upsell philosophy

## Implementation Summary

### What Was Changed

**File Modified:** `dropins/class-sidebar-add-ons-dropin.php`

1. **Preview Mode (line 26):** Added `$preview_promotion` variable - set to `true` to test promo outside date range
2. **Date Range (lines 33-50):** Updated to use WordPress timezone-aware functions:
   - Uses `current_datetime()` and `wp_timezone()` instead of `time()` and `strtotime()`
   - Respects site's timezone setting from Settings > General
   - Active November 24, 2025 00:00:00 through December 1, 2025 23:59:59
3. **Headline (line 54):** Value-focused copy: "Export Logs, Keep History Longer & Add Custom Events 🛍️"
4. **Body Copy (line 57):** Pain-point driven text addressing the 60-day retention limit
5. **Promo Code (line 70):** Displays "Use code BLACKWEEK30 at checkout" below the button
6. **Button & Link (lines 60-66):**
   - Button text: "Get Premium Now"
   - Links directly to `/add-ons/premium/` (not general add-ons page)
   - UTM parameter: `utm_content=black-week-sale-sidebar`

### Final Promotional Copy

**Headline:** Export Logs, Keep History Longer & Add Custom Events 🛍️

**Body:** Don't lose important history after 60 days. Premium keeps your logs as long as you need, plus adds exports, custom events, and powerful filtering. Save 30% (ends December 1).

**Promo Code:** BLACKWEEK30

**Button:** Get Premium Now

### Expected Result

The pink promotional box will appear in the sidebar of the Simple History admin page during Black Week (Nov 24 - Dec 1, 2025), using the site's configured timezone. The promotion focuses on Premium add-on value first, discount second.

## Notes

- The promotion follows the non-intrusive upsell philosophy
- Hides automatically if Premium add-on is active
- Discount code `BLACKWEEK30` has been created in Lemon Squeezy (30% off, Nov 24 - Dec 1, 2025)
- Uses modern WordPress timezone functions for accurate date handling across all timezones
- Set `$preview_promotion = true` on line 26 to test the promo before November 24th
