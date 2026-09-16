# Event Log Compact View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a Detailed | Compact toggle to the Simple History event log, with the choice stored per user and overridable by `?view=` in the URL.

**Architecture:** A new `variant="compact"` on the existing `Event` React component hides the details table and tightens the header, keeping the actions menu. A small `EventsViewToggle` component sits at the right end of `EventsControlBar`. `EventsGui` owns the view state: the `?view=` query param (nuqs) wins, otherwise the value localized from user meta `simple_history_events_view`, which is saved through the core `/wp/v2/users/me` endpoint.

**Tech Stack:** WordPress PHP (7.4+), React via `@wordpress/scripts` 27, `@wordpress/components`, `@wordpress/icons`, nuqs 2.4.3, Codeception wpunit, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-15-event-log-compact-view-design.md`

## Global Constraints

-   PHP 7.4 compatible. No typed properties, no arrow functions with `fn` in PHP, no `match`.
-   `@wordpress/*` imports that are externals must exist in WordPress 6.3. `@wordpress/icons` is bundled, so any icon is fine.
-   Text domain `simple-history`. Prefix `sh`, `simplehistory` or `simple_history`.
-   User meta key: `simple_history_events_view`. Allowed values: `detailed`, `compact`. Default `detailed`.
-   URL parameter: `view`, values `detailed` | `compact`.
-   The dashboard widget (`variant="dashboard"`) must look and behave exactly as before.
-   No hover lifts, shadows or transitions on new UI.
-   Code style from `code.md`: blank line before `if` and `return`, comments on their own line above the code.
-   Never run `npx wp-scripts build`; always `npm run build`.
-   Run phpstan as `./vendor/bin/phpstan analyse --memory-limit=2G` with no path argument before committing PHP.
-   Commit messages end with `Claude-Session: https://claude.ai/code/session_01Ls7PzUNsQ6nx3v76yrnJhZ`.
-   Work on branch `issue-240-event-log-views`.

## File Structure

| File                                          | Change | Responsibility                                                                                  |
| --------------------------------------------- | ------ | ----------------------------------------------------------------------------------------------- |
| `inc/services/class-rest-api.php`             | Modify | Register the `simple_history_events_view` user meta on `init`                                   |
| `dropins/class-react-dropin.php`              | Modify | Localize the current user's stored view as `simpleHistoryReactData.eventsView`                  |
| `tests/wpunit/EventsViewUserMetaTest.php`     | Create | Registration, own-user write, other-user write refused, invalid value refused, no profile event |
| `src/components/Event.jsx`                    | Modify | Hide details for `compact`                                                                      |
| `src/components/EventInitiatorName.jsx`       | Modify | `compact` branch: name in `UserCard`, no email                                                  |
| `src/components/EventDate.jsx`                | Modify | `compact` branch: relative time, opens modal, full-date tooltip                                 |
| `src/components/EventIPAddresses.jsx`         | Modify | No label for `compact`                                                                          |
| `css/styles.css`                              | Modify | `.SimpleHistoryLogitem--variant-compact` density rules and `.sh-EventsViewToggle`               |
| `src/components/EventsViewToggle.jsx`         | Create | Two-radio icon toggle                                                                           |
| `src/components/EventsControlBar.jsx`         | Modify | Render the toggle after Share                                                                   |
| `src/components/EventsListItemsList.jsx`      | Modify | Pass `variant` from `eventsView`                                                                |
| `src/components/EventsList.jsx`               | Modify | Forward `eventsView`                                                                            |
| `src/components/EventsGui.jsx`                | Modify | View state, URL param, background save                                                          |
| `tests/playwright/events-view-toggle.spec.js` | Create | End-to-end behaviour                                                                            |
| `readme.txt`                                  | Modify | Changelog entry                                                                                 |

---

### Task 1: User meta for the stored view

**Files:**

-   Modify: `inc/services/class-rest-api.php`
-   Modify: `dropins/class-react-dropin.php:59-70` (the `wp_localize_script` call)
-   Test: `tests/wpunit/EventsViewUserMetaTest.php`

**Interfaces:**

-   Produces: `Simple_History\Services\REST_API::EVENTS_VIEW_USER_META_KEY` = `'simple_history_events_view'`; public method `REST_API::register_user_meta(): void`; JS global `window.simpleHistoryReactData.eventsView` (`'detailed' | 'compact'`); REST write `POST /wp/v2/users/me` with body `{ meta: { simple_history_events_view: 'compact' } }`.

-   [ ] **Step 1: Write the failing test**

Create `tests/wpunit/EventsViewUserMetaTest.php`:

```php
<?php

use Simple_History\Services\REST_API;

/**
 * Issue 240: the event log view (Detailed or Compact) is stored per user in
 * user meta and saved through the core /wp/v2/users/me endpoint.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsViewUserMetaTest
 */
