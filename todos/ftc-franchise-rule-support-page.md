> [!note] For Pär — draft only, not published
>
> -   **Suggested slug:** `/support/ftc-franchise-rule-compliance-with-simple-history/` (matches the HIPAA page pattern, `/support/hipaa-compliance-with-simple-history/`)
> -   **Suggested title:** FTC Franchise Rule Compliance with Simple History
> -   The support email from **2026-09-01** (agency asking on behalf of a franchise client whether we "document if and when these numbers changed") is still waiting on a reply. Once this page is live you can link to it from that reply instead of writing the whole explanation out.
> -   Still unanswered in that thread: which editor the franchise sales page uses. If it is Elementor or another page builder, the content diff does not apply — see the Limitations section before promising anything.

---

# FTC Franchise Rule Compliance with Simple History

If you sell franchises and your website carries earnings or revenue figures, you need to be able to show what those numbers were and when they changed. Simple History gives you that record.

## What the rule asks for

The [FTC Franchise Rule](https://en.wikipedia.org/wiki/Franchise_rule) (16 CFR Part 436) covers _financial performance representations_ — any claim about the revenue, sales or profits a franchisee might earn. If you make one — and the rule counts claims made in the general media, not just those made directly to a prospect — you may not disseminate it at all unless it is already disclosed in Item 19 of your Franchise Disclosure Document and you have a reasonable basis and written substantiation for it at the time you make it. You also have to make that substantiation available to a prospective franchisee, and to the FTC, on reasonable request.

A number on a web page is easy to change and leaves no trace by itself. So if the figures on your franchise sales page were updated last spring, you want to be able to say exactly what the page said before, what it says now, who changed it and when. That is a page-content audit trail, and it is what an activity log is for.

## What the free plugin records

Every time a page or post is updated, Simple History logs an event with:

-   **Who** made the change — the WordPress user account, with name and email
-   **When** it happened, to the second
-   **The IP address** the change came from — anonymized by default, so an IPv4 address is stored as `142.250.74.x` and an IPv6 address loses its whole second half
-   **A diff of the content** — the event details show the text that was removed and the text that was added, highlighted, so a changed figure stands out
-   **Other fields that changed**, such as the title, the URL slug, the publish date, the status and the author

You do not have to turn any of this on. If the page is edited in the block editor or the classic editor, the numbers on it are already being tracked.

## What Premium adds

Three things matter for a compliance record, and [Simple History Premium](https://simple-history.com/add-ons/premium/) covers the ones the free plugin does not:

-   **Longer retention.** The free plugin keeps 30 days of history on new installs and 60 days on installs that have been around a while, then deletes older events. Premium lets you set your own retention period — a number of days you choose, or keep everything forever.
-   **Export.** The free plugin already exports the whole log as CSV, JSON or HTML, so you can hand a lawyer or an auditor the full record. Premium adds exporting a filtered view, so you can hand over just the events they asked for.
-   **Log forwarding.** The free plugin can already write events to a local log file on the server. Premium adds external destinations — a syslog server, Datadog, Splunk, a webhook or an external MySQL/MariaDB database — plus structured formats (JSON Lines, logfmt, RFC 5424) for the local log file, so the record also lives somewhere other than the WordPress site it describes. Log forwarding is currently a beta feature.

## Limitations — read this part

Being honest about what the plugin does not do is more useful to you than a longer feature list.

-   **Content stored in custom fields is not diffed.** Elementor keeps the page layout in a custom field rather than in the WordPress content field, and so do ACF fields and most block-based builders. For those, Simple History still logs that the page was updated, by whom and when, and that a number of custom fields changed — but not the before-and-after text: you get the _when_ and the _who_, not the _what_. Shortcode-based builders behave differently: Divi and WPBakery store their layout in the WordPress content field, so their changes _are_ diffed, though the diff is shortcode markup rather than clean prose. Check which editor the page uses before you rely on this.
-   **Retention has to be set before you need it.** The log is not retroactive. If retention is at the 30- or 60-day default when an auditor asks about a change from two years ago, that event is already gone. Decide on a retention period and set it now, not when the question arrives.
-   **It records changed fields, not page snapshots.** An event captures the fields that changed — often including the complete before and after text of the content field — but not the page as rendered, with its template, menus and widgets. Reconstructing exactly how a page looked on a given date usually means combining the log with WordPress revisions or a backup.
-   **This is not legal advice.** Simple History is a record-keeping tool. Whether your particular disclosures satisfy the Franchise Rule is a question for your franchise counsel, and every system is different. Talk to a lawyer about the requirement; use the plugin for the record.

## Questions we get

**Does installing Simple History make our site FTC-compliant?**
No. Nothing you install makes you compliant. The rule is about the claims you make and your basis for them. What the plugin gives you is evidence of what your site said and when, which is the part that is otherwise very hard to produce after the fact.

**Our sales page is built with Elementor. Will the figures be tracked?**
Partly. You will see that the page was edited, by whom, at what time, and that custom fields changed — but not a diff of the wording, because Elementor stores the layout in a custom field. (Divi and WPBakery store theirs in the WordPress content field, so those _do_ produce a diff, of shortcode markup.) If a content-level record matters for that page, consider keeping the regulated figures in the WordPress editor rather than in builder widgets.

**How long should we keep the log?**
The Franchise Rule does not hand you a single number you can type into a plugin setting. Its own retention requirements are narrow — three years for a sample of each materially different disclosure document and for each signed receipt (16 CFR 436.6(h) and (i)) — and they say nothing about your website. Record-keeping expectations also vary by state and by what your FDD says. Ask your franchise counsel what period applies to you, then set retention to match. Premium can keep events indefinitely if the answer is "don't throw anything away".

**Can we give the log to an auditor or a lawyer?**
Yes. Filter the log down to the page and the date range in question and export the result as CSV, JSON or HTML with Premium. Log forwarding is worth setting up too, so a copy of the record lives outside the site — note that it is still a beta feature.

---

Still not sure whether this covers your setup? [Get in touch](https://simple-history.com/support/) and tell us which editor the page uses — that is usually the deciding detail.
