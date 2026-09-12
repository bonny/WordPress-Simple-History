# License Status From Update Checks Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Core stores the add-on license's real status and expiry from every premium update check and shows it on the Licenses tab, so "renews on", "expired on" and a trustworthy lapsed-license signal exist without any premium change.

**Architecture:** simple-history.com's update endpoint (`/wp-json/lsq/v1/update`) now appends a `license` object to both its 200 and its 401 responses (local issue 315, shared eskapism plugin). `Plugin_Updater::request()` in core passes that object to a new `AddOn_Plugin::update_license_status_from_response()`, which merges it into the per-add-on license option that already holds the activation-time snapshot. A new `AddOn_Plugin::get_license_state()` reads that option and derives one state (`none`, `active`, `expired`, `disabled`, `invalid`), falling back to today's activation-time expiry when no check has happened yet. The Licenses tab renders that state.

**Tech Stack:** PHP 7.4+, WordPress 6.3+, wpunit (Codeception) tests run through Docker, phpcs + phpstan.

**Spec:** Local Obsidian issues `316 - Core stores license status and expiry from update checks` (this plan) and `315 - Update endpoint returns license status and expiry` (server side, already implemented, awaiting deploy). Parent: `15 - Whats New Section`.

## Global Constraints

-   PHP 7.4 compatible. No `match`, no named arguments, no readonly, no enums, no `str_contains`.
-   **Backwards compatible in every direction:**
    -   A new core against the **old** endpoint (no `license` key) must behave exactly as today: no option writes, no errors.
    -   An **old** core against the new endpoint already ignores the extra key. Do not change the 200 response's existing keys or the 401 status code on the server.
    -   Existing stored license options **lack** every new key. Every reader uses `??` / `isset`; never assume a key exists.
    -   `Plugin_Updater::request()` keeps its public return contract: decoded object on success, `false` otherwise. `site_transient_update_plugins_update()` keeps the `$remote->success` check.
    -   Premium calls no new core method. Verify with `npm run addons:check` before committing. Do not bump `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION`.
