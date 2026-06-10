#!/usr/bin/env bash
#
# Parallel development helper: one git worktree + one WordPress Playground
# instance per issue, so several features can be developed and tested at the
# same time without touching the main checkout or the Docker dev site.
#
# Usage:
#   scripts/parallel-dev.sh up <slug> [--premium=<path>] [--no-premium] [--blueprint=<path>]
#     Premium is mounted and activated by default (from the add-ons checkout
#     next to this repo, or $SH_PREMIUM_DIR). Use --no-premium to skip it,
#     or --premium=<path> to mount a specific premium worktree.
#   scripts/parallel-dev.sh status
#   scripts/parallel-dev.sh down <slug> [--remove]
#   scripts/parallel-dev.sh logs <slug> [lines]
#
# State files (gitignored) live inside each worktree:
#   .playground.json            port/pid/mounts for the running instance
#   .playground.log             Playground server output
#   .playground-blueprint.json  generated blueprint for this instance

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MAIN_REPO="$(cd "$SCRIPT_DIR/.." && pwd)"
WORKTREES_DIR="$MAIN_REPO/.claude/worktrees"
BLUEPRINT_TEMPLATE="$MAIN_REPO/.claude/worktree-blueprint.json"
PREMIUM_DEFAULT_DIR="${SH_PREMIUM_DIR:-$MAIN_REPO/../simple-history-add-ons/simple-history-premium}"
BASE_PORT=9400
HELPER_PORT=9399
HELPER_SCRIPT="$SCRIPT_DIR/parallel-dev-helper.js"
HELPER_LOG=/tmp/sh-parallel-dev-helper.log
MU_PLUGINS_DIR="$SCRIPT_DIR/playground-mu-plugins"

usage() {
	cat <<'EOF'
Parallel development helper: one git worktree + one WordPress Playground
instance per issue.

Usage:
  scripts/parallel-dev.sh up <slug> [--premium=<path>] [--no-premium] [--blueprint=<path>]
      Premium is mounted and activated by default (from the add-ons checkout
      next to this repo, or $SH_PREMIUM_DIR). Use --no-premium to skip it,
      or --premium=<path> to mount a specific premium worktree.
  scripts/parallel-dev.sh status
  scripts/parallel-dev.sh down <slug> [--remove]
  scripts/parallel-dev.sh logs <slug> [lines]
  scripts/parallel-dev.sh helper start|stop|status
      Localhost daemon (port 9399) behind the admin bar "open in
      Fork/VS Code/iTerm" shortcuts. Started automatically by `up`.

State files (gitignored) live inside each worktree:
  .playground.json            port/pid/mounts for the running instance
  .playground.log             Playground server output
  .playground-blueprint.json  generated blueprint for this instance
EOF
	exit 1
}

err() {
	echo "Error: $*" >&2
	exit 1
}

require_slug() {
	local slug="${1:-}"

	[ -n "$slug" ] || usage

	if ! echo "$slug" | grep -Eq '^[a-z0-9][a-z0-9-]*$'; then
		err "slug must be lowercase letters, digits and dashes: '$slug'"
	fi
}

worktree_dir() {
	echo "$WORKTREES_DIR/$1"
}

find_free_port() {
	local port=$BASE_PORT

	while lsof -i :"$port" >/dev/null 2>&1; do
		port=$((port + 1))
	done

	echo "$port"
}

# Read a field from a worktree's .playground.json (empty string if missing).
state_get() {
	local dir="$1" field="$2"

	[ -f "$dir/.playground.json" ] || return 0

	jq -r ".$field // empty" "$dir/.playground.json" 2>/dev/null || true
}

pid_alive() {
	[ -n "${1:-}" ] && kill -0 "$1" 2>/dev/null
}

# Git status excluding our own state files (untracked until .gitignore
# catches up in the worktree's branch).
worktree_dirty_output() {
	git -C "$1" status --porcelain 2>/dev/null | grep -vE '\.playground[.-]' || true
}

helper_running() {
	curl -s --max-time 1 "http://127.0.0.1:$HELPER_PORT/ping" 2>/dev/null | grep -q pong
}

helper_start() {
	helper_running && return 0

	local roots=("$MAIN_REPO")
	local addons_root

	addons_root="$(cd "$PREMIUM_DEFAULT_DIR/.." 2>/dev/null && pwd || true)"
	[ -n "$addons_root" ] && roots+=("$addons_root")

	echo "==> starting open-in-app helper on port $HELPER_PORT"
	nohup node "$HELPER_SCRIPT" "${roots[@]}" > "$HELPER_LOG" 2>&1 &

	local waited=0
	while [ $waited -lt 10 ]; do
		helper_running && return 0
		sleep 1
		waited=$((waited + 1))
	done

	echo "Warning: helper did not respond — see $HELPER_LOG (admin bar shortcuts won't work)"
}

helper_stop() {
	local pid
	pid="$(lsof -ti tcp:"$HELPER_PORT" 2>/dev/null || true)"

	if [ -n "$pid" ]; then
		kill $pid 2>/dev/null || true
		echo "Helper stopped."
	else
		echo "Helper not running."
	fi
}

