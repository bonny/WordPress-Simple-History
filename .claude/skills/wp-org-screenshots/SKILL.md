---
name: wp-org-screenshots
description: How to design and regenerate marketing screenshots for the wordpress.org plugin page (banner-1544x500.png, screenshot-1.png, etc). Covers the event mix that converts, the reproducible WordPress Playground pipeline that bakes it, and every non-obvious gotcha from previous shoots. Use when refreshing screenshot-1.png, banners, or any image showing the Simple History event log.
allowed-tools: Read, Edit, Write, Bash
disable-model-invocation: true
---

# wordpress.org Marketing Screenshots

When the wordpress.org plugin page banner, screenshots, or any marketing image shows a mocked-up Simple History event log, the events you pick are the single biggest conversion lever. The events tell a story that — within 3 seconds — has to answer "does this plugin solve a problem I have?"

This skill captures **(a)** the event mix that does that and **(b)** the reproducible Playground pipeline that produces the screenshot.

## Where it's used

| Asset             | Path                                  | Dimensions                                        |
| ----------------- | ------------------------------------- | ------------------------------------------------- |
| Banner large      | `.wordpress-org/banner-1544x500.png`  | 1544×500                                          |
| Banner small      | `.wordpress-org/banner-772x250.png`   | 772×250                                           |
| Main screenshot   | `.wordpress-org/screenshot-1.png`     | 3200×2260 (retina, 1600×1130 @ 2×)                |
| Dashboard widget  | `.wordpress-org/screenshot-10.png`    | 2880×1800 (retina, 1440×900 @ 2×) — classic 16:10 |
| Other screenshots | `.wordpress-org/screenshot-{2-9}.png` | varies                                            |

All sync to wordpress.org via `.github/workflows/deploy.yml` (10up/action-wordpress-plugin-deploy) on next tag push.

Sizes are fixed by wordpress.org — see [developer.wordpress.org/plugins/wordpress-org/plugin-assets](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/). Key constraints:

-   **Banner 772×250** (standard) and **1544×500** (retina add-on) — must be exactly these dimensions
-   **Retina banner is an add-on only** — can't ship 1544×500 without the 772×250 companion
-   **Plugin headers max 4 MB**, but smaller is better (CDN caches heavily — minutes to 6 hours to refresh after deploy)
-   **PNG or JPG**; for icons SVG is also accepted but needs a PNG fallback
-   **Localized variants** possible with `-rtl`, `-es`, `-es_ES` suffixes (e.g. `banner-772x250-rtl.png`)

---

## Part 1: The event mix (strategy)

### Audience — who you're convincing

Most installers fall into one of three buckets. A good mockup hits all three.

1. **"Who did this?!"** — site owner who just got burned (page deleted, setting changed by a client). Wants accountability across multi-user sites.
2. **"Is anyone trying to hack me?"** — security-aware admin watching for failed logins, suspicious user creation, unauthorized changes.
3. **"I need an audit trail"** — agency, e-commerce, or compliance-driven site. Wants comprehensive logging across plugins, content, users, settings.

### The current 6-event mix (top → bottom in the rendered log)

| #   | Event                                                  | Hook                                     |
| --- | ------------------------------------------------------ | ---------------------------------------- |
| 1   | Failed login from suspicious IP (203.0.113.42)         | Security                                 |
| 2   | Robin published "Summer collection now available"      | Multi-user content workflow              |
| 3   | Sally updated "About us" — inline title + content diff | The differentiator                       |
| 4   | Alex updated plugin "WooCommerce" 9.5.2 → 9.5.3        | Ops tracking                             |
| 5   | Sally uploaded "team-photo.jpg" with thumbnail         | Visual variety, media tracking           |
| 6   | WP-CLI created user "deploy-bot" (administrator)       | Scripted/CLI activity + security tension |

### Why this mix works

-   **Time clustering** — all events from "Today". Site feels alive.
-   **Avatar variety** — three named users with photo avatars (Sally, Robin, Alex), one anonymous attacker, one WP-CLI. Multi-user-site story.
-   **Logger variety** — security, posts, plugins, media, CLI. Hints "logs everything".
-   **One "wow" detail per event** — IP address, inline diff, changelog link, thumbnail, WP-CLI initiator.
-   **All three personas covered.**

### What NOT to show

-   Settings changes like "Updated setting 'Tagline'" — technical, doesn't translate to non-developers.
-   Identical repeating events ("Logged in" × 3 from the same user) — boring, noisy.
-   Long ugly IDs / hashes / serialized PHP.
-   Premium-only events on the free plugin page (misrepresents what users get for free).
-   "lorem ipsum" / placeholder content.
-   Stale timestamps ("3 months ago").
-   "1 update" admin menu bubble — pulls focus from the log (suppressed via mu-plugin, see below).

