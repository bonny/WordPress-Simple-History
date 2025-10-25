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

1. **Date Range (lines 30-38):** Updated to show promo only between November 24 - December 1, 2025
2. **Message (line 49):** Changed to "Black Week is Here - 30% Off All Add-Ons 🛍️"
3. **Urgency Text (line 52):** Updated to "Hurry - this sale ends December 1."
4. **UTM Parameters (line 57):** Changed to `utm_content=black-week-sale-sidebar`

### Expected Result

The pink promotional box will appear in the sidebar of the Simple History admin page during Black Week (Nov 24 - Dec 1, 2025) promoting the 30% discount. It maintains the same visual style as the New Year's promotion.

## Notes

- The promotion should be visible but not annoying to users
- Follow the project's upsell philosophy: "discreetly nudge" users to upgrade without being pushy
- The sidebar promotion is already implemented and just needs content updates
- Current implementation hides the promo if Premium add-on is active (which is correct)
- **Next Step:** Create the 30% discount code in Lemon Squeezy before Black Week starts
