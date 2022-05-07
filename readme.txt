=== Simple History – user activity log, audit tool ===
Contributors: eskapism
Donate link: https://www.paypal.me/eskapism
Tags: history, log, changes, changelog, audit, audit log, event log, user tracking, trail, pages, attachments, users, dashboard, admin, syslog, feed, activity, stream, audit trail, brute-force
Requires at least: 5.2
Tested up to: 5.9.3
Requires PHP: 5.6
Stable tag: 3.3.0

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
The [Jetpack plugin](https://wordpress.org/plugins/jetpack/) is a plugin from Automattic (the company behind the WordPress.com service) that lets you supercharge your website by adding a lot of extra functions.
In Simple History you will see what Jetpack modules that are activated and deactivated.
(The creator of Simple History recommends this plugin and its [brute force attack protection](https://jetpack.com/features/security/brute-force-attack-protection/) functions btw. It's a really good way to block unwanted login attempts from malicious botnets and distributed attacks.

**Advanced Custom Fields (ACF)**<br>
[ACF](https://www.advancedcustomfields.com/) adds fields to your posts and pages.
Simple History will log changes made to the field groups and the fields inside field groups. Your will see when both field groups and fields are created and modified.

**User Switching**<br>
The [User Switching plugin](https://wordpress.org/plugins/user-switching/) allows you to quickly swap between user accounts in WordPress at the click of a button.
Simple History will log each user switch being made.

**WP Crontrol**<br>
The [WP Crontrol plugin](https://wordpress.org/plugins/wp-crontrol/) enables you to view and control what's happening in the WP-Cron system.
Simple History will log when cron events are added, edited, deleted, and manually ran, and when cron schedules are added and deleted.

**Enable Media Replace**<br>
The [Enable Media Replace plugin](https://wordpress.org/plugins/enable-media-replace/) allows you to replace a file in your media library by uploading a new file in its place.
Simple history will log details about the file being replaced and details about the new file.

**Limit Login Attempts**<br>
The plugin [Limit Login Attempts](https://wordpress.org/plugins/limit-login-attempts/) is old
and has not been updated for 4 years. However it still has +1 million installs, so many users will benefit from
Simple History logging login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts.

**Redirection**<br>
The [redirection plugin](https://wordpress.org/plugins/redirection/) manages url redirections, using a nice GUI.
Simple History will log redirects and groups that are created, changed, enabled or disabled and also when the global plugin settings have been modified.

**Duplicate Post**<br>
The plugin [Duplicate Post](https://wordpress.org/plugins/duplicate-post/) allows users to
clone posts of any type.
Simple History will log when a clone of a post or page is done.

**Beaver Builder**<br>
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
<a href="https://github.com/bonny/WordPress-Simple-History">https://github.com/bonny/WordPress-Simple-History</a>

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

= 3.3.0 (May 2022) =

- Fixed: Error when third party plugin passed arguments to the `get_avatar` filter. [#288](https://github.com/bonny/WordPress-Simple-History/issues/288)
- Changed: If Gravatars are disabled in WordPress ("Discussion" -> "Show Avatars" is unchecked) then Simple History respects this and also does not show any user avatars in the activity feed. A new filter has been added that can be used to override this: [`simple_history/show_avatars`](https://docs.simple-history.com/hooks#simple_history/show_avatars). [#288](https://github.com/bonny/WordPress-Simple-History/issues/288)
- Update translations. Props @kebbet. See https://docs.simple-history.com/translate for information how to update or add translations of the plugin.
- Use `constant()` function to get constant values. Makes some linting errors go away.
- Remove `languages` folder. [#287](https://github.com/bonny/WordPress-Simple-History/issues/287)

= 3.2.0 (February 2022) =

- Refactored detection of user profile updates. Order of updated user fields are now shown in the same order as they are in the edit user screen. Also the texts are updated to be more user friendly. And those "show toolbar"-messages that showed up at random times should be gone too. 🤞
- Added: Creation and deletion (revoke) of Application Passwords are now logged.
- Added: Role changes from users overview page are now logged.
- Fixed: Password reset links was always attributed to "Anonymous web user", even those that was sent from the users listing in the WordPress admin area.
- Fixed: Increase contrast ratio on some texts.
- Changed: `sh_d()` now tell you if a value is integer or numeric string or an empty string.
- Changed: The log message "Found an update to WordPress" had a dot in it. No other log message had a dot so the dot is no more.

= 3.1.1 (January 2022) =

- Fixed: Error when uploading images when using WordPress 5.7.0 or earlier.

= 3.1.0 (January 2022) =

- Fixed: Use user selected language instead of selected site language when loading languages for JavaScript libraries. ([#232](https://github.com/bonny/WordPress-Simple-History/issues/232))
- Fixed: Theme deletions are now logged again. ([#266](https://github.com/bonny/WordPress-Simple-History/issues/266))
- Fixed: Theme installs are now logged again. ([#265](https://github.com/bonny/WordPress-Simple-History/issues/265))
- Fixed: Plugin deletions are now logged again. ([#247](https://github.com/bonny/WordPress-Simple-History/issues/247), [#122](https://github.com/bonny/WordPress-Simple-History/issues/122))
- Fixed: Images and other attachments are now logged correctly when being inserted in the Block Editor.
- Fixed: Some PHP notice messages in post logger.
- Updated: JavaScript library TimeAgo updated to 1.6.7 from 1.6.3.
- Added: Log when an admin verifies that the site admin adress is valid using the [Site Admin Email Verification Screen that was added in WordPress 5.3](https://make.wordpress.org/core/2019/10/17/wordpress-5-3-admin-email-verification-screen/). ([#194](https://github.com/bonny/WordPress-Simple-History/issues/194), [#225](https://github.com/bonny/WordPress-Simple-History/issues/225))
- Added: Option "All days" to date range filter dropdown. ([#196](https://github.com/bonny/WordPress-Simple-History/issues/196))
- Added: Media and other attachments now display the post they were uploaded to, if any. ([#274](https://github.com/bonny/WordPress-Simple-History/issues/274))
- Added: Add class static variables $dbtable and $dbtable_contexts that contain full db name (existing class constants DBTABLE and DBTABLE_CONTEXTS needed to be prefixed manually).
- Added: Plugin installs now save required version of PHP and WordPress.
- Changed: Plugin install source is now assumed to be "web" by default.
- Changed: Attachment updates are no longer logged from post logger since the media/attachment logger takes care of it.
- Changed: Function `sh_d()` now does not escape output when running from CLI.
- Removed: Plugin source files-listing removed from plugin installs, because the listing was incomplete, plus some more fields that no longer were able to get meaninful values (plugin rating, number or ratings, etc.).

= 3.0.0 (January 2022) =

- Fixed: Used wrong text domain for some strings in Limit Login Attempts logger.
- Fixed: Post logger now ignores changes to the `_encloseme` meta key.
- Fixed: Readme text loaded from GitHub repo is now filtered using `wp_kses()`.
- Fixed: Links in readme text loaded from GitHub repo now opens in new window/tab by default (instead of loading in the modal/thickbox iframe).
- Added: Logger messages is shown when clicking number of message strings in settings debug tab.
- Added: Num occasions in RSS feed is now wrapped in a `<p>` tag.
- Removed: "Simple Legacy Logger" is removed because it has not been used for a very long time.
- Removed: "GitHub Plugin URI" header removed from index file, so installs of Simple History from Github using Git Updater are not supported from now on.
- Removed: Box with translations notice removed from sidebar because it did not work properly when using different languages as site language and user language.
- Internal: Code formatting to better match the WordPress coding standards, code cleanup, text escaping. ([#243](https://github.com/bonny/WordPress-Simple-History/issues/243))

= 2.43.0 (October 2021) =

- Fixed: PHP notices on menu save when there are ACF fields attached ([#235](https://github.com/bonny/WordPress-Simple-History/issues/235))

- Fixed: `array_map` and `reset` cause warning in PHP 8 ([#263](https://github.com/bonny/WordPress-Simple-History/pull/263))

= 2.42.0 (April 2021) =

- Fixed: Quick diff table had to wrong sizes of the table cells. ([#246](https://github.com/bonny/WordPress-Simple-History/issues/246))

= 2.41.2 (March 2021) =

- Fixed: Error when running on PHP version 7.2 or lower.

= 2.41.1 (March 2021) =

- Fixed: Get information for correct IP Address when multiple IP addresses are shown.

= 2.41.0 (March 2021) =

- Fixed: Error when visiting settings screen on PHP 8.
  Fixes https://wordpress.org/support/topic/simple-history-fatal-error/.
  [#239](https://github.com/bonny/WordPress-Simple-History/issues/239)

= 2.40.0 (March 2021) =

- Changed: IP address is now also shown when a user successfully logs in.
  Previously the IP address was only shown for failed login attempts. Note that the IP address/es of all events are always logged and can be seen in the "context data" table that is displayed when you click the date and time of an event.
  [#233](https://github.com/bonny/WordPress-Simple-History/issues/233)

- Added: If multiple IP addresses are detected, for example when a website is running behind a proxy or similar, all IP addresses are now shown for failed and sucessful logins.

- Added: Filter `simple_history/row_header_output/display_ip_address` that can be used to control when the IP address/es should be visible in the main log. By default sucessful and failed logins are shown.

- Added: Show message when failing to get IP address due to for example ad blocker. IPInfo.io is for example blocked in the EasyList filter list that for example [Chrome extension uBlock Origin](https://chrome.google.com/webstore/detail/ublock-origin/cjpalhdlnbpafiamejdnhcphjbkeiagm) uses.

- Added: Filter `simple_history/row_header_output/template` that controls the output of the header row in the main event log.

= 2.39.0 (January 2021) =

- Added: Logging of events that a user performs via the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin (requires WP Crontrol version 1.9.0 or later). Simple History will log when cron events are added, edited, deleted, and manually ran, and when cron schedules are added and deleted. Props https://github.com/johnbillion.

= 2.38.0 (November 2020) =

- Changed: It's now possible to log things before the `after_setup_theme` hook by using the `SimpleLogger()` function. Before this change calling `SimpleLogger()` before `after_setup_theme`, or on `after_setup_theme` with a prio smaller than 10, would result in a fatal error (`Fatal error: Uncaught Error: Class 'SimpleLogger' not found`). Props https://github.com/JoryHogeveen.

- Changed: More custom post types that use the block editor ("Gutenberg") should now have their changes logged. Props https://github.com/claytoncollie.

= 2.37.2 (September 2020) =

- Fixed: Even more code that was to new for PHP 5.6 (I do have some tests, I just didn't look at them `¯\_(ツ)_/¯`.)

= 2.37.1 (September 2020) =

- Fixed: Some code was to new for PHP 5.6.

= 2.37 (September 2020) =

- Added: Enabling or disabling plugin auto-updates is now logged.
- Added: Function `sh_d()` that echoes any number of variables to the screen.
- Fixed: User logouts did show "other" instead of username of user logging out. Fixes #206, https://wordpress.org/support/topic/suspicious-logged-out-events/, https://wordpress.org/support/topic/login-logout-tracking/.
- Updated: lots of code to be formatted more according to PSR12.

= 2.36 (August 2020) =

- Fix plus and minus icons in quick diff.
- Add filter for Post Logger context. (https://github.com/bonny/WordPress-Simple-History/pull/216)
- Add link to my [GitHub sponsors page](https://github.com/sponsors/bonny/) in the sidebar.
- Misc code cleanups and smaller fixes.

= 2.35.1 (August 2020) =

Minor update to correct readme.

= 2.35 (August 2020) =

You can now [sponsor the developer of this plugin at GitHub](https://github.com/sponsors/bonny/).

**Fixed**

- Fix PHP Warning when bulk editing items in the Redirection plugin. Fixes https://github.com/bonny/WordPress-Simple-History/issues/207, https://wordpress.org/support/topic/crashes-with-redirection-plugin/. (https://github.com/bonny/WordPress-Simple-History/commit/e8be051c4d95e598275a7ba17a01f76008eb7a5b)

**Changed**

- Welcome text updated to be more correct. (https://github.com/bonny/WordPress-Simple-History/pull/211)

= 2.34 (June 2020) =

**Changed**

- Use flexbox for history page layout, so if all dropins are disabled then the content area
  spans the entire 100 % width (#199).

- Adjust style of pagination to match WordPress core pagination.

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

[Changelog for 2016 and earlier](https://github.com/bonny/WordPress-Simple-History/blob/master/CHANGELOG.md)
