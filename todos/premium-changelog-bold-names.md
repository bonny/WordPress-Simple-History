# Bold names for Premium's changelog (1.10–1.16)

For: issue 15, the sidebar card shown to sites whose Premium license expired. simple-history.com sends those sites the Added entries newer than their version, and uses a **leading bold name** as the bullet text. Without bold names only about one entry per version gets a name today.

Where to paste: the release post's changelog field on simple-history.com (Plugin Releases Info → Simple History Premium → changelog, HTML). Wrap the name in `<strong>` at the very start of the `<li>`. Mirror the same change in `simple-history-premium/readme.txt` (Markdown: `-   **Name** rest of entry`), so the next release copies it along.

Rules: a few words, says what the customer gets, no code, under 40 characters. Small additions and developer-only entries get no name and simply don't appear on the card.

Update 2026-10-07: the parser now also reads 1.13.0, 1.12.0 and 1.9.0 (their "Added" label is wrapped in a `<div>`), so it already finds 14 names: Table view, Splunk channel, Changes to Premium's own settings, Standalone activation, User card popover, Initiator card, Weekly email report, Additional reaction emoji types, Alerts feature, Log forwarding, two Remote Syslog options, Notes statistics in History Insights, Date picker for backdated events. The bold names below still improve 1.10–1.15. The release post's edit screen now has a "Lapsed-license card preview" box that shows what is read, so check it there after saving.

## 1.16.0

```html
<li>
	<strong>Table view</strong> for the event log. A sortable, filterable table
	…
</li>
```

(Already a link with this text, which also works. "Notes on events" is experimental: no name.)

## 1.15.0

```html
<li>
	<strong>Splunk forwarding</strong>: forward events to the Splunk HTTP Event
	Collector (HEC).
</li>
```

Replaces the start "Splunk channel —".

## 1.14.0

```html
<li>
	<strong>Premium setting changes logged</strong>: changes to Premium's own
	settings (retention, IP storage, stealth mode, failed login attempts,
	message control, and alerts) are now logged out of the box, shown as
	"Modified settings" via Simple History.
</li>
<li>
	<strong>User details in Datadog and Splunk</strong>: forwarded payloads now
	include the user behind the event — …
</li>
```

The Datadog reserved-attributes entry and the new payload filters: no name (part of the entry above, and developer-only).

## 1.11.0

```html
<li>
	<strong>"This page" filter</strong> in the admin bar Quick View, replacing
	the core teaser with actual post-specific event filtering.
</li>
<li>
	<strong>Alerts for one post</strong>: a "Post ID" condition for alert rules,
	allowing alerts when a specific post or page is edited.
</li>
<li>
	<strong>Alerts on message text</strong>: a "Message" condition for alert
	rules, with contains, does not contain, begins with, and ends with.
</li>
<li>
	<strong>Who did what in the weekly email</strong>: detailed breakdowns in
	each stat section — who logged in, failed login usernames and IPs, post
	titles with editors, plugin changes with version info, WordPress version
	updates, and note activity.
</li>
```

The Premium badge, the styling and the "and more" truncation: no name. Remove the "Weekly email report:" prefix from those three, or the parser will name them "Weekly email report".

## 1.10.0

```html
<li>
	<strong>Last login on user cards</strong>: user cards show last login time,
	last activity, total events count, and a "View all user activity" link.
</li>
```

## Result

A site on Premium 1.9 sees 9 names instead of 4: Table view, Splunk forwarding, Premium setting changes logged, User details in Datadog and Splunk, "This page" filter, Alerts for one post, Alerts on message text, Who did what in the weekly email, Last login on user cards.

Check after pasting (cached until the release post is saved, so it updates right away):

```bash
curl -s https://simple-history.com/wp-json/simple-history/v1/plugins/simple-history-premium | jq -r .sections.changelog | grep -o '<li><strong>[^<]*</strong>'
```
