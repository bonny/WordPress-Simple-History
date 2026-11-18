# Simple History REST API

Simple History provides a REST API that allows you to programmatically access the event log data. This document outlines the available endpoints, parameters, and example usage.

This page covers the routes registered by the free core plugin. The premium add-on registers more routes under the same namespace; they are not listed here.

## Authentication

All REST API endpoints require authentication. You must be logged in as a WordPress user with appropriate permissions to access the Simple History data.

Most read endpoints use the same check as the Simple History admin page: the user must have the "view history" capability, which is `edit_pages` by default (administrators and editors) and can be changed with the `simple_history/view_history_capability` filter. If Stealth Mode hides the log from a user, the REST API is closed to them too.

| Requirement                 | Endpoints                                                                                                                                                                   |
| --------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| View history capability     | `GET /events`, `GET /events/{id}`, `GET /events/aggregate`, `GET /events/has-updates`, `GET /backfill-status`, `POST /events/{id}/react`, `POST /events/{id}/unreact`, `GET /search-options`, `GET /users/{id}/card`, `GET /initiators/{type}/card`, `/events-view`, `/sidebar-visibility` |
| `manage_options`            | `POST /events`, `POST /events/{id}/stick`, `POST /events/{id}/unstick`, all `/stats/*` endpoints, `/support-info`, `/support-info/health-check`, `/email-report/preview/*`, and the `surrounding_event_id` parameter of `GET /events` |
| `list_users`                | `GET /search-user`                                                                                                                                                          |
| `activate_plugins` + dev mode | `/dev-tools/*`                                                                                                                                                            |

A request without a logged-in user gets a `401`. A logged-in user without the required capability gets a `403`.

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

**Permission:** View history capability. Only events from loggers the user is allowed to read are returned.

**Parameters:**

| Parameter                 | Type         | Description                                                                                                       | Default    |
| ------------------------- | ------------ | ----------------------------------------------------------------------------------------------------------------- | ---------- |
| `type`                    | string       | Type of result to return. Options: `overview`, `occasions`                                                        | `overview` |
| `per_page`                | integer      | Maximum number of items to return (1-100)                                                                         | 10         |
| `page`                    | integer      | Current page of the collection                                                                                    | 1          |
| `search`                  | string       | Search term to filter results                                                                                     | -          |
| `include`                 | array        | Limit result set to specific event IDs                                                                            | -          |
| `logRowID`                | integer      | Limit result set to rows with ID lower than this                                                                  | -          |
| `occasionsID`             | string       | Limit result set to rows with occasionsID equal to this                                                           | -          |
| `occasionsCount`          | integer      | The number of occasions to get                                                                                    | -          |
| `occasionsCountMaxReturn` | integer      | The max number of occasions to return                                                                             | -          |
| `max_id_first_page`       | integer      | Limit result set to rows with ID equal to or lower than this                                                      | -          |
| `since_id`                | integer      | Limit result set to rows with ID greater than this, i.e. newer events                                             | -          |
| `since_date`              | string       | Limit result set to events with date after this, or the same date and an ID greater than `since_id`. Use together with `since_id` | - |
| `date_from`               | string       | Limit result set to rows with date on or after this. Unix timestamp or a date string such as `YYYY-MM-DD` (a date without time starts at 00:00:00) | - |
| `date_to`                 | string       | Limit result set to rows with date on or before this. Unix timestamp or a date string such as `YYYY-MM-DD` (a date without time ends at 23:59:59) | - |
| `dates`                   | array        | Limit result set to rows with date within this range, e.g. `month:2021-11`, `lastdays:7` or `allDates`            | -          |
| `lastdays`                | integer      | Limit result set to rows from the last X days                                                                     | -          |
| `months`                  | array        | Limit result set to rows from specific months (format: Y-m)                                                       | -          |
| `loglevels`               | array        | Limit result set to rows with specific log levels                                                                 | -          |
| `loggers`                 | array        | Limit result set to rows with specific loggers                                                                    | -          |
| `messages`                | array        | Limit result set to rows with specific messages (format: LoggerSlug:message)                                      | -          |
| `users`                   | array        | Limit result set to rows with specific user IDs                                                                   | -          |
| `user`                    | integer      | Limit result set to rows with a specific user ID                                                                  | -          |
| `offset`                  | integer      | Offset the result set by a specific number of items                                                               | -          |
| `include_sticky`          | boolean      | Include sticky events in the result set                                                                           | false      |
| `only_sticky`             | boolean      | Only return sticky events                                                                                         | false      |
| `initiator`               | string/array | Limit result set to specific initiator(s) (e.g., `wp_user`, `wp_cli`, `wp`)                                       | -          |
| `ip_address`              | string       | Limit result set to events from one IP address. Masked IPv4 addresses ending in `.x` are supported                 | -          |
| `context_filters`         | object       | Filter events by context data as key-value pairs                                                                  | -          |
| `metadata_search`         | string       | Plain text search across all event context values. Slower than `search` but also finds IP addresses, emails and other internal data | - |
| `ai_only`                 | boolean      | Only return events triggered through an AI agent (Claude Code, ChatGPT, MCP clients and similar)                  | false      |
| `ungrouped`               | boolean      | Return ungrouped events without occasions grouping                                                                | false      |
| `orderby`                 | string       | Column to sort by. Options: `date`, `id`, `level`, `logger`, `message`. Anything other than `date` returns ungrouped events, since occasion grouping depends on date order | `date` |
| `order`                   | string       | Sort direction. Options: `asc`, `desc`                                                                            | `desc`     |
| `skip_count_query`        | boolean      | Skip the total count query. Faster when you do not need pagination info                                           | false      |
| `context`                 | string       | Standard WordPress REST `context` parameter                                                                       | `view`     |

