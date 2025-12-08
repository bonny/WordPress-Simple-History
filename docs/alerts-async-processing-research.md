# Alerts Async Processing Research

**Created:** December 2025
**Related to:** Issue #573 - Log Forwards/Destinations/Alerts
**Purpose:** Research and implementation notes for ensuring alerts don't degrade site performance

## Problem Statement

When many events are logged in quick succession (common during admin operations like bulk updates, imports, or plugin activations), synchronous alert processing could:

1. **Block page loads** - Each API call (Slack, webhook) adds 50-500ms latency
2. **Hit rate limits** - Slack allows only 1 msg/sec per webhook
3. **Cause timeouts** - 10+ alerts could mean 5+ seconds of blocking
4. **Create cascading failures** - If one API is slow, everything waits

**Goal:** Event logging should add < 1ms overhead, regardless of how many alerts are configured.

---

## Current Infrastructure

### Existing Async Patterns in Simple History

Simple History already uses WordPress cron for background processing:

| Service | Hook | Frequency | Pattern |
|---------|------|-----------|---------|
| DB Purge | `simple_history/maybe_purge_db` | Daily | Batch delete in chunks |
| Email Reports | `simple_history/email_report` | Weekly | Scheduled send |
| Auto Backfill | `simple_history/auto_backfill` | Once (60s delay) | Single event |
| File Cleanup | `simple_history_cleanup_log_files` | On-demand (throttled) | Max 1x/hour |

### Key Files

| Component | Path |
|-----------|------|
| Channels Manager | `inc/channels/class-channels-manager.php` |
| Base Channel | `inc/channels/class-channel.php` |
| Alert Rules Engine | `inc/channels/class-alert-rules-engine.php` |
| File Channel (async example) | `inc/channels/channels/class-file-channel.php` |
| DB Purge Cron | `inc/services/class-setup-purge-db-cron.php` |

### Existing TODO Placeholder

In `class-channels-manager.php` (lines 224-228):

```php
private function queue_for_async_processing( Channel_Interface $channel, $event_data, $formatted_message ) {
    // TODO: Implement async queue system using WordPress cron.
    // For now, fall back to synchronous processing.
    $this->send_sync( $channel, $event_data, $formatted_message );
}
```

The infrastructure is ready - just needs implementation.

---

## Recommended Architecture

### Event Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    EVENT LOGGED                              │
│              simple_history/log/inserted                     │
└─────────────────────┬───────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              FAST PATH (< 1ms)                               │
│                                                              │
│  1. Check: Any alert channels enabled? (cached)              │
│  2. Quick rule pre-filter (logger type, level)               │
│  3. If potential match → Add event ID to queue               │
│  4. Schedule processor if not already scheduled              │
│  5. RETURN immediately (non-blocking)                        │
│                                                              │
└─────────────────────┬───────────────────────────────────────┘
                      │
        ┌─────────────┴─────────────┐
        │                           │
        ▼                           ▼
┌───────────────────┐    ┌─────────────────────────────────────┐
│   User continues  │    │     BACKGROUND PROCESSOR            │
│   browsing...     │    │     (WP Cron, 10-30 sec later)      │
│                   │    │                                     │
│   No slowdown!    │    │  1. Load queued event IDs           │
│                   │    │  2. Fetch full event data (batch)   │
│                   │    │  3. Evaluate rules per channel      │
│                   │    │  4. Group alerts by channel         │
│                   │    │  5. Send (respecting rate limits)   │
│                   │    │  6. Handle errors, queue retries    │
│                   │    │  7. Clear processed items           │
│                   │    │  8. Reschedule if more in queue     │
│                   │    │                                     │
└───────────────────┘    └─────────────────────────────────────┘
```

### Processing Modes

| Mode | When to Use | Latency | Implementation |
|------|-------------|---------|----------------|
| **Immediate Sync** | File channel (fast) | 0ms | Direct write |
| **Queued Async** | Slack, Email, Webhooks | 10-60s | WP Cron single event |
| **Batched Async** | Daily digests | Hours | WP Cron recurring |

---

## Queue Storage Options

### Option A: WordPress Options Table (Recommended for MVP)

```php
// Storage
update_option('simple_history_alert_queue', $queue, false);

