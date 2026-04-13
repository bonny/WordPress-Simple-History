---
name: create-logger
description: Guides creation of custom loggers for Simple History. Use when building a new logger class to log WordPress events.
allowed-tools: Read, Grep, Glob, Bash, Edit, Write
---

# Creating a Custom Logger

Step-by-step guide for building a logger that hooks into WordPress actions/filters and logs events to Simple History.

## Architecture Overview

```
WordPress hook fires
  -> Logger::loaded() registers callback
    -> Callback builds context array
      -> $this->info_message('message_key', $context)
        -> Stored in DB with logger slug + message key
          -> get_log_row_details_output() formats for display
```

## Step 1: Create the Logger Class

Create a PHP file in `loggers/` following the naming convention `class-{name}-logger.php`.

### Minimal Boilerplate

```php
<?php

namespace Simple_History\Loggers;

use Simple_History\Log_Initiators;

/**
 * Logs [description of what this logger tracks].
 */
class My_Feature_Logger extends Logger {
    /** @var string Logger slug, max 30 characters, stored in DB. */
    public $slug = 'MyFeatureLogger';

    /**
     * Return logger info.
     *
     * @return array
     */
    public function get_info() {
        return array(
            'name'        => _x( 'My Feature Logger', 'MyFeatureLogger', 'simple-history' ),
            'description' => __( 'Logs changes to my feature', 'simple-history' ),
            'capability'  => 'manage_options',
            'messages'    => array(
                'feature_created' => __( 'Created feature "{feature_name}"', 'simple-history' ),
                'feature_updated' => __( 'Updated feature "{feature_name}"', 'simple-history' ),
                'feature_deleted' => __( 'Deleted feature "{feature_name}"', 'simple-history' ),
            ),
            'labels'      => array(
                'search' => array(
                    'label'     => _x( 'My Feature', 'My Feature logger: search', 'simple-history' ),
                    'label_all' => _x( 'All my feature changes', 'My Feature logger: search', 'simple-history' ),
                    'options'   => array(
                        _x( 'Created', 'My Feature logger: search', 'simple-history' ) => array(
                            'feature_created',
                        ),
                        _x( 'Updated', 'My Feature logger: search', 'simple-history' ) => array(
                            'feature_updated',
                        ),
                        _x( 'Deleted', 'My Feature logger: search', 'simple-history' ) => array(
                            'feature_deleted',
                        ),
                    ),
                ),
            ),
        );
    }

    /**
     * Called when logger is loaded. Hook into WordPress here.
     */
    public function loaded() {
        add_action( 'save_post', array( $this, 'on_save_post' ), 10, 3 );
    }

    /**
     * Handle the WordPress hook.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     */
    public function on_save_post( $post_id, $post, $update ) {
        $context = array(
            'feature_name' => $post->post_title,
            'feature_id'   => $post_id,
        );

        if ( $update ) {
            $this->info_message( 'feature_updated', $context );
        } else {
            $this->info_message( 'feature_created', $context );
        }
    }
}
```

## Step 2: Key Properties and Methods

### The `$slug` Property

-   **Required.** Max 30 characters.
-   Stored in the database to associate log rows with this logger.
-   Use PascalCase by convention (e.g., `PluginLogger`, `SiteHealthLogger`).
-   Once in production, never change it -- existing log entries would become orphaned.

### The `get_info()` Method

Required keys:

| Key           | Type   | Description                                                  |
| ------------- | ------ | ------------------------------------------------------------ |
| `name`        | string | Human-readable logger name (translated)                      |
| `description` | string | What this logger tracks (translated)                         |
| `messages`    | array  | Message key => template string pairs                         |
| `capability`  | string | Required capability to view logs (default: `manage_options`) |
| `labels`      | array  | Search/filter labels for the GUI                             |

Optional keys:

| Key    | Type   | Description                                    |
| ------ | ------ | ---------------------------------------------- |
| `type` | string | `'core'` for built-in loggers, omit for custom |