class EventsViewUserMetaTest extends \Codeception\TestCase\WPTestCase {
	/** @var int */
	private $subscriber_id;

	/** @var int */
	private $admin_id;

	public function setUp(): void {
		parent::setUp();

		$this->subscriber_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->admin_id      = $this->factory->user->create( array( 'role' => 'administrator' ) );

		// rest_do_request() doesn't define REST_REQUEST in the test environment.
		add_filter( 'simple_history/is_rest_request', '__return_true' );
	}

	public function tearDown(): void {
		remove_all_filters( 'simple_history/is_rest_request' );
		parent::tearDown();
	}

	public function test_meta_key_is_registered_for_rest_with_enum() {
		$keys = get_registered_meta_keys( 'user' );

		$this->assertArrayHasKey( REST_API::EVENTS_VIEW_USER_META_KEY, $keys );

		$args = $keys[ REST_API::EVENTS_VIEW_USER_META_KEY ];

		$this->assertTrue( $args['single'] );
		$this->assertSame( 'detailed', $args['default'] );
		$this->assertSame( array( 'detailed', 'compact' ), $args['show_in_rest']['schema']['enum'] );
	}

	public function test_default_is_detailed() {
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_user_can_save_own_view() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->post_view( '/wp/v2/users/me', 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			'compact',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_invalid_value_is_rejected() {
		wp_set_current_user( $this->subscriber_id );

		$response = $this->post_view( '/wp/v2/users/me', 'table' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_admin_cannot_write_another_users_view() {
		wp_set_current_user( $this->admin_id );

		$response = $this->post_view( "/wp/v2/users/{$this->subscriber_id}", 'compact' );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame(
			'detailed',
			get_user_meta( $this->subscriber_id, REST_API::EVENTS_VIEW_USER_META_KEY, true )
		);
	}

	public function test_saving_view_does_not_log_a_profile_edit() {
		wp_set_current_user( $this->admin_id );

		$count_before = $this->get_event_count();

		$response = $this->post_view( '/wp/v2/users/me', 'compact' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			$count_before,
			$this->get_event_count(),
			'Switching the event log view must not log a profile edit'
		);
	}

	/**
	 * @param string $route REST route.
	 * @param string $view  View value to save.
	 * @return WP_REST_Response
	 */
	private function post_view( $route, $view ) {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_body_params(
			array(
				'meta' => array(
					REST_API::EVENTS_VIEW_USER_META_KEY => $view,
				),
			)
		);

		return rest_do_request( $request );
	}

	/**
	 * @return int
	 */
	private function get_event_count() {
		global $wpdb;

		$table = \Simple_History\Simple_History::get_instance()->get_events_table_name();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsViewUserMetaTest`
Expected: FAIL — `Error: Undefined constant Simple_History\Services\REST_API::EVENTS_VIEW_USER_META_KEY`.

-   [ ] **Step 3: Register the meta**

In `inc/services/class-rest-api.php`, add the constant and hook inside the class, and the method after `loaded()`:

```php
class REST_API extends Service {
	/**
	 * User meta key holding the user's chosen event log view, "detailed" or "compact".
	 */
	const EVENTS_VIEW_USER_META_KEY = 'simple_history_events_view';

	/** @inheritDoc */
	public function loaded() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// On init, not rest_api_init, so the registered default also applies
		// when the events page reads the value outside a REST request.
		add_action( 'init', [ $this, 'register_user_meta' ] );
	}

	/**
	 * Register the user meta that stores the event log view.
	 *
	 * Saved by the events page through the core /wp/v2/users/me endpoint,
	 * so no route of our own is needed.
	 */
	public function register_user_meta() {
		register_meta(
			'user',
			self::EVENTS_VIEW_USER_META_KEY,
			[
				'type'          => 'string',
				'single'        => true,
				'default'       => 'detailed',
				'show_in_rest'  => [
					'schema' => [
						'type' => 'string',
						'enum' => [ 'detailed', 'compact' ],
					],
				],
				// Users may only change their own view.
				'auth_callback' => function ( $allowed, $meta_key, $object_id, $user_id ) {
					return (int) $object_id === (int) $user_id;
				},
			]
		);
	}
```

(Keep the existing `register_routes()` method unchanged below.)

-   [ ] **Step 4: Localize the stored view**

In `dropins/class-react-dropin.php`, add `use Simple_History\Services\REST_API;` after `use Simple_History\Helpers;`, then change the localize call to:

```php
		$stored_events_view = get_user_meta( get_current_user_id(), REST_API::EVENTS_VIEW_USER_META_KEY, true );

		wp_localize_script(
			'simple_history_wp_scripts',
			'simpleHistoryReactData',
			[
				'eventsAdminPageURL' => Helpers::get_history_admin_url(),
				// Read at enqueue time so the first render already uses the user's view.
				'eventsView'         => $stored_events_view === 'compact' ? 'compact' : 'detailed',
			]
		);
```

Keep the existing comment block above `wp_localize_script` as it is.

-   [ ] **Step 5: Run test to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsViewUserMetaTest`
Expected: PASS, 6 tests.

If `test_saving_view_does_not_log_a_profile_edit` fails, the User logger is logging a meta-only REST update. Stop and report — issue 294's guard in `User_Logger::on_profile_update_commit()` should prevent it (see `tests/wpunit/UserLoggerGhostProfileEditTest.php`).

-   [ ] **Step 6: Lint and analyse**

Run: `docker compose run --rm php-lint vendor/bin/phpcs inc/services/class-rest-api.php dropins/class-react-dropin.php tests/wpunit/EventsViewUserMetaTest.php`
Expected: no errors. Fix any reported.

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: `[OK] No errors`.

-   [ ] **Step 7: Commit**

```bash
git add inc/services/class-rest-api.php dropins/class-react-dropin.php tests/wpunit/EventsViewUserMetaTest.php
git commit -m "Store the event log view per user in user meta

Registers simple_history_events_view (detailed or compact) for REST so
the events page can save it through /wp/v2/users/me, and hands the
stored value to the page at enqueue time.

Claude-Session: https://claude.ai/code/session_01Ls7PzUNsQ6nx3v76yrnJhZ"
```

---

### Task 2: Compact variant of `Event`

**Files:**

-   Modify: `src/components/Event.jsx:63-65`
-   Modify: `src/components/EventInitiatorName.jsx:21-22`
-   Modify: `src/components/EventDate.jsx:129-131`
-   Modify: `src/components/EventIPAddresses.jsx:554-562`
-   Modify: `css/styles.css` (append after the `.is-dashboard` action-link rules, around line 1140)

**Interfaces:**

-   Produces: `<Event variant="compact" />` renders a compact row. The root `<li>` gets class `SimpleHistoryLogitem--variant-compact` (already produced by `Event.jsx` from the variant). No details element (`.SimpleHistoryLogitem__details`) is rendered; `.SimpleHistoryLogitem__actions` is rendered.

This task has no automated test of its own: nothing renders `variant="compact"` until Task 3, whose Playwright spec covers it. Verify with a build and a search.

-   [ ] **Step 1: Confirm nothing passes the compact variant today**

Run: `grep -rn "variant=\"compact\"\|variant={ 'compact' }\|eventVariant=\"compact\"" src`
Expected: no output. If there is output, stop and report — the existing `compact` branches are in use and must not be repurposed.

-   [ ] **Step 2: Hide details in `Event.jsx`**

Replace:

```jsx
{
	variant !== 'dashboard' && (
		<EventDetails event={ event } eventVariant={ variant } />
	);
}
```

with:

```jsx
{
	variant !== 'dashboard' && variant !== 'compact' && (
		<EventDetails event={ event } eventVariant={ variant } />
	);
}
```

-   [ ] **Step 3: Name without email in `EventInitiatorName.jsx`**

Replace:

```jsx
			if ( eventVariant === 'compact' ) {
				userDisplay = <strong>{ nameToDisplay }</strong>;
			} else if ( eventVariant === 'dashboard' ) {
```

with:

```jsx
			if ( eventVariant === 'compact' || eventVariant === 'dashboard' ) {
```

(The existing `dashboard` body — `<UserCard event={ event }><strong>{ nameToDisplay }</strong></UserCard>` — now serves both.)

-   [ ] **Step 4: Relative date that opens the modal in `EventDate.jsx`**

Replace:

```jsx
	if ( eventVariant === 'compact' ) {
		output = <span>{ formattedDateLiveUpdated }</span>;
	} else if ( eventVariant === 'dashboard' ) {
```

with:

```jsx
	if ( eventVariant === 'compact' ) {
		output = (
			<Tooltip text={ tooltipText } delay={ 500 }>
				<Button variant="link" onClick={ handleDateClick }>
					<time
						dateTime={ event.date_gmt }
						className="SimpleHistoryLogitem__when__liveRelative"
					>
						{ formattedDateLiveUpdated }
					</time>
				</Button>
			</Tooltip>
		);
	} else if ( eventVariant === 'dashboard' ) {
```

-   [ ] **Step 5: No IP label in `EventIPAddresses.jsx`**

Replace:

```jsx
	const ipAddressesLabel =
		eventVariant === 'dashboard'
			? ''
```

with:

```jsx
	const ipAddressesLabel =
		eventVariant === 'dashboard' || eventVariant === 'compact'
			? ''
```

-   [ ] **Step 6: Density CSS**

Append to `css/styles.css` directly after the last `.SimpleHistoryReactRoot.is-dashboard .SimpleHistoryLogitem__actionLinks__link + .SimpleHistoryLogitem__actionLinks__link::before` rule:

```css
/*
 * Compact view of the main event log (issue 240).
 * Same density as the dashboard widget, but kept separate from the
 * .is-dashboard rules so a change to one never moves the other.
 */
.SimpleHistoryLogitem--variant-compact {
	padding-top: var( --sh-spacing-small );
	padding-bottom: var( --sh-spacing-small );
}

/* No extra space before a labelled date separator. */
.SimpleHistoryLogitem--variant-compact:has(
		+ .SimpleHistoryLogitem .SimpleHistoryEventSeparator--hasLabel
	) {
	padding-bottom: calc( var( --sh-spacing-small ) * 3 );
}

/* The separator pulls itself up by the row padding; match the smaller padding. */
.SimpleHistoryLogitem--variant-compact .SimpleHistoryEventSeparator {
	margin-top: calc( var( --sh-spacing-small ) * -1 );
}

.SimpleHistoryLogitem--variant-compact .SimpleHistoryLogitem__senderImage {
	width: 24px;
	height: 24px;
}

.SimpleHistoryLogitem--variant-compact.SimpleHistoryLogitem--initiator-wp
	.SimpleHistoryLogitem__senderImage:before,
.SimpleHistoryLogitem--variant-compact.SimpleHistoryLogitem--initiator-wp_cli
	.SimpleHistoryLogitem__senderImage:before,
.SimpleHistoryLogitem--variant-compact.SimpleHistoryLogitem--initiator-web_user
	.SimpleHistoryLogitem__senderImage:before,
.SimpleHistoryLogitem--variant-compact.SimpleHistoryLogitem--initiator-other
	.SimpleHistoryLogitem__senderImage:before {
	font-size: 24px;
}

.SimpleHistoryLogitem--variant-compact .SimpleHistoryLogitem__secondcol {
	margin-left: 40px;
}

.SimpleHistoryLogitem--variant-compact .SimpleHistoryLogitem__actionLinks {
	gap: var( --sh-spacing-xsmall ) 0;
	margin-top: var( --sh-spacing-xsmall );
	margin-bottom: 0;
}
```

-   [ ] **Step 7: Build**

Run: `npm run build`
Expected: `webpack compiled successfully` (warnings about bundle size are pre-existing and fine; errors are not).

-   [ ] **Step 8: Check the dashboard widget is untouched**

Run: `git diff --stat -- src css` and confirm only the five files above changed. Then open `http://wordpress-stable-docker-mariadb.test:8282/wp-admin/` (login `claude` / `claude`) and confirm the Simple History dashboard widget still shows names without email, relative dates as plain links, and no ⋯ menu.

-   [ ] **Step 9: Commit**

```bash
git add src/components/Event.jsx src/components/EventInitiatorName.jsx src/components/EventDate.jsx src/components/EventIPAddresses.jsx css/styles.css
git commit -m "Add a compact variant of the event row

Dashboard-widget density for the main log, but unlike the widget it
keeps the actions menu, and the date opens the event like it does in
the detailed list. Nothing renders it yet.

Claude-Session: https://claude.ai/code/session_01Ls7PzUNsQ6nx3v76yrnJhZ"
```

---

### Task 3: View toggle, state and persistence

**Files:**

-   Create: `src/components/EventsViewToggle.jsx`
-   Modify: `src/components/EventsControlBar.jsx`
-   Modify: `src/components/EventsListItemsList.jsx`
-   Modify: `src/components/EventsList.jsx`
-   Modify: `src/components/EventsGui.jsx`
-   Modify: `css/styles.css` (append after the `.sh-ControlBarButtons` rule, around line 3919)
-   Test: `tests/playwright/events-view-toggle.spec.js`

**Interfaces:**

-   Consumes: `<Event variant="compact" />` (Task 2); `window.simpleHistoryReactData.eventsView` and the `simple_history_events_view` REST meta (Task 1).
-   Produces: `EventsViewToggle( { view: 'detailed'|'compact', onChange: ( view ) => void } )`; `EventsControlBar` props `eventsView`, `onEventsViewChange`; `EventsList` and `EventsListItemsList` prop `eventsView`.

-   [ ] **Step 1: Write the failing Playwright spec**

Create `tests/playwright/events-view-toggle.spec.js`:

```js
const { test, expect } = require( './fixtures' );

const SIMPLE_HISTORY_PAGE =
	'/wp-admin/admin.php?page=simple_history_admin_menu_page';

const META_KEY = 'simple_history_events_view';

/**
 * Save the admin's stored view directly, so each test starts from a known state.
 *
 * @param {Object} requestUtils
 * @param {string} view
 */
async function setStoredView( requestUtils, view ) {
	await requestUtils.rest( {
		path: '/wp/v2/users/me',
		method: 'POST',
		data: { meta: { [ META_KEY ]: view } },
	} );
}

test.describe( 'Event log view toggle', () => {
	// All tests share the admin's stored preference.
	test.describe.configure( { mode: 'serial' } );

	test.beforeEach( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		await setStoredView( requestUtils, 'detailed' );
	} );

	test( 'defaults to the detailed view', async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Detailed view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-normal' ).first()
		).toBeVisible();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
	} );

	test( 'switching to compact keeps the actions menu and sets the URL', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await page.getByRole( 'radio', { name: 'Compact view' } ).check();

		const firstCompact = page
			.locator( '.SimpleHistoryLogitem--variant-compact' )
			.first();

		await expect( firstCompact ).toBeVisible();
		await expect(
			page.locator(
				'.SimpleHistoryLogitem--variant-compact .SimpleHistoryLogitem__details'
			)
		).toHaveCount( 0 );
		await expect(
			firstCompact.locator( '.SimpleHistoryLogitem__actions' )
		).toHaveCount( 1 );
		await expect( page ).toHaveURL( /[?&]view=compact/ );
	} );

	test( 'compact is remembered after a reload without the parameter', async ( {
		page,
	} ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		const saved = page.waitForResponse(
			( response ) =>
				response.url().includes( 'users/me' ) &&
				response.request().method() === 'POST'
		);

		await page.getByRole( 'radio', { name: 'Compact view' } ).check();
		expect( ( await saved ).ok() ).toBe( true );

		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Compact view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' ).first()
		).toBeVisible();
	} );

	test( 'the URL parameter overrides the stored view', async ( {
		page,
		requestUtils,
	} ) => {
		await setStoredView( requestUtils, 'compact' );

		await page.goto( `${ SIMPLE_HISTORY_PAGE }&view=detailed` );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await expect(
			page.getByRole( 'radio', { name: 'Detailed view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' )
		).toHaveCount( 0 );
	} );

	test( 'arrow keys switch the view', async ( { page } ) => {
		await page.goto( SIMPLE_HISTORY_PAGE );
		await page.waitForSelector( '.SimpleHistoryLogitems.is-loaded' );

		await page.getByRole( 'radio', { name: 'Detailed view' } ).focus();
		await page.keyboard.press( 'ArrowRight' );

		await expect(
			page.getByRole( 'radio', { name: 'Compact view' } )
		).toBeChecked();
		await expect(
			page.locator( '.SimpleHistoryLogitem--variant-compact' ).first()
		).toBeVisible();
	} );
} );
```

-   [ ] **Step 2: Run the spec to verify it fails**

Run: `npx playwright test --project=tests tests/playwright/events-view-toggle.spec.js`
Expected: FAIL — `getByRole('radio', { name: 'Detailed view' })` not found.

-   [ ] **Step 3: Create `EventsViewToggle.jsx`**

```jsx
import { Icon, Tooltip, VisuallyHidden } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { menu, postList } from '@wordpress/icons';
import { clsx } from 'clsx';

/**
 * Switch between the detailed and the compact event list.
 *
 * Native radio inputs give arrow-key navigation and screen reader grouping
 * without any focus management of our own; the inputs are visually hidden
 * and their labels render as icon buttons.
 *
 * @param {Object}   props
 * @param {string}   props.view     Current view, "detailed" or "compact".
 * @param {Function} props.onChange Called with the newly chosen view.
 */
export function EventsViewToggle( { view, onChange } ) {
	const options = [
		{
			value: 'detailed',
			label: __( 'Detailed view', 'simple-history' ),
			icon: postList,
		},
		{
			value: 'compact',
			label: __( 'Compact view', 'simple-history' ),
			icon: menu,
		},
	];

	return (
		<fieldset className="sh-EventsViewToggle">
			<VisuallyHidden as="legend">
				{ __( 'Event list view', 'simple-history' ) }
			</VisuallyHidden>

			{ options.map( ( option ) => {
				const isChecked = view === option.value;

				return (
					<Tooltip key={ option.value } text={ option.label }>
						<label
							className={ clsx( 'sh-EventsViewToggle__option', {
								'is-checked': isChecked,
							} ) }
						>
							<input
								type="radio"
								name="sh-events-view"
								value={ option.value }
								checked={ isChecked }
								onChange={ () => onChange( option.value ) }
								aria-label={ option.label }
								className="sh-EventsViewToggle__input"
							/>
							<Icon icon={ option.icon } />
						</label>
					</Tooltip>
				);
			} ) }
		</fieldset>
	);
}
```

-   [ ] **Step 4: Toggle styles**

Append to `css/styles.css` directly after the `.sh-ControlBarButtons { … }` rule:

```css
/* Detailed | Compact view toggle at the end of the control bar (issue 240). */
.sh-EventsViewToggle {
	display: inline-flex;
	margin: 0 0 0 var( --sh-spacing-small );
	padding: 0 0 0 var( --sh-spacing-small );
	border: 0;
	border-left: 1px solid var( --sh-color-gray-4 );
}

.sh-EventsViewToggle__option {
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	border: 1px solid #949494;
	color: #1e1e1e;
	background: #fff;
	cursor: pointer;
}

.sh-EventsViewToggle__option + .sh-EventsViewToggle__option {
	border-left: 0;
}

.sh-EventsViewToggle__option:first-of-type {
	border-radius: 2px 0 0 2px;
}

.sh-EventsViewToggle__option:last-of-type {
	border-radius: 0 2px 2px 0;
}

.sh-EventsViewToggle__option.is-checked {
	background: #1e1e1e;
	color: #fff;
}

.sh-EventsViewToggle__option svg {
	fill: currentColor;
}

/* Visually hidden but still focusable, so arrow keys and screen readers work. */
.sh-EventsViewToggle__input {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	margin: 0;
	opacity: 0;
	cursor: pointer;
}

.sh-EventsViewToggle__option:has( .sh-EventsViewToggle__input:focus-visible ) {
	outline: 2px solid #2271b1;
	outline-offset: 1px;
	z-index: 1;
}
```

-   [ ] **Step 5: Render the toggle in `EventsControlBar.jsx`**

Add the import after the `CreateLogEntryButton` import:

```jsx
import { EventsViewToggle } from './EventsViewToggle';
```

Add the two props to the destructuring:

```jsx
const {
	eventsIsLoading,
	eventsTotal,
	eventsQueryParams,
	hasAnyActiveFilters,
	newEventsNotifier,
	eventsView,
	onEventsViewChange,
} = props;
```

Replace:

```jsx
						<ShareFilteredViewButton />
					</HStack>
```

with:

```jsx
						<ShareFilteredViewButton />

						<EventsViewToggle
							view={ eventsView }
							onChange={ onEventsViewChange }
						/>
					</HStack>
```

-   [ ] **Step 6: Pass the variant in `EventsListItemsList.jsx`**

Add `eventsView` to the destructured props:

```jsx
const {
	events,
	prevEventsMaxId,
	eventsIsLoading,
	surroundingEventId,
	eventsView,
} = props;
```

Add the variant to `<Event>`, right after `event={ event }`:

```jsx
					variant={ eventsView === 'compact' ? 'compact' : 'normal' }
```

-   [ ] **Step 7: Forward the prop in `EventsList.jsx`**

Add `eventsView,` as the last entry of the destructured props (after `selectedInitiator,`), and pass it on:

```jsx
<EventsListItemsList
	eventsIsLoading={ eventsIsLoading }
	events={ events }
	prevEventsMaxId={ prevEventsMaxId }
	surroundingEventId={ surroundingEventId }
	eventsView={ eventsView }
/>
```

-   [ ] **Step 8: State and saving in `EventsGui.jsx`**

Add `parseAsStringLiteral` to the nuqs import list (alphabetically after `parseAsString`).

After the `eventsAdminPageURL` `useState` (the block commented "Seeded from the value localized at enqueue time"), add:

```jsx
// The view the user last chose, localized at enqueue time from user meta
// so the first render already uses it. See REST_API::register_user_meta().
const [ storedEventsView, setStoredEventsView ] = useState(
	window.simpleHistoryReactData?.eventsView === 'compact'
		? 'compact'
		: 'detailed'
);
```

After the `surroundingCount` `useQueryState` block, add:

```jsx
// View in the URL wins over the stored one, so a shared link opens in the
// view it was copied from. Nothing is written to the URL on page load.
const [ urlEventsView, setUrlEventsView ] = useQueryState(
	'view',
	parseAsStringLiteral( [ 'detailed', 'compact' ] ).withOptions(
		useQueryStateOptions
	)
);

const eventsView = urlEventsView ?? storedEventsView;

const handleEventsViewChange = useCallback(
	( newView ) => {
		setUrlEventsView( newView );
		setStoredEventsView( newView );

		// Remember the choice for next time. A failed save is not worth
		// interrupting the user for; the toggle still works on this visit.
		apiFetch( {
			path: '/wp/v2/users/me',
			method: 'POST',
			data: { meta: { simple_history_events_view: newView } },
		} ).catch( () => {} );
	},
	[ setUrlEventsView ]
);
```

Pass the props to `<EventsControlBar>` (after `hasAnyActiveFilters={ hasAnyActiveFilters }`):

```jsx
eventsView = { eventsView };
onEventsViewChange = { handleEventsViewChange };
```

Pass the prop to `<EventsList>` (after `selectedInitiator={ selectedInitiator }`):

```jsx
eventsView = { eventsView };
```

-   [ ] **Step 9: Build**

Run: `npm run build`
Expected: `webpack compiled successfully`.

-   [ ] **Step 10: Run the spec to verify it passes**

Run: `npx playwright test --project=tests tests/playwright/events-view-toggle.spec.js`
Expected: PASS, 5 tests.

-   [ ] **Step 11: Run the existing log page and dashboard specs**

Run: `npx playwright test --project=tests tests/playwright/log-page.spec.js tests/playwright/dashboard-widget.spec.js tests/playwright/hide-event-type.spec.js`
Expected: PASS. A failure here is a regression from Task 2 or 3; fix before committing.

-   [ ] **Step 12: Lint JS**

Run: `npx wp-scripts lint-js src/components/EventsViewToggle.jsx src/components/EventsControlBar.jsx src/components/EventsListItemsList.jsx src/components/EventsList.jsx src/components/EventsGui.jsx tests/playwright/events-view-toggle.spec.js`
Expected: no errors.

-   [ ] **Step 13: Commit**

```bash
git add src/components/EventsViewToggle.jsx src/components/EventsControlBar.jsx src/components/EventsListItemsList.jsx src/components/EventsList.jsx src/components/EventsGui.jsx css/styles.css tests/playwright/events-view-toggle.spec.js
git commit -m "Let users switch the event log between detailed and compact

Icon toggle at the end of the control bar. The choice goes into the URL
so Share copies it, and is saved to the user's meta so the log opens in
the same view next time.

Claude-Session: https://claude.ai/code/session_01Ls7PzUNsQ6nx3v76yrnJhZ"
```

---

### Task 4: Manual verification and changelog

**Files:**

-   Modify: `readme.txt` (the `### Unreleased` → `**Added**` list)

-   [ ] **Step 1: Verify the local site is serving this working copy**

```bash
echo test > .probe.tmp
curl -s http://wordpress-stable-docker-mariadb.test:8282/wp-content/plugins/simple-history/.probe.tmp
rm .probe.tmp
```

Expected: `test`.

-   [ ] **Step 2: Check in the browser**

Log in to `http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_admin_menu_page` as `claude` / `claude` and confirm:

1. The toggle sits at the right end of the control bar, after Share, with a thin divider.
2. Compact rows: no details table, name without email, relative date, IP without label, ⋯ menu present, action links present.
3. Clicking a compact row's date opens the event modal.
4. "+ N similar events" expands and the similar events render compact.
5. At a window width of ~600px the control bar does not overflow the page.
6. The dashboard widget at `/wp-admin/` looks unchanged.
7. Toggling does not add a "Edited your profile" event to the log (run `docker compose run --rm wpcli_mariadb simple-history list` from `_docker-compose-to-run-on-system-boot/` and check the latest events).

Take a screenshot of the Compact view for the vault: `$SH_NOTES_DIR/Simple History/screenshots/240-compact-view.png`, then run `pngquant --quality=80-95 --strip --skip-if-larger --force --ext .png` and `oxipng -o max --strip safe` on it.

Reset the `claude` user's view to Detailed afterwards.

-   [ ] **Step 3: Add the changelog entry**

In `readme.txt`, add as the last bullet of the `**Added**` list under `### Unreleased`:

```
-   The event log has a compact view. Switch between detailed and compact with the buttons at the end of the bar above the events; the log remembers your choice, and a shared link opens in the view it was copied from.
```

-   [ ] **Step 4: Final checks**

Run: `npm run build`
Expected: `webpack compiled successfully`.

Run: `npm run php:lint`
Expected: no errors.

Run: `./vendor/bin/phpstan analyse --memory-limit=2G`
Expected: `[OK] No errors`.

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit EventsViewUserMetaTest`
Expected: PASS.

-   [ ] **Step 5: Commit**

```bash
git add readme.txt
git commit -m "Add the compact event log view to the changelog

Claude-Session: https://claude.ai/code/session_01Ls7PzUNsQ6nx3v76yrnJhZ"
```
