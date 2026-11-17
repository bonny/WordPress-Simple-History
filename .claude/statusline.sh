#!/bin/bash
# Statusrad för Claude Code i det här projektet: den globala statusraden
# plus en klickbar länk till lokala dev-sajten om den körs.
#
# I en worktree från scripts/parallel-dev.sh visas worktreens Playground-sajt
# (url/pid/port från .playground.json), annars docker-sajten.
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

CWD=$(printf '%s' "$input" | jq -r '.workspace.current_dir // .cwd // empty' 2>/dev/null)
TOP=$(git -C "${CWD:-.}" rev-parse --show-toplevel 2>/dev/null)

if [ -n "$TOP" ] && [ -f "$TOP/.playground.json" ]; then
    IFS=$'\t' read -r URL PID PORT <<< "$(jq -r '[.url, .pid, .port] | map(. // "" | tostring) | @tsv' "$TOP/.playground.json" 2>/dev/null)"

    # Samma koll som parallel-dev.sh: levande pid OCH lyssnare på porten,
    # så att en återanvänd pid efter en krasch inte räknas.
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null \
        && lsof -ti tcp:"$PORT" -sTCP:LISTEN >/dev/null 2>&1; then
        DEV=$(link "$URL")
    else
        DEV=$(down "playground nere")
    fi
elif case "$TOP" in */.claude/worktrees/*) true ;; *) false ;; esac; then
    # Worktree utan startad sajt.
    DEV=$(down "playground ej startad")
else
    URL="http://wordpress-stable-docker-mariadb.test:8282"

    # Snabbare än `docker compose ps`; faller tyst om Docker inte är igång.
    # Ankrat namn, annars matchar t.ex. wordpress_mariadb_lowend-1 också.
    if docker ps -q --filter name='^docker-compose-to-run-on-system-boot-wordpress_mariadb-1$' --filter status=running 2>/dev/null | grep -q .; then
        DEV=$(link "$URL")
    else
        DEV=$(down "docker nere")
    fi
fi

if [ -n "$BASE" ]; then
    printf '%s | %s\n' "$BASE" "$DEV"
else
    printf '%s\n' "$DEV"
fi
