# Log Simple History Premium's own settings changes — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Log changes to Simple History Premium's own settings out of the box, attributed uniformly to core's Simple History logger.

**Architecture:** Core's `Simple_History_Logger` watches a filterable map of "tracked option keys" via the global `updated_option`/`added_option`/`deleted_option` hooks, accumulates changes, and commits one `modified_settings` event on `shutdown`. Premium contributes its own option keys (with labels) through the `simple_history/settings/tracked_options` filter. A generic details renderer shows each changed setting as _label: old → new_.

**Tech Stack:** PHP 7.4+, WordPress plugin APIs (options API, `register_setting`, filters), Codeception wpunit + acceptance tests.

**Repositories:** Core (`/Users/bonnymacmini/Projects/WordPress-Simple-History`) holds the mechanism. Premium (`/Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium`) holds only the filter registration. Commit core first, then premium (see git-commits skill).

**Branch:** Create `issue-232-log-premium-settings-changes` in each repo before starting.

**Spec:** `docs/superpowers/specs/2026-06-07-log-premium-settings-changes-design.md`

**Design refinement vs spec:** The spec left open whether core auto-discovers core-group keys from `$wp_registered_settings` or uses an explicit list. This plan uses **explicit lists via the filter** (core seeds its own keys; Premium registers all of its keys including misc + stealth). Reason: `$wp_registered_settings`/`$new_allowed_options` is only populated after `admin_init`, so auto-discovery would silently miss keys on REST and front-end saves. Explicit registration is robust across all request types and makes the filter the single source of truth.

---

## File Structure

**Core — modify:**

-   `loggers/class-simple-history-logger.php` — replace the options.php-scoped settings watcher with the global watcher + shutdown commit; add the tracked-options map, the `simple_history/settings/tracked_options` and `simple_history/settings/redacted_options` filters, value preparation, and the generic details renderer. Keep the existing Channels "log forwarding" handling and all non-settings messages untouched.

**Core — test:**

-   `tests/wpunit/SimpleHistorySettingsLoggerTest.php` — new wpunit test for the watcher, filter, redaction, and details rendering.
-   `tests/acceptance/SimpleHistoryLoggerCest.php` — already asserts core settings saves log `modified_settings`; used to confirm no regression.

**Core — docs:**

-   `readme.txt` — changelog entry.

**Premium — modify:**

-   `inc/class-extended-settings.php` — register the tracked Premium option keys + labels via the filter (a small `add_filter` in `init()` plus a `get_tracked_settings()` method). No logging logic lives here.

**Premium — docs:**

-   `readme.txt` — changelog entry.

---

## Task 1: Tracked-options map + filters (core)

**Files:**

-   Modify: `loggers/class-simple-history-logger.php`
-   Test: `tests/wpunit/SimpleHistorySettingsLoggerTest.php`

-   [ ] **Step 1: Write the failing test**

Create `tests/wpunit/SimpleHistorySettingsLoggerTest.php`:

```php
<?php

require_once 'functions.php';

use Simple_History\Simple_History;
use Simple_History\Loggers\Simple_History_Logger;

/**
 * Test Simple History settings change logging.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest
 */
class SimpleHistorySettingsLoggerTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History_Logger */
	private $logger;

	public function setUp(): void {
		parent::setUp();

		$sh           = Simple_History::get_instance();
		$this->logger = $sh->get_instantiated_logger_by_slug( 'SimpleHistoryLogger' );

		$admin_user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_user_id );
	}

	public function test_core_keys_are_tracked() {
		$tracked = $this->logger->get_tracked_settings();

		$this->assertArrayHasKey( 'simple_history_show_on_dashboard', $tracked );
		$this->assertSame( 'Show on dashboard', $tracked['simple_history_show_on_dashboard'] );
	}

	public function test_filter_can_add_tracked_keys() {
		$callback = static function ( $tracked ) {
			$tracked['my_addon_option'] = 'My add-on option';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );

		// Rebuild (the map is cached per request; use a fresh logger lookup).
		$tracked = $this->logger->get_tracked_settings( true );

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$this->assertArrayHasKey( 'my_addon_option', $tracked );
		$this->assertSame( 'My add-on option', $tracked['my_addon_option'] );
	}
}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Expected: FAIL — `get_tracked_settings()` does not exist.

-   [ ] **Step 3: Add the tracked-options map and filters**

In `loggers/class-simple-history-logger.php`, add a property near the top of the class (next to `$arr_found_changes`):

```php
	/** @var array<string,string>|null Cached map of tracked option name => label. */
	private $tracked_settings = null;
