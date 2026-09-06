# WordPress Privacy export/erasure integration — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register Simple History as a WordPress personal-data exporter and eraser, and add a core "Privacy & Data" settings tab surfacing the integration.

**Architecture:** Two new `Service` subclasses under `inc/services/`. `Privacy_Data_Handler` registers the WP privacy exporter (always) and eraser (gated behind experimental features), and owns a shared "fetch this user's initiated events" query plus the PII scrub routine. `Privacy_Settings_Page` registers a settings tab under the General-settings parent and renders conditional info text. The existing `Privacy_Logger` is untouched.

**Tech Stack:** PHP 7.4+, WordPress privacy API (`wp_privacy_personal_data_exporters` / `wp_privacy_personal_data_erasers`), Simple History `Log_Query`, Codeception WPUnit tests, phpcs (WordPress standard), phpstan.

**Spec:** `docs/superpowers/specs/2026-05-29-wp-privacy-integration-design.md`

---

## File Structure

| File                                                    | Responsibility                                                                                                           |
| ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| `inc/services/class-privacy-data-handler.php` (create)  | Register exporter (always) + eraser (experimental-gated); shared user-event query; PII scrub routine; summary log event. |
| `inc/services/class-privacy-settings-page.php` (create) | Register "Privacy & Data" `Menu_Page` tab; render conditional Compliance info text.                                      |
| `inc/class-simple-history.php` (modify ~line 144-189)   | Add both new services to the `get_services()` array.                                                                     |
| `tests/wpunit/PrivacyDataHandlerTest.php` (create)      | WPUnit tests for the query helper, exporter, scrub routine, and eraser gating.                                           |
| `readme.txt` (modify)                                   | Changelog entry.                                                                                                         |

## Conventions worth knowing before starting

-   **Namespacing:** service classes live in namespace `Simple_History\Services`. Class name `Privacy_Data_Handler` → file `class-privacy-data-handler.php`.
-   **Logging an event:** `SimpleLogger()->info( $message, $context_array )` (global function). Returns the logger; `->last_insert_id` is the new event id.
-   **Querying events for a user:** `( new \Simple_History\Log_Query() )->query( [ 'user' => $user_id, 'posts_per_page' => N, 'paged' => N ] )`. Returns an array; `$result['log_rows']` is a list of row objects each with `->id`, `->logger`, `->level`, `->date`, `->context` (associative array of context keys).
-   **Plain-text message for a row:** `Simple_History::get_instance()->get_log_row_plain_text_output( $row )`.
-   **Contexts table name:** `Simple_History::get_instance()->get_contexts_table_name()`. Columns: `context_id`, `history_id`, `key`, `value`.
-   **Experimental flag:** `\Simple_History\Helpers::experimental_features_is_enabled()` returns bool (option `simple_history_experimental_features_enabled`, filter `simple_history/experimental_features_enabled`).
-   **Run a single WPUnit test:** `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest` (append `:methodName` for one method).
-   **Lint/analyse after PHP changes:** `npm run php:lint` and `npm run php:phpstan`.

### Decision resolved from spec

The spec's eraser table said IP keys are "re-masked to `0.0.0.x` via `Helpers::privacy_anonymize_ip()`". That helper preserves the network prefix (e.g. `192.168.1.55` → `192.168.1.x`), which is **not** a full erasure. For an erasure request we fully zero the address instead. **Implementation: set every IP context key to the literal `0.0.0.x`.** (The spec has been updated to match.)

---

## Task 1: Scaffold `Privacy_Data_Handler` service and register it

**Files:**

-   Create: `inc/services/class-privacy-data-handler.php`
-   Modify: `inc/class-simple-history.php` (the `get_services()` array, ~line 144-189)
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing test**

Create `tests/wpunit/PrivacyDataHandlerTest.php`:

```php
<?php

use Simple_History\Simple_History;
use Simple_History\Services\Privacy_Data_Handler;

/**
 * Tests for the WordPress privacy export/erasure integration.
 */
class PrivacyDataHandlerTest extends \Codeception\TestCase\WPTestCase {

	/**
	 * The service must be registered and loaded by Simple History.
	 */
	public function test_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );

		$this->assertInstanceOf(
			Privacy_Data_Handler::class,
			$service,
			'Privacy_Data_Handler should be loaded as a core service.'
		);
	}
}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest:test_service_is_loaded`
Expected: FAIL — class `Simple_History\Services\Privacy_Data_Handler` not found.

-   [ ] **Step 3: Create the service class skeleton**

Create `inc/services/class-privacy-data-handler.php`:

```php
<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Log_Query;

/**
 * Registers Simple History with WordPress's personal-data privacy tools
 * (Tools → Export/Erase Personal Data).
 *
 * The exporter is always registered. The eraser is gated behind experimental
 * features for one release cycle (see the design spec, "Release & lifecycle").
 *
 * @since 5.x
 */
class Privacy_Data_Handler extends Service {
	/**
	 * Privacy group / eraser id used by WordPress to bucket our data.
	 *
	 * @var string
	 */
	private const GROUP_ID = 'simple-history';

	/**
	 * Number of events processed per export/erase page.
	 *
	 * @var int
	 */
	private const PAGE_SIZE = 100;

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Exporter — always on. Read-only; zero behavioural risk.
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );

		// Eraser — gated behind experimental features for one release cycle.
		// When off, WordPress's erasure simply skips Simple History (the
		// pre-feature status quo); there is no half-built behaviour.
		if ( Helpers::experimental_features_is_enabled() ) {
			add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
		}
	}
}
```

-   [ ] **Step 4: Register the service**

In `inc/class-simple-history.php`, inside the `$services` array in `get_services()`, add the entry in alphabetical position (after `Services\Post_Row_Actions::class,`):

```php
			Services\Post_Row_Actions::class,
			Services\Privacy_Data_Handler::class,
			Services\REST_API::class,
```

-   [ ] **Step 5: Run test to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest:test_service_is_loaded`
Expected: PASS.

-   [ ] **Step 6: Commit**

```bash
git add inc/services/class-privacy-data-handler.php inc/class-simple-history.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Add Privacy_Data_Handler service skeleton and register it"
```

---

## Task 2: Shared "fetch user's initiated events" query

This private helper resolves an email to a user and returns one page of that user's initiated events. Both the exporter and eraser use it.

**Files:**

-   Modify: `inc/services/class-privacy-data-handler.php`
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing tests**

Add to `PrivacyDataHandlerTest.php`:

```php
	/**
	 * Helper: log an event as a specific user with explicit PII context.
	 *
	 * @param int    $user_id Initiator user id.
	 * @param string $message Event message.
	 * @return int New event id.
	 */
	private function log_event_as_user( $user_id, $message ) {
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			$message,
			[
				'_server_remote_addr'    => '203.0.113.45',
				'server_http_user_agent' => 'Mozilla/5.0 (TestAgent)',
			]
		);

		return $logger->last_insert_id;
	}

	/**
	 * Returns this user's events, newest-page logic aside.
	 */
	public function test_get_user_event_rows_returns_users_events() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'erika@example.com' ] );
		$this->log_event_as_user( $user_id, 'Erika did a thing' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, 'erika@example.com', 1 );

		$this->assertNotEmpty( $rows, 'Should return the user\'s events.' );
		$this->assertSame( (string) $user_id, (string) $rows[0]->context['_user_id'] );
	}

	/**
	 * Unknown email yields no rows.
	 */
	public function test_get_user_event_rows_unknown_email_returns_empty() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'get_user_event_rows' );
		$method->setAccessible( true );

		$rows = $method->invoke( $service, 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $rows, 'Unknown email should return an empty array.' );
	}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: FAIL — `get_user_event_rows` does not exist.

-   [ ] **Step 3: Implement the query helper**

