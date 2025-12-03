# Logger Message Examples

This file contains comprehensive examples of good and bad logger messages across different WordPress contexts.

## Plugin Actions

### ✅ Correct Examples
- "Activated plugin"
- "Deactivated plugin"
- "Installed plugin"
- "Updated plugin"
- "Deleted plugin"

### ❌ Incorrect Examples
- "Plugin was activated"
- "Plugin has been deactivated"
- "Plugin was installed"
- "The plugin got updated"

**Why**: Passive voice distances the action from the actor. Active voice makes it clear who did what.

---

## Post/Page Actions

### ✅ Correct Examples
- "Created post"
- "Updated page"
- "Deleted draft"
- "Published post"
- "Moved post to trash"

### ❌ Incorrect Examples
- "Post was created"
- "Page has been updated"
- "Draft was deleted"
- "Post has been published"

**Why**: Users think "I created a post" not "A post was created by me."

---

## Menu Actions

### ✅ Correct Examples
- "Created menu"
- "Updated menu structure"
- "Deleted menu item"
- "Added menu item"

### ❌ Incorrect Examples
- "Menu has been created"
- "Menu structure was updated"
- "Menu item was deleted"

---

## User Actions

### ✅ Correct Examples
- "Logged in"
- "Logged out"
- "Changed password"
- "Updated profile"
- "Failed login attempt"

### ❌ Incorrect Examples
- "User was logged in"
- "User has been logged out"
- "Password was changed"

---

## System Events

### ✅ Correct Examples
- "Detected file modifications"
- "Detected database changes"
- "Found security issue"
- "Completed backup"

### ❌ Incorrect Examples
- "File modifications were detected"
- "Database changes have been detected"
- "Security issue was found"

**Why**: Even system events sound more immediate and clear in active voice.

---

## Message Characteristics Examples

### 1. User-Friendly Language

#### ✅ User-Friendly
- "Updated site settings"
- "Changed theme"
- "Added new user"

#### ❌ Too Technical
- "Modified wp_options table"
- "Switched active stylesheet"
- "Inserted row into wp_users"

**Rule**: Write for website owners, not database administrators.

---

### 2. Concise and Clear

#### ✅ Concise
- "Published post"
- "Updated settings"
- "Deleted comment"

#### ❌ Too Verbose
- "Successfully published the post to the website"
- "Made changes to the configuration settings"
- "Removed the comment from the database"

**Rule**: Remove filler words. Every word should add value.

---

### 3. Specific When Needed

#### ✅ Good Specificity
- "Updated post title"
- "Changed site language to Spanish"
- "Activated theme Twenty Twenty-Four"

#### ❌ Too Vague
- "Updated something"
- "Changed setting"
- "Activated item"

**Rule**: Be specific about what changed, but avoid overwhelming detail.

---

## Message Structures

### Simple Actions

Format: **[Action] [Object]**

```
"Created post"
"Deleted user"
"Updated plugin"
"Activated theme"
"Published page"
```

### Actions with Details

Format: **[Action] [Object] [Detail]**

```
"Changed site language to French"
"Updated plugin from version 1.0 to 1.1"
"Moved post to trash"
"Scheduled post for tomorrow"
"Set user role to Editor"
```

### Detection/Observation

Format: **[Detected/Found] [What]**

```
"Detected file modifications"
"Found security vulnerability"
"Detected unauthorized access attempt"
"Discovered duplicate posts"
"Found missing database tables"
```

---

## Context Variables Examples

### Good Usage with Placeholders

```php
// Clear what the variables represent
sprintf(
    __( 'Updated post "%1$s"', 'simple-history' ),
    $post_title
);

sprintf(
    __( 'Changed role from %1$s to %2$s', 'simple-history' ),
    $old_role,
    $new_role
);

sprintf(
    __( 'Uploaded file "%1$s" (%2$s)', 'simple-history' ),
    $filename,
    $filesize
);
```

### Keep It Readable

Even with placeholders, the message structure should be clear:

```php
// Good - clear even without seeing values
__( 'Activated plugin', 'simple-history' )

// Good - clear what the placeholder represents
sprintf(
    __( 'Updated %s from %s to %s', 'simple-history' ),
    $setting_name,
    $old_value,
    $new_value
)

// Good - specific context included
sprintf(
    __( 'User %1$s changed password for %2$s', 'simple-history' ),
    $admin_username,
    $target_username
)
```

---

## Common Verbs Reference

### Creation
- Created
- Added
- Generated
- Made
- Built

### Modification
- Updated
- Changed
- Modified
- Edited
- Adjusted
- Revised

### Deletion
- Deleted
- Removed
- Trashed
- Cleared
- Erased

### Activation/Status
- Activated
- Deactivated
- Enabled
- Disabled
- Turned on
- Turned off

### Publication
- Published
- Unpublished
- Scheduled
- Posted
- Released

### Detection
- Detected
- Found
- Discovered
- Identified
- Spotted

### Completion
- Completed
- Finished
- Processed
- Executed

---

## Verbs to Avoid

### Passive Constructions

❌ **Never use**:
- "was [verb]" → "was activated"
- "has been [verb]" → "has been created"
- "got [verb]" → "got updated"
- "were [verb]" → "were modified"

✅ **Use instead**:
- "Activated"
- "Created"
- "Updated"
- "Modified"

---

## Quick Reference Card

```
SITUATION                     ✅ DO                          ❌ DON'T
──────────────────────────────────────────────────────────────────────────────
Plugin activation             Activated plugin               Plugin was activated
Menu creation                 Created menu                   Menu has been created
Settings update               Updated settings               Settings were updated
File detection                Detected modifications         Modifications were detected
Password change               Changed password               Password was changed
Post publication              Published post                 Post has been published
User login                    Logged in                      User was logged in
Failed authentication         Failed login attempt           Login attempt failed
Theme switch                  Activated theme                Theme was activated
Comment approval              Approved comment               Comment was approved
Widget addition               Added widget                   Widget was added
Database backup               Completed backup               Backup was completed
```

---

## Testing Your Messages Checklist

For each message you write, ask:

1. ✅ **Is it active?**
   - Does it sound like someone telling you what they did?
   - No passive voice (was/were/has been)?

2. ✅ **Is it clear?**
   - Would a non-technical user understand it?
   - No jargon or technical terms?

3. ✅ **Is it concise?**
   - Can you remove words without losing meaning?
   - No filler words like "successfully" or "has been"?

4. ✅ **Is it specific?**
   - Does it tell you exactly what happened?
   - Includes relevant context (what, not how)?

---

## Real-World Examples

### Example 1: Post Updates

```php
// ❌ Bad
'post_updated' => __( 'The post has been updated successfully', 'simple-history' )

// ✅ Good
'post_updated' => __( 'Updated post', 'simple-history' )

// ✅ Even Better (with context)
'post_updated' => __( 'Updated post "{post_title}"', 'simple-history' )
```

### Example 2: Plugin Management

```php
// ❌ Bad
'plugin_activated' => __( 'A plugin was activated on the site', 'simple-history' )

// ✅ Good
'plugin_activated' => __( 'Activated plugin', 'simple-history' )

// ✅ Even Better (with details)
'plugin_activated' => __( 'Activated plugin "{plugin_name}"', 'simple-history' )
```

### Example 3: User Management

```php
// ❌ Bad
'user_created' => __( 'A new user account has been created', 'simple-history' )

// ✅ Good
'user_created' => __( 'Created user', 'simple-history' )

// ✅ Even Better (with specifics)
'user_created' => __( 'Created user "{username}" with role {role}', 'simple-history' )
```

### Example 4: System Events

```php
// ❌ Bad
'files_changed' => __( 'Changes to files were detected by the system', 'simple-history' )

// ✅ Good
'files_changed' => __( 'Detected file modifications', 'simple-history' )

// ✅ Even Better (with count)
'files_changed' => __( 'Detected modifications in {count} files', 'simple-history' )
```

---

## Summary

**Remember**: Write as if you're telling someone what you just did, not describing what happened to something.

**Pattern**: [I/Someone] + [Action verb] + [Object] + [Optional context]

**Golden Rules**:
1. Active voice always
2. User-friendly language
3. Concise and clear
4. Specific when helpful
