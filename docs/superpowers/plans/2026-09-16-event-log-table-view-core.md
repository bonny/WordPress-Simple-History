# Event log Table view — core implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Put a Table view button in the event log's view switcher that shows every free user a staged preview of the premium table, and add the server-side sorting the premium table will need.

**Architecture:** Three independent pieces of core work. The view switcher graduates out of experimental features so anyone can see it. `Log_Query` gains `orderby` / `order` on its ungrouped path, exposed through REST and WP-CLI so free users can sort today. A new `TablePreview` component renders inert markup with staged sample rows behind a `SimpleHistorySlotTableView` Slot, which Premium will later fill with the real table.

**Tech Stack:** PHP 7.4+, WordPress 6.3+, `@wordpress/components` Slot/Fill, `@wordpress/icons`, nuqs for URL state, Codeception wpunit for PHP tests, Playwright for browser tests.

**Spec:** `docs/superpowers/specs/2026-09-16-event-log-table-view-design.md`

## Global Constraints

-   **PHP 7.4 compatibility.** Local CLI PHP is 8.5, so `php -l` proves nothing. No `match`, no arrow-function-only constructs that 7.4 lacks, no union types, no constructor property promotion. `match` is PHP 8.0 — use `switch` or an array map.
-   **WordPress 6.3+.** Every `@wordpress/*` import must exist in WP 6.3. Do not upgrade `@wordpress/scripts` beyond 27.x.
-   **MySQL/MariaDB and SQLite both supported.** Use `Log_Query::get_db_engine()` to branch. No MySQL-only SQL without a guard.
-   **Build with `npm run build`**, never `npx wp-scripts build` — the latter only compiles `index.js` and leaves the other entry points stale.
-   **PHPStan before committing PHP:** `./vendor/bin/phpstan analyse --memory-limit=2G` with **no path argument**. A per-file run skips the unmatched-`ignoreErrors` check and can pass while the full run fails.
-   **Text domain** is `simple-history`. Prefixes are `sh`, `simplehistory` or `simple_history`.
-   **Prose rule:** no AI filler in changelog entries, comments or commit messages. Banned: "seamlessly", "robust", "leverage", "streamline", "unlock", "elevate", "empower", "delve", "furthermore"/"moreover" as transitions. Plain sentences only.
-   **Comments go on their own line above the code**, never trailing.
-   **Whitespace:** blank line before `if` and `return` statements and between logical blocks.
-   **No ad-hoc hover effects** in new admin UI. No one-off lifts, shadows or transitions.
-   **Campaign slug** for every link in the preview: `premium_table_view`, with a distinct `utm_content` per link.
-   **Do not commit to git unless the task says to.** Never push.

---

### Task 1: Graduate the view switcher out of experimental features

Until this lands, nothing else in this plan is visible to anyone who has not turned on experimental features.

**Files:**

-   Modify: `src/components/EventsControlBar.jsx:165-171`
-   Modify: `src/components/EventsGui.jsx:352-357`
-   Modify: `dropins/class-experimental-features-dropin.php:83`
-   Modify: `readme.txt` (changelog)
-   Test: `tests/playwright/events-view-toggle.spec.js`

**Interfaces:**

-   Consumes: nothing.
-   Produces: the `EventsViewToggle` renders unconditionally; `eventsView` in `EventsGui` is `urlEventsView ?? storedEventsView` with no experimental branch.

-   [ ] **Step 1: Read the existing spec to see what it asserts today**

Run: `sed -n '1,120p' tests/playwright/events-view-toggle.spec.js`

Look for the tests that turn experimental features off and assert the toggle is absent. Those are the assertions that have to invert.

-   [ ] **Step 2: Write the failing test**

Replace the test that asserts the toggle disappears with one that asserts it survives. Keep the existing `setExperimentalFeatures` helper — it is still used to leave the site in a known state.

```js
test( 'view toggle shows when experimental features are off', async ( {
	page,
	requestUtils,
} ) => {
	await setExperimentalFeatures( requestUtils, false );
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.locator( '.sh-EventsViewToggle' ).waitFor();

	await expect(
		page.getByRole( 'button', { name: 'Compact view' } )
	).toBeVisible();
} );

test( 'compact view works when experimental features are off', async ( {
	page,
	requestUtils,
} ) => {
	await setExperimentalFeatures( requestUtils, false );
	await setStoredView( requestUtils, 'compact' );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.locator( '.sh-EventsViewToggle' ).waitFor();

	await expect(
		page.getByRole( 'button', { name: 'Compact view' } )
	).toHaveAttribute( 'aria-pressed', 'true' );
} );
```

-   [ ] **Step 3: Run the test to verify it fails**

Run: `npx playwright test --project=events-view`
Expected: FAIL. The toggle is not rendered with the flag off, so `.sh-EventsViewToggle` never appears and `waitFor()` times out.

-   [ ] **Step 4: Remove the gate in the control bar**

Locate it with `grep -n "Compact view is experimental" src/components/EventsControlBar.jsx` and match on the content — the indentation below is approximate, the file's own is deeper.

In `src/components/EventsControlBar.jsx`, replace:

```text
					{ /* Compact view is experimental for now. */ }
					{ experimentalFeaturesEnabled && (
						<EventsViewToggle
							view={ eventsView }
							onChange={ onEventsViewChange }
						/>
					) }
```

with (keep the same indentation as the surrounding JSX):

```text
					<EventsViewToggle
						view={ eventsView }
						onChange={ onEventsViewChange }
					/>
```

Then remove the now-unused `experimentalFeaturesEnabled` binding from this component — but only if nothing else in the file uses it. Check first:

Run: `grep -n "experimentalFeaturesEnabled" src/components/EventsControlBar.jsx`

-   [ ] **Step 5: Remove the gate in EventsGui**

In `src/components/EventsGui.jsx`, replace:

