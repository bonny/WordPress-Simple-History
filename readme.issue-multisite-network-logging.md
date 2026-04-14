# Multisite Network Event Logging

**Issue:** Multisite information for super admin
**Branch:** issue-multisite-network-logging
**Status:** In progress
**Priority:** 1-high

## Goal

Log network-level actions that happen in the Network Admin — site created/deleted, plugin network-activated, super admin granted, network settings changed. These events are **completely unlogged today** and land silently in site 1's log (broken behavior).

This is step 1 of 3 in the multisite premium strategy:

1. **Network event logging** (this issue, core/free) — foundation
2. Aggregated cross-site event view (premium) — killer upgrade feature
3. Network-wide settings (premium) — convenience for large networks

## Architecture Findings

### Current state

-   All tables use `$wpdb->prefix` (site-level) — **no `base_prefix` usage anywhere**
-   Table names come from `Simple_History::get_events_table_name()` with filter hooks
-   `Logger::log()` inserts to `$this->db_table` which is set from the Simple_History instance
-   Context stored as separate rows in the contexts table (key-value pairs)
-   **Zero network admin page infrastructure** — no `network_admin_menu` hook, no network REST endpoints
-   Only network code: `Network_Menu_Items` service (admin bar links) and Plugin Logger receiving `$network_wide` param (but ignoring it)

### The broken behavior

Network admin actions (plugin updates from `/wp-admin/network/`, etc.) land in site 1's `wp_simple_history` table because `$wpdb->prefix` in the network admin context equals the main site's prefix (`wp_`). Simple History captures this at init and never re-evaluates.

### What needs a NEW logger vs what needs RE-ROUTING

**New Network_Logger needed (no existing coverage):**

| Category         | Events                                                                                        | Hooks                                                                                                                                                                                                   |
| ---------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Site lifecycle   | Create, delete                                                                                | `wp_initialize_site`, `wp_delete_site`                                                                                                                                                                  |
| Site status      | Spam/unspam, archive/unarchive, mature/unmature, deactivate/activate, delete/undelete, public | `make_spam_blog`, `make_ham_blog`, `archive_blog`, `unarchive_blog`, `mature_blog`, `unmature_blog`, `deactivate_blog`, `activate_blog`, `make_delete_blog`, `make_undelete_blog`, `update_blog_public` |
| Super admin      | Grant, revoke                                                                                 | `granted_super_admin`, `revoked_super_admin`                                                                                                                                                            |
| Network users    | Create, delete, spam/unspam                                                                   | `wpmu_new_user`, `wpmu_delete_user`, `make_spam_user`, `make_ham_user`                                                                                                                                  |
| User-site mgmt   | Add to site, remove from site                                                                 | `add_user_to_blog`, `remove_user_from_blog`                                                                                                                                                             |
| Signups          | User signup, site signup, user activation, site activation                                    | `after_signup_user`, `after_signup_site`, `wpmu_activate_user`, `wpmu_activate_blog`                                                                                                                    |
| Themes           | Network enable/disable                                                                        | `update_site_option_allowedthemes`                                                                                                                                                                      |
| Network settings | Registration policy, banned domains, upload types, etc.                                       | `update_site_option_{$option}` or generic `update_site_option`                                                                                                                                          |

**Already logged by existing loggers (need re-routing + enhanced messages):**

| Logger             | Events                                   | What's needed                                                                                         |
| ------------------ | ---------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Plugin Logger      | Network activate/deactivate              | Route to network table when `$network_wide=true`, add distinct message key `plugin_network_activated` |
| Plugin Logger      | Install/update/delete from network admin | Route to network table when `is_network_admin()`                                                      |
| Theme Logger       | Install/update/delete from network admin | Route to network table when `is_network_admin()`                                                      |
| Core Update Logger | Core update from network admin           | Route to network table when `is_network_admin()`                                                      |
| File Editor        | Plugin/theme file edit                   | Route based on context                                                                                |

### Database design

