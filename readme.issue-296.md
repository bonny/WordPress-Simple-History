# Issue 296 — Failed login suppression summary (experimental)

When failed login throttling has skipped attempts and the burst then ends (a non-failed-login event is actually written), core writes one `failed_logins_not_recorded` event through the Simple History logger. It is gated on experimental features.

The row reads: "Recorded 100 failed login attempts in a row, then stopped recording to keep the log small. 4,183 more attempts followed." Attribution is "WordPress · Using plugin Simple History". Details: attempts in total, recorded, not recorded, last attempt, first unrecorded attempt, username targeted, IP address. Action link: "Configure failed login attempts".

## What is where

-   `inc/services/class-failed-login-limit-service.php` — while suppressing, `track_suppressed_attempt( $context, $recorded_count )` keeps a self-contained burst record in the `sh_core_failed_login_burst` option (recorded count, not-recorded count, first/last time, last username, the attacker's IP, forwarded IPs and referer). The reset runs on `simple_history/log/inserted`, not on the `do_log` filter, and calls `end_burst()`, which reads and deletes the record and logs the summary. Both helpers are public static so premium's limiter can call them.
-   `inc/class-helpers.php` — `get_remote_addr_context()` (the REMOTE_ADDR + proxy-header block lifted out of the logger) and `get_masked_referer()` (the referer masking, likewise). The logger calls both.
-   `inc/class-wp-rest-searchoptions-controller.php` — a search label value that already carries a `Logger:` prefix is kept as is, so one logger's label can list another logger's event.
-   `loggers/class-simple-history-logger.php` — `MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED`, the message, its own "Failed login attempts not recorded" search label, number formatting in the plain-text output, the action link (premium tab when premium is active, core's teaser sub-tab otherwise), and the details table. It lives here rather than in the user logger because it records something the plugin did, and this logger's `name_via` gives the "Using plugin Simple History" attribution.
-   `loggers/class-user-logger.php` — the "Failed user logins" search label also lists the summary, prefixed with its own logger.
-   `uninstall.php` — the burst option is removed on uninstall.
-   `tests/wpunit/FailedLoginSuppressionSummaryTest.php` — 19 tests.

## Design notes

-   The summary is dated at the last unrecorded attempt (`_date`), so it lands directly above the "+99 similar" group even if the burst is only closed days later. Side effect: it is invisible to the "N new events" poller, which keys on the newest date. Accepted for now, noted on the evaluate issue.
-   The burst record counts what was actually suppressed at suppression time. Nothing is derived from the counter and threshold at reset time, so a threshold changed mid-burst cannot invent or hide attempts, and enabling experimental features mid-burst summarises only what was tracked.
-   The reset runs on the inserted action so a later `do_log` filter (the pause action, a per-logger mute) that cancels the ending event leaves the burst open. It closes when the next event lands.
-   The request context is captured at suppression time using the same key list the logger uses for IPs (`Helpers::get_ip_address_context_key_prefixes()`), plus the referer, and pinned on the summary together with `_user_id => 0`, so the row does not wear the IP, headers or identity of whoever ended the burst. The `_user_id` pin is a workaround for a logger-level issue filed separately (307).
-   The key is deliberately not in the failed-login key lists, so it is never counted or suppressed. The reset path also returns early for it.
-   Burst tracking is gated on experimental features too, so non-experimental sites pay no extra option writes.

## Running the tests from this worktree

`vendor/` is a symlink to the main repo, which Docker cannot follow. Mount it explicitly:

```bash
docker compose -p wordpress-simple-history -f compose.yaml -f <override.yaml> run --rm --no-deps php-cli vendor/bin/codecept run wpunit FailedLoginSuppressionSummaryTest
```

where the override adds `/absolute/path/to/main-repo/vendor:/srv/vendor` under `services.php-cli.volumes`.

## Todo

-   Premium parity: issue 305.