# Valet (or any dnsmasq setup) resolving *.test to 127.0.0.1 lets each
# instance get a readable URL like http://issue-15-whats-new.test:9400.
test_domains_available() {
	[ -f /etc/resolver/test ]
}

site_url_for() {
	local slug="$1" port="$2"

	if test_domains_available; then
		echo "http://$slug.test:$port"
	else
		echo "http://localhost:$port"
	fi
}

cmd_up() {
	local slug="" premium="" premium_explicit=0 no_premium=0 blueprint_override=""

	for arg in "$@"; do
		case "$arg" in
			--premium) premium="$PREMIUM_DEFAULT_DIR"; premium_explicit=1 ;;
			--premium=*) premium="${arg#--premium=}"; premium_explicit=1 ;;
			--no-premium) no_premium=1 ;;
			--blueprint=*) blueprint_override="${arg#--blueprint=}" ;;
			-*) err "unknown flag: $arg" ;;
			*) slug="$arg" ;;
		esac
	done

	require_slug "$slug"

	# Premium is mounted by default when the add-ons checkout exists.
	if [ "$no_premium" = 1 ]; then
		premium=""
	elif [ -z "$premium" ]; then
		premium="$PREMIUM_DEFAULT_DIR"
	fi

	if [ -n "$premium" ] && [ ! -f "$premium/simple-history-premium.php" ]; then
		if [ "$premium_explicit" = 1 ]; then
			err "premium plugin not found at: $premium"
		fi

		echo "Note: premium checkout not found at $premium — starting without premium"
		premium=""
	fi

	local dir branch
	dir="$(worktree_dir "$slug")"
	branch="worktree-$slug"

	# Create the worktree if it doesn't exist yet, reusing the branch if it does.
	if [ ! -d "$dir" ]; then
		if git -C "$MAIN_REPO" show-ref --verify --quiet "refs/heads/$branch"; then
			git -C "$MAIN_REPO" worktree add "$dir" "$branch"
		else
			git -C "$MAIN_REPO" worktree add "$dir" -b "$branch"
		fi
	fi

	# Already running? Just report it.
	local existing_pid existing_port
	existing_pid="$(state_get "$dir" pid)"
	existing_port="$(state_get "$dir" port)"

	if pid_alive "$existing_pid"; then
		echo "Already running: $(state_get "$dir" url) ($slug)"
		return 0
	fi

	echo "==> [$slug] installing dependencies and building assets"
	(
		cd "$dir"
		# npm ci: exact install from the lockfile, never modifies it
		# (npm install can rewrite package-lock.json and dirty the worktree).
		[ -d node_modules ] || npm ci
		npm run build
	)

	local port site_url
	port="$(find_free_port)"
	site_url="$(site_url_for "$slug" "$port")"

	# Generate the per-instance blueprint with the slug as site title.
	local blueprint="$dir/.playground-blueprint.json"
	sed "s/WORKTREE_NAME/$slug/" "${blueprint_override:-$BLUEPRINT_TEMPLATE}" > "$blueprint"

	if [ -n "$premium" ]; then
		jq '.steps += [{"step": "activatePlugin", "pluginPath": "simple-history-premium/simple-history-premium.php"}]' \
			"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
	fi

	# With a named URL, WordPress must treat it as canonical — otherwise it
	# redirects back to 127.0.0.1 and REST calls become cross-origin.
	if test_domains_available; then
		jq --arg url "$site_url" \
			'.steps += [{"step": "defineWpConfigConsts", "consts": {"WP_HOME": $url, "WP_SITEURL": $url}}]' \
			"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
	fi

	# Dev toolbar: tell the instance which worktree it serves and where the
	# open-in-app helper listens (read by the mounted mu-plugin).
	jq --arg path "$dir" --argjson hport "$HELPER_PORT" \
		'.steps += [{"step": "defineWpConfigConsts", "consts": {"SH_DEV_WORKTREE_PATH": $path, "SH_DEV_HELPER_PORT": $hport}}]' \
		"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"

	helper_start

	echo "==> [$slug] starting Playground on port $port"

	local mounts=(--mount=.:/wordpress/wp-content/plugins/simple-history)

	if [ -n "$premium" ]; then
		mounts+=("--mount=$premium:/wordpress/wp-content/plugins/simple-history-premium")
	fi

	mounts+=("--mount=$MU_PLUGINS_DIR:/wordpress/wp-content/mu-plugins")

	(
		cd "$dir"
		nohup npx @wp-playground/cli@latest server \
			--port="$port" \
			"${mounts[@]}" \
			--blueprint="$blueprint" \
			> .playground.log 2>&1 &
		echo $! > .playground.pid
	)

	local pid
	pid="$(cat "$dir/.playground.pid")"
	rm -f "$dir/.playground.pid"

	# Wait until the site responds (first run downloads WordPress, allow time).
	# Any 2xx/3xx counts — the blueprint's login step answers with a redirect.
	local waited=0 http_code="" ready=0

	while [ $waited -lt 180 ]; do
		http_code="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$port" 2>/dev/null || true)"

		case "$http_code" in
			2*|3*) ready=1; break ;;
		esac

		if ! pid_alive "$pid"; then
			err "Playground exited early — see $dir/.playground.log"
		fi

		sleep 2
		waited=$((waited + 2))
	done

	[ "$ready" = 1 ] || err "Playground did not respond within ${waited}s — see $dir/.playground.log"

	jq -n \
		--arg slug "$slug" \
		--arg branch "$branch" \
		--argjson port "$port" \
		--argjson pid "$pid" \
		--arg url "$site_url" \
		--arg premium "$premium" \
		--arg started "$(date '+%Y-%m-%d %H:%M:%S')" \
		'{slug: $slug, branch: $branch, port: $port, pid: $pid, url: $url, premium: $premium, started: $started}' \
		> "$dir/.playground.json"

	echo ""
	echo "Ready: $site_url"
	echo "  worktree: $dir"
	echo "  branch:   $branch"
	[ -n "$premium" ] && echo "  premium:  $premium"
	echo "  log:      $dir/.playground.log"
}