Add these methods to `Privacy_Data_Handler`:

```php
	/**
	 * Resolve an email to a user and fetch one page of their initiated events.
	 *
	 * Events are matched by the `_user_id` context key (initiator-only scope).
	 * Uses `ungrouped` so every individual event is returned — without it,
	 * Log_Query collapses repeated events by occasion, which would exclude
	 * duplicates from export and leave their personal data un-scrubbed on
	 * erasure. Rows come back newest-first (Log_Query's default ordering).
	 *
	 * @param string $email_address Email address from the privacy request.
	 * @param int    $page          1-based page number.
	 * @return array<int,object> Array of Log_Query row objects (may be empty).
	 */
	private function get_user_event_rows( $email_address, $page ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user instanceof \WP_User ) {
			return [];
		}

		$query_result = ( new Log_Query() )->query(
			[
				'user'           => $user->ID,
				'posts_per_page' => self::PAGE_SIZE,
				'paged'          => max( 1, (int) $page ),
				'ungrouped'      => true,
			]
		);

		if ( empty( $query_result['log_rows'] ) || ! is_array( $query_result['log_rows'] ) ) {
			return [];
		}

		return $query_result['log_rows'];
	}
```

-   [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: PASS (all three methods so far).

-   [ ] **Step 5: Commit**

```bash
git add inc/services/class-privacy-data-handler.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Add shared user-event query helper to Privacy_Data_Handler"
```

---

## Task 3: The exporter

**Files:**

-   Modify: `inc/services/class-privacy-data-handler.php`
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing tests**

Add to `PrivacyDataHandlerTest.php`:

```php
	/**
	 * Registering the exporter adds our group to the exporters array.
	 */
	public function test_register_exporter_adds_group() {
		$service   = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$exporters = $service->register_exporter( [] );

		$this->assertArrayHasKey( 'simple-history', $exporters );
		$this->assertIsCallable( $exporters['simple-history']['callback'] );
	}

	/**
	 * Export returns the user's events with the expected fields and a done flag.
	 */
	public function test_export_user_data_returns_events() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'export@example.com' ] );
		$this->log_event_as_user( $user_id, 'Exportable event' );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( 'export@example.com', 1 );

		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['data'] );

		$first = $result['data'][0];
		$this->assertSame( 'simple-history', $first['group_id'] );
		$this->assertStringStartsWith( 'sh-event-', $first['item_id'] );

		$field_names = wp_list_pluck( $first['data'], 'name' );
		$this->assertContains( 'Date', $field_names );
		$this->assertContains( 'IP address', $field_names );
		$this->assertContains( 'User agent', $field_names );
	}

	/**
	 * Export for an unknown email is empty and done.
	 */
	public function test_export_user_data_unknown_email_is_done() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->export_user_data( 'nobody-' . uniqid() . '@example.com', 1 );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: FAIL — `register_exporter` / `export_user_data` do not exist.

-   [ ] **Step 3: Implement the exporter**

Add to `Privacy_Data_Handler`:

```php
	/**
	 * Register Simple History as a personal-data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['simple-history'] = [
			'exporter_friendly_name' => __( 'Simple History activity log', 'simple-history' ),
			'callback'               => [ $this, 'export_user_data' ],
		];

		return $exporters;
	}

	/**
	 * Export one page of the user's activity-log events.
	 *
	 * @param string $email_address Email from the privacy request.
	 * @param int    $page          1-based page number.
	 * @return array{data:array,done:bool}
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$rows = $this->get_user_event_rows( $email_address, $page );

		$export_items = [];

		foreach ( $rows as $row ) {
			$export_items[] = [
				'group_id'    => self::GROUP_ID,
				'group_label' => __( 'Simple History activity log', 'simple-history' ),
				'item_id'     => 'sh-event-' . $row->id,
				'data'        => $this->build_export_item_data( $row ),
			];
		}

		return [
			'data' => $export_items,
			'done' => count( $rows ) < self::PAGE_SIZE,
		];
	}

	/**
	 * Build the name/value field list for a single exported event.
	 *
	 * @param object $row Log_Query row object.
	 * @return array<int,array{name:string,value:string}>
	 */
	private function build_export_item_data( $row ) {
		$context = is_array( $row->context ) ? $row->context : [];

		$message = \Simple_History\Simple_History::get_instance()->get_log_row_plain_text_output( $row );

		return [
			[
				'name'  => __( 'Date', 'simple-history' ),
				'value' => $row->date,
			],
			[
				'name'  => __( 'Logger', 'simple-history' ),
				'value' => $row->logger,
			],
			[
				'name'  => __( 'Level', 'simple-history' ),
				'value' => $row->level,
			],
			[
				'name'  => __( 'Message', 'simple-history' ),
				'value' => wp_strip_all_tags( $message ),
			],
			[
				'name'  => __( 'IP address', 'simple-history' ),
				'value' => $context['_server_remote_addr'] ?? '',
			],
			[
				'name'  => __( 'User agent', 'simple-history' ),
				'value' => $context['server_http_user_agent'] ?? '',
			],
		];
	}
```

-   [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: PASS.

-   [ ] **Step 5: Commit**

```bash
git add inc/services/class-privacy-data-handler.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Implement WP personal-data exporter for Simple History events"
```

---

## Task 4: The PII scrub routine

A method that anonymizes the PII context keys for a single event id, in place, without deleting the event. Pure DB work — easy to test directly.

**Files:**

-   Modify: `inc/services/class-privacy-data-handler.php`
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing tests**

Add to `PrivacyDataHandlerTest.php`:

```php
	/**
	 * Helper: read an event's context as an associative array straight from the DB.
	 *
	 * @param int $history_id Event id.
	 * @return array<string,string>
	 */
	private function read_context( $history_id ) {
		global $wpdb;
		$table = Simple_History::get_instance()->get_contexts_table_name();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT `key`, value FROM {$table} WHERE history_id = %d", $history_id ),
			ARRAY_A
		);

		$out = [];
		foreach ( $rows as $r ) {
			$out[ $r['key'] ] = $r['value'];
		}
		return $out;
	}

	/**
	 * Scrubbing removes/masks PII keys but keeps the event and non-PII context.
	 */
	public function test_anonymize_event_scrubs_pii_keeps_rest() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'scrub@example.com' ] );
		wp_set_current_user( $user_id );

		$logger = SimpleLogger()->info(
			'Scrub me',
			[
				'_server_remote_addr'                  => '203.0.113.45',
				'_server_http_x_forwarded_for_0'       => '198.51.100.7',
				'server_http_user_agent'               => 'Mozilla/5.0 (TestAgent)',
				'_server_http_referer'                 => 'https://example.com/secret?token=abc',
				'object_subtype'                       => 'post',
			]
		);
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );

		// Identity removed / zeroed.
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertArrayNotHasKey( '_user_login', $context );
		$this->assertArrayNotHasKey( '_user_email', $context );
		$this->assertArrayNotHasKey( 'server_http_user_agent', $context );
		$this->assertArrayNotHasKey( '_server_http_referer', $context );

		// All IP keys fully anonymized.
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0.0.0.x', $context['_server_http_x_forwarded_for_0'] );

		// Non-PII context preserved.
		$this->assertSame( 'post', $context['object_subtype'] );

		// Event row itself still exists.
		global $wpdb;
		$events_table = Simple_History::get_instance()->get_events_table_name();
		$still_there  = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events_table} WHERE id = %d", $event_id ) );
		$this->assertSame( '1', (string) $still_there, 'Event row must NOT be deleted.' );
	}

	/**
	 * Running the scrub twice is a stable no-op.
	 */
	public function test_anonymize_event_is_idempotent() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'idem@example.com' ] );
		wp_set_current_user( $user_id );

		$logger   = SimpleLogger()->info( 'Idempotent', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$method  = new ReflectionMethod( $service, 'anonymize_event' );
		$method->setAccessible( true );

		$method->invoke( $service, $event_id );
		$method->invoke( $service, $event_id );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0', $context['_user_id'] );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
	}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: FAIL — `anonymize_event` does not exist.

