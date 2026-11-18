#!/bin/bash
# Claude Code status line for this project: the global status line plus a
# clickable link to the local dev site when it is running.
#
# In a worktree from scripts/parallel-dev.sh it shows that worktree's
# Playground site (url/pid/port from .playground.json), otherwise the docker site.
input=$(cat)

GLOBAL="$HOME/.claude/statusline-command.sh"
BASE=""
if [ -f "$GLOBAL" ]; then
    BASE=$(printf '%s' "$input" | bash "$GLOBAL")
fi

ESC=$(printf '\033')

link() {
    printf '🐳 %s]8;;%s/wp-admin/%s\\%s%s]8;;%s\\' "$ESC" "$1" "$ESC" "${1#http://}" "$ESC" "$ESC"
}

down() {
    printf '🐳 %s[31m%s%s[0m' "$ESC" "$1" "$ESC"
}

# Run a command with a time limit. macOS has no `timeout`, perl's alarm does it.
with_timeout() {
    perl -e 'alarm shift; exec @ARGV' "$@"
}

CWD=$(printf '%s' "$input" | jq -r '.workspace.current_dir // .cwd // empty' 2>/dev/null)
TOP=$(git -C "${CWD:-.}" rev-parse --show-toplevel 2>/dev/null)

if [ -n "$TOP" ] && [ -f "$TOP/.playground.json" ]; then
    # One field per read: with `read` over tab-separated values an empty field
    # collapses and shifts the rest (see parallel-dev.sh).
    URL=$(jq -r '.url // empty' "$TOP/.playground.json" 2>/dev/null)
    PID=$(jq -r '.pid // empty' "$TOP/.playground.json" 2>/dev/null)
    PORT=$(jq -r '.port // empty' "$TOP/.playground.json" 2>/dev/null)

    # A live pid AND a listener on the port, like parallel-dev.sh's `up`, so a
    # reused pid after a crash doesn't count as running.
    if [ -n "$URL" ] && [ -n "$PID" ] && [ -n "$PORT" ] && kill -0 "$PID" 2>/dev/null \
        && lsof -ti tcp:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
        DEV=$(link "$URL")
    else
        DEV=$(down "playground down")
    fi
elif case "$TOP" in */.claude/worktrees/*) true ;; *) false ;; esac; then
    # Worktree without a started site.
    DEV=$(down "playground not started")
else
    URL="http://wordpress-stable-docker-mariadb.test:8282"

    # Faster than `docker compose ps`; quiet when Docker isn't running, and cut
    # off after a second so a hung Docker doesn't stall the status line.
    # Anchored name, or wordpress_mariadb_lowend-1 etc. would match too.
    if with_timeout 1 docker ps -q --filter name='^docker-compose-to-run-on-system-boot-wordpress_mariadb-1$' --filter status=running 2>/dev/null | grep -q .; then
        DEV=$(link "$URL")
    else
        DEV=$(down "docker down")
    fi
fi

if [ -n "$BASE" ]; then
    printf '%s | %s\n' "$BASE" "$DEV"
else
    printf '%s\n' "$DEV"
fi
