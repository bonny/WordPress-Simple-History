# Simple History REST API

Simple History provides a REST API that allows you to programmatically access the event log data. This document outlines the available endpoints, parameters, and example usage.

## Authentication

All REST API endpoints require authentication. You must be logged in as a WordPress user with appropriate permissions to access the Simple History data.

- For `GET` requests to `/events`, you need the capability to view history (typically administrators and editors)
- For `POST` requests to `/events`, you need the capability to create events
- For search-related endpoints, you need appropriate permissions based on the endpoint

## Base URL

All REST API endpoints are available under the namespace:

```
/wp-json/simple-history/v1/
```

## Available Endpoints

### Events

#### Get Events

Retrieve a list of events from the Simple History log.

**Endpoint:** `GET /wp-json/simple-history/v1/events`

**Parameters:**

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `type` | string | Type of result to return. Options: `overview`, `occasions` | `overview` |
| `per_page` | integer | Maximum number of items to return | 10 |
| `page` | integer | Current page of the collection | 1 |
| `search` | string | Search term to filter results | - |
| `logRowID` | integer | Limit result set to rows with ID lower than this | - |
| `occasionsID` | string | Limit result set to rows with occasionsID equal to this | - |
| `occasionsCount` | integer | The number of occasions to get | - |
| `occasionsCountMaxReturn` | integer | The max number of occasions to return | - |
| `date_from` | string | Limit result set to rows with date after this (format: YYYY-MM-DD) | - |
| `date_to` | string | Limit result set to rows with date before this (format: YYYY-MM-DD) | - |
| `dates` | array | Limit result set to rows with date within this range | - |
| `lastdays` | integer | Limit result set to rows from the last X days | - |
| `months` | array | Limit result set to rows from specific months (format: Y-m) | - |
| `loglevels` | array | Limit result set to rows with specific log levels | - |
| `loggers` | array | Limit result set to rows with specific loggers | - |
| `messages` | array | Limit result set to rows with specific messages (format: LoggerSlug:message) | - |
| `users` | array | Limit result set to rows with specific user IDs | - |
| `user` | integer | Limit result set to rows with a specific user ID | - |
| `offset` | integer | Offset the result set by a specific number of items | - |

**Example Response:**

```json
[
  {
    "id": 123,
    "date_local": "2023-06-15T14:30:45",
    "date_gmt": "2023-06-15T12:30:45",
    "message": "Updated post \"Hello World\"",
    "message_html": "Updated post <a href=\"#\">Hello World</a>",
    "details_html": "<p>Post was updated</p>",
    "details_data": {
      "post_title": "Hello World",
      "post_type": "post"
    },
    "logger": "SimplePostLogger",
    "level": "info",
    "initiator": "wp_user",
    "initiator_data": {
      "user_id": 1,
      "user_login": "admin",
      "user_email": "admin@example.com"
    },
    "ip_addresses": ["127.0.0.1"],
    "occasions_id": "abc123"
  }
]
```

#### Create Event

Add a new event to the Simple History log.

**Endpoint:** `POST /wp-json/simple-history/v1/events`

**Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `message` | string | Short message to log | Yes |
| `note` | string | Additional note or details about the event | No |
| `level` | string | Log level (emergency, alert, critical, error, warning, notice, info, debug) | No (defaults to "info") |

**Example Request:**

```json
{
  "message": "Custom API event",
  "note": "This event was created via the REST API",
  "level": "info"
}
```

**Example Response:**

```json
{
  "id": 124,
  "date_local": "2023-06-15T15:45:30",
  "date_gmt": "2023-06-15T13:45:30",
  "message": "Custom API event",
  "message_html": "Custom API event",
  "details_html": "<p>This event was created via the REST API</p>",
  "details_data": {},
  "logger": "SimpleLogger",
  "level": "info",
  "initiator": "wp_user",
  "initiator_data": {
    "user_id": 1,
    "user_login": "admin",
    "user_email": "admin@example.com"
  },
  "ip_addresses": ["127.0.0.1"],
  "occasions_id": "def456"
}
```

