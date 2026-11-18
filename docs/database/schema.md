# Database Schema

Simple History uses two tables to store its data. Both are created by `Setup_Database` in `inc/services/class-setup-database.php`. The table names get the site's `$wpdb->prefix`, and can be changed with the `simple_history/db_table` and `simple_history/logger_db_table_contexts` filters.

## 1. Events Table

The events table (`wp_simple_history`) stores the main log entries:

```sql
CREATE TABLE wp_simple_history (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    date datetime NOT NULL,
    logger varchar(30) DEFAULT NULL,
    level varchar(20) DEFAULT NULL,
    message varchar(255) DEFAULT NULL,
    occasionsID varchar(32) DEFAULT NULL,
    initiator varchar(16) DEFAULT NULL,
    PRIMARY KEY  (id),
    KEY date (date),
    KEY loggerdate (logger,date)
) {charset_collate};
```

### Fields Description

-   `id`: Unique identifier for each log entry
-   `date`: When the event occurred. Stored in UTC/GMT timezone in the database, but displayed to users in the site's configured timezone.
-   `logger`: The logger class that created the entry (e.g., 'SimpleLogger')
-   `level`: Log level (e.g., 'info', 'warning', 'debug')
-   `message`: The log message with placeholders (e.g., 'Plugin "{plugin_name}" {plugin_action}')
-   `occasionsID`: Groups similar events together
-   `initiator`: Who/what initiated the event (e.g., 'wp_user', 'wp_cli', 'wp_cron')

### Indexes

-   Primary key on `id`
-   Index on `date` for chronological queries
-   Compound index on `logger` and `date` for filtered queries

## 2. Contexts Table

The contexts table (`wp_simple_history_contexts`) stores additional metadata for events:

```sql
CREATE TABLE wp_simple_history_contexts (
    context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    history_id bigint(20) unsigned NOT NULL,
    `key` varchar(255) DEFAULT NULL,
    value longtext,
    PRIMARY KEY  (context_id),
    KEY history_id (history_id),
    KEY `key` (`key`(191))
) {charset_collate};
```

`{charset_collate}` is the value of `$wpdb->get_charset_collate()`, normally `utf8mb4` on current WordPress. The `key` index uses a 191-character prefix because InnoDB on older row formats limits index keys to 767 bytes, and 191 × 4 bytes (utf8mb4) fits. WordPress core uses the same limit.

The charset and prefix index were added in 5.28.0. They only apply when a table is created. The contexts table is created with `CREATE TABLE IF NOT EXISTS`, so tables from older installs keep their original charset and index.

### Fields Description

-   `context_id`: Unique identifier for each context entry
-   `history_id`: References the ID in the events table. `event_id` would have been a better name.
-   `key`: The context key/name
-   `value`: The context value (can store serialized data)

### Common fields in context table

-   `_message_key`: The key of the message in the logger's `messages` array
-   `_user_id`: The ID of the user who performed the action
-   `_user_login`: The login name of the user who performed the action
-   `_user_email`: The email address of the user who performed the action
-   `_server_remote_addr`: The IP address of the request, from `$_SERVER['REMOTE_ADDR']`

### Indexes

-   Primary key on `context_id`
-   Index on `history_id` for quick event lookups
-   Index on the first 191 characters of `key` for filtered queries

## Database Relationships

```
Events (wp_simple_history)
       ↑
       | One-to-Many
       |
Contexts (wp_simple_history_contexts)
```

Each event can have multiple context entries, linked by the `history_id` field.

## Example Data

### Events Table (`wp_simple_history`)

| id    | date                | logger                 | level  | message                                             | occasionsID                      | initiator |
| ----- | ------------------- | ---------------------- | ------ | --------------------------------------------------- | -------------------------------- | --------- |
| 59887 | 2025-02-28 07:58:39 | AvailableUpdatesLogger | notice | Found an update to theme "{theme_name}"             | 6b19255bcd14dae9d7fa894638f8a487 | wp        |
| 59886 | 2025-02-28 07:58:39 | AvailableUpdatesLogger | notice | Found an update to plugin "{plugin_name}"           | 842ddabd62f3e7e695a94ba0f121e729 | wp        |
| 59697 | 2025-02-27 13:54:43 | WPMailLogger           | error  | Failed to send email with subject "{email_subject}" | 1c94a00d80d514f3333ad2ec08ec0e73 | wp_user   |
| 59663 | 2025-02-27 12:10:21 | WooCommerceLogger      | info   | Modified WooCommerce {settings_page_label} settings | 48685e61a9af36ab4c7a22ca47f85365 | wp_user   |
| 1     | 2024-02-27 14:30:00 | SimplePluginLogger     | info   | Plugin "{plugin_title}" {plugin_action}             | abc123                           | wp_user   |

