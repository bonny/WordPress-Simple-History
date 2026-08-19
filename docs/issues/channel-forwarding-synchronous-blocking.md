# Channel forwarding blocks the request (async queue is a TODO stub)

**Status:** Open 🐞 — root cause confirmed, fix not started
**Size:** Medium
**Labels:** performance, bug, channels
**Related:** [issue-573-log-forwards-destinations.md](issue-573-log-forwards-destinations.md) (the feature this affects)

## Problem

On a site with one or more **channels** enabled (Datadog, remote syslog,
external database, webhook, Splunk), **every logged event forwards to those
channels synchronously, inside the request that triggered the log** — blocking
the response until each remote endpoint answers.

Any action that logs an event pays the full network round-trip cost of every
enabled remote channel, in series, before the user's request returns. The more
channels (and the further away / slower each endpoint), the worse it gets. This
turns audit logging — which should be near-free from the user's perspective —
into a foreground network operation on ordinary admin actions (saving a post,
reordering pages, etc.).

## How it was found

Reported as "moving pages is very slow" in the CMS Tree Page View plugin on a
heavily-configured local test site (`wordpress-stable-docker-mariadb.test`, 223
pages, 29 active plugins). CMS Tree Page View logs one Simple History event per
page move. Bisection isolated the cost to Simple History Premium, then to the
per-logged-event path (not per-request overhead).

### Measurements (REST move endpoint, same page, warm)

| Scenario                                                               | Time per action |
| ---------------------------------------------------------------------- | --------------- |
| No Simple History                                                      | ~120 ms         |
| Simple History core only                                               | ~130 ms         |
| + Premium, **remote channels ON** (Datadog + syslog-TLS + external DB) | **~555 ms**     |
| + Premium, remote channels **OFF**                                     | ~158 ms         |
| Premium's effect on **reads** (no event logged)                        | +15–20 ms only  |

Disabling the three remote channels alone recovered ~400 ms per action.
Read-only requests (no event logged) were essentially unaffected by Premium,
which pins the cost to the per-event forwarding path, not plugin load or
per-request overhead.

## Root cause

`inc/channels/class-channels-manager.php` — the async queue the architecture
already reaches for is an unimplemented stub that falls straight through to
synchronous sending:

```php
// process_logged_event() hooks simple_history/log/inserted and, for each
// enabled channel, calls send_to_channel().

private function should_process_async( Channel_Interface $channel ) {
    // For now, always process async if supported.
    return true;                                    // ← claims async
}

private function queue_for_async_processing( Channel_Interface $channel, $event_data, $formatted_message ) {
    // TODO: Implement async queue system using WordPress cron.
    // For now, fall back to synchronous processing.
    $this->send_sync( $channel, $event_data, $formatted_message );   // ← actually synchronous
}
```

`send_to_channel()` routes through `should_process_async()` →
`queue_for_async_processing()`, but that method just calls `send_sync()`, so the
event is shipped to each channel (`$channel->send_event()`) during the request.
For HTTP channels that is a `wp_remote_request()` round trip; for remote syslog
it is a (TLS) socket connect + write; for the external-database channel it is a
connection + INSERT to a foreign DB.

## Who is affected

-   **Not** typical Premium users: a site with **no channels configured** pays
    only the ~15–20 ms per-request overhead measured above.
-   Anyone using the log-forwarding / SIEM feature (issue-573) — exactly the
    users who most expect it to be robust and unobtrusive. Impact scales with the
    number of enabled channels and each endpoint's latency, and compounds for
    actions that log several events.

## Proposed fix

Implement the async path that `queue_for_async_processing()` already promises,
so forwarding no longer blocks the response:

1. **Buffer during the request, flush on `shutdown`.** Collect events per
   request and send them after the response is flushed to the client
   (`fastcgi_finish_request()` where available, else on the `shutdown` hook).
   Simplest change, keeps "near-real-time" forwarding, removes it from the
   user-perceived latency. Good default.
2. **True async via wp-cron / Action Scheduler.** Enqueue events and forward in
   a background job. Fully decouples user actions from endpoint latency and
   survives slow/broken endpoints, at the cost of a queue store and slight
   delivery delay. Best for reliability at volume.

Either way: cap/timeout per-channel sends aggressively, and keep the existing
per-channel error tracking / auto-disable
(`trait-channel-error-tracking-trait.php`) so a slow or failing endpoint can
never stall or fail a user's request. The scaffolding
(`supports_async()`, `should_process_async()`, `queue_for_async_processing()`)
is already in place — only the queue/flush implementation is missing.

## Verification checklist (for the fix)

-   With remote channels enabled, a logged event (e.g. a post save) returns in
    ~the no-channel time; forwarding happens after the response.
-   A slow/unreachable endpoint does not delay or fail the triggering request.
-   Events are still delivered (or retried) and error tracking / auto-disable
    still trips on repeated failures.
-   No duplicate or dropped forwards under multiple events per request.