-   Text domain `simple-history`. Prefix `sh`/`simple_history`. WordPress Coding Standards, comments above code, blank lines before `if` and `return`.
-   Never store customer name/email from the update check (the server does not send them; do not add them).
-   `expires_at` **null with status active means lifetime**, not unknown. `license` **null/missing means could not check**: keep the previous stored state.
-   Branch: `worktree-issue-316-license-status` (worktree `.claude/worktrees/issue-316-license-status`, instance http://issue-316-license-status.test:9403). Branch notes in `readme.issue-316.md`. Commit only when Pär says so.
-   Server prerequisite (issue 315) must be deployed before end-to-end verification. All automated tests here fake the HTTP layer with `pre_http_request`, so they run without the server.

## Response contract (verified live 2026-09-06, issue 315)

```json
{
	"success": true,
	"error": "",
	"error_code": "",
	"update": { "version": "1.16.0", "download_link": "…" },
	"license": {
		"valid": true,
		"status": "active",
		"expires_at": null,
		"created_at": "2023-08-22T10:51:34.000000Z",
		"activation_limit": 1,
		"activation_usage": 1,
		"product_name": "Simple History Premium",
		"variant_name": "1 site",
		"error": "",
		"checked_at": "2026-09-06T13:32:28Z"
	}
}
```

-   HTTP 200: `success: true`, `license.status` is `active` or `inactive`.
-   HTTP 401: `success: false`, `error_code: "invalid_license_key"`, `license.status` is `expired` or `disabled` (full object, real `expires_at`), or `license.status: null` with `license.error: "license_key not found."` for a key that no longer exists.
-   Any status: `license: null` when Lemon Squeezy was unreachable from simple-history.com.
-   Old server: no `license` key at all.

## Stored option shape after this plan

Option `simple_history_plusplugin_message_<slug>` (unchanged name). Existing keys untouched; new keys added:

| Key                        | Type    | Meaning                                                                           |
| -------------------------- | ------- | --------------------------------------------------------------------------------- |
| `key_expires_at`           | ?string | **Refreshed** from `license.expires_at` on every check. Was activation-time only. |
| `license_status`           | ?string | `active`, `inactive`, `expired`, `disabled`, or null (key not found).             |
| `license_valid`            | bool    | `license.valid`.                                                                  |
| `license_error`            | string  | `license.error`, empty when valid.                                                |
| `license_activation_limit` | ?int    |                                                                                   |
| `license_activation_usage` | ?int    |                                                                                   |
| `license_checked_at`       | ?string | ISO-8601 UTC. **Presence means "a check has happened".**                          |

## File structure

-   Modify `inc/class-addon-plugin.php`: add the new keys to `$message_defaults`; add `update_license_status_from_response()`, `get_license_state()`, `get_license_state_description()`. Owns everything about reading and writing the license option.
-   Modify `inc/class-plugin-updater.php`: `get_addon_plugin()` replaces the inline `new AddOn_Plugin` in `get_license_key()`; `request()` accepts 401 JSON bodies and hands `license` to the add-on. Owns HTTP and caching only.
-   Modify `inc/services/class-licences-settings-page.php`: print the state description under the "License key is active" line, and the error plus renew link when not active.
-   Modify `css/styles.css`: one small block for the status line.
-   Create `tests/wpunit/AddOnPluginLicenseStateTest.php`, `tests/wpunit/PluginUpdaterLicenseStatusTest.php`.
-   Modify `readme.txt` (Unreleased changelog), create `readme.issue-316.md`.

Run a single wpunit file with:

```bash
docker compose run --rm php-cli vendor/bin/codecept run wpunit AddOnPluginLicenseStateTest
```

Run lint and analysis with:

```bash
./vendor/bin/phpcs inc/class-addon-plugin.php inc/class-plugin-updater.php inc/services/class-licences-settings-page.php
./vendor/bin/phpstan analyse --memory-limit=2G
```

---

### Task 1: `AddOn_Plugin` stores and derives license state

**Files:**

-   Modify: `inc/class-addon-plugin.php` (defaults at lines 53-63; new methods after `set_licence_message()` at line 108)
-   Test: `tests/wpunit/AddOnPluginLicenseStateTest.php`

**Interfaces:**

-   Consumes: nothing new.
-   Produces:

    -   `public function update_license_status_from_response( $license ): bool` — `$license` is the decoded `license` array from the update response, or anything else (null, string). Returns true when the option was written, false when ignored.
    -   `public function get_license_state(): array` with keys `state` (string: `none|active|expired|disabled|invalid`), `source` (`none|activation|update_check`), `expires_at` (?string raw), `expires_timestamp` (?int), `is_lifetime` (bool), `checked_at` (?string), `error` (string), `activation_limit` (?int), `activation_usage` (?int).
    -   `public function get_license_state_description(): string` — one plain-text sentence for the Licenses tab, empty string for state `none`.

-   [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/wpunit/AddOnPluginLicenseStateTest.php

use Simple_History\AddOn_Plugin;

/**
 * License state derived from the per-add-on license option, refreshed by update checks.
 */
class AddOnPluginLicenseStateTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG = 'sh-test-addon';
	private const OPTION = 'simple_history_plusplugin_message_' . self::SLUG;

	public function setUp(): void {
		parent::setUp();
		delete_option( self::OPTION );
	}

	public function tearDown(): void {
		delete_option( self::OPTION );
		parent::tearDown();
	}

	private function addon(): AddOn_Plugin {
		return new AddOn_Plugin( self::SLUG . '/index.php', self::SLUG, '1.0.0', 'Test add-on', 1 );
	}

	/** Option as written by an older core at activation time: no license_* keys at all. */
	private function seed_activated_option( ?string $expires_at ): void {
		update_option(
			self::OPTION,
			[
				'key'             => 'abc-123',
				'key_activated'   => true,
				'key_instance_id' => 'inst-1',
				'key_created_at'  => '2024-01-01T00:00:00.000000Z',
				'key_expires_at'  => $expires_at,
				'product_id'      => 1,
				'product_name'    => 'Test add-on',
				'customer_name'   => 'Someone',
				'customer_email'  => 'someone@example.com',
			]
		);
	}

	public function test_no_key_gives_state_none() {
		$state = $this->addon()->get_license_state();

		$this->assertSame( 'none', $state['state'] );
		$this->assertSame( 'none', $state['source'] );
		$this->assertSame( '', $this->addon()->get_license_state_description() );
	}

	public function test_key_that_failed_activation_gives_state_none() {
		update_option( self::OPTION, [ 'key' => 'abc-123', 'key_activated' => false ] );

		$this->assertSame( 'none', $this->addon()->get_license_state()['state'] );
	}

	public function test_activation_only_option_with_future_expiry_is_active() {
		$this->seed_activated_option( gmdate( 'Y-m-d\TH:i:s.000000\Z', time() + 30 * DAY_IN_SECONDS ) );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertFalse( $state['is_lifetime'] );
		$this->assertNull( $state['checked_at'] );
	}

	public function test_activation_only_option_with_past_expiry_is_expired() {
		$this->seed_activated_option( '2024-01-23T13:25:53.000000Z' );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( strtotime( '2024-01-23T13:25:53.000000Z' ), $state['expires_timestamp'] );
	}

	public function test_activation_only_option_without_expiry_is_lifetime() {
		$this->seed_activated_option( null );

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'active', $state['state'] );
		$this->assertTrue( $state['is_lifetime'] );
	}

	public function test_update_check_refreshes_a_stale_activation_expiry() {
		// Activated a year ago with a one-year key; renewed since, but core never knew.
		$this->seed_activated_option( '2025-09-01T00:00:00.000000Z' );
		$this->assertSame( 'expired', $this->addon()->get_license_state()['state'] );

		$written = $this->addon()->update_license_status_from_response(
			[
				'valid'            => true,
				'status'           => 'active',
				'expires_at'       => '2027-09-01T00:00:00.000000Z',
				'created_at'       => '2024-01-01T00:00:00.000000Z',
				'activation_limit' => 5,
				'activation_usage' => 2,
				'product_name'     => 'Test add-on',
				'variant_name'     => '5 sites',
				'error'            => '',
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);

		$this->assertTrue( $written );

		$state  = $this->addon()->get_license_state();
		$option = get_option( self::OPTION );

		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'update_check', $state['source'] );
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $state['expires_at'] );
		$this->assertSame( '2026-09-06T12:00:00Z', $state['checked_at'] );
		$this->assertSame( 5, $state['activation_limit'] );
		$this->assertSame( 2, $state['activation_usage'] );
		// Activation-time fields survive the merge.
		$this->assertSame( 'abc-123', $option['key'] );
		$this->assertTrue( $option['key_activated'] );
		$this->assertSame( 'inst-1', $option['key_instance_id'] );
		$this->assertSame( 'someone@example.com', $option['customer_email'] );
		// key_expires_at is the refreshed value, not the activation-time one.
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $option['key_expires_at'] );
	}

	public function test_update_check_reporting_expired_wins_over_activation_data() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response(
			[
				'valid'            => false,
				'status'           => 'expired',
				'expires_at'       => '2026-05-27T01:17:07.000000Z',
				'created_at'       => '2025-05-19T17:34:22.000000Z',
				'activation_limit' => 1,
				'activation_usage' => 1,
				'product_name'     => 'Test add-on',
				'variant_name'     => '1 site',
				'error'            => 'This license key is expired.',
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);

		$state = $this->addon()->get_license_state();

		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( 'This license key is expired.', $state['error'] );
		$this->assertFalse( $state['is_lifetime'] );
	}

	public function test_disabled_and_not_found_have_their_own_states() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'disabled', 'expires_at' => null, 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertSame( 'disabled', $this->addon()->get_license_state()['state'] );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => null, 'expires_at' => null, 'error' => 'license_key not found.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertSame( 'invalid', $this->addon()->get_license_state()['state'] );
	}

	public function test_inactive_status_counts_as_active() {
		$this->seed_activated_option( null );

		$this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'inactive', 'expires_at' => null, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] );

		$this->assertSame( 'active', $this->addon()->get_license_state()['state'] );
	}

	public function test_null_or_malformed_license_keeps_previous_state() {
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );

		$this->assertFalse( $this->addon()->update_license_status_from_response( null ) );
		$this->assertFalse( $this->addon()->update_license_status_from_response( 'yes' ) );
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'status' => 'active' ] ) ); // no checked_at
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'status' => [ 'nested' ], 'checked_at' => '2026-09-06T12:00:00Z' ] ) );

		$this->assertSame( 'expired', $this->addon()->get_license_state()['state'] );
	}

	public function test_update_check_never_creates_a_license_for_a_site_without_one() {
		// No option at all, yet a response arrives (should not happen, but be safe).
		$this->assertFalse( $this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'active', 'expires_at' => null, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] ) );

		$this->assertFalse( get_option( self::OPTION ) );
	}

	public function test_description_text_per_state() {
		$this->seed_activated_option( null );
		$this->assertSame( 'Lifetime license.', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => true, 'status' => 'active', 'expires_at' => '2027-09-01T00:00:00.000000Z', 'activation_limit' => 5, 'activation_usage' => 2, 'error' => '', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'Renews on', $this->addon()->get_license_state_description() );
		$this->assertStringContainsString( wp_date( get_option( 'date_format' ), strtotime( '2027-09-01T00:00:00.000000Z' ) ), $this->addon()->get_license_state_description() );
		$this->assertStringContainsString( '2 of 5 sites', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'Expired on', $this->addon()->get_license_state_description() );

		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => null, 'expires_at' => null, 'error' => 'license_key not found.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		$this->assertStringContainsString( 'no longer valid', $this->addon()->get_license_state_description() );
	}
}
```

-   [ ] **Step 2: Run the test file to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit AddOnPluginLicenseStateTest`
Expected: FAIL with `Call to undefined method Simple_History\AddOn_Plugin::get_license_state()`.