// Retrieval
$queue = get_option('simple_history_alert_queue', []);
```

**Pros:**
- Simple, no schema changes
- Persistent across requests
- Atomic updates with proper locking

**Cons:**
- Can grow large if queue backs up
- Single row = potential lock contention at very high volume

### Option B: Transients

```php
set_transient('simple_history_alert_queue', $queue, HOUR_IN_SECONDS);
```

**Pros:**
- Can use object cache if available
- Auto-expires (safety net)

**Cons:**
- Can be evicted unexpectedly
- Not reliable for critical alerts

### Option C: Custom Database Table

```sql
CREATE TABLE {prefix}simple_history_alert_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id BIGINT UNSIGNED NOT NULL,
    channel_slug VARCHAR(50) NOT NULL,
    status ENUM('pending', 'processing', 'failed') DEFAULT 'pending',
    attempts TINYINT UNSIGNED DEFAULT 0,
    created_at DATETIME NOT NULL,
    processed_at DATETIME NULL,
    error_message TEXT NULL,
    INDEX idx_status_created (status, created_at),
    INDEX idx_event_id (event_id)
);
```

**Pros:**
- Scalable to millions of items
- Query flexibility (status, retries, etc.)
- Audit trail

**Cons:**
- Schema migration needed
- More complex implementation

### Recommendation

**Start with Option A (Options table)** for simplicity. Migrate to **Option C (Custom table)** if:
- Queue regularly exceeds 1000 items
- Need retry tracking/auditing
- Enterprise customers require it

---

## Implementation Details

### 1. Fast Path Handler

```php
class Channels_Manager {

    private static $channels_enabled_cache = null;

    public function process_logged_event($event_data, $context, $logger) {
        // FAST: Check if any alert channels are enabled (cached)
        if (!$this->has_enabled_alert_channels()) {
            return;
        }

        // FAST: Quick pre-filter - does this event type have any rules?
        if (!$this->could_match_any_rule($event_data)) {
            return;
        }

        // FAST: Add to queue (just event ID, minimal data)
        $this->add_to_alert_queue($event_data['id']);

        // FAST: Schedule processor if not scheduled
        $this->maybe_schedule_alert_processor();

        // Return immediately - no blocking
    }

    private function has_enabled_alert_channels() {
        if (self::$channels_enabled_cache === null) {
            self::$channels_enabled_cache = $this->calculate_enabled_alert_channels();
        }
        return self::$channels_enabled_cache;
    }

    private function could_match_any_rule($event_data) {
        // Quick check: Is this logger type in any rule?
        // Don't do full rule evaluation here - just elimination
        $rules_cache = $this->get_rules_logger_types();
        return empty($rules_cache) || in_array($event_data['logger'], $rules_cache, true);
    }
}
```

### 2. Queue Management

```php
class Alert_Queue {

    const OPTION_KEY = 'simple_history_alert_queue';
    const MAX_QUEUE_SIZE = 1000;

    public function add($event_id) {
        $queue = $this->get_queue();

        // Prevent unbounded growth
        if (count($queue) >= self::MAX_QUEUE_SIZE) {
            // Log warning, drop oldest
            array_shift($queue);
        }

        $queue[] = [
            'event_id' => $event_id,
            'queued_at' => time(),
        ];

        $this->save_queue($queue);
    }

    public function get_queue() {
        return get_option(self::OPTION_KEY, []);
    }

    public function save_queue($queue) {
        update_option(self::OPTION_KEY, $queue, false); // false = don't autoload
    }

    public function clear_processed($event_ids) {
        $queue = $this->get_queue();
        $queue = array_filter($queue, function($item) use ($event_ids) {
            return !in_array($item['event_id'], $event_ids, true);
        });
        $this->save_queue(array_values($queue));
    }
}
```

### 3. Background Processor

```php
class Alert_Processor {

