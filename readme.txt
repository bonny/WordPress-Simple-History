# Simple History – Track, Log, and Audit WordPress Changes

Contributors: eskapism, wpsimplehistory
Donate link: https://simple-history.com/sponsor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=sponsorship&utm_content=readme_donate_link
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.9
Stable tag: 5.24.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Track changes and user activities on your WordPress site. See who created a page, uploaded an attachment, and more, for a complete audit trail.

## Description

Trusted by 300,000+ WordPress sites, rated 4.9 stars with [430+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5), actively developed for 10+ years, and translated into 15+ languages.

Simple History is the complete audit log for WordPress. It tracks every meaningful change — content edits, user logins, plugin updates, security events, and more — so site owners, teams, agencies, and developers always know who did what and when. Just install and activate; no configuration required.

### 🔍 How Simple History Helps in Real Situations

**Track what's happening on your site**
_"Has anyone done anything today? Ah, Sarah uploaded the new press release and created an article for it. Great — now I don't have to do that."_

**Identify issues and debug faster**
_"The site feels slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughty-plugin-x', that must be it."_

**Keep freelancers & agencies accountable**
_"I hired a developer to optimize my site. But did they actually do anything? A quick glance at Simple History shows me exactly what they worked on."_

**Spot suspicious activity early**
_"I see three failed logins from an unfamiliar IP address overnight. Let me click the IP to check all activity from that address — just those attempts, nothing else. Good to know."_

### ✨ What Simple History Tracks

#### Security & Monitoring

-   Failed user logins with IP tracking and filtering by type (wrong password vs. non-existent username)
-   Core file integrity checks against official checksums
-   Forced security auto-updates from WordPress.org
-   Site Health status changes
-   Admin page access denied events

#### Content & Users

-   Posts, pages, and custom post types — create, edit, delete, and homepage assignment
-   Attachments with image edit details (crop, rotate, flip, scale) and thumbnail previews
-   Taxonomies with detailed diffs of name, slug, description, and parent
-   Comments, menus (with item-level detail), and widgets
-   User profiles, logins, logouts, and role changes
-   Notes — the collaboration feature in WordPress 6.9

#### System & Updates

-   Plugin lifecycle: install, update, activate, deactivate, delete, and auto-update toggle
-   Theme install, update, activate, switch, and delete
-   WordPress core updates (manual and automatic)
-   Translation and language pack updates
-   Available update notifications
-   Settings and option screen changes

#### Privacy & Compliance

-   Privacy data export and user data erasure requests
-   Privacy page changes
-   IP addresses anonymized by default — no cookies, no external fonts

### 🔌 Built-in Third-Party Plugin Support

Simple History includes built-in logging for:

-   **Jetpack** – Module activations and deactivations
-   **Advanced Custom Fields (ACF)** – Field group and field changes
-   **User Switching** – User switch events
-   **WP Crontrol** – Cron event and schedule changes
-   **Enable Media Replace** – File replacement details
-   **Limit Login Attempts** – Login attempts, lockouts, and config changes
-   **Redirection** – Redirect and group changes, global settings
-   **Duplicate Post** – Post and page cloning
-   **Beaver Builder** – Layout, template, and settings saves

