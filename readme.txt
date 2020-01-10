=== Simple History ===
Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, log, changes, changelog, audit, audit log, event log, user tracking, trail, pages, attachments, users, dashboard, admin, syslog, feed, activity, stream, audit trail, brute-force
Requires at least: 5.2
Tested up to: 5.2
Requires PHP: 5.6
Stable tag: 2.33.2

View changes made by users within WordPress. See who created a page, uploaded an attachment or approved an comment, and more.

== Description ==

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin works as a log/history/audit log/version history of the most important events that occur in WordPress.

Out of the box Simple History has support for:

- **Posts and pages**<br>
  see who added, updated or deleted a post or page
- **Attachments**<br>
  see who added, updated or deleted an attachment
- **Taxonomies (Custom taxonomies, categories, tags)**<br>
  see who added, updated or deleted an taxonomy
- **Comments**<br>
  see who edited, approved or removed a comment
- **Widgets**<br>
  get info when someone adds, updates or removes a widget in a sidebar
- **Plugins**<br>
  activation and deactivation
- **User profiles**<br>
  info about added, updated or removed users
- **User logins**<br>
  see when a user login & logout. Also see when a user fails to login (good way to catch brute-force login attempts).
- **Failed user logins**<br>
  see when someone has tried to log in, but failed. The log will then include ip address of the possible hacker.
- **Menu edits**
- **Option screens**<br>
  view details about changes made in the differnt settings sections of WordPress. Things like changes to the site title and the permalink structure will be logged.
- **Privacy page**<br>
  when a privacy page is created or set to a new page.
- **Data Export**<br>
  see when a privacy data export request is added and when this request is approved by the user, downloaded by an admin, or emailed to the user.
- **User Data Erasure Requests**<br>
  see when a user privacy data export request is added and when this request is approved by the user and when the user data is removed.

#### Support for third party plugins

By default Simple History comes with built in support for the following plugins:

