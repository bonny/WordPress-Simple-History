# Competitor audit log plugins — research

Comparison of Simple History against the other audit-log / activity-log plugins installed on the mariadb dev instance, focused on three questions:

1. What features do they have that we don't?
2. What events do they log that we miss (including third-party plugin events)?
3. How do they detect and store network/multisite events, and does our recent implementation follow best practice?

Sources: direct reading of each plugin's code under `/Users/bonny/Projects/_docker-compose-to-run-on-system-boot/data/wp/wordpress-stable-mariadb/wp-content/plugins/`.

Plugins reviewed:

-   **WP Activity Log** (by Melapress, dir `wp-security-audit-log/`) — commercial, most mature. 302 numbered events in the free tier.
-   **Stream** (by XWP, dir `stream/`) — popular free plugin; 22 connectors, 10+ third-party integrations, single-table multisite storage.
-   **Aryo Activity Log** (`aryo-activity-log/`) — older free plugin, per-blog tables.
-   **Activity Track** (`activity-track/`) — small modern plugin with AI summaries and real-time alerts as paid add-ons.
-   **Logtivity** (`logtivity/`) — SaaS; ships logs off-site to a remote dashboard, strong third-party plugin coverage.

---

## 1. Features we don't have

Grouped by theme. Marked ★ for features that feel like the biggest gaps — things our users actually ask about — and (P) for things already available in Simple History Premium (confirmed from `simple-history-add-ons/simple-history-premium/inc/modules/`).

### Alerts & notifications

-   **Slack integration** — native in Stream, WPAL Premium, Activity Track, Logtivity. We have HTTP channels in Premium, but no first-class Slack formatter/webhook UI. ★
-   **SMS alerts** — WPAL Premium (via Twilio). Niche, but a differentiator for enterprise.
-   **IFTTT / webhook rules with condition filters** — Stream ships an IFTTT connector plus rule-based triggers (match by action + context + author). Our Premium alerts module has rules; verify parity with Stream's "action + context + author" triple-condition trigger.
-   **Daily / weekly activity summary emails** — WPAL Premium. We have Email Reports module (P) — confirm daily+weekly cadence.
-   **On-screen "highlight" / menu badge alerts** — Stream shows a numeric badge in the admin menu when flagged events fire. Cheap to copy, useful for super-admins.
-   **"Die" / abort action in alert rules** — Stream has an alert type that stops further processing after matching. Unusual, probably not worth copying.

### Exclusion rules (noise control)

All three commercial competitors (WPAL, Stream, Activity Track) expose a rich exclusion UI. We have user blocklist + log level, but nothing like:

-   Exclude by **IP address / CIDR** ★
-   Exclude by **post type** ★
-   Exclude by **custom field name** (glob patterns)
-   Exclude by **user meta field**
-   Exclude by **post status**
-   Exclude by **role** (we have this partially via capability gating, but not a "do not log actions by role X" rule)

WPAL event IDs 6053–6058 are literally "user X added/removed from excluded list" — they log changes to their own exclusion rules. Something to consider for our own self-logging.

### Reports & analytics

-   **HTML / PDF report generation** — WPAL Premium (HTML+CSV), Activity Track (PDF). We have CSV/JSON export; no PDF or templated HTML report.
-   **Customizable report templates** — WPAL Premium.
-   **"Statistical reports"** (top users, top actions, peak times) — WPAL Premium, Activity Track. We have Stats in Premium — confirm depth.
-   **Report scheduling** — WPAL Premium (emailed daily/weekly). We have Email Report (P).

### Log storage, mirroring, retention

Fairly strong on our side — Premium ships external DB, HTTP, file, syslog, JSON feed channels. Competitor features we don't match:

-   **AWS CloudWatch / Loggly / Papertrail** as named first-class destinations — WPAL Premium. We could add pre-configured presets on top of our HTTP channel.
-   **Archive-to-separate-DB** (move old logs instead of deleting) — WPAL Premium. We only purge.
-   **"Database logging status changed" self-event** — WPAL event 6327. We should log toggles of our own channels.

### User sessions (WPAL Premium)

No equivalent on our side:

-   Real-time "who is currently logged in" list
-   Admin-initiated session termination
-   Concurrent session enforcement (max N sessions per user)
-   Idle session auto-timeout (separate from WP's own nonce expiry)

This is a whole feature area — probably a Premium candidate rather than core.

### Third-party plugin coverage — biggest gap

Our integrations (see `loggers/class-plugin-*.php`): ACF, Beaver Builder, Duplicate Post, Enable Media Replace, Jetpack, Limit Login Attempts, Redirection, User Switching, WP Crontrol. Plus WooCommerce via the separate premium add-on.

Competitors cover meaningfully more. Gaps (by how frequently multiple competitors target them):

| Plugin                         | WPAL                                | Stream                    | Aryo          | Logtivity | Us                |
| ------------------------------ | ----------------------------------- | ------------------------- | ------------- | --------- | ----------------- |
| WooCommerce (depth)            | ★ products/orders/coupons/tax/stock | ★ products/orders/coupons | settings only | —         | separate add-on ★ |
| Yoast SEO                      | ✅                                  | ✅                        | —             | —         | —                 |
| RankMath                       | ✅                                  | —                         | —             | —         | —                 |
| Gravity Forms                  | ✅                                  | ✅                        | —             | —         | —                 |
| WPForms                        | ✅                                  | —                         | —             | —         | —                 |
| bbPress                        | ✅                                  | ✅                        | partial       | —         | —                 |
| BuddyPress                     | —                                   | ✅                        | —             | —         | —                 |
| Easy Digital Downloads         | —                                   | ✅                        | —             | ✅        | —                 |
| MemberPress                    | ✅                                  | —                         | —             | ✅        | —                 |
| Paid Memberships Pro           | ✅                                  | —                         | —             | —         | —                 |
| LearnDash                      | ✅                                  | —                         | —             | —         | —                 |
| TablePress                     | ✅                                  | —                         | —             | —         | —                 |
| Formidable Forms               | —                                   | —                         | —             | ✅        | —                 |
| Code Snippets                  | —                                   | —                         | —             | ✅        | —                 |
| WP All Import                  | —                                   | —                         | —             | ✅        | —                 |
| Download Monitor               | —                                   | —                         | —             | ✅        | —                 |
| Two Factor plugin              | —                                   | ✅                        | —             | —         | —                 |
| WP 2FA (Melapress)             | ✅                                  | —                         | —             | —         | —                 |
| Mercator (domain mapping)      | —                                   | ✅                        | —             | —         | —                 |
| Redirection                    | ✅                                  | —                         | —             | —         | ✅                |
| MainWP / ManageWP / InfiniteWP | ✅                                  | —                         | —             | —         | —                 |

**My read**: Yoast, Gravity Forms, WPForms, bbPress, and BuddyPress are the five most-asked-for integrations across the competitive set. Mercator is niche but Stream-unique. Deep WooCommerce (orders, refunds, stock) already has our add-on but is worth double-checking against Stream/WPAL coverage.

### Other features worth noting

-   **PHP error logging** — Logtivity tracks errors/warnings/notices in the activity feed. Unusual but useful for agencies. Not sure it belongs in an audit log though (mixes compliance with debugging).
-   **White-label mode** — Logtivity ships this. We don't need it in core but agencies have asked.
-   **Reverse proxy / Cloudflare IP detection setting** — WPAL exposes a UI toggle. We do the detection silently. A visible setting would help support cases.
-   **WP-CLI parity** — Stream has `wp stream query ... --format=table|json|csv`. We added `wp simple-history list` recently. Verify we cover querying by action, user, date range, and have the same output formats.

---

## 2. Events we don't log (prioritised)

Drawn from diffing the competitor event lists against `loggers/*.php`. Focus on **core WordPress** actions first, then third-party plugin events.

### High-value core events missing or weak

Looking at WPAL's numeric catalog (the most complete reference), events we don't explicitly cover:

**Posts / pages** (WPAL 2000-series)

-   **URL / slug change** as a dedicated event (2017, 2018, 2037) — we log post updates but don't call out slug changes specifically; they matter for SEO audits.
-   **Post author change** (2019, 2020, 2038) — we capture diffs but don't surface author-transfer cleanly.
-   **Post visibility change** (2025, 2026, 2040) — public → private, public → password protected. Security-relevant.
-   **Post date change** (2027, 2028, 2041).
-   **Set / removed sticky** (2049, 2050).
-   **Scheduled** vs **published** distinction (2074–2076). We currently log both as "published".
-   **Page parent change** (2047), **page template change** (2048).
-   **Custom field rename on post** (2062, 2063, 2064). We detect adds/removes but not renames.
-   **Password-protected post access** (2134 success, 2135 wrong password). Security-relevant, low cost to add.
-   **Visitor posted comment** (2126) vs user posted comment (2099) — we log comments but don't separate visitor vs logged-in.

**Taxonomy** (WPAL 2119–2128, 2052)

-   **Post tag add / remove** as its own event (2119, 2120) — right now it blends into "post updated".
-   **Tag slug / description change** (2124, 2125).
-   **Category slug change** (2128), **category parent change** (2052).

**Users / auth** (WPAL 4000s, 1000s)

-   **User requested password reset** (1010).
-   **Login blocked** (1004) — relevant with Limit Login Attempts-style plugins.
-   **User denied access to page** (1011) — useful, rare.
-   **Session events**: "logged in while other sessions still active" (1005), "logged out all other sessions" (1006), "session destroyed by admin" (1007). We don't track session state.
-   **Application password created / revoked** (4025–4028) — introduced in WP 5.6. Worth checking our user logger.
-   **Admin sent password reset to user** (4029).
-   **User custom field add / update / delete** (4015–4018) — WPAL tracks these on users, we only cover post meta + options.
-   **Failed login for non-existing user** (1003) distinguished from existing-user failed login (1002). We have this as of the login logger — confirm.

**Settings / core** (WPAL 6000s)

-   **Site icon add / change / remove** (6063–6065).
-   **Site title change** as a dedicated event (6059) — we log it via options logger but not surfaced.
-   **WP version upgrade translation files** (6080).
-   **Auto-update setting changed** (6044) — check against our Core_Updates_Logger.
-   **Reverse proxy / Cloudflare setting** (6048).
-   **Email sent (success / failure)** (6061) — Aryo and Activity Track log all `wp_mail` calls. High volume, should be opt-in, but useful.
-   **Cron job created / modified / deleted / ran** (6066–6072). Our WP Crontrol logger covers crontrol-managed ones; WPAL covers core `wp_schedule_event` too.

**Appearance / files**

-   **Theme customizer changes** (Aryo logs these).
-   **Plugin file editor access** (WPAL 2051, Aryo) — we have `file-edits-logger`; confirm this specific case.
-   **Database table created / modified / deleted by plugin-theme-core** (WPAL 5010–5024) — unusual but interesting for compliance.

### Third-party plugin events we miss entirely

Ordered by cross-plugin demand:

-   **Yoast SEO**: setting changes, per-post SEO metadata changes (focus keyword, title, description).
-   **RankMath**: same as Yoast.
-   **Gravity Forms / WPForms**: form created/updated/deleted, field added/removed, entry trashed, notification added.
-   **bbPress**: forum/topic/reply CRUD, status (closed/sticky/spam).
-   **BuddyPress**: group CRUD, member joined/left/promoted/banned, profile field edits.
-   **EDD**: download CRUD, discount codes, payment status changes, exports.
-   **MemberPress / PMP**: membership level CRUD, user subscription changes, transactions.
-   **LearnDash**: course/lesson/quiz CRUD, enrollments, completions.
-   **Two Factor / WP 2FA**: 2FA enabled/disabled per user, recovery code use.
-   **Mercator**: domain alias CRUD (multisite only — see network section).
-   **WooCommerce** (deep, beyond our add-on's current scope): worth a direct diff against Stream and WPAL sensor lists.

### Multisite / network events

Our Premium Network_Logger (`simple-history-add-ons/.../class-network-logger.php`) already covers: site create/delete/archive/spam/mature/deactivate/public-status/domain-path changes, super-admin grant/revoke, network user create/delete/spam, user add/remove to site, network themes enable/disable, tracked network settings. Strong coverage. Missing vs WPAL:

-   **Network user signed up** (4024) — pre-activation signup, before `wpmu_new_user` fires. We deliberately skip this; document the reason so future us doesn't re-add it.
-   **`wpmu_upgrade_site`** (WPAL 7013) — fires when a subsite's DB is upgraded after core update.
-   **Site admin panel upload settings** (WPAL 7008–7011) — we track some via `tracked_network_settings` but not all (`upload_space_check_disabled`, `upload_filetypes`, `fileupload_maxk`). Verify our list against WPAL's.
-   **Network registration policy change** — we track the `registration` option. Confirm WPAL's event 7012 maps to the same thing.

---

## 3. Network / multisite: how we compare

### The three architectural approaches

| Plugin                               | Storage                                                                         | How a row is assigned to a site                                                                                                      | Network-level events                                         |
| ------------------------------------ | ------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------ |
| **Simple History Premium (current)** | Per-site tables + a separate shared `{base_prefix}simple_history_network` table | Decided at log-time: `is_network_logger` flag on the logger OR runtime heuristic (`is_network_admin()`, referer sniff for AJAX/REST) | Dedicated `Network_Logger`, written only to the shared table |
| **Stream**                           | Single shared `{base_prefix}stream` table for the whole network                 | Every row has `site_id` (network) + `blog_id` (site) columns; network-admin actions use `blog_id=0`                                  | Same table, filtered by `blog_id=0`                          |
| **WP Activity Log**                  | Single table with `site_id` column                                              | `site_id` populated from `get_current_blog_id()` at log-time                                                                         | Event IDs 7000–7013 in the same table                        |
| **Aryo Activity Log**                | Per-blog tables, created via `switch_to_blog()` on install                      | Implicit from current blog                                                                                                           | Not tracked as distinct events                               |
| **Activity Track**                   | Per-blog tables                                                                 | Implicit from current blog                                                                                                           | Not tracked as distinct events                               |
| **Logtivity**                        | Remote SaaS                                                                     | Service-side                                                                                                                         | Service-side                                                 |

### What Stream and WPAL do that we don't

**Single-table model with a `blog_id` column.** Every event row, including per-site events, carries the blog ID. This makes two queries trivial:

-   "Everything across the whole network" → `SELECT * FROM stream WHERE site_id = N`
-   "All activity on site 5" → `WHERE blog_id = 5`

Our model requires either (a) querying the network table only, which misses per-site activity, or (b) unioning N per-site tables, which is slow and breaks if sites were installed before the plugin.

**No switch_to_blog at log time** (same as us). Both Stream and WPAL write from the current blog context without switching. Our Premium logger does the same. ✅ This is best practice.

### Where our approach is better

-   **Per-site tables stay meaningful when a subsite is detached.** If a site is exported/moved, its history comes with it. Stream's network-wide single table makes that harder.
-   **Site admins can't see network events.** Network events live in a separate table; even if a site admin finds our admin page, they can't accidentally read super-admin-only events. Stream mixes them and relies on `blog_id` filtering at render time.
-   **Clean domain separation.** `Network_Logger` is a single concern — easy to reason about, easy to test.

### Where our approach is riskier

1. **Runtime "where does this event go?" decision is heuristic.** `should_use_network_tables()` checks `is_network_admin()` and, for AJAX/REST, sniffs the referer for `/wp-admin/network/`. That works for interactive flows but:

    - **Background tasks** (cron, action-scheduler, WP-CLI) have no referer and no network admin context. A network-scoped event fired from cron would be misrouted to a per-site table.
    - **Direct API calls** (`wp-json/wp/v2/sites`, `wp cli network ...`) won't match either check.
    - **REST tools** that submit from a network admin page but strip the referer header would fall through.

    Mitigation today: network-scoped loggers use `$is_network_logger = true`, bypassing the heuristic. Loggers that fire network-relevant events without that flag are the risk surface. The `Available_Updates_Logger` is one to audit — network-wide auto-updates fire from cron.

2. **No unified network-wide view possible without the Premium plugin.** Core multisite users get the teaser page but can't see activity across sites at all until they upgrade. Stream and WPAL give network admins _something_ out of the box. This is intentional for our freemium model, but worth being explicit about in the teaser copy.

3. **The network table is base-prefix scoped, single-writer.** On big networks, insert contention into one table is higher than fan-out to per-site tables. Not a problem at current scale but worth knowing.

4. **Routing decision happens inside `Logger::log()`**, swaps `$this->db_table` in place, and relies on `try/finally` to restore. The try/finally fix landed in commit `4109ec0f`, so that's good — but the whole swap-and-restore dance is a smell. Stream's model (the row always has a blog_id column, the logger never decides the table) is simpler.

### Things to verify / improve

-   **Audit non-network loggers for network-admin fires.** Any logger that fires from a network-admin context but doesn't set `$is_network_logger` risks misrouting when `should_use_network_tables()` doesn't detect the context (cron, CLI, REST without referer). Candidates: `Plugin_Logger` (network plugin activation), `Theme_Logger` (network theme enable — already handled by our network logger), `Core_Updates_Logger` (network core update).
-   **Consider adding a `blog_id` column to the per-site events table**. Then super-admins could see aggregated activity across sites by unioning tables (or, long-term, by migrating to a single shared table). Low-risk additive change.
-   **Document the routing decision** in `AGENTS.md` or a skill, so "when should a logger set `$is_network_logger`?" has a one-line answer for future contributors.
-   **Explicitly unit-test the routing heuristic** for cron/WP-CLI/REST-without-referer. If we already have tests, make sure they cover the negative case too.
-   **Mirror compare**: do a targeted diff of our `tracked_network_settings` list against WPAL's events 7007–7012 so we don't miss a network setting that matters.

### Verdict on best practice

Our implementation is defensible and reasonably clean. The main deviation from competitor best practice is **storing events in two separate tables** rather than a single shared table with a blog_id column. That design choice has real upsides (isolation, portability, separation of concerns) but costs us cross-site aggregation and adds a heuristic routing step. It's not wrong — it's a trade-off, and if we stick with it we should:

-   lean into what the split gives us (per-site portability, clean super-admin isolation),
-   document the heuristic clearly,
-   and cover the cron / WP-CLI / REST-without-referer cases with explicit `$is_network_logger` flags rather than relying on context detection.

---

## Quick wins I'd prioritise

If we wanted to close the most visible gaps in a single release cycle:

1. **Yoast + Gravity Forms + bbPress loggers.** The top three integration gaps that appear across the competitive set. ★
2. **Exclusion rules UI** (IP, post type, custom field name). Cheap, high-impact, reduces noise complaints.
3. **Distinct events for URL/slug change, visibility change, author change, sticky toggle, scheduled-vs-published.** These are all already in our post logger's diff — we just don't surface them as named events. ★
4. **Site icon + site title as first-class events.** Already captured, just not separated.
5. **Application password create/revoke.** WP 5.6+ feature, worth a dedicated event.
6. **Document the network-table routing heuristic** and audit loggers that fire from cron/CLI in a network-admin context.

---

## 4. Premium / upsell review

Re-reading the gaps through the freemium lens. Our stated philosophy is "Free is great, Premium is a must-have" — so this section classifies each gap as **FREE** (table stakes, belongs in core), **PREMIUM** (genuine must-have upgrade), or **TEASER** (free sees a hint, premium unlocks the rest). Also flags where the current premium value prop is thinner than WPAL's and could be shored up.

### What must stay FREE

These are things every competitor's _free_ tier already does. Putting them behind premium would break the "free is fully functional" promise and give reviewers ammunition:

-   **Distinct events for slug / author / visibility / sticky / scheduled-vs-published.** We already capture them in diffs — promoting them to named events is pure UX polish. Keep free.
-   **Site icon / site title as first-class events.** Same — already captured, just a labelling change.
-   **Application password create / revoke.** Core WP 5.6+ behaviour. Any serious audit log logs these.
-   **Password reset request, login blocked, password-protected post access.** Security basics.
-   **Post tag add/remove, taxonomy slug/parent changes** as dedicated events.
-   **`wpmu_upgrade_site` + missing tracked network settings** (`upload_filetypes`, `fileupload_maxk`, `upload_space_check_disabled`). We already have a network logger in premium — completing the coverage is maintenance, not a new premium feature.

**Why it matters**: Stream's free tier covers most of these. WPAL's free tier covers _all_ of them (events 2017–2076, 1010, 4025–4028). If we hide them behind Premium we look stingy compared to both. And the incremental implementation cost is low because the data is already in context.

### What's clearly PREMIUM

Things where WPAL has already trained the market to expect a paid upgrade, and where the value proposition is in-scope for an audit log:

-   **Deep third-party plugin integrations** — Yoast, Gravity Forms, WPForms, bbPress, BuddyPress, EDD, MemberPress, LearnDash. Single clearest premium opportunity in the whole research: it's the purest expression of what an audit log does (observe more things), WPAL and Stream both put integration lists front-and-centre on their pricing pages, and every new integration is a concrete bullet we can put on ours. Our current integration set (ACF, Beaver Builder, Jetpack, LLA, Redirection, User Switching, WP Crontrol + the Woo add-on) is narrower than WPAL's 15+ and Stream's 10+. Making integrations the headline premium feature turns the upgrade story into "we log more of what you actually use". ★
-   **Exclusion rules UI** (IP/CIDR, post type, custom field, post status, user meta). WPAL and Stream both ship these in their paid tiers. Solves real user pain (noise) and is easy to demo.
-   **Named alert destinations with rule-based triggers** — Slack, Teams, Discord, Datadog, CloudWatch as first-class presets, not just generic HTTP. (P) partial — we have the alerts module and HTTP channels. Missing: named destinations with working formatters, rules that combine action + context + user, templated message bodies.
-   **Archive-to-separate-DB** (move old logs rather than delete). Compliance / long-retention story. Pairs with our existing external-DB channel.
-   **PDF reports + templated HTML reports + scheduled report delivery.** (P) partial — Email Report exists. Upgrade to templated + PDF is premium-only territory.
-   **Statistical reports** (top users, top actions, peak activity windows) — Stats module exists (P); confirm depth against WPAL's analytics view.
-   **Session observability** (not session management) — session history on the user card (login times, IPs, user agents, geolocation) plus anomaly alerts (new IP, new device). Narrow, in-scope feature built on top of events we already log. **Do not build session _control_** (terminate, concurrent limits, idle timeout): see the "NOT to copy" section below.

### TEASER candidates (free sees enough to want more)

These are features where giving free users a _glimpse_ is good marketing. It matches what we're already doing on the `issue-network-teaser` branch — hide the functionality, keep the discoverability:

-   **Third-party plugin integrations** (Yoast, Gravity Forms, WPForms, bbPress, BuddyPress, EDD, MemberPress, LearnDash). Possible framings:
    -   **Free detects activation, premium tracks events.** When Yoast is active, free logs "Yoast SEO plugin activated" and shows a teaser in the event: _"Premium tracks every setting change and per-post SEO edit."_
    -   **Or ship a small subset in free** (plugin install/activate/deactivate + the one or two most important settings) and everything else in premium.
    -   **Or individual paid add-ons**, following the existing WooCommerce add-on pattern. This is probably the cleanest revenue path for "long tail" integrations (LearnDash, MemberPress, EDD) where the audience is narrow but willing to pay.
-   **Exclusion rules**: free shows a disabled settings panel listing the filter types with an upgrade prompt.
-   **Session observability**: free shows a login history column on the Users screen limited to the last 3 entries, premium unlocks full history + anomaly alerts.
-   **Alerts**: our existing alerts teaser already does this — double check copy.

### Things NOT to copy (would hurt the free positioning or blur the product scope)

-   **WPAL Premium's session _control_ bundle** — terminate sessions from admin, concurrent session limits, idle auto-timeout. These are auth-policy features, not audit. They belong in dedicated session-manager plugins (WP 2FA, Wordfence, Limit Sessions for WP). Shipping them here is scope drift: it turns us into a half-built security plugin and muddies the "audit log" positioning. Stay in the observability lane — log session _events_, surface session _history_, alert on _anomalies_; don't take policy actions on sessions.
-   **Activity Track's "unlimited notification rules in Pro, basic in free"** — free-tier quota caps feel like trialware. Keep free features uncapped and differentiate on capability breadth, not row counts.
-   **Logtivity's remote-only model.** Don't remove local storage from free. Self-hosted local log is a core promise.
-   **PHP error logging** (Logtivity). Don't add unless specifically requested — mixes compliance with debugging, dilutes the product.
-   **"Extend your retention from 30 days to unlimited"-style premium pitches**. WPAL does this, and it reads as "we crippled the free version". We already retain as long as the DB allows — don't regress to a date cap.

### Shoring up the premium value prop

Looking at what WPAL Premium bundles vs. ours, the gaps in our premium value story today are:

1. **Third-party integration breadth.** We have ~9 integrations (ACF, Beaver Builder, Duplicate Post, Enable Media Replace, Jetpack, Limit Login Attempts, Redirection, User Switching, WP Crontrol) plus the Woo add-on. WPAL has 15+. Each new integration is a bullet on the pricing page that says "logs Yoast / Gravity Forms / bbPress". ★
2. **Exclusion rules** — no UI for this anywhere. Prime premium candidate; users feel the pain daily.
3. **Named alert destinations** (Slack, Teams, Discord, Datadog, CloudWatch) — all doable on top of our existing HTTP channel. Mostly marketing: give each a logo, a one-click setup, and a pricing-page checkbox.
4. **Session observability** — login history column on the user card + anomaly alerts (new IP, new device). Smaller than it first looks, because we already log the underlying events; this is a view + alerting layer, not a new module. Narrow and in-scope — do not expand to session control (see "NOT to copy").

In priority order for premium revenue impact: **integrations → exclusion rules → named channel presets → session observability.** Integrations is the headline because it's the purest expression of what an audit log does, and competitors have already trained buyers to evaluate audit logs by their integration list.

### Network-specific upsell angle

Relevant because we're literally on the `issue-network-teaser` branch.

Competitors' baseline in _free_:

-   **Stream free**: single shared table, super-admins get a network-wide view. Whole plugin is free — no premium tier exists.
-   **WPAL free**: per-site events with `site_id` column, 13 dedicated multisite event IDs, network admin menu. All free.
-   **Aryo free**: per-blog tables, network activation supported, network admin viewer accessible. Free plugin, no premium tier.
-   **Activity Track free**: per-blog tables, network-aware admin menu, multisite compatibility. The Pro tier is about AI summaries, alerts, PDF exports — none network-specific.
-   **Simple History free (current)**: per-site tables only. Network admin gets a teaser page that points at premium. Everything network-related is behind premium.

**The honest framing: nobody else gates the basic multisite view behind premium.** Stream, WPAL, Aryo and Activity Track all ship full network support in their free tier. What WPAL charges premium for (alerts, mirroring, reports, archiving, external DB, advanced filtering) are features that work _anywhere_ — they happen to also work on network, but they aren't network-specific. Nobody charges specifically for multisite-ness.

That reframes the two paths from earlier in this review:

1. **Stay aggressive on network being a premium-only feature.** We'd be alone in doing this — not one of two positions. Users coming from Stream or WPAL will read it as "the free tier is crippled on multisite", which is exactly the anti-pattern our freemium philosophy rejects.
2. **Ship a basic read-only network admin view in free** (recent events across sites, no advanced search/filter/export/stats/alerts). Match what every other plugin already does. Charge premium for the same cross-cutting features we'd charge single-site users for.

Path 2 is the consistent-with-competitors path and the one that lines up with our "Free is great, Premium is a must-have" positioning. Path 1 isn't defensible once you see that we're alone in taking it.

Under path 2, premium still has a clean story on multisite — it's the same story as on single-site, plus two genuine network-only advantages to highlight:

-   **Per-site portability**: our split-table model means detaching a site takes its history with it. Neither Stream nor WPAL offer this. For agencies that rebuild/migrate client sites it's a real benefit.
-   **Site-admin isolation**: a site admin cannot see network-level events because they live in a separate table they can't read. Stronger than Stream's blog_id-filter-at-render-time model.

Those two are genuinely ours — worth putting on the premium pricing page under multisite.

### One-sentence summary

**Keep all already-captured-but-unlabelled core events free, ship a basic network admin view free (don't gate multisite itself — nobody else does), make deep third-party integrations the headline premium feature, add exclusion rules and named channel presets next, and ship _session observability_ (history + anomaly alerts) — not session management — as the narrow in-scope session feature.**