Two new tables using `$wpdb->base_prefix` (once per network, not per-site):

-   `{base_prefix}simple_history_network` — same schema as existing events table
-   `{base_prefix}simple_history_network_contexts` — same schema as existing contexts table

### Network Admin GUI

-   New `Network_Admin_Page` service — registers menu page via `network_admin_menu` hook
-   Reuse the same React `EventsGui` component, pointed at a network-specific REST endpoint
-   New REST controller (or mode on existing controller) that queries `base_prefix` tables
-   Only visible to super admins (`manage_network` capability)
-   Network Admin dashboard widget can come later (not MVP)

### Routing approach in base Logger

Modify `Logger::log()` to detect network context and insert into the correct table:

-   If `is_network_admin()` is true → use `base_prefix` tables
-   Existing loggers get this for free without code changes
-   The new Network_Logger always writes to `base_prefix` tables regardless of context

## Implementation plan

### Phase A: Database foundation

-   [ ] Extend `Setup_Database` service to create `base_prefix` tables on multisite
-   [ ] Add `get_network_events_table_name()` / `get_network_contexts_table_name()` to `Simple_History`
-   [ ] Handle db versioning for network tables separately from site tables

### Phase B: Logging infrastructure

-   [ ] Modify `Logger::log()` to route to network tables when in network admin context
-   [ ] Add network table references to Logger base class
-   [ ] Ensure context storage (`append_context`) works with network tables

### Phase C: Network Logger

-   [ ] Create `Network_Logger` class for ~25 new events (site lifecycle, super admin, network users, settings, themes)
-   [ ] Use active voice message format per project conventions
-   [ ] Store rich context for premium "reveal" later

### Phase D: Enhanced existing loggers

-   [ ] Plugin Logger: add `$network_wide` context, distinct message keys
-   [ ] Theme Logger: detect network admin context
-   [ ] Minimal changes — routing handled by Phase B

### Phase E: Network Admin GUI

-   [ ] New `Network_Admin_Page` service (menu registration, script enqueuing)
-   [ ] New REST endpoint for network events (or network mode on existing controller)
-   [ ] Extend `Log_Query` to support network tables
-   [ ] React app reuse — same `EventsGui`, different data source

### Phase F: Cleanup

-   [ ] Update `uninstall.php` to drop network tables
-   [ ] Handle `index.php` activation TODO
-   [ ] Test on multisite installation

## Freemium Split

### Core (free) — this issue

All ~25 events logged with clear, complete messages. No locked rows, no hidden events. Free users get a fully useful network activity log.

**What free users see (example messages):**

-   "Created site clientsite.example.com"
-   "Granted super admin to jane@example.com"
-   "Network activated plugin WooCommerce"
-   "Changed registration policy from 'None' to 'User accounts may be registered'"
-   "Marked site clientsite.example.com as spam"

**What core also does silently:**

-   Stores rich context with `_ext_` prefix keys on every event (site details, old/new values, affected sites list, user metadata)
-   This data is invisible to free users but available the moment they upgrade

### Page layout: Segmented control

The Network Admin page uses a **segmented control** at the top:

```
[ Network-wide actions ]  [ All sites ]
```

-   **"Network-wide actions"** (free) — shows the ~25 network-level events. Fully functional, default view.
-   **"All sites"** (premium) — shows aggregated events from ALL individual sites in one feed. Clicking this tab when not premium shows the upgrade paywall inline. This is stronger than a passive notice — the user self-selects with intent already activated.

Use "Network-wide actions" (not "Network events") as the label — clearer for admins who don't think in WordPress internals.

Add a **one-line explainer on first visit**: "These are actions that affect your entire network, not individual sites."

### Premium — depth, convenience, scale

**1. Aggregated cross-site feed (the main conversion driver)**

The "All sites" tab shows events from every site in the network in one feed. This is what multisite admins have asked for since 2018. The tab exists in core but the content requires premium. Clicking the tab without premium shows an informational upgrade page (benefits, screenshots, CTA link) — not locked functionality.