The standard `_fields` parameter works too, for example `_fields=id,message,date_gmt`.

**Surrounding Events (Admin Only):**

These parameters allow viewing events chronologically before and after a specific event. Useful for debugging to see the full context of what happened around a particular event. Requires administrator privileges (`manage_options` capability).

| Parameter              | Type    | Description                                                                                            | Default |
| ---------------------- | ------- | ------------------------------------------------------------------------------------------------------ | ------- |
| `surrounding_event_id` | integer | The center event ID. When set, returns events before and after this event, ignoring all other filters. | -       |
| `surrounding_count`    | integer | Number of events to show before AND after the center event. (min: 1, max: 50)                          | 5       |

**Note:** When `surrounding_event_id` is provided, all other filters are ignored and events are returned in reverse chronological order (newest first).

**Exclusion Filters (Negative Filters):**

These parameters exclude events matching the criteria. When both inclusion and exclusion filters are specified for the same field, exclusion takes precedence.

| Parameter           | Type         | Description                                                        | Default |
| ------------------- | ------------ | ------------------------------------------------------------------ | ------- |
| `exclude_search`    | string       | Exclude events containing these words                              | -       |
| `exclude_loglevels` | array        | Exclude events with specific log levels                            | -       |
| `exclude_loggers`   | array        | Exclude events from specific loggers                               | -       |
| `exclude_messages`  | array        | Exclude events with specific messages (format: LoggerSlug:message) | -       |
| `exclude_user`      | integer      | Exclude events from a specific user ID                             | -       |
| `exclude_users`     | array        | Exclude events from specific user IDs                              | -       |
| `exclude_initiator` | string/array | Exclude events from specific initiator(s)                          | -       |

An invalid filter value, such as a malformed `date_from` or `months` value, returns a `400` with the code `rest_invalid_param`.

**Response Headers:**

For `overview` queries the response includes these headers:

| Header                     | Description                                               |
| -------------------------- | --------------------------------------------------------- |
| `X-WP-Total`               | Total number of matching events                           |
| `X-WP-TotalPages`          | Total number of pages                                     |
| `X-SimpleHistory-MaxId`    | Highest event ID in the result, for new-event detection   |
| `X-SimpleHistory-MaxDate`  | Date of the newest event in the result                    |
| `Link`                     | `prev` and `next` page links, when there are such pages   |

**Example Response:**

