---
name: populate-log
description: Populate the Simple History event log with test data. Use when the user wants to add test events, generate sample data, fill the log, or benchmark with realistic activity. Triggers when user says "populate log", "add test events", "generate events", "fill log", "test data".
allowed-tools: Bash, Read
---

# Populate Event Log

Add test events to the Simple History event log using WP-CLI.

## Prerequisites

-   Commands run from `/Users/bonny/Projects/_docker-compose-to-run-on-system-boot`
-   Requires `SIMPLE_HISTORY_DEV` constant set to `true`

## Base Command

```bash
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && docker compose run --rm wpcli_mariadb simple-history dev populate [OPTIONS]
```

## Options

| Option          | Default | Description                             |
| --------------- | ------- | --------------------------------------- |
| `--count=<n>`   | 1000    | Number of events to create              |
| `--type=<type>` | mixed   | Type of events (see below)              |
| `--days=<n>`    | 90      | Spread events over this many days       |
| `--reactions`   | off     | Add 1-10 random reactions to each event |

## Event Types (`--type`)

| Type       | Description                                                                        | Use Case                            |
| ---------- | ---------------------------------------------------------------------------------- | ----------------------------------- |
| `mixed`    | Realistic distribution: 40% posts, 25% plugins, 15% users, 10% options, 10% custom | General testing, realistic log      |
| `posts`    | Post/page events: created, updated, trashed, restored                              | Testing post-related UI/filters     |
| `plugins`  | Plugin events: activated, deactivated, updated, installed                          | Testing plugin-related UI/filters   |
| `users`    | User events: login, logout, profile edits, user creation                           | Testing user-related UI/filters     |
| `simple`   | Generic SimpleLogger messages (backups, cron, API, forms)                          | Testing fallback search             |
| `large`    | Events with ~2MB context data (simulated API responses)                            | Performance/memory benchmarking     |
| `showcase` | Curated set of specific events (ignores --count), see below                        | UI testing with diverse event types |

## Common Recipes

```bash
# Quick: 50 mixed events for general UI testing
docker compose run --rm wpcli_mariadb simple-history dev populate --count=50 --days=7

# Realistic: 1000 mixed events over 90 days (default)
docker compose run --rm wpcli_mariadb simple-history dev populate

# Heavy: 5000 events for performance testing
docker compose run --rm wpcli_mariadb simple-history dev populate --count=5000

# Type-specific
docker compose run --rm wpcli_mariadb simple-history dev populate --count=200 --type=posts
docker compose run --rm wpcli_mariadb simple-history dev populate --count=200 --type=plugins
docker compose run --rm wpcli_mariadb simple-history dev populate --count=200 --type=users

# Large events for memory/export benchmarking
docker compose run --rm wpcli_mariadb simple-history dev populate --count=100 --type=large --days=30

# Showcase: curated set of specific events for UI testing (ignores --count)
docker compose run --rm wpcli_mariadb simple-history dev populate --type=showcase
```

## Showcase Events

The `showcase` type creates a fixed curated set (ignores `--count`):

1. Successful login (with IP and user agent)
2. Failed login — known user, wrong password (with IP)
3. Failed login — unknown username (bot user agent)
4. Plugin updated (WooCommerce 9.4.3 → 9.5.1)
5. Plugin installed (Query Monitor with full details)
6. Page updated with content diff (About Us)
7. Blog post published
8. Post trashed
9. Plugin activated (Yoast SEO)
10. Plugin deactivated (Hello Dolly)

## Workflow

1. Ask the user what kind of test data they need (if not specified):
    - What type of events? (mixed, posts, plugins, users, simple, large)
    - How many? (default: 50 for quick testing)
    - Over how many days? (default: 7 for recent data, 90 for realistic spread)
2. Run the populate command
3. Confirm the result

## Realistic Features Built Into Populate

The command automatically generates realistic patterns:

-   **Timestamps**: Weekdays 3x more active than weekends, business hours bias, random spike days
-   **Initiators**: ~60% WP_USER, ~15% WP_CLI, ~15% WORDPRESS, ~5% WEB_USER, ~5% OTHER
-   **IP addresses**: ~40% of events get public IPs, some with X-Forwarded-For headers
-   **Context data**: Realistic metadata matching each logger's format

## Implementation

Source: `inc/services/wp-cli-commands/class-wp-cli-populate-command.php`

Available loggers in populate: SimplePluginLogger, SimplePostLogger, SimpleUserLogger, SimpleOptionsLogger, SimpleLogger.
