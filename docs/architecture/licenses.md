# Add-on Licenses

How Simple History core knows whether a premium add-on's license key is valid, when it expires, and what to show about it. Premium and the other add-ons contain no license code of their own: they register with core, and everything below lives in core.

## The pieces

| Piece                     | Where                                                                       | Responsibility                                                                                                                                                               |
| ------------------------- | --------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `AddOn_Plugin`            | `inc/class-addon-plugin.php`                                                | One instance per add-on. Owns the stored license option: activation, deactivation, refreshing the status from update checks, and deriving one state from it.                 |
| `Plugin_Updater`          | `inc/class-plugin-updater.php`                                              | One instance per add-on. Talks to the update endpoint, caches the answer, and hands the `license` object to `AddOn_Plugin`.                                                  |
| `AddOns_Licences` service | `inc/services/class-addons-licences.php`                                    | Registry of add-ons and their updaters. `register_plugin_for_license()` is what an add-on calls; `get_plugin( $slug )` and `get_updater( $slug )` are how core reaches them. |
| Licenses tab              | `inc/services/class-licences-settings-page.php`                             | Activate and deactivate keys, show the state, force a fresh check on every visit.                                                                                            |
| Update endpoint           | `simple-history.com/wp-json/lsq/v1/update`, repo `bonny/simple-history-com` | Served by the Lemon Squeezy WordPress plugin. The `simple-history-com` plugin appends the `license` object to its answers.                                                   |

## The flow

1. **Activation.** The user enters a key on the Licenses tab. `AddOn_Plugin::activate_license()` calls `/lsq/v1/activate` and stores the answer in the option `simple_history_plusplugin_message_<slug>`: the key, the instance id, the expiry at that moment, product and customer details. This is a snapshot. Lemon Squeezy never pushes changes to it, and it moves `expires_at` forward on every renewal, so the snapshot alone makes renewed customers look expired.
2. **Update checks.** WordPress runs `wp_update_plugins` twice a day and whenever someone opens Plugins or Updates. The `site_transient_update_plugins` filter calls `Plugin_Updater::request()`, which does one GET to `/lsq/v1/update?license_key=…&plugin_slug=…` and caches the raw JSON in the transient `simple_history_updater_cache_<slug>` (see `Plugin_Updater::get_cache_key_for_slug()`).
3. **The answer carries the license.** Since September 2026 the endpoint appends a `license` object to both its 200 (valid key, update info) and its 401 (expired, disabled or unknown key) answers. `request()` passes it to `AddOn_Plugin::update_license_status_from_response()`, which merges it into the same option as the activation snapshot.
4. **Reading.** Anything that needs to know about a license calls `AddOn_Plugin::get_license_state()`. Nothing else should parse the option.

