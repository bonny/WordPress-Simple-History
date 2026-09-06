# Local WordPress Environments

Two tools for running WordPress locally, each suited to different needs.

## Quick Comparison

|                     | wp-env                 | Playground CLI                    |
| ------------------- | ---------------------- | --------------------------------- |
| **Database**        | MariaDB (Docker)       | SQLite                            |
| **Requires Docker** | Yes                    | No                                |
| **Persistent data** | Yes                    | No                                |
| **npm script**      | `npm run wp-env:start` | `npm run playground:start`        |
| **Best for**        | Primary development    | SQLite testing, quick smoke tests |

## wp-env (Primary)

Docker-based environment with MariaDB. Closest to production setups.

```bash
npm run wp-env:start
npm run wp-env:stop
npm run wp-env:reset    # Destroy and recreate
```

-   Configured via `.wp-env.json`
-   Runs at http://localhost:8888
-   Login: admin / password
-   Supports WP-CLI: `npx wp-env run cli wp plugin list`

## Playground CLI (Ephemeral)

Lightweight, no Docker required. Uses SQLite via WebAssembly — good for quick throwaway testing and for verifying database compatibility, since Simple History supports both MySQL/MariaDB and SQLite. Data is lost when the server stops.

```bash
npm run playground:start

# Or run directly with specific WP/PHP versions
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.9 --php=8.2

# Test with different versions
npx @wp-playground/cli@latest server --auto-mount --login --wp=6.7 --php=7.4
```

| Flag           | Description                          |
| -------------- | ------------------------------------ |
| `--auto-mount` | Mounts current directory as a plugin |
| `--login`      | Auto-logs into wp-admin              |
| `--wp=X.X`     | WordPress version                    |
| `--php=X.X`    | PHP version                          |

### When to use

-   Testing that queries work on SQLite (use `Log_Query::get_db_engine()` to check)
-   Quick local testing without Docker running
-   Verifying the plugin works without MySQL-specific SQL
-   Testing compatibility across WP/PHP versions
-   Demos and screenshots

> **Note:** `wp-now` (the old `npm run wp-now` SQLite tool) is [deprecated](https://make.wordpress.org/playground/2026/06/08/wp-now-is-deprecated-migrate-to-playground-cli/) — Playground CLI with `--auto-mount` replaces it.