Is your plugin missing? Plugin authors can add support using the [logging API](https://simple-history.com/docs/logging-api/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_logging_api).

### 💬 What Users Say

[430+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5) on WordPress.org:

-   _"So far the best and most comprehensive logging plugin"_ – [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)
-   _"The best history plugin I've found"_ – [Rich Mehta](https://wordpress.org/support/topic/the-best-history-plugin-ive-found/)
-   _"Fantastic plugin I use on all sites"_ – [Duncan Michael-MacGregor](https://wordpress.org/support/topic/fantastic-plugin-i-use-on-all-sites/)
-   _"It is a standard plugin for all of our sites"_ – [Mr Tibbs](https://wordpress.org/support/topic/it-is-a-standard-plugin-for-all-of-our-sites/)

### 🚀 View Your Log Everywhere

Simple History starts tracking instantly after activation — no setup needed. It even imports recent activity so your log isn't empty on day one. Access your log from:

-   **Dashboard widget** – Activity stats summary and recent events
-   **Admin bar quick view** – Dropdown with latest events on any admin page
-   **"This page" frontend filter** – See only events related to the page you're viewing
-   **Command palette** – Type "Simple History" to jump to the log for the current post
-   **Dedicated admin page** – Full log with search, filters, and insights sidebar
-   **Email reports** – Weekly summary delivered to your inbox
-   **RSS feed** – Password-protected feed for your favorite reader
-   **WP-CLI** – Command-line access for automation and scripting
-   **REST API** – Programmatic access for custom integrations

### 📧 Weekly Email Reports – Stay Informed Without Logging In

[Weekly email reports](https://simple-history.com/features/email-reports-weekly/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_email_reports) deliver a summary of your site's activity every Monday morning — total activity, daily breakdown, key metrics (logins, content updates, plugin changes), and direct links to the full log.

Perfect for site owners, agencies managing client sites, and teams who need regular updates without logging in. Enable it in settings and [see what the email looks like](https://simple-history.com/features/email-reports-weekly/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_email_reports#example) before turning it on.

### 🛠️ For Developers & Power Users

-   **WP-CLI** – [List, search, and export events](https://simple-history.com/features/wp-cli-commands/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_wp_cli_commands) from the command line — perfect for automation and managing multiple sites
-   **REST API** – Full programmatic access to query the log and add custom events. See the [documentation](https://simple-history.com/docs/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_overview)
-   **Logging API** – [Log your own events](https://simple-history.com/docs/logging-api/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_logging_api) from themes and plugins with a single line of code
-   **RSS feed** – Subscribe to changes using any feed reader
-   **AI & agent-friendly** – The REST API and RSS feed make Simple History accessible to AI agents and automated workflows like Claude Code
-   **Stealth Mode** – Run Simple History completely hidden from the admin interface via code; [Premium](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_stealth_mode) adds a GUI. Ideal for agencies and client sites

### 🔆 Extend with Add-ons

#### [Simple History Premium](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium)

**Alerts & Notifications** – Get notified instantly via Email, Slack, Discord, or Telegram when important events occur. Start quickly with preset rules for common scenarios or build custom rules filtered by event type, user, role, and log level.

**Log Forwarding** – Stream events to external destinations: local log files, syslog servers (UDP/TCP/TLS), Datadog, Splunk, webhooks, or external MySQL/MariaDB databases. Perfect for centralized logging, compliance, and backup.

**Enhanced Controls** – Custom retention periods (or keep logs forever), CSV/JSON export of filtered search results, post activity panel in the block editor, custom log entries for team decisions, stealth mode GUI, logger control to fine-tune which events are recorded, and an ad-free experience.

#### [WooCommerce Logger](https://simple-history.com/add-ons/woocommerce/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=addons&utm_content=readme_addon_woocommerce)

Track WooCommerce activity: orders, refunds, stock changes, product updates, pricing adjustments, settings modifications, and coupon usage.

#### [Debug and Monitor](https://simple-history.com/add-ons/debug-and-monitor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=addons&utm_content=readme_addon_debug_monitor)

Monitor outgoing HTTP requests and emails, debug API calls, and see what's happening under the hood. Essential for developers and support teams.

### 💚 Sponsor this project

If you like this plugin please consider [sponsoring the development of the free plugin](https://simple-history.com/sponsor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=sponsorship&utm_content=readme_sponsor_footer). The plugin has been free for over 10 years and will continue to be free.

## Frequently Asked Questions

### Is the plugin free?

Yes! Simple History has been free for over 10 years and will remain free. To support development and unlock extra features, you can purchase the premium add-on. [View premium features](https://simple-history.com/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=addons&utm_content=readme_addons_overview).

### How do I view the log?

You can access the log in multiple ways:

-   The **dashboard** widget with activity stats summary
-   The **admin bar** quick view dropdown – on the frontend, use the "This page" toggle to see events for the current page
-   The **WordPress command palette** – type "Simple History" to jump to the log for the current post
-   A **dedicated log page** in the WordPress admin area

### Can I change where the History menu appears in WordPress admin?

Yes! You can customize the menu position in the plugin settings. Choose between showing Simple History at the top or bottom of the main menu, or inside the dashboard menu or tools menu.

### Do I need coding skills to use the plugin?

No! Just install and activate the plugin, and it will start collecting activity logs automatically.

### Where is the log stored?

The log is stored in your WordPress database.

### Can I export the log?

Yes, you can export logs in **CSV** or **JSON** format for further analysis.

### Is it compatible with other plugins?

Yes! Simple History supports many popular plugins out of the box. Additionally, developers can integrate it with any plugin using the [Logging API](https://simple-history.com/docs/logging-api/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_faq_api).

### Will this plugin slow down my website?

No, Simple History is lightweight and optimized for performance. Most logging occurs in the WordPress admin area when a WordPress user performs an action.

By default, nothing is logged on the front end, ensuring visitors experience no impact on performance.

### Who can view the log?

Access to the log depends on the user's role:

-   **Administrators** can view all logged events.
-   **Editors** can see events related to posts and pages.

### Can I exclude certain users from being logged?

Yes, you can exclude users based on **role** or **email** using the [`simple_history/log/do_log`](https://simple-history.com/docs/hooks/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_hooks#simplehistorylogdolog) filter.

For more details, check the [hooks documentation](https://simple-history.com/docs/hooks/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_hooks#simplehistorylogdolog).

### How long is the history kept?

By default, logs are stored for **60 days**.

Upgrade to [Simple History Premium](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium) to change this using a GUI.

### Can I track changes made by specific users?

Yes! You can **filter logs by username**, making it easy to track individual activity.

### Is this plugin GDPR compliant?

GDPR compliance depends on **how you use the plugin** and how you handle collected data. WordPress guidelines prohibit plugins from making legal compliance claims, so you should review your site's data policies to ensure compliance.

That said, Simple History follows **privacy-friendly practices**:

-   ❌ No Google Fonts
-   ❌ No cookies
-   ❌ No local storage
-   ✅ IP addresses are anonymized by default

Since the plugin logs events (which may contain personal data), it's **your responsibility** to ensure GDPR compliance based on your site's usage.

For more information, see our support page [GDPR and Privacy: How Your Data is Stored in Simple History](https://simple-history.com/support/gdpr-and-privacy/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_gdpr_support).

## Screenshots

1. The log view + it also shows the filter function in use - the log only shows event that
   are of type post and pages and media (i.e. images & other uploads), and only events
   initiated by a specific user.

2. The feature will make it quick and easy for a user of a site to see what updates other users have done to posts and pages.

3. When users are created or changed you can see details on what have changed.

4. Events have context with extra details - Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

5. Click on the IP address of an entry to view the location of for example a failed login attempt.

6. See even more details about a logged event (by clicking on the date and time of the event).

7. A chart with some quick statistics is available, so you can see the number of events that has been logged each day.
   A simple way to see any uncommon activity, for example an increased number of logins or similar.

8. Stats and summaries page that gives you a quick overview summary of your site's activity.

9. Email reports: Get a weekly summary of your site's activity delivered straight to your inbox. Enable and configure this feature in the plugin settings.

## Changelog

✨ If you find Simple History useful ✨

-   [Sponsor the plugin to keep it free.](https://simple-history.com/sponsor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=sponsorship&utm_content=readme_sponsor_footer)
-   [Add a 5-star review so other users know it's good.](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5)
-   [Get the premium add-on for more features.](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium)

### 5.24.0 (March 2026)

A redesigned dashboard widget that takes up less space, user details card on click, and much better logging of menus, categories, and image edits.
[Read more about it in the release post](https://simple-history.com/2026/simple-history-5-24-0-released/)

**Added**

-   User card on avatar and name click, showing name, role, and email with a link to the user profile. The Premium add-on extends the card with login history and recent activity.
-   "Copy as image" action in the event menu that captures an event as a shareable image, ready to paste into Slack, social media, or bug reports.
-   Site Health Logger that tracks WordPress Site Health test status changes, logging when issues are detected, resolved, or change severity.
-   Menu change logging now shows item names, types, renames, moves, order changes, and display location updates instead of just item counts.
-   Parent category changes and diff details (name, slug, description, parent) when viewing edited category and tag events.
-   Logging when a page is set as the homepage or posts page from the block editor, including the name of the previously assigned page.
-   Image edit logging (crop, rotate, flip, scale) in the media logger, including a thumbnail preview.
-   Command palette command to view event history for the current post or page.
-   "Event metadata" search field in the advanced filters for searching all event data including IP addresses and emails.
-   "Clear filters" button to reset all search filters to their default values.
-   Rotating tips in the sidebar to help users discover features like RSS feeds, WP-CLI, export, and sticky events.
-   User creation and profile update counts in the email digest report, displayed alongside login statistics in the Users section.
-   REST API `skip_count_query` parameter to skip the total count query when pagination info is not needed, improving response time for clients that don't require total counts.
-   Multisite uninstall support, removing tables, options, and cron events across all subsites in the network.
-   Compact storage for post content changes (used for creating a diff between the old and new content), reducing database size for large posts (experimental).
-   Failed login throttling to protect the database from brute-force attacks — logs the first 100 failed attempts, then automatically skips the rest. Includes an informational notice on both the main event log and the dashboard widget (experimental).

**Changed**

-   WP-CLI `--user` argument renamed to `--userid` and `--exclude_user` to `--exclude_userid` to avoid conflict with WP-CLI's global `--user` argument, which caused warnings on newer WP-CLI versions. [#629](https://github.com/bonny/WordPress-Simple-History/issues/629)
-   Dashboard widget redesigned with an activity stats summary showing event counts for today and last 7 days, and a more compact event list. Loads significantly faster by limiting queries to the last 7 days and skipping the total count query.
-   Search now only searches the visible event message text by default, making results more relevant and dramatically faster on sites with large activity logs. Previously, search also scanned all hidden metadata which was slow and returned unexpected matches (experimental).
-   Multi-word search now matches each word independently across all searchable fields. For example, "api request 400" now finds events where "api" and "request" appear in the message text and "400" appears in event metadata, instead of requiring all words to exist in the same field (experimental).
-   "Show filters" / "Hide filters" toggle replaces "Show search options" / "Collapse search options".
-   Action links (Edit, View, Preview, Revisions) now appear below post events.
-   IP address popover redesigned with prominent IP display, AS number links, map service links (Google Maps and OpenStreetMap), and subnet filtering.
-   Core file integrity restored log entry now shows how many files are still modified.
-   Auto backfill runs on the first admin page load instead of WP-Cron, ensuring it works in more environments.
-   Admin bar JavaScript reduced by removing the wp-components dependency, saving ~919 KB on every page load.
-   Object caching added to stats queries, preventing duplicate database queries within the same request.

**Fixed**

-   False-positive core file integrity warnings on localized WordPress installs (e.g. sv_SE) caused by hardcoded en_US checksums.
-   Term names showing backslash before apostrophes when editing categories and tags.
-   Incomplete option cleanup on plugin uninstall, leaving orphaned options in the database.
-   Three scheduled cron events not cleared during uninstall (database purge, core file integrity check, log file cleanup).
-   Missing icon for "Other" initiator type.
-   Manual backfill memory error on sites with many users, now processed in batches.

### 5.23.1 (February 2026)

**Fixed**

-   Added backward-compatibility stubs for PHP classes 5.21–5.23, hopefully preventing crashes when updating from those versions. 🤞

### 5.23.0 (February 2026)

**Added**

-   Detection of forced security updates from WordPress.org; shown as "Update method: Security auto-update" in plugin update details.
-   Upgrade notices from WordPress.org API in plugin update details.
-   Search labels on 11 loggers (Beaver Builder, Duplicate Post, Enable Media Replace, Jetpack, Limit Login Attempts, Redirection, User Switching, WP Crontrol, Privacy, Simple History, Translations) for better filtering in alert rules.
-   Granular failed-login filters: "Failed login (wrong password)" for known users and "Failed login (unknown user)" for non-existent usernames, alongside the existing "Failed user logins" option.
-   User role (`_user_role`) in event context for debugging and used by alerts to be able to add rules for specific user roles.
-   Notes feature stats (WordPress 6.9+):
    -   Statistics in weekly email reports (notes added and resolved).
    -   Statistics on History Insights for block editor notes activity.
    -   REST API at `/wp-json/simple-history/v1/stats/notes`.
-   Alerts settings page with premium notification teasers (presets and custom rules in [Premium](https://simple-history.com/add-ons/premium/?utm_source=worg)).

**Changed**

-   Updated some logger messages to use active voice: e.g. "Was denied access" → "Attempted to access restricted", "was auto-disabled" → "Auto-disabled", "Was locked out because" → "Locked out after", "was updated" → "Updated".
-   Debug tab merged into Help & Support; System Information sits directly under support links.
-   Status bar on Help & Support showing plugin version, event count, and retention at a glance.
-   System Information extended with PHP Max Input Vars, WP Memory Limit, Child Theme, Theme Author, and User Agent for support debugging.
-   Log level for forced security plugin updates is changed from "info" to "notice", so auto-updates stand out.
-   Disable autoload for Available Updates Logger options, so they are only loaded when needed.
-   Sub-navigation tabs scroll horizontally on narrow screens instead of wrapping.
-   Plugin loading no longer scans the filesystem at startup; loggers and extensions are registered via static class lists for faster, more reliable init.
-   Sidebar stats and database purge queries rewritten to use the date index (faster on large tables).
-   Log_Query now has a `skip_count_query` option to omit the total row count when pagination metadata is not needed.
-   RSS feed now defaults to last 7 days and skips the count query for better performance. It also has a `dates` parameter for date filtering (e.g. `&dates=lastdays:30`).

**Fixed**

-   Infinite loop when the [Debug & Monitor add-on](https://simple-history.com/add-ons/debug-and-monitor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_debug_monitor) logged HTTP requests from channels (Webhook, Datadog, Splunk).

### 5.22.0 (December 2025)

**Added**

-   Added exclusion filter support to RSS and JSON feeds, allowing you to subscribe to events while excluding specific users, loggers, messages, or log levels. Useful for monitoring what others do without seeing your own actions.

**Fixed**

-   Simplified internal file structure to hopefully fix "Class File_Channel not found" fatal error that some users experienced when updating the plugin.
-   Fixed slow appearance of "Stick event to top" and "Unstick event" menu items in the event actions dropdown.

### 5.21.0 (December 2025)

🔍 Debug like a pro with the new "Surrounding Events" feature — see what happened before and after any event. Plus: Log Forwarding (Beta) lets you send events to external log files, Syslog servers, or external databases for backup and compliance. Also improved: auto-recovery for missing database tables.
[Read more about it in the release post](https://simple-history.com/2025/simple-history-5-21-0-released/)

**Added**

-   "Show surrounding events" feature to view events chronologically before and after a specific event, useful for debugging to see what happened around a particular event. Available via the event actions menu (administrators only), REST API, and WP-CLI. [#610](https://github.com/bonny/WordPress-Simple-History/issues/610).
-   Log Forwarding feature to send events to external destinations for backup, compliance, and security purposes. Includes File Channel for writing events to local log files with automatic rotation. [Premium add-on](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_log_forwarding) adds Syslog and External Database channels. [#573](https://github.com/bonny/WordPress-Simple-History/issues/573).
-   `simple_history/purge_db_where` filter for custom event retention rules, allowing per-logger retention periods or keeping certain events forever. [See documentation](https://simple-history.com/docs/hooks/#simplehistorypurgedbwhere).
-   `simple_history/db/purge_done` action that fires once when purge completes, with total deleted count.
-   `Helpers::count_events()` function for counting events in the database.

**Fixed**

-   Database tables not being created when using the plugin as MU plugin, after site duplication (where options are copied but custom tables are not), or during multisite network activation. The plugin now auto-recovers by recreating missing tables when needed. [#606](https://github.com/bonny/WordPress-Simple-History/issues/606).
-   IP addresses not showing when expanding grouped similar events.
-   Debug page showing "No tables found" when using SQLite (e.g., wp-playground) due to missing dbstat extension.

### 5.20.0 (December 2025)

🚀 Ready for WordPress 6.9 — this release logs the new Notes feature so you can track when notes are added or removed. Also new: automatic backfilling on first install so your log isn't empty, a "Hide my own events" checkbox, and a "Yesterday" date filter.
[Read more about it in the release post](https://simple-history.com/2025/simple-history-5-20-0-released/)

**Changed**

-   Improved code quality by resolving phpcs warnings for WordPress VIP Go compatibility.

**Fixed**

-   Fixed Notes Logger causing error in some cases when deleting comments.
-   Fixed event context being silently dropped when post content contained emojis, causing incomplete log entries. (This is a long standing issue that has been around for a while, but now it's finally fixed 🤞.) [#607](https://github.com/bonny/WordPress-Simple-History/issues/607).

### 5.19.0 (November 2025)

🚀 Ready for WordPress 6.9 — this release logs the new Notes feature so you can track when notes are added or removed. Also new: automatic backfilling on first install so your log isn't empty, a "Hide my own events" checkbox, and a "Yesterday" date filter.
[Read more about it in the release post](https://simple-history.com/2025/simple-history-5-19-0-released/)

**Added**

-   Add automatic backfilling of existing events on first install, so the activity log isn't empty when you start using the plugin for the first time.
-   Add logging of new [notes feature in WordPress 6.9](https://make.wordpress.org/core/2025/11/15/notes-feature-in-wordpress-6-9/). [#599](https://github.com/bonny/WordPress-Simple-History/issues/599).
-   Add "Yesterday" option to the date filter dropdown for quick access to previous day's events.
-   Add "Hide my own events" checkbox to filters, allowing users to quickly exclude their own activity from the log. [#604](https://github.com/bonny/WordPress-Simple-History/issues/604).
-   Add WordPress VIP Go coding standards for enterprise compatibility.
-   Add rollback context to plugin update failed events.
-   Add logging of failed theme updates.
-   Add support for negative filters in the event log query API, to the REST API, and to WP-CLI. [#86](https://github.com/bonny/WordPress-Simple-History/issues/86).
-   Add error message when trying to view an event that does not exist.
-   Add filter `simple_history/show_promo_boxes` to determine if promo boxes should be shown.
-   Add developer mode badge to the page header.
-   Add new Tools tab with manual backfill option for importing historical events on demand.

**Changed**

-   Rename "Export" menu to "Export & Tools" and add tabbed interface to support additional tools.
-   Post creation events now capture initial post content, excerpt, and status transitions to provide complete audit trail without information gaps.
-   Stop polling for new events after 10+ new events are detected to reduce server resource consumption from inactive browser tabs.
-   Improved auto-backfill completion message to be more user-friendly and include the number of days imported.
-   Improved welcome message text for clarity and better Premium feature promotion.
-   Admin Bar Quick View: Display count of similar events (occasions) on a new line below the main event message and style it.
-   Insights sidebar: Clicking on users now also filters the log by the last 30 days.
-   Insights sidebar: Update text to show current events in database and total events logged with links to settings page for retention period.
-   Insights sidebar: Improve messages for message count.
-   Decrease font size on stats sidebar stats box to fit more events.
-   Reduce number of HTTP requests by consolidating the small sidebar CSS file (just 4 rules) into the main stylesheet that's already being loaded on the page.
-   Hide sidebar donation box, support box, and review box when promo boxes are hidden for a cleaner interface with the premium add-on.
-   Internal code and UI refinements.
-   Tested up to WordPress 6.9.

**Fixed**

-   Fixed post creation via Gutenberg autosave not being logged.
-   Fixed incorrect timezone handling for imported user registration dates.
-   Fixed sidebar stats box styling conflict with premium add-on.
-   Fixed warning about invalid HTML nesting in the log GUI filters. [#548](https://github.com/bonny/WordPress-Simple-History/issues/548).

**Removed**

-   Remove donation box from sidebar.

### 5.18.0 (November 2025)

👆 This release makes sidebar stats interactive - click on avatars, user names, or chart dates to instantly filter your event log. It also fixes email reports always showing Sunday as the busiest day, plus several bug fixes and improvements.
[Read more about it in the release post](https://simple-history.com/2025/simple-history-5-18-0-released/)

**Added**

-   Context search to the log GUI filters.
-   Date support to create event REST API endpoint (allows creating events for specific dates).
-   User names to list of most active users in last 30 days (previously only showed avatars).
-   Clickable elements in sidebar stats box: avatars, user names, and chart dates now filter the event log when clicked.
-   Dedicated Experimental Features admin page for users with experimental features enabled.
-   New experimental feature: Import existing data from WordPress into Simple History (posts, pages, users, and attachments).

**Fixed**

-   Email reports always showing Sunday as the busiest day.
-   PHP warning "Trying to access array offset on value of type null" in Theme Logger when displaying widget events.
-   More timezone and localization issues.

**Changed**

-   Weekly email reports now sent earlier in the day (6 AM instead of 8 AM), so they are ready when the user wakes up.
-   Reordered sidebar stats: Most active users now appears before Daily activity to group admin-only information together.
-   Misc internal code improvements and changes.

### 5.17.0 (October 2025)

This version focuses on stats alignment and accuracy, timezone handling fixes, email report improvements, and performance optimizations.

Read more about it in the [release post](https://simple-history.com/2025/simple-history-5-17-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_17_0).

**Added**

-   Add icon to sticky events label.
-   Add help text to sidebar stats box about refresh interval and what data is used for the statistics (for admin the stats are based on all events, for other users is based on the events they have permission to view).
-   Email reports: Add tooltips to email "Activity by day" showing full date (e.g., "Thursday 2 October 2025") on hover for each day.
-   Email reports: Each day is now a link to the full log for that day.

**Fixed**

-   Sidebar stats was not always using the correct cached data.
-   Fix timezone and date handling across all stats features (sidebar, Insights page, REST API, charts) and all filter dropdowns (Today, Last N days, custom date ranges, month filters) to use WordPress timezone instead of server/UTC timezone.
-   "Today" now correctly shows events from 00:00 until current time (previously showed events from now minus 24 hours).
-   Email reports: Fix timezone and date handling issues (now consistently use WordPress timezone), improved daily stats accuracy, date range, and updated email copy.
-   Occasions count in main GUI was displaying incorrect number (always one event to many!) - button now shows the actual number of similar events that will be loaded when expanded.

**Changed**

-   Email preview now shows last 7 days including today (matching sidebar "7 days" stat) so users can verify preview numbers against sidebar.
-   Email sent on Mondays now shows previous complete Monday-Sunday week (excludes current Monday).
-   Email "Activity by day" now displays days in chronological order matching the date range instead of fixed calendar week order.
-   Use "Today" instead of "Last day" in main GUI filters to make it more clear what range is being shown.

**Performance**

-   Improved performance by loading logger messages only when needed, eliminating ~980 gettext filter calls on every page load. This reduces overhead to zero on pages that don't use Simple History.
-   Optimized context handling when logging events with many context items using batch inserts.
-   Plugin Logger now only runs gettext filters and auto-update detection on the plugins.php page instead of globally.
-   Simplified plugin action list hooks by only hooking into our plugin.
-   Added autoloading of deprecated classes, so they are only loaded if needed.

**Removed**

-   Removed legacy AJAX API endpoint (`action=simple_history_api`). The plugin now uses the WordPress REST API exclusively.
