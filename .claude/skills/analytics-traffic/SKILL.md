---
name: analytics-traffic
description: Query Google Analytics (GA4) for simple-history.com traffic, top pages, referrers, and the premium_* UTM campaigns that tag admin links. Use when the user asks about visits, traffic sources, which admin links lead to the site, upsell click-through, or campaign performance.
allowed-tools: ToolSearch, mcp__analytics-mcp__run_report, mcp__analytics-mcp__get_account_summaries
---

# Simple History — GA4 Traffic Lookups

The Anthropic Analytics MCP (`mcp__analytics-mcp__*`) is available. Tool schemas are deferred — load them with `ToolSearch` before calling the first time.

## Key property IDs

-   `347521033` — **simple-history.com** (main marketing site, where premium\_\* campaigns land)
-   `312449738` — docs.simple-history.com
-   `367946141` — Simple History — GA4 (legacy/secondary)

Default to `347521033` unless the question is clearly about docs.

## UTM convention used in admin

The premium plugin tags every "Upgrade"/"Learn more"/etc. link in wp-admin with `utm_campaign=premium_<placement>`. So `sessionCampaignName` is the right dimension to answer "which admin links lead to the site." Known placements include:

`premium_user_card`, `premium_dashboard_sidebar`, `premium_sidebar_compact`, `premium_stats_box`, `premium_stats_charts`, `premium_stats_daterange`, `premium_header_cta`, `premium_header_addons`, `premium_global_modal`, `premium_upsell`, `premium_events_loginlimit`, `premium_events_ipaddress`, `premium_events_backfill`, `premium_settings_purge`, `premium_settings_upgrade`, `premium_licences_addons`, `premium_logger_purged`, `premium_woocommerce_sidebar`, `premium_debug_sidebar`, `premium_feeds_settings`, `premium_pluginspage_upgrade`, `premium_backfill_tools`, `premium_retention_nudge`, `premium_reactions`, `premium_dashboard_footer`, `premium_welcome_backfill`, `premium_welcome_retention`, `premium_link`.

Filter with `BEGINS_WITH "premium_"` to capture all of them (new ones may have been added).

`pageReferrer CONTAINS "wp-admin"` is a poor fallback — most admin links use `rel="noreferrer"` so the referrer is dropped. Always prefer campaign data.

## Common queries

### "Which admin links lead to the site?" / upsell performance

```
run_report(
  property_id=347521033,
  date_ranges=[{"start_date": "<start>", "end_date": "<end>"}],
  dimensions=["sessionCampaignName"],
  metrics=["sessions", "engagedSessions", "engagementRate", "averageSessionDuration"],
  dimension_filter={"filter": {"field_name": "sessionCampaignName",
    "string_filter": {"match_type": "BEGINS_WITH", "value": "premium_"}}},
  order_bys=[{"metric": {"metric_name": "sessions"}, "desc": true}],
  limit=50,
)
```

Report sessions + engagement rate + avg duration. High volume + low engagement = noisy placement (e.g. `premium_user_card` shows on every row). High engagement + decent volume = winners (e.g. `premium_sidebar_compact`, `premium_licences_addons`).

### Top pages

```
dimensions=["pagePath"], metrics=["screenPageViews", "activeUsers"]
order_bys=[{"metric": {"metric_name": "screenPageViews"}, "desc": true}]
```

### Traffic sources / channels

```
dimensions=["sessionDefaultChannelGroup"]  # or sessionSource / sessionMedium
metrics=["sessions", "engagedSessions", "engagementRate"]
```

### Period comparison

Pass two entries in `date_ranges` (the API returns a `dateRange` dimension automatically when there are 2+).

## Known noise — scrambled weekly-report scanner traffic

A recurring campaign `jffxyl-efcbeg` from source `jcbezva` (medium `email`) is **not** a real campaign. It's a near-ROT13 scramble of the plugin's own `weekly-report` / `wpadmin` UTMs (templates/email-summary-report.php), produced by email security scanners (Mimecast / Proofpoint / Defender Safe Links / etc.) prefetching links in the weekly summary email. Profile: all desktop Chrome, mixed countries, near-zero session duration, all landing on `/`. Volume is ~25× the real `weekly-report` campaign. Treat as bot noise — don't include in upsell/engagement analyses, and exclude with `sessionSource != "jcbezva"` if it skews a report.

## Date handling

-   Today's date is in the system context. "Last week" = previous Mon–Sun, not last 7 days. "Last 28 days" excludes today.
-   Always convert relative dates to absolute `YYYY-MM-DD` before calling the API.

## Output style

-   Markdown table sorted by the metric the user asked about.
-   Format engagement rate as a percentage and duration as `Xm Ys`.
-   After the table, call out 2–4 _interesting_ observations (winners, dead placements, surprises). Don't just restate numbers.
-   Note when sample size is too small to draw conclusions (under ~30 sessions per row).
