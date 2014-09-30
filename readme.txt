=== Simple History ===
Contributors: eskapism, MarsApril, Offereins
Donate link: http://eskapism.se/sida/donate/
Tags: history, log, changes, changelog, audit, trail, stream, pages, attachments, users, cms, dashboard, admin, syslog, activity
Requires at least: 3.8.0
Tested up to: 4.0
Stable tag: 1.3.10

View changes made by users within WordPress. See who created a page, uploaded an attachment or approved an comment, and more.

== Description ==

Simple History shows recent changes made within WordPress, directly on your dashboard or on a separate page.

The plugin works as a log/history/audit log/version history of the most important events that occur in WordPress.

Out of the box Simple History has support for:

* **Posts and pages**<br>
see who added, updated or deleted a post or page
* **Attachments**<br>
see who added, updated or deleted an attachment
* **Comments**<br>
see who edited, approved or removed a comment
* **Widgets**<br>
get info when someone adds, updates or removes a widget in a sidebar
* **Plugins**<br>
activation and deactivation
* **User profiles**<br>
info about added, updated or removed users
* **User logins**<br>
see when a user login & logut
* **Failed user logins**<br>
see when someone has tried to log in, but failed. The log will then include ip address of the possible hacker.
* **bbPress**<br>
view changes to forums and topics and view user changes
* **Gravity Forms**<br>
see who created, edited or deleted a form, field, or entry

With it's easy to use Extender system, developers can add their own settings and items to track.

There is also a **RSS feed of changes** available, so you can keep track of the changes made
via your favorite RSS reader on your phone, on your iPad, or on your computer.

It’s a plugin that is good to have on websites where several people are 
involved in editing the content.

#### Example scenarios

Keep track of what other people are doing:
_"Has someone done anything today? Ah, Sarah uploaded 
the new press release and created an article for it. Great! Now I don't have to do that."_

Or for debug purposes:
_"The site feels very slow since yesterday. Has anyone done anything special? ... Ah, Steven activated 'naughy-plugin-x',
that must be it."_

#### See it in action

