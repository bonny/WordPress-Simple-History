# Simple History REST API

Simple History provides a REST API that allows you to programmatically access the event log data. This document outlines the available endpoints, parameters, and example usage.

## Authentication

All REST API endpoints require authentication. You must be logged in as a WordPress user with appropriate permissions to access the Simple History data.

-   For `GET` requests to `/events`, you need the capability to view history (typically administrators and editors)
-   For `POST` requests to `/events`, you need the capability to create events
-   For search-related endpoints, you need appropriate permissions based on the endpoint

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

| Parameter                 | Type    | Description                                                                  | Default    |
| ------------------------- | ------- | ---------------------------------------------------------------------------- | ---------- |
| `type`                    | string  | Type of result to return. Options: `overview`, `occasions`                   | `overview` |
| `per_page`                | integer | Maximum number of items to return                                            | 10         |
| `page`                    | integer | Current page of the collection                                               | 1          |
| `search`                  | string  | Search term to filter results                                                | -          |
| `logRowID`                | integer | Limit result set to rows with ID lower than this                             | -          |
| `occasionsID`             | string  | Limit result set to rows with occasionsID equal to this                      | -          |
| `occasionsCount`          | integer | The number of occasions to get                                               | -          |
| `occasionsCountMaxReturn` | integer | The max number of occasions to return                                        | -          |
| `date_from`               | string  | Limit result set to rows with date after this (format: YYYY-MM-DD)           | -          |
| `date_to`                 | string  | Limit result set to rows with date before this (format: YYYY-MM-DD)          | -          |
| `dates`                   | array   | Limit result set to rows with date within this range                         | -          |
| `lastdays`                | integer | Limit result set to rows from the last X days                                | -          |
| `months`                  | array   | Limit result set to rows from specific months (format: Y-m)                  | -          |
| `loglevels`               | array   | Limit result set to rows with specific log levels                            | -          |
| `loggers`                 | array   | Limit result set to rows with specific loggers                               | -          |
| `messages`                | array   | Limit result set to rows with specific messages (format: LoggerSlug:message) | -          |
| `users`                   | array   | Limit result set to rows with specific user IDs                              | -          |
| `user`                    | integer | Limit result set to rows with a specific user ID                             | -          |
| `offset`                  | integer | Offset the result set by a specific number of items                          | -          |
| `include_sticky`          | boolean | Include sticky events in the result set                                      | false      |
| `only_sticky`             | boolean | Only return sticky events                                                    | false      |
| `initiator`               | string/array | Limit result set to specific initiator(s) (e.g., `wp_user`, `wp_cli`, `wp`) | -     |
| `context_filters`         | object  | Filter events by context data as key-value pairs                             | -          |
| `ungrouped`               | boolean | Return ungrouped events without occasions grouping                           | false      |

**Surrounding Events (Admin Only):**

These parameters allow viewing events chronologically before and after a specific event. Useful for debugging to see the full context of what happened around a particular event. Requires administrator privileges (`manage_options` capability).

| Parameter                 | Type    | Description                                                                  | Default    |
| ------------------------- | ------- | ---------------------------------------------------------------------------- | ---------- |
| `surrounding_event_id`    | integer | The center event ID. When set, returns events before and after this event, ignoring all other filters. | -          |
| `surrounding_count`       | integer | Number of events to show before AND after the center event. (min: 1, max: 50) | 5          |

**Note:** When `surrounding_event_id` is provided, all other filters are ignored and events are returned in reverse chronological order (newest first).

**Exclusion Filters (Negative Filters):**

These parameters exclude events matching the criteria. When both inclusion and exclusion filters are specified for the same field, exclusion takes precedence.

| Parameter                 | Type    | Description                                                                  | Default    |
| ------------------------- | ------- | ---------------------------------------------------------------------------- | ---------- |
| `exclude_search`          | string  | Exclude events containing these words                                        | -          |
| `exclude_loglevels`       | array   | Exclude events with specific log levels                                      | -          |
| `exclude_loggers`         | array   | Exclude events from specific loggers                                         | -          |
| `exclude_messages`        | array   | Exclude events with specific messages (format: LoggerSlug:message)           | -          |
| `exclude_user`            | integer | Exclude events from a specific user ID                                       | -          |
| `exclude_users`           | array   | Exclude events from specific user IDs                                        | -          |
| `exclude_initiator`       | string/array | Exclude events from specific initiator(s)                                | -          |

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
		"ip_addresses": [ "127.0.0.1" ],
		"occasions_id": "abc123"
	}
]
```

#### Create Event

Add a new event to the Simple History log.

**Endpoint:** `POST /wp-json/simple-history/v1/events`

**Parameters:**

| Parameter | Type   | Description                                                                 | Required                |
| --------- | ------ | --------------------------------------------------------------------------- | ----------------------- |
| `message` | string | Short message to log                                                        | Yes                     |
| `note`    | string | Additional note or details about the event                                  | No                      |
| `level`   | string | Log level (emergency, alert, critical, error, warning, notice, info, debug) | No (defaults to "info") |

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
	"ip_addresses": [ "127.0.0.1" ],
	"occasions_id": "def456"
}
```