**Jetpack**<br>
The [Jetpack plugin](https://wordpress.org/plugins/jetpack/) is a plugin from Automattic (the creators of WordPress) that lets you supercharge your website by adding a lot of extra functions.
In Simple History you will see what Jetpack modules that are activated and deactivated.
(The creator of Simple History recommends this plugin and its [brute force attack protection](https://jetpack.com/features/security/brute-force-attack-protection/) functions btw. It's a really good way to block unwanted login attempts from malicious botnets and distributed attacks.

**Advanced Custom Fields (ACF)**<br>
[ACF](https://www.advancedcustomfields.com/) adds fields to your posts and pages.
Simple History will log changes made to the field groups and the fields inside field groups. Your will see when both field groups and fields are created and modified.

**User Switching**<br>
The [User Switching plugin](https://wordpress.org/plugins/user-switching/) allows you to quickly swap between user accounts in WordPress at the click of a button.
Simple History will log each user switch being made.

**Enable Media Replace**<br>
The [Enable Media Replace plugin](https://wordpress.org/plugins/enable-media-replace/) allows you to replace a file in your media library by uploading a new file in its place.
Simple history will log details about the file being replaced and details about the new file.

**Limit Login Attempts**<br>
The plugin [Limit Login Attempts](https://wordpress.org/plugins/limit-login-attempts/) is old
and has not been updated for 4 years. However it still has +1 million installs, so many users will benefit from
Simple History logging login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts.

**Redirection**
The [redirection plugin](https://wordpress.org/plugins/redirection/) manages url redirections, using a nice GUI.
Simple History will log redirects and groups that are created, changed, enabled or disabled and also when the global plugin settings have been modified.

**Duplicate Post**
The plugin [Duplicate Post](https://wordpress.org/plugins/duplicate-post/) allows users to
clone posts of any type.
Simple History will log when a clone of a post or page is done.

**Beaver Builder**
The plugin [Beaver Build](https://wordpress.org/plugins/beaver-builder-lite-version/) is a page builder for WordPress that adds a flexible drag and drop page builder to the front end of your WordPress website.
Simple History will log when a Beaver Builder layout or template is saved or when the settings for the plugins are saved.

#### RSS feed with changes

There is also a **RSS feed of changes** available, so you can keep track of the changes made via your favorite RSS reader on your phone, on your iPad, or on your computer.

It’s a plugin that is good to have on websites where several people are
involved in editing the content.

The plugin works fine on [multisite installations of WordPress](http://codex.wordpress.org/Glossary#Multisite) too.

#### Example scenarios

Keep track of what other people are doing:
_"Has someone done anything today? Ah, Sarah uploaded
the new press release and created an article for it. Great! Now I don't have to do that."_

Or for debug purposes:
_"The site feels slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughy-plugin-x',
that must be it."_

#### API so you can add your own events to the audit log

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

Check out the [examples-folder](https://github.com/bonny/WordPress-Simple-History/tree/master/examples) for more examples.

#### Translations/Languages

So far Simple History is translated to:

- Swedish
- German
- Polish
- Danish
- Dutch
- Finnish
- French
- Russian

I'm looking for translations of Simple History in more languages! If you want to translate Simple History
to your language then read about how this is done over at the [Polyglots handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/#translating-themes-plugins).

#### Contribute at GitHub

Development of this plugin takes place at GitHub. Please join in with feature requests, bug reports, or even pull requests!
https://github.com/bonny/WordPress-Simple-History

#### Donation

- If you like this plugin please consider [donating to support the development](https://www.paypal.me/eskapism).

== Frequently Asked Questions ==

= Can I add my own events to the log? =

Yes. See the [examples file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php).

= Is it possible to exclude users from the log? =

Yes, you exclude users by role or email using the filter `simple_history/log/do_log`.
See the [examples file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php).

= For how long are events stored? =

Events in the log are stored for 60 days by default. Events older than this will be removed.

== Screenshots ==

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

== Changelog ==

## Changelog

= 2.33.2 (January 2020) =
- Fix history displaying blank white space on smaller screens. Fixes https://wordpress.org/support/topic/viewing-the-log-on-a-iphone/.

= 2.33.1 (January 2020) =
- Was just an internal test version.

= 2.33 (November 2019) =
- Better compatibility with the Gutenberg Block editor.
- Correct URL redirected to after clearing log. Fixes #123.
- Fix history log on dashboard leaving lots of white space and sometimes overlapping other dashboard widgets.
  Fixes https://wordpress.org/support/topic/dashboard-block-cut-off/, https://wordpress.org/support/topic/simple-history-v2-32/, and https://wordpress.org/support/topic/new-update-not-working-10/.
- Fix join parameter order for PHP 7.4.
- Update donate link. It's now https://www.paypal.me/eskapism.
  If you like the plugin please consider donate.
	A very small amount makes me much more happy than nothing at all! ;)

= 2.32 (August 2019) =

- Fix error in Beaver Builder logger. Fixes https://wordpress.org/support/topic/conflict-with-beaver-builder-plugin-4/.
- Add filter `simple_history/admin_location` that makes is possible to move the main page from the dashboard menu to any other menu page, for example the Tools menu. Fixes https://github.com/bonny/WordPress-Simple-History/issues/140. Example usage of filter:

```php
// Move Simple History log sub page from the "Dashboard" menu to the "Tools" menu.
add_filter('simple_history/admin_location', function ($location) {
	$location = 'tools';
	return $location;
});
```

- Make it easier to extend SimplePostLogger by making `$old_post_data` protected instead of private. https://github.com/bonny/WordPress-Simple-History/pull/173.
- Try to use taxonomy name instead of taxonomy slug when showing term additions or modifications. Fixes https://github.com/bonny/WordPress-Simple-History/issues/164.
- Fix notice error when showing the log entry for a term that was deleted.
- Remove unused old function `testlog_old()`.
- Move helper functions to own file.
- Move debug code into own dropin.
- Bump required PHP version to 5.6.20 (same version that WordPress itself requires).

= 2.31 (May 2019) =

- Add support for plugin [Beaver Builder](https://wordpress.org/plugins/beaver-builder-lite-version/).

= 2.30 (April 2019) =

- Add better Gutenberg compatibility.
- Don't log WooCommerce scheduled actions. Fixes https://wordpress.org/support/topic/cant-use-flooded-with-deleted-scheduled-action-woocommerce-webhooks/.
- Store if post password has been set, unset, or changed.
- Store if a log entry comes from the REST API. Stored in the event context as `_rest_api_request`.
- Check that logger messages exists and is array before trying to use.
- Bump required version in readme to 5.4. It's just to difficult to keep the plugin compatible with PHP less than [PHP version 5.4](http://php.net/manual/en/migration54.new-features.php).
- Updates to some translation strings.

= 2.29.2 (January 2019) =

- Fix for (the still great) plugin [Advanced Custom Fields](http://advancedcustomfields.com) 5.7.10 that removed the function `_acf_get_field_by_id` that this plugin used. Fixes https://wordpress.org/support/topic/uncaught-error-call-to-undefined-function-_acf_get_field_by_id/.

= 2.29.1 (December 2018) =

- Fix another PHP 7.3 warning. Should fix https://wordpress.org/support/topic/php-7-3-compatibility-3/.

= 2.29 (December 2018) =

- Make log welcome message translateable.
- Add two filters to make it more ease to control via filters if a logger and the combination logger + message should be logged. - `"simple_history/log/do_log/{$this->slug}"` controls if any messages for a specific logger should be logged. Simply return false to this filter to disable all logging to that logger. - `"simple_history/log/do_log/{$this->slug}/{$message_key}"` controls if a specific message for a specific logger should be logged. Simply return false to this filter to disable all logging to that logger. - Code examples for the two filters above:

  ````
  // Disable logging of any user message, i.e. any message from the logger SimpleUserLogger.
  add_filter( 'simple_history/log/do_log/SimpleUserLogger', '\_\_return_false' );

      		// Disable logging of updated posts, i.e. the message "post_updated" from the logger SimplePostLogger.
      		add_filter( 'simple_history/log/do_log/SimplePostLogger/post_updated', '__return_false' );
      		```

  ````

- add_filter('simple_history/log/do_log/SimpleUserLogger', '\_\_return_false');
- Fix notice in Redirection plugin logger due because redirection plugin can have multiple target types. Props @MaximVanhove.
- Fix warning because of missing logging messages in the categories/tags logger. Props @JeroenSormani.
- Fix warning in the next version of PHP, PHP 7.3.

= 2.28.1 (September 2018) =

- Remove a debug message that was left in the code.

= 2.28 (September 2018) =

- Always show time and sometimes date before each event, in addition to the relative date. Fixes https://wordpress.org/support/topic/feature-request-granular-settings-changes-detailed-timestamp/.
- Use WordPress own function (`wp_privacy_anonymize_ip`, available since WordPress version 4.9.6) to anonymize IP addresses, instead of our own class.
- Update timeago.js

= 2.27 (August 2018) =

- Fix notice errors when syncing an ACF field group. Fixes https://github.com/bonny/WordPress-Simple-History/issues/150.
- Fix notice error when trying to read plugin info for a plugin that no longer exists or has changed name. Fixes https://github.com/bonny/WordPress-Simple-History/issues/146.
- Always load the SimpleLogger logger. Fixes https://github.com/bonny/WordPress-Simple-History/issues/129.
- Make more texts translatable.
- Show plugin slug instead of name when translations are updated and a plugin name is not provided by the upgrader. This can happen when a plugin is using an external update service, like EDD.
- Group translation updates in the log. Useful because sometimes you update a lot of translations at the same time and the log is full of just those messages.

= 2.26.1 (July 2018) =

- Fix 5.3 compatibility.

= 2.26 (July 2018) =

- Add support for the [Jetpack plugin](https://wordpress.org/plugins/jetpack/). To begin with, activation and deactivation of Jetpack modules is logged.
- Add logging of translation updates, so now you can see when a plugin or a theme has gotten new translations. Fixes https://github.com/bonny/WordPress-Simple-History/issues/147.
- Fix notice in Advanced Custom Fields logger when saving an ACF options page.
  Fixes https://wordpress.org/support/topic/problem-with-acf-options-pages/, https://wordpress.org/support/topic/problem-with-recent-version-and-acf/, https://github.com/bonny/WordPress-Simple-History/issues/145.

= 2.25 (July 2018) =

- Add `wp_cron_current_filter` to event context when something is logged during a cron job. This can help debugging thing like posts being added or deleted by some plugin and you're trying to figure out which plugin it is.
- Fix for event details not always being shown.
- Fix for sometimes missing user name and user email in export file.

= 2.24 (July 2018) =

- Added user login and user email to CSV export file.
- Fix notice in postlogger when a post was deleted from the trash.
- Clear database in smaller steps. Fixes https://github.com/bonny/WordPress-Simple-History/issues/143.
- Fix notice in ACF logger due to misspelled variable. Fixes https://wordpress.org/support/topic/problem-with-recent-version-and-acf/.

= 2.23.1 (May 2018) =

- Remove some debug messages that was outputed to the error log. Fixes https://wordpress.org/support/topic/errors-in-php-log-since-v2-23/.
- Fix error beacuse function `ucwords()` does not allow a second argument on PHP versions before 5.4.32. Fixes https://wordpress.org/support/topic/error-message-since-last-update/, https://wordpress.org/support/topic/errors-related-to-php-version/.
- Added new function `sh_ucwords()` that works like `ucwords()` but it also works on PHP 5.3.

= 2.23 (May 2018) =

- Add logging of privacy and GDPR related functions in WordPress. Some of the new [privacy related features in WordPress 4.9.6](https://wordpress.org/news/2018/05/wordpress-4-9-6-privacy-and-maintenance-release/) that are logged: - Privacy policy page is created or changed to a new page. - Privacy data export is requested for a user and when this request is confirmed by the user and when the data for the request is downloaded by an admin or emailed to the user. - Erase Personal Data: Request is added for user to have their personal data erased, user confirms the data removal and when the deletion of user data is done.
- Fix error when categories changes was shown in the log. Fixes https://wordpress.org/support/topic/php-notice-undefined-variable-term_object/.
- Fix error when a ACF Field Group was saved.
- Fix error when the IP address anonymization function tried to anonymize an empty IP adress. Could happen when for example running wp cron locally on your server.
- Fix error when calling the REST API with an API endpoint with a closure as the callback. Fixes https://github.com/bonny/WordPress-Simple-History/issues/141.
- Rewrote logger loading method so now it's possible to name your loggers in a WordPress codings standard compatible way. Also: made a bit more code more WordPress-ish.
- The post types in the `skip_posttypes` filter are now also applied to deleted posts.
- Add function `sh_get_callable_name()` that returns a human readable name for a callback.

= 2.22.1 (May 2018) =

- Fix for some REST API Routes not working, for example when using WPCF7. Should fix https://wordpress.org/support/topic/errorexception-with-wpcf7/ and similar.

= 2.22 (May 2018) =

- IP addresses are now anonymized by default. This is mainly done because of the [General Data Protection Regulation](https://en.wikipedia.org/wiki/General_Data_Protection_Regulation) (GDPR)
  Both IPv4 and IPv6 addresses will be anonymized and the IP addresses are anonymized to their network ID.
  So for example the IPv4 address `192.168.123.124` is anonymized to `192.168.123.0` and
  the IPv6 address `2a03:2880:2110:df07:face:b00c::1` is anonymized by default to `2610:28:3090:3001::`.

- Added filter `simple_history/privacy/anonymize_ip_address` than can be used to disable ip address anonymization.

- Added function `sh_error_log()` to easily log variables to the error log. Probably only of interest to developers.

- Fixed logging for [plugin Redirection](https://wordpress.org/plugins/redirection/). The logging of URL redirects and so on stopped working some while back because the Redirection plugin started using the WP REST API. But now it's working again!

= 2.21.1 (May 2018) =

- Make sure support for Advanced Custom Fields is activated for all users – and not only for the developer of the plugin ;)

= 2.21 (May 2018) =

- Added support for Advanced Custom Fields (ACF): when a ACF Field or ACF Field Group is created or modified or deleted you will now get more details in the activity feed.
- Changes to taxonomies/categories/tags now include a link to the modified term and to the category that the term belongs to.
- The post types in the `skip_posttypes` filter are now also applied to trashed and untrashed posts (not only post edits, as before).
- Don't log Jetpack sitemap updates. (Don't log updates to posttypes `jp_sitemap`, `jp_sitemap_master` and `jp_img_sitemap`, i.e. the post types used by Jetpack's Sitemap function.) Should fix https://wordpress.org/support/topic/jetpack-sitemap-logging/.
- Don't log the taxonomies `post_translations` or `term_translations`, that are used by Polylang to store translation mappings. That contained md5-hashed strings and was not of any benefit (a separate logger for Polylang will come soon anyway).
- Fix notice in theme logger because did not check if `$_POST['sidebar']` was set. Fixes https://github.com/bonny/WordPress-Simple-History/issues/136.
- Fix thumbnail title missing notice in post logger.
- Fix PHP warning when a plugin was checked by WordPress for an update, but your WordPress install did not have the plugin folder for that plugin.
- Fix unexpected single-quotations included in file name in Internet Explorer 11 (and possibly other versions) when exporting CSV/JSON file.
- Fix filter/search log by specific users not working. Fixes https://wordpress.org/support/topic/show-activity-from-other-authors-only/.
- Fix a notice in SimpleOptionsLogger.
- Better CSS styling on dashboard.
- Add filter `simple_history/post_logger/post_updated/context` that can be used to modify the context added by SimplePostLogger.
- Add filter `simple_history/post_logger/post_updated/ok_to_log` that can be used to skip logging a post update.
- Add filter `simple_history/categories_logger/skip_taxonomies` that can be used to modify what taxonomies to skip when logging updates to taxonomy terms.

= 2.20 (November 2017) =

- Add logging of post thumbnails.
- Use medium size of image attachments when showing uploaded files in the log. Previously a custom size was used, a size that most sites did not have and instead the full size image would be outputed = waste of bandwidth.
- Make image previews smaller because many uploaded images could make the log a bit to long and not so quick to overview.
- Update Select2 to latest version. Fixes https://wordpress.org/support/topic/select2-js-is-outdated/.
- Show a message if user is running to old WordPress version, and don't continue running the code of this plugin.
  Should fix stuff like https://wordpress.org/support/topic/simple-history-i-cannot-login/.
- Fix an error with PHP 7.1.

= 2.19 (November 2017) =

- Add filter `simple_history/user_can_clear_log`. Return `false` from this filter to disable the "Clear blog" button.
- Remove static keyword from some methods in SimpleLogger, so now calls like `SimpleLogger()->critical('Doh!');` works.
- Don't show link to WordPress updates if user is not allowed to view the updates page.
- Fix notice error in SimpleOptionsLogger.
- Fix for fatal errors when using the lost password form in [Membership 2](https://wordpress.org/plugins/membership/). Fixes https://wordpress.org/support/topic/conflict-with-simple-history-plugin-and-php-7/.
- Code (a little bit) better formatted according to [WordPress coding standard](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards).

= 2.18 (August 2017) =

- Set from_term_description correctly, fixes https://github.com/bonny/WordPress-Simple-History/issues/127.
- Add filter `simple_history/post_logger/skip_posttypes`.
- Don't log post type `jetpack_migation` because for some users that post type filled the log. Fixes https://wordpress.org/support/topic/updated-jetpack_migration-sidebars_widgets/.

= 2.17 (June 2017) =

- Fix search date range inputs not showing correctly.
- Change the message for when a plugin is deactivated due to an error. Now the plugin slug is included, so you know exactly what plugin has been deactivated. Also the reason for the deactivation is included (one of "Invalid plugin path", "Plugin file does not exist", or "The plugin does not have a valid header.").
- Added more filters to log message. Now filter `simple_history_log_debug` exists, together with filters for all other 7 log levels. So you can use `simple_history_log_{loglevel}` where {loglevel} is any of emergency, alert, critical, error, warning, notice, info or debug.
- Add support for logging the changing of "locale" on a user profile, something that was added in WordPress 4.7.
- Add sidebar box with link to the settings page.
- Don't log when old posts are deleted from the trash during cron job wp_scheduled_delete.
- HHVM is not used for any tests any longer because PHP 7 and Travis not supporting it or something. I dunno. Something like that.
- When "development debug mode" is activated also log current filters.
- Show an admin warning if a logger slug is longer than 30 chars.
- Fix fatal error when calling log() method with null as context argument.

= 2.16 (May 2017) =

- Added [WP-CLI](https://wp-cli.org) command for Simple History. Now you can write `wp simple-history list` to see the latest entries from the history log. For now `list` is the only available command. Let me know if you need more commands!
- Added support for logging edits to theme files and plugin files. When a file is edited you will also get a quick diff on the changes,
  so you can see what CSS styles a client changed or what PHP changes they made in a plugin file.
- Removed the edit file logger from the plugin logger, because it did not always work (checked wrong wp path). Intead the new Theme and plugins logger mentioned above will take care of this.

= 2.15 (May 2017) =

- Use thumbnail version of PDF preview instead of full size image.
- Remove Google Maps image when clicking IP address of failed login and similar, because Google Maps must be used with API key.
  Hostname, Network, City, Region and Country is still shown.
- Fix notice in available updates logger.
- Fix notice in redirection logger.

= 2.14.1 (April 2017) =

- Fix for users running on older PHP versions.

= 2.14 (April 2017) =

- Added support for plugin [Duplicate Post](https://wordpress.org/plugins/duplicate-post/).
  Now when a user clones a post or page you will se this in the history log, with links to both the original post and the new copy.
- Removed log level info from title in RSS feed
- Make date dropdown less "jumpy" when loading page (due to select element switching to Select2)
- Only add filters for plugin Limit Login Attempts if plugin is active. This fixes problem with Limit Login Attempts Reloaded and possibly other forks of the plugin.
- Debug page now displays installed plugins.

= 2.13 (November 2016) =

- Added filter `simple_history_log` that is a simplified way to add message to the log, without the need to check for the existance of Simple History or its SimpleLogger function. Use it like this: `apply_filters("simple_history_log", "This is a logged message");` See the [examples file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) for more examples.
- IP info now displays a popup with map + geolocation info for users using HTTPS again. Thanks to the great https://twitter.com/ipinfoio for letting all users use their service :)
- Fix notice warning for missing `$data_parent_row`

= 2.12 (September 2016) =

- You can show a different number of log items in the log on the dashboard and on the dedicated history page. By default the dashboard will show 5 items and the page will show 30.
- On multisites the user search filter now only search users in the current site.
- The statistics chart using Chart.js now uses the namespace window.Simple_History_Chart instead of window.Chart, decreasing the risk that two versions of the Chart.js library overwriting each others. Fixes https://wordpress.org/support/topic/comet-cache-breaks-simple-history/. (Note to future me: this was fixed by renaming the `window.chart` variable to `window.chart.Simple_history_chart` in the line `window.Chart = module.exports = Chart;`)
- If spam comments are logged they are now included in the log. Change made to make sql query shorter and easier. Should not actually show any spam comments anyway because we don't log them since version 2.5.5 anyway. If you want to revert this behavior for some reason you can use the filter `simple_history/comments_logger/include_spam`.

= 2.11 (September 2016) =

- Added support for plugin [Redirection](https://wordpress.org/plugins/redirection/).
  Redirects and groups that are created, changed, enabled and disabled will be logged. Also when the plugin global settings are changed that will be logged.
- Fix possible notice error from User logger.
- "View changelog" link now works on multisite.

= 2.10 (September 2016) =

- Available updates to plugins, themes, and WordPress itself is now logged.
  Pretty great if you subscribe to the RSS feed to get the changes on a site. No need to manually check the updates-page to see if there are any updates.
- Changed to logic used to determine if a post edit should be logged or not. Version 2.9 used a version that started to log a bit to much for some plugins. This should fix the problems with the Nextgen Gallery, All-In-One Events Calendar, and Membership 2 plugins. If you still have problems with a plugin that is causing to many events to be logged, please let me know!

= 2.9.1 (August 2016) =

- Fixed an issue where the logged time was off by some hours, due to timezone being manually set elsewhere.
  Should fix https://wordpress.org/support/topic/logged-time-off-by-2-hours and https://wordpress.org/support/topic/different-time-between-dashboard-and-logger.
- Fixed Nextgen Gallery and Nextgen Gallery Plus logging lots and lots of event when viewing posts with galleries. The posts was actually updated, so this plugin did nothing wrong. But it was indeed a bit annoying and most likely something you didn't want in your log. Fixes https://wordpress.org/support/topic/non-stop-logging-nextgen-gallery-items.

= 2.9 (August 2016) =

- Added custom date ranges to the dates filter. Just select "Custom date range..." in the dates dropdown and you can choose to see the log between any two exact dates.
- The values in the statistics graph can now be clicked and when clicked the log is filtered to only show logged events from that day. Very convenient if you have a larger number of events logged for one day and quickly want to find out what exactly was logged that day.
- Dates filter no longer accepts multi values. It was indeed a bit confusing that you could select both "Last 7 days" and "Last 3 days".
- Fix for empty previous plugin version (the `{plugin_prev_version}` placeholder) when updating plugins.
- Post and pages updates done in the WordPress apps for Ios and Android should be logged again.

= 2.8 (August 2016) =

- Theme installs are now logged
- ...and so are theme updates
- ...and theme deletions. Awesome!
- Support for plugin [Limit Login Attempts](https://wordpress.org/plugins/limit-login-attempts/).
  Failed login attempts, lockouts and configuration changes will be logged.
- Correct message is now used when a plugin update fails, i.e. the message for key `plugin_update_failed`.
- The original untranslated strings for plugin name and so on are stored when storing info for plugin installs and updates and similar.
- Default number of events to show is now 10 instead of 5.

= 2.7.5 (August 2016) =

- User logins using e-mail are now logged correctly. Previously the user would be logged in successfully but the log said that they failed.
- Security fix: only users with [`list_users`](https://codex.wordpress.org/Roles_and_Capabilities#list_users) capability can view the users filter and use the autocomplete api for users.
  Previously the autocomplete function could be used by all logged in users.
- Add labels to search filters. (I do really hate label-less forms so it's kinda very strange that this was not in place before.)
- Misc other internal fixes

= 2.7.4 (July 2016) =

- Log a warning message if a plugin gets disabled automatically by WordPress because of any of these errors: "Plugin file does not exist.", "Invalid plugin path.", "The plugin does not have a valid header."
- Fix warning error if `on_wp_login()` was called without second argument.
- Fix options diff not being shown correctly.
- Fix notice if no message key did exist for a log message.

= 2.7.3 (June 2016) =

- Removed the usage of the mb\_\* functions and mbstring is no longer a requirement.
- Added a new debug tab to the settings page. On the debug page you can see stuff like how large your database is and how many rows that are stored in the database. Also, a list of all loggers are listed there together with some useful (for developers anyway) information.

= 2.7.2 (June 2016) =

- Fixed message about mbstring required not being echo'ed.
- Fixed notice errors for users not allowed to view the log.

= 2.7.1 (June 2016) =

- Added: Add shortcut to history in Admin bar for current site and in Network Admin Bar for each site where plugin is installed. Can be disabled using filters `simple_history/add_admin_bar_menu_item` and `simple_history/add_admin_bar_network_menu_item`.
- Added: Add check that [´mbstring´](http://php.net/manual/en/book.mbstring.php) is enabled and show a warning if it's not.
- Changed: Changes to "Front Page Displays" in "Reading Settings" now show the name of the old and new page (before only id was logged).
- Changed: Changes to "Default Post Category" and "Default Mail Category" in "Writing Settings" now show the name of the old and new category (before only id was logged).
- Fixed: When changing "Front Page Displays" in "Reading Settings" the option "rewrite_rules" also got logged.
- Fixed: Changes in Permalink Settings were not logged correctly.
- Fixed: Actions done with [WP-CLI](https://wp-cli.org/) was not correctly attributed. Now the log should say "WP-CLI" intead of "Other" for actions done in WP CLI.

= 2.7 (May 2016) =

- Added: When a user is created or edited the log now shows what fields have changed and from what old value to what new value. A much requested feature!
- Fixed: If you edited your own profile the log would say that you edited "their profile". Now it says that you edited "your profile" instead.
- Changed: Post diffs could get very tall. Now they are max approx 8 rows by default, but if you hover the diff (or give it focus with your keyboard) you get a scrollbar and can scroll the contents. Fixes https://wordpress.org/support/topic/dashboard-max-length-of-content and https://wordpress.org/support/topic/feature-request-make-content-diff-report-expandable-and-closed-by-default.
- Fixed: Maybe fix a notice varning if a transient was missing a name or value.

= 2.6 (May 2016) =

- Added: A nice little graph in the sidebar that displays the number of logged events per day the last 28 days. Graph is powered by [Chart.js](http://www.chartjs.org/).
- Added: Function `get_num_events_last_n_days()`
- Added: Function `get_num_events_per_day_last_n_days()`
- Changed: Switched to transients from cache at some places, because more people will benefit from transients instead of cache (that requires object cache to be installed).
- Changed: New constant `SETTINGS_GENERAL_OPTION_GROUP`. Fixes https://wordpress.org/support/topic/constant-for-settings-option-group-name-option_group.
- Fixed: Long log messages with no spaces would get cut of. Now all the message is shown, but with one or several line breaks. Fixes https://github.com/bonny/WordPress-Simple-History/pull/112.
- Fixed: Some small CSS modification to make the page less "jumpy" while loading (for example setting a default height to the select2 input box).

= 2.5.5 (April 2016) =

- Changed: The logger for Enable Media Replace required the capability `edit_files` to view the logged events, but since this also made it impossible to view events if the constant `DISALLOW_FILE_EDIT` was true. Now Enable Media Replace requires the capability `upload_files` instead. Makes more sense. Fixes https://wordpress.org/support/topic/simple-history-and-disallow_file_edit.
- Changed: No longer log spam trackbacks or comments. Before this version these where logged, but not shown.
- Fixed: Translations was not loaded for Select2. Fixes https://wordpress.org/support/topic/found-a-string-thats-not-translatable-v-254.
- Fixed: LogQuery `date_to`-argument was using `date_from`.
- Changed: The changelog for 2015 and earlier are now moved to [CHANGELOG.md](https://github.com/bonny/WordPress-Simple-History/blob/master/CHANGELOG.md).

= 2.5.4 (March 2016) =

- Added: Support for new key in info array from logger: "name_via". Set this value in a logger and the string will be shown next to the date of the logged event. Useful when logging actions from third party plugins, or any kind of other logging that is not from WordPress core.
- Added: Method `getInfoValueByKey` added to the SimpleLogger class, for easier retrieval of values from the info array of a logger.
- Fixed: Themes could no be deleted. Fixes https://github.com/bonny/WordPress-Simple-History/issues/98 and https://wordpress.org/support/topic/deleting-theme-1.
- Fixed: Notice error when generating permalink for event.
- Fixed: Removed a `console.log()`.
- Changed: Check that array key is integer or string. Hopefully fixes https://wordpress.org/support/topic/error-in-wp-adminerror_log.

= 2.5.3 (February 2016) =

- Fixed: Old entries was not correctly removed. Fixes https://github.com/bonny/WordPress-Simple-History/issues/108.

= 2.5.2 (February 2016) =

- Added: The GUI log now updates the relative "fuzzy" timestamps in real time. This means that if you keep the log opened, the relative date for each event, for example "2 minutes ago" or "2 hours ago", will always be up to date (hah!). Keep the log opened for 5 minutes and you will see that the event that previously said "2 minutes ago" now says "7 minutes ago". Fixes https://github.com/bonny/WordPress-Simple-History/issues/88 and is implemented using the great [timeago jquery plugin](http://timeago.yarp.com/).
- Added: Filter `simple_history/user_logger/plain_text_output_use_you`. Works the same way as the `simple_history/header_initiator_use_you` filter, but for the rich text part when a user has edited their profile.
- Fixed: Logger slugs that contained for example backslashes (becuase they where namespaced) would not show up in the log. Now logger slugs are escaped. Fixes https://github.com/bonny/WordPress-Simple-History/issues/103.
- Changed: Actions and things that only is needed in admin area are now only called if `is_admin()`. Fixes https://github.com/bonny/WordPress-Simple-History/issues/105.

= 2.5.1 (February 2016) =

- Fixed: No longer assume that the ajaxurl don't already contains query params. Should fix problems with third party plugins like [WPML](https://wpml.org/).
- Fixed: Notice if context key did not exist. Should fix https://github.com/bonny/WordPress-Simple-History/issues/100.
- Fixed: Name and title on dashboard and settings page were not translateable. Fixes https://wordpress.org/support/topic/dashboard-max-length-of-content.
- Fixed: Typo when user resets password.
- Added: Filter `simple_history/row_header_date_output`.
- Added: Filter `simple_history/log/inserted`.
- Added: Filter `simple_history/row_header_date_output`.
