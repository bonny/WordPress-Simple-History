# Simple History – Track, Log, and Audit WordPress Changes

Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.7
Stable tag: 5.3.0

Track changes and user activities on your WordPress site. See who created a page, uploaded an attachment, and more, for a complete audit trail.

## Description

> _"So far the best and most comprehensive logging plugin"_ - [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin works as a audit log of the most important events that occur in WordPress.

It's a plugin that is good to have on websites where several people are involved in editing the content.

No coding is required to use the plugin. Just install it and it will start logging events.

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

#### Build in logging for third party plugins

Simple History comes with built in support for many plugins:

-   **Jetpack** – See what Jetpack modules that are activated and deactivated.

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

### What users say 💬

🌟 [300+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5) speak to the reliability of this plugin. 🌟

-   _"So far the best and most comprehensive logging plugin"_ - [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)

-   _"The best history plugin I’ve found"_ – [Rich Mehta](https://wordpress.org/support/topic/the-best-history-plugin-ive-found/)

-   _"Custom Logs Are Crazy Awesome!"_ - [Ahmad Awais](https://wordpress.org/support/topic/awesome-4654/)

-   _"Amazing activity logging plugin"_ - [digidestination](https://wordpress.org/support/topic/amazing-activity-logging-plugin/)

-   _"Fantastic plugin I use on all sites"_ - [Duncan Michael-MacGregor](https://wordpress.org/support/topic/fantastic-plugin-i-use-on-all-sites/)

-   _"Useful Quick View of Activity"_ - [Dan O](https://wordpress.org/support/topic/useful-quick-view-of-activity/)

-   _"The best Activity Plugin"_ - [Rahim](https://wordpress.org/support/topic/the-best-activity-plugin/)

-   _"The best free history plugin ever"_ - [abazeed](https://wordpress.org/support/topic/the-best-free-history-plugin-ever/)

-   _"It is a standard plugin for all of our sites"_ - [Mr Tibbs](https://wordpress.org/support/topic/it-is-a-standard-plugin-for-all-of-our-sites/)

## Getting Started

After installation, Simple History automatically starts logging activities. Access the history log through the dashboard widget or via the 'Simple History' page in the dashboard menu.

### RSS feed with changes

Using the optional password protected **RSS feed** you can keep track of the changes made on your website using your favorite RSS reader.

### Comes with WP-CLI commands

For those of you who like to work with the command line there are also some WP-CLI commands available.

-   `wp simple-history list` – List the latest logged events.

### Example scenarios

Keep track of what other people are doing:
_"Has someone done anything today? Ah, Sarah uploaded
the new press release and created an article for it. Great! Now I don't have to do that."_

Or for debug purposes:
_"The site feels slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughty-plugin-x',
that must be it."_

### API so you can add your own events to the audit log

If you are a theme or plugin developer and would like to add your own things/events to Simple History you can do that by using the function `SimpleLogger()` like this:

```php
if ( function_exists("SimpleLogger") ) {
		// Most basic example: just add some information to the log
		SimpleLogger()->info("This is a message sent to the log");

		// A bit more advanced: log events with different severities
		SimpleLogger()->info("User admin edited page 'About our company'");
		SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
		SimpleLogger()->debug("Ok, cron job is running!");
}
?>
```

See the [documentation](https://simple-history.com/docs/) for examples on how to [log your own events](https://simple-history.com/docs/logging-api/) and how to [query the log](https://simple-history.com/docs/query-api/), and more.

### 🔆 Extend the plugin functionality with Add-ons

Powerful add-ons are available to extend the functionality of Simple History even further:

**[WooCommerce Logger](https://simple-history.com/add-ons/woocommerce/?utm_source=wpadmin)**  
Enhance your site's tracking with comprehensive logs for WooCommerce orders, products, settings, and coupons.

**[Extended Settings](https://simple-history.com/add-ons/extended-settings/)**  
Extend the settings of Simple History with more options and settings.

**[Developer tools](https://simple-history.com/add-ons/developer-tools/) (coming soon)**  
Log sent emails, HTTP API requests, cron jobs, and more.

### 💚 Sponsor this project

If you like this plugin please consider donating to support the development. The plugin has been free for the last 10 years and will continue to be free.

-   [Donate using PayPal](https://www.paypal.me/eskapism).
-   [Become a GitHub sponsor](https://github.com/sponsors/bonny).
-   [Send Bitcoin or Ethereum](https://simple-history.com/donate/).
-   [Add-ons that you can buy to support the development](https://simple-history.com/add-ons/) (and get some extra features!).

## Frequently Asked Questions

= Is the plugin free? =

Yes! It has been free for the last 10 years and will continue to be free. There are some add-ons that you can buy to support the development of this plugin and get some extra features. [View add-ons](https://simple-history.com/add-ons/).

= How do I view the log? =

You can view the log on the dashboard or on a separate page in the admin area.

= Can I see the log in the front end? =

No, the log is only available in the admin area.

= Do I need to have coding skills to use the plugin? =

No, you don't need to write any code to use the plugin.
Just install the plugin and it will start collecting data.

= Where is the log stored? =

The log is stored in the database used by WordPress.

= Can I export the log? =

Yes, you can export the log to a CSV or JSON file.

= Is my theme supported? =

Yes, the plugin works with all themes.

= Is my plugin supported? =

The plugin comes with built in support for many plugins and support for Simple History can be added to any plugin using the Logging API.

= Will my website slow down because of this plugin? =

No, the plugin is very lightweight and will not slow down your website.

= Who can view the log? =

How much information that is shown in the log depends on the user role of the user viewing the log. Admins can see everything, while editors can only see events related to posts and pages.

= Is it possible to exclude users from the log? =

Yes, you exclude users by role or email using the filter [`simple_history/log/do_log`](https://simple-history.com/docs/hooks/).

See the [hooks documentation](https://simple-history.com/docs/hooks/) for more info.

= For how long are the history kept? =

By default, logs are stored for 60 days. This duration can be adjusted in the settings.

This can be modified using the filter [`simple_history/db_purge_days_interval`](https://simple-history.com/docs/hooks/#simplehistorydbpurgedaysinterval) or using the [Simple History Extended Settings add-on](https://simple-history.com/add-ons/extended-settings?utm_source=wpadmin).

= Can I track changes made by specific users? =

Yes, Simple History allows you to filter the history by user names, making it easy to monitor individual activities.

= Is this plugin GDRP compliant? =

Since GDRP is such a complex topic and since [WordPress plugins are not allowed to imply that they provide legal compliance](https://make.wordpress.org/plugins/2018/04/12/legal-compliance-added-to-guidelines/) we can not simply just say that the plugin is GDPR compliant.

GDPR is very much about how you use the data and how you inform your users about what data you collect and how you use it. No site is the same and the usage together with the plugin can be very different from site to site. So you should always make sure that you are compliant with GDPR when using the plugin.

That said, the plugin does not use Google Fonts, does not set cookies, uses no local storage, and by default the ip addresses are anonymized. The plugin is however a plugin that logs events and that can contain personal data, so you should always make sure that you are compliant with GDPR when using the plugin.

Read more at the [FAQ on the plugin website](https://simple-history.com/docs/faq-frequently-asked-questions/#is-the-plugin-GDPR-complient).

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

✨ Do you use Simple History a lot?
[Then sponsor the plugin to keep it free](https://simple-history.com/sponsor/) or
[add a 5-star review so other users know it's good](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5).

### 5.3.0 (November 2024)

⏱️ This release includes a performance improvement and an enhancement that makes it easier for users in different time zones to understand when an event occurred.
[Read the release post for more info](https://simple-history.com/2024/simple-history-5-3-0-released/).

-   Changed the interval for checking new events from 5 seconds to 30 seconds. This reduces resource usage and is more server-friendly. [#489](https://github.com/bonny/WordPress-Simple-History/issues/489)
-   Event times are now displayed in the user's local time zone, as reported by the web browser, making it easier to understand when an event occurred for users in different time zones. [#488](https://github.com/bonny/WordPress-Simple-History/issues/488)
-   Enhanced the datetime tooltip to show more information about the event date and time, including accurate local and GMT values.
-   Renamed the date field in the REST API response to `date_local` to clarify that it represents the website's local date and time of the event.
-   Added the `date_gmt` field to the event context modal.

### 5.2.0 (November 2024)

Some minor bugfixes but also a new feature in this update. [Read the release post for more info](https://simple-history.com/2024/simple-history-5-2-0-released/).

-   Add counter with total number of events logged. The value of this can be seen on the debug page. [#483](https://github.com/bonny/WordPress-Simple-History/issues/483)
-   Add option with plugin install date. The install date can be seen on the debug page. [#483](https://github.com/bonny/WordPress-Simple-History/issues/483)
-   Fix notice `Function _load_textdomain_just_in_time was called incorrectly`.
-   Fix Quick View not being activated by default after enabling experimental features.
-   Hide WooCommerce Logger promo if [WooCommerce Logger](https://simple-history.com/add-ons/woocommerce/) is installed.

### 5.1.0 (November 2024)

This release contains some bugfixes 🐞 but also a new experimental Admin Bar Quick View feature. [See the release post for info and screenshots](https://simple-history.com/2024/simple-history-5-1-0-released-with-new-experimental-feature/).

**Added**

-   Add "Admin Bar Quick View" as experimental feature. This new feature adds a "History" link in the admin bar, that when hovered shows the latest events in a compact timeline format.
    This is very convenient when you quickly want to check the latest events without leaving the page you are on.
    (This feature is experimental and can be enabled on the settings page.) [#476](https://github.com/bonny/WordPress-Simple-History/issues/476)
-   Add helper function `get_settings_page_url()`.
-   Add helper function `sh_dd()`.

**Changed**

-   Tested on WordPress 6.7.

**Fixed**

-   Use selected WP admin theme colors for colors in links and buttons. [#463](https://github.com/bonny/WordPress-Simple-History/issues/463)
-   Add pagination buttons to first page and last page. [#479](https://github.com/bonny/WordPress-Simple-History/issues/479)
-   Add option to go enter page number to go to. [#479](https://github.com/bonny/WordPress-Simple-History/issues/479)
-   Fix username not always showing in the event details modal.

### 5.0.4 (October 2024)

-   Fix PHP warning when viewing events from anonymous users (for example logged failed logins). [#477](https://github.com/bonny/WordPress-Simple-History/issues/477)
-   Add tests for REST API endpoints.

### 5.0.3 (October 2024)

-   Fix for wrong version number in the readme.txt and index.php file, causing the plugin to find updates forever.

### 5.0.2 (October 2024)

**Added**

-   Add `occasions_id` to the context data modal.
-   Include `user_display_name` in events REST API response.
-   Autoload options `simple_history_detective_mode_enabled`, `simple_history_experimental_features_enabled`, and `simple_history_db_version` to improve performance. Related: [Options API: Disabling Autoload for Large Options](https://make.wordpress.org/core/2024/06/18/options-api-disabling-autoload-for-large-options/).

**Changed**

-   Better output of JSON data in event details view. [#464](https://github.com/bonny/WordPress-Simple-History/issues/464)

**Fixed**

-   Display user "display name", with fallback to "username", in the event feed. This restores how it was displayed in version 4 of the plugin. [#468](https://github.com/bonny/WordPress-Simple-History/issues/468)
-   Disable autoload of option `SimplePluginLogger_plugin_info_before_update`, to improve performance. [#457](https://github.com/bonny/WordPress-Simple-History/issues/457)
-   Fix PHP warnings when fetching occasions.
-   Only get edit link for a post if `get_post()` returns a post object. This _may_ fix issues with, for example, old versions of WPML. [#469](https://github.com/bonny/WordPress-Simple-History/issues/469)
-   Make more strings in the GUI translatable. [#470](https://github.com/bonny/WordPress-Simple-History/issues/470), [#471](https://github.com/bonny/WordPress-Simple-History/issues/471)

### 5.0.1 (September 2024)

A minor update to quickly fix an issue with avatars that affected a few people.

-   Fix: Correct default value used in `get_avatar_data()` when no user found for an event. Solves compatibility issues with [BuddyBoss](https://www.buddyboss.com/) and possible other similar plugins. [#461](https://github.com/bonny/WordPress-Simple-History/issues/461)

### 5.0.0 (September 2024)

A big update that keeps everything familiar. 🚀  
[See what’s changed under the hood.](https://simple-history.com/2024/simple-history-5/?utm_source=wpadmin)

**Changed**

-   **Event Feed Rewrite**: The event GUI has been entirely rewritten using [React](https://react.dev/) and [WordPress components](https://developer.wordpress.org/block-editor/reference-guides/components/).
-   **Auto-Refreshing Filters**: The event feed now updates automatically when filters are changed.
-   **IP Address Info Update**: IP address information popup now include the name of the server header where the IP was sourced.
-   **Quickstats Relocation**: The "quickstats" box has been repositioned to the top of the stats sidebar.
-   **WordPress 6.6 Minimum Requirement**: Simple History now requires [WordPress 6.6](https://wordpress.com/blog/2024/07/16/wordpress-6-6/).
-   **SecuPress Compatibility**: Changes to post types introduced by [SecuPress](https://wordpress.org/plugins/secupress/) will no longer be logged.

**Added**

-   **REST API Endpoints**: REST API endpoints to fetch event logs at `/simple-history/v1/events` and `/wp-json/simple-history/v1/events/<id>`.
-   **Action Menu for Events**: Each event now includes an actions menu, with options to view event details, copy permalinks, and soon more. (Plugins and add-ons can extend the menu with custom actions so keep your eyes opened for more actions in the future.)
-   **New Hooks for Developers**:
    -   `simple_history/history_page/gui_wrap_top`: Fired at the top of the history page GUI wrapper.
    -   `simple_history/dropin/stats/before_content`: Fired inside the stats sidebar, after the headline but before the content.
-   **Experimental Features**: New option on the setting page to enable experimental features.

**Removed**

-   **Settings Metabox**: The metabox linking to the settings page has been removed, as settings are now accessible from the top menu bar.
-   **Legacy Code Cleanup**: Removed several old and unused files, functions, and JavaScript hooks that are no longer relevant to the current implementation.

### 4.17.0 (August 2024)

🐞 This release contains some small bug fixes and enhancements. The [previous version](https://simple-history.com/2024/simple-history-4-16-0/?utm_source=wpadmin) had more cool new features so check out that one if you haven't already.

-   Tested on [WordPress 6.6](https://wordpress.org/news/2024/07/dorsey/).
-   Correct URL for "Go to Simple History" link on updated page on multisite.
-   Add `simple_history/log_query_inner_where_array` as a replacement for filter `simple_history/log_query_inner_where` that got removed in 4.9.0. The new filter is an array filter and can be used to add or modify the where clauses that the log query will use. See this [GitHub issue for some examples](https://github.com/bonny/WordPress-Simple-History/issues/455#issuecomment-2263206236).
-   Add link to Simple History below the "All updates have been completed" message for more cases (it was missing when translations was updated, for example).
-   Add filter `simple_history/show_action_link` that can be used to disable the link to the action that is shown in the log. This can be useful if you want to hide the link to the action for some users or in some cases. Example usage: `add_filter("simple_history/show_action_link", "__return_false");`.
-   Update Select2. [#456](https://github.com/bonny/WordPress-Simple-History/issues/456)

🌟 Pssst.... Don't forget that you can [sponsor this project to keep it free and open source](https://simple-history.com/sponsor/). And if you need more features you can buy [add-ons that also get you some extra features](https://simple-history.com/add-ons/). 🌟

### 4.16.0 (July 2024)

This release contains many new features and improvements. Especially updates made on the settings screen has gonne through a major overhaul and is now much more user friendly and informative.
[View the release post for screenshots and more information](https://simple-history.com/2024/simple-history-4-16-0/?utm_source=wpadmin).

**Added**

-   Debug page additions
    -   Display detected db engine to debug page. Can be useful for debugging since Simple History supports MySQL, MariaDB, and SQLite.
    -   Table size and number of rows for SQLite databases are shown on the debug page (they were already shown for MySQL and MariaDB).
    -   Display [Drop-ins](https://developer.wordpress.org/reference/functions/get_dropins/) on the debug page.
-   Throw exception if [log query](https://simple-history.com/docs/query-api/) has any db errors instead of just dying silently. This should help with debugging since the message often is visible in the log. [#438](https://github.com/bonny/WordPress-Simple-History/issues/438)
-   Plugin update failures are now logged, with error message added to context. This can happen when a plugin can't remove it's folder. [#345](https://github.com/bonny/WordPress-Simple-History/issues/345)
-   Support for the ANSI_QUOTES mode in MySQL/MariaDB. [#334](https://github.com/bonny/WordPress-Simple-History/issues/334)
-   RSS feed support for filtering by loglevels ( e.g.,`?loglevels=warning,notice`). See https://simple-history.com/docs/feeds/ for all available filters. [#443](https://github.com/bonny/WordPress-Simple-History/issues/443)
-   Log when an admin user changes the way WordPress handles auto updates of core, from "automatic updates for all new versions of WordPress" to "automatic updates for maintenance and security releases only", or vice versa. [#449](https://github.com/bonny/WordPress-Simple-History/issues/449)
-   Add Update URI plugin header, if available, to context for plugin installs or updates. This field was added in [WordPress 5.8](https://make.wordpress.org/core/2021/06/29/introducing-update-uri-plugin-header-in-wordpress-5-8/) so it was really time to add it now :) [#451](https://github.com/bonny/WordPress-Simple-History/issues/451)
-   Add link to the Simple History site history below the "All updates have been completed" message that is shown when plugins or themes are updated. [#453](https://github.com/bonny/WordPress-Simple-History/issues/453)
-   Add title, alternative text, caption, description, and slug to modified attachments. [#310](https://github.com/bonny/WordPress-Simple-History/issues/310)
-   Add a link next to number or failed login attempts. If the [extended settings add-on](https://simple-history.com/add-ons/extended-settings/) is installed the link goes to the settings page for that add-on. If that add-on is not installed the link goes to the website of the add-on.

**Changed**

-   Changes to settings screens and logging of their options have gotten a major overhaul and is now much more user friendly and informative:

    -   Only built in WordPress options are logged. Previously other options could "sneak in" when they was added using a filter or similar on the same screen.
    -   When updating the site language option (the options `WPLANG`), set "en_US" as the language when the option is empty. Previously it was set to an empty string which what a bit confusing.
    -   "Week Starts On" now displays the new and previous weekday as human readable text instead of a number.
    -   Use wording "Updated setting..." instead of "Updated option..." in the log when a setting is updated because it's more user friendly to say "setting" instead of "option", since that's the wordings used in the WordPress UI.
    -   Include the name of the settings page in the main log message for each setting updated and also include a link to the settings page.
    -   Use "On" or "Off" when display the changed values for settings that can be toggled on or off. Previously "1" or "0" was used.
    -   Setting "For each post in a feed, include..." now displays "Full text" or "Excerpt", instead of "1" or "0".
    -   The "blog_public" settings is now shown as "Discourage search engines from indexing this site" setting was changed.

-   Don't log the uploading and deletion of the ZIP archive when installing a plugin or theme from a ZIP file. [#301](https://github.com/bonny/WordPress-Simple-History/issues/301)
-   Update testing framework wp-browser to 3.5.
-   Misc refactoring and code cleanup.

**Fixed**

-   Fix a possible strpos()-warning in the ACF logger. [#440](https://github.com/bonny/WordPress-Simple-History/issues/440)
-   Ensure Post via email SMTP password is not exposed in the log.

Pssst! Did you know that you can [sponsor this project](https://github.com/sponsors/bonny) to keep it free and open source? 🌟

### 4.15.1 (April 2024)

This release contains a new feature that logs when scheduled blog posts or site pages automatically publish themselves at any time in the future. It also contains the regular bug fixes and improvements. [View the release post](https://simple-history.com/2024/simple-history-4-15-0/?utm_source=wpadmin).

**Added**

-   Log when post status changes from future to publish, i.e. when scheduled blog posts or site pages automatically publish themselves at any time in the future. [#343](https://github.com/bonny/WordPress-Simple-History/issues/343)

**Fixed**

-   Log theme file edits and plugin file edits again. [#437](https://github.com/bonny/WordPress-Simple-History/pull/437)
-   Show previous featured image when removing a featured image from a post. Before this change the fields was empty. So confusing.
-   Cleanup the edited post event output by remove context keys `post_author/user_login`, `post_author/user_email`, `post_author/display_name` from post edited events, because author change is already shown as plain text. The context keys are still available to see in the context data table.

**Updated**

-   Update WordPress Coding Standards to latest version. [#436](https://github.com/bonny/WordPress-Simple-History/issues/436)

### 4.15.0 (April 2024)

Was never released. Skipped to 4.15.1. Something went wrong with tagging.

### 4.14.0 (April 2024)

🕵️‍♀️ This version introduces a new Detective Mode. Many users use Simple History to catch changes made by users and plugins, but sometimes it can be hard to tell exactly what plugin that was responsible for a specific action. Detective Mode has been created to help users find the responsible plugin, hook, URL, or function used to trigger a specific action. [View screenshots and more information](https://simple-history.com/2024/simple-history-4-14-0-introducing-detective-mode/?utm_source=wpadmin).

-   Add [**Detective Mode**](https://simple-history.com/support/detective-mode/), a new feature aimed to help users find what plugin or theme is causing a specific event or action to be logged or happen. Great for debugging. This new feature can be enabled in the settings. [Read more](https://simple-history.com/2024/simple-history-4-14-0-introducing-detective-mode/?utm_source=wpadmin). Useful for admins, developers, forensics detectives, security experts, and more.
-   Add support for searching for localized logger message strings. [#277](https://github.com/bonny/WordPress-Simple-History/issues/277)
-   Add fix for SQL `MAX_JOIN_SIZE` related error message, that could happen on low end hosting providers or shared hosting providers. [#435](https://github.com/bonny/WordPress-Simple-History/issues/435)
-   Remove check for older PHP versions in `helpers::json_encode`. (PHP 7.4 is since long the minimum requirement for Simple History and for WordPress itself.)
-   Tested on WordPress 6.5.

### 4.13.0 (March 2024)

🚀 Introducing the WooCommerce Logger Add-On: Enhance your site's tracking with comprehensive logs for WooCommerce orders, products, settings, and coupons. Learn more in our [release post](https://simple-history.com/2024/woocommerce-logger-add-on-released/?utm_source=wpadmin).

-   Add support for logging when adding or removing user roles via WP-CLI. [WP-CLI 2.10.0 added "Support for adding and removing of multiple user roles"](https://make.wordpress.org/cli/2024/02/08/wp-cli-v2-10-0-release-notes/) and now Simple History supports logging of these events. [#431](https://github.com/bonny/WordPress-Simple-History/issues/431).

-   Show a message for users with WooCommerce installed and activated, informing them about the new WooCommerce Logger Add-On.

View the [release post to see screenshots of the new features](https://simple-history.com/2024/simple-history-4-13/).

[Changelog for previous versions.](CHANGELOG.md)