**2. Rich context / Event Details (Store in Core, Reveal in Premium pattern)**

| Event                    | Free message                    | Premium adds (via Event Details API)                          |
| ------------------------ | ------------------------------- | ------------------------------------------------------------- |
| Site created             | "Created site example.com"      | Admin email, template site, installed plugins, theme, site ID |
| Super admin granted      | "Granted super admin to jane"   | Previous roles, number of sites, last login, all capabilities |
| Network settings changed | "Changed registration policy"   | Full before/after diff table for every changed field          |
| Plugin network activated | "Network activated WooCommerce" | List of all N sites affected, which already had it active     |
| User added to site       | "Added user to site"            | Role assigned, other sites user belongs to                    |
| Site marked spam         | "Marked site as spam"           | Site stats (posts, users, last activity), reason context      |

Core stores the extended context silently (`_ext_` keys) but does **not** render, blur, or hint at it. Premium adds the Event Details renderers. The free plugin never shows users that hidden data exists — it simply doesn't render it.

On events that have extended context available (when premium is active), premium renders the full Event Details. When premium is not active, a small **non-blurred premium badge** appears below the event: "Premium: see full site details" as a text link. No blur, no locked data theatrics.

**3. Network-specific premium features (deferred to later):**

-   Site-selector filter — filter events by affected site
-   Network dashboard widget with insights ("3 super admin changes this week")
-   Network event export (CSV/JSON)
-   Network-specific alerts ("Alert me when super admin is granted") — ties into existing Alerts feature
-   Weekly network summary email — free gets bare counts, premium gets actionable detail with direct links

### Upsell surfaces (2 total, WordPress.org compliant)

1. **Segmented control** — "All sites" tab shows informational upgrade page on click (intent-driven, user-initiated)
2. **Premium badge on events** — small non-blurred text link below events with extended context: "Premium: see full site details" linking to upgrade page

**What was removed after WordPress.org compliance review:**

-   ~~Grayed inline events~~ — **Removed.** Injecting locked/fake rows into a real event feed violates Guideline 5 (Trialware). Whether real data refused without payment or synthetic rows — both cross the line. Would also generate 1-star reviews (15-25% of feed being ads).
-   ~~Blurred context teasers~~ — **Removed.** Storing data and visually hiding it behind a CSS blur is locked code / trialware. The blur itself is the problem.
-   ~~Growing counter ("N events with hidden details")~~ — **Removed.** Explicitly telling users data is stored but withheld frames the free plugin as incomplete. Users could legitimately complain to WordPress.org.

**Compliant alternative for cross-site visibility:** A **single static card** inserted after the 5th or 10th event row: "Your network has N sites. See all their activity in one feed." One card, not injected fake rows. Dismissible, returns after 30 days.

### Empty state design

Quiet networks may have days with no network events. Without a good empty state, the page feels broken.

-   "No network-wide actions in the last 30 days — your network is stable."
-   On first install: "Network logging is active. Events are being recorded. Check back soon — or see activity across all N sites with Premium."

### Why this split works

-   Free is genuinely useful — removes the multisite adoption blocker
-   The segmented control makes premium visible without being pushy — user discovers it naturally
-   No locked data, no blurred content, no fake rows — fully WordPress.org compliant
-   Premium adds depth (Event Details) and breadth (all-sites feed), not access restrictions
-   The aggregated view is no longer a separate "step 2" — it's a premium mode on the same page
-   Only 2 upsell surfaces — well under the threshold for user annoyance

### Pricing consideration (deferred)

UX review flagged that $79/year may be underpriced for multisite (a 50-site admin gets 50x the value). Consider a multisite tier ($149-199/year) later. Ship the feature first, evaluate pricing after seeing adoption.

## Open questions

-   Should we add a network admin bar menu item linking to the network log?
-   How to handle events that span both contexts (e.g. plugin network-activated affects all sites)?