### The `loaded()` Method

This is where you hook into WordPress actions and filters. Called once when Simple History loads the logger.

### Message Templates

Use `{context_key}` placeholders in message strings. They are automatically replaced with values from the context array.

```php
'messages' => array(
    // {plugin_name} is replaced with $context['plugin_name']
    'plugin_activated' => __( 'Activated plugin "{plugin_name}"', 'simple-history' ),
),
```

### Message Key Uniqueness

Message keys are used as RFC 5424 MSGIDs and must be globally unique across all loggers. Use descriptive prefixes:

```php
// Good - specific to this logger.
'feature_created', 'feature_updated', 'feature_deleted'

// Bad - too generic, may collide with other loggers.
'created', 'updated', 'deleted'
```

## Step 3: Logging Events

Two approaches for logging:

### Message Key Approach (Preferred)

Reference a key from the `messages` array in `get_info()`. The untranslated string is stored in DB; the translated version is shown in the GUI.

```php
$this->info_message( 'feature_created', $context );
$this->warning_message( 'feature_deleted', $context );
$this->notice_message( 'feature_updated', $context );
```

### Direct Message Approach

Pass the message string directly. Less common, used for dynamic messages.

```php
$this->info( 'Something happened', $context );
$this->warning( 'Something bad happened', $context );
```

### Log Levels (PSR-3)

| Method        | When to use                                    |
| ------------- | ---------------------------------------------- |
| `emergency()` | System is unusable                             |
| `alert()`     | Action must be taken immediately               |
| `critical()`  | Critical conditions                            |
| `error()`     | Runtime errors                                 |
| `warning()`   | Destructive actions (deletes, security events) |
| `notice()`    | Normal but noteworthy (setting changes)        |
| `info()`      | Routine events (logins, creates, updates)      |
| `debug()`     | Detailed debug information                     |

Most logger events use `info` or `notice`. Use `warning` for destructive or security-relevant actions.

## Step 4: Context Data Best Practices

The context array stores metadata about the event. It is saved to the `contexts` table as key-value pairs.

### Naming Conventions

Prefix all context keys with the entity name to avoid collisions:

```php
$context = array(
    // Good - prefixed with entity.
    'plugin_name'            => 'Akismet',
    'plugin_current_version' => '5.3',
    'plugin_new_version'     => '5.4',

    // Bad - too generic.
    'name'    => 'Akismet',
    'version' => '5.3',
);
```

### Tracking Changes (prev/new Pattern)

Store previous and new values with `_prev` and `_new` suffixes. The Event Details API auto-detects these for diff display.

```php
$context = array(
    'setting_value_prev' => $old_value,
    'setting_value_new'  => $new_value,
);
```

### Special Context Keys (Underscore Prefix)

Keys starting with `_` have special meaning and are handled by Simple History:

| Key           | Purpose                                      |
| ------------- | -------------------------------------------- |
| `_initiator`  | Override who initiated the event (see below) |
| `_user_id`    | Auto-set to current user ID                  |
| `_user_login` | Auto-set to current user login               |
| `_user_email` | Auto-set to current user email               |

### Setting the Initiator

By default, the initiator is the current logged-in user. Override with `_initiator` in context:

```php
use Simple_History\Log_Initiators;

$context = array(
    '_initiator' => Log_Initiators::WORDPRESS,  // 'wp' - automated/cron
    // Other options:
    // Log_Initiators::WP_USER   - 'wp_user' (default when user logged in)
    // Log_Initiators::WEB_USER  - 'web_user' (anonymous visitor)
    // Log_Initiators::WP_CLI    - 'wp_cli' (terminal command)
    // Log_Initiators::OTHER     - 'other' (unknown source)
);
```

## Step 5: Event Details Output

Override `get_log_row_details_output()` to show additional details below the log message. Use the Event Details API -- never build raw HTML.

