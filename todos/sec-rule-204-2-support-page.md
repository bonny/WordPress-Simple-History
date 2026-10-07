> [!note] For Pär — published 2026-09-15
>
> -   **Suggested slug:** `/support/sec-rule-204-2-compliance-with-simple-history/` (same pattern as the HIPAA and FTC Franchise Rule pages)
> -   **Suggested title:** SEC Rule 204-2 Compliance with Simple History
> -   Written short on purpose (issue 41: compliance pages get no organic traffic, their value is as a link in support and sales replies). Body is about 740 words.
> -   Primary source for the rule: eCFR, 17 CFR 275.204-2, fetched 2026-09-14 via the eCFR versioner API (current text).
> -   **Copy review 2026-09-15:** body below rewritten for voice (em-dashes, signposting and stiff wording removed; US spelling; no facts changed). Page 4945 is published; the reviewed text went live on 2026-09-15, with three claim fixes: Divi/WPBakery hedged as "likely", the examiner FAQ no longer promises acceptance, and the revision link is no longer tied to edit-screen saves.
>
> **Claims and their sources**
>
> Rule (eCFR):
>
> -   Applies to advisers "registered or required to be registered under section 203" — 275.204-2(a) intro.
> -   Copy of each advertisement disseminated, directly or indirectly — 275.204-2(a)(11)(i)(A).
> -   Advertisement = communication to more than one person offering advisory services — 275.206(4)-1(e)(1)(i). The page says a public page offering your services "generally fits"; the rule text does not name websites, so that is hedged.
> -   Records behind performance figures in ads — 275.204-2(a)(16).
> -   Five years from end of fiscal year of last dissemination, first two in an appropriate office — 275.204-2(e)(3)(i).
> -   Electronic storage: index for easy retrieval (g)(2)(i); promptly produce a true, complete copy (g)(2)(ii); separate duplicate copy (g)(2)(iii); safeguard from loss, alteration or destruction (g)(3)(i); limit access (g)(3)(ii).
> -   Broker-dealers: Rule 17a-4 (17 CFR 240.17a-4); 204-2(h)(1) cross-references it.
> -   Marketing Rule "in force since November 2022" = compliance date 4 November 2022 — SEC small-entity compliance guide, sec.gov "Investment Adviser Marketing".
>
> Product (code):
>
> -   Who/when/IP: `_user_login`/`_user_email` context (`loggers/class-logger.php`), UTC date via `current_time( 'mysql', 1 )`; IP anonymised by default (`Helpers::privacy_anonymize_ip()`, filter `simple_history/privacy/anonymize_ip_address` defaults true).
> -   Content diff, usually changed passages plus a line of context, full before/after only when that is smaller: `Post_Logger::add_post_data_diff_to_context()` (`jfcherng_json_html_v1` with `context => 1` vs `full_content_v1`).
> -   Other diffed fields (title, slug, status, date, author, excerpt) and page template: same method plus `_wp_page_template` handling; custom-field changes are counted only (`post_meta_added/removed/changed`).
> -   Default retention 30 days fresh installs / 60 days existing: `Setup_Database::setup_version_8_to_version_9()`, `Helpers::get_clear_history_interval()`.
> -   Premium retention, "Keep forever" or a number of days: premium `inc/modules/class-misc-settings-module.php` (`filter_db_purge_days_interval()` returns 0 for keep forever; core `purge_db()` never purges at 0).
> -   Free export of the whole log as CSV/JSON/HTML: `dropins/class-export-dropin.php` + `inc/class-export.php` (pages through all rows). Premium filtered export: premium `inc/modules/class-export-module.php` + `src/ExportModal.js` (CSV/JSON/HTML).
> -   HTML export includes the event details (the diff) via `get_log_row_details_output()`; JSON includes the full row with context; CSV has summary text only (`output_csv_row()`).
> -   Free local log file channel: `inc/channels/class-file-channel.php`. Premium destinations: syslog (local/remote), Datadog, Splunk, webhook, external database — premium `inc/channels/`. Beta label: `class-channels-settings-page.php` `render_beta_notice()`.
> -   Forwarded content: file, syslog, Datadog and Splunk get the message plus only `_message_key`, `_server_remote_addr`, `_user_id`, `_user_login`, `_user_email` (`inc/channels/class-formatter.php` `ESSENTIAL_FIELDS`); webhook template has message/logger/level/initiator/message key only (premium `class-webhook-channel.php` `process_template()`). Only the external database channel stores the remaining context, including the content diff (premium `class-external-database-channel.php` `insert_event()`).
> -   Which saves get a content diff: the "before" post is saved only for the classic edit form (`admin_action_editpost`), Quick Edit (`wp_ajax_inline-save`), the REST posts controller (block editor) and WP-CLI (`on_pre_post_update()`), see `Post_Logger::loaded()`. Builder editors save through their own AJAX requests, so no diff. Tested 2026-09-14 with Elementor in a throwaway Playground: no content diff, but the event still links to the revision. Divi and WPBakery not tested (commercial), hence "likely".
> -   Each post edit event links to the revision it created (`post_revision_id` context, `get_event_revision_state()`; readme 5.32.0 changelog).
> -   `wp simple-history db clear` calls `Helpers::clear_log()` without firing `simple_history/settings/log_cleared`, so it leaves no event (`inc/services/wp-cli-commands/class-wp-cli-db-command.php`).
> -   "Clear log now" button, default for users with the settings capability (`manage_options`): `Helpers::user_can_clear_log()`, `Setup_Settings_Page`. Clearing truncates both tables (`Helpers::clear_log()`) and, from the settings button only, logs one "Cleared the log" event with the row count (`Simple_History_Logger::on_log_cleared()`).
>
> **Deliberately left out:** the free-core `simple_history/db_purge_days_interval` filter (developers can raise retention without Premium — true, but not a support-page point); the `simple_history/user_can_clear_log` filter that hides the Clear button (could be a follow-up FAQ if anyone asks); Divi/WPBakery back-end (shortcode) diffs, covered on the FTC page; who can view the log by default (`edit_pages`); state-registered advisers' own state rules (not researched).