    const CRON_HOOK = 'simple_history/process_alert_queue';
    const BATCH_SIZE = 50;
    const PROCESS_INTERVAL = 15; // seconds

    public function __construct() {
        add_action(self::CRON_HOOK, [$this, 'process']);
    }

    public function maybe_schedule() {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return; // Already scheduled
        }
        wp_schedule_single_event(time() + self::PROCESS_INTERVAL, self::CRON_HOOK);
    }

    public function process() {
        $queue = $this->queue->get_queue();

        if (empty($queue)) {
            return;
        }

        // Process in batches
        $batch = array_slice($queue, 0, self::BATCH_SIZE);
        $event_ids = array_column($batch, 'event_id');

        // Fetch full event data in one query
        $events = $this->fetch_events($event_ids);

        // Group by channel to minimize API calls
        $by_channel = $this->group_by_matching_channel($events);

        // Process each channel
        $processed = [];
        foreach ($by_channel as $channel_slug => $channel_events) {
            $channel = $this->get_channel($channel_slug);

            if ($channel->supports_batching()) {
                // Send all events in one API call
                $this->send_batch($channel, $channel_events);
            } else {
                // Send one by one, respecting rate limits
                foreach ($channel_events as $event) {
                    $this->send_single($channel, $event);
                    $this->maybe_rate_limit_pause($channel);
                }
            }

            $processed = array_merge($processed, array_column($channel_events, 'id'));
        }

        // Clear processed items
        $this->queue->clear_processed($processed);

        // Reschedule if more items remain
        if (count($queue) > self::BATCH_SIZE) {
            $this->maybe_schedule();
        }
    }

    private function maybe_rate_limit_pause($channel) {
        $rate_limit = $channel->get_rate_limit(); // e.g., 1 per second for Slack
        if ($rate_limit) {
            usleep($rate_limit * 1000); // Convert ms to microseconds
        }
    }
}
```

### 4. Rate Limiting Per Channel

```php
abstract class Alert_Channel extends Channel {

    /**
     * Rate limit in milliseconds between sends.
     * Override in subclasses.
     */
    protected $rate_limit_ms = 0;

    public function get_rate_limit() {
        return $this->rate_limit_ms;
    }
}

class Slack_Alert_Channel extends Alert_Channel {
    protected $rate_limit_ms = 1000; // 1 second (Slack limit)
}

class Discord_Alert_Channel extends Alert_Channel {
    protected $rate_limit_ms = 200; // 5 per second
}

class Email_Alert_Channel extends Alert_Channel {
    protected $rate_limit_ms = 0; // No limit (use WP mail queue)
}

class Webhook_Alert_Channel extends Alert_Channel {
    protected $rate_limit_ms = 0; // User's responsibility
}
```

---

## Throttling & Debouncing

### Processor Scheduling Throttle

```php
class Alert_Processor {

    const MIN_SCHEDULE_INTERVAL = 10; // seconds

    private static $last_schedule_time = 0;

    public function maybe_schedule() {
        // Don't schedule too frequently
        if (time() - self::$last_schedule_time < self::MIN_SCHEDULE_INTERVAL) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        self::$last_schedule_time = time();
        wp_schedule_single_event(time() + self::PROCESS_INTERVAL, self::CRON_HOOK);
    }
}
```

### Per-Alert Throttle (User Configurable)

```php
class Alert_Rule {

    public function should_send($event_data) {
        // Check throttle setting
        $throttle = $this->get_setting('throttle_per_hour');

        if ($throttle > 0) {
            $sent_count = $this->get_sent_count_last_hour();
            if ($sent_count >= $throttle) {
                return false; // Throttled
            }
        }

        // Check cooldown
        $cooldown_minutes = $this->get_setting('cooldown_minutes');

        if ($cooldown_minutes > 0) {
            $last_sent = $this->get_last_sent_time();
            if (time() - $last_sent < ($cooldown_minutes * 60)) {
                return false; // In cooldown
            }
        }

        return true;
    }
}
```

---

## Error Handling & Retries

### Retry Strategy

```php
class Alert_Sender {