## The response contract

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
		"created_at": "2025-01-31T13:18:47.000000Z",
		"activation_limit": 5,
		"activation_usage": 2,
		"product_name": "Simple History Premium",
		"variant_name": "5 sites",
		"error": "",
		"checked_at": "2026-09-06T19:56:16Z"
	}
}
```

-   HTTP 200: `success` true, `license.status` is `active` or `inactive`.
-   HTTP 401: `success` false, `error_code` `invalid_license_key`, `license.status` is `expired` or `disabled` with the real `expires_at`, or `null` with `error` "license_key not found." for a key that no longer exists.
-   `license` is `null` when simple-history.com could not reach Lemon Squeezy. Older versions of the endpoint send no `license` key at all.
-   `expires_at` `null` on an active key means lifetime.
-   The endpoint decides 200 versus 401 by the key alone; `plugin_slug` is not checked.

## What gets stored

Option `simple_history_plusplugin_message_<slug>`, autoloaded. The activation keys are unchanged; these were added for the update-check data:

| Key                        | Type    | Meaning                                                                             |
| -------------------------- | ------- | ----------------------------------------------------------------------------------- |
| `key_expires_at`           | ?string | Was activation-time only. Now refreshed on every check.                             |
| `license_status`           | ?string | `active`, `inactive`, `expired`, `disabled`, or null when the key no longer exists. |
| `license_valid`            | ?bool   | Lemon Squeezy's own flag. Stored, not currently read.                               |
| `license_error`            | string  | Lemon Squeezy's text, empty when valid. Stored, not currently shown.                |
| `license_activation_limit` | ?int    | Sites the key allows.                                                               |
| `license_activation_usage` | ?int    | Sites it is activated on.                                                           |
| `license_checked_at`       | ?string | ISO 8601 UTC. Its presence is what says "a real check has happened".                |

Rules in `update_license_status_from_response()`:

-   Anything that is not an array with a string `checked_at` is ignored. So are responses for a site that has no activated key: an update check never creates a license.
-   A `license` of `null` leaves the stored state alone. But a 200 whose `license` is null still proves the key is valid, so `note_valid_key_without_details()` clears a stored expired, disabled or not-found verdict in that case.
-   Server strings are capped at 255 characters and checked for valid UTF-8. An `expires_at` that does not parse rejects the whole response; an empty string counts as no expiry.
-   Old options written before these keys existed are merged over the defaults in `get_license_message()`, so every reader can rely on the keys being present.

## The derived state

`AddOn_Plugin::get_license_state()` returns:

| Key                                    | Values                                                         |
| -------------------------------------- | -------------------------------------------------------------- |
| `state`                                | `none`, `active`, `inactive`, `expired`, `disabled`, `invalid` |
| `source`                               | `none`, `activation`, `update_check`                           |
| `expires_at`, `expires_timestamp`      | raw string and parsed time, null for lifetime                  |
| `is_lifetime`                          | true for `active` with no expiry                               |
| `checked_at`                           | ISO 8601 UTC, null before the first check                      |
| `error`                                | Lemon Squeezy's text                                           |
| `activation_limit`, `activation_usage` | ints or null                                                   |

-   `none`: no key, or a key whose activation failed.
-   `source: activation`: no update check has stored a status yet. The state is then always `active` (or lifetime). Core deliberately never guesses `expired` from the activation-time date, because that date is stale for every renewed customer. Consumers that need the date can read `expires_timestamp` and decide for themselves.
-   `source: update_check`: `active` and `inactive` come straight from the server. `inactive` means the key is valid but activated on no site, typically because it was deactivated from another site; it still receives updates. `expired`, `disabled` (refund or chargeback) and `invalid` (key not found) are the problem states.
-   The state is never overridden at read time by comparing the stored expiry with the clock. Renewals only show up through a fresh check, which is why freshness matters.

`get_license_state_description()` turns that into one sentence for the Licenses tab: "Valid until 1 September 2027.", "Lifetime license.", "Expired on 27 May 2026.", "The key has been disabled.", "The key was not found, check it for typos.", "The key is valid but not activated on any site…", each followed by "Activated on N of M sites." when known.

## Freshness

-   Background: WordPress checks twice a day, and the answer is cached for one hour (a 200) or ten minutes (a 401), so the stored state is at most about 13 hours old.
-   The Licenses tab purges that cache and runs one request on every visit (not on the request that just activated or deactivated a key), and prints "Last checked …". A renewal is therefore visible on the next look at the tab.
-   Activation and deactivation purge the cache too.
-   A site that removes its key or uninstalls the add-on keeps the last stored state, but it stops updating.

## Backwards compatibility

Every direction is covered by tests in `tests/wpunit/AddOnPluginLicenseStateTest.php` and `tests/wpunit/PluginUpdaterLicenseStatusTest.php`:

-   A new core against an old endpoint (no `license` key) behaves as before: no option writes.
-   An old core against the new endpoint ignores the extra key; the 200 keys and the 401 status code did not change.
-   Old stored options without the new keys derive a correct state.
-   `Plugin_Updater::request()` still returns a decoded object or `false`; a 401 with a JSON body returns the object with `success` false, and the update filter treats that as "no update".
-   Premium calls none of these methods. Check with `npm run addons:check` (or its `php:phpstan:min-core` step) before letting premium use them, and bump `SIMPLE_HISTORY_PREMIUM_MIN_CORE_VERSION` when it does.

## Using it

```php
$service = Simple_History::get_instance()->get_service( AddOns_Licences::class );
$addon   = $service->get_plugin( 'simple-history-premium' );

if ( $addon ) {
	$state = $addon->get_license_state();

	if ( $state['state'] === 'expired' && $state['source'] === 'update_check' ) {
		// A customer who really lapsed, not a stale activation snapshot.
	}
}
```

Ideas this makes possible: a re-engagement card for lapsed customers (local issue 15), a "renews in N days" nudge from `expires_timestamp`, re-activation help for `inactive` keys, and an "add more sites" hint from the activation counts.

## Debugging

With `WP_DEBUG` on, the Licenses tab shows a "Licence message (debug)" block with the raw stored option and the derived state. The WP-CLI command `wp simple-history info` still reads an older option for its license line and does not use this state yet.

## History

Local issues 315 (endpoint) and 316 (core), September 2026. The plan with the full task breakdown is `docs/superpowers/plans/2026-09-06-license-status-from-update-checks.md`.
