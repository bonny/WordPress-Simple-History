# Issue 296 — Failed login suppression summary (experimental)

When failed login throttling has skipped attempts and the burst then ends (any non-failed-login event is logged), core writes one `failed_logins_not_recorded` event through the Simple History logger. It is gated on experimental features.

The row reads: "Recorded 100 failed login attempts in a row, then stopped recording to keep the log small. 4,183 more attempts followed." Attribution is "WordPress · Using plugin Simple History". Details: attempts in total, recorded, not recorded, last attempt, first unrecorded attempt, username targeted, IP address. Action link: "Configure failed login attempts".

## What is where

-   `inc/services/class-failed-login-limit-service.php` — tracks the burst (`sh_core_failed_login_burst` option: first/last skipped time, last username, the attacker's IP, forwarded IPs and referer) while suppressing, and logs the summary from the reset path. `track_suppressed_attempt( $context )` and `end_burst( $suppressed_count, $threshold )` are public static so premium's limiter can call them; `end_burst()` owns reading and deleting the burst option.
-   `inc/class-helpers.php` — `get_remote_addr_context()`, the REMOTE_ADDR + proxy-header block lifted out of `Logger::append_remote_addr_to_context()` so the service can capture the same keys at suppression time. The logger now calls it.
-   `loggers/class-simple-history-logger.php` — `MESSAGE_KEY_FAILED_LOGINS_NOT_RECORDED`, the message, a "Failed login attempts not recorded" search filter under the Simple History logger, number formatting in the plain-text output, the "Configure failed login attempts" action link, and the details table. It lives here rather than in the user logger because it records something the plugin did, and this logger's `name_via` gives the "Using plugin Simple History" attribution. Trade-off: the "Failed user logins" search filter does not include it.
-   `uninstall.php` — the burst option is removed on uninstall.
-   `tests/wpunit/FailedLoginSuppressionSummaryTest.php` — 12 tests.

## Design notes

-   The summary is dated at the last skipped attempt (`_date`), so it lands directly above the "+99 similar" group even if the burst is only closed days later. Side effect: it is invisible to the "N new events" poller, which keys on the newest date. Accepted for now, noted on the evaluate issue.
-   The request context (`_server_remote_addr`, forwarded IPs, referer) is captured at suppression time and pinned on the summary, and `_user_id` is pinned to 0, so the row does not wear the IP, headers or identity of whoever ended the burst. In production the failed-login context has no IP at `do_log` time (the logger appends it later), so the service reads the request itself.
-   The counter is reset _before_ the summary is logged: the summary passes through the same `do_log` filter. The reset path also returns early for the summary's own message key, so it cannot re-enter whatever the option layer returns.
-   Burst details are discarded on every reset, even when nothing was skipped (threshold raised mid-burst), so a later burst never inherits a stale start date.
-   Burst tracking is gated on experimental features too, so non-experimental sites pay no extra option writes.
-   The key is deliberately not in the failed-login key lists, so it is never counted or suppressed.
-   Known gap: the summary is logged re-entrantly from inside the ending event's `do_log` filter. A later-priority filter that cancels logging (e.g. a pause) also cancels the summary. Hooking `simple_history/log/inserted` instead would fix that and change reset semantics to "only events that actually landed". Not done yet.

## Running the tests from this worktree

`vendor/` is a symlink to the main repo, which Docker cannot follow. Mount it explicitly:

```bash
docker compose -p wordpress-simple-history -f compose.yaml -f <override.yaml> run --rm --no-deps php-cli vendor/bin/codecept run wpunit FailedLoginSuppressionSummaryTest
```

where the override adds `/absolute/path/to/main-repo/vendor:/srv/vendor` under `services.php-cli.volumes`.

## Todo

-   Premium parity (separate issue): premium disables the core limiter, so no summary is written while premium is active.