#### Check for Updates

Check if there are new events since a specific event ID.

**Endpoint:** `GET /wp-json/simple-history/v1/events/has-updates`

**Parameters:**

| Parameter                                          | Type    | Description                         | Required |
| -------------------------------------------------- | ------- | ----------------------------------- | -------- |
| `since_id`                                         | integer | Check for events newer than this ID | Yes      |
| (plus all parameters from the GET events endpoint) |         |                                     |          |

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
		{ "name": "Today", "value": "today" },
		{ "name": "Yesterday", "value": "yesterday" },
		{ "name": "Last 7 days", "value": "lastdays:7" }
	],
	"loggers": [
		{ "name": "Posts", "value": "SimplePostLogger" },
		{ "name": "Users", "value": "SimpleUserLogger" }
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

### Stats

Get statistics about Simple History events and activity.

#### Available Stats Endpoints

-   `GET /wp-json/simple-history/v1/stats/summary` - Get overall summary statistics
-   `GET /wp-json/simple-history/v1/stats/users` - Get user-related statistics
-   `GET /wp-json/simple-history/v1/stats/content` - Get content (posts/pages) statistics
-   `GET /wp-json/simple-history/v1/stats/media` - Get media upload/edit/delete statistics
-   `GET /wp-json/simple-history/v1/stats/plugins` - Get plugin activity statistics
-   `GET /wp-json/simple-history/v1/stats/core` - Get WordPress core update statistics
-   `GET /wp-json/simple-history/v1/stats/peak-days` - Get peak activity days
-   `GET /wp-json/simple-history/v1/stats/peak-times` - Get peak activity times
-   `GET /wp-json/simple-history/v1/stats/activity-overview` - Get activity overview by date

**Common Parameters for Stats Endpoints:**

| Parameter         | Type    | Description                                            | Default |
| ----------------- | ------- | ------------------------------------------------------ | ------- |
| `date_from`       | integer | Start date as Unix timestamp (defaults to 28 days ago) | -       |
| `date_to`         | integer | End date as Unix timestamp (defaults to end of today)  | -       |
| `limit`           | integer | Maximum number of items to return (1-100)              | 50      |
| `include_details` | boolean | Whether to include detailed stats                      | false   |

### User Search

Search for WordPress users. Used to retrieve WordPress users for display in the user filter dropdown in the admin interface.

**Endpoint:** `GET /wp-json/simple-history/v1/search-user`

**Parameters:**

| Parameter | Type   | Description            | Required |
| --------- | ------ | ---------------------- | -------- |
| `q`       | string | Search query for users | Yes      |

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

## Filter Examples

### Using Exclusion Filters

```bash
# Exclude debug level events
curl -u username:password \
  '/wp-json/simple-history/v1/events?exclude_loglevels[]=debug&per_page=50'

# Exclude events containing "cron"
curl -u username:password \
  '/wp-json/simple-history/v1/events?exclude_search=cron&per_page=50'

# Exclude WordPress-initiated events (cron jobs, automatic updates)
curl -u username:password \
  '/wp-json/simple-history/v1/events?exclude_initiator=wp&per_page=50'

# Exclude multiple log levels
curl -u username:password \
  '/wp-json/simple-history/v1/events?exclude_loglevels[]=debug&exclude_loglevels[]=info&per_page=50'
```

### Combining Inclusion and Exclusion Filters

```bash
# Get info events, but exclude those containing "cron"
curl -u username:password \
  '/wp-json/simple-history/v1/events?loglevels[]=info&exclude_search=cron&per_page=50'

# Get all events except debug level and WordPress system events
curl -u username:password \
  '/wp-json/simple-history/v1/events?exclude_loglevels[]=debug&exclude_initiator=wp&per_page=100'

# Important events only: errors/warnings, no WordPress system, no CLI
curl -u username:password \
  '/wp-json/simple-history/v1/events?loglevels[]=error&loglevels[]=warning&exclude_initiator[]=wp&exclude_initiator[]=wp_cli&per_page=50'
```

### Surrounding Events

```bash
# Get events surrounding event ID 123 (5 before + center + 5 after = 11 total)
curl -u username:password \
  '/wp-json/simple-history/v1/events?surrounding_event_id=123'

# Get 10 events before and after event ID 456
curl -u username:password \
  '/wp-json/simple-history/v1/events?surrounding_event_id=456&surrounding_count=10'
```

**Response Headers for Surrounding Events:**

When using `surrounding_event_id`, the response includes additional headers:

| Header                          | Description                              |
| ------------------------------- | ---------------------------------------- |
| `X-SimpleHistory-CenterEventId` | The ID of the center event               |
| `X-SimpleHistory-EventsBefore`  | Number of events returned before center  |
| `X-SimpleHistory-EventsAfter`   | Number of events returned after center   |
| `X-SimpleHistory-MaxId`         | Highest event ID in results              |
| `X-SimpleHistory-MinId`         | Lowest event ID in results               |

### Conflict Resolution

When the same value appears in both inclusion and exclusion filters, exclusion takes precedence:

```bash
# Request: Include SimplePluginLogger and SimpleUserLogger, but exclude SimpleUserLogger
curl -u username:password \
  '/wp-json/simple-history/v1/events?loggers[]=SimplePluginLogger&loggers[]=SimpleUserLogger&exclude_loggers[]=SimpleUserLogger&per_page=50'

# Result: Only SimplePluginLogger events are returned (exclusion wins)
```

## Code Examples

### JavaScript Example

```javascript
// Fetch the latest events
fetch( '/wp-json/simple-history/v1/events', {
	method: 'GET',
	credentials: 'same-origin', // Include cookies for authentication
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': wpApiSettings.nonce, // WordPress nonce for authentication
	},
} )
	.then( ( response ) => response.json() )
	.then( ( data ) => console.log( data ) )
	.catch( ( error ) => console.error( 'Error:', error ) );

// Fetch events with exclusion filters
const params = new URLSearchParams({
	per_page: 50,
	'exclude_loglevels[]': 'debug',
	'exclude_search': 'cron'
});

fetch( `/wp-json/simple-history/v1/events?${params}`, {
	method: 'GET',
	credentials: 'same-origin',
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': wpApiSettings.nonce,
	},
} )
	.then( ( response ) => response.json() )
	.then( ( events ) => {
		// Process events without debug level or cron mentions
		events.forEach( event => {
			console.log( event.message, event.level );
		});
	})
	.catch( ( error ) => console.error( 'Error:', error ) );

// Complex filtering: user events only, no debug, no cron
const complexParams = new URLSearchParams();
complexParams.append('per_page', '100');
complexParams.append('initiator', 'wp_user');
complexParams.append('exclude_loglevels[]', 'debug');
complexParams.append('exclude_search', 'cron');

fetch( `/wp-json/simple-history/v1/events?${complexParams}`, {
	method: 'GET',
	credentials: 'same-origin',
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': wpApiSettings.nonce,
	},
} )
	.then( ( response ) => response.json() )
	.then( ( events ) => console.log( `Found ${events.length} user events` ) )
	.catch( ( error ) => console.error( 'Error:', error ) );

// Create a new event
fetch( '/wp-json/simple-history/v1/events', {
	method: 'POST',
	credentials: 'same-origin',
	headers: {
		'Content-Type': 'application/json',
		'X-WP-Nonce': wpApiSettings.nonce,
	},
	body: JSON.stringify( {
		message: 'Custom event from JavaScript',
		note: 'This event was created via JavaScript',
		level: 'info',
	} ),
} )
	.then( ( response ) => response.json() )
	.then( ( data ) => console.log( data ) )
	.catch( ( error ) => console.error( 'Error:', error ) );
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

-   `200` - Success
-   `400` - Bad request (invalid parameters)
-   `401` - Unauthorized (not authenticated)
-   `403` - Forbidden (insufficient permissions)
-   `404` - Not found
-   `500` - Server error

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

-   [Filter Usage Examples](./filters-usage-examples.md) - Comprehensive guide to using positive and negative filters
-   [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/)
-   [Simple History Documentation](https://simple-history.com/docs/)