### Contexts Table (`wp_simple_history_contexts`)

| context_id | history_id | key                 | value                                |
| ---------- | ---------- | ------------------- | ------------------------------------ |
| 1          | 59887      | theme_name          | Twenty Twenty-Four                   |
| 2          | 59887      | current_version     | 1.0                                  |
| 3          | 59887      | new_version         | 1.1                                  |
| 4          | 59886      | plugin_name         | WooCommerce                          |
| 5          | 59886      | current_version     | 8.5.1                                |
| 6          | 59886      | new_version         | 8.5.2                                |
| 7          | 59697      | email_subject       | Password Reset                       |
| 8          | 59697      | error_message       | SMTP connect() failed                |
| 9          | 59697      | to                  | user@example.com                     |
| 10         | 59663      | settings_page_label | Payment                              |
| 11         | 59663      | modified_settings   | ["gateway_order", "default_gateway"] |
| 12         | 59663      | user_id             | 1                                    |
| 13         | 1          | plugin_title        | Hello Dolly                          |
| 14         | 1          | plugin_action       | activated                            |
| 15         | 1          | plugin_name         | hello-dolly                          |
| 16         | 1          | plugin_version      | 1.7.2                                |
| 17         | 1          | user_id             | 1                                    |
| 18         | 1          | user_login          | admin                                |

The tables above show how the data is actually stored in the database. Note how:

1. The events table contains message templates with placeholders in curly braces
2. The actual values for these placeholders are stored in the contexts table
3. Each event (history_id) can have multiple context entries
4. The `occasionsID` field helps group similar events together
5. Context values can store various types of data, including serialized arrays (see the `modified_settings` value)

## Database Versioning

The plugin maintains a database version in the WordPress options table:

-   Option name: `simple_history_db_version`
-   Current version: 10
-   Used for managing database upgrades

`Setup_Database::run_setup_steps()` runs on `after_setup_theme` (priority 5) and calls one method per step, `setup_new_to_version_1()` through `setup_version_9_to_version_10()`. Each step runs only when the stored version equals the version before it, then bumps the option, so a site on any older version runs the remaining steps in order on one request.

| Version | What the step does |
| ------- | ------------------ |
| 1 | Creates the events table (id and date only), flags auto-backfill, first-purge notice and welcome message |
| 2 | Sets default values for `simple_history_show_as_page` and `simple_history_show_on_dashboard` |
| 3 | Adds the remaining events table columns and indexes, creates the contexts table, sets `logger`/`level` on old rows, logs welcome events |
| 4 | Makes columns from very old installs (`action`, `object_type` etc.) nullable, if they exist |
| 5 | Sets autoloaded defaults for detective mode and experimental features |
| 6 | Sets `simple_history_show_in_admin_bar` to 1 |
| 7 | Stores `simple_history_install_date_gmt` |
| 8 | Creates the welcome message option for upgrading sites |
| 9 | Stores `simple_history_retention_days` (30 on fresh installs, 60 on existing ones) |
| 10 | Creates rows with default values for settings that had none (pager sizes, menu location, RSS feed, reactions, auto-backfill pending) |

The schema itself has not changed since version 3; later steps only set options.

If a query fails because a table is missing (for example after a site copy that left the tables behind), `Setup_Database::recreate_tables_if_missing()` deletes the version option and runs all steps again, once per request.

## Sticky Events Storage

Sticky (or pinned) status for events is stored in the `wp_simple_history_contexts` table as a context entry with the key `_sticky`.

-   The `value` field contains an empty JSON object (`{}`) to indicate sticky status.
-   No user or other metadata is stored for sticky events.
-   Sticky events follow the same user view capabilities as other events.

### Example

| history_id | context_id | key      | value |
|------------|------------|----------|-------|
| 20         | 59890      | _sticky  | {}    |
| 21         | 59891      | _sticky  | {}    |

-   To check if an event is sticky, look for a `_sticky` context entry for that event.
-   To query for all sticky events, filter for context rows where `key = '_sticky'`.
-   The JSON object can be extended in the future for more metadata if needed.