```jsx
// Compact view is experimental for now. With the flag off the log is always
// detailed, so a stored preference or a ?view=compact link from the time the
// flag was on does not keep a feature alive that the site has turned off.
const eventsView = isExperimentalFeaturesEnabled
	? urlEventsView ?? storedEventsView
	: 'detailed';
```

with:

```jsx
// The URL wins over the stored preference, so a shared link opens in the
// view it was copied from.
const eventsView = urlEventsView ?? storedEventsView;
```

Then check whether `isExperimentalFeaturesEnabled` is still used elsewhere in the file before removing its declaration:

Run: `grep -n "isExperimentalFeaturesEnabled" src/components/EventsGui.jsx`

-   [ ] **Step 6: Remove the Compact view line from the experimental features list**

In `dropins/class-experimental-features-dropin.php`, delete this line:

```php
				<li><?php esc_html_e( 'Compact event log view — switch the event log between detailed rows and a denser list', 'simple-history' ); ?></li>
```

-   [ ] **Step 7: Build and run the test to verify it passes**

Run: `npm run build && npx playwright test --project=events-view`
Expected: PASS, both new tests plus the existing toggle tests.

-   [ ] **Step 8: Run the wider browser suite for regressions**

Run: `npx playwright test --project=tests`
Expected: PASS. The `tests` project depends on `events-view`, and several of its specs assume the detailed view — if any now fail, the stored view leaked between projects, so check that `events-view-toggle.spec.js` resets the view to `detailed` in an `afterAll`.

-   [ ] **Step 9: Add the changelog entry**

Use the **changelog** skill. The entry goes under `### Changed` in the Unreleased section of `readme.txt`, framed as a graduation:

```
-   The compact event log view is no longer experimental. The Detailed/Compact switch is now available to everyone from the event log page.
```

-   [ ] **Step 10: Commit**

```bash
git add src/components/EventsControlBar.jsx src/components/EventsGui.jsx \
  dropins/class-experimental-features-dropin.php \
  tests/playwright/events-view-toggle.spec.js readme.txt
git commit -m "Graduate the compact event log view out of experimental features"
```

---

### Task 2: `orderby` and `order` in `Log_Query`

**Files:**

-   Modify: `inc/class-log-query.php` — docblock at ~line 136, `prepare_args()` at 1005, validation block at ~1155, `query_overview_simple()` SQL at 276 and 325
-   Test: `tests/wpunit/LogQueryOrderByTest.php` (create)

**Interfaces:**

-   Consumes: the existing `ungrouped` argument.
-   Produces:

    -   `Log_Query::query( [ 'orderby' => string, 'order' => string, ... ] )`.
    -   `orderby` accepts `date`, `id`, `level`, `logger`, `message`. Anything else falls back to `date`.
    -   `order` accepts `ASC` or `DESC`, case-insensitive. Anything else falls back to `DESC`.
    -   Setting `orderby` to anything but `date` forces `ungrouped => true`.
    -   A new protected method `get_order_by_clause( array $args, string $table_alias = '' ) : string` returning a complete `ORDER BY ...` string with the `id` tiebreaker already appended.

-   [ ] **Step 1: Write the failing test**

Create `tests/wpunit/LogQueryOrderByTest.php`. It runs on both database engines, so do **not** add the `SkipsOnSqlite` trait — the ungrouped path is the one SQLite always uses, which is exactly what is being changed here.

```php
<?php

use Simple_History\Log_Query;

/**
 * Test the orderby and order arguments of Log_Query.
 *
 * Runs on MySQL/MariaDB and on SQLite: the argument only affects
 * query_overview_simple(), which is the path SQLite always takes.
 *
 * @coversDefaultClass Simple_History\Log_Query
 */
class LogQueryOrderByTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * Log three events with known, different levels and loggers.
	 *
	 * @return void
	 */
	private function add_known_events() {
		// SimpleLogger() is the global helper every other test in this suite
		// uses to write events.
		SimpleLogger()->info( 'Alpha event' );
		SimpleLogger()->warning( 'Beta event' );
		SimpleLogger()->debug( 'Gamma event' );
	}

	/**
	 * Query ungrouped with the given extra args and return the rows.
	 *
	 * @param array $args Extra query args.
	 * @return array
	 */
	private function query_rows( $args ) {
		$log_query = new Log_Query();

		$result = $log_query->query(
			array_merge(
				[
					'posts_per_page' => 50,
					'ungrouped'      => true,
				],
				$args
			)
		);

		return $result['log_rows'];
	}

	public function setUp(): void {
		parent::setUp();

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$this->add_known_events();
	}

	/**
	 * The default is unchanged: newest first.
	 */
	public function test_default_order_is_date_desc() {
		$rows = $this->query_rows( [] );

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Default order should be newest first.' );
	}

	/**
	 * Ascending by id returns the oldest event first.
	 */
	public function test_order_by_id_asc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'ASC',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Ascending by id should return oldest first.' );
	}

	/**
	 * Sorting by level orders alphabetically by the level column.
	 */
	public function test_order_by_level_asc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'level',
				'order'   => 'ASC',
			]
		);

		$levels = wp_list_pluck( $rows, 'level' );

		$sorted = $levels;
		sort( $sorted, SORT_STRING );

		$this->assertSame( $sorted, $levels, 'Levels should come back alphabetically.' );
	}

	/**
	 * Lowercase order is accepted.
	 */
	public function test_order_is_case_insensitive() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'asc',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Lowercase "asc" should behave like "ASC".' );
	}

	/**
	 * An unknown column falls back to date rather than erroring or
	 * reaching the SQL.
	 */
	public function test_unknown_orderby_falls_back_to_date() {
		$rows = $this->query_rows( [ 'orderby' => 'id; DROP TABLE wp_posts' ] );

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Unknown orderby should behave like the default.' );
		$this->assertNotEmpty( $rows, 'The query should still return rows.' );
	}

	/**
	 * An unknown order direction falls back to DESC.
	 */
	public function test_unknown_order_falls_back_to_desc() {
		$rows = $this->query_rows(
			[
				'orderby' => 'id',
				'order'   => 'sideways',
			]
		);

		$ids = wp_list_pluck( $rows, 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids, 'Unknown order should behave like DESC.' );
	}

	/**
	 * Sorting by a column other than date forces ungrouped, because
	 * occasion grouping depends on rows arriving in date order.
	 */
	public function test_non_date_orderby_forces_ungrouped() {
		$log_query = new Log_Query();

		$result = $log_query->query(
			[
				'posts_per_page' => 50,
				'orderby'        => 'level',
				'order'          => 'ASC',
			]
		);

		$this->assertNotEmpty( $result['log_rows'] );

		// Every row in an ungrouped result reports a single occasion.
		foreach ( $result['log_rows'] as $row ) {
			$this->assertEquals(
				1,
				(int) $row->subsequentOccasions,
				'An ungrouped result should report one occasion per row.'
			);
		}
	}
}
```