    const MAX_RETRIES = 5;
    const RETRY_DELAYS = [100, 500, 2000, 10000, 60000]; // ms

    public function send_with_retry($channel, $event_data) {
        $attempts = 0;

        while ($attempts < self::MAX_RETRIES) {
            try {
                $result = $channel->send($event_data);

                if ($result) {
                    return true; // Success
                }
            } catch (Exception $e) {
                $this->log_error($channel, $e, $attempts);
            }

            $delay = self::RETRY_DELAYS[$attempts] ?? 60000;
            usleep($delay * 1000);
            $attempts++;
        }

        // All retries failed
        $this->handle_permanent_failure($channel, $event_data);
        return false;
    }
}
```

### Auto-Disable on Repeated Failures

```php
class Channel_Health_Monitor {

    const FAILURE_THRESHOLD = 10;
    const FAILURE_WINDOW = 3600; // 1 hour

    public function record_failure($channel_slug, $error) {
        $failures = get_transient("sh_channel_failures_{$channel_slug}") ?: [];
        $failures[] = [
            'time' => time(),
            'error' => $error,
        ];

        // Keep only recent failures
        $failures = array_filter($failures, function($f) {
            return $f['time'] > (time() - self::FAILURE_WINDOW);
        });

        set_transient("sh_channel_failures_{$channel_slug}", $failures, self::FAILURE_WINDOW);

        // Check if should auto-disable
        if (count($failures) >= self::FAILURE_THRESHOLD) {
            $this->auto_disable_channel($channel_slug, $failures);
        }
    }

    private function auto_disable_channel($channel_slug, $failures) {
        // Disable the channel
        $channel = $this->get_channel($channel_slug);
        $channel->set_setting('enabled', false);
        $channel->set_setting('auto_disabled', true);
        $channel->set_setting('last_error', end($failures)['error']);

        // Notify admin
        $this->send_admin_notice($channel_slug, $failures);

        // Log as Simple History event
        SimpleLogger()->info(
            'Alert channel "{channel}" auto-disabled after {count} consecutive failures',
            [
                'channel' => $channel_slug,
                'count' => count($failures),
                'last_error' => end($failures)['error'],
            ]
        );
    }
}
```

---

## Performance Benchmarks

### Target Performance

| Metric | Target | Notes |
|--------|--------|-------|
| Event hook overhead | < 1ms | Fast path only |
| Queue add operation | < 5ms | Single DB write |
| Background batch (50 events) | < 30s | Depends on channels |
| Memory per queued item | < 100 bytes | Just event ID + timestamp |

### Measured Baselines (Current Simple History)

| Operation | Time | Notes |
|-----------|------|-------|
| Event insert (DB) | ~5-10ms | Sync, required |
| Context batch insert | ~2-5ms | Already optimized |
| File channel write | ~1-2ms | Sync, fast enough |
| get_option() | < 1ms | Cached after first call |
| update_option() | ~2-5ms | Single row update |

---

## Edge Cases

### 1. WP Cron Not Running

**Problem:** Low-traffic sites may have stale cron.

**Solutions:**
- Detect stale queue (items > 5 min old)
- Show admin warning: "Alert queue is backing up. Consider setting up a real cron job."
- Fallback: Process one item synchronously on admin page load
- Link to documentation on server cron setup

### 2. Queue Overflow

**Problem:** Too many events, queue grows unbounded.

**Solutions:**
- Hard limit (1000 items default)
- Drop oldest when full (FIFO)
- Log warning when dropping
- Admin notice when queue is > 80% full

### 3. Plugin Deactivation

**Problem:** Scheduled events remain after deactivation.

**Solution:**
```php
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('simple_history/process_alert_queue');
    delete_option('simple_history_alert_queue');
});
```

### 4. Concurrent Processing

**Problem:** Two cron runs process same items.

**Solution:**
```php
public function process() {
    // Acquire lock
    if (!$this->acquire_lock()) {
        return; // Another process is handling it
    }

    try {
        // ... process queue ...
    } finally {
        $this->release_lock();
    }
}

