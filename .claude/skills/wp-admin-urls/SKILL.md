---
name: wp-admin-urls
description: 'Patterns for building WordPress admin page URLs in Simple History. Covers menu location awareness, settings tabs/sub-tabs, and avoiding hardcoded URLs. Triggers: building admin links, menu URLs, settings page links, navigating between pages.'
allowed-tools: Read, Grep, Glob
---

# WordPress Admin URL Building

Simple History's admin pages can live in different locations depending on user settings (top-level, inside Tools, inside Dashboard). **Never hardcode admin URLs.** Always use the helper methods.

## Key Helpers

All in `inc/class-helpers.php`:

| Method                                             | Returns               | Example                                            |
| -------------------------------------------------- | --------------------- | -------------------------------------------------- |
| `Helpers::get_history_admin_url()`                 | Main history page URL | `admin.php?page=simple_history_admin_menu_page`    |
| `Helpers::get_settings_page_url()`                 | Settings page URL     | `admin.php?page=simple_history_settings_page`      |
| `Helpers::get_settings_page_tab_url($tab)`         | Settings tab URL      | `...&selected-tab=general_settings_subtab_general` |
| `Helpers::get_settings_page_sub_tab_url($sub_tab)` | Settings sub-tab URL  | `...&selected-tab=...&selected-sub-tab=...`        |

## Why URLs Are Dynamic

The base admin URL changes with menu location:

| Location setting   | History base         | Settings base                  |
| ------------------ | -------------------- | ------------------------------ |
| `top` / `bottom`   | `admin.php?page=...` | `admin.php?page=...`           |
| `inside_tools`     | `tools.php?page=...` | `options-general.php?page=...` |
| `inside_dashboard` | `index.php?page=...` | `options-general.php?page=...` |

## Menu Page Slugs

Defined as constants in `inc/class-simple-history.php`:

-   `Simple_History::MENU_PAGE_SLUG` - Main history page
-   `Simple_History::SETTINGS_MENU_PAGE_SLUG` - Settings page

## Settings Tab URL Structure

Settings uses a tab/sub-tab system with query parameters:

```
?page=simple_history_settings_page
  &selected-tab=general_settings_subtab_general    ← main tab
  &selected-sub-tab=general_settings_subtab_alerts ← sub-tab
```

Build these with `Helpers::get_settings_page_sub_tab_url('slug')` — never manually.

## Common Tab Slugs

Look up tab slugs in the module or service that registers them:

-   Alerts (free teaser): `Alerts_Settings_Page_Teaser::MENU_SLUG` (`general_settings_subtab_alerts`)
-   Alerts (premium): `Alerts_Module::ALERTS_TAB_SLUG` (same value, premium add-on only)
-   General settings: `Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG`
-   Check module/service classes for their `*_TAB_SLUG` or `MENU_SLUG` constants

## Anti-Patterns

```php
// WRONG: Hardcoded URL breaks when menu location changes.
$url = admin_url('admin.php?page=simple_history_settings_page&selected-sub-tab=alerts');

// WRONG: Building URL manually in JavaScript.
const url = `${adminUrl}admin.php?page=simple_history_settings_page...`;

// RIGHT: Use helper in PHP, pass to JS if needed.
$url = Helpers::get_settings_page_sub_tab_url( Alerts_Settings_Page_Teaser::MENU_SLUG );
```

## Passing URLs to JavaScript

When JS components need admin URLs, generate them in PHP and pass them via:

1. **`wp_localize_script`** — for URLs needed immediately on page load
2. **`simple_history/search_options_data` filter** — for URLs consumed by React components via the search-options REST API

See the `php-to-react-data` skill for details on choosing between these approaches.