-   [ ] **Step 2: Run the test to verify it fails**

Run: `npm run test:wpunit -- --filter LogQueryOrderByTest`
Expected: FAIL. `test_order_by_id_asc` and the others come back in date order because `orderby` is ignored.

-   [ ] **Step 3: Document the new arguments**

In `inc/class-log-query.php`, in the `query()` docblock near line 136 where `$ungrouped` is documented, add:

```php
	 *      @type string $orderby Column to sort by. One of 'date', 'id', 'level', 'logger', 'message'. Anything else falls back to 'date'. Setting this to anything but 'date' forces $ungrouped to true, because occasion grouping depends on rows arriving in date order. Default 'date'.
	 *      @type string $order Sort direction, 'ASC' or 'DESC', case-insensitive. Anything else falls back to 'DESC'. Default 'DESC'.
```

-   [ ] **Step 4: Add the defaults in `prepare_args()`**

In the `wp_parse_args()` default array in `prepare_args()` (starts at line 1007), add next to the other simple scalars:

```php
				// Column to sort by. See the query() docblock for accepted values.
				'orderby'           => 'date',

				// Sort direction, ASC or DESC.
				'order'             => 'DESC',
```

-   [ ] **Step 5: Normalise and validate the new args**

Still in `prepare_args()`, in the validation block after the `paged` handling (around line 1177), add:

```php
		// Normalise orderby to a known column. An unknown value falls back to
		// the default rather than throwing, the same way an out-of-range
		// posts_per_page is clamped. The value never reaches SQL as-is — it is
		// mapped to a literal column name in get_order_by_clause().
		$allowed_orderby = [ 'date', 'id', 'level', 'logger', 'message' ];

		if ( ! isset( $args['orderby'] ) || ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = 'date';
		}

		// Normalise order to ASC or DESC.
		$order = isset( $args['order'] ) ? strtoupper( (string) $args['order'] ) : 'DESC';

		$args['order'] = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';

		// Occasion grouping counts consecutive rows with the same occasionsID,
		// which only holds while rows arrive in date order. Sorting by anything
		// else means the grouped query cannot run, so drop the grouping rather
		// than silently returning date-ordered rows.
		if ( $args['orderby'] !== 'date' ) {
			$args['ungrouped'] = true;
		}
```

-   [ ] **Step 6: Add the clause builder**

Add this protected method to `Log_Query`, next to `prepare_args()`:

```php
	/**
	 * Build the ORDER BY clause for a query.
	 *
	 * The column name is never taken from the args directly — it is looked up
	 * in a map of literal strings, so no caller-supplied text reaches SQL.
	 * The id column is always appended as a tiebreaker: without it, paging
	 * over a low-cardinality column like level can repeat a row on two pages.
	 *
	 * @param array  $args        Prepared query args.
	 * @param string $table_alias Table alias to prefix columns with, without the trailing dot.
	 * @return string Complete ORDER BY clause.
	 */
	protected function get_order_by_clause( $args, $table_alias = '' ) {
		$columns = [
			'date'    => 'date',
			'id'      => 'id',
			'level'   => 'level',
			'logger'  => 'logger',
			'message' => 'message',
		];

		$orderby = isset( $columns[ $args['orderby'] ] ) ? $columns[ $args['orderby'] ] : 'date';
		$order   = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
		$prefix  = $table_alias === '' ? '' : $table_alias . '.';

		// Sorting by id already is the tiebreaker.
		if ( $orderby === 'id' ) {
			return sprintf( 'ORDER BY %1$sid %2$s', $prefix, $order );
		}

		return sprintf(
			'ORDER BY %1$s%2$s %3$s, %1$sid %3$s',
			$prefix,
			$orderby,
			$order
		);
	}
```

-   [ ] **Step 7: Use the clause in the rows query**

In `query_overview_simple()`, change the `$sql_statement_log_rows` template (line ~263) so the hardcoded clause becomes a placeholder. Replace:

```php
			FROM %1$s AS simple_history_1
			%2$s
			ORDER BY simple_history_1.date DESC, simple_history_1.id DESC
			%3$s
		';
```

with:

```php
			FROM %1$s AS simple_history_1
			%2$s
			%4$s
			%3$s
		';
```

and add the fourth argument to the matching `sprintf()` (line ~288):

```php
		$sql_query_log_rows = sprintf(
			$sql_statement_log_rows,
			$Simple_History->get_events_table_name(), // 1
			$inner_where_string, // 2
			$limit_clause, // 3
			$this->get_order_by_clause( $args, 'simple_history_1' ) // 4
		);
```

-   [ ] **Step 8: Drop the pointless ORDER BY from the count query**

Still in `query_overview_simple()`, the count query (line ~320) carries an `ORDER BY` over a single `COUNT(*)`, which does nothing but make the planner sort. Remove that line from `$sql_statement_log_rows_count`, leaving:

```php
			$sql_statement_log_rows_count = '
				SELECT count(*) as count
				FROM %1$s AS simple_history_1
				%2$s
			';
```

-   [ ] **Step 9: Run the test to verify it passes**