-   [ ] **Step 3: Implement the scrub routine**

Add to `Privacy_Data_Handler`:

```php
	/**
	 * Anonymize all PII in a single event's context, in place.
	 *
	 * Removes login/email/user-agent/referer, zeroes the initiator user id,
	 * and fully anonymizes every stored IP-address key. The event row itself
	 * is preserved as an audit record. Idempotent.
	 *
	 * @param int $history_id Event id.
	 * @return void
	 */
	private function anonymize_event( $history_id ) {
		global $wpdb;

		$contexts_table = \Simple_History\Simple_History::get_instance()->get_contexts_table_name();

		// Initiator identity + device/network keys removed entirely. `_user_role`
		// is included because on small sites a role like "administrator" is
		// linkable to a specific person.
		$keys_to_remove = [ '_user_login', '_user_email', '_user_role', 'server_http_user_agent', '_server_http_referer' ];

		foreach ( $keys_to_remove as $key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $contexts_table, [ 'history_id' => $history_id, 'key' => $key ] );
		}

		// Initiator user id zeroed (kept as a key so the row stays well-formed).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $contexts_table, [ 'value' => '0' ], [ 'history_id' => $history_id, 'key' => '_user_id' ] );

		// Fully anonymize every stored IP key (main + proxy-header variants).
		$ip_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT `key` FROM {$contexts_table}
				 WHERE history_id = %d
				 AND ( `key` = %s OR `key` REGEXP %s )",
				$history_id,
				'_server_remote_addr',
				'^_server_http_.+_[0-9]+$'
			)
		);

		foreach ( $ip_keys as $ip_key ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update( $contexts_table, [ 'value' => '0.0.0.x' ], [ 'history_id' => $history_id, 'key' => $ip_key ] );
		}
	}
```

