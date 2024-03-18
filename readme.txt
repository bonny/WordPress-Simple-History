# Simple History – user activity log, audit tool

Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.5
Stable tag: 4.13.0

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

```
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
```

See the [documentation](https://simple-history.com/docs/) for examples on how to [log your own events](https://simple-history.com/docs/logging-api/) and how to [query the log](https://simple-history.com/docs/query-api/), and more.

### Add-ons available

**WooCommerce Logger**: Enhance your site's tracking with comprehensive logs for WooCommerce orders, products, settings, and coupons. [Read more](https://simple-history.com/add-ons/woocommerce/?utm_source=wpadmin).

**Extended Settings**: Extend the settings of Simple History with more options and settings. [Read more](https://simple-history.com/add-ons/extended-settings/?utm_source=wpadmin).

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

### 4.13.0 (March 2024)

🚀 Introducing the WooCommerce Logger Add-On: Enhance your site's tracking with comprehensive logs for WooCommerce orders, products, settings, and coupons. Learn more in our [release post](https://simple-history.com/2024/woocommerce-logger-add-on-released/?utm_source=wpadmin).

- Add support for logging when adding or removing user roles via WP-CLI. [WP-CLI 2.10.0 added "Support for adding and removing of multiple user roles"](https://make.wordpress.org/cli/2024/02/08/wp-cli-v2-10-0-release-notes/) and now Simple History supports logging of these events. [#431](https://github.com/bonny/WordPress-Simple-History/issues/431).

View the [release post to see screenshots of the new features](https://simple-history.com/2024/simple-history-4-13/).

### 4.12.0 (Februari 2024)

**Added**

- Theme activation/switch done via WP CLI (e.g. `wp theme activate twentytwentyone`) is now logged.

**Fixed**

- Message type search/filter not working. [#428](https://github.com/bonny/WordPress-Simple-History/issues/428)
- PHP notice when user has no roles. [#429](https://github.com/bonny/WordPress-Simple-History/issues/429).

[Changelog for previous versions.](CHANGELOG.md)