Run: `npm run test:wpunit -- --filter LogQueryOrderByTest`
Expected: PASS, all seven tests.

-   [ ] **Step 10: Run the existing Log_Query tests for regressions**

Run: `npm run test:wpunit-logquery`
Expected: PASS. Default ordering is unchanged, so `LogQueryTest`, `DateOrderingTest` and `DateOrderingDataIntegrityTest` should all be unaffected. If `DateOrderingTest` fails, the tiebreaker direction is wrong — it must match the main column's direction, not be hardcoded `DESC`.

-   [ ] **Step 11: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: no errors. Pass no path argument.

-   [ ] **Step 12: Commit**

```bash
git add inc/class-log-query.php tests/wpunit/LogQueryOrderByTest.php
git commit -m "Add orderby and order arguments to Log_Query"
```

---

### Task 3: `orderby` and `order` in the REST events controller

**Files:**

-   Modify: `inc/class-wp-rest-events-controller.php` — query params at ~591, both `$parameter_mappings` arrays at ~900 and ~1018
-   Test: `tests/wpunit/RestEventsOrderByTest.php` (create)

**Interfaces:**

-   Consumes: `Log_Query`'s `orderby` / `order` args from Task 2.
-   Produces: `GET /simple-history/v1/events?orderby=<date|id|level|logger|message>&order=<asc|desc>`. An invalid value is rejected by the schema with a 400, unlike the `Log_Query` fallback — a typo in a URL should be told about, not quietly ignored.

-   [ ] **Step 1: Write the failing test**

Create `tests/wpunit/RestEventsOrderByTest.php`:

```php
<?php

/**
 * Test the orderby and order query parameters of the events REST endpoint.
 */
class RestEventsOrderByTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	protected $server;

	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		SimpleLogger()->info( 'Alpha event' );
		SimpleLogger()->warning( 'Beta event' );
		SimpleLogger()->debug( 'Gamma event' );
	}

	/**
	 * Make a GET request to the events endpoint.
	 *
	 * @param array $params Query parameters.
	 * @return WP_REST_Response
	 */
	private function get_events( $params ) {
		$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );

		$request->set_param( 'per_page', 50 );
		$request->set_param( 'ungrouped', true );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $this->server->dispatch( $request );
	}

	public function test_order_by_id_asc_returns_oldest_first() {
		$response = $this->get_events(
			[
				'orderby' => 'id',
				'order'   => 'asc',
			]
		);

		$this->assertSame( 200, $response->get_status() );

		$ids = wp_list_pluck( $response->get_data(), 'id' );

		$sorted = $ids;
		sort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids );
	}

	public function test_default_is_newest_first() {
		$response = $this->get_events( [] );

		$this->assertSame( 200, $response->get_status() );

		$ids = wp_list_pluck( $response->get_data(), 'id' );

		$sorted = $ids;
		rsort( $sorted, SORT_NUMERIC );

		$this->assertSame( $sorted, $ids );
	}

	public function test_invalid_orderby_is_rejected() {
		$response = $this->get_events( [ 'orderby' => 'nonsense' ] );

		$this->assertSame( 400, $response->get_status() );
	}

	public function test_invalid_order_is_rejected() {
		$response = $this->get_events( [ 'order' => 'sideways' ] );

		$this->assertSame( 400, $response->get_status() );
	}
}
```

-   [ ] **Step 2: Run the test to verify it fails**

Run: `npm run test:wpunit -- --filter RestEventsOrderByTest`
Expected: FAIL. `test_order_by_id_asc_returns_oldest_first` gets date order; the two rejection tests get 200 because the params are not registered and WordPress ignores unknown ones.

-   [ ] **Step 3: Register the query parameters**

In `get_collection_params()`, next to the `ungrouped` parameter (line ~591), add:

```php
		$query_params['orderby'] = array(
			'description' => __( 'Column to sort events by. Sorting by anything other than date returns ungrouped events, because occasion grouping depends on date order.', 'simple-history' ),
			'type'        => 'string',
			'default'     => 'date',
			'enum'        => array( 'date', 'id', 'level', 'logger', 'message' ),
		);

		$query_params['order'] = array(
			'description' => __( 'Sort direction.', 'simple-history' ),
			'type'        => 'string',
			'default'     => 'desc',
			'enum'        => array( 'asc', 'desc' ),
		);
```

-   [ ] **Step 4: Map the parameters into query args**

There are **two** `$parameter_mappings` arrays in this file, one at ~line 900 and one at ~line 989. Add the same two entries to **both**, next to `'ungrouped' => 'ungrouped',`:

```php
			'orderby'                 => 'orderby',
			'order'                   => 'order',
```

Run this afterwards to confirm both were changed:

Run: `grep -c "'orderby'                 => 'orderby'," inc/class-wp-rest-events-controller.php`
Expected: `2`

-   [ ] **Step 5: Run the test to verify it passes**

Run: `npm run test:wpunit -- --filter RestEventsOrderByTest`
Expected: PASS, all four tests.

-   [ ] **Step 6: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: no errors.

-   [ ] **Step 7: Commit**

```bash
git add inc/class-wp-rest-events-controller.php tests/wpunit/RestEventsOrderByTest.php
git commit -m "Add orderby and order parameters to the events REST endpoint"
```

---

### Task 4: `--orderby` and `--order` on `wp simple-history event list`

This is what makes sorting a real core capability rather than an API only a paid plugin reaches. The command already passes `ungrouped => true`, so there is no grouping interaction to worry about.

**Files:**

-   Modify: `inc/services/wp-cli-commands/class-wp-cli-list-command.php` — the `list()` docblock options, the `wp_parse_args()` defaults at ~283, the `$query_args` assembly at ~351
-   Modify: `readme.txt` (changelog)

**Interfaces:**

-   Consumes: `Log_Query`'s `orderby` / `order` from Task 2.
-   Produces: `wp simple-history event list --orderby=level --order=asc`.

-   [ ] **Step 1: Add the options to the command docblock**

