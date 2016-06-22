=== Simple History ===
Contributors: eskapism
Donate link: http://eskapism.se/sida/donate/
Tags: history, log, changes, changelog, audit, trail, pages, attachments, users, cms, dashboard, admin, syslog, feed, activity, stream, audit trail, brute-force
Requires at least: 4.5.1
Tested up to: 4.5.2
Stable tag: 2.7.3

View changes made by users within WordPress. See who created a page, uploaded an attachment or approved an comment, and more.

== Description ==

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin works as a log/history/audit log/version history of the most important events that occur in WordPress.

Out of the box Simple History has support for:

* **Posts and pages**<br>
see who added, updated or deleted a post or page
* **Attachments**<br>
see who added, updated or deleted an attachment
* **Taxonomies (Custom taxonomies, categories, tags)**<br>
see who added, updated or deleted an taxonomy
* **Comments**<br>
see who edited, approved or removed a comment
* **Widgets**<br>
get info when someone adds, updates or removes a widget in a sidebar
* **Plugins**<br>
activation and deactivation
* **User profiles**<br>
info about added, updated or removed users
* **User logins**<br>
see when a user login & logout. Also see when a user fails to login (good way to catch brute-force login attempts).
* **Failed user logins**<br>
see when someone has tried to log in, but failed. The log will then include ip address of the possible hacker.
* **Menu edits**
* **Option screens**<br>
view details about changes made in the differnt settings sections of WordPress. Things like changes to the site title and the permalink structure will be logged.

#### Support for third party plugins

By default Simple History comes with support for these third party plugins:

**User Switching**
The [User Switching plugin](https://wordpress.org/plugins/user-switching/) allows you to quickly swap between user accounts in WordPress at the click of a button. Simple History will log each user switch being made.

**Enable Media Replace**
The [Enable Media Replace plugin](https://wordpress.org/plugins/enable-media-replace/) allows you to replace a file in your media library by uploading a new file in its place. Simple history will log details about the file being replaced and details about the new file.

Support for more plugins are coming.

#### RSS feed available

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

#### See it in action

See the plugin in action with this short screencast:
[youtube http://www.youtube.com/watch?v=4cu4kooJBzs]

#### API so you can add your own events to Simple History

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

* Swedish
* German
* Polish
* Danish
* Dutch
* Finnish
* French
* Russian

I'm looking for translations of Simple History in more languages! If you want to translate Simple History
to your language then read about how this is done over at the [Polyglots handbook](https://make.wordpress.org/polyglots/handbook/rosetta/theme-plugin-directories/#translating-themes-plugins).

#### Contribute at GitHub

Development of this plugin takes place at GitHub. Please join in with feature requests, bug reports, or even pull requests!
https://github.com/bonny/WordPress-Simple-History

#### Donation & more plugins

* If you like this plugin don't forget to [donate to support further development](http://eskapism.se/sida/donate/).
* More [WordPress CMS plugins](http://wordpress.org/extend/plugins/profile/eskapism) by the same author.


== Screenshots ==

1. The log view + it also shows the filter function in use - the log only shows event that
are of type post and pages and media (i.e. images & other uploads), and only events
initiated by a specific user.

2. The __Post Quick Diff__ feature will make it quick and easy for a user of a site to see what updates other users have done to posts and pages.

3. When users are created or changed you can see details on what have changed.

4. Events have context with extra details - Each logged event can include useful rich formatted extra information. For example: a plugin install can contain author info and a the url to the plugin, and an uploaded image can contain a thumbnail of the image.

5. Click on the IP address of an entry to view the location of for example a failed login attempt.

6. See even more details about a logged event (by clicking on the date and time of the event).

7. A chart with some quick statistics is available, so you can see the number of events that has been logged each day.
A simple way to see any uncommon activity, for example an increased number of logins or similar.

== Changelog ==

## Changelog

= 2.7.3 (June 2016) =

- Removed the usage of the mb_* functions and mbstring is no longer a requirement.
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
