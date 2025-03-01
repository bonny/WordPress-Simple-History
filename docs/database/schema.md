# Database Schema

Simple History uses two main tables to store its data:

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
) CHARSET=utf8;
```

### Fields Description

- `id`: Unique identifier for each log entry
- `date`: When the event occurred
- `logger`: The logger class that created the entry (e.g., 'SimpleLogger')
- `level`: Log level (e.g., 'info', 'warning', 'debug')
- `message`: The log message with placeholders (e.g., 'Plugin "{plugin_name}" {plugin_action}')
- `occasionsID`: Groups similar events together
- `initiator`: Who/what initiated the event (e.g., 'wp_user', 'wp_cli', 'wp_cron')

### Indexes
- Primary key on `id`
- Index on `date` for chronological queries
- Compound index on `logger` and `date` for filtered queries

## 2. Contexts Table

The contexts table (`wp_simple_history_contexts`) stores additional metadata for events:

```sql
CREATE TABLE wp_simple_history_contexts (
    context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    history_id bigint(20) unsigned NOT NULL,
    key varchar(255) DEFAULT NULL,
    value longtext,
    PRIMARY KEY  (context_id),
    KEY history_id (history_id),
    KEY key (key)
) CHARSET=utf8;
```

### Fields Description

- `context_id`: Unique identifier for each context entry
- `history_id`: References the ID in the events table
- `key`: The context key/name
- `value`: The context value (can store serialized data)

### Indexes
- Primary key on `context_id`
- Index on `history_id` for quick event lookups
- Index on `key` for filtered queries

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

| id    | date                | logger                 | level  | message                                                    | occasionsID                        | initiator |
|-------|--------------------|-----------------------|---------|-------------------------------------------------------|-----------------------------------|-----------|
| 59887 | 2025-02-28 07:58:39| AvailableUpdatesLogger| notice | Found an update to theme "{theme_name}"                | 6b19255bcd14dae9d7fa894638f8a487  | wp        |
| 59886 | 2025-02-28 07:58:39| AvailableUpdatesLogger| notice | Found an update to plugin "{plugin_name}"             | 842ddabd62f3e7e695a94ba0f121e729  | wp        |
| 59697 | 2025-02-27 13:54:43| WPMailLogger          | error  | Failed to send email with subject "{email_subject}"    | 1c94a00d80d514f3333ad2ec08ec0e73  | wp_user   |
| 59663 | 2025-02-27 12:10:21| WooCommerceLogger     | info   | Modified WooCommerce {settings_page_label} settings    | 48685e61a9af36ab4c7a22ca47f85365  | wp_user   |
| 1     | 2024-02-27 14:30:00| SimplePluginLogger    | info   | Plugin "{plugin_title}" {plugin_action}               | abc123                            | wp_user   |

### Contexts Table (`wp_simple_history_contexts`)

| context_id | history_id | key               | value                                    |
|------------|------------|-------------------|------------------------------------------|
| 1          | 59887      | theme_name        | Twenty Twenty-Four                       |
| 2          | 59887      | current_version   | 1.0                                     |
| 3          | 59887      | new_version       | 1.1                                     |
| 4          | 59886      | plugin_name       | WooCommerce                             |
| 5          | 59886      | current_version   | 8.5.1                                   |
| 6          | 59886      | new_version       | 8.5.2                                   |
| 7          | 59697      | email_subject     | Password Reset                          |
| 8          | 59697      | error_message     | SMTP connect() failed                   |
| 9          | 59697      | to                | user@example.com                        |
| 10         | 59663      | settings_page_label| Payment                                |
| 11         | 59663      | modified_settings | ["gateway_order", "default_gateway"]    |
| 12         | 59663      | user_id           | 1                                       |
| 13         | 1          | plugin_title      | Hello Dolly                            |
| 14         | 1          | plugin_action     | activated                              |
| 15         | 1          | plugin_name       | hello-dolly                            |
| 16         | 1          | plugin_version    | 1.7.2                                  |
| 17         | 1          | user_id           | 1                                       |
| 18         | 1          | user_login        | admin                                   |

The tables above show how the data is actually stored in the database. Note how:

1. The events table contains message templates with placeholders in curly braces
2. The actual values for these placeholders are stored in the contexts table
3. Each event (history_id) can have multiple context entries
4. The `occasionsID` field helps group similar events together
5. Context values can store various types of data, including serialized arrays (see the `modified_settings` value)

## Database Versioning

The plugin maintains a database version in the WordPress options table:
- Option name: `simple_history_db_version`
- Current version: 7
- Used for managing database upgrades

For more information about database versioning and upgrades, see [Database Versioning](versioning.md). 