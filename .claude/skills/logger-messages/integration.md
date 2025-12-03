# Logger Message Integration with Code

This file provides practical examples of integrating logger messages into Simple History logger classes.

## Logger Class Structure

### Basic Logger Class

```php
<?php

class SimpleHistoryPluginLogger extends SimpleLogger {

    /**
     * Logger slug (unique identifier)
     */
    public $slug = 'SimpleHistoryPluginLogger';

    /**
     * Get logger info
     *
     * @return array Logger configuration
     */
    public function getInfo() {
        return [
            'name' => __( 'Plugin Logger', 'simple-history' ),
            'description' => __( 'Logs plugin installations, activations, updates, and deletions', 'simple-history' ),
            'capability' => 'activate_plugins',
            'messages' => [
                // ✅ All messages use active voice
                'plugin_activated' => __( 'Activated plugin', 'simple-history' ),
                'plugin_deactivated' => __( 'Deactivated plugin', 'simple-history' ),
                'plugin_installed' => __( 'Installed plugin', 'simple-history' ),
                'plugin_updated' => __( 'Updated plugin', 'simple-history' ),
                'plugin_deleted' => __( 'Deleted plugin', 'simple-history' ),
            ],
        ];
    }
}
```

---

## Logging Events

### Simple Event Logging

```php
// ✅ Good - Clear, active voice message
$this->infoMessage(
    'plugin_activated',
    [
        'plugin_name' => $plugin_name,
        'plugin_slug' => $plugin_slug,
    ]
);
```

### Event with Context

```php
// ✅ Good - Includes relevant context
$this->infoMessage(
    'plugin_updated',
    [
        'plugin_name' => $plugin_name,
        'plugin_slug' => $plugin_slug,
        'old_version' => $old_version,
        'new_version' => $new_version,
    ]
);
```

### Error/Warning Events

```php
// ✅ Good - Active voice even for failures
$this->warningMessage(
    'plugin_activation_failed',
    [
        'plugin_name' => $plugin_name,
        'error_message' => $error_message,
    ]
);
```

---

## Message Definitions with Placeholders

### Using Context Variables

```php
public function getInfo() {
    return [
        'messages' => [
            // ✅ Good - Clear placeholder usage
            'plugin_activated' => __(
                'Activated plugin "{plugin_name}"',
                'simple-history'
            ),

            // ✅ Good - Multiple placeholders with clear meaning
            'plugin_updated' => __(
                'Updated plugin "{plugin_name}" from version {old_version} to {new_version}',
                'simple-history'
            ),

            // ✅ Good - Conditional context
            'plugin_installed' => __(
                'Installed plugin "{plugin_name}" version {version}',
                'simple-history'
            ),
        ],
    ];
}
```

### Numbered Placeholders

```php
public function getInfo() {
    return [
        'messages' => [
            // ✅ Good - Numbered placeholders for sprintf
            'user_role_changed' => __(
                'Changed role from %1$s to %2$s',
                'simple-history'
            ),

            'post_updated' => __(
                'Updated post "%1$s" (ID: %2$d)',
                'simple-history'
            ),
        ],
    ];
}

// Usage
$this->infoMessage(
    'user_role_changed',
    [
        'old_role' => $old_role,
        'new_role' => $new_role,
    ]
);
```

---

## Complete Logger Example

### Post Logger

```php
<?php

class SimpleHistoryPostLogger extends SimpleLogger {

    public $slug = 'SimpleHistoryPostLogger';

    public function getInfo() {
        return [
            'name' => __( 'Post Logger', 'simple-history' ),
            'description' => __( 'Logs post creation, updates, and deletion', 'simple-history' ),
            'capability' => 'edit_posts',
            'messages' => [
                // ✅ All active voice
                'post_created' => __( 'Created post "{post_title}"', 'simple-history' ),
                'post_updated' => __( 'Updated post "{post_title}"', 'simple-history' ),
                'post_deleted' => __( 'Deleted post "{post_title}"', 'simple-history' ),
                'post_published' => __( 'Published post "{post_title}"', 'simple-history' ),
                'post_trashed' => __( 'Moved post "{post_title}" to trash', 'simple-history' ),
                'post_restored' => __( 'Restored post "{post_title}" from trash', 'simple-history' ),
            ],
        ];
    }

    public function loaded() {
        // Hook into WordPress post actions
        add_action( 'save_post', [ $this, 'on_save_post' ], 10, 3 );
        add_action( 'delete_post', [ $this, 'on_delete_post' ], 10, 2 );
        add_action( 'transition_post_status', [ $this, 'on_transition_post_status' ], 10, 3 );
    }

    public function on_save_post( $post_id, $post, $update ) {
        // Ignore autosaves and revisions
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        $message_key = $update ? 'post_updated' : 'post_created';

        // ✅ Good - Log with active voice message
        $this->infoMessage(
            $message_key,
            [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_status' => $post->post_status,
            ]
        );
    }

    public function on_delete_post( $post_id, $post ) {
        // ✅ Good - Active voice for deletion
        $this->infoMessage(
            'post_deleted',
            [
                'post_id' => $post_id,
                'post_title' => $post->post_title,
                'post_type' => $post->post_type,
            ]
        );
    }
}
```

