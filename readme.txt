# Simple History – Track, Log, and Audit WordPress Changes

Contributors: eskapism, wpsimplehistory
Donate link: https://simple-history.com/sponsor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=sponsorship&utm_content=readme_donate_link
Tags: history, audit log, event log, user tracking, activity
Tested up to: 7.1
Stable tag: 5.30.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Track changes and user activities on your WordPress site. See who created a page, uploaded an attachment, and more, for a complete audit trail.

## Description

Trusted by 300,000+ WordPress sites, rated 4.9 stars with [450+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5), actively developed for 10+ years, and translated into 15+ languages.

Simple History is the complete audit log for WordPress. It tracks every meaningful change — content edits, user logins, plugin updates, security events, and more — so site owners, teams, agencies, and developers always know who did what and when. Just install and activate; no configuration required.

Every event is written to be read: plain language like _Updated page "About us"_, relative timestamps such as "5 minutes ago", and before/after comparisons instead of raw data dumps.

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
-   WordPress AI plugin activity is logged without ever storing API keys or prompt content

### 🔌 Built-in Third-Party Plugin Support

Simple History includes built-in logging for:

-   **WordPress AI plugin** – Feature toggles, AI provider and model changes, and connector approval requests, grants, and revocations
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

[450+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5) on WordPress.org:

-   _"So far the best and most comprehensive logging plugin"_ – [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)
-   _"The best history plugin I've found"_ – [Rich Mehta](https://wordpress.org/support/topic/the-best-history-plugin-ive-found/)
-   _"Fantastic plugin I use on all sites"_ – [Duncan Michael-MacGregor](https://wordpress.org/support/topic/fantastic-plugin-i-use-on-all-sites/)
-   _"It is a standard plugin for all of our sites"_ – [Mr Tibbs](https://wordpress.org/support/topic/it-is-a-standard-plugin-for-all-of-our-sites/)

### 🚀 View Your Log Everywhere

Simple History starts tracking instantly after activation — no setup needed. It even imports recent activity so your log isn't empty on day one. Access your log from:

-   **Dashboard widget** – Activity stats summary and recent events
-   **Admin bar quick view** – Dropdown with latest events on any admin page
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
-   A **dedicated log page** in the WordPress admin area
-   The **admin bar** quick view dropdown on
-   The **WordPress command palette** – type "Simple History" to jump to the log for the current post

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

### Does Simple History log the WordPress AI plugin?

Yes. When the official WordPress AI plugin is active, Simple History logs when AI features are turned on or off, when a feature's AI provider or model changes, and when plugins or themes request, are granted, or lose access to an AI provider on the Connector Approvals screen.

API keys and AI prompt or response content are never logged — those stay in the AI plugin's own settings.

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

1. The main event log: a clear timeline of who did what on your site, when, and from where — alongside a sidebar with daily activity and your most active users.

2. Content changes show a full before/after diff, so you can see exactly which words were edited on a post or page — not just that something changed.

3. User events capture every change to a profile: first and last name, display name, website, role, and more — with the previous value preserved next to the new one.

4. Every plugin install, activation, and deactivation is logged with author, version, source, and a link to the plugin — so you always know what's running on your site.

5. Click any IP address to see where it came from — hostname, organisation, city, and country — then filter every event from that IP or subnet in one click. Ideal for investigating failed logins.

6. Open any event to see the full details Simple History stores behind it: post IDs, user IDs, before/after values, and every other field — the complete audit trail for each entry.

7. History Insights shows a chart of daily activity, event counts for today, this week, and this month, and your most active users — all next to the log.

8. Stats and Summaries is a full reporting dashboard: breakdowns of users, posts and pages, plugins, media, and more — for any date range you choose.

9. Dashboard widget: a compact view of recent activity right on your WordPress Dashboard, so you see what's happened on your site without leaving the page you already check every day.

10. Weekly email reports keep you informed without logging in. Pick who receives the digest, preview it, or send a test email — all from the settings page.

11. The weekly digest itself: a clean summary of posts, users, logins, plugin changes, and more — delivered straight to your inbox.

## Changelog

✨ If you find Simple History useful ✨

-   [Sponsor the plugin to keep it free.](https://simple-history.com/sponsor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=sponsorship&utm_content=readme_sponsor_footer)
-   [Add a 5-star review so other users know it's good.](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5)
-   [Get the premium add-on for more features.](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium)

> Experimental entries are gated behind the experimental features setting (Settings → Simple History → Experimental). Enable it to try them, then share feedback so we know what to ship for everyone.

### Unreleased

**Added**

-   Site Editor changes are now logged: templates, template parts, site-wide styles, patterns, navigation menus and fonts, including changes made outside the block editor. Resetting a template to the theme default is logged as a reset, not a deletion.
-   Support for the official WordPress AI plugin: Simple History now logs when AI features are enabled or disabled, when a feature's AI provider or model is changed, and when plugins or themes request, are granted, or lose access to AI providers on the Connector Approvals screen. API keys and AI prompt content are never stored in the log.
-   `--format=json` and `--format=yaml` on `wp simple-history info`, so a deploy or CI script can check that Premium is active and licensed.
-   Experimental — Activity log is now available to AI tools and automation through the WordPress Abilities API (WordPress 6.9+). Read-only — nothing exposed can change or delete log entries.

**Changed**

-   Tested on WordPress 7.1.
-   Theme update events now name the version the theme went from and to, the way plugin update events already did.
-   Experimental — Role events no longer list every capability in the details panel when there are more than 10; the count stays in the event message and the full list in the event context.

**Fixed**

-   "Deleted user" events showed a blank id, email and login instead of the details of the removed user.
-   Personal data export requests were logged whatever their status, not only when newly requested.
-   `wp simple-history info` never showed the license line on sites with Premium active.
-   Event counts are now grouped for your locale — "187 304 events" rather than "187304 events" — in the log header, the stats bar, pagination and grouped-event counts.
-   Backfill notice showed a stray `&nbsp;` in its item counts on locales that separate thousands with a space.
-   Welcome notice shown after install no longer appears on the history page it links to, so its "Take a look" link always goes somewhere.
-   Log now shows the real reason it failed to load instead of "Unknown error" — on most sites every error detail was being discarded before it reached the screen.
-   Database errors while loading the log now name the problem, so you can act on it or pass it to your host.

**Security**

-   Comment content is escaped before it reaches the event details panel, so a comment can no longer put markup into the log.
-   RSS feed no longer breaks when logged content contains the `]]>` character sequence, which anyone able to leave a comment could trigger.
-   Colour values from the theme customizer are validated before being drawn as a swatch, so a theme with a permissive colour setting cannot inject CSS into the log.
-   CSV exports treat tab and carriage return as formula triggers, alongside the `=`, `+`, `-` and `@` already covered.
-   Additional escaping and input validation across the options, theme and media loggers.
-   Referring URL stored with every event now has secret-looking query string values masked, the way Detective Mode already masked the URLs it stores.
-   Masking now also covers session, bearer, credentials and private key field names.

### 5.30.0 (August 2026)

👍 Two experimental features graduate in this release: **event reactions** and the **header status bar**, which shows the status of your current settings at a glance — how long history is kept, whether email reports and alerts are on, and where logs are forwarded. This release also includes a round of security hardening and some miscellaneous fixes.
[Read more about all changes in the release post](https://simple-history.com/2026/simple-history-5-30-0-released/)

**Added**

-   "Plugin info" action link on plugin update-available events, so you can quickly check what an unfamiliar plugin is without leaving the log.
-   "Find events from the same IP address" in an event's actions menu, alongside the existing user and event-type filters.
-   Changes to more Simple History settings are now logged: Email Reports, the Experimental features toggle, and add-on license keys (key values are never stored in the log). (And yes – it was a bit funny that the plugin that logs changes to other plugins didn't log its own settings changes!)
-   WP-CLI: `--metadata_search` and `--ai_only` options on `wp simple-history list`, matching the metadata search and AI filter in the GUI.
-   WP-CLI: AI attribution columns (`ai_agent`, `ai_detected_via`, `ai_application`) on `wp simple-history list`, showing which AI tool made a change and how it was detected.
-   Header now shows "Stealth mode: on" while stealth mode is hiding Simple History from other users, including other administrators.

**Changed**

-   Reactions graduated from experimental and are now on by default — react to events with a 👍 (disable in Settings → General). Premium adds ❤️ 🎉 🚀 and more reaction types.
-   Header settings/info bar is graduated from experimental and now shows for all admins — a glance at how long history is kept, whether email reports and alerts are on, and where logs are forwarded, with each one linking straight to its setting.
-   Checkbox settings now show as On/Off (instead of 1/0) in the "Modified settings" log details.
-   Settings changes are now detected across all save mechanisms (Settings API, direct option updates, and REST) and recorded as a single event.
-   Large or structured settings are now logged as "changed" without storing their full value, keeping the log readable.
-   Developers: `simple_history/user_can_clear_log` now defaults to whether the user can manage settings, instead of always allowing it. The "Clear log" button is unaffected for administrators.
-   Exporting the log as HTML is faster on sites with large activity logs.

**Deprecated**

-   WP-CLI: `wp simple-history event search` — use `wp simple-history event list --search=<term>` instead. The old command still works but will be removed in a future version.

**Fixed**

-   WP-CLI: `wp simple-history event search` always returned zero results.
-   WP-CLI: `--fields` on `wp simple-history list` ignored column names written with a space after the comma.
-   PHP 8 fatal error when a setting was changed by a request without a referrer, such as from the REST API or WP-CLI. [#649](https://github.com/bonny/WordPress-Simple-History/pull/649)
-   Untranslatable strings in the statistics view and the weekly email report. [#672](https://github.com/bonny/WordPress-Simple-History/pull/672)
-   Invalid date or month filter values now return a clear error (HTTP 400 in the REST API, a friendly message in WP-CLI) instead of a server error.
-   RSS feed no longer breaks when its address contains a date filter it can't read — for example an older feed URL saved in a feed reader. It now returns an empty feed instead of an error.
-   Removed an unnecessary database query on every admin page load (a leftover from the one-time history backfill check).
-   Dashboard widget now shows an error message with details when the log can't be loaded (for example when the REST API is blocked), instead of loading placeholders forever.
-   Fatal error on WordPress 6.3 when saving a post that creates a revision.
-   Post update events now link to the revision they created. (The link had been missing since the feature was added in 5.16.0!)
-   PHP warning when logging a comment whose post has been deleted. Such events now read "a comment to (deleted)" instead of showing an empty title.
-   "Filter events: This IP" in the IP address popover did nothing when used from the dashboard widget — it now opens the event log filtered to that address.
-   Filtering by IP address now finds events by any address recorded for them, not just the one the web server saw. On sites behind a proxy or load balancer the visitor's real address is read from a forwarding header, and filtering by it previously returned nothing.
-   Experimental — Failed XML-RPC logins no longer create a duplicate "failed application password" entry alongside the regular failed-login entry.

**Security**

-   Looking up a person's username, email address and roles from the user card now follows WordPress's own rule and requires permission to list users. Who performed an event is still shown to everyone who can read that event.
-   REST API endpoints now require the same permission as opening the history page.
-   Detective Mode masks more field names — passwords, tokens, secrets and card numbers — and now also covers nested values, query strings and command line arguments.
-   Clearing the log, exporting it and regenerating the RSS feed address now also require permission to manage settings.
-   Event text escaping is now consistent across the media, categories, user and comments loggers, and in exported HTML files.

### 5.29.0 (June 2026)

🔒 This release brings Simple History together with WordPress's built-in privacy tools: a person's activity log is now included in personal-data exports (Tools → Export Personal Data), and a new "Privacy & Data" settings tab explains how it works. Plus: overview action links across user, plugin, post, and media events, and action links on core update and privacy events for quicker navigation.
[Read more about all changes in the release post](https://simple-history.com/2026/simple-history-5-29-0-released/)

**Added**

-   Overview action links ("All users", "All plugins", "All posts", "All media") on user, plugin, post, and media events.
-   "About this version" and "WordPress X.Y release notes" links on core update events for major-version bumps.
-   Action links on privacy events linking to the matching WordPress tool page (Tools → Export / Erase Personal Data, Settings → Privacy).
-   Activity log is now included in WordPress's personal-data export (Tools → Export Personal Data).
-   New "Privacy & Data" settings tab (Settings → Simple History) explaining how Simple History works with WordPress's personal-data tools.
-   Experimental — Exports also include activity about a person performed by others, with other people's names and emails redacted.
-   Experimental — Running a WordPress personal-data erasure (Tools → Erase Personal Data) anonymizes the person's data in matching log entries while keeping the entries as audit records.

**Changed**

-   Action link labels dropped the "View" prefix ("View plugin info" → "Plugin info").
-   External action links now show an "open in new tab" icon and open in a new tab.
-   Dashboard widget action links are now more compact, so the event message stays the visual anchor.
-   License reminder for missing add-on license keys moved from a full-width banner to a dismissible card in the History Insights sidebar.
-   Experimental — Role and capability events show a count ("Added 40 capabilities to role Editor") instead of dumping every capability slug into the headline; the full list stays in the event details.

**Fixed**

-   Alt-text changes to media made via direct meta updates are now logged.
-   Removed custom fields on post updates are now counted in the event details.
-   The UTC publish date no longer appears as a duplicate row in post update details.

### 5.28.0 (May 2026)

Ready for [WordPress 7.0](https://make.wordpress.org/core/7-0/)! This version is tested and confirmed working on the latest WordPress version. It also adds logging for the new [AI Connectors Screen](https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/). Plus: WP-CLI and REST API coverage for content and settings changes. And the usual round of UI improvements and bug fixes.
[Read more about all changes in the release post](https://simple-history.com/2026/simple-history-5-28-0-released/)

**Added**

-   WordPress 7.0 AI Connectors screen changes are now logged.
-   Built-in WordPress settings changed via the REST API (`POST /wp/v2/settings`) or WP-CLI (`wp option update`) are now logged. Previously the Options Logger only captured changes made through Settings → General/Writing/Reading/Discussion/Media/Permalinks, so automation, scripts, and AI agents could change the site tagline, title, default category, permalinks, and similar settings invisibly.
-   Post, user, media, menu, widget, and privacy page changes made via WP-CLI or the REST API are now logged. Previously these loggers only captured changes from inside wp-admin, so commands like `wp post create`, `wp post update`, `wp user update`, `wp menu item add`, and REST-driven edits from external tools or AI agents were not recorded.
-   Post update events now expose status, publish date, comment status, author, and page template as structured data in the REST API, "Copy as JSON", and "Copy as Markdown" outputs — previously these fields were only available as prerendered HTML, so external clients had to parse the markup.
-   Action link on Options Logger events for quick navigation back to the Settings page where the option lives.
-   "How are AI agents detected?" link in the AI agent attribution tooltip, pointing to a [docs article that explains the detection signals](https://simple-history.com/docs/ai-agent-detection/).
-   System Information page, `wp simple-history db stats`, and the `/wp-json/simple-history/v1/support-info` REST endpoint now report the charset and collation of each Simple History table — useful when diagnosing emoji-related context-drop issues.
-   Reminder card on Simple History pages when an add-on is installed without a license key entered, so users notice that updates won't arrive until the key is added. Links directly to the license entry field.

**Changed**

-   `wp simple-history info` now shows "Experimental features: enabled" when experimental features are active.
-   Options Logger event details show the change inline as a single row (new value → strike-through old value) labeled with the setting name (e.g. "Site Title", "Tagline"), instead of stacked "New value" / "Old value" rows.
-   Admin display for post update status, publish date, comment status, author, and page template switches from a stacked table row ("Changed from draft to publish") to an inline pill style ("Status: draft → publish"), matching how user profile changes already render. Title, content, custom field, term, and featured-image diffs still render in the existing table layout.

**Fixed**

-   "Copy as JSON" and "Copy as Markdown" now include the full event context (request URI, method, user agent, error codes, etc.), making copied payloads self-contained for triage and bug reports.
-   IP addresses are now included in failed application password authentication events, matching how wp-login failures already worked.
-   New installs create history tables as `utf8mb4` (using `$wpdb->get_charset_collate()`), so emoji and other 4-byte UTF-8 characters in events are preserved.
-   Support info page no longer prints a "no such table: dbstat" database error when `WP_DEBUG` is on and SQLite's optional `dbstat` virtual table isn't available (notably on WordPress Playground).
-   "Most active users" widget no longer shows nameless entries for users without a display name.
-   Redirect loops in wp-admin for low-privilege users. A legacy-URL redirect intended only for the old `/wp-admin/index.php?page=simple_history_page` bookmark was also firing for unrelated access-denied events on the dashboard, which could send users in circles. [#639](https://github.com/bonny/WordPress-Simple-History/issues/639)
-   Experimental — Brute-force attempts against `xmlrpc.php` now show which account is being targeted instead of logging an empty username.

### 5.27.0 (May 2026)

🤖 This release adds AI agent attribution to log events, so you can see when an action was triggered through Claude Code, ChatGPT, or other AI tools. Also, Action links are now front-and-center for media, plugins, users, menus, and failed plugin installs.
[Read more about all changes in the release post](https://simple-history.com/2026/simple-history-5-27-0-released/)

**Added**

-   Plugin active/inactive status is now recorded when plugins are updated, shown in event details when the plugin was inactive at update time.
-   Success confirmation and automatic log refresh after manually adding a log entry.
-   Action links for media attachments (Edit, View), plugins ("View changelog"), user profiles ("Edit user"), menu edits ("Edit menu" and "Manage menu locations".
-   "Show error message" action link on plugin install/update failure events — opens the event details modal where the underlying error message and diagnostic context are shown.
-   `wp simple-history info` WP-CLI command — prints the installed version, premium add-on status, and a list of useful subcommands.
-   New opt-in columns for `wp simple-history list` via `--fields=`: `date_relative` ("5 minutes ago" style timestamps), `site` (blog name and host, useful when comparing output across installs), and `ai_agent` (detected AI tool name when an event was initiated through an AI agent).
-   AI agent attribution on event log rows: when an event is triggered by an AI tool (Claude Code, ChatGPT, MCP clients, the Abilities API, etc.), a sparkle icon and the agent name appear next to the user who initiated the event. The signed-in user remains the actual initiator — this is additional audit context, not an authentication signal.
-   "AI-initiated events only" filter in the expanded filters panel — quickly narrow the log to actions triggered via AI tools.
-   New "Copy as JSON" menu item for each event, that copies the full event payload — including all context data — for scripting and debugging.
-   Experimental — "History" column on post and page list tables showing recent activity at a glance, with "View history" row action links.
-   Experimental — Failed application password authentication on REST API and XML-RPC requests is now logged as a warning, with the attempted user, error code and message, request URI, request method, and user agent. Closes a visibility gap where wrong app password attempts left no trace in the log, while wp-login failures already did. Can also be toggled directly via the new `simple_history/log_failed_app_password_auth` filter.

**Changed**

-   Event details for 12 loggers are now more consistent across the UI and structured in the REST API (migrated from manual HTML output to the Event Details API).
-   Navigational links in comment and plugin events (e.g. "Edit comment", "View plugin info") moved from event details to the action links bar for better discoverability.
-   Date filter dropdown reorganized: "All dates" moved to the top as the reset option, presets grouped under "Recent" (Today through Last 60 days, plus "Custom range…"), and specific months grouped under "By month" — easier to scan and matches how users think about date ranges.
-   "Copy detailed event message" action menu item renamed to "Copy as Markdown" with a richer Markdown layout (heading + properties table + structured details + context table) suitable for pasting into a ticket, Slack, or notes app. The Details section reflects what the event row shows (e.g. plugin description / version / author for plugin install events).
-   Stats page "Events overview" chart and sidebar "History Insights" daily activity chart switched from line charts to bar charts, with today highlighted in a contrasting accent color for at-a-glance recency.

**Security**

-   Event reaction endpoints now enforce per-event read permissions to prevent logged in users to be able to read events they shouldn't have access to. Reactions are experimental and off by default. Many thanks to Ly Hoang at Wordfence for responsibly disclosing this vulnerability.
-   Password reset request events no longer store the full reset email body, which contained the activation URL. User, email, and origin are still logged.
-   Removed the `simple_history/comments_logger/log_failed_password` and `simple_history/comments_logger/log_not_existing_user_password` filters, which could log plaintext passwords from failed logins. Both defaulted to off.

**Fixed**

-   Retention upsell message showing "deleted in 0 days" when event deletion is imminent. Now shows "scheduled for deletion" instead.
-   Menu logger flagging unrelated items as "Renamed" on every menu save. Items with HTML in their label, and items inheriting their label from a linked page, are no longer reported as renamed when nothing was actually changed.
-   Menu logger not surfacing renames of the menu itself — the previous and new menu name are now shown in the event details when the "Menu Name" field is changed.

### 5.26.0 (April 2026)

This version makes the log actions more discoverable by moving them out of the dropdown menu and into inline buttons. It also contains a new experimental feature: reactions!

[Read more about it in the release post](https://simple-history.com/2026/simple-history-5-26-0-released/)

**Added**

-   Media, Comments, and Themes sections to the weekly email summary report. Comments section only appears when comments are enabled on the site.
-   `--fields` support for `wp simple-history list` WP-CLI command, including a `reactions` field showing reaction counts.
-   Experimental — Event reactions: react to log events with a thumbs up emoji, with a Slack-style emoji picker in the actions bar.

**Changed**

-   Control bar actions are now inline buttons instead of a dropdown menu, making Export, Create Alert, Create Log Entry, and Share View more visible and accessible.
-   Expanded filters panel: reordered filters with Users first, moved "Hide my own events" into the Users row, replaced initiators help link with an icon, and trimmed helper text for a cleaner layout.

**Fixed**

-   Memory exhaustion when exporting large event logs by reducing batch size and eliminating redundant database queries.
-   Layout shift in control bar action buttons while search options are loading.
-   Oversized file type icon for non-image attachments (e.g. DOCX, PDF) in the event log.

### 5.25.0 (March 2026)

This release focuses on keeping your database lean. Three features that reduce log storage size are now active for all users: smarter default retention for new installs, failed login rate limiting, and compact diff storage for post content changes.
[Read more about it in the release post](https://simple-history.com/2026/simple-history-5-25-0-released/)

**Added**

-   Failed login rate limiting is now active for all users, capping logging at 100 consecutive failed attempts to prevent database bloat from brute force attacks.
-   Compact diff storage for post content changes is now active for all users, storing only a compact diff instead of full old+new content (up to 99% smaller for typical edits) with automatic fallback when the diff would be larger.
-   Search is now faster and more accurate for all users: queries skip occasion grouping for speed and only search relevant context keys from registered loggers instead of scanning all metadata. Previously this was an experimental opt-in feature. Use the "Event metadata" search field in the advanced filters to search all metadata (similar to the old behavior).
-   Hover-reveal quick action button on event rows for faster access to event details.
-   List of current experimental features shown near the enable toggle in settings.
-   "/" keyboard shortcut to focus the search input, with a visual hint badge. Pressing Escape returns focus to the previously focused element.
-   Settings and Premium/Get Premium buttons in the top-right header, replacing the Add-ons link.
-   Email Reports settings moved to their own sub-tab under Settings for better discoverability.
-   New installs default to 30-day retention (existing installs keep 60 days), keeping your database lean from day one.
-   Experimental — Feature discovery bar in the page header showing active features and settings status with dot indicators. Each item links directly to its settings section for quick access.

**Changed**

-   Search and filters redesigned into a single compact row with search input, date selector, and action buttons — replacing the previous multi-line layout.
-   Expanded filters panel now stacks labels above inputs on smaller screens for better usability.
-   History Insights sidebar: today's data point is now highlighted with a visible dot and the end date shows "(today)" for clarity.
-   History Insights sidebar: reduced y-axis clutter on the activity chart for a cleaner look.
-   History Insights sidebar: database stats section is now visually separated as footer content with cache freshness info moved into the tooltip.

**Fixed**

-   Dashboard widget corners not matching the new rounded style in WordPress 7.0.
-   PHP notice on the widget editor screen (widgets.php) caused by the command palette script loading `wp-editor` on non-post-editor screens.
-   Occasion counts in the RSS feed were always zero and never rendered.
-   Inverted condition in the GitHub plugin info handler that caused it to always fail.
-   "No matching events" empty state text and icon too light to meet WCAG AA contrast requirements.
-   Deprecation notice when using Yoast Duplicate Post 4.6, which replaced the `dp_duplicate_post` and `dp_duplicate_page` hooks with `duplicate_post_after_duplicated`.

**Security**

-   Nonce verification added to the GitHub plugin info AJAX handler to prevent CSRF.

### 5.24.1 (March 2026)

**Security**

-   RSS feed error response no longer exposes the feed secret token in the self-referencing link.

**Changed**

-   Capabilities added to roles are now logged at "notice" level instead of "warning" to reduce unnecessary alarm during routine plugin activations.

**Fixed**

-   Role Capability Logger no longer spams the log when plugins (e.g. Astra/Spectra) toggle capabilities on every page load. Changes are now batched per request and only net differences are logged.

**Added**

-   User ID displayed as an inline suffix on the name in the user card popover, making it easier to identify users when debugging.

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
-   Role & Capability Logger that tracks when roles are created, deleted, or have their capabilities modified, including which plugin triggered the change (experimental).

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

See [CHANGELOG.md](https://github.com/bonny/WordPress-Simple-History/blob/main/CHANGELOG.md) for the full changelog, including all releases from 2025 and earlier.
