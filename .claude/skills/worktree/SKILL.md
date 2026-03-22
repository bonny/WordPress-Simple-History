---
name: worktree
description: Create an isolated git worktree for extended development work with its own WordPress test environment. Useful for multi-day features or risky changes that need parallel testing.
allowed-tools: Bash, Read, Write, Edit, Glob
disable-model-invocation: true
---

# Worktree Workflow

Use git worktrees for issues that require extended development (multiple days, risky changes, or parallel feature work). Each worktree gets its own branch, dependencies, and a WordPress test site.

## When to Use Worktrees

- Issue has `size: 2-medium` or `3-large`
- Issue has `complexity: branch`
- Work will span multiple sessions/days
- You want to test a feature in isolation without affecting the main branch
- You need to work on multiple features in parallel

## Creating a Worktree

### Step 1: Create the worktree

Use the `EnterWorktree` Claude Code tool (not a bash command) with a descriptive name based on the issue:

```
EnterWorktree(name="issue-name-short")
```

This creates a worktree at `.claude/worktrees/<name>` on branch `worktree-<name>`.

### Step 2: Install dependencies

```bash
npm install
```

### Step 3: Build assets

```bash
npm run build
```

### Step 4: Start WordPress Playground CLI

First generate a blueprint from the template, replacing the worktree name:

```bash
WORKTREE_NAME="<worktree-name>"
MAIN_REPO="$(dirname "$(git rev-parse --git-common-dir)")"
sed "s/WORKTREE_NAME/$WORKTREE_NAME/" "$MAIN_REPO/.claude/worktree-blueprint.json" > /tmp/wp-blueprint-$WORKTREE_NAME.json
```

Then start the server:

```bash
npx @wp-playground/cli@latest server \
  --port=<unique-port> \
  --mount=.:/wordpress/wp-content/plugins/simple-history \
  --blueprint=/tmp/wp-blueprint-$WORKTREE_NAME.json &
```

The blueprint (`/.claude/worktree-blueprint.json`) automatically:
- Sets `SIMPLE_HISTORY_DEV` constant (enables dev mode badges and tools)
- Logs in as admin (sets WP auth cookies on first visit)
- Activates the Simple History plugin
- Enables experimental features
- Sets the site title to the worktree name (visible in browser tab and wp-admin header)

### Multisite

If the issue involves network/multisite functionality, ask the user if they want a multisite install. If yes, add an `enableMultisite` step to the generated blueprint before starting:

```bash
# Add enableMultisite as the first step in the blueprint
jq '.steps = [{"step": "enableMultisite"}] + .steps' /tmp/wp-blueprint-$WORKTREE_NAME.json > /tmp/wp-blueprint-$WORKTREE_NAME-tmp.json && mv /tmp/wp-blueprint-$WORKTREE_NAME-tmp.json /tmp/wp-blueprint-$WORKTREE_NAME.json
```

**Port assignment:** Find the next available port automatically:

```bash
PORT=9400; while lsof -i :$PORT >/dev/null 2>&1; do PORT=$((PORT+1)); done; echo $PORT
```

Ports start at 9400 and increment for each active worktree.

### Step 5: Report the URL

Tell the user:
- Worktree path
- Branch name
- WordPress test site URL (e.g., `http://localhost:9400`)
- Login credentials (Playground auto-logs in with `--login`)

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

- **`.git` is a file in worktrees** (not a directory) — this is how you can tell you're in a worktree
- **`node_modules` is not shared** — each worktree needs its own `npm install`
- **Two worktrees cannot have the same branch checked out**
- **Build assets after copying files** — always run `npm run build` after setup
- **Docker dev site is separate** — the main Docker-based dev site at port 8282 is unaffected by worktrees
- **Auto-login needs clean cookies** — if the browser visited the Playground URL before the blueprint was applied, old cookies can prevent auto-login. Use an incognito window or clear cookies for `localhost:<port>`
