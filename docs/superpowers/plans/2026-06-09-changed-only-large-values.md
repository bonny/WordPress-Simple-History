# Log large/structured setting values as "changed only" — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Log changes to large/structured settings (alert rules, destinations, preset settings, Message Control map) as "changed" with no before/after value, removing the unreadable JSON blob and the contexts-table bloat.

**Architecture:** Core's `Simple_History_Logger` gains a `simple_history/settings/changed_only_options` filter plus a non-scalar safety net. When an option is changed-only, the watcher records a flag instead of values, and the commit stores a single `{base}_new = "(changed)"` sentinel (no `_prev`, no serialized value). The existing generic renderer shows "Label: (changed)" with no code change. Premium registers its four structured keys via the new filter.

**Tech Stack:** PHP 7.4+, WordPress options/filters API, Codeception wpunit.

**Repositories:** Core (`/Users/bonnymacmini/Projects/WordPress-Simple-History`) holds the mechanism. Premium (`/Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium`) holds the filter registration. Commit core first, then premium.

**Branches:** Core branch `issue-233-changed-only-large-values` already exists (current). Create `issue-233-changed-only-large-values` in the Premium repo before Task 2.

**Spec:** `docs/superpowers/specs/2026-06-08-large-setting-values-in-log-design.md`

**Sentinel string:** `(changed)` (translatable).

---

## File Structure

**Core — modify:** `loggers/class-simple-history-logger.php`

-   Add `$changed_only_settings` cache property.
-   Add `get_changed_only_settings( $force_rebuild = false )` (filter, cached).
-   Add private `is_changed_only_setting( $option, $old, $new )`.
-   Update `on_tracked_option_updated`, `on_tracked_option_added`, `on_tracked_option_deleted` to record a `changed_only` flag.
-   Update `commit_settings_changes()` to emit the sentinel for changed-only entries.

**Core — test:** `tests/wpunit/SimpleHistorySettingsLoggerTest.php` (add cache flush to `tearDown`, add new tests).

**Core — docs:** `readme.txt` (changelog).

**Premium — modify:** `inc/class-extended-settings.php` (one `add_filter` in `init()` + one method).

**Premium — docs:** `readme.txt` (changelog).

---

## Task 1: Changed-only mechanism in core

**Files:**

-   Modify: `loggers/class-simple-history-logger.php`
-   Test: `tests/wpunit/SimpleHistorySettingsLoggerTest.php`

-   [ ] **Step 1: Add the tearDown cache flush, then write the failing tests**

In `tests/wpunit/SimpleHistorySettingsLoggerTest.php`, update `tearDown()` so it also flushes the new cache. The current `tearDown()` is:

```php
	public function tearDown(): void {
		// Flush the per-request tracked-settings cache so a filter added by
		// one test cannot leak into the next via the shared logger instance.
		$this->logger->get_tracked_settings( true );

		parent::tearDown();
	}
```

Change it to:

```php
	public function tearDown(): void {
		// Flush the per-request caches so a filter added by one test cannot
		// leak into the next via the shared logger instance.
		$this->logger->get_tracked_settings( true );
		$this->logger->get_redacted_settings( true );
		$this->logger->get_changed_only_settings( true );

		parent::tearDown();
	}
```

Then append these tests:

```php
	public function test_changed_only_filter_logs_sentinel_without_value() {
		$tracked_cb = static function ( $tracked ) {
			$tracked['sh_test_changed_only_scalar'] = 'Test changed-only scalar';
			return $tracked;
		};
		$changed_cb = static function ( $changed_only ) {
			$changed_only[] = 'sh_test_changed_only_scalar';
			return $changed_only;
		};
		add_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		add_filter( 'simple_history/settings/changed_only_options', $changed_cb );
		$this->logger->get_tracked_settings( true );
		$this->logger->get_changed_only_settings( true );

		add_option( 'sh_test_changed_only_scalar', 'old-value' );
		update_option( 'sh_test_changed_only_scalar', 'new-secret-detail' );
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		remove_filter( 'simple_history/settings/changed_only_options', $changed_cb );

		$context_map = [];
		foreach ( \Simple_History\tests\get_latest_context() as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertSame( '(changed)', $context_map['sh_test_changed_only_scalar_new'] );
		$this->assertArrayNotHasKey( 'sh_test_changed_only_scalar_prev', $context_map );
		$this->assertStringNotContainsString( 'new-secret-detail', wp_json_encode( $context_map ) );
	}

	public function test_non_scalar_value_logs_as_changed_only_safety_net() {
		$tracked_cb = static function ( $tracked ) {
			$tracked['sh_test_array_option'] = 'Test array option';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		$this->logger->get_tracked_settings( true );

		add_option( 'sh_test_array_option', [ 'unique_marker_aaa' => 1 ] );
		update_option( 'sh_test_array_option', [ 'unique_marker_bbb' => 2 ] );
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $tracked_cb );

		$context_map = [];
		foreach ( \Simple_History\tests\get_latest_context() as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertSame( '(changed)', $context_map['sh_test_array_option_new'] );
		$this->assertArrayNotHasKey( 'sh_test_array_option_prev', $context_map );
		$this->assertStringNotContainsString( 'unique_marker_bbb', wp_json_encode( $context_map ) );
	}

	public function test_changed_only_renders_sentinel_in_details() {
		$tracked_cb = static function ( $tracked ) {
			$tracked['sh_test_changed_only_render'] = 'Test changed-only render';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		$this->logger->get_tracked_settings( true );

		$row = (object) [
			'context_message_key' => 'modified_settings',
			'context'             => [
				'sh_test_changed_only_render_new' => '(changed)',
			],
		];

		$output = $this->logger->get_log_row_details_output( $row );
		$html   = (string) ( new \Simple_History\Event_Details\Event_Details_Container( $output, $row->context ) );

		remove_filter( 'simple_history/settings/tracked_options', $tracked_cb );

		$this->assertStringContainsString( 'Test changed-only render', $html );
		$this->assertStringContainsString( '(changed)', $html );
	}

	public function test_scalar_setting_still_logs_before_and_after() {
		$tracked_cb = static function ( $tracked ) {
			$tracked['sh_test_plain_scalar'] = 'Test plain scalar';
			return $tracked;
		};
		add_filter( 'simple_history/settings/tracked_options', $tracked_cb );
		$this->logger->get_tracked_settings( true );

		add_option( 'sh_test_plain_scalar', 'before' );
		update_option( 'sh_test_plain_scalar', 'after' );
		$this->logger->commit_settings_changes();

		remove_filter( 'simple_history/settings/tracked_options', $tracked_cb );

		$context_map = [];
		foreach ( \Simple_History\tests\get_latest_context() as $item ) {
			$context_map[ $item['key'] ] = $item['value'];
		}

		$this->assertSame( 'before', $context_map['sh_test_plain_scalar_prev'] );
		$this->assertSame( 'after', $context_map['sh_test_plain_scalar_new'] );
	}
```

-   [ ] **Step 2: Run the new tests, verify they FAIL**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Expected: FAIL — `get_changed_only_settings()` does not exist (fatal in tearDown / tests). `test_non_scalar_value_logs_as_changed_only_safety_net` would also fail because today non-scalars are JSON-encoded into `_prev`/`_new`.

-   [ ] **Step 3: Add the cache property**

In `loggers/class-simple-history-logger.php`, after the `$redacted_settings` property declaration (around line 23), add:

```php
	/** @var array<int,string>|null Cached list of changed-only option names. */
	private $changed_only_settings = null;
```

-   [ ] **Step 4: Add `get_changed_only_settings()` and `is_changed_only_setting()`**

Place `get_changed_only_settings()` immediately after the existing `get_redacted_settings()` method:

```php
	/**
	 * Get the list of tracked option names that are logged as "changed" without
	 * storing their before/after value (for large or structured settings).
	 *
	 * @param bool $force_rebuild Rebuild the cached list (used in tests).
	 * @return array<int,string>
	 */
	public function get_changed_only_settings( $force_rebuild = false ) {
		if ( $this->changed_only_settings !== null && ! $force_rebuild ) {
			return $this->changed_only_settings;
		}

		/**
		 * Filter the list of tracked option names logged as "changed" without
		 * storing their before/after value.
		 *
		 * Use this for large or structured settings (e.g. arrays of rules) whose
		 * raw value would be unreadable and bloat the log.
		 *
		 * @param array<int,string> $option_names List of option names.
		 */
		$this->changed_only_settings = apply_filters( 'simple_history/settings/changed_only_options', [] );

		return $this->changed_only_settings;
	}

	/**
	 * Whether an option should be logged as "changed" without its value.
	 *
	 * True when the option is explicitly registered as changed-only, or when
	 * either value is non-scalar (safety net so structured values are never
	 * serialized into the log).
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 * @return bool
	 */
	private function is_changed_only_setting( $option, $old_value, $new_value ) {
		if ( in_array( $option, $this->get_changed_only_settings(), true ) ) {
			return true;
		}

		return ! is_scalar( $old_value ) || ! is_scalar( $new_value );
	}
```

-   [ ] **Step 5: Update the three watcher handlers**

Replace `on_tracked_option_updated()` body so it short-circuits to a flag:

```php
	public function on_tracked_option_updated( $option, $old_value, $new_value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		if ( $this->is_changed_only_setting( $option, $old_value, $new_value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => $this->prepare_setting_value( $option, $old_value ),
			'new' => $this->prepare_setting_value( $option, $new_value ),
		];
	}
```

Replace `on_tracked_option_added()` body:

```php
	public function on_tracked_option_added( $option, $value ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		if ( $this->is_changed_only_setting( $option, $value, $value ) ) {
			$this->settings_changes[ $option ] = [ 'changed_only' => true ];

			return;
		}

		$this->settings_changes[ $option ] = [
			'old' => '',
			'new' => $this->prepare_setting_value( $option, $value ),
		];
	}
```

Replace `on_tracked_option_deleted()` body (keeps the `(deleted)` new value, but avoids storing a structured old value):

```php
	public function on_tracked_option_deleted( $option ) {
		if ( ! array_key_exists( $option, $this->get_tracked_settings() ) ) {
			return;
		}

		$old_value = array_key_exists( $option, $this->deleted_option_values )
			? $this->deleted_option_values[ $option ]
			: '';

		if ( $this->is_changed_only_setting( $option, $old_value, $old_value ) ) {
			$this->settings_changes[ $option ] = [
				'changed_only' => true,
				'new'          => __( '(deleted)', 'simple-history' ),
			];
		} else {
			$this->settings_changes[ $option ] = [
				'old' => $this->prepare_setting_value( $option, $old_value ),
				'new' => __( '(deleted)', 'simple-history' ),
			];
		}

		unset( $this->deleted_option_values[ $option ] );
	}
```

-   [ ] **Step 6: Update `commit_settings_changes()`**

Replace the `foreach` loop body so changed-only entries emit only the sentinel:

```php
		foreach ( $this->settings_changes as $option => $change ) {
			$base = $this->get_setting_context_base( $option );

			if ( ! empty( $change['changed_only'] ) ) {
				$context[ "{$base}_new" ] = isset( $change['new'] )
					? $change['new']
					: __( '(changed)', 'simple-history' );

				continue;
			}

			$context[ "{$base}_prev" ] = $change['old'];
			$context[ "{$base}_new" ]  = $change['new'];
		}
```

-   [ ] **Step 7: Run the tests, verify PASS**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
Expected: PASS — all prior tests plus the four new ones.

-   [ ] **Step 8: phpstan + phpcs**

Run: `npm run php:phpstan` (no new errors)
Run: `vendor/bin/phpcs loggers/class-simple-history-logger.php tests/wpunit/SimpleHistorySettingsLoggerTest.php` (clean; use full ternaries / null-coalesce, no short ternary)

-   [ ] **Step 9: Commit**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add loggers/class-simple-history-logger.php tests/wpunit/SimpleHistorySettingsLoggerTest.php
git commit -m "Log changed-only settings without storing values (issue 233)"
```

---

## Task 2: Register Premium's structured keys as changed-only

**Files:**

-   Modify: `inc/class-extended-settings.php` (premium repo)

-   [ ] **Step 1: Create the premium branch**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git checkout main
git checkout -b issue-233-changed-only-large-values
```

-   [ ] **Step 2: Register the filter and add the method**

In `inc/class-extended-settings.php`, in `init()`, right after the existing `add_filter( 'simple_history/settings/redacted_options', ... )` line, add:

```php
		add_filter( 'simple_history/settings/changed_only_options', array( $this, 'add_changed_only_settings' ) );
```

Then add this method immediately after the existing `add_redacted_settings()` method:

```php
	/**
	 * Mark Premium's structured settings to be logged as "changed" without
	 * storing their (large, nested-array) before/after value.
	 *
	 * @param array<int,string> $changed_only List of option names.
	 * @return array<int,string>
	 */
	public function add_changed_only_settings( $changed_only ) {
		return array_merge(
			$changed_only,
			[
				'shp_message_control',
				'simple_history_alert_destinations',
				'simple_history_alert_preset_settings',
				'simple_history_alert_custom_rules',
			]
		);
	}
```

-   [ ] **Step 3: Lint + syntax check**

Run the premium repo's phpcs on the file (same Docker phpcs command the repo uses, or `vendor/bin/phpcs inc/class-extended-settings.php`); run phpcbf first if needed. Must be clean.
Run: `php -l inc/class-extended-settings.php` (no syntax errors).

-   [ ] **Step 4: Manual verification (premium has no test infra)**

With both plugins active on the local site:

1. Edit and save an Alerts setting (custom rule or destination). Confirm the "Modified settings" event shows the alert setting as **"(changed)"** — not a JSON blob — and the contexts table holds the short sentinel, not the serialized array.
2. Toggle a logger in Message Control. Confirm `shp_message_control` logs as "(changed)".
3. Change a scalar Premium setting (e.g. "Days to keep log"). Confirm it still shows the real before → after.

Check with: `docker compose run --rm wpcli_mariadb simple-history list`

-   [ ] **Step 5: Commit**

```bash
cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git add inc/class-extended-settings.php
git commit -m "Log Premium's structured settings as changed-only (issue 233)"
```

---

## Task 3: Changelog entries

**Files:**

-   Modify: `readme.txt` (core)
-   Modify: `readme.txt` (premium)

-   [ ] **Step 1: Core changelog**

In core `readme.txt`, find the `### Unreleased` block (created during issue 232; if it was since released, add a new `### Unreleased` block above the latest version following the file's convention). Add under its **Changed** group (matching the file's bullet style):

> Large or structured settings (e.g. alert rules) are now logged as "changed" without storing their full value, keeping the log readable. Add-ons can opt keys in via the new `simple_history/settings/changed_only_options` filter.

-   [ ] **Step 2: Premium changelog**

In premium `readme.txt`, find the `### Unreleased` block (add one above the latest version if needed). Add under **Changed** (or the file's equivalent):

> Changes to structured settings (alerts, Message Control) are now recorded as "changed" rather than dumping their full value into the log.

-   [ ] **Step 3: Commit both**

```bash
cd /Users/bonnymacmini/Projects/WordPress-Simple-History
git add readme.txt
git commit -m "Add changelog entry for changed-only settings logging (issue 233)"

cd /Users/bonnymacmini/Projects/Simple-History-Add-Ons/simple-history-premium
git add readme.txt
git commit -m "Add changelog entry for changed-only structured settings (issue 233)"
```

---

## Final verification

-   [ ] Core wpunit passes: `docker compose run --rm php-cli vendor/bin/codecept run wpunit SimpleHistorySettingsLoggerTest`
-   [ ] phpstan clean: `npm run php:phpstan`
-   [ ] phpcs clean: `npm run php:lint`
-   [ ] Manual cross-plugin verification (Task 2 Step 4) done.
-   [ ] Update issue 233 to done with agent notes (local-issues skill); `review: pending`.

---

## Spec coverage check

-   Changed-only via explicit filter → Task 1 (`get_changed_only_settings` + filter), Task 2 (premium registration).
-   Non-scalar safety net → Task 1 (`is_changed_only_setting`).
-   Storage as single sentinel, no `_prev`, no serialized value → Task 1 (`commit_settings_changes`).
-   No renderer change; renders "Label: (changed)" → Task 1 (`test_changed_only_renders_sentinel_in_details`).
-   Deletion edge case → Task 1 (`on_tracked_option_deleted`).
-   Scalars unaffected → Task 1 (`test_scalar_setting_still_logs_before_and_after`).
-   Premium four structured keys → Task 2.