private function acquire_lock() {
    return set_transient('sh_alert_processor_lock', time(), 60);
}
```

### 5. Large Event Context

**Problem:** Event with huge context data.

**Solution:**
- Store only event ID in queue (not full data)
- Fetch full data in batch during processing
- Consider context size limits in rule evaluation

---

## Action Scheduler Consideration

### When to Use Action Scheduler

| Scenario | WP Cron | Action Scheduler |
|----------|---------|------------------|
| < 100 alerts/day | ✅ | Overkill |
| 100-1000 alerts/day | ✅ | Optional |
| 1000+ alerts/day | ⚠️ | ✅ Recommended |
| Need admin UI for queue | ❌ | ✅ Built-in |
| Zero dependencies | ✅ | ❌ Requires library |
| WooCommerce sites | N/A | ✅ Already installed |

### Implementation (If Needed)

```php
// Check if Action Scheduler is available
if (function_exists('as_schedule_single_action')) {
    as_schedule_single_action(time() + 15, 'simple_history/process_alert_queue');
} else {
    wp_schedule_single_event(time() + 15, 'simple_history/process_alert_queue');
}
```

### Recommendation

**Start with WP Cron** (matches existing patterns, zero dependencies). Add Action Scheduler support as optional enhancement for high-volume sites or when WooCommerce is active.

---

## Open Questions

1. **Queue persistence vs speed trade-off**
   - Options table is persistent but slightly slower
   - Object cache is faster but can be evicted
   - Which is more important for alerts?

2. **Batch size optimization**
   - 50 items per batch? 100?
   - Should it be configurable?
   - Different batch sizes for different channels?

3. **Real-time priority alerts**
   - Some alerts may need < 5s delivery (security events)
   - Should we have a "high priority" flag that bypasses queue?
   - Or shorter queue interval for critical events?

4. **Multi-site considerations**
   - Separate queues per site? Or network-wide?
   - How to handle network-activated plugin?

5. **Queue monitoring UI**
   - Should we show queue status in admin?
   - "X alerts pending, last processed Y seconds ago"
   - Useful for debugging but adds complexity

---

## Implementation Phases

### Phase 1: Basic Async (MVP)

- [ ] Implement `Alert_Queue` class with options storage
- [ ] Implement `Alert_Processor` with WP Cron
- [ ] Wire up `queue_for_async_processing()` in Channels Manager
- [ ] Add basic throttling (schedule interval)
- [ ] Add lock mechanism to prevent concurrent processing
- [ ] Handle plugin deactivation cleanup

### Phase 2: Robustness

- [ ] Add retry mechanism with exponential backoff
- [ ] Add auto-disable on repeated failures
- [ ] Add admin notice for disabled channels
- [ ] Add queue overflow handling
- [ ] Add per-channel rate limiting

### Phase 3: Advanced

- [ ] Add per-rule throttle and cooldown settings
- [ ] Add queue monitoring UI in settings
- [ ] Add Action Scheduler support (optional)
- [ ] Add high-priority bypass for critical alerts
- [ ] Add queue health metrics

---

## Scheduler Abstraction Layer

### Decision: Hybrid Approach

Use Action Scheduler if available (WooCommerce sites, ~10-20% of WordPress), fall back to WP Cron otherwise. Bundle Action Scheduler in premium for guaranteed reliability.

### Wrapper Class Design

```php
<?php
/**
 * Background_Job class.
 *
 * Abstracts background job scheduling using Action Scheduler (if available)
 * or WP Cron as fallback. Provides consistent API regardless of backend.
 *
 * @package SimpleHistory
 */

namespace Simple_History;

