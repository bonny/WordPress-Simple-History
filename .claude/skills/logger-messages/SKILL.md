---
name: logger-messages
description: Enforces active voice for logger messages and the Event Details API. Use when writing a new logger class or modifying message arrays in getInfo().
allowed-tools: Read, Grep, Glob
---

# Logger Message Guidelines

Write clear, user-friendly messages for Simple History event logs.

## Core Principle: Active Voice

Write as if someone is telling you what they just did.

```
✅ DO                          ❌ DON'T
─────────────────────────────────────────────
Activated plugin              Plugin was activated
Created menu                  Menu has been created
Updated settings              Settings were updated
Published post                Post has been published
```

## In Logger Classes

```php
public function getInfo() {
    return [
        'messages' => [
            'plugin_activated' => __( 'Activated plugin', 'simple-history' ),
            'plugin_deactivated' => __( 'Deactivated plugin', 'simple-history' ),
            'post_updated' => __( 'Updated post "{post_title}"', 'simple-history' ),
        ],
    ];
}
```

## Message Key Uniqueness

Keys must be globally unique across all loggers (used as RFC 5424 MSGID).

```php
// ✅ Good - descriptive prefix
'plugin_activated', 'theme_switched', 'user_logged_in'

// ❌ Bad - too generic
'activated', 'updated', 'deleted'
```

Verify uniqueness: `grep -r "'your_key'" loggers/`

## Common Verbs

-   **Create:** Created, Added, Generated
-   **Modify:** Updated, Changed, Edited
-   **Delete:** Deleted, Removed, Trashed
-   **Toggle:** Activated, Deactivated, Enabled, Disabled

## Avoid

-   ❌ "was [verb]" - passive
-   ❌ "has been [verb]" - passive
-   ❌ Technical jargon users won't understand

## Context Key Naming

Prefix all context keys with the entity name to avoid collisions and keep keys self-documenting.

```php
// ✅ Good - prefixed with entity
'plugin_name', 'plugin_current_version', 'theme_new_version'
'site_health_status', 'site_health_label', 'site_health_badge_label'

// ❌ Bad - too generic
'test', 'label', 'status', 'name', 'version'
```

## Event Details Output

Use the Event Details API for `get_log_row_details_output()`. Never build raw HTML with `SimpleHistoryLogitem__keyValueTable`.

```php
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

public function get_log_row_details_output( $row ) {
    $group = new Event_Details_Group();
    $group->set_formatter( new Event_Details_Group_Table_Formatter() );
    $group->add_items(
        array(
            // Read value directly from context key.
            new Event_Details_Item( 'status', __( 'Status', 'simple-history' ) ),
            // Read new/prev pair from context (looks for key_new and key_prev).
            new Event_Details_Item( array( 'setting_name' ), __( 'Setting', 'simple-history' ) ),
        )
    );
    return $group;
}
```

**Formatters:**

-   `Event_Details_Group_Table_Formatter` — key-value table (default)
-   `Event_Details_Group_Diff_Table_Formatter` — before/after with diffs
-   `Event_Details_Group_Inline_Formatter` — compact inline text

**Manual values** (when context keys don't match conventions):

```php
( new Event_Details_Item( null, __( 'Label', 'simple-history' ) ) )
    ->set_new_value( $value )
```

See `docs/architecture/event-details.md` for full API reference.

## Detailed Resources

-   [examples.md](examples.md) - Extensive examples across all WordPress contexts
-   [integration.md](integration.md) - Complete logger class implementation