-   [ ] **Step 3: Add the new keys to the option defaults**

In `inc/class-addon-plugin.php`, replace the `$message_defaults` array (lines 53-63) with:

```php
	private array $message_defaults = [
		'key'                      => null,
		'key_activated'            => false,
		'key_instance_id'          => null,
		'key_created_at'           => null,
		'key_expires_at'           => null,
		'product_id'               => null,
		'product_name'             => null,
		'customer_name'            => null,
		'customer_email'           => null,
		// Refreshed by every update check, see update_license_status_from_response().
		// Absent from options written by older versions, so read them with ??.
		'license_status'           => null,
		'license_valid'            => null,
		'license_error'            => '',
		'license_activation_limit' => null,
		'license_activation_usage' => null,
		'license_checked_at'       => null,
	];
```

Also add the same six `license_*` keys (same values) to the reset array inside `deactivate_license()` (lines 243-254), so a deactivated key leaves no stale status behind. `activate_license()` (lines 182-195) writes a fresh array without them; that is correct (readers use `??`), and its `key_expires_at` is fresh from the activation response.

Then make both `activate_license()` and `deactivate_license()` drop the updater's cached response, so a customer who renews and re-activates is not shown a cached 401 for up to an hour. Add this private method to `AddOn_Plugin` and call `$this->purge_updater_cache();` right after each `set_licence_message()` in those two methods:

```php
	/**
	 * Forget the cached update-check response for this add-on.
	 *
	 * Mirrors Plugin_Updater::$cache_key. Called on (de)activation so the next
	 * check asks the server instead of replaying a stale answer.
	 */
	private function purge_updater_cache() {
		delete_transient( 'simple_history_updater_cache_' . str_replace( '-', '_', $this->slug ) );
	}
```

Add a test to `AddOnPluginLicenseStateTest` for it (and `remove_all_filters( 'pre_http_request' )` in `tearDown()`):

```php
	public function test_deactivation_clears_status_and_updater_cache() {
		$this->seed_activated_option( null );
		$this->addon()->update_license_status_from_response( [ 'valid' => false, 'status' => 'expired', 'expires_at' => '2026-05-27T01:17:07.000000Z', 'error' => 'This license key is expired.', 'checked_at' => '2026-09-06T12:00:00Z' ] );
		set_transient( 'simple_history_updater_cache_sh_test_addon', '{"success":false}', HOUR_IN_SECONDS );
		// deactivate_license() calls the license server; fake a 200 so the reset branch runs.
		add_filter( 'pre_http_request', static function () { return [ 'headers' => [], 'body' => '{}', 'response' => [ 'code' => 200, 'message' => '' ], 'cookies' => [], 'filename' => null ]; }, 10, 3 );

		$this->addon()->deactivate_license();

		$this->assertSame( 'none', $this->addon()->get_license_state()['state'] );
		$this->assertFalse( get_transient( 'simple_history_updater_cache_sh_test_addon' ) );
	}
```

-   [ ] **Step 4: Implement the three methods**