### Variations

-   **Lean security** — swap upload for "User 'editor-john' was created with role Administrator" by unknown initiator.
-   **Lean e-commerce** — swap upload for "Order #1024 status: Pending → Processing" (use on the premium plugin page, NOT the free wp.org page — order tracking is premium).
-   **Lean agency** — swap names to suggest agency + client roles.

---

## Part 2: The Playground pipeline (production)

### Quick start

```bash
npm run screenshot
```

That boots a fresh WordPress Playground, populates the curated events + historical noise, captures **both** `.wordpress-org/screenshot-1.png` (main log view) **and** `.wordpress-org/screenshot-dashboard-widget.png` (dashboard widget) via Playwright, and tears the playground down. **About 90 seconds end-to-end.**

### Files in the pipeline

```
tests/screenshot/
├── blueprint.json              ← Playground blueprint (steps: login, mkdir, cp, activatePlugin, setSiteOptions, runPHP)
├── events.php                  ← Creates users, fires the 5 curated events, generates ~50 historical events
├── silence-mu-plugin.php       ← Filters out noise + remaps avatars
├── run.sh                      ← Boots playground, calls Playwright, tears down
├── team-photo.jpg              ← Upload fixture (Unsplash / Pexels — see "Image fixtures" below)
├── avatar-sally.webp           ← Avatar for sally@example.com
├── avatar-alex.webp            ← Avatar for alex@example.com
└── avatar-robin.png            ← Avatar for robin@example.com

tests/playwright/
├── screenshot-playground.spec.js          ← Main log view (1600×1130 @ 2×)
└── screenshot-dashboard-widget.spec.js    ← Dashboard widget at /wp-admin/index.php (clipped to widget element @ 2×)

playwright.config.js               ← Defines the `screenshot` project (separate from the chromium/auth pipeline)
```

### Viewport dimensions

Currently **1600×1130 @ deviceScaleFactor 2** → output **3200×2260 retina**.

Width 1600 gives the sidebar room to breathe (Insights chart legible). Height 1130 is just enough to show all 6 curated events without revealing the historical noise underneath. Drop below ~1100 and the WP-CLI event at the bottom gets cut off; go much higher and the historical-noise rows below leak into the frame.

### What the blueprint does

1. `login` — admin / password (Playground's default — note this differs from the project's other Playwright pipeline at `auth.setup.js`, which targets the Docker/wp-env stack with `claude` / `claude`. They're separate stacks, don't unify the creds)
2. `mkdir mu-plugins` — manual because Playground's WP install doesn't have it
3. `cp` — copies `silence-mu-plugin.php` to `wp-content/mu-plugins/` (drop-in mu-plugin loaded before everything else). Note this aborts the whole blueprint if the source is missing, unlike the `runPHP copy()` it replaced, which failed silently and left the noise filters off
4. `activatePlugin` — Simple History
5. `setSiteOptions` — site title "My website", tagline "Just another WordPress site", `show_avatars=1`, default avatar `mystery`
6. `runPHP` — requires `events.php` to populate the log

The plugin is **mounted from the working directory** (`--mount=.:/wordpress/wp-content/plugins/simple-history`) — not pulled from wordpress.org — so local changes show up immediately.

### What events.php does (in order)

