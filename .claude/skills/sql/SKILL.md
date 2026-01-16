---
name: sql
description: Runs SQL queries against the WordPress development database. Queries tables, inspects Simple History events, checks WordPress data, and helps with debugging. Triggers: "run query", "check database", "SQL", "show tables".
allowed-tools: Read, Bash
---

# Run SQL Queries

Run SQL queries against the WordPress development database.

**Security:** Development database only. Always confirm UPDATE/DELETE with user first.

## Command Pattern

```bash
docker compose exec mariadb mysql -u<USER> -p<PASSWORD> <DATABASE> -e "YOUR_SQL_HERE"
```

Credentials are in `CLAUDE.local.md` under "Database Access".

## Common Queries

```bash
# Show all tables
docker compose exec mariadb mysql -u<USER> -p<PASSWORD> <DATABASE> -e "SHOW TABLES;"

# Recent Simple History events
docker compose exec mariadb mysql -u<USER> -p<PASSWORD> <DATABASE> -e "SELECT * FROM wp_simple_history ORDER BY id DESC LIMIT 10;"

# Describe table structure
docker compose exec mariadb mysql -u<USER> -p<PASSWORD> <DATABASE> -e "DESCRIBE wp_simple_history;"
```

## Table Prefixes

| Prefix | Installation |
|--------|--------------|
| `wp_` | Main install |
| `wp_nightly_` | Nightly build |
| `wp_multisite_` | Multisite |

## Simple History Tables

- `wp_simple_history` - Main events table
- `wp_simple_history_contexts` - Event context/metadata

## Workflow

1. Read credentials from `CLAUDE.local.md`
2. Ask user for query (if not specified)
3. Run query and display results
4. **For UPDATE/DELETE:** Always confirm with user first
