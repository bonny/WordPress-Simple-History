# Handle large/structured setting values in the settings log

**Follows:** issue 232 (Log Simple History Premium's own settings changes)
**Project:** core (with a small premium registration)
**Date:** 2026-06-08
**Status:** Design approved

## Summary

The settings-change logger (core `Simple_History_Logger`, added in issue 232)
stores each changed option's old and new value in the event context and renders
them as _before → after_. For structured options whose value is a nested array —
alert rules, alert destinations, alert preset settings, and the Message Control
per-logger map — this produces an unreadable JSON blob in the log row and stores
the serialized value twice (old + new) per change in the contexts table.

This change makes such settings log only that they **changed**, with no
before/after value: the audit fact (what setting, by whom, when) is preserved,
while the unreadable display and the storage bloat are eliminated.

## Goals

-   A structured/large setting change is recorded as "changed" with no value.
-   No multi-KB JSON value is ever written to the contexts table for these options.
-   Scalar settings (booleans, counts, API keys, email lists) keep their normal
    _before → after_ rendering.
-   Add-ons control which keys are value-less via a filter, and a type-based
    safety net prevents accidental blobs for any key not explicitly marked.
-   No change to the event-details renderer.

## Non-goals

-   Summaries or item counts (e.g. "5 rules → 6 rules") — explicitly rejected
    during brainstorming as added per-option complexity spanning two repos.
-   A structured diff of which sub-item changed — rejected as over-engineering for
    this audit tool.
-   Truncating JSON — rejected (truncated JSON is unreadable and still partially
    bloats).

## Background

In `loggers/class-simple-history-logger.php` the watcher accumulates changes
keyed by option name and `commit_settings_changes()` writes one
`modified_settings` event, storing `{base}_prev` / `{base}_new` context pairs
(`base` = option name with the `simple_history_` prefix stripped). Today
`prepare_setting_value()` `wp_json_encode()`s non-scalar values, so the four
structured premium options serialize their entire nested array into both the
`_prev` and `_new` context values.

The four structured options (all nested arrays):

-   `simple_history_alert_destinations`
-   `simple_history_alert_preset_settings`
-   `simple_history_alert_custom_rules`
-   `shp_message_control`

## Design

### 1. Determining "changed-only" status

An option is logged as changed-only if **either** condition holds:

1. **Explicit opt-in** — the option name is returned by a new filter
   `simple_history/settings/changed_only_options` (a list of option names,
   mirroring the existing `simple_history/settings/redacted_options`).
2. **Non-scalar safety net** — its old or new value is not a scalar
   (`! is_scalar( $old ) || ! is_scalar( $new )`).

A helper resolves this:

```php
private function is_changed_only_setting( $option, $old_value, $new_value ) {
    if ( in_array( $option, $this->get_changed_only_settings(), true ) ) {
        return true;
    }

    return ! is_scalar( $old_value ) || ! is_scalar( $new_value );
}
```

`get_changed_only_settings()` caches the filter result in a property and accepts
a `$force_rebuild` parameter for tests — identical to the established
`get_tracked_settings()` / `get_redacted_settings()` pattern.

### 2. Recording a changed-only option

The watcher handlers (`on_tracked_option_updated`, `on_tracked_option_added`)
mark the accumulated entry so the commit step knows to omit values:

```php
$this->settings_changes[ $option ] = [ 'changed_only' => true ];
```

For scalar, non-marked options the existing behaviour is unchanged: the entry
records `old` / `new` (with redaction still applied to sensitive scalars).

### 3. Storage — `commit_settings_changes()`

For a changed-only entry, store a single short, translatable sentinel and omit
the `_prev` key and the real value entirely:

```php
if ( ! empty( $change['changed_only'] ) ) {
    $context[ "{$base}_new" ] = __( '(changed)', 'simple-history' );
    continue;
}

$context[ "{$base}_prev" ] = $change['old'];
$context[ "{$base}_new" ]  = $change['new'];
```

This is the core of the fix: one short string per changed structured option
instead of two serialized arrays.

Precedence: changed-only is decided in the watcher before redaction. Redaction
(`(value hidden)`) continues to apply only to scalar values that are stored;
a non-scalar value is never serialized, so it cannot leak regardless of
redaction.

### 4. Rendering — no changes

The existing generic renderer builds an `Event_Details_Item( [ $base ], $label )`
for every base found via a `_new` / `_prev` suffix. With only `{base}_new`
present, the item formatter's "added" branch renders **"Label: (changed)"**. No
renderer code changes are required.

### 5. Deletion edge case

`on_tracked_option_deleted()` currently records `old => <previous value>`,
`new => '(deleted)'`. For a changed-only option, the previous value must not be
stored: when the deleted option is changed-only, record it as changed-only so
the commit emits `{base}_new = '(deleted)'` with no stored old value. (Deletion
of these options is rare, but the path must not serialize the old array.)

### 6. Premium registration

Premium hooks the new filter to mark its four structured options:

```php
public function add_changed_only_settings( $changed_only ) {
    return array_merge( $changed_only, [
        'shp_message_control',
        'simple_history_alert_destinations',
        'simple_history_alert_preset_settings',
        'simple_history_alert_custom_rules',
    ] );
}
```

These are also caught by the non-scalar safety net; the explicit list documents
intent and guards against a future change that stores one of them as a string.

## Components and responsibilities

-   **Core `Simple_History_Logger`**: the `simple_history/settings/changed_only_options`
    filter, `get_changed_only_settings()` (cached), `is_changed_only_setting()`,
    the changed-only branches in the watcher handlers and `commit_settings_changes()`.
-   **Premium `Extended_Settings`**: registers its four structured keys via the
    new filter (one method + one `add_filter`).

## Error handling and edge cases

-   **No-op saves** still fire no `updated_option`, so nothing is recorded.
-   **Mixed change set** (a scalar and a structured option saved together): the
    scalar renders before → after, the structured one renders "(changed)", both in
    the same single event.
-   **Sensitive non-scalar** (hypothetical): never serialized, so no leak.
-   **Value text collision**: the sentinel `(changed)` is unambiguous in context;
    it is a stored value, not a separate schema.

## Testing (core wpunit, `SimpleHistorySettingsLoggerTest`)

-   An explicitly-marked scalar option → context has `{base}_new = (changed)`, no
    `{base}_prev`, and the real value does not appear in context.
-   An unmarked array option (safety net) → `{base}_new = (changed)`, no `_prev`,
    and the serialized array does not appear in context.
-   A normal scalar option → still records `{base}_prev` / `{base}_new` with real
    values (no regression).
-   The details renderer output for a changed-only option contains the label and
    the "(changed)" sentinel.

## Open questions for the implementation plan

-   Final wording of the sentinel string (`(changed)` vs. e.g.
    `(changed — details not logged)`). Default to `(changed)` unless review
    prefers the more explicit phrasing.

## References

-   Issue 232 design: `docs/superpowers/specs/2026-06-07-log-premium-settings-changes-design.md`
-   `loggers/class-simple-history-logger.php`
-   Premium `inc/class-extended-settings.php`