```json
[
	{
		"id": 123,
		"date_local": "2023-06-15 14:30:45",
		"date_gmt": "2023-06-15 12:30:45",
		"link": "https://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page#simple-history/event/123",
		"permalink": "https://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page#simple-history/event/123",
		"via": "",
		"message": "Updated post \"Hello World\"",
		"message_html": "Updated post <a href=\"#\">Hello World</a>",
		"message_uninterpolated": "Updated {post_type} \"{post_title}\"",
		"message_template": "Updated {post_type} \"{post_title}\"",
		"message_key": "post_updated",
		"details_html": "<p>Post was updated</p>",
		"details_data": {},
		"logger": "SimplePostLogger",
		"loglevel": "info",
		"initiator": "wp_user",
		"initiator_data": {
			"user_id": 1,
			"user_login": "admin",
			"user_email": "admin@example.com",
			"user_image": "<img ... />",
			"user_avatar_url": "https://secure.gravatar.com/avatar/...",
			"user_profile_url": "https://example.com/wp-admin/profile.php",
			"user_display_name": "Admin"
		},
		"ip_addresses": {
			"_server_remote_addr": "127.0.0.1"
		},
		"occasions_id": "abc123",
		"subsequent_occasions_count": 1,
		"context": {
			"post_id": "1",
			"post_type": "post",
			"post_title": "Hello World"
		},
		"ai_origin": null,
		"sticky": false,
		"sticky_appended": false,
		"annotation": null,
		"backfilled": false,
		"reactions": {},
		"action_links": [
			{
				"url": "https://example.com/wp-admin/post.php?post=1&action=edit",
				"label": "Edit post",
				"action": "edit",
				"description": ""
			}
		]
	}
]
```

Notes on some fields:

-   `ip_addresses` is an empty object unless IP addresses are shown for that kind of event and the current user may see IP addresses (`manage_options` by default). The same rule removes IP addresses from `context`.
-   `annotation` is `null`, or an object with `text`, `user_id`, `user_name`, `updated_at` and `revision_count`. Annotations are written through a premium route; core only reads them. The raw `_annotation` context key is never included in `context`.
-   `reactions` is only included when reactions are enabled. It is an object keyed by reaction type, each with `count`, `user_ids`, `user_names` and `reacted` (whether the current user has reacted).
-   `ai_origin` is `null`, or an object with `agent_name`, `detected_via` and `application`.

#### Get Event

Retrieve a single event.

**Endpoint:** `GET /wp-json/simple-history/v1/events/{id}`

**Permission:** View history capability, and the user must be allowed to read the logger that wrote the event.

**Parameters:**

| Parameter | Type    | Description | Required |
| --------- | ------- | ----------- | -------- |
| `id`      | integer | Event ID    | Yes      |

**Response:** One event object, with the same fields as the items in the Get Events response.

An ID that does not exist returns a `404` with the code `rest_event_invalid_id`. An event the user may not read returns `401`/`403` with `rest_forbidden_context`.

#### Create Event

Add a new event to the Simple History log. The event is logged by the Custom Entry logger (`CustomEntryLogger`) with the message key `custom_entry_added`.

**Endpoint:** `POST /wp-json/simple-history/v1/events`

**Permission:** `manage_options`.

**Parameters:**

| Parameter | Type   | Description                                                                 | Required                |
| --------- | ------ | --------------------------------------------------------------------------- | ----------------------- |
| `message` | string | Short message to log                                                        | Yes                     |
| `note`    | string | Additional note or details about the event                                  | No                      |
| `level`   | string | Log level (emergency, alert, critical, error, warning, notice, info, debug) | No (defaults to "info") |
| `date`    | string | Date and time of the event in `Y-m-d H:i:s` format. Defaults to now         | No                      |

**Example Request:**

```json
{
	"message": "Custom API event",
	"note": "This event was created via the REST API",
	"level": "info"
}
```

**Example Response:**

The response has status `201`. It does not contain the created event.

```json
{
	"message": "Event logged successfully",
	"data": {
		"status": 201
	}
}
```

A `date` in any other format returns a `400` with the code `rest_invalid_date`.

#### Check for Updates

Check if there are new events since a specific event ID.

**Endpoint:** `GET /wp-json/simple-history/v1/events/has-updates`

**Permission:** View history capability.

**Parameters:**

