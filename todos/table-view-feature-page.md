# Feature page draft: Table view

Draft for https://simple-history.com/features/table-view/. Nothing has been posted. Pär publishes it himself.

## Checklist for Pär

-   [ ] Create the page with slug `table-view` and parent **Features** (page id 2877), same as `alerts` and `log-forwarding`. No page with that slug exists yet (checked 2026-09-22).
-   [ ] **Publish the page before the core release that ships the table preview.** The free plugin's "Get the table view with Premium" button links to `https://simple-history.com/features/table-view/` (with tracking parameters, see `tableViewUpgradeUrl` in `dropins/class-react-dropin.php`). Until the page exists, that button leads to a 404.
-   [ ] Check the version numbers before publishing. From the code: Premium's saved-views filter says `@since 1.16.0`, and Premium's `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` is `5.34.0-dev`, so this looks like Premium 1.16.0 + core 5.34.0. The draft does not mention versions, so add them if you want to.
-   [ ] Notes on events are left out on purpose. They are behind the experimental features flag right now (`annotationsEnabled`).
-   [ ] Take the screenshots below. Use the demo event mix from the `wp-org-screenshots` skill so the chart has a visible red spike, and run them through pngquant + oxipng.
    1. **Hero**: the whole table view. Chart with a red "Warning or higher" spike, the breakdown line ("… events · N warnings, N severe · N% logged-in users"), the filter box with a query in it (for example `level:warning,error days:7`), and 8 to 10 rows with ID, Date, Relative date, User, Message and Level showing.
    2. **Query bar suggestions**: the filter box with `lev` typed and the suggestion dropdown open, or `level:` typed with the level values listed.
    3. **Chart close-up**: a 7-day range showing hourly bars, with the pointer mid-drag selecting a range, or the tooltip on one bar.
    4. **Saved views menu**: open "Views" menu showing the "Built in" group (Errors and warnings, Logins and failed logins, Content changes, Plugin and core updates), one or two views under "Your views", and the items "Copy link to this view", "Alert me about this view…" and "Copy as WP-CLI command".
    5. **Selection**: two rows ticked, with the Copy menu open (Copy, Copy as CSV, Copy as JSON, Copy links) and "Compare" visible. Optionally a second shot of the Compare modal with differing rows marked.
    6. **Export modal**: "Export events" with Format CSV / JSON / HTML.
    7. **Columns menu** (optional): the column list with reorder buttons and the Comfortable/Compact density choice.
-   [ ] Humanizer was not installed on this machine when this was written (`npx skills add blader/humanizer --global`), so the draft was checked against the voice skill and code.md by hand only. Worth a read-through in your own voice.

---

## Readable draft

**Page title:** Table view for the WordPress activity log

> Simple History Premium can show your activity log as a table: one event per row, the columns you choose, a chart of when things happened, and a filter box you can type into.

The regular event log is good for reading what happened. The table view is for when you are looking for something specific. You can sort, filter by clicking a value, type a query, save the result as a view, and export it. It is part of Simple History Premium.

Switch to it from the view picker above the log, where it sits next to Detailed view and Compact view.

[SCREENSHOT 1: hero, see checklist]

### Who it is for

-   **Site owners** who want a quick answer to "what changed this week".
-   **Agencies** who send clients a monthly report of what was done on their site.
-   **Anyone looking into an incident**, when you need every failed login from last night, what happened just before and just after, and a copy you can send on.

### Type what you are looking for

The filter box takes a short query. Bare words are a text search, and `key:value` terms filter:

```
level:warning,error days:7 plugin
```

That is "warnings and errors from the last seven days that mention plugin". Put a minus in front to exclude: `-level:debug` or `-cron`. Put quotes around text with spaces in it: `"disk full"`. Suggestions show up while you type, including users, event types and initiators, which you pick from a list since nobody can type a user ID from memory.

[SCREENSHOT 2: query bar suggestions]

### Query cheat sheet

| Term            | What it does                            | Example                         |
| --------------- | --------------------------------------- | ------------------------------- |
| `word`          | Free text search                        | `password`                      |
| `search:`       | Same as a bare word                     | `search:"reset link"`           |
| `-word`         | Exclude events containing the text      | `-cron`                         |
| `level:`        | Log level, several separated by commas  | `level:warning,error`           |
| `-level:`       | Exclude log levels                      | `-level:debug,info`             |
| `-logger:`      | Exclude a logger (exclude only)         | `-logger:SimpleHistoryLogger`   |
| `days:`         | Last N days                             | `days:30`                       |
| `day:`          | One day: a date, `today` or `yesterday` | `day:yesterday`                 |
| `from:` / `to:` | Date range, YYYY-MM-DD                  | `from:2026-08-01 to:2026-08-31` |
| `ip:`           | IP address                              | `ip:203.0.113.7`                |
| `meta:`         | Search event metadata                   | `meta:woocommerce`              |