```

Add these methods to the class (place them after `loaded()`):

```php
	/**
	 * Get the map of option keys that should be logged when changed.
	 *
	 * Keyed by full option name, value is a human-readable label.
	 * Add-ons contribute their own keys via the
	 * `simple_history/settings/tracked_options` filter.
	 *
	 * @param bool $force_rebuild Rebuild the cached map (used in tests).
	 * @return array<string,string>
	 */
	public function get_tracked_settings( $force_rebuild = false ) {
		if ( $this->tracked_settings !== null && ! $force_rebuild ) {
			return $this->tracked_settings;
		}

		$core_settings = [
			'simple_history_show_on_dashboard'     => __( 'Show on dashboard', 'simple-history' ),
			'simple_history_show_as_page'          => __( 'Show as a page', 'simple-history' ),
			'simple_history_pager_size'            => __( 'Items on page', 'simple-history' ),
			'simple_history_pager_size_dashboard'  => __( 'Items on dashboard', 'simple-history' ),
			'simple_history_enable_rss_feed'       => __( 'RSS feed enabled', 'simple-history' ),
			'simple_history_detective_mode_enabled' => __( 'Detective Mode enabled', 'simple-history' ),
			'simple_history_menu_page_location'    => __( 'Menu page location', 'simple-history' ),
			'simple_history_show_in_admin_bar'     => __( 'Show in admin bar', 'simple-history' ),
		];

		/**
		 * Filter the map of option keys that Simple History logs when changed.
		 *
		 * Add-ons use this to have their own settings logged as
		 * "Modified settings" via the Simple History logger.
		 *
		 * @param array<string,string> $settings Map of option name => human label.
		 */
		$this->tracked_settings = apply_filters( 'simple_history/settings/tracked_options', $core_settings );

		return $this->tracked_settings;
	}

	/**
	 * Get the list of tracked option names whose values must not be stored
	 * in the log (e.g. secrets/API keys). Their change is logged, but the
	 * value is replaced with a placeholder.
	 *
	 * @return array<int,string>
	 */
	public function get_redacted_settings() {
		/**
		 * Filter the list of tracked option names whose values are redacted in the log.
		 *
		 * @param array<int,string> $option_names List of option names to redact.
		 */
		return apply_filters( 'simple_history/settings/redacted_options', [] );
	}
```

-   [ ] **Step 4: Run test to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Expected: PASS (2 tests).

-   [ ] **Step 5: Commit**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add loggers/class-simple-history-logger.php tests/wpunit/SimpleHistorySettingsLoggerTest.php
git commit -m "Add tracked-options map and filters to Simple History logger (issue 232)"
```

---

## Task 2: Global option watcher + shutdown commit (core)

Replaces the options.php-scoped core-settings watcher with a global, save-mechanism-agnostic watcher. The Channels "log forwarding" handling and all non-settings messages are preserved.

**Files:**

-   Modify: `loggers/class-simple-history-logger.php`
-   Test: `tests/wpunit/SimpleHistorySettingsLoggerTest.php`

-   [ ] **Step 1: Write the failing test**

Add to `tests/wpunit/SimpleHistorySettingsLoggerTest.php`:

```php
	public function test_logs_tracked_option_change_on_commit() {
		$callback = static function ( $tracked ) {
			$tracked['sh_test_tracked_option'] = 'Test tracked option';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );
		$this->logger->get_tracked_settings( true );

		add_option( 'sh_test_tracked_option', 'old-value' );
		update_option( 'sh_test_tracked_option', 'new-value' );

		// Commit is normally on 'shutdown'; call directly in the test.
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$row = \Simple_History\tests\get_latest_row();
		$this->assertSame( 'SimpleHistoryLogger', $row['logger'], 'Event should be logged by the Simple History logger' );

		$context = \Simple_History\tests\get_latest_context();
		$context_map = [];
		foreach ( $context as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertArrayHasKey( '_message_key', $context_map );
		$this->assertSame( 'modified_settings', $context_map['_message_key'] );
		$this->assertSame( 'old-value', $context_map['sh_test_tracked_option_prev'] );
		$this->assertSame( 'new-value', $context_map['sh_test_tracked_option_new'] );
	}

	public function test_does_not_log_untracked_option_change() {
		update_option( 'some_unrelated_option', 'whatever' );
		$this->logger->commit_settings_changes();

		$context = \Simple_History\tests\get_latest_context();
		$context_map = [];
		foreach ( $context as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		// No tracked change accumulated, so the latest event must not be a settings change for this option.
		$this->assertArrayNotHasKey( 'some_unrelated_option_new', $context_map );
	}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest:test_logs_tracked_option_change_on_commit`
Expected: FAIL — `commit_settings_changes()` does not exist.

-   [ ] **Step 3: Replace the old watcher with the global watcher**

In `loggers/class-simple-history-logger.php`:

(a) Replace the `$arr_found_changes` property doc/declaration with a keyed buffer:

```php
	/** @var array<string,array{old:mixed,new:mixed}> Accumulated settings changes, keyed by option name. */
	private $settings_changes = [];
```

(b) In `loaded()`, remove the `load-options.php` line that drove core-settings capture and add the global watcher + shutdown commit. Keep the Channels handling by retaining `on_load_options_page` but trimming it (next sub-step). New `loaded()`:

```php
	public function loaded() {
		add_action( 'load-options.php', [ $this, 'on_load_options_page' ] );
		add_action( 'simple_history/rss_feed/secret_updated', [ $this, 'on_rss_feed_secret_updated' ] );
		add_action( 'simple_history/settings/log_cleared', [ $this, 'on_log_cleared' ] );
		add_action( 'simple_history/db/purge_done', [ $this, 'on_purge_done' ], 10, 2 );
		add_action( 'simple_history/backfill/completed', [ $this, 'on_backfill_completed' ] );
		add_action( 'simple_history/channel/auto_disabled', [ $this, 'on_channel_auto_disabled' ], 10, 2 );

		// Watch tracked settings (core + add-ons) across every save mechanism.
		add_action( 'updated_option', [ $this, 'on_tracked_option_updated' ], 10, 3 );
		add_action( 'added_option', [ $this, 'on_tracked_option_added' ], 10, 2 );
		add_action( 'deleted_option', [ $this, 'on_tracked_option_deleted' ], 10, 1 );
		add_action( 'shutdown', [ $this, 'commit_settings_changes' ] );
	}
```

(c) Trim `on_load_options_page` to handle only the Channels (log forwarding) option group — remove the core general-settings branch and its `updated_option`/`wp_redirect` wiring:

```php
	public function on_load_options_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['option_page'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$option_page = sanitize_text_field( wp_unslash( $_POST['option_page'] ) );

		if ( $option_page === Channels_Settings_Page::SETTINGS_OPTION_GROUP ) {
			add_filter( 'wp_redirect', [ $this, 'log_forwarding_settings_saved' ], 10, 2 );
		}
	}
```

(d) Delete the now-unused methods `on_updated_option()` and `commit_log_on_wp_redirect()`.