In the `list()` docblock, after the `[--only_sticky]` block and before `## Surrounding Events`, add:

```php
	 * [--orderby=<column>]
	 * : Column to sort events by.
	 * ---
	 * default: date
	 * options:
	 *   - date
	 *   - id
	 *   - level
	 *   - logger
	 *   - message
	 *
	 * [--order=<direction>]
	 * : Sort direction.
	 * ---
	 * default: desc
	 * options:
	 *   - asc
	 *   - desc
	 * ---
	 *
```

-   [ ] **Step 2: Add the examples**

In the `## Examples` block of the same docblock, after the "Show only sticky events" example, add:

```php
	 *
	 *     # Show the oldest events first
	 *     wp simple-history event list --orderby=id --order=asc
	 *
	 *     # Group the output by log level
	 *     wp simple-history event list --orderby=level --order=asc --count=50
```

-   [ ] **Step 3: Add the defaults**

In `list()`, in the `wp_parse_args()` default array, next to `'only_sticky' => false,`, add:

```php
				'orderby'              => 'date',
				'order'                => 'desc',
```

-   [ ] **Step 4: Pass them to the query**

In the `$query_args` array (around line 351), add the two keys:

```php
		$query_args = array(
			'posts_per_page' => $assoc_args['count'],
			'ungrouped'      => true,
			'orderby'        => $assoc_args['orderby'],
			'order'          => $assoc_args['order'],
		);
```

No validation is needed here. WP-CLI enforces the `options:` list from the docblock when the command is run from a shell, and `Log_Query::prepare_args()` falls back to the defaults for anything that arrives another way — for example the deprecated `event search` alias, which calls this method directly.

-   [ ] **Step 5: Verify by hand against the dev site**

Run:

```bash
cd /Users/bonnymacmini/Projects/_docker-compose-to-run-on-system-boot
docker compose run --rm wpcli_mariadb simple-history event list --orderby=id --order=asc --count=5 --fields=ID,date,description
docker compose run --rm wpcli_mariadb simple-history event list --count=5 --fields=ID,date,description
```

Expected: the first command lists the five lowest event IDs in ascending order, the second lists the five newest. If either fatals with a Rank Math error, re-run with `--skip-plugins --skip-themes`.

-   [ ] **Step 6: Verify the option list is enforced**

Run:

```bash
cd /Users/bonnymacmini/Projects/_docker-compose-to-run-on-system-boot
docker compose run --rm wpcli_mariadb simple-history event list --orderby=nonsense --count=5
```

Expected: WP-CLI errors with a message naming the accepted values, without running the query.

-   [ ] **Step 7: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: no errors.

-   [ ] **Step 8: Add the changelog entry**

Use the **changelog** skill. Under `### Added` in the Unreleased section:

```
-   Events can now be sorted by date, level, logger or event type through the REST API (`orderby` and `order`) and on the command line (`wp simple-history event list --orderby=level --order=asc`).
```

-   [ ] **Step 9: Commit**

```bash
git add inc/services/wp-cli-commands/class-wp-cli-list-command.php readme.txt
git commit -m "Add --orderby and --order to wp simple-history event list"
```

---

### Task 5: Add the Table view to the switcher, showing a stub preview

Split from Task 6 deliberately: this task makes the button work end to end with placeholder content, so the plumbing (URL literal, user meta enum, Slot fallback) can be verified on its own before any markup design happens.

**Files:**

-   Create: `src/components/TablePreview.jsx`
-   Modify: `src/components/EventsViewToggle.jsx` — the `VIEWS` array and `labels`
-   Modify: `src/components/EventsGui.jsx:346-350` — the `parseAsStringLiteral` list, and wherever the events list is rendered
-   Modify: `inc/services/class-rest-api.php:93` — the `enum` on the `view` argument
-   Test: `tests/playwright/events-view-toggle.spec.js`

**Interfaces:**

-   Consumes: `eventsView` from Task 1.
-   Produces:

    -   `'table'` is a valid value of `eventsView`, of `?view=`, and of the `view` parameter on `POST /simple-history/v1/events-view`.
    -   `<TablePreview />` exported from `src/components/TablePreview.jsx`, taking no props.
    -   A `SimpleHistorySlotTableView` Slot rendered where the events list normally goes, falling back to `<TablePreview />` when nothing fills it.

-   [ ] **Step 1: Write the failing test**

Append to `tests/playwright/events-view-toggle.spec.js`:

```js
test( 'table view shows the premium preview', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE );
	await page.locator( '.sh-EventsViewToggle' ).waitFor();

	await page.getByRole( 'button', { name: 'Table view' } ).click();

	await expect( page.locator( '.sh-TablePreview' ) ).toBeVisible();
} );

test( 'table view can be linked to with ?view=table', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );

	await expect( page.locator( '.sh-TablePreview' ) ).toBeVisible();
	await expect(
		page.getByRole( 'button', { name: 'Table view' } )
	).toHaveAttribute( 'aria-pressed', 'true' );
} );
```

Remember to reset the view to `detailed` in the spec's existing cleanup so the `tests` project is not left on the table view.

-   [ ] **Step 2: Run the test to verify it fails**

Run: `npx playwright test --project=events-view`
Expected: FAIL. There is no "Table view" button, so `click()` times out.

-   [ ] **Step 3: Create the stub preview component**

Create `src/components/TablePreview.jsx`:

```jsx
import { __ } from '@wordpress/i18n';

/**
 * Preview of the premium Table view, shown when nothing fills the
 * SimpleHistorySlotTableView Slot.
 *
 * This component deliberately contains no table logic: no sorting, no
 * selection, no data fetching. It is a picture of a feature that lives in
 * Simple History Premium, in the same spirit as the alerts settings teaser.
 * Keep it that way — shipping working-but-switched-off code in the free
 * plugin is what the WordPress.org guidelines call trialware.
 */
export function TablePreview() {
	return (
		<div className="sh-TablePreview">
			<p>
				{ __(
					'Table view is part of Simple History Premium.',
					'simple-history'
				) }
			</p>
		</div>
	);
}
```