/**
 * Background job scheduler abstraction.
 */
class Background_Job {

    /**
     * Group name for Action Scheduler jobs.
     */
    const AS_GROUP = 'simple-history';

    /**
     * Check if Action Scheduler is available.
     *
     * @return bool
     */
    public static function has_action_scheduler(): bool {
        return function_exists( 'as_schedule_single_action' )
            && function_exists( 'as_unschedule_all_actions' )
            && function_exists( 'as_next_scheduled_action' );
    }

    /**
     * Get the current scheduler backend name.
     *
     * @return string 'action_scheduler' or 'wp_cron'
     */
    public static function get_backend(): string {
        return self::has_action_scheduler() ? 'action_scheduler' : 'wp_cron';
    }

    /**
     * Schedule a single action to run once.
     *
     * @param int    $timestamp Unix timestamp when to run.
     * @param string $hook      Action hook name.
     * @param array  $args      Arguments to pass to the hook.
     * @return int|bool Action ID (AS) or true (WP Cron) on success, false on failure.
     */
    public static function schedule_single( int $timestamp, string $hook, array $args = [] ) {
        if ( self::has_action_scheduler() ) {
            return as_schedule_single_action( $timestamp, $hook, $args, self::AS_GROUP );
        }

        // WP Cron fallback
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            return wp_schedule_single_event( $timestamp, $hook, $args );
        }