(e) Add the new watcher + commit methods (place after `log_forwarding_settings_saved`):

```php
	/**
	 * Record a changed tracked option.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return void
	 */
	public function on_tracked_option_updated( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => $this->prepare_setting_value( $option, $old_value ),
			'new' => $this->prepare_setting_value( $option, $new_value ),
		];
	}

	/**
	 * Record a newly added tracked option.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New value.
	 * @return void
	 */
	public function on_tracked_option_added( $option, $value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => '',
			'new' => $this->prepare_setting_value( $option, $value ),
		];
	}

	/**
	 * Record a deleted tracked option.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public function on_tracked_option_deleted( $option ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => '',
			'new' => '',
		];
	}

	/**
	 * Prepare an option value for storage in the log.
	 *
	 * Redacts sensitive options and stringifies non-scalar values.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Raw value.
	 * @return mixed
	 */
	private function prepare_setting_value( $option, $value ) {
		if ( in_array( $option, $this->get_redacted_settings(), true ) ) {
			return __( '(value hidden)', 'simple-history' );
		}

		if ( is_scalar( $value ) || $value === null ) {
			return $value;
		}

		return wp_json_encode( $value );
	}

	/**
	 * Commit all accumulated settings changes as one event.
	 *
	 * Hooked to `shutdown` so it runs regardless of how the save happened
	 * (Settings API, direct update_option, or REST).
	 *
	 * @return void
	 */
	public function commit_settings_changes() {
		if ( count( $this->settings_changes ) === 0 ) {
			return;
		}

		$context = [];

		foreach ( $this->settings_changes as $option => $change ) {
			$base = $this->get_setting_context_base( $option );

			$context[ "{$base}_prev" ] = $change['old'];
			$context[ "{$base}_new" ]  = $change['new'];
		}

		$this->info_message( 'modified_settings', $context );

		$this->settings_changes = [];
	}

	/**
	 * Get the context-key base for an option name.
	 *
	 * Strips the `simple_history_` prefix so core keys keep their historical
	 * short context names (e.g. `show_on_dashboard`).
	 *
	 * @param string $option Option name.
	 * @return string
	 */
	private function get_setting_context_base( $option ) {
		return preg_replace( '/^simple_history_/', '', $option );
	}
```

-   [ ] **Step 4: Run the new wpunit tests**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Expected: PASS (all tests, including the two new ones).

-   [ ] **Step 5: Run the acceptance test to confirm no regression**

Run: `docker compose run --rm php-cli vendor/bin/codecept run acceptance SimpleHistoryLoggerCest`
Expected: PASS — `it_can_log_show_history` and `it_can_enable_rss_feed` still log `modified_settings` with the same context keys (`show_on_dashboard_prev`, `enable_rss_feed_new`, etc.). If acceptance can't run in this environment, manually save the core settings page and confirm a single "Modified settings" event with the changed keys.

-   [ ] **Step 6: Run phpstan**

Run: `npm run php:phpstan`
Expected: No new errors in `class-simple-history-logger.php`.

-   [ ] **Step 7: Commit**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add loggers/class-simple-history-logger.php tests/wpunit/SimpleHistorySettingsLoggerTest.php
git commit -m "Watch tracked settings globally and commit on shutdown (issue 232)"
```

---

## Task 3: Generic details renderer (core)

Make the event details show every changed tracked key, including add-on keys, instead of only a hardcoded core list.

**Files:**

-   Modify: `loggers/class-simple-history-logger.php`
-   Test: `tests/wpunit/SimpleHistorySettingsLoggerTest.php`

-   [ ] **Step 1: Write the failing test**

Add to `tests/wpunit/SimpleHistorySettingsLoggerTest.php`:

```php
	public function test_details_output_renders_tracked_key_with_label() {
		$callback = static function ( $tracked ) {
			$tracked['shp_message_control'] = 'Message Control settings';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $callback );
		$this->logger->get_tracked_settings( true );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'shp_message_control_prev' => 'a',
				'shp_message_control_new'  => 'b',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) $output;

		remove_filter( 'simple_history/settings/tracked_options', $callback );

		$this->assertStringContainsString( 'Message Control settings', $html );
	}