-   [ ] **Step 4: Add the Table option to the toggle**

In `src/components/EventsViewToggle.jsx`, import the table icon and add the third view:

```jsx
import { table } from '@wordpress/icons';
```

```jsx
const VIEWS = [
	{ value: 'detailed', icon: viewAgenda },
	{ value: 'compact', icon: viewHeadline },
	{ value: 'table', icon: table },
];
```

and in `labels`:

```jsx
		table: __( 'Table view', 'simple-history' ),
```

`table` comes from `@wordpress/icons`, which is bundled into the build, so there is no WP 6.3 concern. Do not add a Material Symbols path to `src/icons.jsx` for this — that file's own guidance is to prefer an existing `@wordpress/icons` export when one fits.

-   [ ] **Step 5: Accept `table` in the URL state**

In `src/components/EventsGui.jsx`, extend the literal list:

```jsx
const [ urlEventsView, setUrlEventsView ] = useQueryState(
	'view',
	parseAsStringLiteral( [ 'detailed', 'compact', 'table' ] ).withOptions(
		useQueryStateOptions
	)
);
```

-   [ ] **Step 6: Accept `table` in the saved preference**

In `inc/services/class-rest-api.php`, extend the enum on the `view` argument:

```php
						'enum'        => [ 'detailed', 'compact', 'table' ],
```

-   [ ] **Step 7: Render the Slot with the preview as its fallback**

In `src/components/EventsGui.jsx`, find where the events list is rendered for the current view and add the table branch. Import the Slot and the preview:

```jsx
import { Slot } from '@wordpress/components';
import { TablePreview } from './TablePreview';
```

Render, where the list would go:

```jsx
			{ eventsView === 'table' ? (
				/* Premium fills this Slot with the real table. With no fill —
				   no Premium, or a Premium too old to know about the Slot —
				   the preview renders, so the view is never blank. */
				<Slot
					name="SimpleHistorySlotTableView"
					fillProps={ {
						eventsQueryParams,
						eventsTotal,
						hasAnyActiveFilters,
						eventsIsLoading,
					} }
				>
					{ ( fills ) =>
						fills.length > 0 ? fills : <TablePreview />
					}
				</Slot>
			) : (
				/* existing detailed/compact list rendering, unchanged */
			) }
```

Do not pass `bubblesVirtually` — the default of `false` is what makes render-prop children work.

Before writing this, read the surrounding render to get the existing variable names right:

Run: `grep -n "EventsList\|eventsIsLoading\|eventsTotal\|hasAnyActiveFilters" src/components/EventsGui.jsx | head -30`

-   [ ] **Step 8: Build and run the test to verify it passes**

Run: `npm run build && npx playwright test --project=events-view`
Expected: PASS. Note that the dev WordPress has Premium active — that is fine, because Premium does not fill this Slot yet, so the fallback renders.

-   [ ] **Step 9: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: no errors.

-   [ ] **Step 10: Commit**

```bash
git add src/components/TablePreview.jsx src/components/EventsViewToggle.jsx \
  src/components/EventsGui.jsx inc/services/class-rest-api.php \
  tests/playwright/events-view-toggle.spec.js
git commit -m "Add a Table view option to the event log view switcher"
```

---

### Task 6: Build out the preview — banner, staged rows, styles

**Files:**

-   Modify: `src/components/TablePreview.jsx`
-   Modify: `css/styles.css`
-   Modify: `inc/services/class-scripts-and-templates.php` (only if the preview needs a new localised value — check before assuming it does)
-   Test: `tests/playwright/events-view-toggle.spec.js`

**Interfaces:**

-   Consumes: `TablePreview` from Task 5.
-   Produces: `.sh-TablePreview` containing `.sh-TablePreview__banner` (clickable) and `.sh-TablePreview__table` (inert, `aria-hidden`).

**Reference to read first:** `inc/services/class-alerts-settings-page-teaser.php`, especially `render_preview_banner()` at line 139 and the `.sh-AlertsTeaser-banner` styles. That teaser converts at 15.6% and this one copies its structure.

-   [ ] **Step 1: Write the failing test**

Add to `tests/playwright/events-view-toggle.spec.js`:

```js
test( 'table preview is inert and its CTA is not', async ( {
	page,
	requestUtils,
} ) => {
	await setStoredView( requestUtils, 'detailed' );

	await page.goto( SIMPLE_HISTORY_PAGE + '&view=table' );
	await page.locator( '.sh-TablePreview' ).waitFor();

	// The sample table is decoration: hidden from screen readers and
	// unreachable by the mouse.
	const sampleTable = page.locator( '.sh-TablePreview__table' );
	await expect( sampleTable ).toHaveAttribute( 'aria-hidden', 'true' );
	await expect( sampleTable ).toHaveCSS( 'pointer-events', 'none' );

	// Every checkbox in it is disabled.
	const boxes = sampleTable.locator( 'input[type="checkbox"]' );
	const count = await boxes.count();
	expect( count ).toBeGreaterThan( 0 );

	for ( let i = 0; i < count; i++ ) {
		await expect( boxes.nth( i ) ).toBeDisabled();
	}

	// The upgrade link stays clickable and carries the campaign.
	const cta = page.locator( '.sh-TablePreview__banner a' ).first();
	await expect( cta ).toBeVisible();

	const href = await cta.getAttribute( 'href' );
	expect( href ).toContain( 'utm_campaign=premium_table_view' );
	expect( href ).toContain( 'utm_content=' );
} );
```

-   [ ] **Step 2: Run the test to verify it fails**

Run: `npx playwright test --project=events-view -g "inert"`
Expected: FAIL. `.sh-TablePreview__table` does not exist yet.

-   [ ] **Step 3: Get the sample event mix**

Read the **wp-org-screenshots** skill for which events read well when the log is being shown off, and use that mix here. Aim for six to eight rows covering: a post update, a failed login, a plugin update, a user role change, a settings save, and a WP-CLI action. Use plausible names and times, not `foo`/`bar`.

