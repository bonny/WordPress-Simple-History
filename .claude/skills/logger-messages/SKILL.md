---
name: logger-messages
description: Writes user-friendly logger messages in active voice for Simple History event logs. Fixes passive voice issues. Use when creating/modifying logger classes in loggers/ directory, writing getInfo() messages, fixing passive voice, or reviewing log message clarity.
---

# Logger Message Writing Guidelines

This skill provides guidelines for writing clear, user-friendly messages for Simple History event logs.

## When to Use This Skill

Invoke this skill when:
- Writing new logger messages
- Updating existing logger messages
- Creating or modifying logger classes in `loggers/` directory
- Reviewing log message clarity and tone

## Core Principle: Active Voice

Write messages in **active tone** as if someone is telling you what happened in the present or recent past.

**Think of it as**: "I just did this action" or "Someone just performed this task"

## The Golden Rule

✅ **Do**: Use active voice - what action was performed
❌ **Don't**: Use passive voice - what was done to something

## Quick Examples

### Good vs Bad

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

### Common Patterns

**Simple actions**: `[Action] [Object]`
```
"Created post"
"Deleted user"
"Updated plugin"
```

**With details**: `[Action] [Object] [Detail]`
```
"Changed site language to French"
"Updated plugin from version 1.0 to 1.1"
"Moved post to trash"
```

**System events**: `[Detected/Found] [What]`
```
"Detected file modifications"
"Found security issue"
"Completed backup"
```

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

## Detailed Information

For comprehensive examples and code integration:

- See @examples.md for extensive examples across all WordPress contexts (plugins, posts, users, menus, system events)
- See @integration.md for complete logger class implementation examples and best practices

## Summary

**Remember**: Write as if you're telling someone what you just did, not what happened to something.

**Pattern**: [I/Someone] + [Action verb] + [Object] + [Optional context]

**Always**: Active voice, user-friendly, concise, specific