Level names are debug, info, notice, warning, error, critical, alert and emergency.

### See when things happened

Above the table is a bar chart of the matching events. Up to seven days it shows one bar per hour, so you can see the working day, the quiet nights, and the hour something went wrong. Anything at warning or above is drawn in red. Click a bar to narrow the table to that hour or day, or drag across several bars to pick a range.

Next to the event count there is a short breakdown: how many warnings, how many severe events, and how much of it came from logged-in users. It is counted over everything that matches, not only the rows on screen.

[SCREENSHOT 3: chart]

### Filter by clicking a value

Click a user, level, event type, logger or IP address in a row to filter by it or exclude it. Loggers can only be excluded and IP addresses only filtered by. "Exclude this user" is often the fastest way to hide the one account that updates plugins every night.

### Pick your columns

ID, Date, Relative date, User, Message, Level, Logger, Event type and IP address, plus Reactions if you use them. Show the ones you want, in the order you want, and choose Comfortable or Compact rows. Sort by ID, Date or Level. Your choice is saved to your user account.

### Saved views

Four views are built in: Errors and warnings, Logins and failed logins, Content changes, and Plugin and core updates. Save your own filters, columns and sort as a named view (up to 50 per user). Each view can also:

-   **Copy link to this view**, since a view is a URL
-   **Alert me about this view…** turns its filters into an [alert](https://simple-history.com/features/alerts/) rule, and tells you first which filters an alert cannot check
-   **Copy as WP-CLI command** gives you the same filters as a `wp simple-history event list` command

**Example: find every failed login this week.** Pick the built-in "Logins and failed logins" view and add `days:7`.

[SCREENSHOT 4: saved views menu]

### Select, compare, copy

Tick rows (or press `x`) to act on them:

-   **Copy**, **Copy as CSV**, **Copy as JSON** or **Copy links**
-   **Compare** two events side by side, with the values that differ marked. This is useful for the same setting saved twice.
-   **Show surrounding events** for one event, to see what happened right before and after it

[SCREENSHOT 5: selection]

### Export

**Export all** downloads every event matching the current filters, and **Export selected** downloads only the ticked rows. Choose CSV, JSON or HTML. Export is for administrators.

**Example: last month for a client.** Type `from:2026-08-01 to:2026-08-31 -level:debug`, click Export all, and pick CSV or HTML.

[SCREENSHOT 6: export modal]

### Live

Turn on Live and new events appear at the top of the table as they are logged, highlighted for a few seconds. The table checks every ten seconds, and Live is only available when the table is sorted newest first.

### Keyboard

`j` and `k` move between rows, `x` selects, `o` or Enter opens the details, `/` jumps to the filter box, `g` then `v` opens saved views, and `?` lists them all.

### Common questions

**Does it work on a large log?**
Yes. The table loads rows as you scroll, and the chart and breakdown are counted by the database instead of in your browser. Export fetches in batches on the server, so it isn't limited to what you have scrolled through.

**Does it work with SQLite?**
Yes. Simple History supports both MySQL/MariaDB and SQLite, and the chart's counts have their own SQLite query.

**Is my data sent anywhere?**
No. Everything runs on your own site through the WordPress REST API. Views and column choices are stored in your WordPress database.

**Does it replace the normal log view?**
No. Detailed view and Compact view are still there, and you can switch between them any time.

**What does it cost?**
The table view is included in Simple History Premium, which starts at $79 a year for a single site. There is a 30-day money-back guarantee.

### Get the table view

[Upgrade to Premium](https://simple-history.com/add-ons/premium/)

---

## Gutenberg block markup

Paste into the code editor (Options → Code editor). Heading anchors, the `is-style-info` intro and `is-style-screenshot-image` images follow `/features/alerts/`. The final `wp:block` ref 3108 is the same pricing/buy box used at the end of `/features/alerts/`. Each screenshot is an image block with an empty `src`: replace it via the block toolbar, or delete the block and insert the uploaded image with the same class.

```html
<!-- wp:paragraph {"className":"is-style-info"} -->
<p class="is-style-info">
	Simple History Premium can show your activity log as a table: one event per
	row, the columns you choose, a chart of when things happened, and a filter
	box you can type into.
</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>
	The regular event log is good for reading what happened. The table view is
	for when you are looking for something specific. You can sort, filter by
	clicking a value, type a query, save the result as a view, and export it. It
	is part of Simple History Premium.
</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>
	Switch to it from the view picker above the log, where it sits next to
	<em>Detailed view</em> and <em>Compact view</em>.
</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img
		src=""
		alt="The Simple History table view with an activity chart, a filter query and a table of events"
	/>
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="who">Who it is for</h2>
<!-- /wp:heading -->

<!-- wp:list {"className":"wp-block-list"} -->
<ul class="wp-block-list">
	<!-- wp:list-item -->
	<li>
		<strong>Site owners</strong> who want a quick answer to “what changed
		this week”.
	</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Agencies</strong> who send clients a monthly report of what was
		done on their site.
	</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Anyone looking into an incident</strong>, when you need every
		failed login from last night, what happened just before and just after,
		and a copy you can send on.
	</li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="query">Type what you are looking for</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	The filter box takes a short query. Bare words are a text search, and
	<code>key:value</code> terms filter:
</p>
<!-- /wp:paragraph -->

<!-- wp:code -->
<pre class="wp-block-code"><code>level:warning,error days:7 plugin</code></pre>
<!-- /wp:code -->

<!-- wp:paragraph -->
<p>
	That is “warnings and errors from the last seven days that mention plugin”.
	Put a minus in front to exclude: <code>-level:debug</code> or
	<code>-cron</code>. Put quotes around text with spaces in it:
	<code>"disk full"</code>. Suggestions show up while you type, including
	users, event types and initiators, which you pick from a list since nobody
	can type a user ID from memory.
</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img
		src=""
		alt="Suggestions in the table view filter box while typing a query"
	/>
</figure>
<!-- /wp:image -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading" id="cheat-sheet">Query cheat sheet</h3>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table">
	<table>
		<thead>
			<tr>
				<th>Term</th>
				<th>What it does</th>
				<th>Example</th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><code>word</code></td>
				<td>Free text search</td>
				<td><code>password</code></td>
			</tr>
			<tr>
				<td><code>search:</code></td>
				<td>Same as a bare word</td>
				<td><code>search:"reset link"</code></td>
			</tr>
			<tr>
				<td><code>-word</code></td>
				<td>Exclude events containing the text</td>
				<td><code>-cron</code></td>
			</tr>
			<tr>
				<td><code>level:</code></td>
				<td>Log level, several separated by commas</td>
				<td><code>level:warning,error</code></td>
			</tr>
			<tr>
				<td><code>-level:</code></td>
				<td>Exclude log levels</td>
				<td><code>-level:debug,info</code></td>
			</tr>
			<tr>
				<td><code>-logger:</code></td>
				<td>Exclude a logger (exclude only)</td>
				<td><code>-logger:SimpleHistoryLogger</code></td>
			</tr>
			<tr>
				<td><code>days:</code></td>
				<td>Last N days</td>
				<td><code>days:30</code></td>
			</tr>
			<tr>
				<td><code>day:</code></td>
				<td>
					One day: a date, <code>today</code> or
					<code>yesterday</code>
				</td>
				<td><code>day:yesterday</code></td>
			</tr>
			<tr>
				<td><code>from:</code> / <code>to:</code></td>
				<td>Date range, YYYY-MM-DD</td>
				<td><code>from:2026-08-01 to:2026-08-31</code></td>
			</tr>
			<tr>
				<td><code>ip:</code></td>
				<td>IP address</td>
				<td><code>ip:203.0.113.7</code></td>
			</tr>
			<tr>
				<td><code>meta:</code></td>
				<td>Search event metadata</td>
				<td><code>meta:woocommerce</code></td>
			</tr>
		</tbody>
	</table>
</figure>
<!-- /wp:table -->

<!-- wp:paragraph -->
<p>
	Level names are debug, info, notice, warning, error, critical, alert and
	emergency.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="chart">See when things happened</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Above the table is a bar chart of the matching events. Up to seven days it
	shows one bar per hour, so you can see the working day, the quiet nights,
	and the hour something went wrong. Anything at warning or above is drawn in
	red. Click a bar to narrow the table to that hour or day, or drag across
	several bars to pick a range.
</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>
	Next to the event count there is a short breakdown: how many warnings, how
	many severe events, and how much of it came from logged-in users. It is
	counted over everything that matches, not only the rows on screen.
</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img
		src=""
		alt="Hourly activity chart above the event table, with warnings shown in red"
	/>
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="click-to-filter">
	Filter by clicking a value
</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Click a user, level, event type, logger or IP address in a row to filter by
	it or exclude it. <em>Exclude this user</em> is often the fastest way to
	hide the one account that updates plugins every night.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="columns">Pick your columns</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	ID, Date, Relative date, User, Message, Level, Logger, Event type and IP
	address, plus Reactions if you use them. Show the ones you want, in the
	order you want, and choose Comfortable or Compact rows. Sort by ID, Date or
	Level. Your choice is saved to your user account.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="saved-views">Saved views</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Four views are built in: Errors and warnings, Logins and failed logins,
	Content changes, and Plugin and core updates. Save your own filters, columns
	and sort as a named view (up to 50 per user). Each view can also:
</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"wp-block-list"} -->
<ul class="wp-block-list">
	<!-- wp:list-item -->
	<li><strong>Copy link to this view</strong>, since a view is a URL</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Alert me about this view…</strong> turns its filters into an
		<a href="https://simple-history.com/features/alerts/">alert</a> rule,
		and tells you first which filters an alert cannot check
	</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Copy as WP-CLI command</strong> gives you the same filters as a
		<code>wp simple-history event list</code> command
	</li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>
	<strong>Example: find every failed login this week.</strong> Pick the
	built-in <em>Logins and failed logins</em> view and add <code>days:7</code>.
</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img
		src=""
		alt="The saved views menu with built-in views and options to copy a link, create an alert or copy a WP-CLI command"
	/>
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="select">Select, compare, copy</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Tick rows (or press <code>x</code>) to act on them:</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"wp-block-list"} -->
<ul class="wp-block-list">
	<!-- wp:list-item -->
	<li>
		<strong>Copy</strong>, <strong>Copy as CSV</strong>,
		<strong>Copy as JSON</strong> or <strong>Copy links</strong>
	</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Compare</strong> two events side by side, with the values that
		differ marked. This is useful for the same setting saved twice.
	</li>
	<!-- /wp:list-item -->

	<!-- wp:list-item -->
	<li>
		<strong>Show surrounding events</strong> for one event, to see what
		happened right before and after it
	</li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img
		src=""
		alt="Two selected events in the table view with the copy menu open"
	/>
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="export">Export</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	<strong>Export all</strong> downloads every event matching the current
	filters, and <strong>Export selected</strong> downloads only the ticked
	rows. Choose CSV, JSON or HTML. Export is for administrators.
</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>
	<strong>Example: last month for a client.</strong> Type
	<code>from:2026-08-01 to:2026-08-31 -level:debug</code>, click
	<em>Export all</em>, and pick CSV or HTML.
</p>
<!-- /wp:paragraph -->

<!-- wp:image {"sizeSlug":"large","linkDestination":"none","className":"is-style-screenshot-image"} -->
<figure class="wp-block-image size-large is-style-screenshot-image">
	<img src="" alt="The export dialog with CSV, JSON and HTML formats" />
</figure>
<!-- /wp:image -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="live">Live</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Turn on <em>Live</em> and new events appear at the top of the table as they
	are logged, highlighted for a few seconds. The table checks every ten
	seconds, and Live is only available when the table is sorted newest first.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="keyboard">Keyboard</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	<code>j</code> and <code>k</code> move between rows, <code>x</code> selects,
	<code>o</code> or Enter opens the details, <code>/</code> jumps to the
	filter box, <code>g</code> then <code>v</code> opens saved views, and
	<code>?</code> lists them all.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="faq">Common questions</h2>
<!-- /wp:heading -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Does it work on a large log?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Yes. The table loads rows as you scroll, and the chart and breakdown are
	counted by the database instead of in your browser. Export fetches in
	batches on the server, so it isn't limited to what you have scrolled
	through.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Does it work with SQLite?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	Yes. Simple History supports both MySQL/MariaDB and SQLite, and the chart's
	counts have their own SQLite query.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Is my data sent anywhere?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	No. Everything runs on your own site through the WordPress REST API. Views
	and column choices are stored in your WordPress database.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Does it replace the normal log view?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	No. Detailed view and Compact view are still there, and you can switch
	between them any time.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">What does it cost?</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>
	The table view is included in Simple History Premium, which starts at $79 a
	year for a single site. There is a 30-day money-back guarantee.
</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading" id="get">Get the table view</h2>
<!-- /wp:heading -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
	<!-- wp:button -->
	<div class="wp-block-button">
		<a
			class="wp-block-button__link wp-element-button"
			href="https://simple-history.com/add-ons/premium/"
			>Upgrade to Premium</a
		>
	</div>
	<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:block {"ref":3108} /-->
```