```

-   [ ] **Step 2: Run test to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest:test_details_output_renders_tracked_key_with_label`
Expected: FAIL — the hardcoded renderer does not include `shp_message_control`, so the label is absent.

-   [ ] **Step 3: Replace the hardcoded item list with a generic renderer**

In `get_log_row_details_output()`, keep the `purged_events` branch exactly as-is. Replace the final `return ( new Event_Details_Group() )->add_items( [ ... fixed items ... ] )->set_title( ... );` block with:

```php
		$context = isset( $row->context ) && is_array( $row->context ) ? $row->context : [];

		// Build a base => label lookup from the tracked-options map.
		$labels = [];
		foreach ( $this->get_tracked_settings() as $option => $label ) {
			$labels[ $this->get_setting_context_base( $option ) ] = $label;
		}

		$group       = new Event_Details_Group();
		$items       = [];
		$bases_added = [];

		foreach ( $context as $key => $value ) {
			if ( substr( $key, -4 ) === '_new' ) {
				$base = substr( $key, 0, -4 );
			} elseif ( substr( $key, -5 ) === '_prev' ) {
				$base = substr( $key, 0, -5 );
			} else {
				continue;
			}

			if ( isset( $bases_added[ $base ] ) ) {
				continue;
			}

			$bases_added[ $base ] = true;

			$label   = $labels[ $base ] ?? $base;
			$items[] = new Event_Details_Item( [ $base ], $label );
		}

		$group->add_items( $items );
		$group->set_title( __( 'Changed items', 'simple-history' ) );

		return $group;
```

-   [ ] **Step 4: Run the test to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest:test_details_output_renders_tracked_key_with_label`
Expected: PASS.

-   [ ] **Step 5: Run the full settings test file + phpstan**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Run: `npm run php:phpstan`
Expected: All PASS; no new phpstan errors.

-   [ ] **Step 6: Commit**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add loggers/class-simple-history-logger.php tests/wpunit/SimpleHistorySettingsLoggerTest.php
git commit -m "Render settings-change details generically from tracked options (issue 232)"
```

---

## Task 4: Verify redaction of sensitive values (core)

The redaction path was added in Task 2; lock it with a test.

**Files:**

-   Test: `tests/wpunit/SimpleHistorySettingsLoggerTest.php`

-   [ ] **Step 1: Write the failing test**

Add to `tests/wpunit/SimpleHistorySettingsLoggerTest.php`:

```php
	public function test_redacted_option_value_is_hidden() {
		$tracked_cb = static function ( $tracked ) {
			$tracked['sh_test_secret_option'] = 'Test secret';
			return $tracked;
		};
		$redact_cb = static function ( $redacted ) {
			$redacted[] = 'sh_test_secret_option';
			return $redacted;
		};
		add_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		add_filter( 'simple_history/settings/redacted_options', $redact_cb );
		$this->logger->get_tracked_settings( true );

		add_option( 'sh_test_secret_option', 'old-secret' );
		update_option( 'sh_test_secret_option', 'new-secret' );
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		remove_filter( 'simple_history/settings/redacted_options', $redact_cb );

		$context = \Simple_History\tests\get_latest_context();
		$context_map = [];
		foreach ( $context as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertSame( '(value hidden)', $context_map['sh_test_secret_option_new'] );
		$this->assertStringNotContainsString( 'new-secret', wp_json_encode( $context_map ) );
	}
```