-   [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: PASS.

> **SQLite note:** `REGEXP` is supported by the SQLite integration plugin Simple History uses; if a future engine lacks it, the fallback is to fetch all `_server_*` keys and filter in PHP. Not needed now.

-   [ ] **Step 5: Commit**

```bash
git add inc/services/class-privacy-data-handler.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Add PII scrub routine for privacy erasure"
```

---

## Task 5: The eraser (experimental-gated) + summary log event

**Files:**

-   Modify: `inc/services/class-privacy-data-handler.php`
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing tests**

Add to `PrivacyDataHandlerTest.php`:

```php
	/**
	 * Registering the eraser adds our group with a callable.
	 */
	public function test_register_eraser_adds_group() {
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$erasers = $service->register_eraser( [] );

		$this->assertArrayHasKey( 'simple-history', $erasers );
		$this->assertIsCallable( $erasers['simple-history']['callback'] );
	}

	/**
	 * Erasing scrubs the user's events and reports the WP-shaped result.
	 */
	public function test_erase_user_data_scrubs_and_reports() {
		$user_id = $this->factory->user->create( [ 'role' => 'administrator', 'user_email' => 'erase@example.com' ] );
		wp_set_current_user( $user_id );
		$logger   = SimpleLogger()->info( 'Erase me', [ '_server_remote_addr' => '203.0.113.45' ] );
		$event_id = $logger->last_insert_id;

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$result  = $service->erase_user_data( 'erase@example.com', 1 );

		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
		$this->assertNotEmpty( $result['messages'] );

		$context = $this->read_context( $event_id );
		$this->assertSame( '0.0.0.x', $context['_server_remote_addr'] );
		$this->assertSame( '0', $context['_user_id'] );
	}

	/**
	 * The eraser is NOT registered when experimental features are off.
	 */
	public function test_eraser_not_registered_when_experimental_off() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );

		// Re-run loaded() to re-evaluate the gate with the filter applied.
		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
		$service->loaded();

		$this->assertFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must not be registered when experimental features are off.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
	}

	/**
	 * The eraser IS registered when experimental features are on.
	 */
	public function test_eraser_registered_when_experimental_on() {
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		$service = Simple_History::get_instance()->get_service( Privacy_Data_Handler::class );
		$service->loaded();

		$this->assertNotFalse(
			has_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] ),
			'Eraser must be registered when experimental features are on.'
		);

		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		remove_filter( 'wp_privacy_personal_data_erasers', [ $service, 'register_eraser' ] );
	}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: FAIL — `register_eraser` / `erase_user_data` do not exist.

-   [ ] **Step 3: Implement the eraser + summary log event**

Add to `Privacy_Data_Handler`:

```php
	/**
	 * Register Simple History as a personal-data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['simple-history'] = [
			'eraser_friendly_name' => __( 'Simple History activity log', 'simple-history' ),
			'callback'             => [ $this, 'erase_user_data' ],
		];

		return $erasers;
	}

	/**
	 * Anonymize one batch of the user's activity-log events.
	 *
	 * Scrubs PII while preserving the event rows as an audit record.
	 *
	 * Always fetches the FIRST page, ignoring the incoming `$page`: scrubbing
	 * zeroes each event's `_user_id`, so anonymized events drop out of the
	 * `user => ID` filter. Re-querying page 1 each call therefore walks through
	 * the remaining un-erased events. (Incrementing `$page` would skip events,
	 * because the result set shrinks under us between calls.)
	 *
	 * @param string $email_address Email from the privacy request.
	 * @param int    $page          1-based page number (unused; see above).
	 * @return array{items_removed:bool,items_retained:bool,messages:array,done:bool}
	 */
	public function erase_user_data( $email_address, $page = 1 ) {
		$rows = $this->get_user_event_rows( $email_address, 1 );

		foreach ( $rows as $row ) {
			$this->anonymize_event( $row->id );
		}

		$count = count( $rows );
		$done  = $count < self::PAGE_SIZE;

		$messages = [];

		if ( $count > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of activity-log entries anonymized. */
				_n(
					'Simple History anonymized the personal data in %d activity-log entry. The entry is retained as an audit record with personal data removed.',
					'Simple History anonymized the personal data in %d activity-log entries. The entries are retained as an audit record with personal data removed.',
					$count,
					'simple-history'
				),
				$count
			);
		}

		// On the final batch that actually scrubbed events, log a single summary
		// event (count only, no subject PII). Guarding on `$count > 0` avoids a
		// misleading breadcrumb on an empty erasure or the trailing empty page.
		if ( $done && $count > 0 ) {
			$this->log_erasure_summary( $email_address );
		}

		return [
			'items_removed'  => $count > 0,
			'items_retained' => $count > 0,
			'messages'       => $messages,
			'done'           => $done,
		];
	}

	/**
	 * Log a single summary event for an erasure request. Count only, no subject PII.
	 *
	 * @param string $email_address Email from the privacy request (not logged).
	 * @return void
	 */
	private function log_erasure_summary( $email_address ) {
		SimpleLogger()->info(
			'Anonymized personal data in Simple History for a privacy erasure request',
			[
				'_initiator' => Log_Initiators::WP_USER,
			]
		);
	}
