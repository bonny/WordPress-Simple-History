---
name: worktree
description: Creates an isolated git worktree with its own WordPress test environment. Use when working on multi-day features or risky changes needing parallel testing.
allowed-tools: Bash, Read, Write, Edit, Glob
disable-model-invocation: true
---

# Worktree Workflow

Use git worktrees for issues that require extended development (multiple days, risky changes, or parallel feature work). Each worktree gets its own branch, dependencies, and a WordPress test site.

## Quick Path: scripts/parallel-dev.sh

For the common case (worktree + Playground instance per issue), use the helper script instead of the manual steps below:

```bash
# Create worktree, npm install + build, start Playground on a free port (9400+).
# With Valet's .test resolution available (/etc/resolver/test), the instance
# gets a named canonical URL like http://issue-42-some-feature.test:9400
# (WP_HOME/WP_SITEURL set via blueprint — avoids 127.0.0.1 redirects and
# Site Editor CORS issues). Falls back to http://localhost:<port> otherwise.
scripts/parallel-dev.sh up issue-42-some-feature

# Premium is mounted + activated by default (add-ons main checkout, or
# $SH_PREMIUM_DIR). Opt out, or point at a premium worktree:
scripts/parallel-dev.sh up issue-42-some-feature --no-premium
scripts/parallel-dev.sh up issue-42-premium-thing --premium=/path/to/premium-worktree

# Overview of all worktrees: port, URL, running?, dirty?
scripts/parallel-dev.sh status

# Stop the instance / stop and remove the worktree (refuses if dirty)
scripts/parallel-dev.sh down issue-42-some-feature
scripts/parallel-dev.sh down issue-42-some-feature --remove

# Tail the Playground server log
scripts/parallel-dev.sh logs issue-42-some-feature
```

Per-instance state lives inside the worktree as `.playground.json` / `.playground.log` / `.playground-blueprint.json` (all gitignored).

### REST API access (Basic auth)

Instances started by `parallel-dev.sh` get a fixed application password (provisioned through the core API by a blueprint `runPHP` step), and a mounted mu-plugin (`scripts/playground-mu-plugins/sh-allow-basic-auth.php`) lets REST and Basic-auth requests through Playground's auto-login redirect — so plain `curl -u` works. Read the credentials from `.playground.json` (`app_user` / `app_password`) rather than hardcoding them:

```bash
URL=$(jq -r .url .playground.json)
curl -u "$(jq -r '.app_user + ":" + .app_password' .playground.json)" \
  "$URL/wp-json/simple-history/v1/events?per_page=5"
```

Caveats:

-   Instances started before this feature existed, and worktrees set up via the manual steps below, have no app password. If `jq -r .app_password .playground.json` returns `null`, restart with `down` + `up`.
-   The script sets `WP_ENVIRONMENT_TYPE=local` (app passwords are unavailable over plain HTTP otherwise) unless a custom `--blueprint` defines its own environment type — so environment-gated behavior differs from a default production site.
-   Don't authenticate REST calls with the WP auth cookies — cookie auth without a nonce makes REST return 401.

For wp-admin HTML (non-REST), the first request auto-logs in and sets cookies — use a curl cookie jar:

```bash
jar=$(mktemp)
curl -s -c "$jar" -o /dev/null "$URL/"
curl -s -b "$jar" "$URL/wp-admin/admin.php?page=simple_history_admin_menu_page"
```

Run Playwright against an instance from inside its worktree — read the URL from the state file, since the instance's canonical URL may be the named `.test` one and `localhost` would get canonical-redirected cross-origin:

```bash
PLAYWRIGHT_BASE_URL=$(jq -r .url .playground.json) WP_ADMIN_USER=admin WP_ADMIN_PASSWORD=password \
  npx playwright test tests/playwright/<spec>.spec.js
```

The manual steps below remain useful for special setups (multisite, custom blueprints).

## When to Use Worktrees

-   Issue has `size: 2-medium` or `3-large`
-   Issue has `complexity: branch`
-   Work will span multiple sessions/days
-   You want to test a feature in isolation without affecting the main branch
-   You need to work on multiple features in parallel

