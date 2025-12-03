# Changelog for 2025

### 5.16.0 (August 2025)

**Added**

-   Revision Post ID, if available, is stored in event context for post/page updates.
-   Stats API responses now include human-readable, localized date formats.

**Changed**

-   The [_Post Activity Panel_](https://simple-history.com/features/post-activity-panel/) feature is now part of the [Simple History Premium](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium) add-on and available for all users of that plugin. It was previously an experimental feature in the main plugin.
-   The number of days for a month in sidebar stats is now 30 days instead of the previous 28 days, to make it more standard and consistent with common business and reporting cycles.
-   The scripts for the [_Admin Bar Quick View_](https://simple-history.com/features/admin-bar-quick-view/) is now loaded using `strategy: 'defer'` to improve performance.

**Fixed**

-   Correct query in get_successful_logins_details(), so it will correctly fetch successful logins.

### 5.15.0 (August 2025)

📧 This release enables _Weekly Email Reports_ for all users.
It also adds a new _Core Files Integrity Logger_ that detects modifications to WordPress core files through daily checksum verification.

[Release post with details and screenshots.](https://simple-history.com/2025/simple-history-5-15-0/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_15_0)

**Added**

-   📨 _Email reports_ are now available for all users, not just those with experimental features enabled. You can enable email reports in the settings page. Read more about [email reports](https://simple-history.com/features/weekly-email-report/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_email_reports).
-   New _Core Files Integrity Logger_ that detects modifications to WordPress core files through daily checksum verification. Monitors for modified, missing, or unreadable files. Runs once every night. [#538](https://github.com/bonny/WordPress-Simple-History/issues/538)

**Changed**

-   Change order of the menu items.
-   Update description of WP CLI command description.

### 5.14.0 (July 2025)

**Added**

-   Add filter options to the WP-CLI events list command [#570](https://github.com/bonny/WordPress-Simple-History/issues/570)
-   Add ungrouped events support for the Log Query API and the REST API. This will get you a list of events without grouping them by occasion. This is useful for getting a simple list of events without the overhead of grouping.
-   Add support for filtering/querying by context in the log query and REST API.
-   Add search filter for initiators. This allows you to filter events by the initiator, e.g. "Web user", "WordPress user", "WP-CLI", "Other". Support is added the search GUI and to the REST API.
-   Add footer to dashboard widget with links to the [blog](https://simple-history.com/blog/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_14_0), [support](https://simple-history.com/support/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_14_0) and [premium features](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_14_0).
-   Add filters to customize plugin update details. Plugin authors can now add custom information about new features and improvements when their plugins are updated. This is done using the filter `simple_history/pluginlogger/plugin_updated_details/{plugin-slug}/{version}`. For example, to add custom details for Simple History version 5.14.0, use the filter `simple_history/pluginlogger/plugin_updated_details/simple-history/5.14.0` :).

**New experimental features**

These features are experimental and may change in future releases.
You need to have experimental features enabled in the settings to use them.

-   Sidebar panel on the Gutenberg block editor showing Simple History events for the current post.
-   Core Files Integrity Logger to detect and monitor modifications to WordPress core files through automated checksum verification.

**Fixed**

-   Fix export functionality not working when accessed from the settings page. [#574](https://github.com/bonny/WordPress-Simple-History/issues/574)
-   Fix collapse of search filters not working. [#569](https://github.com/bonny/WordPress-Simple-History/issues/569)

### 5.13.1 (July 2025)

**Fixed**

-   Fix cache issue when sticking or unsticking events. [#566](https://github.com/bonny/WordPress-Simple-History/issues/566)
-   Fix issue when Divi frontend builder is active. [#565](https://github.com/bonny/WordPress-Simple-History/issues/565)
-   Fix issue when no menu page is found. [#564](https://github.com/bonny/WordPress-Simple-History/issues/564)

**Improved**

-   Improve email summary report (still only available for users with experimental features enabled).
-   Improve license key settings page text to make it more clear that you need to install and activate the add-on first, before you can enter the license key.
-   Auto expand search options when filters are applied via URL parameters. [#567](https://github.com/bonny/WordPress-Simple-History/issues/567)
-   Add more checks in Admin Bar Quick Stats before initing the JS code.
-   Misc internal code improvements and spelling fixes.

### 5.13.0 (June 2025)

📧 This release introduces weekly email reports for site activity monitoring and adds REST API endpoints for event statistics.
[Read the release post](https://simple-history.com/2025/simple-history-5-13-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_13_0) for more details.

**Added**

-   Weekly email reports. Enabled for users with experimental features enabled, this new feature let you get an weekly email with a brief summary of what's been happening on your site. The emails are opt-in so you need to add your email address to the settings to start receiving them.
-   REST API Endpoints for Event Statistics.

**Changed**

-   Improvements to the layout of the quick stats box.
-   Misc internal code improvements.

**Fixed**

-   Fix PHP notice due to `wpdb::prepare()` not using placeholders correctly.

### 5.12.0 (May 2025)

📊 This release enhances the quick stats visualization with improved readability and additional metrics, while also addressing several minor issues.
[Read the release post](https://simple-history.com/2025/simple-history-5-12-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_12_0) for more details.

**Improved**

-   Redesign the "quick stats" box (to the right of the main events listing):
    -   Improve readability of statistics.
    -   Add metrics for today and last 7 days (in addition to existing stats for last 28 days and total events).
    -   Add graphical list of most active users in the last 28 days (visible only to administrators).
    -   Improve chart interaction - hover anywhere on the box to view daily values.
    -   Change chart visualization from bar chart to line chart.

**Fixed**

-   Fix deprecated function warning when searching for events.
-   Fix undefined chart label on Stats and Summaries page.
-   Enhance translation support.

### 5.11.0 (May 2025)

📌 This release introduces Sticky Events support for sticking important events to the top of your log, adds visual dividers for better log overview, and includes several UX improvements.
[Read the release post](https://simple-history.com/2025/simple-history-5-11-0-released-sticky-events-visual-day-dividers/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_11_0) for more details and screenshots.

**Added**

-   Visual day divider labels to the event log, e.g., "Sticky", "Today", "Yesterday", "May 14, 2025", for improved scannability and better log overview.
-   Sticky Events: pin important events to the top of your log.
-   WP-CLI commands to manage sticky events (stick, unstick, list):
    -   wp simple-history event stick
    -   wp simple-history event unstick
    -   wp simple-history event is_sticky
    -   wp simple-history event list_sticky [--format=<format>]
-   Date and ID of the oldest event is now shown on the debug page.

**Fixed**

-   URL is no longer changed when using filters on the dashboard.
-   Hide link to stats and summaries page in quick stats box if user doesn't have permission to view it.

### 5.10.0 (May 2025)

🎯 This release improves performance, enhances the user interface, and adds several quality-of-life improvements to make Simple History more efficient and user-friendly.
[Read the release post](https://simple-history.com/2025/simple-history-5-10-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_10_0) for more details.

**Added**

-   Add option to include header row in CSV exports.
-   Add URL-based search filters for easy bookmarking and sharing of search selections.
-   Add option to view more events from the same user or for the same type of event.

**Changed**

-   Load icons from a separate CSS file for better cache busting.
-   Make interface even clearer by hiding some promo boxes if [Premium](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium) is active.
-   Remove `server_http_user_agent` from user updates, user creation, user deletion. It is still available for user successful and failed logins. If support personal needs to know the user agent, the login event is the best place to find this. Note: if you need event more debug info, you can enable [Detective Mode](https://simple-history.com/support/detective-mode/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_detective_mode) or get the [Debug and Monitor add-on](https://simple-history.com/add-ons/debug-and-monitor/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_debug_monitor).
-   Use priority 1 for `wp_login` action instead of default 10 for better compatibility with auth plugins like [Two Factor](https://wordpress.org/plugins/two-factor/).
-   Improve performance of stats and summaries page.
-   Refactor query `date_from` and `date_to` parsing so sending in format `Y-m-d` means start/end of day automatically.

**Fixed**

-   Add log level `notice` to the GUI filters.
-   Remove duplicate `date_gmt` column from event details table.
-   Hide link to stats and summaries page from quick stats box if user doesn't have permission to view it.
-   Hide notification bar if user can't visit link that is provided for the notification message.
-   Misc internal improvements.
-   Add option to copy event message (with or without details) to clipboard.

### 5.9.0 (April 2025)

📊 This release adds a new [stats and summaries page](https://simple-history.com/features/stats-and-summaries/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_stats_summaries) that gives you a quick overview summary of your site's activity.
[Read more about the new stats and summaries page in the release post](https://simple-history.com/2025/simple-history-5-9-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_9_0).

**Added**

-   New stats and summaries page that gives you a lot of information about your site's activity. For the last month (customizable in [Premium](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium)) you can see things like:
    -   Quick numbers like total events during this period, total number of users that performed actions, number of posts and pages created, etc.
    -   A big chart with the number of events logged each day.
    -   A visual overview of the most active users.
-   Quick access link to [stats and summaries](https://simple-history.com/features/stats-and-summaries/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_stats_summaries) in the quick stats box

-   Hook `simple_history/admin_page/after_header` to allow plugins to add content after the header in Simple History admin pages.
-   A discrete notification bar at top.

**Fixed**

-   Remove upsell boxes in settings page when [Premium](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium) is active

**Changed**

-   Verified compatibility with WordPress 6.8

### 5.8.2 (April 2025)

🧩 This release improves compatibility with older WordPress versions and fixes some issues.

**Added**

-   Add filter `simple_history/post_logger/meta_keys_to_ignore` to modify the array with custom field keys to ignore. [#543](https://github.com/bonny/WordPress-Simple-History/issues/543)

**Changed**

-   Add compatibility with WordPress down to version 6.3 (from 6.6 previously). This makes it possible for users on older versions of WordPress to use the plugin. This was possible thanks to the great https://github.com/johnbillion/wp-compat library. [#542](https://github.com/bonny/WordPress-Simple-History/issues/542)
-   Mask more password related fields when using [Detective Mode](https://simple-history.com/support/detective-mode/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_detective_mode). [#546](https://github.com/bonny/WordPress-Simple-History/issues/546)

### 5.8.1 (march 2025)

🔧 This release adds several UI improvements and internal enhancements to make Simple History more user-friendly and robust.

**Added**

-   Add reload button when events fail to load (typically due to an expired nonce from admin inactivity).
-   Add review notice for admins after many events has been logged to encourage [leaving a review](https://wordpress.org/support/plugin/simple-history/reviews/#new-post?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_review). (It keeps me motivated, you know.) It will only be shown on the admin pages that belong to Simple History.

**Changed**

-   Rename debug page to "Help & Support" for better clarity.
-   Improve dropin loading by automatically finding dropins in the dropins folder.

**Fixed**

-   Remove unnecessary div element in diff output.
-   Enhance footer text handling to properly manage boolean and non-string inputs.

### 5.8.0 (March 2025)

🔍 This release adds support for custom log entries and also adds logging when a user gets an access denied message when trying to view an admin page that they do not have access to.
[Read the release post](https://simple-history.com/2025/simple-history-5-8-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_8_0) for more details and screenshots.

**Added**

-   Add logging of admin page access denied events. When a user attempts to access an admin page they don't have permission for, this is now logged in the User Logger.
-   Add new `Custom_Entry_Logger` logger that adds support for custom entries to be added via WP-CLI and REST API.
    -   Only administrators (users with `manage_options` capability) can add custom log entries using the REST API.
    -   Users with access to WP-CLI can add custom log entries.
    -   See the release post for examples and screenshots of how to use custom entries.
    -   (There is also an option in the Premium add-on to add custom entries via the UI.).

**Fixed**

-   Fix rare options key missing error when retrieving logger search options.

### 5.7.0 (February 2025)

🔄 This release adds more menu location options and some other smaller improvements to the interface and internal code.
[Read the release post](https://simple-history.com/2025/simple-history-5-7-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_7_0) for more details and screenshots.

**Added**

-   Add new menu location options "Inside dashboard menu item" and "Inside tools menu item" (in addition to the available "Top of main menu" and "Bottom of main menu").
    -   The "Inside dashboard menu item" option will add the main history log page to the Dashboard page, while the settings page for the plugin will be located under the Settings menu item. This is pretty much the same location as before the 5.5.0 update.
    -   The location can be set using filter `simple_history/admin_menu_location`.
-   Total number of events logged since install in now shown in the [Stats & Insights box](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_stats_insights).

**Changed**

-   Enhancement: Format number of events in Stats & Insights.
-   Update menu settings name from "Menu page location" to "History menu position".
-   Improve location of settings errors.
-   Improve logic for determine if the current admin page belongs to Simple History or not. Improves compatibility with translation plugins. [#531](https://github.com/bonny/WordPress-Simple-History/issues/531)

**Fixed**

-   Fix warning for [deprecated bottom styles in SelectControl component](https://make.wordpress.org/core/2024/10/18/editor-components-updates-in-wordpress-6-7/#bottom-margin-styles-are-deprecated).
-   Show correct [limit login attempts link](https://simple-history.com/add-ons/premium/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_limit_login#limit-failed-logins) for [premium](https://simple-history.com/add-ons/premium?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_premium#features) users.
-   Remove setting "Show history: as a page under the dashboard menu", since the history menu now can be set to multiple locations.

**Other**

-   Deprecate functions `register_settings_tab()`, `get_main_nav_html()`, `get_subnav_html()`, `get_settings_tabs()`.
-   Misc internal improvements and changes.

### 5.6.1 (January 2025)

🚀 This release fixes incomplete exports due to an error in pagination logic.
It also improves the post Quick Diff view by preventing scrollbar jumping on hover states.
A small but very nice improvement! [See the difference in the release post.](https://simple-history.com/2025/simple-history-5-6-1-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_6_1)

**Fixed**

-   Incomplete exports due to error in pagination logic.
-   PHP notice when exporting events with missing user email data.

**Improved**

-   Enhance post Quick Diff view by preventing scrollbar jumping on hover states. [#530](https://github.com/bonny/WordPress-Simple-History/issues/530)

### 5.6.0 (January 2025)

🔝 This version adds an option to the settings page to control the location of the menu page (at top or bottom).
🫣 It also adds support for **Stealth Mode**: When enabled, Simple History will be hidden from places like the dashboard, the admin menu, the admin bar, and the plugin list.
👉 Read the [release post](https://simple-history.com/2025/simple-history-5-6-released-with-stealth-mode/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_6_0) for more details and examples how to use this feature.

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
Read the [release post](https://simple-history.com/2025/simple-history-5-5-0-released/?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_release_5_5_0) for more details.

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

[Changelog for previous versions.](https://github.com/bonny/WordPress-Simple-History/blob/main/CHANGELOG.md?utm_source=wordpress_org&utm_medium=plugin_directory&utm_campaign=documentation&utm_content=readme_doc_changelog)

## Changelog for 2024 and earlier

### 5.4.3 (December 2024)

-   Fix for _Admin Bar Quick View_ setting not being saved correctly.

### 5.4.2 (December 2024)

⚡ This release contains new features and improvements.
[Read the release post for more details](https://simple-history.com/2024/simple-history-5-4-0/).

**Added**

-   Enable [Admin Bar History Quick View](https://simple-history.com/2024/simple-history-5-1-0-released-with-new-experimental-feature/#:~:text=Try%20out%20our%20latest%20upcoming%20feature%3A%20the%20Admin%20Bar%20Quick%20View) by default - making it easier to check the latest events without leaving your current page.
-   New [WP-CLI commands for interacting with the events log](https://simple-history.com/features/wp-cli-commands/):
    -   `wp simple-history event list` to list events (alias to existing `wp simple-history list` command).
    -   `wp simple-history event get` to get details about a single event.
    -   `wp simple-history event search` to search events.
    -   `wp simple-history db stats` to get stats.
    -   `wp simple-history db clear` to clear the events database.
-   HTML export format support - exports an unstyled HTML file viewable in web browsers.
-   Loading skeleton for events log.
-   Show a nicer message when no results found.
-   Error message display when log fails to load, showing server error messages for easier troubleshooting.

**Changed**

-   Always show event item actions to make them more discoverable - no more need to hover to see available actions.
-   Move Quick View reload button above event list.
-   More accurate logging when creating users - now shows if "Send the new user an email about their account" was checked instead of assuming the email was sent [#493](https://github.com/bonny/WordPress-Simple-History/issues/493)
-   Log when posts/pages are moved to trash using Gutenberg editor. [#491](https://github.com/bonny/WordPress-Simple-History/issues/491)

**Fixed**

-   Fix PHP notice when logging found plugin updates with invalid plugin slugs. [#497](https://github.com/bonny/WordPress-Simple-History/pull/497)
-   Fix error message `widget_setting_too_many_options` when saving widgets in classic theme with Classic Widgets plugin. [#498](https://github.com/bonny/WordPress-Simple-History/issues/498)

### 5.4.0 & 5.4.1 (December 2024)

-   Internal versions to fix and test automatic deploy issues.

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
-   Add Events Control Bar above events. This bar contains information about number of events, paginations, and actions dropdown with actions that are available for the log.
-   Add Slot `SimpleHistorySlotEventsControlBarMenu`.

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

### 4.12.0 (Februari 2024)

**Added**

-   Theme activation/switch done via WP CLI (e.g. `wp theme activate twentytwentyone`) is now logged.

**Fixed**

-   Message type search/filter not working. [#428](https://github.com/bonny/WordPress-Simple-History/issues/428)
-   PHP notice when user has no roles. [#429](https://github.com/bonny/WordPress-Simple-History/issues/429).

### 4.11.0 (February 2024)

This version introduces improved user role support and enhanced export functionality. For more details and screenshots, check out the [release post](https://simple-history.com/2024/simple-history-4-11-0/).

**Added**

-   Improved support for detecting and displaying changes to user role(s), including showing the adding and removal of multiple roles. This improvement is tested with the [Member](https://wordpress.org/plugins/members/) plugin and the [Multiple Roles](https://wordpress.org/plugins/multiple-roles/) plugin. [#424](https://github.com/bonny/WordPress-Simple-History/issues/424).
-   Column with user role(s) are included in the CSV and JSON exports. [#423](https://github.com/bonny/WordPress-Simple-History/issues/423).
-   Column with event date based on current timezone added to CSV export, in addition the the existing GMT date. [#422](https://github.com/bonny/WordPress-Simple-History/issues/422).

**Fixed**

-   Ensure only strings are escaped in csv export. [#426](https://github.com/bonny/WordPress-Simple-History/issues/426).

### 4.10.0 (January 2024)

This version introduces new features and improvements, including an enhanced first experience for new users. For more details and screenshots, check out the [release post](https://simple-history.com/2024/simple-history-4-10-0/).

**Added**

-   Add logging of terms (custom taxonomies and built in tags and categories supported) added or removed to a post. [#214](https://github.com/bonny/WordPress-Simple-History/issues/214).

**Improved**

-   Terms that are added, removed, or modified are now grouped. [#398](https://github.com/bonny/WordPress-Simple-History/issues/398).
-   Show a more user-friendly and informative welcome message after installation. [#418](https://github.com/bonny/WordPress-Simple-History/issues/418).

**Fixed**

-   Missing translation in sidebar. [#417](https://github.com/bonny/WordPress-Simple-History/issues/417).
-   'Activated plugin "{plugin_name}"' message after first install.
-   Duplicated plugin installed and activated messages after first install. [#317](https://github.com/bonny/WordPress-Simple-History/issues/317).

**Removed**

-   Remove usage of [load_plugin_textdomain()](https://developer.wordpress.org/reference/functions/load_plugin_textdomain/) since it's not required for plugins that are translated via https://translate.wordpress.org/. [#419](https://github.com/bonny/WordPress-Simple-History/issues/419).

### 4.9.0 (January 2024)

This release comes with improvements to the SQL queries that the plugin use to fetch events. These optimizations enhance query performance and reliability on both MySQL and MariaDB. Additionally, the plugin now provides support for SQLite databases.

Read the [release post](https://simple-history.com/?p=2229) for more information.

-   Added: support for SQLite Database. Tested with the WordPress [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) feature plugin. See [Let's make WordPress officially support SQLite](https://make.wordpress.org/core/2022/09/12/lets-make-wordpress-officially-support-sqlite/) and [Help us test the SQLite implementation](https://make.wordpress.org/core/2022/12/20/help-us-test-the-sqlite-implementation/) for more information about the SQLite integration in WordPress and the current status. Fixes [#394](https://github.com/bonny/WordPress-Simple-History/issues/394) and [#411](https://github.com/bonny/WordPress-Simple-History/issues/411).
-   Added: Support for plugin preview button that soon will be available in the WordPress.org plugin directory. This is a very nice way to quickly test plugins in your web browser. Read more in blog post ["Plugin Directory: Preview button revisited"](https://make.wordpress.org/meta/2023/11/22/plugin-directory-preview-button-revisited/) and follow progress in [trac ticket "Add a Preview in Playground button to the plugin directory"](https://meta.trac.wordpress.org/ticket/7251). You can however already test the functionality using this link: [Preview Simple History plugin](https://playground.wordpress.net/?plugin=simple-history&blueprint-url=https://wordpress.org/plugins/wp-json/plugins/v1/plugin/simple-history/blueprint.json).
-   Added: IP addresses are now shown on occasions.
-   Added: Helper functions `get_cache_group()`, `clear_cache()`.
-   Changed: Better support for MariaDB and MySQL 8 by using full group by in the query. Fixes multiple database related errors. Fixes [#397](https://github.com/bonny/WordPress-Simple-History/issues/397), [#409](https://github.com/bonny/WordPress-Simple-History/issues/409), and [#405](https://github.com/bonny/WordPress-Simple-History/issues/405).
-   Changed: Misc code cleanup and improvements and GUI changes.
-   Removed: Usage of `SQL_CALC_FOUND_ROWS` since it's deprecated in MySQL 8.0.17. Also [this should make the query faster](https://stackoverflow.com/a/188682). Fixes [#312](https://github.com/bonny/WordPress-Simple-History/issues/312).
-   Removed: Columns "rep", "repeated" and "occasionsIDType" are removed from return value in `Log_Query()`.
-   Fixed: Stats widget counting could be wrong due to incorrect loggers included in stats query.

### 4.8.0 (December 2023)

🧩 This release contains minor fixes, some code cleanup, and adds [support for add-ons](https://simple-history.com/2023/simple-history-4-8-0-introducing-add-ons/)!

-   Add support for add-ons. Add-ons are plugins that extends Simple History with new features. The first add-on is [Simple History Extended Settings](https://simple-history.com/add-ons/extended-settings?utm_source=wpadmin) that adds a new settings page with more settings for Simple History.
-   Add `last_insert_data` property to `Logger` class.
-   Fix position of navigation bar when admin notice with additional class "inline" is shown. Fixes [#408](https://github.com/bonny/WordPress-Simple-History/issues/408).
-   Update logotype.
-   Fix notice when visiting the "hidden" options page `/wp-admin/options.php`.
-   Move functions `get_pager_size()`, `get_pager_size_dashboard()`, `user_can_clear_log()`, `clear_log()`, `get_clear_history_interval()`, `get_view_history_capability()`, `get_view_settings_capability()`, `is_on_our_own_pages()`, `does_database_have_data()`, `setting_show_on_dashboard()`, `setting_show_as_page()`, `get_num_events_last_n_days()`, `get_num_events_per_day_last_n_days()`, `get_unique_events_for_days()` from `Simple_History` class to `Helpers` class.
-   Remove unused function `filter_option_page_capability()`.
-   Update coding standards to [WordPressCS 3](https://make.wordpress.org/core/2023/08/21/wordpresscs-3-0-0-is-now-available/).
-   Misc code cleanup and improvements.

### 4.7.2 (October 2023)

-   Changed: Check that a service class exists before trying to instantiate it.
-   Added [Connection Business Directory](https://simple-history.com/2023/connections-business-directory-adds-support-for-simple-history/) to list of plugins with Simple History support.
-   Added new icons! ✨
-   Tested on WordPress 6.4.

### 4.7.1 (October 2023)

-   Fix: Only context table was cleared when clearing the database. Now also the events table is cleared.
-   Add function `AddOns_Licences::get_plugin()`.
-   Misc internal code cleanup and improvements.

### 4.7.0 (October 2023)

Most notable in this release is the new logotype and a new shortcut to the "Settings & Tools" page.
[Read the release post for more info](https://simple-history.com/2023/simple-history-4-7-0/).

-   Changed: UI changes, including a new logo and a shortcut to the settings page.
-   Add function `get_view_history_page_admin_url()`.
-   Add filter `simple_history/log_row_details_output-{logger_slug}` to allow modifying the output of the details of a log row.
-   Misc internal code cleanup and improvements.

### 4.6.0 (September 2023)

This release contains some new filters and some other improvements.
[See the release post for more info](https://simple-history.com/2023/simple-history-4-6-0/).

-   Added: Filter `simple_history/get_log_row_plain_text_output/output` to be able to modify the output of the plain text output of a log row. Solves support thread [Is it possible to log post ID](https://wordpress.org/support/topic/is-it-possible-to-log-post-id/). See [documentation page for filter](https://simple-history.com/docs/hooks/#simplehistorygetlogrowplaintextoutputoutput) for details.
-   Added: Filter `simple_history/log_insert_data_and_context` to be able to modify the data and context that is inserted into the log.
-   Added: WP-CLI command now includes "via" in output.
-   Added: Debug settings tab now shows if a logger is enabled or disabled.
-   Changed: WP-CLI: ID field is not the first column and in uppercase, to follow the same format as the other wp cli commands use.
-   Changed: GUI enhancements on settings page.
-   Changed: Don't log WooCommerce post type `shop_order_placehold`, that is used by WooCommerce new [High-Performance Order Storage (HPOS)](https://developer.woocommerce.com/2022/10/11/hpos-upgrade-faqs/).
-   Fixed: Allow direct access to protected class variable `$logger->slug` but mark access as deprecated and recommend usage of `$logger->get_slug()`. Fixes support thread [PHP fatal error Cannot access protected property $slug](https://wordpress.org/support/topic/php-fatal-error-cannot-access-protected-property-slug/).

### 4.5.0 (August 2023)

This release contains some smaller new features and improvements.
[See the release post for more info](https://simple-history.com/simple-history-4-5-0/).

**Added**

-   The debug page now detects if the required tables are missing and shows a warning. This can happen when the database of a website is moved between different servers using software that does not know about the tables used by Simple History. Fixes issue [#344](https://github.com/bonny/WordPress-Simple-History/issues/344) and support thread [Missing table support](https://wordpress.org/support/topic/missing-table-support/) among others.
-   Add filters `simple_history/feeds/enable_feeds_checkbox_text` and `simple_history/feeds/after_address`.
-   Add action `simple_history/settings_page/general_section_output`.
-   Add filter `simple_history/db/events_purged` that is fired after db has been purged from old events.
-   Add helper functions `required_tables_exist()`, `get_class_short_name()`.
-   Add function `get_slug()` to `Dropin` class.
-   Add function `get_rss_secret()` to `RSS_Dropin` class.
-   Show review hint at footer on settings page and log page.
-   Add functions `get_instantiated_dropin_by_slug()`, `get_external_loggers()`, `set_instantiated_loggers()`, ` set_instantiated_dropins()`, `get_instantiated_services()` to `Simple_History` class.
-   Dropins and services are now listed on the debug page.

**Changed**

-   Order of settings tab can now be set with key `order` in the array passed to `add_settings_tab()`.
-   Rename network admin menu item "Simple History" to "View History" to use to same name as the admin menu item.
-   Purged events are logged using the simple history logger (instead of directly in the purge function).
-   Refactor code and move core functionality to multiple service classes.

### 4.4.0 (August 2023)

This version of Simple history is tested on the just released [WordPress 6.3](https://wordpress.org/news/2023/08/lionel/). It also contains some new features and bug fixes.

[Release post for Simple History 4.4.0](https://simple-history.com/2023/simple-history-4-4-0/).

**Added**

-   Logger for logging changes to the Simple History settings page. 🙈 And yes, it was quite embarrassing that the plugin itself did not log its activities.
-   RSS feed now accepts arguments to filter the events that are included in the feed. This makes it possible to subscribe to for example only WordPress core updates, or failed user logins, or any combination you want. See the documentation page for [available arguments and some examples](https://simple-history.com/docs/feeds/). [#387](https://github.com/bonny/WordPress-Simple-History/issues/387)
-   Event ID of each entry is included in WP-CLI output when running command `wp simple-history list`.
-   Filter `simple_history/settings/log_cleared` that is fired after the log has been cleared using the "Clear log now" button on the settings page.
-   Add helper function `is_plugin_active()` that loads the needed WordPress files before using the WordPress function with the same name. Part of fix for [#373](https://github.com/bonny/WordPress-Simple-History/issues/373).

**Fixed**

-   Shop changes to post type `customize_changeset`. Fix issue [#224](https://github.com/bonny/WordPress-Simple-History/issues/224) and support threads [stop the “Updated changeset” and “Move changeset” notifications](https://wordpress.org/support/topic/stop-the-updated-changeset-and-move-changeset-notifications/), [Newbie question](https://wordpress.org/support/topic/newbie-question-65/).
-   Scrollbar on dashboard on RTL websites. Fixes issue [#212](https://github.com/bonny/WordPress-Simple-History/issues/212), support thread [Horizontal Scroll](https://wordpress.org/support/topic/horizontal-scroll-16/).
-   PHP error when showing a log entry when all core loggers are disabled. Fixes [#373](https://github.com/bonny/WordPress-Simple-History/issues/373).

**Changed**

-   Tested on WordPress 6.3.
-   Use `uniqid()` as cache invalidator instead of `time()`. Querying the log multiple times during the same PHP request with the same arguments, adding entries to the log between each log query, the same results would be returned.
-   Function `get_event_ip_number_headers()` moved from Simple Logger class to Helpers class.
-   Misc internal code cleanup.

### 4.3.0 (July 2023)

**Added**

-   Add action `simple_history/rss_feed/secret_updated` that is fired when the secret for the RSS feed is updated.
-   Add tests for RSS feed.

**Fixed**

-   RSS feed: Use `esc_xml` to escape texts. Fixes support thread [XML error with RSS feed](https://wordpress.org/support/topic/xml-error-with-rss-feed/), issue [#364](https://github.com/bonny/WordPress-Simple-History/issues/364).
-   RSS feed: Some texts was double escaped.
-   Plugin User Switching: store login and email context of user performing action, so information about a user exists even after user deletion. [#376](https://github.com/bonny/WordPress-Simple-History/issues/376).

### 4.2.1 (July 2023)

**Fixed**

-   Fix PHP error when running WP-Cron jobs on PHP 8 and something was to be logged. Fixes [#370](https://github.com/bonny/WordPress-Simple-History/issues/370) and support threads [wordpress.org/support/topic/fatal-error-4492/](https://wordpress.org/support/topic/fatal-error-4492/), [wordpress.org/support/topic/fatal-error-4488/](https://wordpress.org/support/topic/fatal-error-4488/), [wordpress.org/support/topic/php-error-in-lastest-version/](https://wordpress.org/support/topic/php-error-in-lastest-version/).

### 4.2.0 (July 2023)

**Added**

-   Filter `simple_history/day_of_week_to_purge_db` to set the day that the db should be cleared/purged on. 0 = monday, 7 = sunday. Default is 7.
-   Add class `SimpleHistory` so old code like `SimpleHistory->get_instance()` will work.
-   Add helper function `camel_case_to_snake_case()`.
-   Automatically convert camelCase function names to snake_case function names when calling functions on the `\Simple_History` class. This way more old code and old examples will work. Fixes for example [support thread](https://wordpress.org/support/topic/uncaught-error-class-simplehistory/).
-   Add `Helpers::privacy_anonymize_ip()`.
-   Add filter `simple_history/privacy/add_char_to_anonymized_ip_address` to control if a char should be added to anonymized IPV4 addresses.
-   Add filter `simple_history/maps_api_key` to set a Google Maps API key to be used to show a Google Map of the location of a user login using the user IP address.
-   If a Google Maps API key is set then a map of a users location is shown when clicking on the IP address of a logged event. [#249](https://github.com/bonny/WordPress-Simple-History/issues/249).

**Fixed**

-   Fix `Undefined property` warning when loading more similar events. [#357](https://github.com/bonny/WordPress-Simple-History/issues/357)
-   Include "Plugin URI" from plugin when logging single plugin installs. [#323](https://github.com/bonny/WordPress-Simple-History/issues/323)
-   Check that installed theme has a `destination_name`. [#324](https://github.com/bonny/WordPress-Simple-History/issues/324)
-   Log correct role for user when adding a user on a subsite on a network/multisite install. [#325](https://github.com/bonny/WordPress-Simple-History/issues/325)
-   Check that required array keys exists in theme- and translation loggers. Fixes [support thread](https://wordpress.org/support/topic/strange-error-message-during-updates/), issue [#339](https://github.com/bonny/WordPress-Simple-History/issues/339).
-   Fix undefined index warning in logger when context was missing `_user_id`, `_user_email`, or `_user_login`. Fix [#367](https://github.com/bonny/WordPress-Simple-History/issues/367).
-   Misc code cleanup and improvements.
-   Fix spellings, as found by [Typos](https://github.com/crate-ci/typos/).

**Changed**

-   Move function `get_avatar()` to helpers class.
-   Change location of filter `gettext` and `gettext_with_context` and unhook when filter is not needed any more, resulting in much fewer function calls.
-   IPV4 addresses that are anonymized get a ".x" added last instead of ".0" to make it more clear to the user that the IP address is anonymized.

**Removed**

-   Remove unused schedule `simple_history/purge_db`.
-   Remove function `filter_gettext_store_latest_translations()`.
-   Remove support for automatically un-translating messages to the log, loggers are better and have better support for languages.

### 4.1.0 (July 2023)

**Added**

-   Actions `simple_history/pause` and `simple_history/resume` to pause and resume logging. Useful for developers that for example write their own data importers because the log can be overwhelmed with data when importing a lot of data. [#307](https://github.com/bonny/WordPress-Simple-History/issues/307)
-   `clear_log()` now returns the number of rows deleted.
-   Added `disable_taxonomy_log()` to simplify disabling logging of a taxonomy.
-   Function `get_db_table_stats()` that returns for example the number of rows in each table.

**Fixed**

-   Check that array keys `attachment_parent_title` and `attachment_parent_post_type` exists in Media Logger. [#313](https://github.com/bonny/WordPress-Simple-History/issues/313)
-   Don't log when terms are added to author taxonomy in [Co-Authors Plus](https://wordpress.org/plugins/co-authors-plus/). Fixes [support thread](https://wordpress.org/support/topic/co-author-plus-spamming-simple-history-plugin-is-this-a-but-or-a-feature/), issue [#238](https://github.com/bonny/WordPress-Simple-History/issues/238).
-   Don't load the log or check for updates on dashboard if the widget is hidden.
-   Don't check for updates on dashboard if a request is already ongoing.

**Changed**

-   Moved filter `simple_history/dashboard_pager_size` to method `get_pager_size_dashboard()`.
-   Move function `get_initiator_text_from_row()` to `Log_Initiators` class.
-   If a filter is modifying the pager sizes then show a readonly text input with pager size instead of a dropdown select. [#298](https://github.com/bonny/WordPress-Simple-History/issues/298)
-   Update Chart.js library from 2.0.2 to 4.3.0. Fixes [support thread](https://wordpress.org/support/topic/outdated-chartjs-component-used/), issue [#340](https://github.com/bonny/WordPress-Simple-History/issues/340).

### 4.0.1 (June 2023)

**Fixed**

-   Replace multibyte functions with non-multibyte versions, since `mbstring` is not a [required PHP extension](https://make.wordpress.org/hosting/handbook/server-environment/#php-extensions) (it is however a highly recommended one). Should fix https://wordpress.org/support/topic/wordpress-critical-error-9/. ([#351](https://github.com/bonny/WordPress-Simple-History/issues/351))

### 4.0.0 (June 2023)

🚀 This update of Simple History contains some big changes – that you hopefully won't even notice.

For regular users these are the regular additions and bug fixes:

**Changed**

-   Minimum required PHP version is 7.4. Users with lower versions can use [version 3.4.0 of the plugin](https://downloads.wordpress.org/plugin/simple-history.3.3.0.zip).
-   Minimum required WordPress version is 6.1.
-   Categories logger does not log changes to taxonomy `nav_menu` any longer, since the Menu logger takes care of those, i.e. changes to the menus.

**Added**

-   Log if "Send personal data export confirmation email" is checked when adding a Data Export Request.
-   Log when a Data Export Request is marked as complete.
-   Log when Personal Data is erased by an admin.
-   Log when a group is modified in Redirection plugin.
-   Added context key `export_content` to export logger. The key will contain the post type exported, or "all" if all content was exported.

**Fixed**

-   Fix error on MariaDB databases when collation `utf8mb4_unicode_520_ci` is used for the Simple history tables. Reported at: [https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/](https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/).
-   Privacy logger is logging the creation and selection of privacy page again. It stopped worked because [a WordPress core file was renamed](https://core.trac.wordpress.org/ticket/43895).
-   Log when a groups is enabled, disabled, or deleted in Redirection plugin.

👩‍💻 For developers there are some bigger changes, that is noticeable:

-   The plugin now uses namespaces – and they are loaded using an autoloader.
-   The code has been changed to follow [WordPress coding standard](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/). This means that for example all functions have been renamed from `myFunctionName()` to `my_function_name()`.
-   The update to PHP 7.4 as the minimum required PHP version makes code more modern in so many ways and makes development easier and more funny, since we don't have to worry about backwards compatibility as much.
-   Many more tests using [wp-browser](https://wpbrowser.wptestkit.dev/) have has been added to minimize risk of bugs or fatal errors.

A more detailed changelog that probably most developers are interested in:

**Added**

-   Add cached = true|false to AJAX JSON answer when fetching events or checking for new events. It's a simple way to see if an object cache is in use and is working.
-   Most of the code now uses namespaces.
    -   The main namespace is `Simple_History`.
    -   The main class is `Simple_History\Simple_History`.
    -   Dropins use namespace `Simple_History\Dropins` and dropins must now extend the base class `Dropin`.
    -   Loggers use namespace `Simple_History\Loggers` and loggers must extend the base class `Logger`.
-   Add hooks that controls loggers and their instantiation: `simple_history/core_loggers`, `simple_history/loggers/instantiated`.
-   Add hooks that controls dropins and their instantiation: `simple_history/dropins_to_instantiate`, `simple_history/core_dropins`, `simple_history/dropins_to_instantiate`, `simple_history/dropin/instantiate_{$dropin_short_name}`, `simple_history/dropin/instantiate_{$dropin_short_name}`, `simple_history/dropins/instantiated`.
-   Add filter `simple_history/ip_number_header_names`.
-   Add methods `get_events_table_name()` and `get_contexts_table_name()`.
-   Call method `loaded()` on dropins when they are loaded (use this instead of `__construct`).
-   Make sure that a dropin class exists before trying to use it.

**Changed**

-   Improved code organization with the introduction of namespaces. Code now uses namespaces and classes (including loggers and dropins) are now loaded using an autoloader.
-   Functions are renamed to use `snake_case` (WordPress coding style) instead of `camelCase` (PHP PSR coding style). Some examples:
    -   `registerSettingsTab` is renamed to `register_settings_tab`.
-   Remove usage of deprectead function `wp_get_user_request_data()`.
-   Rename message key from `data_erasure_request_sent` to `data_erasure_request_added`.
-   Rename message key from `data_erasure_request_handled` to `data_erasure_request_completed`.
-   Applied code fixes using Rector and PHPStan for better code quality.
-   Add new class `Helpers` that contain helper functions.
-   Move functions `simple_history_get_current_screen()`, `interpolate()`, `text_diff`, `validate_ip`, `ends_with`, `get_cache_incrementor` to new helper class.
-   Function `get_ip_number_header_keys` is moved to helper class and renamed `get_ip_number_header_names`.
-   Class `SimpleHistoryLogQuery` renamed to `Log_Query`.
-   Class `SimpleLoggerLogLevels` renamed to `Log_Levels`.
-   Class `SimpleLoggerLogInitiators` renamed to `Log_Initiators`.
-   Dropin files are renamed.
-   Move init code in dropins from `__construct()` to new `loaded()` method.
-   Rename `getLogLevelTranslated()` to `get_log_level_translated()` and move to class `log_levels`.
-   Rename message key `user_application_password_deleted` to `user_application_password_revoked`.
-   Context key `args` is renamed to `export_args` in export logger. This key contains some of the options that was passed to export function, like author, category, start date, end date, and status.
-   Ensure loggers has a name and a slug set to avoid development oversights. `_doing_it_wrong()` will be called if they have not.
-   Logger: Method `get_info_value_by_key()` is now public so it can be used outside of a logger.
-   Logger: Method `get_info()` is now abstract, since it must be added by loggers.
-   For backwards compatibility `SimpleHistoryLogQuery`, `SimpleLoggerLogLevels`, `SimpleLoggerLogInitiators`, `SimpleLogger` will continue to exist for a couple of more versions.

**Removed**

-   Function `simple_history_add` has been removed. See [docs.simple-history.com/logging](https://docs.simple-history.com/logging) for other ways to add messages to the history log.
-   Unused function `sh_ucwords()` has been removed.
-   Removed filters `simple_history/loggers_files`, `simple_history/logger/load_logger`, `'simple_history/dropins_files'`.
-   Unused class `SimpleLoggerLogTypes` removed.
-   Removed logger for plugin Ultimate Members.
-   Removed patches for plugin [captcha-on-login](https://wordpress.org/plugins/captcha-on-login/).
-   Remove dropin used to populate log with test data.
-   Remove dropin used to show log stats.
-   Examples in examples folder are removed and moved to the documentation site at docs.[simple-history.com](https://docs.simple-history.com/).

### 3.5.1 (May 2023)

-   Fixed JavaScript error when Backbone.history is already started by other plugins. Fixes https://github.com/bonny/WordPress-Simple-History/issues/319.

### 3.5.0 (March 2023)

-   Added: Log an entry when a cron event hook is paused or resumed with the WP Crontrol plugin [#328](https://github.com/bonny/WordPress-Simple-History/pull/328).
-   Fixed: DB error on MariaDB database when collation `utf8mb4_unicode_520_ci` is used for the Simple history tables. Reported at: https://wordpress.org/support/topic/database-error-after-upgrade-to-wordpress-6-1/.
-   Tested up to WordPress 6.2.

Note: Next major version of the plugin will require PHP 7. If you are running a PHP version older than that please read https://wordpress.org/support/update-php/.

= 3.4.0 (February 2023) =

-   Changed: When exporting a CSV file of the history, each cell is escaped to reduce the risk of "CSV injection" in spreadsheet applications when importing the exported CSV. Reported at: https://patchstack.com/database/vulnerability/simple-history/wordpress-simple-history-plugin-3-3-1-csv-injection-vulnerability.

= 3.3.1 (October 2022) =

-   Tested up to WordPress 6.1.

= 3.3.0 (May 2022) =

-   Fixed: Error when third party plugin passed arguments to the `get_avatar` filter. [#288](https://github.com/bonny/WordPress-Simple-History/issues/288)
-   Changed: If Gravatars are disabled in WordPress ("Discussion" -> "Show Avatars" is unchecked) then Simple History respects this and also does not show any user avatars in the activity feed. A new filter has been added that can be used to override this: [`simple_history/show_avatars`](https://docs.simple-history.com/hooks#simple_history/show_avatars). [#288](https://github.com/bonny/WordPress-Simple-History/issues/288)
-   Update translations. Props @kebbet. See https://docs.simple-history.com/translate for information how to update or add translations of the plugin.
-   Use `constant()` function to get constant values. Makes some linting errors go away.
-   Remove `languages` folder. [#287](https://github.com/bonny/WordPress-Simple-History/issues/287)

= 3.2.0 (February 2022) =

-   Refactored detection of user profile updates. Order of updated user fields are now shown in the same order as they are in the edit user screen. Also the texts are updated to be more user friendly. And those "show toolbar"-messages that showed up at random times should be gone too. 🤞
-   Added: Creation and deletion (revoke) of Application Passwords are now logged.
-   Added: Role changes from users overview page are now logged.
-   Fixed: Password reset links was always attributed to "Anonymous web user", even those that was sent from the users listing in the WordPress admin area.
-   Fixed: Increase contrast ratio on some texts.
-   Changed: `sh_d()` now tell you if a value is integer or numeric string or an empty string.
-   Changed: The log message "Found an update to WordPress" had a dot in it. No other log message had a dot so the dot is no more.

= 3.1.1 (January 2022) =

-   Fixed: Error when uploading images when using WordPress 5.7.0 or earlier.

= 3.1.0 (January 2022) =

-   Fixed: Use user selected language instead of selected site language when loading languages for JavaScript libraries. ([#232](https://github.com/bonny/WordPress-Simple-History/issues/232))
-   Fixed: Theme deletions are now logged again. ([#266](https://github.com/bonny/WordPress-Simple-History/issues/266))
-   Fixed: Theme installs are now logged again. ([#265](https://github.com/bonny/WordPress-Simple-History/issues/265))
-   Fixed: Plugin deletions are now logged again. ([#247](https://github.com/bonny/WordPress-Simple-History/issues/247), [#122](https://github.com/bonny/WordPress-Simple-History/issues/122))
-   Fixed: Images and other attachments are now logged correctly when being inserted in the Block Editor.
-   Fixed: Some PHP notice messages in post logger.
-   Updated: JavaScript library TimeAgo updated to 1.6.7 from 1.6.3.
-   Added: Log when an admin verifies that the site admin address is valid using the [Site Admin Email Verification Screen that was added in WordPress 5.3](https://make.wordpress.org/core/2019/10/17/wordpress-5-3-admin-email-verification-screen/). ([#194](https://github.com/bonny/WordPress-Simple-History/issues/194), [#225](https://github.com/bonny/WordPress-Simple-History/issues/225))
-   Added: Option "All days" to date range filter dropdown. ([#196](https://github.com/bonny/WordPress-Simple-History/issues/196))
-   Added: Media and other attachments now display the post they were uploaded to, if any. ([#274](https://github.com/bonny/WordPress-Simple-History/issues/274))
-   Added: Add class static variables $dbtable and $dbtable_contexts that contain full db name (existing class constants DBTABLE and DBTABLE_CONTEXTS needed to be prefixed manually).
-   Added: Plugin installs now save required version of PHP and WordPress.
-   Changed: Plugin install source is now assumed to be "web" by default.
-   Changed: Attachment updates are no longer logged from post logger since the media/attachment logger takes care of it.
-   Changed: Function `sh_d()` now does not escape output when running from CLI.
-   Removed: Plugin source files-listing removed from plugin installs, because the listing was incomplete, plus some more fields that no longer were able to get meaningful values (plugin rating, number or ratings, etc.).

= 3.0.0 (January 2022) =

-   Fixed: Used wrong text domain for some strings in Limit Login Attempts logger.
-   Fixed: Post logger now ignores changes to the `_encloseme` meta key.
-   Fixed: Readme text loaded from GitHub repo is now filtered using `wp_kses()`.
-   Fixed: Links in readme text loaded from GitHub repo now opens in new window/tab by default (instead of loading in the modal/thickbox iframe).
-   Added: Logger messages is shown when clicking number of message strings in settings debug tab.
-   Added: Num occasions in RSS feed is now wrapped in a `<p>` tag.
-   Removed: "Simple Legacy Logger" is removed because it has not been used for a very long time.
-   Removed: "GitHub Plugin URI" header removed from index file, so installs of Simple History from Github using Git Updater are not supported from now on.
-   Removed: Box with translations notice removed from sidebar because it did not work properly when using different languages as site language and user language.
-   Internal: Code formatting to better match the WordPress coding standards, code cleanup, text escaping. ([#243](https://github.com/bonny/WordPress-Simple-History/issues/243))

= 2.43.0 (October 2021) =

-   Fixed: PHP notices on menu save when there are ACF fields attached ([#235](https://github.com/bonny/WordPress-Simple-History/issues/235))

-   Fixed: `array_map` and `reset` cause warning in PHP 8 ([#263](https://github.com/bonny/WordPress-Simple-History/pull/263))

= 2.42.0 (April 2021) =

-   Fixed: Quick diff table had to wrong sizes of the table cells. ([#246](https://github.com/bonny/WordPress-Simple-History/issues/246))

= 2.41.2 (March 2021) =

-   Fixed: Error when running on PHP version 7.2 or lower.

= 2.41.1 (March 2021) =

-   Fixed: Get information for correct IP Address when multiple IP addresses are shown.

= 2.41.0 (March 2021) =

-   Fixed: Error when visiting settings screen on PHP 8.
    Fixes https://wordpress.org/support/topic/simple-history-fatal-error/.
    [#239](https://github.com/bonny/WordPress-Simple-History/issues/239)

= 2.40.0 (March 2021) =

-   Changed: IP address is now also shown when a user successfully logs in.
    Previously the IP address was only shown for failed login attempts. Note that the IP address/es of all events are always logged and can be seen in the "context data" table that is displayed when you click the date and time of an event.
    [#233](https://github.com/bonny/WordPress-Simple-History/issues/233)

-   Added: If multiple IP addresses are detected, for example when a website is running behind a proxy or similar, all IP addresses are now shown for failed and successful logins.

-   Added: Filter `simple_history/row_header_output/display_ip_address` that can be used to control when the IP address/es should be visible in the main log. By default successful and failed logins are shown.

-   Added: Show message when failing to get IP address due to for example ad blocker. IPInfo.io is for example blocked in the EasyList filter list that for example [Chrome extension uBlock Origin](https://chrome.google.com/webstore/detail/ublock-origin/cjpalhdlnbpafiamejdnhcphjbkeiagm) uses.

-   Added: Filter `simple_history/row_header_output/template` that controls the output of the header row in the main event log.

= 2.39.0 (January 2021) =

-   Added: Logging of events that a user performs via the [WP Crontrol](https://wordpress.org/plugins/wp-crontrol/) plugin (requires WP Crontrol version 1.9.0 or later). Simple History will log when cron events are added, edited, deleted, and manually ran, and when cron schedules are added and deleted. Props https://github.com/johnbillion.

= 2.38.0 (November 2020) =

-   Changed: It's now possible to log things before the `after_setup_theme` hook by using the `SimpleLogger()` function. Before this change calling `SimpleLogger()` before `after_setup_theme`, or on `after_setup_theme` with a prio smaller than 10, would result in a fatal error (`Fatal error: Uncaught Error: Class 'SimpleLogger' not found`). Props https://github.com/JoryHogeveen.

-   Changed: More custom post types that use the block editor ("Gutenberg") should now have their changes logged. Props https://github.com/claytoncollie.

= 2.37.2 (September 2020) =

-   Fixed: Even more code that was to new for PHP 5.6 (I do have some tests, I just didn't look at them `¯\_(ツ)_/¯`.)

= 2.37.1 (September 2020) =

-   Fixed: Some code was to new for PHP 5.6.

= 2.37 (September 2020) =

-   Added: Enabling or disabling plugin auto-updates is now logged.
-   Added: Function `sh_d()` that echoes any number of variables to the screen.
-   Fixed: User logouts did show "other" instead of username of user logging out. Fixes #206, https://wordpress.org/support/topic/suspicious-logged-out-events/, https://wordpress.org/support/topic/login-logout-tracking/.
-   Updated: lots of code to be formatted more according to PSR12.

= 2.36 (August 2020) =

-   Fix plus and minus icons in quick diff.
-   Add filter for Post Logger context. (https://github.com/bonny/WordPress-Simple-History/pull/216)
-   Add link to my [GitHub sponsors page](https://github.com/sponsors/bonny/) in the sidebar.
-   Misc code cleanups and smaller fixes.

= 2.35.1 (August 2020) =

Minor update to correct readme.

= 2.35 (August 2020) =

You can now [sponsor the developer of this plugin at GitHub](https://github.com/sponsors/bonny/).

**Fixed**

-   Fix PHP Warning when bulk editing items in the Redirection plugin. Fixes https://github.com/bonny/WordPress-Simple-History/issues/207, https://wordpress.org/support/topic/crashes-with-redirection-plugin/. (https://github.com/bonny/WordPress-Simple-History/commit/e8be051c4d95e598275a7ba17a01f76008eb7a5b)

**Changed**

-   Welcome text updated to be more correct. (https://github.com/bonny/WordPress-Simple-History/pull/211)

= 2.34 (June 2020) =

**Changed**

-   Use flexbox for history page layout, so if all dropins are disabled then the content area
    spans the entire 100 % width (#199).

-   Adjust style of pagination to match WordPress core pagination.

= 2.33.2 (January 2020) =

-   Fix history displaying blank white space on smaller screens. Fixes https://wordpress.org/support/topic/viewing-the-log-on-a-iphone/.

= 2.33.1 (January 2020) =

-   Was just an internal test version.

= 2.33 (November 2019) =

-   Better compatibility with the Gutenberg Block editor.
-   Correct URL redirected to after clearing log. Fixes #123.
-   Fix history log on dashboard leaving lots of white space and sometimes overlapping other dashboard widgets.
    Fixes https://wordpress.org/support/topic/dashboard-block-cut-off/, https://wordpress.org/support/topic/simple-history-v2-32/, and https://wordpress.org/support/topic/new-update-not-working-10/.
-   Fix join parameter order for PHP 7.4.
-   Update donate link. It's now https://www.paypal.me/eskapism.
    If you like the plugin please consider donate.
    A very small amount makes me much more happy than nothing at all! ;)

= 2.32 (August 2019) =

-   Fix error in Beaver Builder logger. Fixes https://wordpress.org/support/topic/conflict-with-beaver-builder-plugin-4/.
-   Add filter `simple_history/admin_location` that makes is possible to move the main page from the dashboard menu to any other menu page, for example the Tools menu. Fixes https://github.com/bonny/WordPress-Simple-History/issues/140. Example usage of filter:

```php
// Move Simple History log sub page from the "Dashboard" menu to the "Tools" menu.
add_filter('simple_history/admin_location', function ($location) {
	$location = 'tools';
	return $location;
});
```

-   Make it easier to extend SimplePostLogger by making `$old_post_data` protected instead of private. https://github.com/bonny/WordPress-Simple-History/pull/173.
-   Try to use taxonomy name instead of taxonomy slug when showing term additions or modifications. Fixes https://github.com/bonny/WordPress-Simple-History/issues/164.
-   Fix notice error when showing the log entry for a term that was deleted.
-   Remove unused old function `testlog_old()`.
-   Move helper functions to own file.
-   Move debug code into own dropin.
-   Bump required PHP version to 5.6.20 (same version that WordPress itself requires).

= 2.31 (May 2019) =

-   Add support for plugin [Beaver Builder](https://wordpress.org/plugins/beaver-builder-lite-version/).

= 2.30 (April 2019) =

-   Add better Gutenberg compatibility.
-   Don't log WooCommerce scheduled actions. Fixes https://wordpress.org/support/topic/cant-use-flooded-with-deleted-scheduled-action-woocommerce-webhooks/.
-   Store if post password has been set, unset, or changed.
-   Store if a log entry comes from the REST API. Stored in the event context as `_rest_api_request`.
-   Check that logger messages exists and is array before trying to use.
-   Bump required version in readme to 5.4. It's just to difficult to keep the plugin compatible with PHP less than [PHP version 5.4](http://php.net/manual/en/migration54.new-features.php).
-   Updates to some translation strings.

= 2.29.2 (January 2019) =

-   Fix for (the still great) plugin [Advanced Custom Fields](http://advancedcustomfields.com) 5.7.10 that removed the function `_acf_get_field_by_id` that this plugin used. Fixes https://wordpress.org/support/topic/uncaught-error-call-to-undefined-function-_acf_get_field_by_id/.

= 2.29.1 (December 2018) =

-   Fix another PHP 7.3 warning. Should fix https://wordpress.org/support/topic/php-7-3-compatibility-3/.

= 2.29 (December 2018) =

-   Make log welcome message translatable.
-   Add two filters to make it more ease to control via filters if a logger and the combination logger + message should be logged. - `"simple_history/log/do_log/{$this->slug}"` controls if any messages for a specific logger should be logged. Simply return false to this filter to disable all logging to that logger. - `"simple_history/log/do_log/{$this->slug}/{$message_key}"` controls if a specific message for a specific logger should be logged. Simply return false to this filter to disable all logging to that logger. - Code examples for the two filters above:

    ````
    // Disable logging of any user message, i.e. any message from the logger SimpleUserLogger.
    add_filter( 'simple_history/log/do_log/SimpleUserLogger', '\_\_return_false' );

        		// Disable logging of updated posts, i.e. the message "post_updated" from the logger SimplePostLogger.
        		add_filter( 'simple_history/log/do_log/SimplePostLogger/post_updated', '__return_false' );
        		```

    ````

-   add_filter('simple_history/log/do_log/SimpleUserLogger', '\_\_return_false');
-   Fix notice in Redirection plugin logger due because redirection plugin can have multiple target types. Props @MaximVanhove.
-   Fix warning because of missing logging messages in the categories/tags logger. Props @JeroenSormani.
-   Fix warning in the next version of PHP, PHP 7.3.

= 2.28.1 (September 2018) =

-   Remove a debug message that was left in the code.

= 2.28 (September 2018) =

-   Always show time and sometimes date before each event, in addition to the relative date. Fixes https://wordpress.org/support/topic/feature-request-granular-settings-changes-detailed-timestamp/.
-   Use WordPress own function (`wp_privacy_anonymize_ip`, available since WordPress version 4.9.6) to anonymize IP addresses, instead of our own class.
-   Update timeago.js

= 2.27 (August 2018) =

-   Fix notice errors when syncing an ACF field group. Fixes https://github.com/bonny/WordPress-Simple-History/issues/150.
-   Fix notice error when trying to read plugin info for a plugin that no longer exists or has changed name. Fixes https://github.com/bonny/WordPress-Simple-History/issues/146.
-   Always load the SimpleLogger logger. Fixes https://github.com/bonny/WordPress-Simple-History/issues/129.
-   Make more texts translatable.
-   Show plugin slug instead of name when translations are updated and a plugin name is not provided by the upgrader. This can happen when a plugin is using an external update service, like EDD.
-   Group translation updates in the log. Useful because sometimes you update a lot of translations at the same time and the log is full of just those messages.

= 2.26.1 (July 2018) =

-   Fix 5.3 compatibility.

= 2.26 (July 2018) =

-   Add support for the [Jetpack plugin](https://wordpress.org/plugins/jetpack/). To begin with, activation and deactivation of Jetpack modules is logged.
-   Add logging of translation updates, so now you can see when a plugin or a theme has gotten new translations. Fixes https://github.com/bonny/WordPress-Simple-History/issues/147.
-   Fix notice in Advanced Custom Fields logger when saving an ACF options page.
    Fixes https://wordpress.org/support/topic/problem-with-acf-options-pages/, https://wordpress.org/support/topic/problem-with-recent-version-and-acf/, https://github.com/bonny/WordPress-Simple-History/issues/145.

= 2.25 (July 2018) =

-   Add `wp_cron_current_filter` to event context when something is logged during a cron job. This can help debugging thing like posts being added or deleted by some plugin and you're trying to figure out which plugin it is.
-   Fix for event details not always being shown.
-   Fix for sometimes missing user name and user email in export file.

= 2.24 (July 2018) =

-   Added user login and user email to CSV export file.
-   Fix notice in postlogger when a post was deleted from the trash.
-   Clear database in smaller steps. Fixes https://github.com/bonny/WordPress-Simple-History/issues/143.
-   Fix notice in ACF logger due to misspelled variable. Fixes https://wordpress.org/support/topic/problem-with-recent-version-and-acf/.

= 2.23.1 (May 2018) =

-   Remove some debug messages that was outputted to the error log. Fixes https://wordpress.org/support/topic/errors-in-php-log-since-v2-23/.
-   Fix error because function `ucwords()` does not allow a second argument on PHP versions before 5.4.32. Fixes https://wordpress.org/support/topic/error-message-since-last-update/, https://wordpress.org/support/topic/errors-related-to-php-version/.
-   Added new function `sh_ucwords()` that works like `ucwords()` but it also works on PHP 5.3.

= 2.23 (May 2018) =

-   Add logging of privacy and GDPR related functions in WordPress. Some of the new [privacy related features in WordPress 4.9.6](https://wordpress.org/news/2018/05/wordpress-4-9-6-privacy-and-maintenance-release/) that are logged: - Privacy policy page is created or changed to a new page. - Privacy data export is requested for a user and when this request is confirmed by the user and when the data for the request is downloaded by an admin or emailed to the user. - Erase Personal Data: Request is added for user to have their personal data erased, user confirms the data removal and when the deletion of user data is done.
-   Fix error when categories changes was shown in the log. Fixes https://wordpress.org/support/topic/php-notice-undefined-variable-term_object/.
-   Fix error when a ACF Field Group was saved.
-   Fix error when the IP address anonymization function tried to anonymize an empty IP address. Could happen when for example running wp cron locally on your server.
-   Fix error when calling the REST API with an API endpoint with a closure as the callback. Fixes https://github.com/bonny/WordPress-Simple-History/issues/141.
-   Rewrote logger loading method so now it's possible to name your loggers in a WordPress codings standard compatible way. Also: made a bit more code more WordPress-ish.
-   The post types in the `skip_posttypes` filter are now also applied to deleted posts.
-   Add function `sh_get_callable_name()` that returns a human readable name for a callback.

= 2.22.1 (May 2018) =

-   Fix for some REST API Routes not working, for example when using WPCF7. Should fix https://wordpress.org/support/topic/errorexception-with-wpcf7/ and similar.

= 2.22 (May 2018) =

-   IP addresses are now anonymized by default. This is mainly done because of the [General Data Protection Regulation](https://en.wikipedia.org/wiki/General_Data_Protection_Regulation) (GDPR)
    Both IPv4 and IPv6 addresses will be anonymized and the IP addresses are anonymized to their network ID.
    So for example the IPv4 address `192.168.123.124` is anonymized to `192.168.123.0` and
    the IPv6 address `2a03:2880:2110:df07:face:b00c::1` is anonymized by default to `2610:28:3090:3001::`.

-   Added filter `simple_history/privacy/anonymize_ip_address` than can be used to disable ip address anonymization.

-   Added function `sh_error_log()` to easily log variables to the error log. Probably only of interest to developers.

-   Fixed logging for [plugin Redirection](https://wordpress.org/plugins/redirection/). The logging of URL redirects and so on stopped working some while back because the Redirection plugin started using the WP REST API. But now it's working again!

= 2.21.1 (May 2018) =

-   Make sure support for Advanced Custom Fields is activated for all users – and not only for the developer of the plugin ;)

= 2.21 (May 2018) =

-   Added support for Advanced Custom Fields (ACF): when a ACF Field or ACF Field Group is created or modified or deleted you will now get more details in the activity feed.
-   Changes to taxonomies/categories/tags now include a link to the modified term and to the category that the term belongs to.
-   The post types in the `skip_posttypes` filter are now also applied to trashed and untrashed posts (not only post edits, as before).
-   Don't log Jetpack sitemap updates. (Don't log updates to posttypes `jp_sitemap`, `jp_sitemap_master` and `jp_img_sitemap`, i.e. the post types used by Jetpack's Sitemap function.) Should fix https://wordpress.org/support/topic/jetpack-sitemap-logging/.
-   Don't log the taxonomies `post_translations` or `term_translations`, that are used by Polylang to store translation mappings. That contained md5-hashed strings and was not of any benefit (a separate logger for Polylang will come soon anyway).
-   Fix notice in theme logger because did not check if `$_POST['sidebar']` was set. Fixes https://github.com/bonny/WordPress-Simple-History/issues/136.
-   Fix thumbnail title missing notice in post logger.
-   Fix PHP warning when a plugin was checked by WordPress for an update, but your WordPress install did not have the plugin folder for that plugin.
-   Fix unexpected single-quotations included in file name in Internet Explorer 11 (and possibly other versions) when exporting CSV/JSON file.
-   Fix filter/search log by specific users not working. Fixes https://wordpress.org/support/topic/show-activity-from-other-authors-only/.
-   Fix a notice in SimpleOptionsLogger.
-   Better CSS styling on dashboard.
-   Add filter `simple_history/post_logger/post_updated/context` that can be used to modify the context added by SimplePostLogger.
-   Add filter `simple_history/post_logger/post_updated/ok_to_log` that can be used to skip logging a post update.
-   Add filter `simple_history/categories_logger/skip_taxonomies` that can be used to modify what taxonomies to skip when logging updates to taxonomy terms.

= 2.20 (November 2017) =

-   Add logging of post thumbnails.
-   Use medium size of image attachments when showing uploaded files in the log. Previously a custom size was used, a size that most sites did not have and instead the full size image would be outputted = waste of bandwidth.
-   Make image previews smaller because many uploaded images could make the log a bit to long and not so quick to overview.
-   Update Select2 to latest version. Fixes https://wordpress.org/support/topic/select2-js-is-outdated/.
-   Show a message if user is running to old WordPress version, and don't continue running the code of this plugin.
    Should fix stuff like https://wordpress.org/support/topic/simple-history-i-cannot-login/.
-   Fix an error with PHP 7.1.

= 2.19 (November 2017) =

-   Add filter `simple_history/user_can_clear_log`. Return `false` from this filter to disable the "Clear blog" button.
-   Remove static keyword from some methods in SimpleLogger, so now calls like `SimpleLogger()->critical('Doh!');` works.
-   Don't show link to WordPress updates if user is not allowed to view the updates page.
-   Fix notice error in SimpleOptionsLogger.
-   Fix for fatal errors when using the lost password form in [Membership 2](https://wordpress.org/plugins/membership/). Fixes https://wordpress.org/support/topic/conflict-with-simple-history-plugin-and-php-7/.
-   Code (a little bit) better formatted according to [WordPress coding standard](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards).

= 2.18 (August 2017) =

-   Set from_term_description correctly, fixes https://github.com/bonny/WordPress-Simple-History/issues/127.
-   Add filter `simple_history/post_logger/skip_posttypes`.
-   Don't log post type `jetpack_migation` because for some users that post type filled the log. Fixes https://wordpress.org/support/topic/updated-jetpack_migration-sidebars_widgets/.

= 2.17 (June 2017) =

-   Fix search date range inputs not showing correctly.
-   Change the message for when a plugin is deactivated due to an error. Now the plugin slug is included, so you know exactly what plugin has been deactivated. Also the reason for the deactivation is included (one of "Invalid plugin path", "Plugin file does not exist", or "The plugin does not have a valid header.").
-   Added more filters to log message. Now filter `simple_history_log_debug` exists, together with filters for all other 7 log levels. So you can use `simple_history_log_{loglevel}` where {loglevel} is any of emergency, alert, critical, error, warning, notice, info or debug.
-   Add support for logging the changing of "locale" on a user profile, something that was added in WordPress 4.7.
-   Add sidebar box with link to the settings page.
-   Don't log when old posts are deleted from the trash during cron job wp_scheduled_delete.
-   HHVM is not used for any tests any longer because PHP 7 and Travis not supporting it or something. I dunno. Something like that.
-   When "development debug mode" is activated also log current filters.
-   Show an admin warning if a logger slug is longer than 30 chars.
-   Fix fatal error when calling log() method with null as context argument.

= 2.16 (May 2017) =

-   Added [WP-CLI](https://wp-cli.org) command for Simple History. Now you can write `wp simple-history list` to see the latest entries from the history log. For now `list` is the only available command. Let me know if you need more commands!
-   Added support for logging edits to theme files and plugin files. When a file is edited you will also get a quick diff on the changes,
    so you can see what CSS styles a client changed or what PHP changes they made in a plugin file.
-   Removed the edit file logger from the plugin logger, because it did not always work (checked wrong wp path). Instead the new Theme and plugins logger mentioned above will take care of this.

= 2.15 (May 2017) =

-   Use thumbnail version of PDF preview instead of full size image.
-   Remove Google Maps image when clicking IP address of failed login and similar, because Google Maps must be used with API key.
    Hostname, Network, City, Region and Country is still shown.
-   Fix notice in available updates logger.
-   Fix notice in redirection logger.

= 2.14.1 (April 2017) =

-   Fix for users running on older PHP versions.

= 2.14 (April 2017) =

-   Added support for plugin [Duplicate Post](https://wordpress.org/plugins/duplicate-post/).
    Now when a user clones a post or page you will se this in the history log, with links to both the original post and the new copy.
-   Removed log level info from title in RSS feed
-   Make date dropdown less "jumpy" when loading page (due to select element switching to Select2)
-   Only add filters for plugin Limit Login Attempts if plugin is active. This fixes problem with Limit Login Attempts Reloaded and possibly other forks of the plugin.
-   Debug page now displays installed plugins.

= 2.13 (November 2016) =

-   Added filter `simple_history_log` that is a simplified way to add message to the log, without the need to check for the existence of Simple History or its SimpleLogger function. Use it like this: `apply_filters("simple_history_log", "This is a logged message");` See the [examples file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) for more examples.
-   IP info now displays a popup with map + geolocation info for users using HTTPS again. Thanks to the great https://twitter.com/ipinfoio for letting all users use their service :)
-   Fix notice warning for missing `$data_parent_row`

= 2.12 (September 2016) =

-   You can show a different number of log items in the log on the dashboard and on the dedicated history page. By default the dashboard will show 5 items and the page will show 30.
-   On multisites the user search filter now only search users in the current site.
-   The statistics chart using Chart.js now uses the namespace window.Simple_History_Chart instead of window.Chart, decreasing the risk that two versions of the Chart.js library overwriting each others. Fixes https://wordpress.org/support/topic/comet-cache-breaks-simple-history/. (Note to future me: this was fixed by renaming the `window.chart` variable to `window.chart.Simple_history_chart` in the line `window.Chart = module.exports = Chart;`)
-   If spam comments are logged they are now included in the log. Change made to make sql query shorter and easier. Should not actually show any spam comments anyway because we don't log them since version 2.5.5 anyway. If you want to revert this behavior for some reason you can use the filter `simple_history/comments_logger/include_spam`.

= 2.11 (September 2016) =

-   Added support for plugin [Redirection](https://wordpress.org/plugins/redirection/).
    Redirects and groups that are created, changed, enabled and disabled will be logged. Also when the plugin global settings are changed that will be logged.
-   Fix possible notice error from User logger.
-   "View changelog" link now works on multisite.

= 2.10 (September 2016) =

-   Available updates to plugins, themes, and WordPress itself is now logged.
    Pretty great if you subscribe to the RSS feed to get the changes on a site. No need to manually check the updates-page to see if there are any updates.
-   Changed to logic used to determine if a post edit should be logged or not. Version 2.9 used a version that started to log a bit to much for some plugins. This should fix the problems with the Nextgen Gallery, All-In-One Events Calendar, and Membership 2 plugins. If you still have problems with a plugin that is causing to many events to be logged, please let me know!

= 2.9.1 (August 2016) =

-   Fixed an issue where the logged time was off by some hours, due to timezone being manually set elsewhere.
    Should fix https://wordpress.org/support/topic/logged-time-off-by-2-hours and https://wordpress.org/support/topic/different-time-between-dashboard-and-logger.
-   Fixed Nextgen Gallery and Nextgen Gallery Plus logging lots and lots of event when viewing posts with galleries. The posts was actually updated, so this plugin did nothing wrong. But it was indeed a bit annoying and most likely something you didn't want in your log. Fixes https://wordpress.org/support/topic/non-stop-logging-nextgen-gallery-items.

= 2.9 (August 2016) =

-   Added custom date ranges to the dates filter. Just select "Custom date range..." in the dates dropdown and you can choose to see the log between any two exact dates.
-   The values in the statistics graph can now be clicked and when clicked the log is filtered to only show logged events from that day. Very convenient if you have a larger number of events logged for one day and quickly want to find out what exactly was logged that day.
-   Dates filter no longer accepts multi values. It was indeed a bit confusing that you could select both "Last 7 days" and "Last 3 days".
-   Fix for empty previous plugin version (the `{plugin_prev_version}` placeholder) when updating plugins.
-   Post and pages updates done in the WordPress apps for Ios and Android should be logged again.

= 2.8 (August 2016) =

-   Theme installs are now logged
-   ...and so are theme updates
-   ...and theme deletions. Awesome!
-   Support for plugin [Limit Login Attempts](https://wordpress.org/plugins/limit-login-attempts/).
    Failed login attempts, lockouts and configuration changes will be logged.
-   Correct message is now used when a plugin update fails, i.e. the message for key `plugin_update_failed`.
-   The original untranslated strings for plugin name and so on are stored when storing info for plugin installs and updates and similar.
-   Default number of events to show is now 10 instead of 5.

= 2.7.5 (August 2016) =

-   User logins using e-mail are now logged correctly. Previously the user would be logged in successfully but the log said that they failed.
-   Security fix: only users with [`list_users`](https://codex.wordpress.org/Roles_and_Capabilities#list_users) capability can view the users filter and use the autocomplete api for users.
    Previously the autocomplete function could be used by all logged in users.
-   Add labels to search filters. (I do really hate label-less forms so it's kinda very strange that this was not in place before.)
-   Misc other internal fixes

= 2.7.4 (July 2016) =

-   Log a warning message if a plugin gets disabled automatically by WordPress because of any of these errors: "Plugin file does not exist.", "Invalid plugin path.", "The plugin does not have a valid header."
-   Fix warning error if `on_wp_login()` was called without second argument.
-   Fix options diff not being shown correctly.
-   Fix notice if no message key did exist for a log message.

= 2.7.3 (June 2016) =

-   Removed the usage of the mb\_\* functions and mbstring is no longer a requirement.
-   Added a new debug tab to the settings page. On the debug page you can see stuff like how large your database is and how many rows that are stored in the database. Also, a list of all loggers are listed there together with some useful (for developers anyway) information.

= 2.7.2 (June 2016) =

-   Fixed message about mbstring required not being echo'ed.
-   Fixed notice errors for users not allowed to view the log.

= 2.7.1 (June 2016) =

-   Added: Add shortcut to history in Admin bar for current site and in Network Admin Bar for each site where plugin is installed. Can be disabled using filters `simple_history/add_admin_bar_menu_item` and `simple_history/add_admin_bar_network_menu_item`.
-   Added: Add check that [´mbstring´](http://php.net/manual/en/book.mbstring.php) is enabled and show a warning if it's not.
-   Changed: Changes to "Front Page Displays" in "Reading Settings" now show the name of the old and new page (before only id was logged).
-   Changed: Changes to "Default Post Category" and "Default Mail Category" in "Writing Settings" now show the name of the old and new category (before only id was logged).
-   Fixed: When changing "Front Page Displays" in "Reading Settings" the option "rewrite_rules" also got logged.
-   Fixed: Changes in Permalink Settings were not logged correctly.
-   Fixed: Actions done with [WP-CLI](https://wp-cli.org/) was not correctly attributed. Now the log should say "WP-CLI" instead of "Other" for actions done in WP CLI.

= 2.7 (May 2016) =

-   Added: When a user is created or edited the log now shows what fields have changed and from what old value to what new value. A much requested feature!
-   Fixed: If you edited your own profile the log would say that you edited "their profile". Now it says that you edited "your profile" instead.
-   Changed: Post diffs could get very tall. Now they are max approx 8 rows by default, but if you hover the diff (or give it focus with your keyboard) you get a scrollbar and can scroll the contents. Fixes https://wordpress.org/support/topic/dashboard-max-length-of-content and https://wordpress.org/support/topic/feature-request-make-content-diff-report-expandable-and-closed-by-default.
-   Fixed: Maybe fix a notice warning if a transient was missing a name or value.

= 2.6 (May 2016) =

-   Added: A nice little graph in the sidebar that displays the number of logged events per day the last 28 days. Graph is powered by [Chart.js](http://www.chartjs.org/).
-   Added: Function `get_num_events_last_n_days()`
-   Added: Function `get_num_events_per_day_last_n_days()`
-   Changed: Switched to transients from cache at some places, because more people will benefit from transients instead of cache (that requires object cache to be installed).
-   Changed: New constant `SETTINGS_GENERAL_OPTION_GROUP`. Fixes https://wordpress.org/support/topic/constant-for-settings-option-group-name-option_group.
-   Fixed: Long log messages with no spaces would get cut of. Now all the message is shown, but with one or several line breaks. Fixes https://github.com/bonny/WordPress-Simple-History/pull/112.
-   Fixed: Some small CSS modification to make the page less "jumpy" while loading (for example setting a default height to the select2 input box).

= 2.5.5 (April 2016) =

-   Changed: The logger for Enable Media Replace required the capability `edit_files` to view the logged events, but since this also made it impossible to view events if the constant `DISALLOW_FILE_EDIT` was true. Now Enable Media Replace requires the capability `upload_files` instead. Makes more sense. Fixes https://wordpress.org/support/topic/simple-history-and-disallow_file_edit.
-   Changed: No longer log spam trackbacks or comments. Before this version these where logged, but not shown.
-   Fixed: Translations was not loaded for Select2. Fixes https://wordpress.org/support/topic/found-a-string-thats-not-translatable-v-254.
-   Fixed: LogQuery `date_to`-argument was using `date_from`.
-   Changed: The changelog for 2015 and earlier are now moved to [CHANGELOG.md](https://github.com/bonny/WordPress-Simple-History/blob/master/CHANGELOG.md).

= 2.5.4 (March 2016) =

-   Added: Support for new key in info array from logger: "name_via". Set this value in a logger and the string will be shown next to the date of the logged event. Useful when logging actions from third party plugins, or any kind of other logging that is not from WordPress core.
-   Added: Method `getInfoValueByKey` added to the SimpleLogger class, for easier retrieval of values from the info array of a logger.
-   Fixed: Themes could no be deleted. Fixes https://github.com/bonny/WordPress-Simple-History/issues/98 and https://wordpress.org/support/topic/deleting-theme-1.
-   Fixed: Notice error when generating permalink for event.
-   Fixed: Removed a `console.log()`.
-   Changed: Check that array key is integer or string. Hopefully fixes https://wordpress.org/support/topic/error-in-wp-adminerror_log.

= 2.5.3 (February 2016) =

-   Fixed: Old entries was not correctly removed. Fixes https://github.com/bonny/WordPress-Simple-History/issues/108.

= 2.5.2 (February 2016) =

-   Added: The GUI log now updates the relative "fuzzy" timestamps in real time. This means that if you keep the log opened, the relative date for each event, for example "2 minutes ago" or "2 hours ago", will always be up to date (hah!). Keep the log opened for 5 minutes and you will see that the event that previously said "2 minutes ago" now says "7 minutes ago". Fixes https://github.com/bonny/WordPress-Simple-History/issues/88 and is implemented using the great [timeago jquery plugin](http://timeago.yarp.com/).
-   Added: Filter `simple_history/user_logger/plain_text_output_use_you`. Works the same way as the `simple_history/header_initiator_use_you` filter, but for the rich text part when a user has edited their profile.
-   Fixed: Logger slugs that contained for example backslashes (because they where namespaced) would not show up in the log. Now logger slugs are escaped. Fixes https://github.com/bonny/WordPress-Simple-History/issues/103.
-   Changed: Actions and things that only is needed in admin area are now only called if `is_admin()`. Fixes https://github.com/bonny/WordPress-Simple-History/issues/105.

= 2.5.1 (February 2016) =

-   Fixed: No longer assume that the ajaxurl don't already contains query params. Should fix problems with third party plugins like [WPML](https://wpml.org/).
-   Fixed: Notice if context key did not exist. Should fix https://github.com/bonny/WordPress-Simple-History/issues/100.
-   Fixed: Name and title on dashboard and settings page were not translatable. Fixes https://wordpress.org/support/topic/dashboard-max-length-of-content.
-   Fixed: Typo when user resets password.
-   Added: Filter `simple_history/row_header_date_output`.
-   Added: Filter `simple_history/log/inserted`.
-   Added: Filter `simple_history/row_header_date_output`.

= 2.5 (December 2015) =

-   Added: Category edits are now logged, so now you can see terms, categories and taxonomies that are added, changed, and deleted. Fixes for example https://wordpress.org/support/topic/view-changes-to-categories and https://twitter.com/hmarafi/status/655994402037362688.
-   Fixed: The media logger now shows the width and height of uploaded images again.
-   Fixed: IP Lookup using ipinfo.io would fail if your site was using HTTPS (pro account on ipinfo.io required for that), so now falls back to opening a link to ipinfo.io in a new tab instead.
-   Fixed: If there was a server error while loading the log, the error would be shown, to help you debug any errors. The error would however not go away if you successfully loaded the log again. Now it does.
-   Changed: The search/filter now falls back to showing events for the last 14 days, if 30 days would return over 1000 pages of events. This change is to try to make the log fail to load in less scenarios. If a site got a bit spike if brute force attacks (yes, it's always those attacks!) then there could be a big jump in the number of events and pages between 14 days and 30 days.
-   Changed: Failed login attempts now use shorter messages and shorter variable names. Not really the fault of this plugin, but sites can get a huge amount of failed login attempts logged. Sites with almost 2 million logged rows just in the last 60 days for example. And that will cause the database tables with the history to grow to several hundreds of megabyte. So to make those tables a bit smaller the plugin now uses shorter messages for failed login attempts, shorter variable names, and it stores less data. If you want to stop hackers from attacking your site (resulting in big history logs) you should install a plugin like [Jetpack and its BruteProtect module](https://jetpack.me/support/security-features/).
-   Updated: Added date filter to show just events from just one day. Useful for sites that get hammered by brute force login attempts. On one site where I had 166434 login attempts the last 7 days this helped to make the log actually load :/.
-   Updated: New French translation

= 2.4 (November 2015) =

-   Added: Now logs when a user changes their password using the "reset password" link.
-   Added: Now logs when a user uses the password reset form.
-   Added: New method `register_dropin` that can be used to add dropins.
-   Added: New action `simple_history/add_custom_dropin`.
-   Added: Example on how to add an external dropin: [example-dropin.php](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/example-dropin.php).
-   Added: "Last day" added to filter, because a brute force attack can add so many logs that it's not possible to fetch a whole week.
-   Changed: Filter `simple_history/log/do_log` now pass 5 arguments instead of 3. Before this update in was not possible for multiple add_action()-calls to use this filter, because you would not now if any other code had canceled it and so on. If you have been using this filter you need to modify your code.
-   Changed: When hovering the time of an event in the log, the date of the event displays in both local time and GMT time. Hopefully makes it easier for admins in different timezones that work together on a site to understand when each event happened. Fixes https://github.com/bonny/WordPress-Simple-History/issues/84.
-   Fixed: Line height was a bit tight on the dashboard. Also: the margin was a tad to small for the first logged event on the dashboard.
-   Fixed: Username was not added correctly to failed login attempts when using plugin Captcha on Login + it would still show that a user logged out sometimes when a bot/script brute force attacked a site by only sending login and password and not the captcha field.

= 2.3.1 (October 2015) =

-   Fixed: Hopefully fixed the wrong relative time, as reported at URL: https://wordpress.org/support/topic/wrong-reporting-time.
-   Changed: The RSS-feed with updates is now disabled by default for new installs. It is password protected, but some users felt that is should be optional to activate it. And now it is! Thanks to https://github.com/guillaumemolter for adding this feature.
-   Fixed: Failed login entries when using plugin [Captcha on Login](https://wordpress.org/plugins/captcha-on-login/) was reported as "Logged out" when they really meant "Failed to log in". Please note that this was nothing that Simple History did wrong, it was rather Captcha on Login that manually called `wp_logout()` each time a user failed to login. Should fix all those mystery "Logged out"-entries some of you users had.
-   Added: Filter `simple_history/log/do_log` that can be used to shortcut the log()-method.
-   Updated: German translation updated.

= 2.3 (October 2015) =

-   Added: The title of the browser tab with Simple History open will now show the number of new and unread events available. Nice feature to have if you keep a tab with the Simple History log open but in the background: now you can see directly in the title if new events are available. Such small change. Very much nice.
-   Added: If the AJAX call to fetch the log failed, a message now appears telling the user that something went wrong. Also, the output from the server is displayed so they can get a hint of what's going wrong. Hopefully this will reduce the number of support requests that is caused by other plugins.
-   Fixed: Edited posts/pages/custom post types does not get a linked title unless the user viewing the log has edit rights.
-   Fixed: Another try to fix the notice error: https://wordpress.org/support/topic/simplehistoryphp-creates-debug-entries.
-   Updated: Danish translation updated.
-   Updated: POT file updated.

= 2.2.4 (September 2015) =

-   Added: Basic support for plugin [Ultimate Member](https://wordpress.org/plugins/ultimate-member/), so users logging in using the plugin will now be logged in Simple History. Fixes https://wordpress.org/support/topic/compatibility-with-ultimate-member.
-   Added: Filter `simple_history/logger/interpolate/context` that can be used to modify the variables sent to the message template.
-   Changed: Remove "type" key from context detail table, because it's an old an unused column.
-   Changed: During a first install the plugin now creates a few less columns than before (some columns where left from version 1 of the plugin).
-   Changed: Don't show the "translate this plugin" metabox for any english talking locale.
-   Changed: Don't show the GitHub metabox.
-   Fixed: If the plugin is deleted (but why?!) then the context table is also removed now.
-   Behind the scenes: More unit tests! Hopefully more nasty things will get caught before releasing new versions of the plugin.

= 2.2.3 (September 2015) =

-   Fixed: On new installs the database tables was not created correctly and new events could not be logged.

= 2.2.2 (September 2015) =

-   Fixed: Logging stopped working for languages other then English. Sorry about that!
-   Fixed: When running unit tests for a site where Simple History is a must use plugin it sometimes tried to create tables and add columns more then once. Now uses `if not exists` and similar to only try to create the tables if they not already exists.

= 2.2.1 (September 2015) =

-   Fixed: Missed to log users switching back on using the User Switching plugin. Fixes https://github.com/bonny/WordPress-Simple-History/issues/89.

= 2.2 (September 2015) =

-   Added: Support for plugin [User Switching](https://wordpress.org/plugins/user-switching/). The event log will show when a user switched to another user, when they switched back, or when they switched off.
-   Added: Support for plugin [Enable Media Replace](https://wordpress.org/plugins/enable-media-replace/). Whenever a user replaces an attachment with a new, you will now know about it and also see the name of both the old and the new attachment. Awesome!
-   Fixed: Mouse over (:hover state) on buttons no longer use blue background. Now works much better with admin themes other than the standard one. Fixes https://wordpress.org/support/topic/pagination-button-design.

= 2.1.7 (September 2015) =

-   Fixed: Date and time in the log was using GMT time rather than local time. Could be confusing. Even very confusing if living in a time zone far far away from the GMT zone.

= 2.1.6 (August 2015) =

-   Updated: Danish translation updated. Thanks translator!
-   Fixed: Icon on settings page was a bit unaligned on WordPress not running the latest beta version (hrm, which I guess most of you were..)
-   Fixed: Possible php notice. Should fix https://wordpress.org/support/topic/simplehistoryphp-creates-debug-entries.
-   Changed: Logged messages are now trimmed by default (spaces and new lines will be removed from messages).
-   Updated: When installing and activating the plugin it will now add the same "plugin installed" and "plugin activated" message that other plugins get when they are installed. These events where not logged before because the plugin was not installed and could therefor not log its own installation. Solution was to log it manually. Works. Looks good. But perhaps a bit of cheating.
-   Added: A (hopefully) better welcome message when activating the plugin for the first time. Hopefully the new message makes new users understand a bit better why the log may be empty at first.

= 2.1.5 (August 2015) =

-   Fixed: It was not possible to modify the filters `simple_history/view_settings_capability` and `simple_history/view_history_capability` from the `functions.php`-file in a theme (filters where applied too early - they did however work from within a plugin!)
-   Changed: Use `h1` instead of `h2` on admin screens. Reason for this the changes in 4.3: https://make.wordpress.org/core/2015/07/31/headings-in-admin-screens-change-in-wordpress-4-3/.
-   Removed: the constant `VERSION` is now removed. Use constant `SIMPLE_HISTORY_VERSION` instead of you need to check the current version of Simple History.

= 2.1.4 (July 2015) =

-   Fixed: WordPress core updates got the wrong previous version.
-   Updated: Updated German translations.
-   Added: GHU header added to plugin header, to support [GitHub Updater plugin](https://github.com/afragen/github-updater).

= 2.1.3 (July 2015) =

-   Fixed: Ajax error when loading a log that contained uploaded images.
-   Fixed: Removed some debug log messages.

= 2.1.2 (July 2015) =

-   Changed: By default the log now shows events from the last week, last two weeks or last 30 days, all depending on how many events you have in your log. The previous behavior was to not apply any filtering what so ever during the first load. Anyway: this change makes it possible to load the log very quickly even for very large logs. A large amount of users + keeping the log forever = millions of rows of data. Previously this could stall the log or make it load almost forever. Now = almost always very fast. I have tried it with over 5.000 users and a million row and yes - zing! - much faster. Fixes https://wordpress.org/support/topic/load-with-pagination-mysql.
-   Added: Finnish translation. Thanks a lot to the translator!
-   Updated: Swedish translation updated
-   Added: Cache is used on a few more places.
-   Added: Plugin now works as a ["must-use-plugin"](https://codex.wordpress.org/Must_Use_Plugins). Props [jacquesletesson](https://github.com/jacquesletesson).
-   Added: Filter `SimpleHistoryFilterDropin/show_more_filters_on_load` that is used to control if the search options should be expanded by default when the history page is loaded. Default is false, to have a less cluttered GUI.
-   Added: Filter `SimpleHistoryFilterDropin/filter_default_user_ids` that is used to search/filter specific user ids by default (no need to search and select users). Should fix https://wordpress.org/support/topic/how-to-pass-array-of-user-ids-to-history-query.
-   Added: Filter `SimpleHistoryFilterDropin/filter_default_loglevel` that is used to search/filter for log levels by default.
-   Fixed: if trying to log an array or an object the logger now automagically runs `json_encode()` on the value to make it a string. Previously is just tried to run `$wpdb->insert()` with the array and that gave errors. Should fix https://wordpress.org/support/topic/mysql_real_escape_string.
-   Fixed: The function that checks for new rows each second (or actually each tenth second to spare resources) was called an extra time each time the submit button for the filter was clicked. Kinda stupid. Kinda fixed now.
-   Fixed: The export feature that was added in version 2.1 was actually not enabled for all users. Now it is!
-   Fixed: Image attachments that is deleted from file system no longer result in "broken image" in the log. (Rare case, I know, but it does happen for me that local dev server and remote prod server gets out of "sync" when it comes to attachments.)

= 2.1.1 (May 2015) =

-   Removed: filter `simple_history/dropins_dir` removed.
-   Changed: Dropins are not loaded from a `glob()` call anymore (just like plugins in the prev release)
-   Updated: Brazilian Portuguese translation updated.
-   Fixed: POT file updated for translators.
-   Fixed: Better sanitization of API arguments.

= 2.1 (May 2015) =

-   Added: Export! Now it's possible to export the events log to a JSON or CSV formatted file. It's your data so you should be able to export it any time you want or need. And now you can do that. You will find the export function in the Simple History settings page (Settings -> Simple History).
-   Added: Filter `simple_history/add_custom_logger` and function `register_logger` that together are used to load external custom loggers. See [example-logger.php](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/example-logger.php) for usage example.
-   Added: Filter `simple_history/header_initiator_use_you`.
-   Fixed: Fixed an undefined variable in get_avatar(). Fixes https://github.com/bonny/WordPress-Simple-History/issues/74.
-   Fixed: When using [HyperDB](https://wordpress.org/support/plugin/hyperdb) only one event was returned. Fixed by using [adding `NO_SELECT_FOUND_ROWS` to the query](https://plugins.trac.wordpress.org/browser/hyperdb/trunk/db.php?#L49). Should fix problems for users using HyperDB and also users using for example [wpengine.com](http://wpengine.com) (that probably also is using HyperDB or a similar approach).
-   Changed: Loggers now get default capability "manage_options" if they have no capability set.
-   Changed: Misc internal cleanup.
-   Removed: filter `simple_history/loggers_dir` removed, because loggers are loaded from array instead of file listing generated from `glob()`. Should be (however to the eye non-noticeable) faster.

= 2.0.30 (May 2015) =

-   Added: Username of logged events now link to that user's profile.
-   Fixed: When expanding occasions the first loaded occasion was the same event as the one you expanded from, and the last occasion was missing. Looked extra stupid when only 1 occasion existed, and you clicked "show occasions" only to just find the same event again. So stupid. But fixed now!
-   Fixed: If an event had many similar events the list of similar events could freeze the browser. ([17948 failed login attempts overnight](https://twitter.com/eskapism/status/595478847598002176) is not that uncommon it turns out!)
-   Fixed: Some loggers were missing the "All"-message in the search.
-   Changed: Hide some more keys and values by default in the context data popup.
-   Changed: Use `truncate` instead of `delete` when clearing the database. Works much faster on large logs.

= 2.0.29 (April 2015) =

-   Added: Introducing [Post "Quick Diff"](http://eskapism.se/blog/2015/04/quick-diff-shows-post-changes-in-wordpress/) – a very simple and efficient way to quickly see what’s been changed in a post. With Quick Diff you will in a glance see the difference between the title, permalink, content, publish date, post status, post author, or the template of the post. It's really a super simple and fast way to follow the work of your co-editors.
-   Added: Filter to add custom HTML above and after the context data table. They are named `simple_history/log_html_output_details_single/html_before_context_table` and `simple_history/log_html_output_details_single/html_after_context_table` (and yes, I do fancy really long filter names).
-   Added: Filters to control what to output in the data/context details table (the popup you see when you click the time of each event): `simple_history/log_html_output_details_table/row_keys_to_show` and `simple_history/log_html_output_details_table/context_keys_to_show`. Also added [two usage examples](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) for the filters.
-   Added: Filter `simple_history/log_insert_context` to control what gets saved to the context table. Example on usage for this is also available in the [example file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php).
-   Added: data attribute `data-ip-address-multiple` and class `SimpleHistoryLogitem--IPAddress-multiple` added for events that have more than one IP address detected. Happens when `http_x_forwarded_for` or similar headers are included in response.
-   Updated: Danish translation updated.
-   Fixed: Images in GitHub readme files are now displayed correctly.
-   Fixed: Readme files to GitHub repositories ending with slash (/) now works correctly too.
-   Fixed: IP Info popup is now again closeable with `ESC` key or with a click outside it.
-   Fixed: Some enqueued scripts had double slashes in them.
-   Fixed: Make sure [URLs from add_query_arg() gets escaped](https://make.wordpress.org/plugins/2015/04/20/fixing-add_query_arg-and-remove_query_arg-usage/).
-   Fixed: Some other small things.

= 2.0.28 (April 2015) =

-   Fixed: Do not try to load the Translation Install API if using WordPress before 4.0. Fixes https://github.com/bonny/WordPress-Simple-History/issues/67.
-   Updated: German translation updated.

= 2.0.27 (April 2015) =

-   Fixed: Even better support for plugins from GitHub with the `GitHub Plugin URI` header. Plugin install, deactivations, and activations should have correct view-info-links now.
-   Updated: German translation updated.
-   Updated: Swedish translation updated.

= 2.0.26 (March 2015) =

-   Fixed: Plugin installs from wordpress.org would show "wordpress plugin directory" as their source file. Looked stupid. Fixed now!
-   Added: `composer.json` added, so Simple History can be pulled in to other projects via [Composer](https://getcomposer.org/). Actually untested, but at least the file is there. Please let me know if it works! :)

= 2.0.25 (March 2015) =

-   Added: Plugin installs now shows the source of the plugin. Supported sources are "WordPress plugin repository" and "uploaded ZIP archives".
-   Added: Plugin installs via upload now shows the uploaded file name.
-   Added: Support for showing plugin info-link for plugins from GitHub, installed with uploaded ZIP-archive. Only tested with a few plugins. Please let me know if it works or not!
-   Fixed: Messages for disabled loggers was not shown.
-   Fixed: An error when trying to show edit link for deleted comments.
-   Fixed: Use a safer way to get editable roles. Hopefully fixes https://wordpress.org/support/topic/php-warnings-simpleloggerphp-on-line-162.
-   Fixed: Some notice warnings from the comments logger.
-   Changed: Some other small things too.

= 2.0.24 (March 2015) =

-   Fixed: Plugin installs from uploaded ZIP files are now logged correctly. Fixes https://github.com/bonny/WordPress-Simple-History/issues/59.
-   Fixed: Check that JavaScript variables it set and that the object have properties set. Fixes https://wordpress.org/support/topic/firefox-37-js-error-generated-by-simplehistoryipinfodropinjs.
-   Updated: German translation updated.
-   Changed: Loading of loggers, dropins, and so one are moved from action `plugins_loaded` to `after_setup_theme` so themes can actually use for example the load*dropin*\*-filters...
-   Changed: Misc small design fixes.

= 2.0.23 (March 2015) =

-   Added: Filter `simple_history/rss_item_link`, so plugins can modify the link used in the RSS feed.
-   Added: Links for changed posts and attachments in RSS feed now links directly to WordPress admin, making is easier to follow things from your RSS reeder.
-   Added: Filters to hide history dashboard widget and history dashboard page. Filters are `simple_history/show_dashboard_widget` and `simple_history/show_dashboard_page`.
-   Fixed: A missing argument error when deleting a plugin. Fixes https://wordpress.org/support/topic/warning-missing-argument-1-for-simplepluginlogger.

= 2.0.22 (February 2015) =

-   Fixed: Deleted plugins were not logged correctly (name and other info was missing).
-   Added: Filter `simple_history/logger/load_logger` and `simple_history/dropin/load_dropin` that can be used to control the loading of each logger or dropin. See [example file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) for usage examples.
-   Fixed: modal window with context data now works better on small screens.
-   Changed: Misc internal changes.

= 2.0.21 (February 2015) =

-   Added: Updates via XML RPC are now logged, for example when using the WordPress app for iOS or Android. Supported actions for now is post/page created, edited, deleted, and media uploads.
-   Added: `_xmlrpc_request` is added to context of event when an event is initiated through a XML-RPC all.
-   Changed: RSS feed now has loglevel of event prepended to the title.
-   Changed: Options logger now only shows the first 250 chars of new and old option values. Really long values could make the log look strange.
-   Added: If constant `SIMPLE_HISTORY_LOG_DEBUG` is defined and true automatically adds `$_GET`, `$_POST`, and more info to each logged event. Mostly useful for the developer, but maybe some of you are a bit paranoid and want it too.
-   Updated: German translation updated.

= 2.0.20 (February 2015) =

-   Added: changes via [WP-CLI](http://wp-cli.org) is now detected (was previously shown as "other").
-   Added: severity level (info, warning, debug, etc.) of event is includes in the RSS output.
-   Changed the way user login is logged. Should fix https://github.com/bonny/WordPress-Simple-History/issues/40 + possible more related issues.
-   Added: filter `simple_history/simple_logger/log_message_key` added, that can be used to shortcut log messages. See [example file](https://github.com/bonny/WordPress-Simple-History/blob/master/examples/examples.php) for usage. Fixes https://wordpress.org/support/topic/stop-logging-certain-types-of-activity.
-   Added: now uses object caching at some places. Should speed up some parts of the plugin for users with caching enabled.
-   Fixed: IP info popup can now be closed with `esc`.
-   Fixed: works better on small screens (like mobile phones) + misc other style related fixes.

= 2.0.19 (February 2015) =

-   Added: Dutch translation by [https://github.com/niknetniko](https://github.com/niknetniko). Thanks!
-   Changed: better compatibility with plugins like [WP User Avatar](https://wordpress.org/plugins/wp-user-avatar/).
-   Updated: German translation update.

= 2.0.18 (January 2015) =

-   Fixed: really correctly show the version number of the previous version!

= 2.0.17 (January 2015) =

-   Added: messages added using for example `SimpleLogger()->info( __("My log message") )` that have translations now auto translated the message back to english before storing the message (together with the text domain). Then upon retrieval it uses the english message + the text domain to translate the message to the currently selected language. This makes it easier to make multilingual log entries. (Yeah, I know its hard to understand what the heck this does, but it's something good and cool, trust me!)
-   Added: A sidebar with text contents on the history page.
-   Changed: Search now shows only the search box by default, with a link to show all search options.
-   Fixed: Search is now available at the dashboard again. Hooray!
-   Fixed: Old entries were not cleared automatically. Now it correctly removes old events, so your database will not risk growing to large.
-   Fixed: Quick stats could show two messages sometimes.
-   Fixed: When headers like `HTTP_X_FORWARDED_FOR` exists all valid IPs in that header is now stored.
-   Fixed: Plugin updates via third party software like [InfiniteWP](http://infinitewp.com/) should now correctly show the version number of the previous version.
-   Updated: German translation updated.
-   Notice: Do you read these messages? Then you must love this plugin! Come on then, [go and give it a nice review](https://wordpress.org/support/view/plugin-reviews/simple-history).

= 2.0.16 (January 2015) =

-   Fixed: Use the [X-Forwarded-For header](http://en.wikipedia.org/wiki/X-Forwarded-For), if it is set, to determine remote IP address. Should now correctly store IP addresses for servers behind load balancers or for clients going through proxies. Fixes https://wordpress.org/support/topic/use-x-forwarded-for-http-header-when-logging-remote_addr.
-   Changed: Failed login attempts from unknown and known users are now grouped together. This change was made because a hacker could make many login attempts to a site and rotate the logins, so they would try with both existing and non existing user names, which would make the log flood with failed login attempts.
-   Changed: use "n similar events" instead of "n more", to more clearly mark that the grouped events are not necessary exactly the same kind.
-   Changed: Quick stats text changed, to also include other sources. Previous behavior was to only include events from WordPress users, but now also events from anonymous users and WordPress (like from WP-Cron) are included.

= 2.0.15 (January 2015) =

-   Fixed: Widget changes where not always translated.
-   Fixed: More RSS fixes to make feed valid. Maybe even for real this time.
-   Updated: German translation updated.

= 2.0.14 (January 2015) =

-   Added: Danish translation added. Thanks [ThomasDK81](https://github.com/ThomasDK81)!
-   Misc translation fixes, for example the log levels where not translatable (it may be a good idea to keep the original English ones however because they are the ones that are common in other software).

= 2.0.13 (January 2015) =

-   Fixed: RSS feed is now valid according to http://validator.w3.org/. Fixes https://wordpress.org/support/topic/a-feed-which-was-valid-under-v206-is-no-longer-under-v209-latest.
-   Translation fixes. Thanks [ThomasDK81](https://github.com/ThomasDK81)!

= 2.0.12 (January 2015) =

-   Fixed: Deleted attachments did not get translations.
-   Fixed: A notice when showing details for a deleted attachment.

= 2.0.11 (January 2015) =

-   Fixed: Comments where not logged correctly.
-   Fixed: Comments where not translated correctly.
-   Updated: German translation updated.

= 2.0.10 (January 2015) =

-   Updated: Polish translation updated. Thanks [https://github.com/m-czardybon](m-czardybon)!
-   Updated: German translation updated. Thanks [http://klein-aber-fein.de/](Ralph)!
-   Updated: Swedish translation updated.

= 2.0.9 (December 2014) =

-   Actually enable IP address lookup for all users. Sorry for missing to do that! ;)

= 2.0.8 (December 2014) =

-   Added: IP addresses can now be clicked to view IP address info from [ipinfo.io](http://ipinfo.io). This will get you the location and network of an IP address and help you determine from where for example a failed login attempt originates from. [See screenshot of IP address info in action](http://glui.me/?d=y89nbgmvmfnxl4r/ip%20address%20information%20popup.png/).
-   Added: new action `simple_history/admin_footer`, to output HTML and JavaScript in footer on pages that belong to Simple History
-   Added: new trigger for JavaScript: `SimpleHistory:logReloadStart`. Fired when the log starts to reload, like when using the pagination or using the filter function.
-   Fixed: use Mustache-inspired template tags instead of Underscore default ones, because they don't work with PHP with asp_tags on.
-   Updated: Swedish translation updated

= 2.0.7 (December 2014) =

-   Fix: no message when restoring page from trash
-   Fix: use correct width for media attachment
-   Add: filter `simple_history/logrowhtmloutput/classes`, to modify HTML classes added to each log row item

= 2.0.6 (November 2014) =

-   Added: [WordPress 4.1 added the feature to log out a user from all their sessions](http://codex.wordpress.org/Version_4.1#Users). Simple History now logs when a user is logged out from all their sessions except the current browser, or if an admin destroys all sessions for a user. [View screenshot of new session logout log item](https://dl.dropboxusercontent.com/s/k4cmfmncekmfiib/2014-12-simple-history-changelog-user-sessions.png)

-   Added: filter to shortcut loading of a dropin. Example that completely skips loading the RSS-feed-dropin:
    `add_filter("simple_history/dropin/load_dropin_SimpleHistoryRSSDropin", "__return_false");`

= 2.0.5 (November 2014) =

-   Fix undefined variable in plugin logger. Fixes https://wordpress.org/support/topic/simple-history-201-is-not-working?replies=8#post-6343684.
-   Made the dashboard smaller
-   Misc other small GUI changes

= 2.0.4 (November 2014) =

-   Make messages for manually updated plugins and bulk updated plugins more similar

= 2.0.3 (November 2014) =

-   Show the version of PHP that the user is running, when the PHP requirement of >= 5.3 is not met

= 2.0.2 (November 2014) =

-   Fixed wrong number of arguments used in filter in RSS-feed

= 2.0.1 (November 2014) =

-   Removed anonymous function in index file causing errors during install on older versions of PHP

= 2.0 (November 2014) =

Major update - Simple History is now better and nicer than ever before! :)
I've spend hundreds of hours making this update, so if you use it and like it please [donate to keep my spirit up](http://eskapism.se/sida/donate/) or [give it a nice review](https://wordpress.org/support/view/plugin-reviews/simple-history).

-   Code cleanup and modularization
-   Support for log contexts
-   Kinda PSR-3-compatible :)
-   Can handle larger logs (doesn't load whole log into memory any more)
-   Use nonces at more places
-   More filters and hooks to make it easier to customize
-   Better looking! well, at least I think so ;)
-   Much better logging system to make it much easier to create new loggers and to translate logs into different languages
-   Features as plugins: more things are moved into modules/its own file
-   Users see different logs depending on their capability, for example an administrator will see what plugins have been installed, but an editor will not see any plugin related logs
-   Much much more.

= 1.3.11 =

-   Don't use deprecated function get_commentdata(). Fixes https://wordpress.org/support/topic/get_commentdata-function-is-deprecated.
-   Don't use mysql_query() directly. Fixes https://wordpress.org/support/topic/deprecated-mysql-warning.
-   Beta testers wanted! I'm working on the next version of Simple History and now I need some beta testers. If you want to try out the shiny new and cool version please download the [v2 branch](https://github.com/bonny/WordPress-Simple-History/tree/v2) over at GitHub. Thanks!

= 1.3.10 =

-   Fix: correct usage of "its"
-   Fix: removed serif font in log. Fixes https://wordpress.org/support/topic/two-irritations-and-pleas-for-change.

= 1.3.9 =

-   Fixed strict standards warning
-   Tested on WordPress 4.0

= 1.3.8 =

-   Added filter for rss feed: `simple_history/rss_feed_show`. Fixes more things in this thread: http://wordpress.org/support/topic/more-rss-feed-items.

= 1.3.7 =

-   Added filter for rss feed: `simple_history/rss_feed_args`. Fixes http://wordpress.org/support/topic/more-rss-feed-items.

= 1.3.6 =

-   Added Polish translation
-   Added correct XML encoding and header
-   Fixed notice warnings when media did not exist on file system

= 1.3.5 =

-   Added a reload-button at top. Click it to reload the history. No need to refresh page no more!
-   Fixed items being reloaded when just clicking the dropdown (not having selected anything yet)
-   Fixed bug with keyboard navigation
-   Added Portuguese translation by [X6Web](http://x6web.com)
-   Use less SQL queries

= 1.3.4 =

-   Changed the way post types show in the dropdown. Now uses plural names + not prefixed with main post type. Looks better I think. Thank to Hassan for the suggestion!
-   Added "bytes" to size units that an attachment can have. Also fixes undefined notice warning when attachment had a size less that 1 KB.

= 1.3.3 =

-   Capability for viewing settings changed from edit_pages to the more correct [manage_options](http://codex.wordpress.org/Roles_and_Capabilities#manage_options)

= 1.3.2 =

-   Could get php notice warning if rss secret was not set. Also: make sure both public and private secret exists.

= 1.3.1 =

-   Improved contrast for details view
-   Fix sql error on installation due to missing column
-   Remove options and database table during removal of plugin
-   Added: German translation for extender module

= 1.3 =

-   Added: history events can store text description with a more detailed explanation of the history item
-   Added: now logs failed login attempts for existing username. Uses the new text description to store more info, for example user agent and remote ip address (REMOTE_ADDR)
-   Fixed: box did not change height when clicking on occasions
-   Fixed: use on() instead of live() in JavaScript

= 1.2 =

-   Fixed: Plugin name is included when plugins is activated or deactivated. Previously only folder name and name of php file was included.
-   Added: Attachment thumbnails are now visible if history item is an attachment. Also includes some metadata.
-   Changed: Filters now use dropdowns for type and user. When a site had lots of users and lots of post types, the filter section could be way to big.
-   Added keyboard navigation. Use right and left arrow when you are on Simple History's own page to navigation between next and previous history page.
-   Added loading indicator, so you know it's grabbing your history, even if it's taking a while
-   Misc JS and CSS fixes
-   Arabic translation updated
-   POT-file updated

= 1.1 =

-   Added the Simple History Extender-module/plugin. With this great addon to Simple History it is very easy for other developers to add their own actions to simple history, including a settings panel to check actions on/off. All work on this module was made by Laurens Offereins (lmoffereins@gmail.com). Super thanks!
-   With the help of Simple History Extender this plugin also tracks changes made in bbPress, Gravity Forms and in Widgets. Awesome!
-   Added user email to RSS feed + some other small changed to make it compatible with IFTTT.com. Thanks to phoenixMagoo for the code changes. Fixes http://wordpress.org/support/topic/suggestions-a-couple-of-tweaks-to-the-rss-feed.
-   Added two filters for the RSS feed: simple_history_rss_item_title and simple_history_rss_item_description.
-   Changed the way the plugin directory was determined. Perhaps and hopefully this fixes some problems with multi site and plugin in different locations and stuff like that
-   Style fixes for RTL languages
-   Small fixes here and there, for example changing deprecated WordPress functions to not deprecated
-   Added new filter: simple_history_db_purge_days_interval. Hook it to change default clear interval of 60 days.

= 1.0.9 =

-   Added French translation

= 1.0.8 =

-   Added: filter simple_history_allow_db_purge that is used to determine if the history should be purged/cleaned after 60 days or not. Return false and it will never be cleaned.
-   Fixed: fixed a security issue with the RSS feed. User who should not be able to view the feed could get access to it. Please update to this version to keep your change log private!

= 1.0.7 =

-   Fixed: Used a PHP shorthand opening tag at a place. Sorry!
-   Fixed: Now loads scripts and styles over HTTPS, if that's being used. Thanks to "llch" for the patch.

= 1.0.6 =

-   Added: option to set number of items to show, per page. Default i 5 history log items.

= 1.0.5 =

-   Fixed: some translation issues, including updated POT-file for translators.

= 1.0.4 =

-   You may want to clear the history database after this update because the items in the log will have mixed translate/untranslated status and it may look/work a bit strange.
-   Added: Option to clear the database of log items.
-   Changed: No longer stored translated history items in the log. This makes the history work even if/when you switch language of WordPress.
-   Fixed: if for example a post was edited several times and during these edits it changed name, it would end up at different occasions. Now it's correctly stored as one event with several occasions.
-   Some more items are translatable

= 1.0.3 =

-   Updated German translation
-   Some translation fixes

= 1.0.2 =

-   Fixed a translation bug
-   Added updated German translation

= 1.0.1 =

-   The pagination no longer disappear after clickin "occasions"
-   Fixed: AJAX loading of new history items didn't work.
-   New filter: simple_history_view_history_capability. Default is "edit_pages". Modify this to change what capability is required to view the history.
-   Modified: styles and scripts are only added on pages that use/show Simple History
-   Updated: new POT file. So translators my want to update their translations...

= 1.0 =

-   Added: pagination. Gives you more information, for example the number of items, and quicker access to older history items. Also looks more like the rest of the WordPress GUI.
-   Modified: search now searches type of action (added, modified, deleted, etc.).

= 0.8.1 =

-   Fixed some annoying errors that slipt through testing.

= 0.8 =

-   Added: now also logs when a user saves any of the built in settings page (general, writing, reading, discussion, media, privacy, and permalinks. What more things do you want to see in the history? Let me know in the [support forum](http://wordpress.org/support/plugin/simple-history).
-   Added: gravatar of user performing action is always shown
-   Fixed: history items that was posts/pages/custom post types now get linked again
-   Fixed: search is triggered on enter (no need to press search button) + search searches object type and object subtype (before it just searched object name)
-   Fixed: showing/loading of new history items was kinda broken. Hopefully fixed and working better than ever now.
-   Plus: even more WordPress-ish looking!
-   Also added donate-links. Tried to keep them discrete. Anyway: please [donate](http://eskapism.se/sida/donate/?utm_source=wordpress&utm_medium=changelog&utm_campaign=simplehistory) if you use this plugin regularly.

= 0.7.2 =

-   Default settings should be to show on page, missed that one. Sorry!

= 0.7.1 =

-   Fixed a PHP shorttag

= 0.7 =

-   Do not show on dashboard by default to avoid clutter. Can be enabled in settings.
-   Add link to settings from plugin list
-   Settings are now available as it's own page under Settings -> Simple Fields. It was previously on the General settings page and some people had difficulties finding it there.
-   Added filters: simple_history_show_settings_page, simple_history_show_on_dashboard, simple_history_show_as_page

= 0.6 =

-   Changed widget name to just "History" instead of "Simple History". Keep it simple. Previous name implied there also was an "Advanced History" somewhere.
-   Made the widget look a bit WordPress-ish by borrwing some of the looks from the comments widget.
-   Fix for database that didn't use UTF-8 (sorry international users!)
-   Some security fixes
-   Updated POT-file

= 0.5 =

-   Added author to RSS
-   Added german translation, thanks http://www.fuerther-freiheit.info/
-   Added swedish translation, thanks http://jockegustin.se
-   Better support for translation

= 0.4 =

-   Added: Now you can search the history
-   Added: Choose if you wan't to load/show more than just 5 rows from the history

= 0.3.11 =

-   Fixed: titles are now escaped

= 0.3.10 =

-   Added chinese translation
-   Fixed a variable notice
-   More visible ok-message after setting a new RSS secret

= 0.3.9 =

-   Attachment names were urlencoded and looked weird. Now they're not.
-   Started to store plugin version number

= 0.3.8 =

-   Added chinese translation
-   Uses WordPress own human_time_diff() instead of own version
-   Fix for time zones

= 0.3.7 =

-   Directly after installation of Simple History you could view the history RSS feed without using any secret. Now a secret is automatically set during installation.

= 0.3.6 =

-   Made the RSS-feature a bit easier to find: added a RSS-icon to the dashboard window - it's very discrete, you can find it at the bottom right corner. On the Simple History page it's a bit more clear, at the bottom, with text and all. Enjoy!
-   Added POT-file

= 0.3.5 =

-   using get_the_title instead of fetching the title directly from the post object. should make plugins like qtranslate work a bit better.
-   preparing for translation by using \_\_() and \_e() functions. POT-file will be available shortly.
-   Could get cryptic "simpleHistoryNoMoreItems"-text when loading a type with no items.

= 0.3.4 =

-   RSS-feed is now valid, and should work at more places (could be broken because of html entities and stuff)

= 0.3.3 =

-   Moved JavaScript to own file
-   Added comments to the history, so now you can see who approved a comment (or unapproved, or marked as spam, or moved to trash, or restored from the trash)

= 0.3.2 =

-   fixed some php notice messages + some other small things I don't remember..

= 0.3.1 =

-   forgot to escape html for posts
-   reduced memory usage... I think/hope...
-   changes internal verbs for actions. some old history items may look a bit weird.
-   added RSS feed for recent changes - keep track of changes via your favorite RSS-reader

= 0.3 =

-   page is now added under dashboard (was previously under tools). just feel better.
-   mouse over on date now display detailed date a bit faster
-   layout fixes to make it cooler, better, faster, stronger
-   multiple events of same type, performed on the same object, by the same user, are now grouped together. This way 30 edits on the same page does not end up with 30 rows in Simple History. Much better overview!
-   the name of deleted items now show up, instead of "Unknown name" or similar
-   added support for plugins (who activated/deactivated what plugin)
-   support for third party history items. Use like this:
    simple_history_add("action=repaired&object_type=starship&object_name=USS Enterprise");
    this would result in something like this:
    Starship "USS Enterprise" repaired
    by admin (John Doe), just now
-   capability edit_pages needed to show history. Is this an appropriate capability do you think?

= 0.2 =

-   Compatible with 2.9.2

= 0.1 =

-   First public version. It works!
