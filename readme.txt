# Simple History – user activity log, audit tool

Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.4
Stable tag: 4.12.0

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

### 4.12.0 (Februari 2024)

**Added**

- Theme activation/switch done via WP CLI (e.g. `wp theme activate twentytwentyone`) is now logged.

**Fixed**

- Message type search/filter not working. [#428](https://github.com/bonny/WordPress-Simple-History/issues/428)
- PHP notice when user has no roles. [#429](https://github.com/bonny/WordPress-Simple-History/issues/429).

### 4.11.0 (February 2024)

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

[Changelog for 2023 and earlier](https://github.com/bonny/WordPress-Simple-History/blob/master/CHANGELOG.md)
