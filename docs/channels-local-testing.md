# Channels Local Testing Guide

This document describes how to test all Simple History Premium channels locally using the Docker development environment.

## Prerequisites

-   Docker development environment running from `/Users/bonny/Projects/_docker-compose-to-run-on-system-boot`
-   Simple History Premium plugin activated
-   WordPress admin access at http://wordpress-stable-docker-mariadb.test:8282/wp-admin/

## Channel Settings Location

Navigate to **Settings > Simple History > Channels** or directly:
http://wordpress-stable-docker-mariadb.test:8282/wp-admin/admin.php?page=simple_history_settings_menu_slug&selected-sub-tab=channels

---

## 1. File Channel

The File Channel writes events to local JSON or text files.

### Testing

1. Enable the channel and configure a file path
2. Trigger an event (login, edit post, etc.)
3. Check the configured file location for new entries

### Verification

```bash
# View latest entries in the log file
tail -f /path/to/your/configured/logfile.log
```

---

## 2. Syslog Channel

The Syslog Channel forwards events to local or remote syslog servers.

### Mode: Local Syslog

Uses PHP's native `syslog()` function to write to the system log.

**Configuration:**

-   Mode: Local syslog

**Important:** The Docker WordPress containers don't have a syslog daemon installed, so local syslog messages go nowhere when testing with Docker.

**Testing options:**

1. **Verify PHP syslog works on macOS** (proves the underlying function works):

    ```bash
    # Send a test message
    php -r "openlog('SimpleHistoryTest', LOG_PID, LOG_USER); syslog(LOG_INFO, 'Test message'); closelog();"

    # Check if it arrived
    log show --predicate 'eventMessage contains "SimpleHistoryTest"' --last 1m
    ```

2. **Test with WP Playground** (runs PHP natively on macOS):

    ```bash
    npx @wp-playground/cli@latest server \
      --login \
      --wp=6.7 \
      --php=8.2 \
      --mount "$(pwd):/wordpress/wp-content/plugins/simple-history" \
      --mount "/Users/bonny/Projects/Personal/simple-history-add-ons/simple-history-premium:/wordpress/wp-content/plugins/simple-history-premium"
    ```

    Note: WP Playground uses WASM which may sandbox syslog calls.

3. **Trust the code path**: Since Remote UDP/TCP modes test the message formatting and generation, and local mode only differs in using `syslog()` instead of sockets, testing remote modes provides good coverage.

### Mode: Remote UDP / Remote TCP

Sends syslog messages to a remote server via UDP or TCP protocol.

#### Option 1: Docker Syslog Server (Recommended)

A syslog-ng server is included in the Docker Compose stack (`compose-testing.yaml`). It starts automatically with other containers.

```bash
# Start/restart if needed
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot
docker compose up -d syslog-server
```

**Configuration in WordPress:**

| Setting | Value (UDP)         | Value (TCP)         |
| ------- | ------------------- | ------------------- |
| Mode    | Remote syslog (UDP) | Remote syslog (TCP) |
| Host    | `syslog-server`     | `syslog-server`     |
| Port    | `514`               | `514`               |

**View incoming messages:**

```bash
# Follow logs in real-time
docker logs -f syslog-server

# Filter for Simple History messages
docker logs syslog-server 2>&1 | grep -i "SimpleHistory\|Incoming log"
```

**Manage the container:**

```bash
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot

# Stop
docker compose stop syslog-server

# Start
docker compose start syslog-server

# Restart
docker compose restart syslog-server

# View status
docker compose ps syslog-server
```

#### Option 2: Netcat (Quick & Simple)

For quick one-off testing without a persistent container:

```bash
# UDP listener (Terminal 1)
nc -ul 5514

# TCP listener (Terminal 1)
nc -l 5514
```

Then configure WordPress with:

-   Host: `host.docker.internal`
-   Port: `5514`

### Notes

-   `syslog-server` is the Docker container name, accessible from other containers on the same network
-   `host.docker.internal` resolves to the host machine (macOS) from within Docker
-   Port 5514 on host maps to standard port 514 inside the container
-   For production, use rsyslog, syslog-ng, Graylog, Splunk, or a cloud service