| Parameter                                          | Type    | Description                         | Required |
| -------------------------------------------------- | ------- | ----------------------------------- | -------- |
| `since_id`                                         | integer | Check for events newer than this ID | Yes      |
| (plus all parameters from the GET events endpoint) |         |                                     |          |

The count is always taken over ungrouped events, and `surrounding_event_id` and `surrounding_count` are ignored.

**Example Response:**

```json
{
	"new_events_count": 5
}
```

#### Aggregate Events

Count the events that match a set of filters, grouped into buckets. Used for the histogram above the event log.

**Endpoint:** `GET /wp-json/simple-history/v1/events/aggregate`

**Permission:** View history capability.

**Parameters:**

All filter parameters from the GET events endpoint, except `page`, `per_page` and `offset`, plus:

| Parameter        | Type    | Description                                                         | Default |
| ---------------- | ------- | ------------------------------------------------------------------- | ------- |
| `group_by`       | string  | What to count by. Options: `date`, `level`, `logger`, `initiator`   | `date`  |
| `interval`       | string  | Bucket size when `group_by` is `date`. Options: `day`, `hour`       | `day`   |
| `split_by_level` | boolean | Also split each bucket by log level                                 | false   |

Date buckets use the site's timezone. At most 500 buckets are returned; when there are more, the oldest are dropped. Results are cached per user for 60 seconds.

**Example Response:**

```json
[
	{ "bucket": "2026-11-16", "level": null, "count": 42 },
	{ "bucket": "2026-11-17", "level": null, "count": 17 }
]
```

Buckets come in chronological order. `level` is `null` unless `split_by_level` is true. For hourly buckets the `bucket` value looks like `2026-11-17 14:00:00`.

#### Stick Event

Make an event sticky, so it is pinned to the top of the log.

**Endpoint:** `POST /wp-json/simple-history/v1/events/{id}/stick`

**Permission:** `manage_options`.

**Parameters:**

| Parameter | Type    | Description | Required |
| --------- | ------- | ----------- | -------- |
| `id`      | integer | Event ID    | Yes      |

**Response:** The updated event object, with `sticky` set to `true`.

An ID that does not exist returns a `404` with the code `rest_event_invalid_id`.

#### Unstick Event

Remove the sticky status from an event.

**Endpoint:** `POST /wp-json/simple-history/v1/events/{id}/unstick`

**Permission:** `manage_options`.

**Parameters:**

| Parameter | Type    | Description | Required |
| --------- | ------- | ----------- | -------- |
| `id`      | integer | Event ID    | Yes      |

**Response:** The updated event object, with `sticky` set to `false`.

Unsticking an event that is not sticky returns a `500` with the code `rest_unstick_event_failed`, because there was nothing to remove.

#### React to Event

Add the current user's reaction to an event.

**Endpoint:** `POST /wp-json/simple-history/v1/events/{id}/react`

**Permission:** Same as Get Event.

**Parameters:**

| Parameter | Type    | Description                                                                                                  | Required |
| --------- | ------- | ------------------------------------------------------------------------------------------------------------ | -------- |
| `id`      | integer | Event ID                                                                                                     | Yes      |
| `type`    | string  | Reaction type. Core allows `thumbsup`; the list can be extended with the `simple_history/reactions/allowed_types` filter | Yes      |

**Response:** The updated event object, including its `reactions` field.

Reacting twice with the same type is not an error; the reaction is only stored once. When reactions are turned off the endpoint returns a `404` with the code `rest_reactions_disabled`.

#### Remove Reaction

Remove the current user's reaction from an event.

**Endpoint:** `POST /wp-json/simple-history/v1/events/{id}/unreact`

**Permission:** Same as Get Event.

**Parameters:** Same as React to Event.

**Response:** The updated event object, including its `reactions` field.

When reactions are turned off the endpoint returns a `404` with the code `rest_reactions_disabled`.

### Backfill Status

Get the status of the automatic backfill, which imports existing posts, pages and users into the log after Simple History is first installed.

**Endpoint:** `GET /wp-json/simple-history/v1/backfill-status`

**Permission:** View history capability.

**Example Response:**