-   [ ] **Step 2: Run the test**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest:test_redacted_option_value_is_hidden`
Expected: PASS (the redaction code already exists from Task 2). If it fails, fix `prepare_setting_value()`.

-   [ ] **Step 3: Commit**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add tests/wpunit/SimpleHistorySettingsLoggerTest.php
git commit -m "Cover redaction of sensitive tracked option values (issue 232)"
```

---

## Task 5: Register Premium option keys via the filter (premium)

Premium contributes all of its own settings keys (with labels) and marks the Google Maps API key as redacted. No logging logic lives in Premium.

**Files:**

-   Modify: `inc/class-extended-settings.php` (premium repo)
-   Docs: `readme.txt` (premium repo)

-   [ ] **Step 1: Add the filter registration**

In `/Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium/inc/class-extended-settings.php`, in the `init()` method (near the other `add_action`/`add_filter` calls), add:

```php
		add_filter( 'simple_history/settings/tracked_options', [ $this, 'add_tracked_settings' ] );
		add_filter( 'simple_history/settings/redacted_options', [ $this, 'add_redacted_settings' ] );
```

Then add these two methods to the class:

```php
	/**
	 * Register Premium's own settings so changes to them are logged via the
	 * Simple History logger as "Modified settings".
	 *
	 * @param array<string,string> $settings Map of option name => label.
	 * @return array<string,string>
	 */
	public function add_tracked_settings( $settings ) {
		$premium_settings = [
			// Misc settings.
			'shp_days_to_keep_type'          => __( 'Days to keep log (type)', 'simple-history-add-on' ),
			'shp_days_to_keep_log'           => __( 'Days to keep log', 'simple-history-add-on' ),
			'shp_store_full_ip_address'      => __( 'Store full IP address', 'simple-history-add-on' ),
			'shp_google_maps_api_key'        => __( 'Google Maps API key', 'simple-history-add-on' ),
			'shp_enable_post_activity_panel' => __( 'Post activity panel enabled', 'simple-history-add-on' ),

			// Stealth mode.
			'shp_stealth_mode_enabled'         => __( 'Stealth mode enabled', 'simple-history-add-on' ),
			'shp_stealth_mode_email_addresses' => __( 'Stealth mode email addresses', 'simple-history-add-on' ),

			// Failed login attempts.
			'sh_existing_users_failed_login_attempts'       => __( 'Failed login logging (existing users)', 'simple-history-add-on' ),
			'sh_existing_users_failed_login_attempts_count' => __( 'Failed login attempts count (existing users)', 'simple-history-add-on' ),
			'sh_unknown_users_failed_login_attempts'        => __( 'Failed login logging (unknown users)', 'simple-history-add-on' ),
			'sh_unknown_users_failed_login_attempts_count'  => __( 'Failed login attempts count (unknown users)', 'simple-history-add-on' ),
			'sh_combine_consecutive_attempts'               => __( 'Combine consecutive failed attempts', 'simple-history-add-on' ),

			// Message control.
			'shp_message_control' => __( 'Message Control settings', 'simple-history-add-on' ),

			// Alerts.
			'simple_history_alert_destinations'    => __( 'Alert destinations', 'simple-history-add-on' ),
			'simple_history_alert_preset_settings' => __( 'Alert preset settings', 'simple-history-add-on' ),
			'simple_history_alert_custom_rules'    => __( 'Alert custom rules', 'simple-history-add-on' ),
			'simple_history_alert_tracking'        => __( 'Alert tracking', 'simple-history-add-on' ),
		];

		return array_merge( $settings, $premium_settings );
	}

	/**
	 * Mark Premium option values that must not be stored in the log.
	 *
	 * @param array<int,string> $redacted List of option names.
	 * @return array<int,string>
	 */
	public function add_redacted_settings( $redacted ) {
		$redacted[] = 'shp_google_maps_api_key';

		return $redacted;
	}
```

