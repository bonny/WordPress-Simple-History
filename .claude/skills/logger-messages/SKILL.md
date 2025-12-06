---
name: logger-messages
description: Writes user-friendly logger messages in active voice for Simple History event logs. Fixes passive voice issues. Use when creating or modifying logger classes in loggers/ directory, writing getInfo() messages, fixing passive voice, reviewing log message clarity, or adding new events to the activity log.
allowed-tools: Read, Grep, Glob
---

# Logger Message Writing Guidelines

Guidelines for writing clear, user-friendly messages for Simple History event logs.

## When to Use This Skill

**Trigger scenarios:**
- Writing new logger messages
- Updating existing logger messages
- Creating or modifying logger classes in `loggers/` directory
- Reviewing log message clarity and tone
- Adding new events to the activity log

## Supporting Files

For comprehensive guidance:
- **[examples.md](examples.md)**: Extensive examples across all WordPress contexts (plugins, posts, users, menus, system events)
- **[integration.md](integration.md)**: Complete logger class implementation examples and best practices

## Core Principle: Active Voice

Write messages in **active tone** as if someone is telling you what happened in the present or recent past.

**Think of it as**: "I just did this action" or "Someone just performed this task"

## The Golden Rule

✅ **Do**: Use active voice - what action was performed
❌ **Don't**: Use passive voice - what was done to something

## Quick Examples

```
✅ DO                          ❌ DON'T
─────────────────────────────────────────────
Activated plugin              Plugin was activated
Created menu                  Menu has been created
Updated settings              Settings were updated
Detected modifications        Modifications were detected
Changed password              Password was changed
Published post                Post has been published
```

See [examples.md](examples.md) for comprehensive examples across all contexts.

## Key Characteristics

### 1. User-Friendly Language

Write for regular users, not developers.

✅ "Updated site settings"
❌ "Modified wp_options table"

### 2. Concise and Clear

Remove filler words. Be direct.

✅ "Published post"
❌ "Successfully published the post to the website"

### 3. Specific When Needed

Include relevant context without overwhelming.

✅ "Changed site language to Spanish"
❌ "Updated something"

## Message Structure in Code

### In Logger Classes

```php
class SimpleHistoryPluginLogger extends SimpleLogger {
    public function getInfo() {
        return [
            'messages' => [
                // ✅ All active voice
                'plugin_activated' => __( 'Activated plugin', 'simple-history' ),
                'plugin_deactivated' => __( 'Deactivated plugin', 'simple-history' ),
                'plugin_installed' => __( 'Installed plugin', 'simple-history' ),
            ],
        ];
    }
}
```

### With Context Variables

```php
// ✅ Good - Clear placeholders
'post_updated' => __( 'Updated post "{post_title}"', 'simple-history' )

// ✅ Good - Multiple variables
'role_changed' => __( 'Changed role from {old_role} to {new_role}', 'simple-history' )
```

## Message Key Uniqueness

Message keys must be globally unique across all loggers, not just within a single logger.

### Why Uniqueness Matters

Message keys are used as message identifiers in syslog RFC 5424 logging, where unique MSGID values are required for proper log parsing and filtering. Duplicate keys would cause conflicts in external logging systems.

### Best Practices for Unique Keys

```php
// ✅ Good - Use descriptive prefixes that indicate the logger context
'plugin_activated'           // Plugin_Logger
'theme_switched'             // Theme_Logger
'user_logged_in'             // User_Logger
'privacy_data_exported'      // Privacy_Logger
'crontrol_event_added'       // Plugin_WP_Crontrol_Logger

// ❌ Bad - Generic keys that could conflict
'activated'                  // Too generic
'updated'                    // Could exist in multiple loggers
'deleted'                    // Ambiguous
```

### Verification

Before adding new message keys, verify they don't already exist in other loggers:

```bash
# Search for existing key usage
grep -r "'your_proposed_key'" loggers/
```

## Common Verbs to Use

- **Creation**: Created, Added, Generated
- **Modification**: Updated, Changed, Modified, Edited
- **Deletion**: Deleted, Removed, Trashed
- **Activation**: Activated, Deactivated, Enabled, Disabled
- **Publication**: Published, Unpublished, Scheduled
- **Detection**: Detected, Found, Discovered

## Verbs to Avoid

Never use passive constructions:
- ❌ "was [verb]" → "was activated"
- ❌ "has been [verb]" → "has been created"
- ❌ "got [verb]" → "got updated"

## Testing Your Messages

Ask yourself:
1. **Is it active?** Does it sound like someone telling you what they did?
2. **Is it clear?** Would a non-technical user understand?
3. **Is it concise?** Can you remove words without losing meaning?
4. **Is it specific?** Does it tell you exactly what happened?

## Summary

**Remember**: Write as if you're telling someone what you just did, not what happened to something.

**Pattern**: [I/Someone] + [Action verb] + [Object] + [Optional context]

**Always**: Active voice, user-friendly, concise, specific