```json
{
	"completed": true,
	"type_stats": {
		"post": {
			"available": 300,
			"imported": 53,
			"missed_label": "247 more posts"
		},
		"user": {
			"available": 4,
			"imported": 4,
			"missed_label": "0 more users"
		}
	}
}
```

`type_stats` is keyed by post type, plus `user`. `missed_label` is a translated text for the number of items that were not imported. If the backfill has never run the response is `{ "completed": false, "type_stats": [] }`.

### Search Options

Retrieve data required for search options in the admin UI.

**Endpoint:** `GET /wp-json/simple-history/v1/search-options`

**Permission:** View history capability.

**Example Response:**

```json
{
	"dates": {
		"arr_days_and_pages": [
			{ "daysToShow": 1, "numPages": 0.4 },
			{ "daysToShow": 7, "numPages": 3.2 }
		],
		"daysToShow": 7,
		"result_months": [ { "yearMonth": "2026-11" }, { "yearMonth": "2026-10" } ]
	},
	"loggers": [
		{
			"slug": "SimplePostLogger",
			"name": "Post Logger",
			"search_data": {
				"search": {
					"label": "Posts & Pages",
					"options": [ "SimplePostLogger:post_updated" ]
				},
				"search_all": {
					"label": "All posts & pages activity",
					"options": [ "SimplePostLogger:post_updated" ]
				},
				"search_options": [
					{
						"label": "Posts updated",
						"options": [ "SimplePostLogger:post_updated" ]
					}
				]
			}
		}
	],
	"initiators": [
		{ "value": "wp_user", "label": "WordPress user" },
		{ "value": "wp_cli", "label": "WP-CLI" }
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
	"has_failed_login_limit": false,
	"failed_login_limit_threshold": 0,
	"failed_login_suppressed_count": 0,
	"experimental_features_enabled": false,
	"reactions_enabled": true,
	"events_admin_page_url": "https://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page",
	"settings_page_url": "https://example.com/wp-admin/options-general.php?page=simple_history_settings_page",
	"stats_page_url": "https://example.com/wp-admin/admin.php?page=simple_history_stats_page",
	"current_user_id": 1,
	"current_user_can_manage_options": true,
	"stats": {
		"num_events_today": 12,
		"num_events_last_7_days": 140
	}
}
```

`loggers` only lists the loggers the current user can read. Add-ons can add keys to this response with the `simple_history/search_options_data` filter.

### Stats

Get statistics about Simple History events and activity.

**Permission:** `manage_options` for all stats endpoints.

#### Available Stats Endpoints

-   `GET /wp-json/simple-history/v1/stats/summary` - Get overall summary statistics
-   `GET /wp-json/simple-history/v1/stats/users` - Get user-related statistics
-   `GET /wp-json/simple-history/v1/stats/content` - Get content (posts/pages) statistics
-   `GET /wp-json/simple-history/v1/stats/media` - Get media upload/edit/delete statistics
-   `GET /wp-json/simple-history/v1/stats/plugins` - Get plugin activity statistics
-   `GET /wp-json/simple-history/v1/stats/core` - Get WordPress core update statistics
-   `GET /wp-json/simple-history/v1/stats/notes` - Get statistics for block editor notes (WordPress 6.9+): added, resolved and total
-   `GET /wp-json/simple-history/v1/stats/peak-days` - Get peak activity days
-   `GET /wp-json/simple-history/v1/stats/peak-times` - Get peak activity times
-   `GET /wp-json/simple-history/v1/stats/activity-overview` - Get activity overview by date

**Common Parameters for Stats Endpoints:**

| Parameter         | Type    | Description                                            | Default |
| ----------------- | ------- | ------------------------------------------------------ | ------- |
| `date_from`       | integer | Start date as Unix timestamp (defaults to 30 days ago) | -       |
| `date_to`         | integer | End date as Unix timestamp (defaults to end of today)  | -       |
| `limit`           | integer | Maximum number of items to return (1-100)              | 50      |
| `include_details` | boolean | Whether to include detailed stats                      | false   |

`limit` is used by the `users`, `content` and `plugins` details. `include_details` affects `users`, `content`, `media` and `plugins`.

**Response shape:**

Every stats response has a `date_range` object:

```json
{
	"date_range": {
		"from": 1760745600,
		"to": 1763337599,
		"from_formatted": "October 18, 2025 12:00 am",
		"to_formatted": "November 16, 2025 11:59 pm",
		"duration_days": 30
	}
}
```

The rest depends on the endpoint:

| Endpoint            | Other keys                                                                                                                                                                  |
| ------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `summary`           | `total_events`, `total_events_since_install`, and `totals` with `users`, `content`, `media`, `plugins`, `core` and `notes` objects (the same counts as the `summary` of each endpoint below) |
| `users`             | `summary`: `logins`, `failed_logins`, `profile_updates`, `total`. With `include_details`: `details`                                                                        |
| `content`           | `summary`: `created`, `updated`, `deleted`, `total`. With `include_details`: `details`                                                                                     |
| `media`             | `summary`: `uploads`, `edits`, `deletions`, `total`. With `include_details`: `details` with `uploads`, `edits`, `deletions`                                                |
| `plugins`           | `summary`: `updates`, `installations`, `activations`, `available_updates`, `total`. With `include_details`: `details` with `updates`, `installations`, `activations`, `available_updates` |
| `core`              | `summary`: `updates`, `available_updates`, `total`                                                                                                                         |
| `notes`             | `summary`: `added`, `resolved`, `total`                                                                                                                                    |
| `peak-days`         | `peak_days`                                                                                                                                                                 |
| `peak-times`        | `peak_times`                                                                                                                                                                |
| `activity-overview` | `activity_by_date`                                                                                                                                                          |

### User Search

Search for WordPress users. Used to retrieve WordPress users for display in the user filter dropdown in the admin interface.

**Endpoint:** `GET /wp-json/simple-history/v1/search-user`

**Permission:** `list_users`.

**Parameters:**

| Parameter | Type   | Description            | Required |
| --------- | ------ | ---------------------- | -------- |
| `q`       | string | Search query for users | Yes      |

The query is passed to WordPress's `get_users()` as `*q*`, so it matches anywhere in the searched user columns. At most 20 users are returned. An empty `q` returns a `400`.

**Example Response:**

```json
[
	{
		"ID": "1",
		"user_login": "admin",
		"user_nicename": "admin",
		"user_email": "admin@example.com",
		"display_name": "Admin User"
	},
	{
		"ID": "2",
		"user_login": "editor",
		"user_nicename": "editor",
		"user_email": "editor@example.com",
		"display_name": "Editor User"
	}
]
```

### User Card

Get the data for the popover card shown when you click a user's name or avatar in the log.

**Endpoint:** `GET /wp-json/simple-history/v1/users/{id}/card`

**Permission:** View history capability. Login, email and roles are only filled in for users who may see other users' personal data, `list_users` by default (filterable with `simple_history/user_can_view_user_pii`). For everyone else those fields are empty.

**Parameters:**

| Parameter | Type    | Description       | Required |
| --------- | ------- | ----------------- | -------- |
| `id`      | integer | WordPress user ID | Yes      |

**Example Response:**

```json
{
	"user_id": 1,
	"display_name": "Admin User",
	"user_login": "admin",
	"user_email": "admin@example.com",
	"avatar_url": "https://secure.gravatar.com/avatar/...?s=96",
	"profile_url": "https://example.com/wp-admin/profile.php",
	"roles": [ "administrator" ],
	"has_premium_add_on": false,
	"details": [],
	"actions": [
		{
			"key": "view_profile",
			"label": "User profile",
			"url": "https://example.com/wp-admin/profile.php"
		}
	]
}
```

`profile_url` is an empty string when the current user may not edit that user. Core leaves `details` empty; add-ons fill it with the `simple_history/user_card/details` filter. Each detail item has `key`, `label`, `value` and optionally `type` (`text` or `date`). `actions` can be extended with `simple_history/user_card/actions`.

A user ID that does not exist returns a `404` with the code `rest_user_not_found`.

### Initiator Card

Get the data for the popover card shown for events that were not started by a logged-in user.

**Endpoint:** `GET /wp-json/simple-history/v1/initiators/{type}/card`

**Permission:** View history capability.

**Parameters:**