Insert after `set_licence_message()` (after line 108) in `inc/class-addon-plugin.php`:

```php
	/**
	 * Merge the license object from an update-check response into the stored license message.
	 *
	 * The activation response is stored once and never refreshed by Lemon Squeezy,
	 * so a renewed subscription looks expired locally. The update endpoint on
	 * simple-history.com now returns the key's current status and expiry on every
	 * check; this stores it next to the activation data.
	 *
	 * Ignores anything that is not a well-formed license object, and never
	 * creates a license for a site that has none: a missing or null object
	 * means "could not check", and the previous state must survive.
	 *
	 * @param mixed $license Decoded `license` array from the update response.
	 * @return bool True when the option was written.
	 */
	public function update_license_status_from_response( $license ) {
		if ( ! is_array( $license ) ) {
			return false;
		}

		// checked_at is the marker that a real check happened; without it the
		// object is not from our endpoint.
		if ( ! isset( $license['checked_at'] ) || ! is_string( $license['checked_at'] ) ) {
			return false;
		}

		$message = $this->get_license_message();

		if ( empty( $message['key'] ) || empty( $message['key_activated'] ) ) {
			return false;
		}

		$status = $license['status'] ?? null;

		if ( $status !== null && ! is_string( $status ) ) {
			return false;
		}

		$expires_at = $license['expires_at'] ?? null;

		if ( $expires_at !== null && ! is_string( $expires_at ) ) {
			$expires_at = null;
		}

		$message['key_expires_at']           = $expires_at;
		$message['license_status']           = $status;
		$message['license_valid']            = ! empty( $license['valid'] );
		$message['license_error']            = isset( $license['error'] ) && is_string( $license['error'] ) ? $license['error'] : '';
		$message['license_activation_limit'] = isset( $license['activation_limit'] ) && is_int( $license['activation_limit'] ) ? $license['activation_limit'] : null;
		$message['license_activation_usage'] = isset( $license['activation_usage'] ) && is_int( $license['activation_usage'] ) ? $license['activation_usage'] : null;
		$message['license_checked_at']       = $license['checked_at'];

		$this->set_licence_message( $message );

		return true;
	}

	/**
	 * Derive one license state from the stored license message.
	 *
	 * Prefers the status from the last update check. Falls back to the
	 * activation-time expiry date for sites where no check has run since
	 * this was added, which is the same guess older versions made.
	 *
	 * @return array{
	 *   state: string,
	 *   source: string,
	 *   expires_at: ?string,
	 *   expires_timestamp: ?int,
	 *   is_lifetime: bool,
	 *   checked_at: ?string,
	 *   error: string,
	 *   activation_limit: ?int,
	 *   activation_usage: ?int
	 * } state is one of none, active, expired, disabled, invalid. source is one of none, activation, update_check.
	 */
	public function get_license_state() {
		$message = $this->get_license_message();

		$state = [
			'state'             => 'none',
			'source'            => 'none',
			'expires_at'        => null,
			'expires_timestamp' => null,
			'is_lifetime'       => false,
			'checked_at'        => null,
			'error'             => '',
			'activation_limit'  => null,
			'activation_usage'  => null,
		];

		if ( empty( $message['key'] ) || empty( $message['key_activated'] ) ) {
			return $state;
		}

		$expires_at        = isset( $message['key_expires_at'] ) && is_string( $message['key_expires_at'] ) ? $message['key_expires_at'] : null;
		$expires_timestamp = $expires_at !== null ? strtotime( $expires_at ) : false;

		if ( $expires_timestamp === false ) {
			$expires_timestamp = null;
			$expires_at        = null;
		}

		$state['expires_at']        = $expires_at;
		$state['expires_timestamp'] = $expires_timestamp;
		$state['error']             = isset( $message['license_error'] ) && is_string( $message['license_error'] ) ? $message['license_error'] : '';
		$state['activation_limit']  = isset( $message['license_activation_limit'] ) && is_int( $message['license_activation_limit'] ) ? $message['license_activation_limit'] : null;
		$state['activation_usage']  = isset( $message['license_activation_usage'] ) && is_int( $message['license_activation_usage'] ) ? $message['license_activation_usage'] : null;

		$checked_at = isset( $message['license_checked_at'] ) && is_string( $message['license_checked_at'] ) ? $message['license_checked_at'] : null;

		if ( $checked_at === null ) {
			// Never checked: the activation-time expiry is all there is.
			$state['source'] = 'activation';
			$state['state']  = $expires_timestamp !== null && $expires_timestamp < time() ? 'expired' : 'active';
		} else {
			$state['source']     = 'update_check';
			$state['checked_at'] = $checked_at;

			$status = isset( $message['license_status'] ) && is_string( $message['license_status'] ) ? $message['license_status'] : null;

			if ( $status === 'active' || $status === 'inactive' ) {
				$state['state'] = 'active';
			} elseif ( $status === 'expired' ) {
				$state['state'] = 'expired';
			} elseif ( $status === 'disabled' ) {
				$state['state'] = 'disabled';
			} else {
				$state['state'] = 'invalid';
			}
		}

		$state['is_lifetime'] = $state['state'] === 'active' && $expires_at === null;

		return $state;
	}

	/**
	 * One-sentence, plain-text description of the license state for the Licenses tab.
	 *
	 * @return string Empty when there is no license.
	 */
	public function get_license_state_description() {
		$state = $this->get_license_state();

		$date = $state['expires_timestamp'] !== null ? wp_date( get_option( 'date_format' ), $state['expires_timestamp'] ) : '';

		$usage = '';

		if ( $state['activation_limit'] !== null && $state['activation_usage'] !== null ) {
			$usage = sprintf(
				/* translators: 1: number of sites the key is activated on, 2: number of sites the key allows. */
				__( 'Activated on %1$d of %2$d sites.', 'simple-history' ),
				$state['activation_usage'],
				$state['activation_limit']
			);
		}

		switch ( $state['state'] ) {
			case 'active':
				if ( $state['is_lifetime'] ) {
					$text = __( 'Lifetime license.', 'simple-history' );
				} else {
					/* translators: %s: date */
					$text = sprintf( __( 'Renews on %s.', 'simple-history' ), $date );
				}
				break;

			case 'expired':
				/* translators: %s: date */
				$text = $date !== '' ? sprintf( __( 'Expired on %s.', 'simple-history' ), $date ) : __( 'License has expired.', 'simple-history' );
				break;

			case 'disabled':
				$text = __( 'License has been disabled.', 'simple-history' );
				break;

			case 'invalid':
				$text = __( 'License key is no longer valid.', 'simple-history' );
				break;

			default:
				return '';
		}

		return trim( $text . ' ' . $usage );
	}
```

