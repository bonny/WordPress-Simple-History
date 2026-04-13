---
name: wp-playground
description: Use for quick local testing with WordPress Playground CLI. Spins up a disposable WordPress instance with the plugin mounted — no Docker needed. Ideal for worktrees, quick feature checks, and parallel testing on different branches.
allowed-tools: Bash, Read, Write, Edit, Glob
---

# WordPress Playground CLI for Quick Testing

Use `@wp-playground/cli` to spin up fast, disposable WordPress instances for testing. No Docker required — uses WebAssembly + SQLite.

## When to Use

-   **Worktrees**: each worktree gets its own Playground instance on a unique port
-   **Quick feature check**: spin up a site to verify a change without touching the Docker dev environment
-   **Parallel branch testing**: multiple branches in separate browser tabs
-   **UI review**: fast visual check of a feature or fix

For thorough testing that needs MySQL or WP-CLI, use the Docker Compose setup instead.

## Quick Start (current directory)

**Before starting**, ensure build assets exist — Playground mounts the directory as-is:

```bash
# Install dependencies if node_modules is missing (common in worktrees)
[ -d node_modules ] || npm install

# Build JS/CSS assets (required — Playground has no build step)
npm run build
```

Then start:

```bash
npm run playground:start
```

This runs `npx @wp-playground/cli server` with the plugin mounted and the blueprint applied.

## With a Blueprint (recommended)

Use the project blueprint for a pre-configured environment:

```bash
npx @wp-playground/cli server \
  --mount=.:/wordpress/wp-content/plugins/simple-history \
  --blueprint=.claude/worktree-blueprint.json \
  --port=9400
```

The blueprint (`.claude/worktree-blueprint.json`) enables dev mode, activates the plugin, logs in automatically, and enables experimental features.

## Multiple Instances (worktrees or branches)

Each instance needs a unique port. Find the next available one:

```bash
PORT=9400; while lsof -i :$PORT >/dev/null 2>&1; do PORT=$((PORT+1)); done; echo $PORT
```

Then start each instance:

```bash
# Worktree 1
cd .claude/worktrees/issue-42
npx @wp-playground/cli server --mount=.:/wordpress/wp-content/plugins/simple-history --port=9400

# Worktree 2
cd .claude/worktrees/issue-55
npx @wp-playground/cli server --mount=.:/wordpress/wp-content/plugins/simple-history --port=9401
```

Each gets its own browser tab: `http://localhost:9400`, `http://localhost:9401`, etc.

## Useful Flags

| Flag               | Purpose                               |
| ------------------ | ------------------------------------- |
| `--auto-mount`     | Auto-detect plugin/theme and mount it |
| `--mount=src:dest` | Manually mount a directory            |
| `--port=NNNN`      | Set the server port                   |
| `--wp=6.3`         | Use a specific WordPress version      |
| `--php=7.4`        | Use a specific PHP version            |
| `--blueprint=path` | Apply a blueprint JSON on startup     |
| `--login`          | Auto-login as admin                   |

## Limitations

-   **SQLite only** — not MySQL/MariaDB. Use Docker for database-specific testing.
-   **No WP-CLI** — use `wp-env` or Docker if you need CLI commands.
-   **Ephemeral by default** — state may not persist across restarts.

## Deprecated: wp-now

`@wp-now/wp-now` is deprecated. Use `@wp-playground/cli` with `--auto-mount` instead — it provides the same auto-detection behavior.

## See Also

-   **Worktree skill** — full worktree workflow including Playground setup
-   **npm scripts**: `playground:start`, `playground:server`