| Parameter | Type   | Description                                                  | Required |
| --------- | ------ | ------------------------------------------------------------ | -------- |
| `type`    | string | Initiator type. Options: `wp`, `wp_cli`, `web_user`, `other` | Yes      |

**Example Response:**

```json
{
	"initiator": "wp_cli",
	"has_premium_add_on": false,
	"details": [],
	"actions": [
		{
			"key": "view_activity",
			"label": "View WP-CLI events",
			"url": "https://example.com/wp-admin/admin.php?page=simple_history_admin_menu_page&initiator=..."
		}
	]
}
```

The `view_activity` link opens the log filtered by that initiator. Core leaves `details` empty; add-ons fill it with the `simple_history/initiator_card/details` filter. `actions` can be extended with `simple_history/initiator_card/actions`.

### Support Info

Get system information for support requests, the same data shown on the Help & Support page.

**Endpoint:** `GET /wp-json/simple-history/v1/support-info`

**Permission:** `manage_options`.

**Example Response (shortened):**

```json
{
	"info": {
		"wordpress": {
			"version": "6.9",
			"multisite": "No",
			"locale": "en_US",
			"timezone": "Europe/Stockholm",
			"...": "..."
		},
		"server": {
			"php_version": "8.3.0",
			"database": "MySQL 8.0.36",
			"memory_limit": "256M",
			"...": "..."
		},
		"simple_history": {
			"version": "5.x.x",
			"premium": "Not active",
			"total_events": 12345,
			"db_engine": "mysql",
			"retention_days": "60 days",
			"...": "..."
		},
		"warnings": [],
		"theme": { "name": "Twenty Twenty-Five", "version": "1.0", "author": "the WordPress team", "child_theme": "No" },
		"plugins": [ { "name": "Simple History", "version": "5.x.x" } ],
		"mu_plugins": [],
		"dropins": [],
		"loggers": [],
		"browser": { "user_agent": "Mozilla/5.0 ..." }
	},
	"plain_text": "### WordPress ###\n..."
}
```

`info` holds the data as structured sections: `wordpress`, `server`, `simple_history`, `warnings`, `theme`, `plugins`, `mu_plugins`, `dropins`, `loggers` and `browser`. Many values are translated display strings ("Yes", "Enabled", "Not set") rather than booleans. `plain_text` is the same data as text, ready to paste into a support request.

#### Health Check

Check that the REST API can be reached. The support page uses it to detect when something blocks REST requests.

**Endpoint:** `GET /wp-json/simple-history/v1/support-info/health-check`

**Permission:** `manage_options`.

**Example Response:**

```json
{
	"status": "ok",
	"timestamp": "2026-11-18 10:15:00"
}
```

`timestamp` is the current time in the site's timezone.

### User Preferences

These endpoints save a setting for the current user only. There is no user ID parameter, so they cannot change another user's settings.

#### Save Event Log View

Remember which event log view the current user picked.

**Endpoint:** `POST /wp-json/simple-history/v1/events-view` (also accepts `PUT` and `PATCH`)

**Permission:** View history capability.

**Parameters:**

| Parameter | Type   | Description                                         | Required |
| --------- | ------ | --------------------------------------------------- | -------- |
| `view`    | string | The view. Options: `detailed`, `compact`, `table`   | Yes      |

**Example Response:**

```json
{
	"view": "compact"
}
```

#### Save Sidebar Visibility

Save the event log views in which the current user wants the page sidebar hidden. The whole list is sent each time and replaces the stored one.

**Endpoint:** `POST /wp-json/simple-history/v1/sidebar-visibility` (also accepts `PUT` and `PATCH`)

**Permission:** View history capability.

**Parameters:**

| Parameter | Type  | Description                                                                 | Required |
| --------- | ----- | --------------------------------------------------------------------------- | -------- |
| `views`   | array | Views to hide the sidebar in. Allowed items: `detailed`, `compact`, `table` | Yes      |

**Example Response:**

```json
{
	"views": [ "table" ]
}
```

### Email Report Previews

Used by the Email Reports settings page.

#### Send Test Email

Send a test email report, covering the last 7 days, to the configured recipients.

**Endpoint:** `POST /wp-json/simple-history/v1/email-report/preview/email` (also accepts `GET`)