-   [ ] **Step 5: Run the test file to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit AddOnPluginLicenseStateTest`
Expected: PASS, 13 tests.

-   [ ] **Step 6: Lint**

Run: `./vendor/bin/phpcs inc/class-addon-plugin.php tests/wpunit/AddOnPluginLicenseStateTest.php`
Expected: no errors. Fix any warnings the file introduces.

-   [ ] **Step 7: Note progress in `readme.issue-316.md`** (create it: title, link to local issue 316 and 315, a "Done" list with "Task 1: AddOn_Plugin state + tests") and stop for review. Do not commit; Pär commits.

---

### Task 2: `Plugin_Updater` feeds the license object to the add-on

**Files:**

-   Modify: `inc/class-plugin-updater.php:76-84` (`get_license_key`) and `inc/class-plugin-updater.php:91-143` (`request`)
-   Test: `tests/wpunit/PluginUpdaterLicenseStatusTest.php`

**Interfaces:**

-   Consumes: `AddOn_Plugin::update_license_status_from_response( $license ): bool`, `AddOn_Plugin::get_license_state(): array` from Task 1.
-   Produces: `protected function get_addon_plugin(): AddOn_Plugin`. `request()` unchanged signature, but now also returns the decoded object for a 401 JSON body (with `success => false`), and stores `license` when present.

-   [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/wpunit/PluginUpdaterLicenseStatusTest.php

use Simple_History\AddOn_Plugin;
use Simple_History\Plugin_Updater;

/**
 * The updater passes the update endpoint's `license` object on to the add-on,
 * and keeps working against a server that does not send one.
 */
class PluginUpdaterLicenseStatusTest extends \Codeception\TestCase\WPTestCase {
	private const SLUG    = 'sh-test-addon';
	private const ID      = 'sh-test-addon/index.php';
	private const OPTION  = 'simple_history_plusplugin_message_' . self::SLUG;
	private const API_URL = 'https://example.test/wp-json/lsq/v1';

	/** @var array{code:int, body:string}|null Next fake HTTP reply. */
	private $fake_reply = null;

	/** @var int Number of HTTP requests seen. */
	private $requests_seen = 0;

	public function setUp(): void {
		parent::setUp();
		delete_option( self::OPTION );
		delete_transient( 'simple_history_updater_cache_sh_test_addon' );
		add_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ], 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'filter_pre_http_request' ] );
		delete_option( self::OPTION );
		delete_transient( 'simple_history_updater_cache_sh_test_addon' );
		parent::tearDown();
	}

	public function filter_pre_http_request( $preempt, $args, $url ) {
		if ( strpos( $url, self::API_URL ) === false ) {
			return $preempt;
		}

		$this->requests_seen++;

		if ( $this->fake_reply === null ) {
			return new WP_Error( 'http_request_failed', 'Fake network failure' );
		}

		return [
			'headers'  => [],
			'body'     => $this->fake_reply['body'],
			'response' => [
				'code'    => $this->fake_reply['code'],
				'message' => '',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	private function seed_activated_option(): void {
		update_option(
			self::OPTION,
			[
				'key'             => 'abc-123',
				'key_activated'   => true,
				'key_instance_id' => 'inst-1',
				'key_created_at'  => '2024-01-01T00:00:00.000000Z',
				'key_expires_at'  => '2025-01-01T00:00:00.000000Z',
				'product_id'      => 1,
				'product_name'    => 'Test add-on',
				'customer_name'   => 'Someone',
				'customer_email'  => 'someone@example.com',
			]
		);
	}

	private function updater(): Plugin_Updater {
		$updater = new Plugin_Updater( self::ID, self::SLUG, '1.0.0', self::API_URL );
		// Each test controls its own HTTP reply; no cross-test caching.
		$updater->cache_allowed = false;

		return $updater;
	}

	private function addon(): AddOn_Plugin {
		return new AddOn_Plugin( self::ID, self::SLUG, '1.0.0', 'Test add-on', 1 );
	}

	private function license_json( string $status, ?string $expires_at, bool $valid, string $error ): string {
		return wp_json_encode(
			[
				'valid'            => $valid,
				'status'           => $status === 'null' ? null : $status,
				'expires_at'       => $expires_at,
				'created_at'       => '2024-01-01T00:00:00.000000Z',
				'activation_limit' => 1,
				'activation_usage' => 1,
				'product_name'     => 'Test add-on',
				'variant_name'     => '1 site',
				'error'            => $error,
				'checked_at'       => '2026-09-06T12:00:00Z',
			]
		);
	}

	public function test_no_key_makes_no_request() {
		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 0, $this->requests_seen );
	}

	public function test_200_with_license_stores_status_and_returns_update() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 200,
			'body' => '{"success":true,"error":"","error_code":"","update":{"version":"2.0.0","download_link":"https://example.test/dl.zip"},"license":' . $this->license_json( 'active', '2027-09-01T00:00:00.000000Z', true, '' ) . '}',
		];

		$remote = $this->updater()->request();

		$this->assertIsObject( $remote );
		$this->assertTrue( $remote->success );
		$this->assertSame( '2.0.0', $remote->update->version );

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'active', $state['state'] );
		$this->assertSame( 'update_check', $state['source'] );
		$this->assertSame( '2027-09-01T00:00:00.000000Z', $state['expires_at'] );
	}

	public function test_401_with_license_stores_expired_and_returns_no_update() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":' . $this->license_json( 'expired', '2026-05-27T01:17:07.000000Z', false, 'This license key is expired.' ) . '}',
		];

		$remote = $this->updater()->request();

		$this->assertIsObject( $remote );
		$this->assertFalse( $remote->success );
		$this->assertObjectNotHasProperty( 'update', $remote );

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'expired', $state['state'] );
		$this->assertSame( '2026-05-27T01:17:07.000000Z', $state['expires_at'] );
		$this->assertSame( 'This license key is expired.', $state['error'] );
	}

	public function test_401_with_null_license_keeps_previous_state() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":null}',
		];

		$this->updater()->request();

		$state = $this->addon()->get_license_state();
		$this->assertSame( 'activation', $state['source'] );
		$this->assertSame( '2025-01-01T00:00:00.000000Z', $state['expires_at'] );
	}

	public function test_old_server_without_license_key_changes_nothing() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 200,
			'body' => '{"success":true,"error":"","error_code":"","update":{"version":"2.0.0","download_link":"https://example.test/dl.zip"}}',
		];

		$remote = $this->updater()->request();

		$this->assertSame( '2.0.0', $remote->update->version );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
		$this->assertSame( '2025-01-01T00:00:00.000000Z', get_option( self::OPTION )['key_expires_at'] );
	}

	public function test_old_server_401_without_body_json_returns_false() {
		$this->seed_activated_option();
		$this->fake_reply = [ 'code' => 401, 'body' => 'Unauthorized' ];

		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
	}

	public function test_network_error_returns_false_and_keeps_state() {
		$this->seed_activated_option();
		$this->fake_reply = null;

		$this->assertFalse( $this->updater()->request() );
		$this->assertSame( 'activation', $this->addon()->get_license_state()['source'] );
	}

	public function test_site_transient_filter_offers_no_update_for_expired_key() {
		$this->seed_activated_option();
		$this->fake_reply = [
			'code' => 401,
			'body' => '{"success":false,"error":"Invalid license_key","error_code":"invalid_license_key","license":' . $this->license_json( 'expired', '2026-05-27T01:17:07.000000Z', false, 'This license key is expired.' ) . '}',
		];

		$transient = (object) [
			'checked'   => [ self::ID => '1.0.0' ],
			'response'  => [],
			'no_update' => [],
		];

		$transient = $this->updater()->site_transient_update_plugins_update( $transient );

		$this->assertArrayNotHasKey( self::ID, $transient->response );
		$this->assertArrayHasKey( self::ID, $transient->no_update );
	}
}
```

