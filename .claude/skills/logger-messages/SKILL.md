---
name: logger-messages
description: Writes user-friendly logger messages in active voice for Simple History event logs. Fixes passive voice issues. Triggered when creating/modifying logger classes in loggers/, writing getInfo() messages, or adding events to activity log. Triggers: "logger message", "active voice", "event log text".
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

- **Create:** Created, Added, Generated
- **Modify:** Updated, Changed, Edited
- **Delete:** Deleted, Removed, Trashed
- **Toggle:** Activated, Deactivated, Enabled, Disabled

## Avoid

- ❌ "was [verb]" - passive
- ❌ "has been [verb]" - passive
- ❌ Technical jargon users won't understand

## Detailed Resources

- [examples.md](examples.md) - Extensive examples across all WordPress contexts
- [integration.md](integration.md) - Complete logger class implementation