---

## User Logger Example

```php
<?php

class SimpleHistoryUserLogger extends SimpleLogger {

    public $slug = 'SimpleHistoryUserLogger';

    public function getInfo() {
        return [
            'name' => __( 'User Logger', 'simple-history' ),
            'description' => __( 'Logs user registrations, profile updates, and deletions', 'simple-history' ),
            'capability' => 'list_users',
            'messages' => [
                // ✅ All active voice
                'user_created' => __( 'Created user "{username}"', 'simple-history' ),
                'user_updated' => __( 'Updated user profile for "{username}"', 'simple-history' ),
                'user_deleted' => __( 'Deleted user "{username}"', 'simple-history' ),
                'user_login' => __( 'Logged in', 'simple-history' ),
                'user_logout' => __( 'Logged out', 'simple-history' ),
                'user_login_failed' => __( 'Failed login attempt for username "{username}"', 'simple-history' ),
                'user_role_changed' => __( 'Changed role from {old_role} to {new_role}', 'simple-history' ),
            ],
        ];
    }

    public function loaded() {
        add_action( 'user_register', [ $this, 'on_user_register' ] );
        add_action( 'profile_update', [ $this, 'on_profile_update' ], 10, 2 );
        add_action( 'delete_user', [ $this, 'on_delete_user' ] );
        add_action( 'wp_login', [ $this, 'on_wp_login' ], 10, 2 );
        add_action( 'wp_login_failed', [ $this, 'on_wp_login_failed' ] );
    }

    public function on_user_register( $user_id ) {
        $user = get_userdata( $user_id );

        // ✅ Good - Active voice
        $this->infoMessage(
            'user_created',
            [
                'user_id' => $user_id,
                'username' => $user->user_login,
                'user_email' => $user->user_email,
                'role' => implode( ', ', $user->roles ),
            ]
        );
    }

    public function on_wp_login_failed( $username ) {
        // ✅ Good - Active voice even for failures
        $this->warningMessage(
            'user_login_failed',
            [
                'username' => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]
        );
    }
}
```

---

## System Logger Example

```php
<?php

class SimpleHistorySystemLogger extends SimpleLogger {

    public $slug = 'SimpleHistorySystemLogger';

    public function getInfo() {
        return [
            'name' => __( 'System Logger', 'simple-history' ),
            'description' => __( 'Logs system events and file changes', 'simple-history' ),
            'capability' => 'manage_options',
            'messages' => [
                // ✅ Active voice for system events
                'files_modified' => __( 'Detected modifications in {count} files', 'simple-history' ),
                'database_updated' => __( 'Updated database to version {version}', 'simple-history' ),
                'cron_completed' => __( 'Completed scheduled task "{task_name}"', 'simple-history' ),
                'backup_created' => __( 'Created backup of {item_type}', 'simple-history' ),
                'security_scan_completed' => __( 'Completed security scan', 'simple-history' ),
                'security_issue_found' => __( 'Found security issue: {issue_type}', 'simple-history' ),
            ],
        ];
    }
}
```

---

## Best Practices Summary

### ✅ DO

```php
// Clear, active message
'plugin_activated' => __( 'Activated plugin', 'simple-history' )

// Context included naturally
'plugin_updated' => __( 'Updated plugin "{plugin_name}" from {old_version} to {new_version}', 'simple-history' )

// User-friendly language
'theme_changed' => __( 'Activated theme "{theme_name}"', 'simple-history' )
```

### ❌ DON'T

```php
// Passive voice
'plugin_activated' => __( 'Plugin was activated', 'simple-history' )

// Too verbose
'plugin_updated' => __( 'The plugin has been successfully updated', 'simple-history' )

// Too technical
'settings_changed' => __( 'Modified wp_options table', 'simple-history' )
```

---

## Message Key Naming Conventions

Use descriptive, consistent naming for message keys:

```php
// ✅ Good naming
'post_created'
'post_updated'
'post_deleted'
'user_login'
'user_login_failed'
'plugin_activated'

// ❌ Avoid ambiguous names
'action_1'
'event_occurred'
'thing_happened'
```

---

## Context Data Structure

Always provide useful context:

```php
// ✅ Good context structure
$this->infoMessage(
    'post_updated',
    [
        'post_id' => $post_id,              // Unique identifier
        'post_title' => $post->post_title,  // Human-readable
        'post_type' => $post->post_type,    // Categorization
        'author_id' => $author_id,          // Who did it
        'changes' => $changed_fields,       // What changed
    ]
);

// ❌ Insufficient context
$this->infoMessage(
    'post_updated',
    [ 'id' => $post_id ]  // Not enough information
);
```

---

## Summary

**Integration Checklist**:

1. ✅ Define messages in `getInfo()` with active voice
2. ✅ Use clear, descriptive message keys
3. ✅ Include relevant context in event logging
4. ✅ Use placeholders for dynamic content
5. ✅ Hook into appropriate WordPress actions
6. ✅ Test messages from a user's perspective
7. ✅ Keep language non-technical and friendly

**Remember**: Every logged event tells a story. Make it clear, concise, and in active voice!
