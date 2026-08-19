# Network Logging → Premium Migration Plan

**Source branch (core):** `issue-multisite-network-logging` — contains all work to be migrated and reverted
**Target repo (premium):** `/Users/bonny/Projects/Personal/simple-history-add-ons/simple-history-premium`
**Core behavior after migration:** same as today's shipped plugin — network admin events silently land in site 1's log. No regression. Plus a new teaser page for multisite super admins.
**Freemium stance:** additive upgrade, not gate. No locked code ships in the wp.org ZIP.

## Current status (2026-04-15)

Both sides implemented. Ready for manual integration testing.

### Core branch `issue-network-teaser` (6 commits on top of develop)

```
03521b8d Add Network Admin teaser page on multisite
b454cc1b Add factory methods to REST controllers
84da0bb5 Centralize Event table name resolution
4eb6f804 Centralize Log_Query table name resolution
c6d2069c Add filter to opt external pages into Simple History asset pipeline
d53c5209 Expose Events_Stats table getters to subclasses
```

### Premium branch `network-module` (9 commits on top of main)

```
a5627fa Match core's get_events_table_name() / get_contexts_table_name() method names
90e7f7a Register Network_Module in Extended_Settings
4a5366b Add network CSS and JS helpers
1245517 Add Network Admin page and sidebar dropin
0b07698 Add network-scoped REST controllers
5c68341 Add network-aware Log_Query / Event / Events_Stats subclasses
b5fe558 Port Network_Logger for multisite event capture
863563f Add network database setup
3e7aeda Scaffold network module skeleton
```

### Coupling

Premium `network-module` requires core `issue-network-teaser` to function — premium relies on `protected` table-name accessors on `Log_Query` / `Event` / `Events_Stats`, the `create_log_query()` / `create_event()` / `create_events_stats()` factory methods on REST controllers, and the `simple_history/is_on_our_own_pages` filter. Ship core first, then premium.

### Known limitation (not blocking ship)

`WP_REST_User_Card_Controller` has its `Log_Query` instantiations inside `public static` helper methods. Those static helpers can't be overridden via instance factory methods. Result: on the Premium network page, user card activity counts will show site-level activity rather than network-level. Fix in a follow-up — either introduce late-static-binding `static::create_log_query()` or migrate those helpers off statics.

### readme.txt audit

Done. Two historical changelog entries mention "multisite" and "network" but both describe the plugin's own multisite install/uninstall behavior, not a claim that network admin events are logged. No changes needed.

## Guiding principles

1. **No regression in core.** Core continues to behave exactly like the shipped version for non-premium users — network events "leak" into site 1's log as they do today.
2. **No locked code in core.** Everything network-specific leaves core entirely.
3. **Clean extensibility, no special casing.** If premium needs a hook in core, it's a legitimate hook useful beyond network — not a "premium backdoor."
4. **Full taxonomy carried forward.** All 25 Network_Logger events with their tuned active-voice messages, context keys, and memoization move as-is.
5. **Branch preserved.** Don't delete `reference-network-premium-port-source` until premium ships. Use it as the source for cherry-picked file copies and history reference.

## Branching strategy

1. Keep `reference-network-premium-port-source` (formerly `issue-multisite-network-logging`) as the reference branch.
   Pushing it is fine — it is a backup, and the work exists nowhere else in this form.
   **It must never be merged into `develop`.** Its whole point is that this code left core; merging
   it back would reintroduce the network implementation into the free plugin and undo the split.
2. Create **`issue-network-teaser`** from `develop` for core changes (teaser page + readme audit + possibly small hook additions).
3. In the premium repo, create a branch `network-module` (or whatever naming convention premium uses) for the new module.

At the end: merge `issue-network-teaser` → `develop` → `main` on core. Release premium with network module. The original `issue-multisite-network-logging` branch gets archived/deleted once everything is shipped.

---

## Phase 1 — Premium: build the network module

Premium already uses a module pattern (`inc/modules/class-*-module.php`, namespace `Simple_History\AddOns\Pro\Modules`). Network logging becomes one module that bootstraps everything.

### 1.1 New module: `class-network-module.php`