```

`Log_Initiators::WP_USER` (value `'wp_user'`) is confirmed to exist in `inc/class-log-initiators.php`.

-   [ ] **Step 4: Add the `Log_Initiators` import**

At the top of `class-privacy-data-handler.php`, add to the existing `use` block (which currently has `Helpers` and `Log_Query`):

```php
use Simple_History\Log_Initiators;
```

-   [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: PASS (all methods).

-   [ ] **Step 6: Run phpstan and phpcs**

Run: `npm run php:phpstan && npm run php:lint`
Expected: No new errors for `class-privacy-data-handler.php`. Fix any reported issues (run `npm run php:lint-fix` for autofixable style).

-   [ ] **Step 7: Commit**

```bash
git add inc/services/class-privacy-data-handler.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Implement experimental-gated WP personal-data eraser"
```

---

## Task 6: "Privacy & Data" settings tab with conditional info text

**Files:**

-   Create: `inc/services/class-privacy-settings-page.php`
-   Modify: `inc/class-simple-history.php` (the `get_services()` array)
-   Test: `tests/wpunit/PrivacyDataHandlerTest.php`

-   [ ] **Step 1: Write the failing test**

Add to `PrivacyDataHandlerTest.php`:

```php
	/**
	 * The settings-page service is loaded.
	 */
	public function test_privacy_settings_page_service_is_loaded() {
		$service = Simple_History::get_instance()->get_service( \Simple_History\Services\Privacy_Settings_Page::class );

		$this->assertInstanceOf(
			\Simple_History\Services\Privacy_Settings_Page::class,
			$service,
			'Privacy_Settings_Page should be loaded as a core service.'
		);
	}

	/**
	 * The Compliance section output always mentions the export tool, and only
	 * mentions erasure when experimental features are enabled.
	 */
	public function test_compliance_section_text_is_conditional() {
		$service = Simple_History::get_instance()->get_service( \Simple_History\Services\Privacy_Settings_Page::class );

		// Experimental OFF — export mentioned, erasure not.
		add_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );
		ob_start();
		$service->render_compliance_section();
		$off = ob_get_clean();
		remove_filter( 'simple_history/experimental_features_enabled', '__return_false', 99 );

		$this->assertStringContainsString( 'export', strtolower( $off ) );
		$this->assertStringNotContainsString( 'erasure', strtolower( $off ) );

		// Experimental ON — erasure also mentioned.
		add_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );
		ob_start();
		$service->render_compliance_section();
		$on = ob_get_clean();
		remove_filter( 'simple_history/experimental_features_enabled', '__return_true', 99 );

		$this->assertStringContainsString( 'erasure', strtolower( $on ) );
	}