See the plugin in action with this short screencast:
[youtube http://www.youtube.com/watch?v=4cu4kooJBzs]

#### Add your own events to simple history

If you are a plugin developer and would like to add your own things/events to Simple History
you can do that by calling the function simple_history_add like this:
`<?php

# Check that function exists before trying to use it
# Just in case someone disabled the history plugin or similar
if (function_exists("simple_history_add")) {

	# Log that an email has been sent
	simple_history_add(array(
		"object_type" => "Email",
		"object_name" => "Hi there",
		"action" => "was sent"
	));

	# Will show “Plugin your_plugin_name Edited” in the history log
	simple_history_add("action=edited&object_type=plugin&object_name=your_plugin_name");
	
	# Will show the history item "Starship USS Enterprise repaired"
	simple_history_add("action=repaired&object_type=Starship&object_name=USS Enterprise");

	# Log with some extra details about the email
	simple_history_add(array(
		"object_type" => "Email",
		"object_name" => "Hi there",
		"action" => "was sent",
		"description" => "The database query to generate the email took .3 seconds. This is email number 4 that is sent to this user"
	));

?>
`

#### Add support for your own custom events

There's also a simple class that you can extend to add support for custom history items. It's super simple to use! Take a look at `class.simple-history-extend.php` to get started, and then extending Simple_History_Extend and fill the required methods with their events and log messages.

#### Never clear the history

By default the items in the history log is cleared automatically afer 60 days. 
You can override this behaviour by using a filter, like this:

`
<?php
// Never clear the database
add_action("simple_history_allow_db_purge", function($bool) {
	return false;
});
?>
`

#### Filters

Available filters if you want to modify any behavior

* simple_history_rss_item_title
* simple_history_view_history_capability
* simple_history_show_settings_page
* simple_history_rss_item_description
* simple_history_rss_item_title
* simple_history_show_on_dashboard
* simple_history_show_as_page
* simple_history_allow_db_purge
* simple_history_db_purge_days_interval


#### Translations/Languages

This plugin is available in the following languages:

* English
* German
* Simplified Chinese
* Swedish
* French
* Arabic

Lots of thanks to the translators!

#### Contribute at GitHub
Development of this plugin takes place at GitHub. Please join in with feature requests, bug reports, or even pull requests!
https://github.com/bonny/WordPress-Simple-History

#### Donation and more plugins
* If you like this plugin don't forget to [donate to support further development](http://eskapism.se/sida/donate/).
* More [WordPress CMS plugins](http://wordpress.org/extend/plugins/profile/eskapism) by the same author.

== Installation ==

1. Upload the folder "simple-history" to "/wp-content/plugins/"
1. Activate the plugin through the "Plugins" menu in WordPress
1. Done!

Now Simple History will be visible in a submenu under the dashboard main menu. You can also show it directly on the dashboard by modified Simple History's settings page.

== Feedback ==

Like the plugin? Dislike it? Got bugs or feature request?
Great! Contact me at par.thernstrom@gmail.com or at https://twitter.com/eskapism and hopefully 
I can do something about it.


== Screenshots ==

1. Simple History showing som recent changes to my posts, users and attachments. Also showing several failed login attempts to one of my users.

2. Simple History settings. Choose to show the plugin on your dashboard, or as a separately page. Or both. Or none, since you can choose
to only use the secret RSS feed to keep track of the changes on you web site/WordPress installation.

3. The RSS feed with changes, as shown in Firefox.


== Changelog ==

= 1.3.10 =
- Fix: correct usage of "its"
- Fix: removed serif font in log. Fixes https://wordpress.org/support/topic/two-irritations-and-pleas-for-change.

= 1.3.9 =
- Fixed strict standards warning
- Tested on WordPress 4.0

= 1.3.8 =
- Added filter for rss feed: `simple_history/rss_feed_show`. Fixes more things in this thread: http://wordpress.org/support/topic/more-rss-feed-items.
 
= 1.3.7 =
- Added filter for rss feed: `simple_history/rss_feed_args`. Fixes http://wordpress.org/support/topic/more-rss-feed-items.

= 1.3.6 =
- Added Polish translation
- Added correct XML encoding and header
- Fixed notice warnings when media did not exist on file system

= 1.3.5 =
- Added a reload-button at top. Click it to reload the history. No need to refresh page no more!
- Fixed items being reloaded when just clicking the dropdown (not having selected anything yet)
- Fixed bug with keyboard navigation
- Added Portuguese translation by [X6Web](http://x6web.com)
- Use less SQL queries

= 1.3.4 =
- Changed the way post types show in the dropdown. Now uses plural names + not prefixed with main post type. Looks better I think. Thank to Hassan for the suggestion!
- Added "bytes" to size units that an attachment can have. Also fixes undefined notice warning when attachment had a size less that 1 KB.

= 1.3.3 =
- Capability for viewing settings changed from edit_pages to the more correct [manage_options](http://codex.wordpress.org/Roles_and_Capabilities#manage_options)

= 1.3.2 =
- Could get php notice warning if rss secret was not set. Also: make sure both public and private secret exists.

= 1.3.1 =
- Improved contrast for details view
- Fix sql error on installation due to missing column
- Remove options and database table during removal of plugin
- Added: German translation for extender module

= 1.3 =
- Added: history events can store text description with a more detailed explanation of the history item
- Added: now logs failed login attempts for existing username. Uses the new text description to store more info, for example user agent and remote ip address (REMOTE_ADDR)
- Fixed: box did not change height when clicking on occasions
- Fixed: use on() instead of live() in JavaScript

= 1.2 =
- Fixed: Plugin name is included when plugins is activated or deactivated. Previosuly only folder name and name of php file was included.
- Added: Attachment thumbnails are now visible if history item is an attachment. Also includes some metadata.
- Changed: Filters now use dropdowns for type and user. When a site had lots of users and lots of post types, the filter section could be way to big.
- Added keyboard navigation. Use right and left arrow when you are on Simple History's own page to navigation between next and previous history page.
- Added loading indicator, so you know it's grabbing your history, even if it's taking a while
- Misc JS and CSS fixes
- Arabic translation updated
- POT-file updated

= 1.1 =
- Added the Simple History Extender-module/plugin. With this great addon to Simple History it is very easy for other developers to add their own actions to simple history, including a settings panel to check actions on/off. All work on this module was made by Laurens Offereins (lmoffereins@gmail.com). Super thanks!
- With the help of Simple History Extender this plugin also tracks changes made in bbPress, Gravity Forms and in Widges. Awesome!
- Added user email to RSS feed + some other small changed to make it compatible with IFTTT.com. Thanks to phoenixMagoo for the code changes. Fixes http://wordpress.org/support/topic/suggestions-a-couple-of-tweaks-to-the-rss-feed.
- Added two filters for the RSS feed: simple_history_rss_item_title and simple_history_rss_item_description.
- Changed the way the plugin directory was determined. Perhaps and hopefully this fixes some problems with multi site and plugin in different locations and stuff like that
- Style fixes for RTL languages
- Small fixes here and there, for example changing deprecated WordPress functions to not deprecated
- Added new filter: simple_history_db_purge_days_interval. Hook it to change default clear interval of 60 days.

= 1.0.9 =
- Added French translation

= 1.0.8 =
- Added: filter simple_history_allow_db_purge that is used to determine if the history should be purged/cleaned after 60 days or not. Return false and it will never be cleaned.
- Fixed: fixed a security issue with the RSS feed. User who should not be able to view the feed could get access to it. Please update to this version to keep your change log private!

= 1.0.7 =
- Fixed: Used a PHP shorthand opening tag at a place. Sorry!
- Fixed: Now loads scripts and styles over HTTPS, if that's being used. Thanks to "llch" for the patch.

= 1.0.6 =
- Added: option to set number of items to show, per page. Default i 5 history log items.

= 1.0.5 =
- Fixed: some translation issues, including updated POT-file for translators.

= 1.0.4 =
- You may want to clear the history database after this update because the items in the log will have mixed translate/untranslated status and it may look/work a bit strange.
- Added: Option to clear the database of log items.
- Changed: No longer stored translated history items in the log. This makes the history work even if/when you switch langauge of WordPress.
- Fixed: if for example a post was editied several times and during these edits it changed name, it would end up at different occasions. Now it's correctly stored as one event with several occasions.
- Some more items are translateable

= 1.0.3 =
- Updated German translation
- Some translation fixes

= 1.0.2 =
- Fixed a translation bug
- Added updated German translation

= 1.0.1 =
- The pagination no longer disappear after clickin "occasions"
- Fixed: AJAX loading of new history items didn't work.
- New filter: simple_history_view_history_capability. Default is "edit_pages". Modify this to change what cabability is required to view the history.
- Modified: styles and scripts are only added on pages that use/show Simple History
- Updated: new POT file. So translators my want to update their translations...

= 1.0 =
- Added: pagination. Gives you more information, for example the number of items, and quicker access to older history items. Also looks more like the rest of the WordPress GUI.
- Modified: search now searches type of action (added, modified, deleted, etc.).

= 0.8.1 =
- Fixed some annoying errors that slipt through testing.

= 0.8 =
- Added: now also logs when a user saves any of the built in settings page (general, writing, reading, discussion, media, privacy, and permalinks. What more things do you want to see in the history? Let me know in the [support forum](http://wordpress.org/support/plugin/simple-history).
- Added: gravatar of user performing action is always shown
- Fixed: history items that was posts/pages/custom post types now get linked again
- Fixed: search is triggered on enter (no need to press search button) + search searches object type and object subtype (before it just searched object name)
- Fixed: showing/loading of new history items was kinda broken. Hopefully fixed and working better than ever now.
- Plus: even more WordPress-ish looking!
- Also added donate-links. Tried to keep them discrete. Anyway: please [donate](http://eskapism.se/sida/donate/?utm_source=wordpress&utm_medium=changelog&utm_campaign=simplehistory) if you use this plugin regularly.

= 0.7.2 =
- Default settings should be to show on page, missed that one. Sorry!

= 0.7.1 =
- Fixed a PHP shorttag

= 0.7 =
- Do not show on dashboard by default to avoid clutter. Can be enabled in settings.
- Add link to settings from plugin list
- Settings are now available as it's own page under Settings -> Simple Fields. It was previosly on the General settings page and some people had difficulties finding it there.
- Added filters: simple_history_show_settings_page, simple_history_show_on_dashboard, simple_history_show_as_page

= 0.6 =
- Changed widget name to just "History" instead of "Simple History". Keep it simple. Previous name implied there also was an "Advanced History" somewhere.
- Made the widget look a bit WordPress-ish by borrwing some of the looks from the comments widget.
- Fix for database that didn't use UTF-8 (sorry international users!)
- Some security fixes
- Updated POT-file

= 0.5 =
- Added author to RSS
- Added german translation, thanks http://www.fuerther-freiheit.info/
- Added swedish translation, thanks http://jockegustin.se
- Better support for translation

= 0.4 =
- Added: Now you can search the history
- Added: Choose if you wan't to load/show more than just 5 rows from the history

= 0.3.11 =
- Fixed: titles are now escaped

= 0.3.10 =
- Added chinese translation
- Fixed a variable notice
- More visible ok-message after setting a new RSS secret

= 0.3.9 =
- Attachment names were urlencoded and looked wierd. Now they're not.
- Started to store plugin version number

= 0.3.8 =
- Added chinese translation
- Uses WordPress own human_time_diff() instead of own version
- Fix for time zones

= 0.3.7 =
- Directly after installation of Simple History you could view the history RSS feed without using any secret. Now a secret is automatically set during installation.

= 0.3.6 =
- Made the RSS-feature a bit easier to find: added a RSS-icon to the dashboard window - it's very discrete, you can find it at the bottom right corner. On the Simple History page it's a bit more clear, at the bottom, with text and all. Enjoy!
- Added POT-file

= 0.3.5 =
- using get_the_title instead of fetching the title directly from the post object. should make plugins like qtranslate work a bit better.
- preparing for translation by using __() and _e() functions. POT-file will be available shortly.
- Could get cryptic "simpleHistoryNoMoreItems"-text when loading a type with no items.

= 0.3.4 =
- RSS-feed is now valid, and should work at more places (could be broken because of html entities and stuff)

= 0.3.3 =
- Moved JavaScript to own file
- Added comments to the history, so now you can see who approved a comment (or unapproved, or marked as spam, or moved to trash, or restored from the trash)

= 0.3.2 =
- fixed some php notice messages + some other small things I don't remember..

= 0.3.1 =
- forgot to escape html for posts
- reduced memory usage... I think/hope...
- changes internal verbs for actions. some old history items may look a bit weird.
- added RSS feed for recent changes - keep track of changes via your favorite RSS-reader

= 0.3 =
- page is now added under dashboard (was previously under tools). just feel better.
- mouse over on date now display detailed date a bit faster
- layout fixes to make it cooler, better, faster, stronger
- multiple events of same type, performed on the same object, by the same user, are now grouped together. This way 30 edits on the same page does not end up with 30 rows in Simple History. Much better overview!
- the name of deleted items now show up, instead of "Unknown name" or similar
- added support for plugins (who activated/deactivated what plugin)
- support for third party history items. Use like this:
simple_history_add("action=repaired&object_type=starship&object_name=USS Enterprise");
this would result in somehting like this:
Starship "USS Enterprise" repaired
by admin (John Doe), just now
- capability edit_pages needed to show history. Is this an appropriate capability do you think?

= 0.2 =
* Compatible with 2.9.2

= 0.1 =
* First public version. It works!