#### Check for Updates

Check if there are new events since a specific event ID.

**Endpoint:** `GET /wp-json/simple-history/v1/events/has-updates`

**Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `since_id` | integer | Check for events newer than this ID | Yes |
| (plus all parameters from the GET events endpoint) | | | |

**Example Response:**

```json
{
  "new_events_count": 5
}
```

### Search Options

Retrieve data required for search options in the admin UI.

**Endpoint:** `GET /wp-json/simple-history/v1/search-options`

**Example Response:**

```json
{
  "dates": [
    {"name": "Today", "value": "today"},
    {"name": "Yesterday", "value": "yesterday"},
    {"name": "Last 7 days", "value": "lastdays:7"}
  ],
  "loggers": [
    {"name": "Posts", "value": "SimplePostLogger"},
    {"name": "Users", "value": "SimpleUserLogger"}
  ],
  "pager_size": {
    "page": 15,
    "dashboard": 5
  },
  "new_events_check_interval": 10000,
  "maps_api_key": "",
  "addons": {
    "addons": [],
    "has_extended_settings_add_on": false,
    "has_premium_add_on": false
  },
  "experimental_features_enabled": false,
  "events_admin_page_url": "admin.php?page=simple_history",
  "settings_page_url": "options-general.php?page=simple_history_settings_menu_slug"
}
```

### User Search

Search for WordPress users.

**Endpoint:** `GET /wp-json/simple-history/v1/search-user`

**Parameters:**

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `q` | string | Search query for users | Yes |

**Example Response:**

```json
[
  {
    "id": 1,
    "login": "admin",
    "email": "admin@example.com",
    "name": "Admin User"
  },
  {
    "id": 2,
    "login": "editor",
    "email": "editor@example.com",
    "name": "Editor User"
  }
]
```

## Code Examples

### JavaScript Example

```javascript
// Fetch the latest events
fetch('/wp-json/simple-history/v1/events', {
  method: 'GET',
  credentials: 'same-origin', // Include cookies for authentication
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce // WordPress nonce for authentication
  }
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));

// Create a new event
fetch('/wp-json/simple-history/v1/events', {
  method: 'POST',
  credentials: 'same-origin',
  headers: {
    'Content-Type': 'application/json',
    'X-WP-Nonce': wpApiSettings.nonce
  },
  body: JSON.stringify({
    message: 'Custom event from JavaScript',
    note: 'This event was created via JavaScript',
    level: 'info'
  })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Error:', error));
```

### PHP Example

```php
<?php
// Fetch the latest events
$response = wp_remote_get(
    rest_url('simple-history/v1/events'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        )
    )
);

if (!is_wp_error($response)) {
    $events = json_decode(wp_remote_retrieve_body($response), true);
    // Process events
}

// Create a new event
$response = wp_remote_post(
    rest_url('simple-history/v1/events'),
    array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ),
        'body' => json_encode(array(
            'message' => 'Custom event from PHP',
            'note' => 'This event was created via PHP',
            'level' => 'info'
        ))
    )
);

if (!is_wp_error($response)) {
    $event = json_decode(wp_remote_retrieve_body($response), true);
    // Process created event
}
```

## Error Handling

The REST API returns standard HTTP status codes:

- `200` - Success
- `400` - Bad request (invalid parameters)
- `401` - Unauthorized (not authenticated)
- `403` - Forbidden (insufficient permissions)
- `404` - Not found
- `500` - Server error

Error responses include a JSON object with details about the error:

```json
{
  "code": "rest_forbidden_context",
  "message": "Sorry, you are not allowed to view this resource.",
  "data": {
    "status": 401
  }
}
```

## Rate Limiting

The REST API does not currently implement rate limiting, but excessive requests may impact server performance. It's recommended to implement appropriate caching and limit the frequency of requests in your applications.

## Further Reading

- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
- [Simple History Documentation](https://simple-history.com/docs/) 