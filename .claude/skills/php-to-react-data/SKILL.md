---
name: php-to-react-data
description: Patterns for passing data from PHP to React via wp_localize_script and REST API. Use when sharing config or data between PHP and React components.
allowed-tools: Read, Grep, Glob
---

# Passing Data from PHP to React

Simple History uses two main approaches to pass server-side data to React components. Choose based on when and where the data is needed.

## Approach 1: `wp_localize_script` (Global Variable)

Data is available immediately on page load as `window.variableName`.

### When to Use

-   Data needed **before** any API calls complete (e.g. URLs for initial render)
-   Static config that doesn't change during the session
-   Data needed by components **outside** the React tree (e.g. FilteredComponent HOCs)
-   Simple values like URLs, feature flags, nonces

### Pattern

**PHP** (in the module that enqueues scripts):

```php
wp_localize_script(
    'simple_history_pro_scripts',  // Script handle.
    'simpleHistoryPremium',        // JS global variable name.
    [
        'alertsPageUrl' => Helpers::get_settings_page_sub_tab_url( Alerts_Module::ALERTS_TAB_SLUG ),
    ]
);
```

**JavaScript:**

```js
const url = window.simpleHistoryPremium?.alertsPageUrl ?? '';
```

### Existing Globals

| Variable                      | Script                                             | Contains                                                                                            |
| ----------------------------- | -------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `simpleHistoryScriptVars`     | `simple_history_script`                            | Confirmation strings                                                                                |
| `simpleHistoryCommandPalette` | `simple-history-command-palette`                   | `historyPageUrl`                                                                                    |
| `simpleHistoryAdminBar`       | `simple_history_admin_bar_scripts`                 | `adminPageUrl`, `viewSettingsUrl`, `currentUserCanViewHistory`, `currentPostId`, `currentPostTitle` |
| `simpleHistoryPremium`        | `simple_history_pro_scripts` (premium add-on only) | `alertsPageUrl` and other premium-specific config                                                   |

### Naming Convention

-   Global variable: `simpleHistory` + context (camelCase), e.g. `simpleHistoryPremium`
-   Keys: camelCase, e.g. `alertsPageUrl`

## Approach 2: Search Options REST API

Data flows through the React component tree via context: API response -> `EventsSearchFilters` -> state setters -> `EventsSettingsContext` -> `useEventsSettings()`.

### When to Use

-   Data consumed by React components **inside** the `EventsSettingsProvider`
-   Data that may change or is derived from user state
-   Complex structured data (arrays, nested objects)
-   Data that should be available to all React components via context

### Pattern

**PHP** — hook into the filter in `class-wp-rest-searchoptions-controller.php`:

```php
add_filter( 'simple_history/search_options_data', [ $this, 'add_my_data' ] );

public function add_my_data( $data ) {
    $data['my_feature_url'] = Helpers::get_settings_page_sub_tab_url( 'my_tab' );
    return $data;
}
```

**JavaScript** — plumb through the data pipeline:

1. `EventsGui.jsx`: Add state: `const [myUrl, setMyUrl] = useState();`
2. `EventsGui.jsx`: Add to `eventsSettingsValue` useMemo and pass `setMyUrl` to `EventsSearchFilters`
3. `EventsSearchFilters.jsx`: Extract from response: `setMyUrl(searchOptionsResponse.my_feature_url)`
4. Components: `const { myUrl } = useEventsSettings();`

### Already Available via Context

Key values from `useEventsSettings()`:

-   `mapsApiKey`
-   `hasExtendedSettingsAddOn`, `hasPremiumAddOn`
-   `hasFailedLoginLimit`
-   `eventsSettingsPageURL`, `eventsAdminPageURL`
-   `alertsPageURL` (populated by premium add-on only, `undefined` without it)
-   `userCanManageOptions`

## Decision Guide

| Criteria                            | `wp_localize_script` | Search Options API     |
| ----------------------------------- | -------------------- | ---------------------- |
| Available at first render           | Yes                  | No (async)             |
| Works in FilteredComponent HOCs     | Yes                  | No (outside provider)  |
| Works in Fill/Slot children         | Yes                  | Maybe (context issues) |
| Works inside EventsSettingsProvider | Yes                  | Yes                    |
| Extensible by other add-ons         | No                   | Yes (via filter)       |
| Avoids global variables             | No                   | Yes                    |

### Rule of Thumb

-   **URLs and simple config** -> `wp_localize_script` (immediate, no async issues)
-   **Feature data consumed by many components** -> search-options API + context
-   **Premium-only data** -> `wp_localize_script` on the premium script handle
-   **When in doubt** -> `wp_localize_script` is simpler and more reliable

## Anti-Patterns

```js
// WRONG: Building admin URLs in JavaScript.
const url = window.ajaxurl.replace('admin-ajax.php', '') + 'admin.php?page=...';

// WRONG: Fetching search-options manually when context is available.
apiFetch({ path: '/simple-history/v1/search-options' }).then(...);

// WRONG: Using wp_localize_script for large datasets.
// Use a REST endpoint instead.

// RIGHT: Use wp_localize_script for URLs, use context for feature data.
const url = window.simpleHistoryPremium?.alertsPageUrl ?? '';
const { hasPremiumAddOn } = useEventsSettings();
```