Location: `inc/modules/class-network-module.php`
Namespace: `Simple_History\AddOns\Pro\Modules`

Responsibilities:

-   Gate on `is_multisite()` — no-op on single site.
-   Register the Network_Logger via `simple_history/add_custom_logger`.
-   Register network REST controllers on `rest_api_init` (after core's).
-   Register the Network_Admin_Page service on `network_admin_menu`.
-   Create network tables on plugin activation and version bumps.
-   Localize `simpleHistoryNetworkContext` to the React bundle on our page.
-   Enqueue network-specific CSS.
-   Register the sidebar dropin for the network page.

Hook into premium's main `Extended_Settings` init path so the module loads automatically when premium boots.

### 1.2 Files to copy from core branch → premium

| Core branch path                                                                                                                           | Premium destination                                                            | Notes                                                                                                                                                                                                                                                                                             |
| ------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `loggers/class-network-logger.php`                                                                                                         | `inc/modules/network/class-network-logger.php`                                 | Keep namespace logic — may extend core's `Simple_History\Loggers\Logger`. Override `$db_table` / `$db_table_contexts` in `loaded()` to premium's network tables. Drop the `$is_network_logger` flag and `force_network_tables()` helper (no longer needed — tables are explicit on the subclass). |
| `inc/class-wp-rest-network-events-controller.php`                                                                                          | `inc/modules/network/class-wp-rest-network-events-controller.php`              | Extends core's `WP_REST_Events_Controller`. Factory methods (`create_log_query`, `create_event`) stay.                                                                                                                                                                                            |
| `inc/class-wp-rest-network-stats-controller.php`                                                                                           | `inc/modules/network/class-wp-rest-network-stats-controller.php`               | Extends core's stats controller.                                                                                                                                                                                                                                                                  |
| `inc/class-wp-rest-network-searchoptions-controller.php`                                                                                   | `inc/modules/network/class-wp-rest-network-searchoptions-controller.php`       |                                                                                                                                                                                                                                                                                                   |
| `inc/class-wp-rest-network-user-card-controller.php`                                                                                       | `inc/modules/network/class-wp-rest-network-user-card-controller.php`           |                                                                                                                                                                                                                                                                                                   |
| `inc/services/class-network-admin-page.php`                                                                                                | `inc/modules/network/class-network-admin-page.php`                             | Page renders NETWORK badge + subtitle + React mount.                                                                                                                                                                                                                                              |
| `dropins/class-network-sidebar-dropin.php`                                                                                                 | `inc/modules/network/class-network-sidebar-dropin.php`                         | Multisite upgrade card → becomes a "premium active" info/site-count card instead.                                                                                                                                                                                                                 |
| CSS for `.sh-PageHeader-badge--network`, `.sh-PageHeader-titleGroup--stacked`, `.sh-PageHeader-subtitle`                                   | Premium's CSS build pipeline                                                   | Enqueued only on the network page.                                                                                                                                                                                                                                                                |
| JS helpers: `getEventsApiPath()`, `getSearchOptionsApiPath()`, `getUserCardApiBase()`, `getInitiatorCardApiBase()` (in `src/functions.js`) | Premium's React loader or a premium JS file enqueued alongside the main bundle | These read `window.simpleHistoryNetworkContext` and route API calls. Core's bundle must not reference them.                                                                                                                                                                                       |

### 1.3 Premium needs from core (minimal extensibility)

Audit whether premium can do its job using what core already exposes, or if a small extensibility hook needs adding to core. Decide these case-by-case during implementation:

-   **Table override on Logger subclass.** Premium's `Network_Logger` will set its own `$db_table` and `$db_table_contexts`. Verify core's `Logger` supports this without special casing. If core always overwrites these on `loaded()`, expose a `get_db_table()` / `get_db_table_contexts()` method that subclasses can override. (This is a clean hook, useful beyond network.)
-   **`Log_Query` table injection.** Premium's REST controllers need `Log_Query` to read from network tables. Either: (a) `Log_Query` gains a constructor arg / fluent setter for custom table names (clean, minimal), or (b) premium filters `simple_history/get_events_sql_where` and related hooks. Prefer (a) — less fragile.
-   **`Event::get()` table override.** Same as Log_Query — needs a way to hydrate an Event from a non-default table. Minimal API surface.
-   **`Events_Stats` table override.** Same pattern.
-   **`is_on_our_own_pages()`.** Must return true on the network admin page so core enqueues its React bundle. Add a filter `simple_history/is_on_our_own_pages` that premium can hook.
-   **REST controller inheritance.** Confirm core's `WP_REST_Events_Controller` / `WP_REST_Stats_Controller` / `WP_REST_SearchOptions_Controller` / `WP_REST_User_Card_Controller` expose their factory methods (`create_log_query`, `create_event`, `create_events_stats`, etc.) as `protected` so premium subclasses can override. Keep these.

**Rule:** every core-side hook added must be defensible as generic extensibility, not "for premium." If a hook smells like a premium backdoor, find another way.

### 1.4 Premium database setup

On premium activation (or on version bump), create:

-   `{base_prefix}simple_history_network`
-   `{base_prefix}simple_history_network_contexts`

Both with `utf8mb4` charset. Track version in `simple_history_premium_network_db_version` site option. On uninstall (premium), drop these tables.

### 1.5 Premium readme.txt + changelog

Add a prominent "Network event logging" section to premium's readme. Add a changelog entry. Bump premium version.

### 1.6 Teaser coordination

Core's teaser page must disappear when premium is active. Two options:

-   **Option A (recommended):** Core registers the teaser at `network_admin_menu` priority 10. Premium registers its real page at priority 10 with the same slug — WordPress overwrites the first registration, so the premium page wins. No special check needed.
-   **Option B:** Core's teaser checks `class_exists('\Simple_History\AddOns\Pro\Modules\Network_Module')` (or similar) and returns early if true.

Prefer A if it works cleanly — it's idiomatic. Confirm behavior.

---

## Phase 2 — Core: revert + teaser page

Starting from `develop`, create branch `issue-network-teaser`.

### 2.1 Revert all network infrastructure

Revert these files to their state on `develop`:

-   `loggers/class-logger.php` — remove `$is_network_logger`, `force_network_tables()`, `should_use_network_tables()`, try/finally table swap in `log()`
-   `inc/class-simple-history.php` — remove `DBTABLE_NETWORK`, `DBTABLE_NETWORK_CONTEXTS`, `get_network_events_table_name()`, `get_network_contexts_table_name()`, Network_Admin_Page + Network_Logger registrations
-   `inc/services/class-setup-database.php` — remove `setup_network_tables()` and related version tracking
-   `inc/class-log-query.php` — remove `set_network_query()`, `is_network_query`, `get_events_table()`, `get_contexts_table()` helpers (unless we decide to keep them as extensibility per 1.3)
-   `inc/class-event.php` — remove `$is_network` constructor arg and all downstream wiring (unless kept per 1.3)
-   `inc/class-wp-rest-events-controller.php` — remove factory methods `create_log_query()`, `create_event()`, `should_force_network_logging()` — OR keep them `protected` for premium to subclass (per 1.3, likely keep)
-   `inc/class-events-stats.php` — remove `$is_network` constructor arg (unless kept per 1.3)
-   `inc/class-helpers.php` — remove `get_network_admin_page_url()`, revert `is_on_our_own_pages()` (but add the new filter per 1.3), revert the `$is_network` additions to `get_data_for_date_filter()`, `get_unique_events_for_days()`, `get_num_events_last_n_days()`, `get_num_events_today()`
-   `inc/services/class-rest-api.php` — remove the four `is_multisite()`-gated network controller registrations
-   `dropins/class-quick-view-dropin.php` — revert the `simpleHistoryNetworkContext` localization on admin bar
-   `src/functions.js` — remove `getEventsApiPath()` / `getSearchOptionsApiPath()` / `getUserCardApiBase()` / `getInitiatorCardApiBase()`. Restore all `src/components/*.jsx` callers to hardcoded `/simple-history/v1/events`, `/search-options`, `/users/{id}/card`, `/initiators/{type}/card` paths.
-   `css/styles.css` — remove `.sh-PageHeader-badge--network`, `.sh-PageHeader-titleGroup--stacked`, `.sh-PageHeader-subtitle`
-   Delete `inc/services/class-network-admin-page.php`
-   Delete `dropins/class-network-sidebar-dropin.php`
-   Delete `loggers/class-network-logger.php`
-   Delete the four `inc/class-wp-rest-network-*.php` files

### 2.2 Add the teaser page

New file: `inc/services/class-network-teaser-page.php`.

Responsibilities:

-   Only load on `is_multisite()`.
-   Register at `network_admin_menu`, slug `simple_history_network_page` (same as premium's — so premium can override by registering at same slug; confirm Phase 1.6 Option A first).
-   Capability: `manage_network`.
-   Menu icon: reuse `Admin_Pages::MENU_ICON`.
-   Renders a static PHP page — no React, no JS dependencies.

Register the service in `inc/class-simple-history.php` alongside other services (multisite-gated).

### 2.3 Teaser page content

Use the UX agent's draft copy as a starting point. Final rendering (PHP template):

```
<div class="wrap SimpleHistoryWrap">
    <header class="sh-PageHeader">
        <div class="sh-PageHeader-titleGroup">
            <h1 class="sh-PageHeader-title">
                <img ... class="sh-PageHeader-logo" alt="Simple History" />
            </h1>
        </div>
    </header>

    <div class="sh-NetworkTeaser">
        <h2>Network Event Log</h2>
        <p class="sh-NetworkTeaser-lede">
            Track what happens across all sites in your network — from one place.
        </p>

        <p>
            Simple History's free version logs activity on each site individually.
            Network-wide logging is a Premium feature.
        </p>

        <p>
            Super admins managing multiple sites often need to know: who changed a
            setting on the marketing site? Which editor deleted content on the
            client portal? The Network Event Log gives you a single stream of
            activity across your entire WordPress network.
        </p>

        <h3>What Simple History Premium adds for multisite</h3>

        <ul class="sh-NetworkTeaser-features">
            <li>Network admin event log — site creation and deletion, super admin grants and revokes, plugin network activations, network settings changes, and more (25+ event types)</li>
            <li>Unified activity feed across all sites in your network</li>
            <li>Filter by site, user, event type, or date</li>
            <li>WP-CLI support: <code>wp simple-history list --network</code> for scripted audits</li>
            <li>Full event details for every action — before/after diffs, affected sites, user metadata</li>
        </ul>

        <div class="sh-NetworkTeaser-cta">
            <a href="https://simple-history.com/premium/?utm_source=wpadmin&utm_medium=network-teaser&utm_campaign=network-log"
               class="button button-primary button-hero">
                Upgrade to Simple History Premium — $79/year
            </a>
            <p class="sh-NetworkTeaser-licenseLink">
                Already have Premium?
                <a href="<?php echo admin_url(...license settings url...) ?>">Activate your license</a>
            </p>
        </div>

        <p class="sh-NetworkTeaser-footer">
            Need more information? <a href="https://simple-history.com/premium/">Learn more about Simple History Premium</a>.
        </p>
    </div>
</div>
```

### 2.4 Teaser page styling

Add minimal CSS directly to `css/styles.css`:

-   `.sh-NetworkTeaser` — max-width 600px, centered, generous padding
-   `.sh-NetworkTeaser-lede` — larger font, slightly muted
-   `.sh-NetworkTeaser-features` — list styling, good line-height
-   `.sh-NetworkTeaser-cta` — centered, prominent
-   `.sh-NetworkTeaser-licenseLink` — muted, smaller
-   `.sh-NetworkTeaser-footer` — small, top border separator

Do not use modal styling or notice-style backgrounds. Page chrome should match other admin pages.

### 2.5 readme.txt audit

Grep `readme.txt` and the wp.org plugin description for any of: "multisite", "network", "super admin", "blog". Remove or rephrase anything that implies network-log support in core. This closes the expectation-gap risk the UX agent flagged.

If core already honestly says "doesn't log network admin events" anywhere, that's fine — leave it.

### 2.6 Cleanup

-   No `uninstall.php` network-table drop needed (core never creates them anymore).
-   No changes to `index.php`.
-   Verify `Logger::loaded()` no longer has network branches.
-   Run phpstan + phpcs.
-   Manual test on `wordpress-multisite.test:8289`:
    -   Core plugin only: network admin shows "Simple History" menu → teaser page renders correctly.
    -   Core + premium: network admin shows the real network log page (premium overrode the menu slug).
    -   Site-level admin: no visible regression.

---

## Phase 3 — Execution order

Build premium first, with core still on the feature branch, so both sides can be tested end-to-end before reverting core:

1. **Premium module built on `network-module` branch.** Validates that the Logger subclass, REST controller subclasses, and Log_Query/Event extensibility all work through the core hooks we're keeping.
2. **Identify any core-side hooks actually needed** (Phase 1.3 audit). Commit them to `develop` as small, standalone commits with clear generic-extensibility names.
3. **Start `issue-network-teaser` branch from updated develop.** Apply Phase 2 reverts. Add teaser.
4. **Dual-plugin integration test** with core on `issue-network-teaser` + premium with network module. Confirm teaser disappears when premium is active, and network log works end-to-end.
5. **Merge teaser branch to develop → main.** Ship new core release.
6. **Ship premium release** with network module.

## Phase 4 — Post-migration

-   Archive `issue-multisite-network-logging` branch (don't delete until confident premium works in production).
-   Update this project's `readme.issue-multisite-network-logging.md` to a "shipped as premium" note pointing to the premium module.
-   Add a simple-history.com blog post announcing multisite network logging as a premium feature.

---

## Checklist

### Premium side

-   [ ] Create `class-network-module.php` module skeleton
-   [ ] Port `Network_Logger` with full taxonomy
-   [ ] Port 4 REST controllers (events, stats, search options, user cards)
-   [ ] Port `Network_Admin_Page` service
-   [ ] Port `Network_Sidebar_Dropin` (rework for premium-active case)
-   [ ] Port CSS (badge, subtitle, stacked title)
-   [ ] Port React API path helpers + localize `simpleHistoryNetworkContext`
-   [ ] Database setup on activation + version tracking
-   [ ] Wire module into `Extended_Settings` init
-   [ ] Premium readme.txt entry + changelog
-   [ ] Version bump

### Core side

-   [ ] Audit and add any necessary generic extensibility hooks (Logger table override, Log_Query table injection, Event table injection, Events_Stats table injection, `is_on_our_own_pages` filter)
-   [ ] Revert all network-specific code
-   [ ] Add `Network_Teaser_Page` service (multisite only)
-   [ ] Teaser CSS
-   [ ] readme.txt audit for stray network/multisite claims
-   [ ] phpstan + phpcs green
-   [ ] Manual multisite test: core-only and core+premium

### Integration

-   [ ] End-to-end test: teaser shows without premium, real page shows with premium
-   [ ] Confirm no regression on single-site installs
-   [ ] Confirm no regression on existing premium features
-   [ ] Confirm site-1 leak behavior for network events is unchanged in core

## Risks and mitigations

| Risk                                                          | Mitigation                                                                                                        |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------- |
| Core Logger doesn't cleanly allow subclass table override     | Add minimal `get_db_table()` method in Phase 1.3 audit. Small, generic.                                           |
| Log_Query/Event extensibility bleeds into too many core files | Keep it to one constructor arg per class. Document as extensibility in a short PHPDoc.                            |
| Menu slug collision doesn't override as expected              | Fall back to Phase 1.6 Option B (explicit class_exists check in teaser).                                          |
| readme.txt already claims multisite support                   | Fix it in Phase 2.5. Commit separately so it's auditable.                                                         |
| Site-1 leak behavior breaks during revert                     | Run core test suite post-revert. Manual test by triggering `wpmu_new_user` while on a sub-site.                   |
| Customer expects network log after paying, doesn't see it     | Premium activation should surface the network page prominently first time — existing premium onboarding patterns. |

## Out of scope (for later)

-   Multisite pricing tier ($149-199). Ship feature first, revisit pricing after 3 months of data.
-   All Sites aggregated feed (deferred to next premium milestone).
-   `_ext_` rich context / Event Details reveal (deferred).
-   Network dashboard widget.
-   Network event export.
-   Network-specific alerts.
-   Weekly network summary email.