        return false; // Already scheduled
    }

    /**
     * Schedule a single action with delay in seconds.
     *
     * @param int    $delay_seconds Seconds from now.
     * @param string $hook          Action hook name.
     * @param array  $args          Arguments to pass to the hook.
     * @return int|bool Action ID or true on success.
     */
    public static function schedule_single_delayed( int $delay_seconds, string $hook, array $args = [] ) {
        return self::schedule_single( time() + $delay_seconds, $hook, $args );
    }

    /**
     * Schedule a recurring action.
     *
     * @param int    $timestamp         Unix timestamp for first run.
     * @param int    $interval_seconds  Interval between runs in seconds.
     * @param string $hook              Action hook name.
     * @param array  $args              Arguments to pass to the hook.
     * @return int|bool Action ID or true on success.
     */
    public static function schedule_recurring( int $timestamp, int $interval_seconds, string $hook, array $args = [] ) {
        if ( self::has_action_scheduler() ) {
            return as_schedule_recurring_action( $timestamp, $interval_seconds, $hook, $args, self::AS_GROUP );
        }

        // WP Cron fallback - need to use named schedule or create custom
        $schedules = wp_get_schedules();
        $schedule_name = self::get_or_create_schedule( $interval_seconds, $schedules );

        if ( ! wp_next_scheduled( $hook, $args ) ) {
            return wp_schedule_event( $timestamp, $schedule_name, $hook, $args );
        }

        return false;
    }

    /**
     * Check if an action is already scheduled.
     *
     * @param string $hook Action hook name.
     * @param array  $args Arguments (must match exactly).
     * @return bool|int False if not scheduled, timestamp if scheduled.
     */
    public static function is_scheduled( string $hook, array $args = [] ) {
        if ( self::has_action_scheduler() ) {
            $next = as_next_scheduled_action( $hook, $args, self::AS_GROUP );
            return $next !== false ? $next : false;
        }

        return wp_next_scheduled( $hook, $args );
    }

    /**
     * Unschedule all instances of an action.
     *
     * @param string $hook Action hook name.
     * @param array  $args Arguments (optional, null = all).
     * @return void
     */
    public static function unschedule_all( string $hook, ?array $args = null ): void {
        if ( self::has_action_scheduler() ) {
            if ( $args !== null ) {
                as_unschedule_all_actions( $hook, $args, self::AS_GROUP );
            } else {
                as_unschedule_all_actions( $hook, [], self::AS_GROUP );
            }
            return;
        }

        // WP Cron fallback
        if ( $args !== null ) {
            $timestamp = wp_next_scheduled( $hook, $args );
            while ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook, $args );
                $timestamp = wp_next_scheduled( $hook, $args );
            }
        } else {
            wp_clear_scheduled_hook( $hook );
        }
    }

    /**
     * Unschedule the next instance of an action.
     *
     * @param string $hook Action hook name.
     * @param array  $args Arguments.
     * @return bool True on success.
     */
    public static function unschedule_next( string $hook, array $args = [] ): bool {
        if ( self::has_action_scheduler() ) {
            $action_id = as_next_scheduled_action( $hook, $args, self::AS_GROUP );
            if ( $action_id ) {
                as_unschedule_action( $hook, $args, self::AS_GROUP );
                return true;
            }
            return false;
        }

        $timestamp = wp_next_scheduled( $hook, $args );
        if ( $timestamp ) {
            return wp_unschedule_event( $timestamp, $hook, $args );
        }

        return false;
    }

    /**
     * Run an action immediately/async (if supported).
     *
     * @param string $hook Action hook name.
     * @param array  $args Arguments.
     * @return int|bool Action ID or scheduled timestamp.
     */
    public static function dispatch_async( string $hook, array $args = [] ) {
        if ( self::has_action_scheduler() ) {
            // Action Scheduler can run "now" actions
            return as_enqueue_async_action( $hook, $args, self::AS_GROUP );
        }

        // WP Cron: Schedule for 1 second from now (soonest possible)
        return self::schedule_single( time() + 1, $hook, $args );
    }

    /**
     * Get count of pending actions for a hook.
     *
     * @param string $hook Action hook name.
     * @return int Count of pending actions.
     */
    public static function get_pending_count( string $hook ): int {
        if ( self::has_action_scheduler() ) {
            return as_get_scheduled_actions(
                [
                    'hook'   => $hook,
                    'status' => \ActionScheduler_Store::STATUS_PENDING,
                    'group'  => self::AS_GROUP,
                ],
                'count'
            );
        }

        // WP Cron: Can only check if next is scheduled
        return wp_next_scheduled( $hook ) ? 1 : 0;
    }

    /**
     * Get or create a WP Cron schedule for custom intervals.
     *
     * @param int   $interval_seconds Interval in seconds.
     * @param array $schedules        Existing schedules.
     * @return string Schedule name.
     */
    private static function get_or_create_schedule( int $interval_seconds, array $schedules ): string {
        // Check if matching schedule exists
        foreach ( $schedules as $name => $schedule ) {
            if ( $schedule['interval'] === $interval_seconds ) {
                return $name;
            }
        }

        // Create custom schedule name
        $schedule_name = 'simple_history_every_' . $interval_seconds . '_seconds';

        // Register the schedule
        add_filter(
            'cron_schedules',
            function ( $schedules ) use ( $schedule_name, $interval_seconds ) {
                $schedules[ $schedule_name ] = [
                    'interval' => $interval_seconds,
                    'display'  => sprintf( 'Every %d seconds (Simple History)', $interval_seconds ),
                ];
                return $schedules;
            }
        );

        return $schedule_name;
    }

    /**
     * Clean up all Simple History scheduled actions.
     * Call on plugin deactivation.
     *
     * @return void
     */
    public static function cleanup_all(): void {
        if ( self::has_action_scheduler() ) {
            as_unschedule_all_actions( '', [], self::AS_GROUP );
            return;
        }

        // WP Cron: Unschedule known hooks
        $hooks = [
            'simple_history/process_alert_queue',
            'simple_history/maybe_purge_db',
            'simple_history/email_report',
            'simple_history/auto_backfill',
            'simple_history_cleanup_log_files',
        ];

        foreach ( $hooks as $hook ) {
            wp_clear_scheduled_hook( $hook );
        }
    }
}
```

### Usage Examples

```php
use Simple_History\Background_Job;

// Schedule alert processing in 15 seconds
Background_Job::schedule_single_delayed( 15, 'simple_history/process_alert_queue' );

