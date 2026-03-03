# Local WordPress Environments

Three tools for running WordPress locally, each suited to different needs.

## Quick Comparison

| | wp-env | wp-now | Playground CLI |
|---|---|---|---|
| **Database** | MariaDB (Docker) | SQLite | SQLite |
| **Requires Docker** | Yes | No | No |
| **Persistent data** | Yes | Yes (in ~/.wp-now) | No |
| **npm script** | `npm run wp-env:start` | `npm run wp-now` | — |
| **Best for** | Primary development | SQLite testing | Quick smoke tests |

## wp-env (Primary)

Docker-based environment with MariaDB. Closest to production setups.

```bash
npm run wp-env:start
npm run wp-env:stop
npm run wp-env:reset    # Destroy and recreate
```

- Configured via `.wp-env.json`
- Runs at http://localhost:8888
- Login: admin / password
- Supports WP-CLI: `npx wp-env run cli wp plugin list`

## wp-now (SQLite Testing)

Lightweight, no Docker required. Uses SQLite — useful for testing database compatibility since Simple History supports both MySQL/MariaDB and SQLite.

```bash
npm run wp-now
```

- Auto-detects and mounts the plugin from the current directory
- Data persists across restarts in `~/.wp-now`
- Uses SQLite via the [SQLite Database Integration](https://wordpress.org/plugins/sqlite-database-integration/) plugin

### When to use

- Testing that queries work on SQLite (use `Log_Query::get_db_engine()` to check)
- Quick local testing without Docker running
- Verifying the plugin works without MySQL-specific SQL

## Playground CLI (Ephemeral)

For quick throwaway testing with specific WP/PHP versions. Data is lost when the server stops.

```bash
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.9 --php=8.2

# Test with different versions
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.7 --php=7.4
```

| Flag | Description |
|------|-------------|
| `--auto-mount` | Mounts current directory as a plugin |
| `--login` | Auto-logs into wp-admin |
| `--wp=X.X` | WordPress version |
| `--php=X.X` | PHP version |

### When to use

- Testing compatibility across WP/PHP versions
- Demos and screenshots
- No persistent data needed
