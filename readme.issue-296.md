# Issue 296 — Failed login suppression summary (experimental)

When failed login throttling has skipped attempts and the burst then ends (any non-failed-login event is logged), core writes one `user_failed_logins_suppressed` event. It is gated on experimental features.

## What is where

-   `inc/services/class-failed-login-limit-service.php` — tracks the burst (`sh_core_failed_login_burst` option: first/last skipped time, last username, last IP) while suppressing, and logs the summary from the reset path. `track_suppressed_attempt()` and `maybe_log_suppression_summary()` are public static so premium's limiter can call them.
-   `loggers/class-user-logger.php` — `MESSAGE_KEY_FAILED_LOGINS_SUPPRESSED`, the message, a "Skipped failed login attempts" search filter, and the details table.
-   `tests/wpunit/FailedLoginSuppressionSummaryTest.php` — 8 tests.

## Design notes

-   The summary is dated at the last skipped attempt (`_date`), so it lands directly above the "+99 similar" group even if the burst is only closed days later.
-   `_server_remote_addr` is set to the attacker's last IP so the row does not wear the IP of whoever ended the burst.
-   The counter is reset _before_ the summary is logged: the summary passes through the same `do_log` filter.
-   The key is deliberately not in the failed-login key lists, so it is never counted or suppressed.

## Running the tests from this worktree

`vendor/` is a symlink to the main repo, which Docker cannot follow. Mount it explicitly:

```bash
docker compose -p wordpress-simple-history -f compose.yaml -f <override.yaml> run --rm --no-deps php-cli vendor/bin/codecept run wpunit FailedLoginSuppressionSummaryTest
```

where the override adds `/absolute/path/to/main-repo/vendor:/srv/vendor` under `services.php-cli.volumes`.

## Todo

-   Premium parity (separate issue): premium disables the core limiter, so no summary is written while premium is active.
