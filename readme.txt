# Simple History – user activity log, audit tool

Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, log, changes, changelog, audit, audit log, event log, user tracking, trail, pages, attachments, users, dashboard, admin, syslog, feed, activity, stream, audit trail, brute-force
Tested up to: 6.4
Stable tag: 4.11.0

View changes made by users within WordPress. See who created a page, uploaded an attachment or approved an comment, and more.

## Description

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin works as a log/history/audit log/version history of the most important events that occur in WordPress.

It's a plugin that is good to have on websites where several people are involved in editing the content.

Out of the box Simple History has support for:

- **Posts and pages** – see who added, updated or deleted a post or page

- **Attachments** – see who added, updated or deleted an attachment

- **Taxonomies (Custom taxonomies, categories, tags)** – see who added, updated or deleted an taxonomy

- **Comments** – see who edited, approved or removed a comment

- **Widgets** – get info when someone adds, updates or removes a widget in a sidebar

- **Plugins** – activation and deactivation

- **User profiles** – info about added, updated or removed users

- **User logins** – see when a user login & logout. Also see when a user fails to login (good way to catch brute-force login attempts).

- **User edits** – see when a user is added, updated or removed, and get detailed information about the changes made to the user.

- **Failed user logins** – see when someone has tried to log in, but failed. The log will then include ip address of the possible hacker.

- **Menu edits**

- **Option screens** – view details about changes made in the different settings sections of WordPress. Things like changes to the site title and the permalink structure will be logged.

- **Privacy page** – when a privacy page is created or set to a new page.

- **Data Export** – see when a privacy data export request is added and when this request is approved by the user, downloaded by an admin, or emailed to the user.

- **User Data Erasure Requests** – see when a user privacy data export request is added and when this request is approved by the user and when the user data is removed.

### Support for third party plugins

By default Simple History comes with built in support for the following plugins:

- **Jetpack** – The [Jetpack plugin](https://wordpress.org/plugins/jetpack/) is a plugin from Automattic (the company behind the WordPress.com service) that lets you supercharge your website by adding a lot of extra functions.
  In Simple History you will see what Jetpack modules that are activated and deactivated.

- **Advanced Custom Fields (ACF)** – [ACF](https://www.advancedcustomfields.com/) adds fields to your posts and pages.
  Simple History will log changes made to the field groups and the fields inside field groups. Your will see when both field groups and fields are created and modified.

- **User Switching** – The [User Switching plugin](https://wordpress.org/plugins/user-switching/) allows you to quickly swap between user accounts in WordPress at the click of a button.
  Simple History will log each user switch being made.

- **WP Crontrol** – The [WP Crontrol plugin](https://wordpress.org/plugins/wp-crontrol/) enables you to view and control what's happening in the WP-Cron system.
  Simple History will log when cron events are added, edited, deleted, paused, resumed, and manually ran, and when cron schedules are added and deleted.

- **Enable Media Replace** – The [Enable Media Replace plugin](https://wordpress.org/plugins/enable-media-replace/) allows you to replace a file in your media library by uploading a new file in its place.
  Simple history will log details about the file being replaced and details about the new file.

- **Limit Login Attempts** – The plugin [Limit Login Attempts](https://wordpress.org/plugins/limit-login-attempts/) is old
  and has not been updated for 4 years. However it still has +1 million installs, so many users will benefit from
  Simple History logging login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts.

- **Redirection** – The [redirection plugin](https://wordpress.org/plugins/redirection/) manages url redirections, using a nice GUI. Simple History will log redirects and groups that are created, changed, enabled or disabled and also when the global plugin settings have been modified.

- **Duplicate Post** – The plugin [Duplicate Post](https://wordpress.org/plugins/duplicate-post/) allows users to clone posts of any type. Simple History will log when a clone of a post or page is done.

- **Beaver Builder** – The plugin [Beaver Build](https://wordpress.org/plugins/beaver-builder-lite-version/) is a page builder for WordPress that adds a flexible drag and drop page builder to the front end of your WordPress website. Simple History will log when a Beaver Builder layout or template is saved or when the settings for the plugins are saved.

Plugin authors can add support for Simple History in their plugins using the [logging API](https://simple-history.com/docs/logging-api/). Plugins that have support for Simple History includes:

- [Connections Business Directory](https://wordpress.org/plugins/connections/)
- [Simple History Beaver Builder Add-On](https://wordpress.org/plugins/extended-simple-history-for-beaver-builder/)
- [WP-Optimize – Cache, Clean, Compress.](https://wordpress.org/plugins/wp-optimize/)
- [Add Customer for WooCommerce](https://wordpress.org/plugins/add-customer-for-woocommerce/)
- [Better WishList API](https://wordpress.org/plugins/better-wlm-api/)
- [AJAX Login and Registration modal popup + inline form](https://wordpress.org/plugins/ajax-login-and-registration-modal-popup/)
- [Loginpetze](https://wordpress.org/plugins/loginpetze/)
- [Authorizer](https://wordpress.org/plugins/authorizer/)

### What users say

[300+ five-star reviews](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5) speak to the reliability of this plugin.

- _"So far the best and most comprehensive logging plugin"_ - [@herrschuessler](https://wordpress.org/support/topic/so-far-the-best-and-most-comprehensive-logging-plugin/)

- _"The best history plugin I’ve found"_ – [Rich Mehta](https://wordpress.org/support/topic/the-best-history-plugin-ive-found/)

- _"Custom Logs Are Crazy Awesome!"_ - [Ahmad Awais](https://wordpress.org/support/topic/awesome-4654/)

- _"Amazing activity logging plugin"_ - [digidestination](https://wordpress.org/support/topic/amazing-activity-logging-plugin/)

- _"Fantastic plugin I use on all sites"_ - [Duncan Michael-MacGregor](https://wordpress.org/support/topic/fantastic-plugin-i-use-on-all-sites/)

- _"Useful Quick View of Activity"_ - [Dan O](https://wordpress.org/support/topic/useful-quick-view-of-activity/)

- _"The best Activity Plugin"_ - [Rahim](https://wordpress.org/support/topic/the-best-activity-plugin/)

- _"The best free history plugin ever"_ - [abazeed](https://wordpress.org/support/topic/the-best-free-history-plugin-ever/)

- _"It is a standard plugin for all of our sites"_ - [Mr Tibbs](https://wordpress.org/support/topic/it-is-a-standard-plugin-for-all-of-our-sites/)

### RSS feed with changes

Using the optional password protected **RSS feed** you can keep track of the changes made on your website using your favorite RSS reader.

### Comes with WP-CLI commands

For those of you who like to work with the command line there are also some WP-CLI commands available.

- `wp simple-history list` – List the latest logged events.

### Example scenarios

Keep track of what other people are doing:
_"Has someone done anything today? Ah, Sarah uploaded
the new press release and created an article for it. Great! Now I don't have to do that."_

Or for debug purposes:
_"The site feels slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughty-plugin-x',
that must be it."_

### API so you can add your own events to the audit log

If you are a theme or plugin developer and would like to add your own things/events to Simple History you can do that by using the function `SimpleLogger()` like this:

`

<?php

if ( function_exists("SimpleLogger") ) {

		// Most basic example: just add some information to the log
		SimpleLogger()->info("This is a message sent to the log");

		// A bit more advanced: log events with different severities
		SimpleLogger()->info("User admin edited page 'About our company'");
		SimpleLogger()->warning("User 'Jessie' deleted user 'Kim'");
		SimpleLogger()->debug("Ok, cron job is running!");

}
?>

`

See the [documentation](https://simple-history.com/docs/) for examples on how to [log your own events](https://simple-history.com/docs/logging-api/) and how to [query the log](https://simple-history.com/docs/query-api/), and more.

### Translations/Languages

So far Simple History is translated to 17 languages: Chinese (China), Danish, Dutch, Dutch (Belgium), English (US), French (France), German, German (Switzerland), Korean, Polish, Portuguese (Brazil), Russian, Spanish (Colombia), Spanish (Mexico), Spanish (Spain), Spanish (Venezuela), and Swedish.

If you want to translate Simple History to your language then read about how this is done over at the [Polyglots handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/#translating-themes-plugins).

### Contribute at GitHub

Development of this plugin takes place at GitHub. Please join in with feature requests, bug reports, or even pull requests!
<a href="https://github.com/bonny/WordPress-Simple-History">https://github.com/bonny/WordPress-Simple-History</a>

### Sponsor this project

If you like this plugin please consider donating to support the development.

You can [donate using PayPal](https://www.paypal.me/eskapism) or you can [become a GitHub sponsor](https://github.com/sponsors/bonny).

There is also some [add-ons](https://simple-history.com/add-ons/) that you can buy to support the development of this plugin and get some extra features.

## Frequently Asked Questions

= Can I add my own events to the log? =

Yes. See the [Developer Documentation](https://simple-history.com/docs/).

= Is it possible to exclude users from the log? =

Yes, you exclude users by role or email using the filter [`simple_history/log/do_log`](https://simple-history.com/docs/hooks/).

See the [hooks documentation](https://simple-history.com/docs/hooks/) for more info.

= For how long are events stored? =

Events in the log are stored for 60 days by default. Events older than this will be removed.

This can be modified using the filter [`simple_history/db_purge_days_interval`](https://simple-history.com/docs/hooks/#simplehistorydbpurgedaysinterval) or using the [Simple History Extended Settings add-on](https://simple-history.com/add-ons/extended-settings?utm_source=wpadmin).

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

### 4.11.0 (february 2024)

This version introduces improved user role support and enhanced export functionality. For more details and screenshots, check out the [release post](https://simple-history.com/2024/simple-history-4-11-0/).

**Added**

- Improved support for detecting and displaying changes to user role(s), including showing the adding and removal of multiple roles. This improvement is tested with the [Member](https://wordpress.org/plugins/members/) plugin and the [Multiple Roles](https://wordpress.org/plugins/multiple-roles/) plugin. [#424](https://github.com/bonny/WordPress-Simple-History/issues/424).
- Column with user role(s) are included in the CSV and JSON exports. [#423](https://github.com/bonny/WordPress-Simple-History/issues/423).
- Column with event date based on current timezone added to CSV export, in addition the the existing GMT date. [#422](https://github.com/bonny/WordPress-Simple-History/issues/422).

**Fixed**

- Ensure only strings are escaped in csv export. [#426](https://github.com/bonny/WordPress-Simple-History/issues/426).

### 4.10.0 (January 2024)

This version introduces new features and improvements, including an enhanced first experience for new users. For more details and screenshots, check out the [release post](https://simple-history.com/2024/simple-history-4-10-0/).

**Added**

- Add logging of terms (custom taxonomies and built in tags and categories supported) added or removed to a post. [#214](https://github.com/bonny/WordPress-Simple-History/issues/214).

**Improved**

- Terms that are added, removed, or modified are now grouped. [#398](https://github.com/bonny/WordPress-Simple-History/issues/398).
- Show a more user-friendly and informative welcome message after installation. [#418](https://github.com/bonny/WordPress-Simple-History/issues/418).

**Fixed**

- Missing translation in sidebar. [#417](https://github.com/bonny/WordPress-Simple-History/issues/417).
- 'Activated plugin "{plugin_name}"' message after first install.
- Duplicated plugin installed and activated messages after first install. [#317](https://github.com/bonny/WordPress-Simple-History/issues/317).

**Removed**

- Remove usage of [load_plugin_textdomain()](https://developer.wordpress.org/reference/functions/load_plugin_textdomain/) since it's not required for plugins that are translated via https://translate.wordpress.org/. [#419](https://github.com/bonny/WordPress-Simple-History/issues/419).

### 4.9.0 (January 2024)

This release comes with improvements to the SQL queries that the plugin use to fetch events. These optimizations enhance query performance and reliability on both MySQL and MariaDB. Additionally, the plugin now provides support for SQLite databases.

Read the [release post](https://simple-history.com/?p=2229) for more information.

- Added: support for SQLite Database. Tested with the WordPress [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) feature plugin. See [Let's make WordPress officially support SQLite](https://make.wordpress.org/core/2022/09/12/lets-make-wordpress-officially-support-sqlite/) and [Help us test the SQLite implementation](https://make.wordpress.org/core/2022/12/20/help-us-test-the-sqlite-implementation/) for more information about the SQLite integration in WordPress and the current status. Fixes [#394](https://github.com/bonny/WordPress-Simple-History/issues/394) and [#411](https://github.com/bonny/WordPress-Simple-History/issues/411).
- Added: Support for plugin preview button that soon will be available in the WordPress.org plugin directory. This is a very nice way to quickly test plugins in your web browser. Read more in blog post ["Plugin Directory: Preview button revisited"](https://make.wordpress.org/meta/2023/11/22/plugin-directory-preview-button-revisited/) and follow progress in [trac ticket "Add a Preview in Playground button to the plugin directory"](https://meta.trac.wordpress.org/ticket/7251). You can however already test the functionality using this link: [Preview Simple History plugin](https://playground.wordpress.net/?plugin=simple-history&blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/simple-history/blueprint.json).
- Added: IP addresses are now shown on occasions.
- Added: Helper functions `get_cache_group()`, `clear_cache()`.
- Changed: Better support for MariaDB and MySQL 8 by using full group by in the query. Fixes multiple database related errors. Fixes [#397](https://github.com/bonny/WordPress-Simple-History/issues/397), [#409](https://github.com/bonny/WordPress-Simple-History/issues/409), and [#405](https://github.com/bonny/WordPress-Simple-History/issues/405).
- Changed: Misc code cleanup and improvements and GUI changes.
- Removed: Usage of `SQL_CALC_FOUND_ROWS` since it's deprecated in MySQL 8.0.17. Also [this should make the query faster](https://stackoverflow.com/a/188682). Fixes [#312](https://github.com/bonny/WordPress-Simple-History/issues/312).
- Removed: Columns "rep", "repeated" and "occasionsIDType" are removed from return value in `Log_Query()`.
- Fixed: Stats widget counting could be wrong due to incorrect loggers included in stats query.

### 4.8.0 (December 2023)

🧩 This release contains minor fixes, some code cleanup, and adds [support for add-ons](https://simple-history.com/2023/simple-history-4-8-0-introducing-add-ons/)!

- Add support for add-ons. Add-ons are plugins that extends Simple History with new features. The first add-on is [Simple History Extended Settings](https://simple-history.com/add-ons/extended-settings?utm_source=wpadmin) that adds a new settings page with more settings for Simple History.
- Add `last_insert_data` property to `Logger` class.
- Fix position of navigation bar when admin notice with additional class "inline" is shown. Fixes [#408](https://github.com/bonny/WordPress-Simple-History/issues/408).
- Update logotype.
- Fix notice when visiting the "hidden" options page `/wp-admin/options.php`.
- Move functions `get_pager_size()`, `get_pager_size_dashboard()`, `user_can_clear_log()`, `clear_log()`, `get_clear_history_interval()`, `get_view_history_capability()`, `get_view_settings_capability()`, `is_on_our_own_pages()`, `does_database_have_data()`, `setting_show_on_dashboard()`, `setting_show_as_page()`, `get_num_events_last_n_days()`, `get_num_events_per_day_last_n_days()`, `get_unique_events_for_days()` from `Simple_History` class to `Helpers` class.
- Remove unused function `filter_option_page_capability()`.
- Update coding standards to [WordPressCS 3](https://make.wordpress.org/core/2023/08/21/wordpresscs-3-0-0-is-now-available/).
- Misc code cleanup and improvements.

### 4.7.2 (October 2023)

- Changed: Check that a service class exists before trying to instantiate it.
- Added [Connection Business Directory](https://simple-history.com/2023/connections-business-directory-adds-support-for-simple-history/) to list of plugins with Simple History support.
- Added new icons! ✨
- Tested on WordPress 6.4.

### 4.7.1 (October 2023)

- Fix: Only context table was cleared when clearing the database. Now also the events table is cleared.
- Add function `AddOns_Licences::get_plugin()`.
- Misc internal code cleanup and improvements.

### 4.7.0 (October 2023)

Most notable in this release is the new logotype and a new shortcut to the "Settings & Tools" page.
[Read the release post for more info](https://simple-history.com/2023/simple-history-4-7-0/).

- Changed: UI changes, including a new logo and a shortcut to the settings page.
- Add function `get_view_history_page_admin_url()`.
- Add filter `simple_history/log_row_details_output-{logger_slug}` to allow modifying the output of the details of a log row.
- Misc internal code cleanup and improvements.

### 4.6.0 (September 2023)

This release contains some new filters and some other improvements.
[See the release post for more info](https://simple-history.com/2023/simple-history-4-6-0/).

- Added: Filter `simple_history/get_log_row_plain_text_output/output` to be able to modify the output of the plain text output of a log row. Solves support thread [Is it possible to log post ID](https://wordpress.org/support/topic/is-it-possible-to-log-post-id/). See [documentation page for filter](https://simple-history.com/docs/hooks/#simplehistorygetlogrowplaintextoutputoutput) for details.
- Added: Filter `simple_history/log_insert_data_and_context` to be able to modify the data and context that is inserted into the log.
- Added: WP-CLI command now includes "via" in output.
- Added: Debug settings tab now shows if a logger is enabled or disabled.
- Changed: WP-CLI: ID field is not the first column and in uppercase, to follow the same format as the other wp cli commands use.
- Changed: GUI enhancements on settings page.
- Changed: Don't log WooCommerce post type `shop_order_placehold`, that is used by WooCommerce new [High-Performance Order Storage (HPOS)](https://developer.woocommerce.com/2022/10/11/hpos-upgrade-faqs/).
- Fixed: Allow direct access to protected class variable `$logger->slug` but mark access as deprectad and recommend usage of `$logger->get_slug()`. Fixes support thread [PHP fatal error Cannot access protected property $slug](https://wordpress.org/support/topic/php-fatal-error-cannot-access-protected-property-slug/).

### 4.5.0 (August 2023)

This release contains some smaller new features and improvements.
[See the release post for more info](https://simple-history.com/simple-history-4-5-0/).

**Added**

- The debug page now detects if the required tables are missing and shows a warning. This can happen when the database of a website is moved between different servers using software that does not know about the tables used by Simple History. Fixes issue [#344](https://github.com/bonny/WordPress-Simple-History/issues/344) and support thread [Missing table support](https://wordpress.org/support/topic/missing-table-support/) among others.
- Add filters `simple_history/feeds/enable_feeds_checkbox_text` and `simple_history/feeds/after_address`.
- Add action `simple_history/settings_page/general_section_output`.
- Add filter `simple_history/db/events_purged` that is fired after db has been purged from old events.
- Add helper functions `required_tables_exist()`, `get_class_short_name()`.
- Add function `get_slug()` to `Dropin` class.
- Add function `get_rss_secret()` to `RSS_Dropin` class.
- Show review hint at footer on settings page and log page.
- Add functions `get_instantiated_dropin_by_slug()`, `get_external_loggers()`, `set_instantiated_loggers()`, ` set_instantiated_dropins()`, `get_instantiated_services()` to `Simple_History` class.
- Dropins and services are now listed on the debug page.

**Changed**

- Order of settings tab can now be set with key `order` in the array passed to `add_settings_tab()`.
- Rename network admin menu item "Simple History" to "View History" to use to same name as the admin menu item.
- Purged events are logged using the simple history logger (instead of directly in the purge function).
- Refactor code and move core functionality to multiple service classes.

### 4.4.0 (August 2023)

This version of Simple history is tested on the just released [WordPress 6.3](https://wordpress.org/news/2023/08/lionel/). It also contains some new features and bug fixes.

[Release post for Simple History 4.4.0](https://simple-history.com/2023/simple-history-4-4-0/).

**Added**

- Logger for logging changes to the Simple History settings page. 🙈 And yes, it was quite embarrassing that the plugin itself did not log its activities.
- RSS feed now accepts arguments to filter the events that are included in the feed. This makes it possible to subscribe to for example only WordPress core updates, or failed user logins, or any combination you want. See the documentation page for [available arguments and some examples](https://simple-history.com/docs/feeds/). [#387](https://github.com/bonny/WordPress-Simple-History/issues/387)
- Event ID of each entry is included in WP-CLI output when running command `wp simple-history list`.
- Filter `simple_history/settings/log_cleared` that is fired after the log has been cleared using the "Clear log now" button on the settings page.
- Add helper function `is_plugin_active()` that loads the needed WordPress files before using the WordPress function with the same name. Part of fix for [#373](https://github.com/bonny/WordPress-Simple-History/issues/373).

**Fixed**

- Shop changes to post type `customize_changeset`. Fix issue [#224](https://github.com/bonny/WordPress-Simple-History/issues/224) and support threads [stop the “Updated changeset” and “Move changeset” notifications](https://wordpress.org/support/topic/stop-the-updated-changeset-and-move-changeset-notifications/), [Newbie question](https://wordpress.org/support/topic/newbie-question-65/).
- Scrollbar on dashboard on RTL websites. Fixes issue [#212](https://github.com/bonny/WordPress-Simple-History/issues/212), support thread [Horizontal Scroll](https://wordpress.org/support/topic/horizontal-scroll-16/).
- PHP error when showing a log entry when all core loggers are disabled. Fixes [#373](https://github.com/bonny/WordPress-Simple-History/issues/373).

**Changed**

- Tested on WordPress 6.3.
- Use `uniqid()` as cache invalidator instead of `time()`. Querying the log multiple times during the same PHP request with the same arguments, adding entries to the log between each log query, the same results would be returned.
- Function `get_event_ip_number_headers()` moved from Simple Logger class to Helpers class.
- Misc internal code cleanup.

### 4.3.0 (July 2023)

**Added**

- Add action `simple_history/rss_feed/secret_updated` that is fired when the secret for the RSS feed is updated.
- Add tests for RSS feed.

**Fixed**

- RSS feed: Use `esc_xml` to escape texts. Fixes support thread [XML error with RSS feed](https://wordpress.org/support/topic/xml-error-with-rss-feed/), issue [#364](https://github.com/bonny/WordPress-Simple-History/issues/364).
- RSS feed: Some texts was double escaped.
- Plugin User Switching: store login and email context of user performing action, so information about a user exists even after user deletion. [#376](https://github.com/bonny/WordPress-Simple-History/issues/376).

### 4.2.1 (July 2023)

**Fixed**

- Fix PHP error when running WP-Cron jobs on PHP 8 and something was to be logged. Fixes [#370](https://github.com/bonny/WordPress-Simple-History/issues/370) and support threads [wordpress.org/support/topic/fatal-error-4492/](https://wordpress.org/support/topic/fatal-error-4492/), [wordpress.org/support/topic/fatal-error-4488/](https://wordpress.org/support/topic/fatal-error-4488/), [wordpress.org/support/topic/php-error-in-lastest-version/](https://wordpress.org/support/topic/php-error-in-lastest-version/).

### 4.2.0 (July 2023)

**Added**

- Filter `simple_history/day_of_week_to_purge_db` to set the day that the db should be cleared/purged on. 0 = monday, 7 = sunday. Default is 7.
- Add class `SimpleHistory` so old code like `SimpleHistory->get_instance()` will work.
- Add helper function `camel_case_to_snake_case()`.
- Automatically convert camelCase function names to snake_case function names when calling functions on the `\Simple_History` class. This way more old code and old examples will work. Fixes for example [support thread](https://wordpress.org/support/topic/uncaught-error-class-simplehistory/).
- Add `Helpers::privacy_anonymize_ip()`.
- Add filter `simple_history/privacy/add_char_to_anonymized_ip_address` to control if a char should be added to anonymized IPV4 addresses.
- Add filter `simple_history/maps_api_key` to set a Google Maps API key to be used to show a Google Map of the location of a user login using the user IP address.
- If a Google Maps API key is set then a map of a users location is shown when clicking on the IP address of a logged event. [#249](https://github.com/bonny/WordPress-Simple-History/issues/249).

**Fixed**

- Fix `Undefined property` warning when loading more similar events. [#357](https://github.com/bonny/WordPress-Simple-History/issues/357)
- Include "Plugin URI" from plugin when logging single plugin installs. [#323](https://github.com/bonny/WordPress-Simple-History/issues/323)
- Check that installed theme has a `destination_name`. [#324](https://github.com/bonny/WordPress-Simple-History/issues/324)
- Log correct role for user when adding a user on a subsite on a network/multisite install. [#325](https://github.com/bonny/WordPress-Simple-History/issues/325)
- Check that required array keys exists in theme- and translation loggers. Fixes [support thread](https://wordpress.org/support/topic/strange-error-message-during-updates/), issue [#339](https://github.com/bonny/WordPress-Simple-History/issues/339).
- Fix undefined index warning in logger when context was missing `_user_id`, `_user_email`, or `_user_login`. Fix [#367](https://github.com/bonny/WordPress-Simple-History/issues/367).
- Misc code cleanup and improvements.
- Fix spellings, as found by [Typos](https://github.com/crate-ci/typos/).

**Changed**

- Move function `get_avatar()` to helpers class.
- Change location of filter `gettext` and `gettext_with_context` and unhook when filter is not needed any more, resulting in much fewer function calls.
- IPV4 addresses that are anonymized get a ".x" added last instead of ".0" to make it more clear to the user that the IP address is anonymized.

**Removed**

- Remove unused schedule `simple_history/purge_db`.
- Remove function `filter_gettext_store_latest_translations()`.
- Remove support for automatically un-translating messages to the log, loggers are better and have better support for languages.

### 4.1.0 (July 2023)

**Added**

- Actions `simple_history/pause` and `simple_history/resume` to pause and resume logging. Useful for developers that for example write their own data importers because the log can be overwhelmed with data when importing a lot of data. [#307](https://github.com/bonny/WordPress-Simple-History/issues/307)
- `clear_log()` now returns the number of rows deleted.
- Added `disable_taxonomy_log()` to simplify disabling logging of a taxonomy.
- Function `get_db_table_stats()` that returns for example the number of rows in each table.

**Fixed**

- Check that array keys `attachment_parent_title` and `attachment_parent_post_type` exists in Media Logger. [#313](https://github.com/bonny/WordPress-Simple-History/issues/313)
- Don't log when terms are added to author taxonomy in [Co-Authors Plus](https://wordpress.org/plugins/co-authors-plus/). Fixes [support thread](https://wordpress.org/support/topic/co-author-plus-spamming-simple-history-plugin-is-this-a-but-or-a-feature/), issue [#238](https://github.com/bonny/WordPress-Simple-History/issues/238).
- Don't load the log or check for updates on dashboard if the widget is hidden.
- Don't check for updates on dashboard if a request is already ongoing.

**Changed**

- Moved filter `simple_history/dashboard_pager_size` to method `get_pager_size_dashboard()`.
- Move function `get_initiator_text_from_row()` to `Log_Initiators` class.
- If a filter is modifying the pager sizes then show a readonly text input with pager size instead of a dropdown select. [#298](https://github.com/bonny/WordPress-Simple-History/issues/298)
- Update Chart.js library from 2.0.2 to 4.3.0. Fixes [support thread](https://wordpress.org/support/topic/outdated-chartjs-component-used/), issue [#340](https://github.com/bonny/WordPress-Simple-History/issues/340).

### 4.0.1 (June 2023)

**Fixed**

- Replace multibyte functions with non-multibyte versions, since `mbstring` is not a [required PHP extension](https://make.wordpress.org/hosting/handbook/server-environment/#php-extensions) (it is however a highly recommended one). Should fix https://wordpress.org/support/topic/wordpress-critical-error-9/. ([#351](https://github.com/bonny/WordPress-Simple-History/issues/351))

### 4.0.0 (June 2023)

🚀 This update of Simple History contains some big changes – that you hopefully won't even notice.

For regular users these are the regular additions and bug fixes:

**Changed**

- Minimum required PHP version is 7.4. Users with lower versions can use [version 3.4.0 of the plugin](https://downloads.wordpress.org/plugin/simple-history.3.3.0.zip).
- Minimum required WordPress version is 6.1.
- Categories logger does not log changes to taxonomy `nav_menu` any longer, since the Menu logger takes care of those, i.e. changes to the menus.

**Added**

- Log if "Send personal data export confirmation email" is checked when adding a Data Export Request.
- Log when a Data Export Request is marked as complete.
- Log when Personal Data is erased by an admin.
- Log when a group is modified in Redirection plugin.
- Added context key `export_content` to export logger. The key will contain the post type exported, or "all" if all content was exported.

**Fixed**

- Fix error on MariaDB databases when collation `utf8mb4_unicode_520_ci` is used for the Simple history tables. Reported at: [https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/](https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/).
- Privacy logger is logging the creation and selection of privacy page again. It stopped worked because [a WordPress core file was renamed](https://core.trac.wordpress.org/ticket/43895).
- Log when a groups is enabled, disabled, or deleted in Redirection plugin.

👩‍💻 For developers there are some bigger changes, that is noticeable:

- The plugin now uses namespaces – and they are loaded using an autoloader.
- The code has been changed to follow [WordPress coding standard](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). This means that for example all functions have been renamed from `myFunctionName()` to `my_function_name()`.
- The update to PHP 7.4 as the minimum required PHP version makes code more modern in so many ways and makes development easier and more funny, since we don't have to worry about backwards compatibility as much.
- Many more tests using [wp-browser](https://wpbrowser.wptestkit.dev/) have has been added to minimize risk of bugs or fatal errors.

A more detailed changelog that probably most developers are interested in:

**Added**

- Add cached = true|false to AJAX JSON answer when fetching events or checking for new events. It's a simple way to see if an object cache is in use and is working.
- Most of the code now uses namespaces.
  - The main namespace is `Simple_History`.
  - The main class is `Simple_History\Simple_History`.
  - Dropins use namespace `Simple_History\Dropins` and dropins must now extend the base class `Dropin`.
  - Loggers use namespace `Simple_History\Loggers` and loggers must extend the base class `Logger`.
- Add hooks that controls loggers and their instantiation: `simple_history/core_loggers`, `simple_history/loggers/instantiated`.
- Add hooks that controls dropins and their instantiation: `simple_history/dropins_to_instantiate`, `simple_history/core_dropins`, `simple_history/dropins_to_instantiate`, `simple_history/dropin/instantiate_{$dropin_short_name}`, `simple_history/dropin/instantiate_{$dropin_short_name}`, `simple_history/dropins/instantiated`.
- Add filter `simple_history/ip_number_header_names`.
- Add methods `get_events_table_name()` and `get_contexts_table_name()`.
- Call method `loaded()` on dropins when they are loaded (use this instead of `__construct`).
- Make sure that a dropin class exists before trying to use it.

**Changed**

- Improved code organization with the introduction of namespaces. Code now uses namespaces and classes (including loggers and dropins) are now loaded using an autoloader.
- Functions are renamed to use `snake_case` (WordPress coding style) instead of `camelCase` (PHP PSR coding style). Some examples:
  - `registerSettingsTab` is renamed to `register_settings_tab`.
- Remove usage of deprectead function `wp_get_user_request_data()`.
- Rename message key from `data_erasure_request_sent` to `data_erasure_request_added`.
- Rename message key from `data_erasure_request_handled` to `data_erasure_request_completed`.
- Applied code fixes using Rector and PHPStan for better code quality.
- Add new class `Helpers` that contain helper functions.
- Move functions `simple_history_get_current_screen()`, `interpolate()`, `text_diff`, `validate_ip`, `ends_with`, `get_cache_incrementor` to new helper class.
- Function `get_ip_number_header_keys` is moved to helper class and renamed `get_ip_number_header_names`.
- Class `SimpleHistoryLogQuery` renamed to `Log_Query`.
- Class `SimpleLoggerLogLevels` renamed to `Log_Levels`.
- Class `SimpleLoggerLogInitiators` renamed to `Log_Initiators`.
- Dropin files are renamed.
- Move init code in dropins from `__construct()` to new `loaded()` method.
- Rename `getLogLevelTranslated()` to `get_log_level_translated()` and move to class `log_levels`.
- Rename message key `user_application_password_deleted` to `user_application_password_revoked`.
- Context key `args` is renamed to `export_args` in export logger. This key contains some of the options that was passed to export function, like author, category, start date, end date, and status.
- Ensure loggers has a name and a slug set to avoid development oversights. `_doing_it_wrong()` will be called if they have not.
- Logger: Method `get_info_value_by_key()` is now public so it can be used outside of a logger.
- Logger: Method `get_info()` is now abstract, since it must be added by loggers.
- For backwards compatibility `SimpleHistoryLogQuery`, `SimpleLoggerLogLevels`, `SimpleLoggerLogInitiators`, `SimpleLogger` will continue to exist for a couple of more versions.

**Removed**

- Function `simple_history_add` has been removed. See [docs.simple-history.com/logging](https://docs.simple-history.com/logging) for other ways to add messages to the history log.
- Unused function `sh_ucwords()` has been removed.
- Removed filters `simple_history/loggers_files`, `simple_history/logger/load_logger`, `'simple_history/dropins_files'`.
- Unused class `SimpleLoggerLogTypes` removed.
- Removed logger for plugin Ultimate Members.
- Removed patches for plugin [captcha-on-login](https://wordpress.org/plugins/captcha-on-login/).
- Remove dropin used to populate log with test data.
- Remove dropin used to show log stats.
- Examples in examples folder are removed and moved to the documentation site at docs.[simple-history.com](https://docs.simple-history.com/).

### 3.5.1 (May 2023)

- Fixed JavaScript error when Backbone.history is already started by other plugins. Fixes https://github.com/bonny/WordPress-Simple-History/issues/319.

### 3.5.0 (March 2023)

- Added: Log an entry when a cron event hook is paused or resumed with the WP Crontrol plugin [#328](https://github.com/bonny/WordPress-Simple-History/pull/328).
- Fixed: DB error on MariaDB database when collation `utf8mb4_unicode_520_ci` is used for the Simple history tables. Reported at: https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/.
- Tested up to WordPress 6.2.

Note: Next major version of the plugin will require PHP 7. If you are running a PHP version older than that please read https://wordpress.org/support/update-php/.

= 3.4.0 (February 2023) =

- Changed: When exporting a CSV file of the history, each cell is escaped to reduce the risk of "CSV injection" in spreadsheet applications when importing the exported CSV. Reported at: https://patchstack.com/database/vulnerability/simple-history/wordpress-simple-history-plugin-3-3-1-csv-injection-vulnerability.

[Changelog for 2022 and earlier](https://github.com/bonny/WordPress-Simple-History/blob/master/CHANGELOG.md)