1. TRUNCATEs `wp_simple_history` + `wp_simple_history_contexts` (clean slate)
2. Deletes `simple_history_auto_backfill_pending` flag (so welcome event doesn't fire on first admin page hit)
3. Creates users: Sally (editor), Alex (admin), Robin (editor), Mike/Jess/Sam (extras)
4. Fires events in chronological order — **fire-order matters: first call → oldest → bottom of log**:
    1. WP-CLI user_created for `deploy-bot` (administrator) — fires first → lands at bottom
    2. Media upload of `team-photo.jpg`
    3. WooCommerce plugin update
    4. About us page diff
    5. Robin publishes "Summer collection"
    6. Failed login (fires last → top of log)
5. Generates ~50 historical events across 28 days via direct DB inserts (loggers always stamp NOW, so direct insert is the only way to backdate).
    - Weighted user pool: Sally + Alex + Robin get 4 slots each, extras 1 each — ensures top 3 of "Most active users" widget have photo avatars.
    - Per-day count is randomised: 30% quiet days, 40% light days (1-3), 30% busy days (4-7) — gives the History Insights chart varied bars instead of a flat line.

### What silence-mu-plugin.php does

**Filters out noise events:**

```php
// Self-noise from blueprint bootstrap and auto-backfill.
simple_history/log/do_log/SimpleHistoryLogger/auto_backfill_completed   → false
simple_history/log/do_log/SimpleHistoryLogger/manual_backfill_completed → false
simple_history/log/do_log/AvailableUpdatesLogger/{plugin,theme,core}_update_available → false
simple_history/log/do_log/SimpleUserLogger/user_logged_in               → false
simple_history/log/do_log/SimpleUserLogger/user_unknown_logged_in       → false
simple_history/log/do_log/SimpleUserLogger/user_logged_out              → false
simple_history/log/do_log/SimplePluginLogger/plugin_activated           → false
```

**Mutes auto-fired `user_created` events from blueprint bootstrap, but lets the curated WP-CLI one through:**

```php
// Bootstrap creates Sally/Alex/Robin/Mike/Jess/Sam → each fires a user_created
// event we don't want. Discriminate by _initiator: only let WP_CLI through.
add_filter( 'simple_history/log/do_log', function ( $do_log, $level, $message, $context, $logger ) {
    if (
        $logger->get_slug() === 'SimpleUserLogger'
        && ( $context['_message_key'] ?? '' ) === 'user_created'
        && ( $context['_initiator'] ?? '' ) !== \Simple_History\Log_Initiators::WP_CLI
    ) {
        return false;
    }
    return $do_log;
}, 10, 5 );
```

**Suppresses the "1 update" admin menu bubble** (pulls focus from the log):

```php
add_filter( 'site_transient_update_plugins', ... );  // empty stdClass
add_filter( 'site_transient_update_themes',  ... );
add_filter( 'site_transient_update_core',    ... );
```

**Remaps avatars via `pre_get_avatar_data`** (NOT `get_avatar_data` or `get_avatar_url` — Simple History calls `get_avatar_data()` directly which only fires `pre_get_avatar_data` for early-return):

```php
add_filter( 'pre_get_avatar_data', function ($args, $id_or_email) {
    // is_numeric BEFORE is_string — string "2" passes both, numeric path needs to win.
    if ( is_numeric( $id_or_email ) ) { ... }
    elseif ( is_string( $id_or_email ) ) { ... }
    // ...
    $presets = [
        'sally@example.com' => 'avatar-sally.webp',
        'alex@example.com'  => 'avatar-alex.webp',
        'robin@example.com' => 'avatar-robin.png',
    ];
    // returns array with url + found_avatar=true
}, 10, 2 );
```

### What the Playwright spec does

1. Logs in as admin / password
2. Navigates to the Simple History admin page
3. Waits for `.SimpleHistoryLogitems.is-loaded` (60s timeout)
4. Sleeps 2s for avatars / thumbs to finish loading
5. Hides any admin notices (`.notice` → `display:none`)
6. **The mouse-away dance:**
    - `page.mouse.move(0, viewport.height - 1)` — park cursor in bottom-left so it's not hovering the admin menu (left-side hover expands it) or any event row (grey hover background).
    - Dispatches synthetic `mouseleave` / `mouseout` on all event rows + log items — kills any in-flight hover state from cursor movement during page load.
7. Screenshots to `.wordpress-org/screenshot-1.png` with `fullPage: false` (viewport only).
8. **Test timeout is 120s** — long enough for cold playground first-page hit with ~70 events in the log.

The spec also captures `pageerror` + 4xx/5xx responses to the console — if the React app crashes (which it does if context keys are missing from direct DB inserts), the stack trace surfaces in the Playwright output.

### Customising

| Want to change                        | Edit                                                                             |
| ------------------------------------- | -------------------------------------------------------------------------------- |
| The curated events                    | `tests/screenshot/events.php` (top half, before historical loop)                 |
| Which user has which avatar           | `tests/screenshot/silence-mu-plugin.php` `$presets` array                        |
| Which events are silenced             | `tests/screenshot/silence-mu-plugin.php` add/remove `__return_false` filters     |
| Site title / tagline                  | `tests/screenshot/blueprint.json` `setSiteOptions`                               |
| Viewport / dimensions                 | `tests/playwright/screenshot-playground.spec.js` `test.use`                      |
| Historical event count / distribution | `tests/screenshot/events.php` `for ( $days_ago = 1; $days_ago <= 28; ... )` loop |
| User weighting (who tops the widget)  | `tests/screenshot/events.php` `$user_ids = array_merge(...)`                     |

---

## Part 3: Image fixtures

### Avatar fixtures

Saved as `avatar-{username}.{ext}` in `tests/screenshot/`. Served via the `pre_get_avatar_data` filter. WebP and PNG both work. ~200×200 square is the right size — Simple History renders them at 48×48 in the log.

### Team photo

Source from **Unsplash** or **Pexels** — both license commercial use, derivatives, no attribution required.

-   Unsplash License: no attribution required for any use except reselling unmodified copies or building a competing service.
-   Pexels License: same terms.
-   For zero-risk model-release safety: prefer people-free photos (desks, hands on keyboards, plants, generic office).

Save as `tests/screenshot/team-photo.jpg`. Re-running `npm run screenshot` picks it up — no code changes.

---

## Part 4: Banner production (separate from main screenshot)

The banner is a designed asset — the UI panel on the right is a stylized mockup, not a literal admin screenshot. Two paths:

### Option A — Standalone HTML mockup

Build the UI panel as a standalone HTML file matching Simple History's event row styling, populate the 6 events above as static HTML, screenshot via Playwright at 2× scale, then composite into the banner in Figma / design tool.

Advantages:

-   No "today's actual logger output" leaking in
-   Pixel control over layout, spacing, badges
-   Easy to iterate (edit HTML, re-screenshot)

### Option B — Design tool (Figma, Sketch)

If a banner source file exists in the project archive, edit there. Recreating from scratch loses headline typography and layout decisions.

### Headline copy (banner only)

Current copy works and shouldn't change unless deliberately revisiting brand:

-   Headline: _Your powerful all-in-one WordPress Activity Log._
-   Subhead: _Stay informed and in control with Simple History._
-   Body: _Track content changes, debug faster, ensure security, troubleshoot client issues – and more!_

---

## Part 5: Gotchas + lessons learned

These are the things that cost an hour to figure out the first time.

1. **`get_avatar_url` filter doesn't fire** — Simple History calls `get_avatar_data()` directly. Use `pre_get_avatar_data` (early-return) or `get_avatar_data` (late filter). The `pre_*` variant is the cleanest.
2. **`is_string("2")` returns true** — when checking `$id_or_email`, put `is_numeric` first or you'll resolve user-id 2 as the literal string `"2"` and the email lookup will fail.
3. **Direct DB inserts need full context** — if you write to `wp_simple_history` directly to backdate events, the row needs matching `wp_simple_history_contexts` entries for `_user_id`, `_user_login`, `_user_email`, `_initiator`, `_server_remote_addr`, `_message_key`, `_loggerSlug`. Missing any of these can crash the React app with `Cannot read properties of null (reading 'replace')`.
4. **Loggers stamp NOW** — `$logger->info_message(...)` always uses the current time. To backdate, write to the DB directly.
5. **Fire-order = visual-order reversed** — first event fired → oldest timestamp → bottom of log.
6. **Mouse hover leaves a grey row background** — the cursor often ends up over an event row after the navigation animation finishes. Park it in the bottom-left AND dispatch synthetic mouseleave events on the rows to be safe.
7. **Auto-backfill fires on first admin_init** — kill `simple_history_auto_backfill_pending` in events.php or you'll get a "Welcome to Simple History" event at the top.
8. **The auth.setup spec creates a "Logged in" event** — separate the `screenshot` Playwright project from the `chromium` one (with no setup dependency) so the screenshot spec logs in on its own clean session.
9. **Most Active Users widget showed "(1)" for users with no display_name** — fixed in core: `inc/class-events-stats.php` `get_top_users()` now selects `user_login` and falls back in PHP when display_name is empty.
10. **WordPress version compatibility** — pipeline targets WP latest. If you need to test against a specific version, set `"preferredVersions": { "wp": "6.9" }` in `blueprint.json`.
11. **Per-event-type filters can't be conditional** — `add_filter('simple_history/log/do_log/SimpleUserLogger/user_created', '__return_false')` blocks the message globally. To allow some `user_created` events through (e.g. the WP-CLI one) but block bootstrap noise, use the general `simple_history/log/do_log` filter and discriminate on `$context['_initiator']` instead.

---

## Quick reference — copy-paste event template

```
⚠️ Anonymous web user · Today 14:23 · IP address: 203.0.113.42
Failed to login with username "admin" (incorrect password entered) [warning]

Robin Editor (robin@example.com) · Today 14:21
Updated post "Summer collection now available"
  Status: Changed from draft to publish

Sally Admin (sally@example.com) · Today 14:18
Updated page "About us"
  Title:   About → About us
  Content: We sell t-shirts. → We sell premium t-shirts and hoodies. Visit our store downtown.

Alex Admin (alex@example.com) · Today 13:51
Updated plugin "WooCommerce" to version 9.5.3 from 9.5.2
View changelog →

Sally Admin (sally@example.com) · Today 11:42
Uploaded attachment "Team photo"
[thumbnail of team-photo.jpg]

WP-CLI · Today 09:12
Created user deploy-bot (deploy@example.com) with role administrator
```