```php
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

public function get_log_row_details_output( $row ) {
    $group = new Event_Details_Group();
    $group->set_formatter( new Event_Details_Group_Table_Formatter() );

    // Reads 'feature_status' from context automatically.
    $group->add_item(
        new Event_Details_Item( 'feature_status', __( 'Status', 'simple-history' ) )
    );

    // Reads 'setting_value_new' and 'setting_value_prev' automatically.
    $group->add_item(
        new Event_Details_Item( array( 'setting_value' ), __( 'Value', 'simple-history' ) )
    );

    return $group;
}
```

### Formatters

| Formatter                                  | Use case                      |
| ------------------------------------------ | ----------------------------- |
| `Event_Details_Group_Table_Formatter`      | Key-value table (most common) |
| `Event_Details_Group_Diff_Table_Formatter` | Before/after with diffs       |
| `Event_Details_Group_Inline_Formatter`     | Compact inline text           |

### Manual Values

When context keys don't follow conventions:

```php
( new Event_Details_Item( null, __( 'Label', 'simple-history' ) ) )
    ->set_new_value( $computed_value )
```

See the **logger-messages** skill for full Event Details API reference, RAW formatters, and migration patterns.

## Step 6: Action Links

Add navigational links below log events. See the **action-links** skill for details.

```php
public function get_action_links( $row ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return [];
    }

    return [
        [
            'url'    => admin_url( 'admin.php?page=my-feature' ),
            'label'  => __( 'View feature', 'simple-history' ),
            'action' => 'view',
        ],
    ];
}
```

## Step 7: Registration

### For Core Loggers (in this repository)

Add the class to the loader array in `inc/services/class-loggers-loader.php`.

### For External Plugins

Use the `simple_history/add_custom_logger` hook:

```php
add_action(
    'simple_history/add_custom_logger',
    function ( $simple_history ) {
        require_once __DIR__ . '/class-my-feature-logger.php';
        $simple_history->register_logger( My_Feature_Logger::class );
    }
);
```

## Step 8: Testing

### Manual Testing with WP-CLI

```bash
# View latest events (run from docker-compose directory)
docker compose run --rm wpcli_mariadb simple-history list

# Trigger the WordPress hook your logger listens to, then check the log
```

### Automated Tests

Create a test in `tests/wpunit/loggers/` that:

1. Instantiates Simple History and loads the logger.
2. Triggers the WordPress hook.
3. Asserts the event was logged with correct message key and context.

## Message Style Guide

Follow active voice. See the **logger-messages** skill.

```
Created feature       (not "Feature was created")
Updated settings      (not "Settings have been updated")
Deleted attachment    (not "Attachment has been deleted")
```

## Checklist

Before submitting a new logger:

-   [ ] `$slug` is unique, PascalCase, max 30 characters
-   [ ] Message keys are globally unique (prefixed with entity name)
-   [ ] Context keys are prefixed with entity name
-   [ ] Messages use active voice
-   [ ] Capability is set appropriately (not everyone needs `manage_options`)
-   [ ] Event Details uses the API, not raw HTML
-   [ ] Action links check capabilities before returning URLs
-   [ ] Text domain is `simple-history` for core loggers
-   [ ] PHP 7.4+ compatible

## Reference Files

| File                                    | Purpose                               |
| --------------------------------------- | ------------------------------------- |
| `loggers/class-logger.php`              | Base class with all available methods |
| `loggers/class-site-health-logger.php`  | Clean, simple real-world example      |
| `loggers/class-plugin-logger.php`       | Complex example with many events      |
| `inc/class-log-initiators.php`          | Initiator constants                   |
| `inc/class-log-levels.php`              | Log level constants                   |
| `inc/services/class-loggers-loader.php` | Core logger registration              |
| `docs/architecture/event-details.md`    | Full Event Details API reference      |
| `tests/_data/mu-plugins/mu-plugin.php`  | External logger registration example  |