-   [ ] **Step 2: Run the test file to verify it fails**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginUpdaterLicenseStatusTest`
Expected: `test_401_with_license_stores_expired_and_returns_no_update` fails (`request()` returns false and no state stored); `test_200_with_license_stores_status_and_returns_update` fails on `source` being `activation`. The old-server and network tests pass already; that is the backwards-compatibility baseline.

-   [ ] **Step 3: Replace `get_license_key()` with an add-on accessor**

Replace lines 70-84 of `inc/class-plugin-updater.php` with:

```php
	/**
	 * The add-on this updater serves, for reading and refreshing its license.
	 *
	 * @return AddOn_Plugin
	 */
	protected function get_addon_plugin() {
		return new AddOn_Plugin(
			$this->plugin_id,
			$this->plugin_slug,
			$this->version,
		);
	}

	/**
	 * Get the license key. Normally, your plugin would have a settings page where
	 * you ask for and store a license key. Fetch it here.
	 *
	 * @return string
	 */
	protected function get_license_key() {
		return $this->get_addon_plugin()->get_license_key();
	}
```

-   [ ] **Step 4: Accept 401 JSON bodies and store the license object in `request()`**

Replace lines 126-143 of `inc/class-plugin-updater.php` (from `if (` through `return json_decode( $payload );` **and the closing `}` of `request()` on line 143**, since the replacement below closes the method itself) with:

```php
		if ( is_wp_error( $remote ) || empty( wp_remote_retrieve_body( $remote ) ) ) {
			// Cache errors for 10 minutes.
			set_transient( $this->cache_key, 'error', MINUTE_IN_SECONDS * 10 );

			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $remote );
		$payload       = wp_remote_retrieve_body( $remote );
		$decoded       = json_decode( $payload );

		// 200 is an update answer. 401 is the endpoint's answer for a key that
		// is expired, disabled or unknown, and since issue 315 it carries the
		// key's status too. Anything else, or a non-JSON body from an older
		// server, is an error like before.
		if ( ! in_array( $response_code, [ 200, 401 ], true ) || ! is_object( $decoded ) ) {
			// Cache errors for 10 minutes.
			set_transient( $this->cache_key, 'error', MINUTE_IN_SECONDS * 10 );

			return false;
		}

		$this->store_license_from_payload( $decoded );

		if ( $response_code !== 200 ) {
			// Cache the refusal too, so an expired key does not re-validate on every check.
			set_transient( $this->cache_key, $payload, HOUR_IN_SECONDS );

			return $decoded;
		}

		// Cache response for 1 hour.
		set_transient( $this->cache_key, $payload, HOUR_IN_SECONDS );

		return $decoded;
	}

	/**
	 * Hand the `license` object from an update response to the add-on.
	 *
	 * Older servers send no such key and newer ones send null when Lemon
	 * Squeezy could not be reached; both leave the stored state untouched.
	 *
	 * @param object $decoded Decoded update response.
	 * @return void
	 */
	private function store_license_from_payload( $decoded ) {
		if ( ! isset( $decoded->license ) || ! is_object( $decoded->license ) ) {
			return;
		}

		$this->get_addon_plugin()->update_license_status_from_response( (array) $decoded->license );
	}
