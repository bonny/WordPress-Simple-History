# Simple History – Track, Log, and Audit WordPress Changes

Contributors: eskapism, wpsimplehistory
Donate link: https://simple-history.com/sponsor/
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.7
Stable tag: 5.7.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Track changes and user activities on your WordPress site. See who created a page, uploaded an attachment, and more, for a complete audit trail.

## Description

> _"So far the best and most comprehensive logging plugin"_ - [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin acts as an audit log of the most important events that occur in WordPress.

It's a plugin that is good to have on websites where several people are involved in editing the content.

No coding is required to use the plugin. Just install it and it will start logging events.

### 🔍 How Simple History Helps in Real Situations

**Track what’s happening on your site**  
_"Has someone done anything today? Ah, Sarah uploaded the new press release and created an article for it. Great! Now I don't have to do that."_

**Identify issues and debug faster**  
_"The site feels slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughty-plugin-x', that must be it."_

**Keep Freelancers & Agencies Accountable**
_"I hired a developer to optimize my site. But did they actually do anything? A quick glance at Simple History shows me exactly what they worked on, so I know I’m getting my money’s worth!"_

### ✨ Simple History Features

Out of the box Simple History has support for:

-   **Posts and pages** – see who added, updated or deleted a post or page

-   **Attachments** – see who added, updated or deleted an attachment

-   **Taxonomies (Custom taxonomies, categories, tags)** – see who added, updated or deleted an taxonomy

-   **Comments** – see who edited, approved or removed a comment

-   **Widgets** – get info when someone adds, updates or removes a widget in a sidebar

-   **Plugins** – activation and deactivation

-   **User profiles** – info about added, updated or removed users

-   **User logins** – see when a user login & logout. Also see when a user fails to login (good way to catch brute-force login attempts).

-   **User edits** – see when a user is added, updated or removed, and get detailed information about the changes made to the user.

-   **Failed user logins** – see when someone has tried to log in, but failed. The log will then include ip address of the possible hacker.

-   **Menu edits**

-   **Option screens** – view details about changes made in the different settings sections of WordPress. Things like changes to the site title and the permalink structure will be logged.

-   **Privacy page** – when a privacy page is created or set to a new page.

-   **Data Export** – see when a privacy data export request is added and when this request is approved by the user, downloaded by an admin, or emailed to the user.

-   **User Data Erasure Requests** – see when a user privacy data export request is added and when this request is approved by the user and when the user data is removed.

#### Built-in logging for third-party plugins

Simple History comes with built in support for many plugins:

-   **Jetpack** – See which Jetpack modules are activated and deactivated.

-   **Advanced Custom Fields (ACF)** – See when field groups and fields are created and modified.

-   **User Switching** – See each user switch being made.

-   **WP Crontrol** – See when cron events are added, edited, deleted, paused, resumed, and manually ran, and when cron schedules are added and deleted.

-   **Enable Media Replace** – See details about the file being replaced and details about the new file.

-   **Limit Login Attempts** – See login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts.

-   **Redirection** – See redirects and groups that are created, changed, enabled or disabled and also when the global plugin settings have been modified.

-   **Duplicate Post** –See when a clone of a post or page is done.

-   **Beaver Builder** – See when a Beaver Builder layout or template is saved or when the settings for the plugins are saved.

Is your plugin missing? No problem – plugin authors can add support for Simple History in their plugins using the [logging API](https://simple-history.com/docs/logging-api/).

#### Plugins that have support for Simple History includes:

-   [Connections Business Directory](https://wordpress.org/plugins/connections/)
-   [Simple History Beaver Builder Add-On](https://wordpress.org/plugins/extended-simple-history-for-beaver-builder/)
-   [WP-Optimize – Cache, Clean, Compress.](https://wordpress.org/plugins/wp-optimize/)
-   [Add Customer for WooCommerce](https://wordpress.org/plugins/add-customer-for-woocommerce/)
-   [Better WishList API](https://wordpress.org/plugins/better-wlm-api/)
-   [AJAX Login and Registration modal popup + inline form](https://wordpress.org/plugins/ajax-login-and-registration-modal-popup/)
-   [Loginpetze](https://wordpress.org/plugins/loginpetze/)
-   [Authorizer](https://wordpress.org/plugins/authorizer/)
-   [Ad Inserter](https://wordpress.org/plugins/ad-inserter/)
-   [FV Player Pro](https://foliovision.com/player/features/sharing/video-downloading-with-simple-history#integration-with-simple-history)
-   [Login Me Now](https://wordpress.org/plugins/login-me-now/)

### 💬 What users say

🌟 [300+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5) speak to the reliability of this plugin. 🌟

-   _"The best history plugin I’ve found"_ – [Rich Mehta](https://wordpress.org/support/topic/the-best-history-plugin-ive-found/)

-   _"Custom Logs Are Crazy Awesome!"_ - [Ahmad Awais](https://wordpress.org/support/topic/awesome-4654/)

-   _"Amazing activity logging plugin"_ - [digidestination](https://wordpress.org/support/topic/amazing-activity-logging-plugin/)

-   _"Fantastic plugin I use on all sites"_ - [Duncan Michael-MacGregor](https://wordpress.org/support/topic/fantastic-plugin-i-use-on-all-sites/)

-   _"Useful Quick View of Activity"_ - [Dan O](https://wordpress.org/support/topic/useful-quick-view-of-activity/)

-   _"The best Activity Plugin"_ - [Rahim](https://wordpress.org/support/topic/the-best-activity-plugin/)

-   _"The best free history plugin ever"_ - [abazeed](https://wordpress.org/support/topic/the-best-free-history-plugin-ever/)

-   _"It is a standard plugin for all of our sites"_ - [Mr Tibbs](https://wordpress.org/support/topic/it-is-a-standard-plugin-for-all-of-our-sites/)

### 🚀 Zero-Config Activity Tracking

Simple History begins tracking your WordPress site's activities instantly after installation - no setup required. View your activity logs through the convenient dashboard widget or browse the full history on the dedicated 'Simple History' page in your WordPress admin.

### 📡 RSS feed with changes

Using the optional password protected **RSS feed** you can keep track of the changes made on your website using your favorite RSS reader.

### 🤖 WP-CLI commands for automation

[Multiple WP-CLI commands exist](https://simple-history.com/features/wp-cli-commands/), to view and search the log, and to view more information about a specific event.

WP-CLI support is perfect for system administrators and agencies managing multiple WordPress sites. Using WP-CLI commands they can automate log monitoring, create reports, or integrate with existing DevOps tools.

Example commands:

-   `wp simple-history event list` – List the latest logged events.
-   `wp simple-history event search` – Search for events.

### 🥷 Stealth Mode – Hide Simple History from the WordPress Admin

[Stealth Mode](https://simple-history.com/features/stealth-mode/) allows Simple History to run completely in the background, hidden from the WordPress admin interface. This is ideal for agencies, developers, and administrators who want to track user activity discreetly without exposing the logs to other users.

There are two modes:

-   **Full Stealth Mode** – Completely hides Simple History from everyone.
-   **Partial Stealth Mode** – Hides the plugin but allows selected users to access the logs.

The activity log remains accessible via the REST API, WP-CLI, and RSS feed, ensuring administrators can still retrieve logs when needed.

Stealth Mode is enabled using a constant or filter, allowing you to customize who can access the logs while keeping the plugin hidden in the admin interface.

### 📝 Add your own events to the log using the API

Theme and plugin developers can log custom events in Simple History using the `simple_history_log` filter:

`apply_filters(
  'simple_history_log',
  'This is a logged message'
);`

See the [documentation](https://simple-history.com/docs/) for examples on how to [log your own events](https://simple-history.com/docs/logging-api/), [query the log](https://simple-history.com/docs/query-api/), and more.

### 🔆 Extend with Add-ons

Take your activity logging to the next level with add-ons that enhance tracking, security, and customization.

#### [Simple History Premium](https://simple-history.com/add-ons/premium/)

Unlock advanced features and customization options:

-   **Log Retention** – Control how long logs are stored, from a few days to forever.
-   **Export Search Results** – Download logs in CSV or JSON format for deeper analysis.
-   **Failed User Logins** – Disable or limit logging of failed login attempts to reduce noise.
-   **IP Address Anonymization** – Choose whether to store full IPs or anonymize them for privacy compliance (e.g., GDPR).
-   **Login Location Lookup** – View the location of a specific login attempt on Google Maps to identify suspicious activity.
-   **Logger Control** – Enable or disable specific loggers to manage what type of events are recorded.
-   **Ad-Free Experience** – Remove promotional banners for a distraction-free workflow.

#### [WooCommerce Logger](https://simple-history.com/add-ons/woocommerce/?utm_source=wpadmin)

Track WooCommerce activity with detailed logs for:

✔️ Orders, refunds, and stock changes  
✔️ Product updates and pricing adjustments  
✔️ Settings modifications and coupon usage

#### [Debug and Monitor](https://simple-history.com/add-ons/debug-and-monitor/)

Gain deeper insights into your site’s background activity:

🛠️ Monitor outgoing requests and emails  
🔍 Debug HTTP API calls and server communication  
👨‍💻 Essential for developers, support teams, and anyone curious about what’s happening under the hood

### 💚 Sponsor this project

If you like this plugin please consider [donating to support the development of the free plugin](https://simple-history.com/donate). The plugin has been free for the last 10 years and will continue to be free.

## Frequently Asked Questions

### Is the plugin free?

Yes! Simple History has been free for over 10 years and will remain free. To support development and unlock extra features, you can purchase add-ons. [View add-ons](https://simple-history.com/add-ons/).

### How do I view the log?

You can access the log in multiple ways:

-   The **dashboard** widget
-   The **admin bar menu**
-   A **dedicated log page** in the WordPress admin area

### Do I need coding skills to use the plugin?

No! Just install and activate the plugin, and it will start collecting activity logs automatically.

### Where is the log stored?

The log is stored in your WordPress database.

### Can I export the log?

Yes, you can export logs in **CSV** or **JSON** format for further analysis.

### Is it compatible with other plugins?

Yes! Simple History supports many popular plugins out of the box. Additionally, developers can integrate it with any plugin using the [Logging API](https://simple-history.com/docs/logging-api/).

### Will this plugin slow down my website?

No, Simple History is lightweight and optimized for performance. Most logging occurs in the WordPress admin area when a WordPress user performs an action.

By default, nothing is logged on the front end, ensuring visitors experience no impact on performance.

### Who can view the log?

Access to the log depends on the user’s role:

-   **Administrators** can view all logged events.
-   **Editors** can see events related to posts and pages.

### Can I exclude certain users from being logged?

Yes, you can exclude users based on **role** or **email** using the [`simple_history/log/do_log`](https://simple-history.com/docs/hooks/) filter.

For more details, check the [hooks documentation](https://simple-history.com/docs/hooks/).

### How long is the history kept?

By default, logs are stored for **60 days**. You can change this in the settings.

You can also adjust the retention period using the [`simple_history/db_purge_days_interval`](https://simple-history.com/docs/hooks/#simplehistorydbpurgedaysinterval) filter or the [Extended Settings add-on](https://simple-history.com/add-ons/extended-settings?utm_source=wpadmin).

### Can I track changes made by specific users?

Yes! You can **filter logs by username**, making it easy to track individual activity.

### Is this plugin GDPR compliant?

GDPR compliance depends on **how you use the plugin** and how you handle collected data. WordPress guidelines prohibit plugins from making legal compliance claims, so you should review your site's data policies to ensure compliance.

That said, Simple History follows **privacy-friendly practices**:

-   ❌ No Google Fonts
-   ❌ No cookies
-   ❌ No local storage
-   ✅ IP addresses are anonymized by default

Since the plugin logs events (which may contain personal data), it’s **your responsibility** to ensure GDPR compliance based on your site's usage.

For more information, see the full [GDPR FAQ](https://simple-history.com/docs/faq-frequently-asked-questions/#is-the-plugin-GDPR-complient).

## Screenshots

1. The log view + it also shows the filter function in use - the log only shows event that
   are of type post and pages and media (i.e. images & other uploads), and only events
   initiated by a specific user.

2. The **Post Quick Diff** feature will make it quick and easy for a user of a site to see what updates other users have done to posts and pages.

3. When users are created or changed you can see details on what have changed.

4. Events have context with extra details - Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

5. Click on the IP address of an entry to view the location of for example a failed login attempt.

6. See even more details about a logged event (by clicking on the date and time of the event).

7. A chart with some quick statistics is available, so you can see the number of events that has been logged each day.
   A simple way to see any uncommon activity, for example an increased number of logins or similar.

## Changelog

✨ If you find Simple History useful ✨

-   [Sponsor the plugin to keep it free.](https://simple-history.com/sponsor/)
-   [Add a 5-star review so other users know it's good.](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5)
-   [Get the premium add-on for more features.](https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=readme).

### 5.7.0 (February 2025)

🔄 This release adds more menu location options and some other smaller improvements to the interface and internal code.
[Read the release post](https://simple-history.com/2025/simple-history-5-7-0-released/) for more details and screenshots.

**Added**

-   Add new menu location options "Inside dashboard menu item" and "Inside tools menu item" (in addition to the available "Top of main menu" and "Bottom of main menu").
    -   The "Inside dashboard menu item" option will add the main history log page to the Dashboard page, while the settings page for the plugin will be located under the Settings menu item. This is pretty much the same location as before the 5.5.0 update.
    -   The location can be set using filter `simple_history/admin_menu_location`.
-   Total number of events logged since install in now shown in the [Stats & Insights box](https://simple-history.com/features/stats-insights/).

**Changed**

-   Enhancement: Format number of events in Stats & Insights.
-   Update menu settings name from "Menu page location" to "History menu position".
-   Improve location of settings errors.
-   Improve logic for determine if the current admin page belongs to Simple History or not. Improves compatibility with translation plugins. [#531](https://github.com/bonny/WordPress-Simple-History/issues/531)

**Fixed**

-   Fix warning for [deprecated bottom styles in SelectControl component](https://make.wordpress.org/core/2024/10/18/editor-components-updates-in-wordpress-6-7/#bottom-margin-styles-are-deprecated).
-   Show correct [limit login attempts link](https://simple-history.com/add-ons/premium/#limit-failed-logins) for [premium](https://simple-history.com/add-ons/premium/) users.
-   Remove setting "Show history: as a page under the dashboard menu", since the history menu now can be set to multiple locations.

**Other**

-   Deprecate functions `register_settings_tab()`, `get_main_nav_html()`, `get_subnav_html()`, `get_settings_tabs()`.
-   Misc internal improvements and changes.

### 5.6.1 (January 2025)

🚀 This release fixes incomplete exports due to an error in pagination logic.
It also improves the post Quick Diff view by preventing scrollbar jumping on hover states.
A small but very nice improvement! [See the difference in the release post.](https://simple-history.com/2025/simple-history-5-6-1-released/)

**Fixed**

-   Incomplete exports due to error in pagination logic.
-   PHP notice when exporting events with missing user email data.

**Improved**

-   Enhance post Quick Diff view by preventing scrollbar jumping on hover states. [#530](https://github.com/bonny/WordPress-Simple-History/issues/530)

### 5.6.0 (January 2025)

🔝 This version adds an option to the settings page to control the location of the menu page (at top or bottom).
🫣 It also adds support for **Stealth Mode**: When enabled, Simple History will be hidden from places like the dashboard, the admin menu, the admin bar, and the plugin list.
👉 Read the [release post](https://simple-history.com/2025/simple-history-5-6-released-with-stealth-mode/) for more details and examples how to use this feature.

**Added**

-   Add support for **Stealth Mode**. When enabled (programmatically using a constant or filters) Simple History will be hidden from places like the dashboard, the admin menu, the admin bar, and the plugin list. [#401](https://github.com/bonny/WordPress-Simple-History/issues/401)
-   Add option to set menu page location to settings page. [#525](https://github.com/bonny/WordPress-Simple-History/issues/525)
-   Add WP-CLI command `simple-history stealth-mode status` to get status of Stealh Mode using WP-CLI.
-   Add filter `simple_history/show_admin_menu_page` to
-   Add filter `simple_history/admin_menu_location`.
-   Add filters `simple_history/show_in_admin_bar` and `simple_history/show_on_dashboard`, that work the same way as `simple_history_show_in_admin_bar` and `simple_history_show_dashboard_widget`, but with correct naming convention.

**Improved**

-   Decrease the icon size in the admin bar and main menu, to match the size of other icons. Props @hjalle.

**Fixed**

-   Fix for `simple_history/show_action_link` when being used and returning false then the other action links was not shown.

### 5.5.1 (January 2025)

-   Fix the redirect from old settings page to new settings page and from old event log page to new event log page not always working when there was for example a WordPress update notice.

### 5.5.0 (January 2025)

Simple History 5.5.0 contains an improved event log menu location, and more 💥.
Read the [release post](https://simple-history.com/2025/simple-history-5-5-0-released/) for more details.

**Added**

-   Added Simple History to the top level of the admin bar for improved accessibility and visibility. Previously, the plugin was located in the dashboard menu, the settings menu, and contained tools like export and debug scattered across sub-tabs. This change centralizes these tools, making them easier to find and use.
-   Introduced a link to the settings page for users with the Premium add-on, shown in the "events cleared" text. [#486](https://github.com/bonny/WordPress-Simple-History/issues/486)
-   Added slotfill `SimpleHistorySlotEventActionsMenu` to enable future extensions and customizations. [#499](https://github.com/bonny/WordPress-Simple-History/issues/499)

**Deprecated**

-   Deprecated the filter `simple_history/admin_location` since the event log page now includes sub-pages and cannot be moved.

**Changed**

-   Updated icons next to menu titles to improve visual clarity and consistency. [#517](https://github.com/bonny/WordPress-Simple-History/issues/517)

**Fixed**

-   Resolved an issue where premium info was displayed below the "clear log" button even when the Premium add-on was installed.
-   Various internal code enhancements and optimizations.

### 5.4.4 (January 2025)

First release of 2025! 🎉

-   Don't output CSS and JS for _Admin Bar Quick View_ if admin bar is not visible. [#510](https://github.com/bonny/WordPress-Simple-History/issues/510)
-   Only load events from the last 7 days in the _Admin Bar Quick View_.
-   Remove some unused CSS. [#505](https://github.com/bonny/WordPress-Simple-History/issues/505)
-   🎨 Fresh new logo for the plugin.
-   Style some admin boxes to match new design.
-   Misc other internal improvements.

[Changelog for previous versions.](https://github.com/bonny/WordPress-Simple-History/blob/main/CHANGELOG.md)