---

## 3. External Database Channel

The External Database Channel forwards events to a separate MySQL/MariaDB database.

### Test Database Setup

A test database has been configured on the `mariadb_10_4` container:

```bash
# Run this to create/recreate the test database
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && \
docker compose exec mariadb_10_4 mysql -uroot -p'3qGe9TPbMc8o' -e "
CREATE DATABASE IF NOT EXISTS simple_history_test;
CREATE USER IF NOT EXISTS 'shtest'@'%' IDENTIFIED BY 'shtest';
GRANT ALL PRIVILEGES ON simple_history_test.* TO 'shtest'@'%';
FLUSH PRIVILEGES;
"
```

### Configuration

| Setting  | Value                   |
| -------- | ----------------------- |
| Host     | `mariadb_10_4`          |
| Port     | `3306`                  |
| Database | `simple_history_test`   |
| Username | `shtest`                |
| Password | `shtest`                |
| Table    | `simple_history_events` |

### Testing

1. Configure the channel with the settings above
2. Click "Test Connection" to verify connectivity
3. Enable the channel
4. Trigger an event in WordPress

### Verification

```bash
# View latest events in the external database
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && \
docker compose exec mariadb_10_4 mysql -ushtest -pshtest simple_history_test -e \
"SELECT id, event_date, logger, level, message FROM simple_history_events ORDER BY id DESC LIMIT 10;"
```

### Reset Test Database

To clear all test data and start fresh:

```bash
cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot && \
docker compose exec mariadb_10_4 mysql -ushtest -pshtest simple_history_test -e \
"TRUNCATE TABLE simple_history_events;"
```

### Alternative Database Servers

Other MariaDB/MySQL containers available for testing:

| Container      | Port (host) | Root Password  |
| -------------- | ----------- | -------------- |
| `mariadb_10_4` | 3307        | `3qGe9TPbMc8o` |
| `mariadb_10_5` | 3308        | `3qGe9TPbMc8o` |
| `mariadb_10_6` | 3309        | `3qGe9TPbMc8o` |
| `mariadb_11_4` | 3312        | `3qGe9TPbMc8o` |
| `mysql_5_7`    | 3310        | `3qGe9TPbMc8o` |
| `mysql_8_0`    | 3311        | `3qGe9TPbMc8o` |

---

## Quick Test Workflow

1. **Start listeners** (for syslog testing):

    ```bash
    # UDP test
    nc -ul 5514

    # Or TCP test
    nc -l 5514
    ```

2. **Configure channels** in WordPress admin

3. **Trigger test events**:

    - Log in/out
    - Edit a post
    - Change a plugin setting
    - Use WP-CLI: `docker compose run --rm wpcli_mariadb user update 1 --display_name="Test Name"`

4. **Verify output**:
    - File: Check the log file
    - Syslog Local: Check `log stream` output
    - Syslog Remote: Check netcat output
    - External DB: Query the database

---

## Troubleshooting

### Local syslog not working in Docker

Docker containers typically don't have a syslog daemon. PHP's `syslog()` function will appear to succeed but messages go nowhere. This is expected behavior - test with Remote UDP/TCP modes instead, or use WP Playground.

### Connection refused (Syslog Remote)

-   Ensure the syslog server container is running: `docker ps | grep syslog-server`
-   Check that the port number matches (514 for container, 5514 for host)
-   Verify the container is on the same Docker network
-   If using netcat, ensure it's running before enabling the channel

### External Database connection failed

-   Verify the container is running: `docker compose ps mariadb_10_4`
-   Check credentials are correct
-   Ensure the database exists: `SHOW DATABASES;`
-   Check user permissions: `SHOW GRANTS FOR 'shtest'@'%';`

### No events appearing

-   Verify the channel is enabled
-   Check for PHP errors in `wp-content/debug.log`
-   Ensure the channel's "Test" button works
-   Check consecutive error count hasn't triggered auto-disable