```

Note `request()`'s docblock return type stays `object|stdClass|bool`. The cached-payload branch at the top of `request()` (lines 99-107) already returns `json_decode( $remote )` for a cached 401 payload, which yields `success: false` and therefore "no update" in `site_transient_update_plugins_update()`. Cached payloads are not re-stored into the option; the option was written when the payload was first fetched.

-   [ ] **Step 5: Run the test file to verify it passes**

Run: `docker compose run --rm php-cli vendor/bin/codecept run wpunit PluginUpdaterLicenseStatusTest`
Expected: PASS, 8 tests.

-   [ ] **Step 6: Run Task 1's tests again and phpstan**

Run:

```bash
docker compose run --rm php-cli vendor/bin/codecept run wpunit AddOnPluginLicenseStateTest
./vendor/bin/phpcs inc/class-plugin-updater.php tests/wpunit/PluginUpdaterLicenseStatusTest.php
./vendor/bin/phpstan analyse --memory-limit=2G
```

Expected: all pass. If phpstan complains about `(array) $decoded->license` being `mixed`, keep the `is_object` guard; the cast of an object is always an array.

-   [ ] **Step 7: Update `readme.issue-316.md`** with "Task 2: updater stores license, 401 bodies accepted + cached 1h" and stop for review.

---

### Task 3: Licenses tab shows the state

**Files:**

-   Modify: `inc/services/class-licences-settings-page.php:359-373` (the "License key is active" block inside `output_licence_key_fields_for_plugin()`)
-   Modify: `css/styles.css` (append a block near the other `.sh-LicencesPage-*` rules)

**Interfaces:**

-   Consumes: `AddOn_Plugin::get_license_state()` and `AddOn_Plugin::get_license_state_description()` from Task 1; `Helpers::get_tracking_url( $url, $utm_campaign )`. Campaign names use the `premium_*` prefix so the analytics and freemium-conversion skills pick them up.
-   Produces: nothing new for later tasks.

There is no wpunit test for this task: the method is private and echoes markup, and the text it prints is already unit-tested in Task 1. Verification is by eye on the docker site (Step 4).

-   [ ] **Step 1: Print the state under the active line**

Replace lines 359-373 of `inc/services/class-licences-settings-page.php`:

```php
				// Show deactivate key button if key is activated.
				if ( $licence_message['key_activated'] === true ) {
					?>
					<p class="sh-LicencesPage-plugin-active">
						<?php
						echo wp_kses(
							__( 'License key is <strong>active</strong>. ', 'simple-history' ),
							[
								'strong' => [],
							]
						);
						?>
					</p>
					<?php
				}
```

with:

```php
				// Show license status if key is activated.
				if ( $licence_message['key_activated'] === true ) {
					$license_state = $plus_plugin->get_license_state();
					$is_ok         = $license_state['state'] === 'active';

					// Expired keys can be renewed. Disabled (refunded) and unknown keys
					// cannot, so those get the support page instead of a sales page.
					if ( $license_state['state'] === 'expired' ) {
						$help_url   = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_license_renew' );
						$help_label = __( 'Renew license', 'simple-history' );
					} else {
						$help_url   = Helpers::get_tracking_url( 'https://simple-history.com/support/', 'premium_license_help' );
						$help_label = __( 'Get help', 'simple-history' );
					}
					?>
					<p class="sh-LicencesPage-plugin-active <?php echo $is_ok ? '' : 'sh-LicencesPage-plugin-active--problem'; ?>">
						<?php
						if ( $is_ok ) {
							echo wp_kses(
								__( 'License key is <strong>active</strong>.', 'simple-history' ),
								[ 'strong' => [] ]
							);
						} else {
							echo wp_kses(
								__( 'License key is <strong>not active</strong>.', 'simple-history' ),
								[ 'strong' => [] ]
							);
						}

						echo ' ';
						echo esc_html( $plus_plugin->get_license_state_description() );

						if ( ! $is_ok ) {
							echo ' ';
							printf(
								'<a href="%s" class="sh-ExternalLink" target="_blank">%s</a>',
								esc_url( $help_url ),
								esc_html( $help_label )
							);
						}
						?>
					</p>
					<?php
				}
```

Confirm `use Simple_History\Helpers;` is already present at the top of the file (it is used elsewhere in the same file for `get_view_settings_capability`); add it if not.

-   [ ] **Step 2: Style the problem state**

Append to `css/styles.css` after the existing `.sh-LicencesPage-plugin-active` rule (search for it; if none exists, append at the end of the licences section):

```css
/* Licenses tab: expired, disabled or invalid key. Color plus text, never color alone. */
.sh-LicencesPage-plugin-active--problem {
	color: #b32d2e;
}
```

`#b32d2e` is WordPress's own error red (`--wp-admin-color-error` equivalent), which is AA against white.

-   [ ] **Step 3: Lint**

Run: `./vendor/bin/phpcs inc/services/class-licences-settings-page.php`
Expected: no new errors.

-   [ ] **Step 4: Verify by eye on the docker site**