> Note: confirm the `init()` method (or the appropriate setup method) is where other hooks are registered; from inspection `Extended_Settings::init()` already calls `add_action(...)`. If `init()` runs before the core logger's `loaded()`, the filters still apply because the core logger reads the map lazily (on first option change / at shutdown), not at registration time.

-   [ ] **Step 2: Manual verification (no automated test infra in premium)**

With both plugins active on the local site (`http://wordpress-stable-docker-mariadb.test:8282/wp-admin/`):

1. Change a **Settings API own-group** setting — Failed Login Attempts settings — and save. Confirm a single "Modified settings" event (via "Using plugin Simple History") with the changed key and old → new.
2. Change a **direct `update_option`** setting — Message Control — and save. Confirm one "Modified settings" event.
3. Change an **Alerts** setting (REST/direct save). Confirm one "Modified settings" event.
4. Change a **core-group Premium** setting — e.g. "Days to keep log" — together with a core setting on the main settings page; save once. Confirm a single "Modified settings" event listing both keys (no duplicate event).
5. Change the **Google Maps API key**. Confirm the event shows it changed but the value reads "(value hidden)".

Check events with WP-CLI:

```bash
docker compose run --rm wpcli_mariadb simple-history list
```

-   [ ] **Step 3: Commit**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git add inc/class-extended-settings.php
git commit -m "Log Premium's own settings changes via Simple History logger (issue 232)"
```

---

## Task 6: Changelog entries (core + premium)

**Files:**

-   Modify: `readme.txt` (core)
-   Modify: `readme.txt` (premium)

-   [ ] **Step 1: Add the core changelog entry**

Use the **changelog** skill. Add to the Unreleased/Added section of core `readme.txt`:

> Add-ons can now register their own settings to be logged as "Modified settings" via the new `simple_history/settings/tracked_options` filter. Settings changes are now detected across all save mechanisms (Settings API, direct option updates, and REST).

-   [ ] **Step 2: Add the premium changelog entry**

Add to the Unreleased section of premium `readme.txt`:

> Simple History Premium now logs changes to its own settings (Failed Login Attempts, Message Control, Alerts, retention, stealth mode, and more) out of the box, shown as "Modified settings" via Simple History.

-   [ ] **Step 3: Format and commit**

Run the **markdown-formatting** skill if a markdown file was touched (readme.txt is not markdown — skip). Commit each repo:

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add readme.txt
git commit -m "Add changelog entry for tracked settings logging (issue 232)"

cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git add readme.txt
git commit -m "Add changelog entry for logging Premium's own settings (issue 232)"
```

---

## Final verification

-   [ ] Core wpunit suite for the new test passes: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
-   [ ] Core acceptance settings test passes (or manual equivalent): `docker compose run --rm php-cli vendor/bin/codecept run acceptance SimpleHistoryLoggerCest`
-   [ ] phpstan clean: `npm run php:phpstan`
-   [ ] phpcs clean: `npm run php:lint`
-   [ ] Manual cross-plugin verification from Task 5 Step 2 completed.
-   [ ] Update issue 232 status to done with agent notes (local-issues skill); set `review: pending`.

---

## Spec coverage check

-   "Watch Premium's own option keys out of the box" → Task 5 (filter registration), Task 2 (watcher).
-   "Log via the Simple History logger (not Extended Settings)" → Tasks 2–3 reuse core `Simple_History_Logger` / `modified_settings`; no Premium logger or via label.
-   "Cover Failed Login, Message Control, and other Premium options pages" → Task 5 key list.
-   "Generic mechanism: a maintained list of Premium option keys" → Task 1 filter + Task 5 list.
-   All save mechanisms (Settings API, direct update_option, REST) → Task 2 global hooks + shutdown commit.
-   Generic details rendering for Premium keys → Task 3.
-   Sensitive value handling → Tasks 1/2 (redacted filter) + Task 4 (test) + Task 5 (Google Maps key).
-   No double-logging → Task 2 (single accumulation path replaces the old core-group flow).