```

-   [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: FAIL — `Privacy_Settings_Page` not found.

-   [ ] **Step 3: Create the settings-page service**

Create `inc/services/class-privacy-settings-page.php`:

```php
<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;

/**
 * Settings tab for privacy and data-handling controls.
 *
 * In core this surfaces the always-on WordPress privacy integration. It is the
 * shared container that premium privacy features register additional
 * subsections into.
 *
 * @since 5.x
 */
class Privacy_Settings_Page extends Service {
	private const SETTINGS_PAGE_SLUG = 'simple_history_settings_menu_slug_privacy';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
		add_action( 'admin_menu', [ $this, 'register_and_add_settings' ] );
	}

	/**
	 * Add the "Privacy & Data" tab as a subtab of the main settings page.
	 */
	public function add_settings_menu_tab() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if the parent settings page does not exist (Stealth Mode, etc.).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		( new Menu_Page() )
			->set_page_title( __( 'Privacy & Data', 'simple-history' ) )
			->set_menu_title( __( 'Privacy & Data', 'simple-history' ) )
			->set_menu_slug( 'general_settings_subtab_privacy' )
			->set_callback( [ $this, 'settings_output' ] )
			->set_order( 45 ) // After Log Forwarding (40), before Licences (50).
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Register the Compliance settings section.
	 */
	public function register_and_add_settings() {
		Helpers::add_settings_section(
			'simple_history_settings_section_privacy_compliance',
			__( 'Compliance', 'simple-history' ),
			[ $this, 'render_compliance_section' ],
			self::SETTINGS_PAGE_SLUG
		);
	}

	/**
	 * Render the Compliance section intro. The erasure line only appears when
	 * experimental features are enabled, so the claim matches what is wired up.
	 */
	public function render_compliance_section() {
		?>
		<div class="sh-SettingsSectionIntroduction">
			<p>
				<?php esc_html_e( 'Simple History is registered with WordPress\'s personal-data export tool (Tools → Export Personal Data). When you process a request there, Simple History\'s activity log is included automatically.', 'simple-history' ); ?>
			</p>
			<?php if ( Helpers::experimental_features_is_enabled() ) : ?>
				<p>
					<?php esc_html_e( 'It is also registered with the erasure tool — running an erasure request anonymizes personal data in matching activity-log entries while preserving the audit record.', 'simple-history' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output the settings page wrapper.
	 */
	public function settings_output() {
		?>
		<div class="wrap sh-Page-content">
			<?php do_settings_sections( self::SETTINGS_PAGE_SLUG ); ?>
		</div>
		<?php
	}
}
```

-   [ ] **Step 4: Register the service**

In `inc/class-simple-history.php`, add directly after `Services\Privacy_Data_Handler::class,`:

```php
			Services\Privacy_Data_Handler::class,
			Services\Privacy_Settings_Page::class,
			Services\REST_API::class,
```

-   [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
Expected: PASS.

-   [ ] **Step 6: Run phpstan and phpcs**

Run: `npm run php:phpstan && npm run php:lint`
Expected: No new errors. Fix any reported issues.

-   [ ] **Step 7: Commit**

```bash
git add inc/services/class-privacy-settings-page.php inc/class-simple-history.php tests/wpunit/PrivacyDataHandlerTest.php
git commit -m "Add Privacy & Data settings tab with conditional compliance info text"
```

---

## Task 7: Manual verification, changelog, and release notes

**Files:**

-   Modify: `readme.txt`

-   [ ] **Step 1: Manual smoke test in the dev site**

1. Visit `/wp-admin/admin.php?page=simple_history_admin_menu_page` → **Settings → Privacy & Data**. Confirm the tab appears and the Compliance text shows the **export** line. Enable experimental features (Settings → Experimental) and confirm the **erasure** line appears.
2. Generate some events as a test user, then go to **Tools → Export Personal Data**, request an export for that user's email, and confirm a "Simple History activity log" group appears in the export with Date/Logger/Level/Message/IP/User agent fields.
3. With experimental features ON, go to **Tools → Erase Personal Data**, run an erasure for that user, and confirm: the result mentions Simple History anonymized N entries; viewing those events now shows masked IP / no user agent / "someone".

Record the outcome in `readme.<branch>.md` if one exists for this branch.

-   [ ] **Step 2: Add the changelog entry**

Use the **changelog** skill to add an entry to the `Unreleased` section of `readme.txt`. Suggested wording:

```
### Added
- Simple History now integrates with WordPress's personal-data **export** tool — a user's activity-log events are included when you run Tools → Export Personal Data. [#3]
- Experimental: integration with WordPress's personal-data **erasure** tool — running an erasure request anonymizes personal data in matching activity-log entries while keeping the audit record. Enable under Settings → Experimental features. [#3]
- New "Privacy & Data" settings tab describing the privacy-tool integration. [#3]
```

-   [ ] **Step 3: Commit**

```bash
git add readme.txt
git commit -m "Add changelog entry for WP privacy integration"
```

-   [ ] **Step 4: Create the build→evaluate follow-up issue (release task, not code)**

Per the project's experimental-feature lifecycle, create a local issue **"Evaluate experimental — WP Privacy eraser"** (`type: idea`, `status: 2-todo`), linking back to `[[3 - Add privacy-GDPR settings group]]`, revisit ~4–8 weeks after release. Evaluation criteria: did anyone run an erasure? any over/under-scrubbing reports? graduate to always-on, keep experimental, or adjust. Use the **local-issues** skill.

---

## Final verification

-   [ ] Run the full WPUnit suite for this test file: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyDataHandlerTest`
-   [ ] Run `npm run php:phpstan` — no new errors.
-   [ ] Run `npm run php:lint` — no new errors.
-   [ ] Confirm the manual smoke test (Task 7 Step 1) passed.