The docker site has premium mounted but no key. Seed a fake stored state and look at the tab. Write this script to the scratchpad, copy it into the container, run it, delete it:

```php
<?php
require '/var/www/html/wp-load.php';
$opt = 'simple_history_plusplugin_message_simple-history-premium';
update_option( $opt, [
	'key' => 'abc-123', 'key_activated' => true, 'key_instance_id' => 'inst-1',
	'key_created_at' => '2024-01-01T00:00:00.000000Z', 'key_expires_at' => '2026-05-27T01:17:07.000000Z',
	'product_id' => 394362, 'product_name' => 'Simple History Premium', 'customer_name' => null, 'customer_email' => null,
	'license_status' => 'expired', 'license_valid' => false, 'license_error' => 'This license key is expired.',
	'license_activation_limit' => 1, 'license_activation_usage' => 1, 'license_checked_at' => '2026-09-06T12:00:00Z',
] );
echo "seeded\n";
```

```bash
C=docker-compose-to-run-on-system-boot-wordpress_mariadb-1
docker cp seed.php $C:/tmp/seed.php && docker exec $C php /tmp/seed.php && docker exec $C rm /tmp/seed.php
```

Open `http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_settings_page&selected-sub-tab=general_settings_subtab_licenses` (the sub-tab URL; if it 404s, take the URL from `Helpers::get_settings_page_sub_tab_url( 'general_settings_subtab_licenses' )`). Expected: "License key is **not active**. Expired on 27 May 2026. Activated on 1 of 1 sites. Renew license" in red. Flip `license_status` to `disabled` and re-run: the link must read "Get help" and point at the support page.

Then flip `license_status` to `active`, `license_valid` to `true`, `license_error` to `''`, `key_expires_at` to `'2027-09-01T00:00:00.000000Z'`, re-run. Expected: "License key is **active**. Renews on 1 September 2027. Activated on 1 of 1 sites." in the normal colour.

Finally clean up: `docker exec $C php -r 'require "/var/www/html/wp-load.php"; delete_option("simple_history_plusplugin_message_simple-history-premium");'`

-   [ ] **Step 5: Update `readme.issue-316.md`** with "Task 3: Licenses tab shows state, verified with seeded option" and stop for review.

---

### Task 4: Changelog, full checks, add-ons compatibility

**Files:**

-   Modify: `readme.txt` (the `### Unreleased` section, `**Added**` and `**Changed**` lists)
-   Modify: `readme.issue-316.md`

-   [ ] **Step 1: Add changelog entries**

Use the `changelog` skill. Under `### Unreleased`:

Under `**Added**`:

```
-   The Licenses tab now shows when an add-on license renews or when it expired, and how many sites the key is activated on. The status is refreshed from simple-history.com on every update check, so a renewed license no longer looks expired.
```

Under `**Fixed**` (create the heading if missing, in keepachangelog order Added, Changed, Fixed, Security):

```
-   An expired or disabled add-on license key silently stopped updates without saying why. The Licenses tab now shows the reason and a renew link.
```

-   [ ] **Step 2: Full test, lint, analysis and add-on checks**

Run:

```bash
docker compose run --rm php-cli vendor/bin/codecept run wpunit
./vendor/bin/phpcs
./vendor/bin/phpstan analyse --memory-limit=2G
npm run addons:check
```

Expected: all green. `addons:check` proves premium calls no new core method, so `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` stays at 5.29.0.

-   [ ] **Step 3: Backwards-compatibility checklist in `readme.issue-316.md`**

Record, with the test name that proves each:

-   New core, old server (no `license` key): `test_old_server_without_license_key_changes_nothing`, `test_old_server_401_without_body_json_returns_false`.
-   New core, new server, Lemon Squeezy down (`license: null`): `test_401_with_license_stores_expired_and_returns_no_update` counterpart `test_401_with_null_license_keeps_previous_state`.
-   Old stored option without `license_*` keys: every `seed_activated_option()` test in `AddOnPluginLicenseStateTest`.
-   Old core, new server: server side, the 200 keys and 401 status are unchanged (issue 315).
-   Premium untouched: `npm run addons:check` output.

-   [ ] **Step 4: Stop.** Do not commit or push. Report to Pär: files changed, tests added, the two things that still need him: (1) deploy issue 315 to simple-history.com, (2) after deploy, put a real key on the docker site, trigger `wp plugin update --dry-run` or visit Plugins, and confirm `license_checked_at` appears in the option.

---

## After this plan (not part of it)

-   Issue 15's branch `worktree-issue-15-whats-new` replaces its own `get_license_state()` (reads the option directly and derives from `key_expires_at`) with `AddOn_Plugin::get_license_state()` from Task 1. That closes issue 15's gate 1 and removes the need for the `simple_history/whats_new/license_state` filter from premium.
-   A "renews in N days" nudge is now possible from `expires_timestamp` but is a separate decision (issue 15 or new issue).

## Self-review

-   Spec coverage: 316 task 1 (updater merge, 401 handling, network vs 401 distinction) → Task 2. Task 2 (getter) → Task 1. Task 3 (Licenses tab with dates, usage, error, renew link) → Task 3. Task 4 (tests with active/expired/lifetime/401/network fixtures) → Tasks 1 and 2. Contract note (null expiry = lifetime, null license = keep state, not-found = invalid) → Task 1 tests `..._without_expiry_is_lifetime`, `..._keeps_previous_state`, `..._own_states`.
-   Placeholders: none. Every code step has the code.
-   Type consistency: `update_license_status_from_response( $license ): bool`, `get_license_state(): array` with the key list above, `get_license_state_description(): string`, `get_addon_plugin(): AddOn_Plugin` are used with the same names and shapes in Tasks 1, 2 and 3.