---

# SEC Rule 204-2 Compliance with Simple History

If you're an SEC-registered investment adviser, you need to be able to show what your website said, and when. Simple History logs every update to your pages, which covers part of that.

## What the rule asks for

[Rule 204-2](https://www.ecfr.gov/current/title-17/chapter-II/part-275/section-275.204-2) under the Investment Advisers Act (17 CFR 275.204-2) applies to advisers who are registered, or required to be registered, with the SEC. Among other records, you need to keep:

-   a copy of each advertisement you publish or distribute, directly or indirectly (paragraph (a)(11)(i)(A))
-   the records behind any performance figures you show (paragraph (a)(16))
-   those records for five years from the end of the fiscal year the advertisement was last distributed, with the first two years in an appropriate office (paragraph (e)(3)(i))

Electronic records also have to meet paragraph (g). They must be indexed so any record can be found, produced promptly as a true and complete copy, backed up by a separate duplicate, protected from loss, alteration or destruction, and only accessible to authorized people.

What counts as an advertisement? Under the [Marketing Rule](<https://www.ecfr.gov/current/title-17/chapter-II/part-275/section-275.206(4)-1>), in force since November 2022, it's any communication to more than one person that offers your advisory services. So a public page describing your services will usually count.

If you're a broker-dealer, [Rule 17a-4](https://www.ecfr.gov/current/title-17/chapter-II/part-240/section-240.17a-4) applies instead.

## What the free plugin records

Every time a page or post is updated, Simple History logs:

-   who made the change (the WordPress user, with login and email)
-   when, to the second
-   the IP address, anonymized by default
-   what changed in the content, with the removed and added text highlighted
-   other changed fields, like title, URL slug, status, publish date, author and page template
-   a link to the WordPress revision the edit created, if revisions are on

You don't need to set anything up. The content and field changes are recorded when the page is saved from the normal WordPress edit screen.

Every change is dated, so the log also shows when a version was last live. You need that date to work out when its five years start.

## What Premium adds

[Premium](https://simple-history.com/add-ons/premium/) lets you keep events for any number of days, or forever, which is long enough for the rule.

The free plugin exports the whole log as CSV, JSON or HTML. Premium can export just the events you've filtered to, for example one page within a date range.

Log forwarding is in beta. The free plugin can write events to a log file on the server. Premium adds syslog, Datadog, Splunk and webhooks, which get a one-line summary of each event, and an external database, which stores the full event including the content changes.

## Limitations

-   **Default retention is 30 or 60 days.** Five years from the end of the fiscal year is often close to six years. The log isn't retroactive, so set retention before you need it.
-   **No copy of the rendered page.** The log stores the text that changed. Theme, menus, widgets, images and dynamic content aren't in it. We tested Elementor: a save from its editor is logged with who and when, but without the before-and-after text. The front-end editors of Divi and WPBakery likely work the same way. The rule asks for a copy of each advertisement, so keep WordPress revisions turned on and consider a website archiving service.
-   **Not tamper-proof.** Events are stored in ordinary WordPress database tables, not write-once storage. If someone uses **Clear log now** in the settings, one event is left saying how many rows were removed. If they use the WP-CLI command `wp simple-history db clear`, nothing is left. Anyone with database access can edit rows. For a copy that clearing the log doesn't touch, forward events to an external database with Premium.
-   **Not legal advice.** Whether your records meet Rule 204-2 is a question for your chief compliance officer or counsel.

## FAQ

**Does installing Simple History make us compliant?**
No. It gives you evidence of what changed on your site, who changed it and when.

**What retention should we set?**
Ask your compliance officer. If you're unsure, pick "Keep forever" in Premium.

**Can we hand the log to an SEC examiner?**
You can give them an export. Search for the page, pick the date range and export with Premium. HTML and JSON exports include the change details. CSV has one summary line per event. Whether that's enough is for your compliance officer to judge.

---

Not sure if this covers your setup? [Get in touch](https://simple-history.com/support/) and tell us how your site is built.