cmd_helper() {
	case "${1:-}" in
		start) helper_start ;;
		stop) helper_stop ;;
		status)
			if helper_running; then
				echo "Helper running on port $HELPER_PORT"
			else
				echo "Helper not running"
			fi
			;;
		*) usage ;;
	esac
}

cmd_status() {
	[ -d "$WORKTREES_DIR" ] || { echo "No worktrees."; return 0; }

	if helper_running; then
		echo "Open-in-app helper: running (port $HELPER_PORT)"
	else
		echo "Open-in-app helper: not running (scripts/parallel-dev.sh helper start)"
	fi
	echo ""

	printf '%-28s %-32s %-6s %-42s %-8s %-6s %s\n' SLUG BRANCH PORT URL RUNNING DIRTY PREMIUM

	local dir slug branch port pid url premium running dirty
	for dir in "$WORKTREES_DIR"/*/; do
		[ -d "$dir" ] || continue

		dir="${dir%/}"
		slug="${dir##*/}"
		branch="$(git -C "$dir" branch --show-current 2>/dev/null || echo '?')"
		port="$(state_get "$dir" port)"
		pid="$(state_get "$dir" pid)"
		url="$(state_get "$dir" url)"
		premium="$(state_get "$dir" premium)"

		# Older state files predate the url field.
		[ -z "$url" ] && [ -n "$port" ] && url="http://localhost:$port"

		if pid_alive "$pid"; then
			running="yes"
		elif [ -n "$pid" ]; then
			running="dead"
		else
			running="-"
		fi

		if [ -n "$(worktree_dirty_output "$dir")" ]; then
			dirty="yes"
		else
			dirty="no"
		fi

		printf '%-28s %-32s %-6s %-42s %-8s %-6s %s\n' \
			"$slug" "$branch" "${port:--}" "${url:--}" \
			"$running" "$dirty" "${premium:--}"
	done
}

cmd_down() {
	local slug="" remove=0

	for arg in "$@"; do
		case "$arg" in
			--remove) remove=1 ;;
			-*) err "unknown flag: $arg" ;;
			*) slug="$arg" ;;
		esac
	done

	require_slug "$slug"

	local dir
	dir="$(worktree_dir "$slug")"

	[ -d "$dir" ] || err "no worktree at $dir"

	local pid port
	pid="$(state_get "$dir" pid)"
	port="$(state_get "$dir" port)"

	if pid_alive "$pid"; then
		echo "==> [$slug] stopping Playground (pid $pid)"
		kill "$pid" 2>/dev/null || true
	fi

	# Belt and braces: kill whatever still listens on the recorded port.
	if [ -n "$port" ]; then
		local listener
		listener="$(lsof -ti tcp:"$port" 2>/dev/null || true)"
		[ -n "$listener" ] && kill $listener 2>/dev/null || true
	fi

	rm -f "$dir/.playground.json"

	if [ "$remove" = 1 ]; then
		if [ -n "$(worktree_dirty_output "$dir")" ]; then
			err "worktree has uncommitted changes — commit or discard them first: $dir"
		fi

		echo "==> [$slug] removing worktree"
		rm -f "$dir/.playground.log" "$dir/.playground-blueprint.json"
		git -C "$MAIN_REPO" worktree remove --force "$dir"
		echo "Removed. Branch worktree-$slug still exists (delete with: git branch -D worktree-$slug)"
	else
		echo "Stopped. Worktree kept at $dir"
	fi
}

cmd_logs() {
	local slug="${1:-}"
	local lines="${2:-50}"

	require_slug "$slug"

	local dir
	dir="$(worktree_dir "$slug")"

	[ -f "$dir/.playground.log" ] || err "no log at $dir/.playground.log"

	tail -n "$lines" "$dir/.playground.log"
}

case "${1:-}" in
	up) shift; cmd_up "$@" ;;
	status) shift; cmd_status "$@" ;;
	down) shift; cmd_down "$@" ;;
	logs) shift; cmd_logs "$@" ;;
	helper) shift; cmd_helper "$@" ;;
	*) usage ;;
esac