// Check if already scheduled before scheduling
if ( ! Background_Job::is_scheduled( 'simple_history/process_alert_queue' ) ) {
    Background_Job::schedule_single_delayed( 15, 'simple_history/process_alert_queue' );
}

// Dispatch immediately (async if Action Scheduler available)
Background_Job::dispatch_async( 'simple_history/send_critical_alert', [ 'event_id' => 123 ] );

// Schedule recurring job (e.g., queue health check every 5 minutes)
Background_Job::schedule_recurring( time(), 300, 'simple_history/check_queue_health' );

// Clean up on deactivation
register_deactivation_hook( __FILE__, [ Background_Job::class, 'cleanup_all' ] );

// Check which backend is being used (for debugging/admin display)
$backend = Background_Job::get_backend(); // 'action_scheduler' or 'wp_cron'
```

### Admin Notice for WP Cron Fallback

```php
class Alert_Admin_Notices {

    public function __construct() {
        add_action( 'admin_notices', [ $this, 'maybe_show_cron_notice' ] );
    }

    public function maybe_show_cron_notice(): void {
        // Only show if alerts are enabled and using WP Cron
        if ( ! $this->has_enabled_alerts() ) {
            return;
        }

        if ( Background_Job::has_action_scheduler() ) {
            return;
        }

        // Check if dismissed
        if ( get_option( 'simple_history_dismiss_cron_notice' ) ) {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible" data-notice="simple-history-cron">
            <p>
                <strong>Simple History:</strong>
                <?php
                esc_html_e(
                    'For more reliable alert delivery, consider installing WooCommerce or the Action Scheduler plugin. Currently using WP-Cron which depends on site traffic.',
                    'simple-history'
                );
                ?>
                <a href="https://wordpress.org/plugins/action-scheduler/" target="_blank">
                    <?php esc_html_e( 'Learn more', 'simple-history' ); ?>
                </a>
            </p>
        </div>
        <?php
    }
}
```

### Integration with Existing Services

Migrate existing cron usage to use the new wrapper:

```php
// Before (in Setup_Purge_DB_Cron):
wp_schedule_event( time(), 'daily', 'simple_history/maybe_purge_db' );

// After:
Background_Job::schedule_recurring( time(), DAY_IN_SECONDS, 'simple_history/maybe_purge_db' );
```

```php
// Before (in Auto_Backfill_Service):
wp_schedule_single_event( time() + 60, self::CRON_HOOK );

// After:
Background_Job::schedule_single_delayed( 60, self::CRON_HOOK );
```

### Benefits of Abstraction

| Benefit | Description |
|---------|-------------|
| **Consistent API** | Same method calls regardless of backend |
| **Automatic upgrade** | Sites that add WooCommerce automatically get better reliability |
| **Testable** | Can mock the class in unit tests |
| **Future-proof** | Easy to add new backends (e.g., server cron integration) |
| **Debugging** | `get_backend()` shows which system is active |

---

## References

- [Action Scheduler Performance Guide](https://actionscheduler.org/perf/)
- [Action Scheduler Usage Guide](https://actionscheduler.org/usage/)
- [Action Scheduler FAQ](https://actionscheduler.org/faq/)
- [WooCommerce Best Practices for Action Scheduler](https://developer.woocommerce.com/2021/10/12/best-practices-for-deconflicting-different-versions-of-action-scheduler/)
- [WordPress.tv: Why Action Scheduler Does It Better](https://wordpress.tv/2025/05/19/crond-service-wp-cron-and-why-action-scheduler-does-it-better/)
- [WordPress Background Processing at Scale](https://www.sitebox.io/how-to-implement-background-processing-in-wordpress-without-slowing-down-ux/)
- [Delicious Brains - Background Processing in WordPress](https://deliciousbrains.com/background-processing-wordpress/)
- [WordPress VIP - Action Scheduler](https://docs.wpvip.com/wordpress-on-vip/action-scheduler/)

---

## Notes & Findings

*(Add implementation notes here as work progresses)*