**Permission:** `manage_options`.

**Example Response:**

```json
{
	"success": true,
	"message": "Test email sent to admin@example.com."
}
```

If no valid recipient is configured the endpoint returns a `400` with the code `email_no_recipients`. If no email could be sent it returns a `500` with the code `email_send_failed`. When some emails were sent and some failed, the request succeeds and `message` names the addresses that failed.

#### HTML Preview

Render the email report for the last 7 days.

**Endpoint:** `GET /wp-json/simple-history/v1/email-report/preview/html`

**Permission:** `manage_options`.

**Response:** The report as an HTML page (`Content-Type: text/html`), not JSON.

### Developer Tools

These routes are only registered when the `SIMPLE_HISTORY_DEV` constant is true. They exist for local development and the Playwright tests. All of them require the `activate_plugins` capability.

| Method | Endpoint                                     | Parameters                                                     | Response                                                     |
| ------ | -------------------------------------------- | -------------------------------------------------------------- | ------------------------------------------------------------ |
| `POST` | `/dev-tools/toggle-plugin`                   | `plugin` (required). Only `simple-history-premium/simple-history-premium.php` is allowed | `success`, `plugin`, `is_active`, `was_active`             |
| `GET`  | `/dev-tools/plugin-status`                   | `plugin` (required), a plugin file path                        | `plugin`, `is_active`                                        |
| `POST` | `/dev-tools/toggle-experimental-features`    | -                                                              | `success`, `is_enabled`                                      |
| `POST` | `/dev-tools/set-license-key`                 | `slug` (required), e.g. `simple-history-premium`; `key` (empty string clears the key) | `success`, `slug`, `has_key`                |
| `POST` | `/dev-tools/reset-license-reminder-dismissals` | -                                                            | `success`, `user_id`                                         |
| `GET`  | `/dev-tools/license-reminder-dismissals`     | -                                                              | `user_id`, `dismissed_addons`                                |

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

| Header                          | Description                             |
| ------------------------------- | --------------------------------------- |
| `X-WP-Total`                    | Number of events returned               |
| `X-WP-TotalPages`               | Always `1`                              |
| `X-SimpleHistory-CenterEventId` | The ID of the center event              |
| `X-SimpleHistory-EventsBefore`  | Number of events returned before center |
| `X-SimpleHistory-EventsAfter`   | Number of events returned after center  |
| `X-SimpleHistory-MaxId`         | Highest event ID in results             |
| `X-SimpleHistory-MinId`         | Lowest event ID in results              |
| `X-SimpleHistory-MaxDate`       | Date of the newest event in results     |

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
			console.log( event.message, event.loglevel );
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

Inside WordPress, call the endpoints with `rest_do_request()`. It runs the request in the same PHP process as the current user, so no cookies or nonce are needed. (A `wp_remote_get()` call to your own site does not carry the user's login cookies, so it would be rejected as logged out.)

```php
<?php
// Fetch the latest events.
$request = new WP_REST_Request( 'GET', '/simple-history/v1/events' );
$request->set_query_params( array( 'per_page' => 20 ) );

$response = rest_do_request( $request );

if ( ! $response->is_error() ) {
    $events = $response->get_data();
    // Process events.
}

// Create a new event.
$request = new WP_REST_Request( 'POST', '/simple-history/v1/events' );
$request->set_body_params(
    array(
        'message' => 'Custom event from PHP',
        'note'    => 'This event was created via PHP',
        'level'   => 'info',
    )
);

$response = rest_do_request( $request );

if ( $response->get_status() === 201 ) {
    // Event was logged.
}
```

From outside WordPress, authenticate with an application password, for example `curl -u username:application-password`.

## Error Handling

The REST API returns standard HTTP status codes:

-   `200` - Success
-   `201` - Created (`POST /events`)
-   `400` - Bad request (invalid parameters)
-   `401` - Unauthorized (not authenticated)
-   `403` - Forbidden (insufficient permissions)
-   `404` - Not found
-   `500` - Server error

Error responses include a JSON object with details about the error:

```json
{
	"code": "rest_forbidden_context",
	"message": "Sorry, you are not allowed to view events.",
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