## Creating a Worktree

### Step 1: Create the worktree

Use the `EnterWorktree` Claude Code tool (not a bash command) with a descriptive name based on the issue:

```
EnterWorktree(name="issue-name-short")
```

This creates a worktree at `.claude/worktrees/<name>` on branch `worktree-<name>`.

### Step 2: Start Playground (mandatory — do this immediately)

From the **main repo root** (not the worktree), run:

```bash
cd "$(git rev-parse --git-common-dir)/.."
scripts/parallel-dev.sh up issue-name-short
```

This handles `npm ci`, `npm run build`, port allocation, and Playground startup in one command. Once it finishes, read `.claude/worktrees/issue-name-short/.playground.json` to get the URL and report it to the user:

```bash
jq -r .url .claude/worktrees/issue-name-short/.playground.json
```

**Always report the URL immediately after worktree creation.** The user needs it to preview the feature without merging.

Premium is mounted and activated by default. Opt out if the issue is core-only:

```bash
scripts/parallel-dev.sh up issue-name-short --no-premium
```

### Multisite

If the issue involves network/multisite functionality, ask the user if they want a multisite install. If yes, pass a custom blueprint:

```bash
# Generate a multisite blueprint from the template
WORKTREE_NAME="issue-name-short"
MAIN_REPO="$(git rev-parse --git-common-dir)/.."
sed "s/WORKTREE_NAME/$WORKTREE_NAME/" "$MAIN_REPO/.claude/worktree-blueprint.json" > /tmp/wp-blueprint-$WORKTREE_NAME.json
jq '.steps = [{"step": "enableMultisite"}] + .steps' /tmp/wp-blueprint-$WORKTREE_NAME.json > /tmp/wp-blueprint-$WORKTREE_NAME-tmp.json && mv /tmp/wp-blueprint-$WORKTREE_NAME-tmp.json /tmp/wp-blueprint-$WORKTREE_NAME.json

scripts/parallel-dev.sh up $WORKTREE_NAME --blueprint=/tmp/wp-blueprint-$WORKTREE_NAME.json
```

## Copying Uncommitted Changes

If the user has uncommitted changes in the main repo that should be in the worktree:

```bash
# From the main repo, list changed/untracked files
git -C "$(git rev-parse --show-toplevel)" status --short

# Copy specific files to the worktree
cp path/to/file ./path/to/file
```

**Important:** Also copy any untracked files that are imported by modified files (e.g., new components).

## Managing Worktrees

### List all worktrees

```bash
git -C "$(git rev-parse --show-toplevel)" worktree list
```

### Switch to an existing worktree

Just `cd` to its path. All git and npm commands work as usual.

### Stop the Playground server

```bash
# Find the process
lsof -i :<port> | grep LISTEN

# Kill it
kill <pid>
```

### Remove a worktree when done

```bash
# Stop any running Playground server first
# Then from the main repo:
git -C "$(git rev-parse --show-toplevel)" worktree remove .claude/worktrees/<name>
```

Or use the `ExitWorktree` tool if in a Claude Code session.

## Merging Back

When the feature is complete and tested:

1. Commit all changes in the worktree
2. Switch to the main branch in the main repo
3. Merge the worktree branch:
    ```bash
    cd "$(git rev-parse --show-toplevel)"
    git merge worktree-<name>
    ```
4. Remove the worktree

## Key Things to Remember

-   **`.git` is a file in worktrees** (not a directory) — this is how you can tell you're in a worktree
-   **`node_modules` is not shared** — each worktree needs its own `npm install`
-   **Two worktrees cannot have the same branch checked out**
-   **Build assets after copying files** — always run `npm run build` after setup
-   **Docker dev site is separate** — the main Docker-based dev site at port 8282 is unaffected by worktrees
-   **Auto-login needs clean cookies** — if the browser visited the Playground URL before the blueprint was applied, old cookies can prevent auto-login. Use an incognito window or clear cookies for `localhost:<port>`
