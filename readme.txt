# Simple History – Track, Log, and Audit WordPress Changes

Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, audit log, event log, user tracking, activity
Tested up to: 6.7
Stable tag: 5.6.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

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

`
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

✨ If you find Simple History useful ✨

-   [Sponsor the plugin to keep it free.](https://simple-history.com/sponsor/)
-   [Add a 5-star review so other users know it's good.](https://wordpress.org/support/plugin/simple-history/reviews/?filter=5)
-   [Get the premium add-on for more features.](https://simple-history.com/add-ons/premium/?utm_source=wpadmin&utm_content=readme).

### 5.6.0 (January 2025)

🔝 This version adds an option to the settings page to control the location of the menu page (at top or bottom).
🫣 It also adds support for **Stealth Mode**: When enabled Simple History will be hidden from places like the dashboard, the admin menu, the admin bar, and the plugin list.
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