-   [ ] **Step 4: Write the full preview component**

Replace the body of `src/components/TablePreview.jsx`. The banner sits outside the inert wrapper so its link still works, exactly as `.sh-AlertsTeaser-banner` does.

```jsx
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { table } from '@wordpress/icons';

// Staged sample rows. Deliberately not the reader's own events: a quiet site
// previews badly, and the point of the preview is to show the table doing the
// things it is good at — sorted, with rows picked out for export.
//
// The mix follows the wp-org-screenshots skill: a spread of loggers and levels,
// one warning to give the Level column something to say, and plausible names
// rather than placeholders. Two rows are pre-selected so the bulk bar below has
// a reason to read "2 selected".
const SAMPLE_ROWS = [
	{
		id: 1,
		selected: true,
		date: '12:04:11',
		user: 'anna',
		message: __( 'Updated post "Pricing"', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 2,
		selected: true,
		date: '12:03:58',
		user: 'anna',
		message: __( 'Updated the option "blogdescription"', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 3,
		selected: false,
		date: '11:59:02',
		user: '—',
		message: __(
			'Failed to login with username "admin"',
			'simple-history'
		),
		level: __( 'Warning', 'simple-history' ),
	},
	{
		id: 4,
		selected: false,
		date: '11:47:20',
		user: 'jonas',
		message: __(
			'Changed role for user "sara" to Editor',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 5,
		selected: false,
		date: '11:12:44',
		user: 'WP-CLI',
		message: __(
			'Updated plugin "WooCommerce" to 9.4.2',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 6,
		selected: false,
		date: '10:58:06',
		user: 'jonas',
		message: __(
			'Uploaded attachment "hero-autumn.jpg"',
			'simple-history'
		),
		level: __( 'Info', 'simple-history' ),
	},
	{
		id: 7,
		selected: false,
		date: '09:30:15',
		user: 'WordPress',
		message: __( 'Updated WordPress to 6.9.1', 'simple-history' ),
		level: __( 'Info', 'simple-history' ),
	},
];

/**
 * Preview of the premium Table view, shown when nothing fills the
 * SimpleHistorySlotTableView Slot.
 *
 * This component contains no table logic on purpose: no sorting, no selection,
 * no data fetching, no TanStack import. It is a picture of a feature that lives
 * in Simple History Premium, in the same spirit as the alerts settings teaser.
 * Keep it that way — shipping working-but-switched-off code in the free plugin
 * is what the WordPress.org guidelines call trialware.
 */
export function TablePreview() {
	const upgradeUrl = window.simpleHistoryTablePreview?.upgradeUrl ?? '';

	return (
		<div className="sh-TablePreview">
			<div className="sh-TablePreview__banner">
				<Icon icon={ table } size={ 24 } aria-hidden="true" />

				<div className="sh-TablePreview__banner-content">
					<span className="sh-TablePreview__banner-title">
						{ __(
							'Sort, select and export your log',
							'simple-history'
						) }
						<span className="sh-Badge sh-Badge--premium">
							{ __( 'Premium', 'simple-history' ) }
						</span>
					</span>

					<span>
						{ __(
							'See your events as a sortable table. Sort by date, level or event type, pick the rows you want, and export them to CSV or JSON.',
							'simple-history'
						) }
					</span>

					<a href={ upgradeUrl }>
						{ __( 'Upgrade to Premium', 'simple-history' ) } →
					</a>
				</div>
			</div>

			{ /* Decoration, not data: hidden from screen readers, which get the
			     banner above instead, and unreachable by the mouse. */ }
			<table className="sh-TablePreview__table" aria-hidden="true">
				<thead>
					<tr>
						<th>
							<input type="checkbox" disabled />
						</th>
						<th className="is-sorted">
							{ __( 'Date', 'simple-history' ) } ↓
						</th>
						<th>{ __( 'User', 'simple-history' ) }</th>
						<th>{ __( 'Message', 'simple-history' ) }</th>
						<th>{ __( 'Level', 'simple-history' ) }</th>
					</tr>
				</thead>

				<tbody>
					{ SAMPLE_ROWS.map( ( row ) => (
						<tr key={ row.id }>
							<td>
								<input
									type="checkbox"
									disabled
									defaultChecked={ row.selected }
								/>
							</td>
							<td>{ row.date }</td>
							<td>{ row.user }</td>
							<td>{ row.message }</td>
							<td>{ row.level }</td>
						</tr>
					) ) }
				</tbody>
			</table>

			<div className="sh-TablePreview__bulkBar" aria-hidden="true">
				{ __( '2 selected', 'simple-history' ) } ·{ ' ' }
				{ __( 'Export', 'simple-history' ) } ▾
			</div>
		</div>
	);
}
```

-   [ ] **Step 5: Localise the tracking URL**

The URL has to be built in PHP so it goes through `Helpers::get_tracking_url()`. Find how the existing promo components get their URLs before adding a new localised object — there may already be one to extend:

Run: `grep -rn "get_tracking_url" inc/services/class-scripts-and-templates.php inc/class-simple-history.php | head`

If there is an existing localised settings object for the log page, add the URL to it rather than creating `simpleHistoryTablePreview`, and update the component to read from it. The value must be:

```php
Helpers::get_tracking_url(
	'https://simple-history.com/premium/',
	'premium_table_view',
	'wpadmin',
	'plugin',
	'banner_cta'
);
```

The `utm_content` of `banner_cta` matters. Issue 280 found that three quarters of the user card's traffic came from an untagged link and nobody could tell which link it was — do not repeat that.

-   [ ] **Step 6: Add the styles**

Add to `css/styles.css`, following the `.sh-AlertsTeaser-banner` rules. Keep it static: no hover lifts, no shadows, no transitions.

