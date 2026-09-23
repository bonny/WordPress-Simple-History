> [!note] For Pär — published 2026-09-15
>
> -   **Slug:** `/support/ftc-franchise-rule-compliance-with-simple-history/` (matches the HIPAA page pattern, `/support/hipaa-compliance-with-simple-history/`)
> -   **Title:** FTC Franchise Rule Compliance with Simple History
> -   **Live draft:** page id 4903 on simple-history.com, published 2026-09-15, author Pär (id 1). This file and the draft were rewritten together on 2026-09-14 after a review.
> -   **Excerpt:** Simple History records who changed the earnings figures on a franchise sales page, when, and what changed. What the FTC Franchise Rule asks for, what the log covers, and where it stops.
> -   The support email from **2026-09-01** (agency asking on behalf of a franchise client whether we "document if and when these numbers changed") is still waiting on a reply. A corrected draft reply is in issue 41. Link this page from it once published.
> -   Still unanswered in that thread: which editor the franchise sales page uses. If it is Elementor or another page builder's own editor, the content diff does not apply.
> -   **Copy review 2026-09-15:** body below rewritten for voice (AI tells removed, no facts changed, button name corrected to "Clear log now"). Page is published; the reviewed text went live on 2026-09-15, plus the `wp simple-history db clear` line in Limitations.
>
> **Claims and sources (checked 2026-09-14):**
>
> -   FPR definition: 16 CFR 436.1(e). Item 19 + reasonable basis + written substantiation, and the "results may differ" admonition: 436.9(c). Exceptions that make it "generally": 436.5(s)(4), (s)(5). Retention of disclosure documents and receipts: 436.6(h), (i).
> -   Retention default 30 days for fresh installs, 60 for older ones: `inc/services/class-setup-database.php:445`, `Helpers::get_clear_history_interval()`. Filter `simple_history/db_purge_days_interval`. Premium days or keep forever: premium `inc/modules/class-misc-settings-module.php:130`; 0 days never purges, `inc/services/class-setup-purge-db-cron.php:107`.
> -   IP anonymized by default (`142.250.74.x`): `Helpers::privacy_anonymize_ip()`. Premium "Store full IP address": premium `class-misc-settings-module.php:70`.
> -   Content stored as the changed passage (compact word diff since 5.24.0), full text only when that is not smaller: `loggers/class-post-logger.php:1061-1104`. Events link to the revision they created since 5.32.0: `class-post-logger.php:1672`.
> -   Page builders: a "before" copy is only taken for the classic edit form, Quick Edit, bulk edit, REST (block editor) and WP-CLI (`class-post-logger.php:110-113`, `233-236`, `441-451`). **Tested 2026-09-14** in a throwaway Playground with Elementor: changing a heading from 184000 to 250000 in the Elementor editor logged three "Updated page" events with "custom fields added" and no content diff, although Elementor did write the new figure to `post_content` and the linked revision holds it. A block editor save on the same site logged the full before/after text. Divi and WPBakery were not tested (commercial), hence "likely".
> -   Export: free exports the whole log; Premium exports the filtered view (same `Export` class). CSV has no IP and no content change (`inc/class-export.php:243-289`); HTML includes the details/diff (`313-345`); JSON has the full context (`299-306`). No event cap: batches of 250, all pages (`inc/class-export.php:113-114`, `165-196`).
> -   Forwarding: only the Remote Database channel stores full context (premium `inc/channels/class-external-database-channel.php:368-394`); file, syslog, Datadog, Splunk, webhook send message + user + IP (`inc/channels/class-formatter.php:37-43`). Beta label: `inc/services/class-channels-settings-page.php:477`.
> -   Clear log: `manage_options` (`Helpers::user_can_clear_log()`), logs "Cleared the log" when used from settings; `wp simple-history db clear` leaves no event (`inc/services/wp-cli-commands/class-wp-cli-db-command.php`) (`inc/services/class-setup-settings-page.php:756`, `loggers/class-simple-history-logger.php:42`).

---

# FTC Franchise Rule Compliance with Simple History

Do your franchise sales pages show earnings or revenue figures? Then you may need to prove what those figures said, and when they changed. Simple History keeps that record.

## What the rule requires

The [FTC Franchise Rule](https://www.ecfr.gov/current/title-16/chapter-I/subchapter-D/part-436) (16 CFR Part 436) covers _financial performance representations_. That's any statement, express or implied, of a specific level or range of actual or potential sales, income or profits, including historical figures from existing outlets. A figure on your website counts.

With a few exceptions, you can only use one if it's also in Item 19 of your Franchise Disclosure Document, and you had a reasonable basis and written substantiation for it when you made it ([16 CFR 436.9](https://www.ecfr.gov/current/title-16/section-436.9)). It also needs the warning that a new franchisee's results may differ. More in the FTC's [compliance guide](https://www.ftc.gov/business-guidance/resources/franchise-rule-compliance-guide).

## What the free plugin records

When a page is updated, Simple History logs:

-   who made the change, with name and email
-   when, to the second
-   the IP address, anonymized by default (`142.250.74.x`). Premium can store the full address.
-   what changed: the edited text before and after, plus title, slug, status, date and author

For pages edited in the block editor or the classic editor, there's nothing to switch on.

## What Premium adds

The free plugin keeps 30 days of events (60 on older installs), which you can change [with a filter](https://simple-history.com/support/change-number-of-days-to-keep-log/). [Premium](https://simple-history.com/add-ons/premium/) keeps any number of days, or forever.

The free plugin exports the whole log. Premium can export just the page and dates you're asked about. Pick HTML or JSON to get the before-and-after text. CSV has one summary line per event.

Premium can also send events off-site. An external MySQL or MariaDB database gets the full event. Syslog, Datadog, Splunk and webhooks get a one-line summary. Log forwarding is in beta.

## Limitations

-   Page builders work differently. We tested Elementor: saving from its editor logs who and when, but not the before-and-after text, though the linked WordPress revision has the new version. Divi's and WPBakery's front-end editors likely behave the same.
-   Retention isn't retroactive. Set it before anyone asks about an old change.
-   The log records changes, not snapshots. To show exactly how a page looked on a certain date, keep WordPress revisions on, or keep backups.
-   The log is stored in your site's database. An administrator can empty it with the Clear log now button (that gets logged too) or with the WP-CLI command `wp simple-history db clear` (that doesn't), and anyone with database access can change it. For a record that stands on its own, forward events to an external database.
-   This isn't legal advice. The rule's own record-keeping covers disclosure documents and signed receipts for three years (16 CFR 436.6(h), (i)), and state laws can add more. Ask your franchise counsel what applies.

---

Not sure if this covers your setup? [Get in touch](https://simple-history.com/support/) and tell us which editor you use for the page.