```css
.sh-TablePreview__table {
	width: 100%;
	border-collapse: collapse;
	/* Decoration, not a control. The aria-hidden above keeps it out of the
	   accessibility tree; this keeps it out of reach of the mouse. */
	pointer-events: none;
	user-select: none;
}

.sh-TablePreview__table th,
.sh-TablePreview__table td {
	padding: 8px 10px;
	text-align: left;
	border-bottom: 1px solid #dcdcde;
	font-size: 13px;
	line-height: 1.5;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.sh-TablePreview__table th {
	font-weight: 600;
	color: #1d2327;
	border-bottom: 1px solid #c3c4c7;
}

/* The column the staged table is "sorted" by. Colour alone would not carry
   this, so the arrow in the markup does the work and this only reinforces it. */
.sh-TablePreview__table th.is-sorted {
	color: #2271b1;
}

.sh-TablePreview__table td {
	color: #50575e;
}

.sh-TablePreview__banner {
	display: flex;
	gap: 12px;
	align-items: flex-start;
	padding: 16px;
	margin-bottom: 16px;
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-left: 4px solid #2271b1;
	/* The one part of the preview that is meant to be used. */
	pointer-events: auto;
}

.sh-TablePreview__banner-content {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.sh-TablePreview__banner-title {
	display: flex;
	gap: 8px;
	align-items: center;
	font-weight: 600;
	color: #1d2327;
}

.sh-TablePreview__bulkBar {
	padding: 10px;
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	border-top: none;
	font-size: 13px;
	color: #50575e;
}
```

Every colour above is from the WordPress admin palette, so contrast is already AA: `#1d2327` and `#50575e` on `#fff`/`#f6f7f7` clear 4.5:1, and `#2271b1` is the admin's own primary blue. If you change any of them, re-check with a contrast tool rather than by eye.

-   [ ] **Step 7: Build and verify the styles actually reached the browser**

`styles.css` is served under a static `?ver`, so an edit can look like a no-op until the cache is busted. Confirm the file on the server has the new rule before blaming a selector:

```bash
npm run build
curl -s http://wordpress-stable-docker-mariadb.test:8282/wp-content/plugins/simple-history/css/styles.css | grep -c "sh-TablePreview"
```

Expected: a non-zero count.

-   [ ] **Step 8: Run the test to verify it passes**

Run: `npx playwright test --project=events-view`
Expected: PASS, all table-preview tests plus the earlier toggle tests.

-   [ ] **Step 9: Look at it**

Open `http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page&view=table` and check it against the alerts teaser at Settings → Alerts. It should read as a preview of something good, not as a broken table. Measure rather than eyeball anything you are unsure about — `getBoundingClientRect()` and `getComputedStyle()` in the console beat reading the CSS.

-   [ ] **Step 10: Run the compliance check**

Use the **wordpress-org-compliance** skill against `src/components/TablePreview.jsx`. Confirm specifically:

-   no sorting, selection or fetching logic in the file
-   no license key consulted anywhere in the free path
-   List and Compact are untouched and fully working

Run: `grep -nE "onClick|onChange|useState|useEffect|apiFetch|sort\(" src/components/TablePreview.jsx`
Expected: no matches.

-   [ ] **Step 11: Commit**

```bash
git add src/components/TablePreview.jsx css/styles.css \
  tests/playwright/events-view-toggle.spec.js
git commit -m "Build out the Table view preview"
```

---

### Task 7: Changelog, screenshot and final checks

**Files:**

-   Modify: `readme.txt`
-   Create: screenshot in the Obsidian vault at `$SH_NOTES_DIR/Simple History/screenshots/240-table-preview.png`

-   [ ] **Step 1: Add the changelog entry**

Use the **changelog** skill, then run the draft through the **simple-history-voice** skill. Under `### Added`:

```
-   A Table view of the event log, with sortable columns, row selection and export. Part of Simple History Premium; the free plugin shows a preview of it in the view switcher.
```

Check that Task 1's **Changed** entry and Task 4's **Added** entry are both still present and read as one set.

-   [ ] **Step 2: Take the feature screenshot**

Capture the table preview and save it to `$SH_NOTES_DIR/Simple History/screenshots/240-table-preview.png` for later blog and release use. Compress before saving:

```bash
pngquant --quality=80-95 --strip --skip-if-larger --force --ext .png <file>.png
oxipng -o max --strip safe <file>.png
```

-   [ ] **Step 3: Run every PHP suite**

Run: `npm run test:wpunit`
Expected: PASS. If something fails, suspect the environment before the code — rebuild the fixture with `scripts/build-test-fixture.sh` and baseline against the previous tag before concluding the change broke it.

-   [ ] **Step 4: Run the full browser suite**

Run: `npx playwright test`
Expected: PASS. Always pass `--project` when running a subset — a bare `npx playwright test` also runs the screenshot project, which rewrites committed PNGs.

Run: `git status --short` afterwards and confirm no screenshot PNGs were modified.

-   [ ] **Step 5: Lint and analyse**

```bash
npm run php:lint
npm run lint:js
npm run lint:css
./vendor/bin/phpstan analyse --memory-limit=2G
```

Expected: clean. PHPStan with no path argument.

-   [ ] **Step 6: Check the premium minimum core version**

Nothing in this plan is called from Premium yet, so no bump should be needed. Confirm:

Run: `npm run addons:check`
Expected: PASS.

-   [ ] **Step 7: Commit**

```bash
git add readme.txt
git commit -m "Add changelog entries for the table view and event sorting"
```

-   [ ] **Step 8: Update the issue**

Append an agent note to `240 - Event log views — list, compact, table, calendar` recording: what shipped, the commit range, the branch, that it is not yet merged, and that the premium table is the next plan. Use the **local-issues** skill. Leave the branch unmerged and do not push — merging and pushing are the user's calls.

---

## What this plan does not cover

The premium table itself — TanStack Table, the virtualizer, sortable headers, row selection, the bulk bar into `ExportModal`, and column configuration. That is a separate plan in the add-ons repo, written once this core work is merged and `premium_table_view` has a month of numbers behind it. The spec's "Premium work" section is the input to it.
