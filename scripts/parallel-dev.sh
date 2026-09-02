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
HELPER_TOKEN_FILE="$MAIN_REPO/.claude/parallel-dev-helper-token"
MU_PLUGINS_DIR="$SCRIPT_DIR/playground-mu-plugins"

# Deterministic application password provisioned in every instance, so REST
# calls work with plain Basic auth (no cookie/nonce dance). Local-only, so a
# fixed value is fine. Validated as alphanumeric below — WordPress strips
# other chars from application passwords during authentication, so anything
# else would hash one value and verify another (silent 401s).
APP_PASSWORD_USER="admin"
APP_PASSWORD="paralleldevpassword"
PROVISION_PHP_FILE="$SCRIPT_DIR/playground-provision-app-password.php"

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

if ! [[ "$APP_PASSWORD_USER" =~ ^[A-Za-z0-9]+$ && "$APP_PASSWORD" =~ ^[A-Za-z0-9]+$ ]]; then
	err "APP_PASSWORD_USER and APP_PASSWORD must be alphanumeric"
fi

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

# Git status excluding our own state files at the worktree root (untracked
# until .gitignore catches up in the worktree's branch). If git itself fails
# (corrupt worktree, pruned admin dir), emit the error so callers treat the
# worktree as unsafe instead of clean.
worktree_dirty_output() {
	local out

	if ! out="$(git -C "$1" status --porcelain 2>&1)"; then
		echo "git-error: $out"
		return
	fi

	echo "$out" | grep -vE '^.. \.playground(\.json|\.log|-blueprint\.json)$' || true
}

helper_running() {
	curl -s --max-time 1 "http://127.0.0.1:$HELPER_PORT/ping" 2>/dev/null | grep -q pong
}

# Shared secret between instances and the helper, so random web pages
# can't trigger /open. Stable across helper restarts.
helper_token() {
	if [ ! -f "$HELPER_TOKEN_FILE" ]; then
		uuidgen | tr -d '-' > "$HELPER_TOKEN_FILE"
	fi

	cat "$HELPER_TOKEN_FILE"
}

helper_start() {
	helper_running && return 0

	local roots=("$MAIN_REPO")
	local addons_root

	addons_root="$(cd "$PREMIUM_DEFAULT_DIR/.." 2>/dev/null && pwd || true)"
	[ -n "$addons_root" ] && roots+=("$addons_root")

	echo "==> starting open-in-app helper on port $HELPER_PORT"
	nohup node "$HELPER_SCRIPT" "$HELPER_PORT" "$(helper_token)" "${roots[@]}" > "$HELPER_LOG" 2>&1 &

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
	pid="$(lsof -ti tcp:"$HELPER_PORT" -sTCP:LISTEN 2>/dev/null || true)"

	if [ -n "$pid" ]; then
		kill $pid 2>/dev/null || true
		echo "Helper stopped."
	else
		echo "Helper not running."
	fi
}

# For slugs following the issue-<id>-<name> convention, resolve the local
# Obsidian issue file. Echoes an empty string when the slug has no id,
# SH_NOTES_DIR is unset, or no issue file matches.
issue_file_for() {
	local slug="$1"

	[[ "$slug" =~ ^issue-([0-9]+)- ]] || return 0

	[ -n "${SH_NOTES_DIR:-}" ] || return 0

	# `|| true` because a missing issue is normal, not an error: the issue may
	# have been archived while its worktree is still up. Without it, `ls`
	# failing propagates through pipefail and set -e aborts the whole command
	# — which made `up` die silently for any slug whose issue had been filed away.
	ls "$SH_NOTES_DIR/Simple History/issues/${BASH_REMATCH[1]} - "*.md 2>/dev/null | head -1 || true
}

# obsidian:// deep link to the issue document, for the dev toolbar.
issue_url_for() {
	local slug="$1" vault="${SH_OBSIDIAN_VAULT:-nvALT}"

	local file
	file="$(issue_file_for "$slug")"

	[ -n "$file" ] || return 0

	# Vault-relative path, without the .md extension.
	local rel="${file#"$SH_NOTES_DIR"/}"
	rel="${rel%.md}"

	local encoded
	encoded="$(jq -rn --arg v "$rel" '$v | @uri')"

	echo "obsidian://open?vault=$vault&file=$encoded"
}

# Record (or clear) which worktree serves an issue in the issue's
# frontmatter, so issue overviews can link straight to the instance.
# Best-effort: silently skipped when the obsidian CLI is unavailable.
issue_set_worktree() {
	local slug="$1" vault="${SH_OBSIDIAN_VAULT:-nvALT}"

	command -v obsidian >/dev/null 2>&1 || return 0

	local file
	file="$(issue_file_for "$slug")"

	[ -n "$file" ] || return 0

	obsidian property:set vault="$vault" name=worktree value="$slug" path="${file#"$SH_NOTES_DIR"/}" >/dev/null 2>&1 || true
}

issue_clear_worktree() {
	local slug="$1" vault="${SH_OBSIDIAN_VAULT:-nvALT}"

	command -v obsidian >/dev/null 2>&1 || return 0

	local file
	file="$(issue_file_for "$slug")"

	[ -n "$file" ] || return 0

	obsidian property:remove vault="$vault" name=worktree path="${file#"$SH_NOTES_DIR"/}" >/dev/null 2>&1 || true
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

	# Already running? Require BOTH a live pid and a listener on the recorded
	# port — a recycled pid alone must not count (stale state after a crash).
	local existing_pid existing_port
	existing_pid="$(state_get "$dir" pid)"
	existing_port="$(state_get "$dir" port)"

	if pid_alive "$existing_pid" && [ -n "$existing_port" ] \
		&& lsof -ti tcp:"$existing_port" -sTCP:LISTEN >/dev/null 2>&1; then
		echo "Already running: $(state_get "$dir" url) ($slug)"
		return 0
	fi

	# Anything left in the state file at this point is stale.
	rm -f "$dir/.playground.json"

	echo "==> [$slug] installing dependencies and building assets"
	(
		cd "$dir"
		# npm ci: exact install from the lockfile, never modifies it
		# (npm install can rewrite package-lock.json and dirty the worktree).
		[ -d node_modules ] || npm ci

		# Skip the build when no source file changed since the last one.
		if [ ! -f build/index.asset.php ] \
			|| [ -n "$(find src -newer build/index.asset.php -print -quit 2>/dev/null)" ]; then
			npm run build
		else
			echo "Build up to date — skipping npm run build"
		fi
	)

	# PHP tooling: phpstan/phpcs need vendor/ and the gitignored test plugin
	# stubs, neither of which exists in a fresh worktree. Symlink them from
	# the main checkout (both are gitignored, so the worktree stays clean).
	[ -e "$dir/vendor" ] || ln -s "$MAIN_REPO/vendor" "$dir/vendor"

	if [ -d "$MAIN_REPO/tests/plugins" ]; then
		mkdir -p "$dir/tests/plugins"

		local stub stub_name
		for stub in "$MAIN_REPO/tests/plugins"/*/; do
			[ -d "$stub" ] || continue

			stub="${stub%/}"
			stub_name="$(basename "$stub")"
			[ -e "$dir/tests/plugins/$stub_name" ] || ln -s "$stub" "$dir/tests/plugins/$stub_name"
		done
	fi

	local port site_url
	port="$(find_free_port)"
	site_url="$(site_url_for "$slug" "$port")"

	# Generate the per-instance blueprint with the slug as site title.
	# sed writes to a temp file first: redirecting straight onto $blueprint
	# truncates the input when --blueprint points at the generated file itself.
	local blueprint="$dir/.playground-blueprint.json"
	sed "s/WORKTREE_NAME/$slug/" "${blueprint_override:-$BLUEPRINT_TEMPLATE}" > "$blueprint.tmp" \
		&& mv "$blueprint.tmp" "$blueprint"

	# Strip any steps a previous run injected (supports --blueprint pointing
	# at the generated file itself) — otherwise the appends below accumulate
	# on every restart, and stale copies carry old ports/tokens/passwords.
	jq '.steps |= map(select(
			((.step == "defineWpConfigConsts") and ((.consts // {}) | has("SH_DEV_WORKTREE_PATH")))
			or ((.step == "runPHP") and ((.code // "") | contains("sh-parallel-dev-provision")))
			or ((.step == "activatePlugin") and (.pluginPath == "simple-history-premium/simple-history-premium.php"))
			| not))' \
		"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"

	if [ -n "$premium" ]; then
		jq '.steps += [{"step": "activatePlugin", "pluginPath": "simple-history-premium/simple-history-premium.php"}]' \
			"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"
	fi

	# One consts step for everything the instance needs:
	# - debug logging on (matches the premium repo's playground defaults)
	# - WP_HOME/WP_SITEURL pinned to the named URL (avoids 127.0.0.1
	#   redirects and Site Editor CORS) — only when .test resolution exists
	# - dev toolbar metadata (read by the mounted mu-plugin)
	local named=false
	test_domains_available && named=true

	local issue_url
	issue_url="$(issue_url_for "$slug")"

	# Link the issue to this worktree in its frontmatter (overview shows it).
	issue_set_worktree "$slug"

	# Default the environment type to 'local' — application passwords are
	# unavailable over plain HTTP otherwise. Respect a blueprint that sets
	# its own WP_ENVIRONMENT_TYPE (e.g. to reproduce production-only
	# behavior); note environment-gated code behaves differently than on a
	# default ('production') site either way.
	local set_env_type=true

	if [ "$(jq '[.steps[]? | select(.step == "defineWpConfigConsts") | ((.consts // {}) | has("WP_ENVIRONMENT_TYPE"))] | any' "$blueprint")" = "true" ]; then
		set_env_type=false
		echo "Note: blueprint defines WP_ENVIRONMENT_TYPE — keeping it. Basic-auth REST access needs 'local' over plain HTTP."
	fi

	# The branch has to be passed in: a worktree's .git is a file pointing at
	# an absolute path on this machine, which the container serving the site
	# cannot follow, so the toolbar cannot read it for itself.
	local branch_name
	branch_name="$(git -C "$dir" rev-parse --abbrev-ref HEAD 2>/dev/null || echo '')"

	local extra_consts
	extra_consts="$(jq -n \
		--arg url "$site_url" \
		--arg path "$dir" \
		--arg branch "$branch_name" \
		--argjson hport "$HELPER_PORT" \
		--arg token "$(helper_token)" \
		--arg issue "$issue_url" \
		--argjson named "$named" \
		--argjson set_env "$set_env_type" \
		--arg app_user "$APP_PASSWORD_USER" \
		--arg app_password "$APP_PASSWORD" \
		'{WP_DEBUG: true, WP_DEBUG_LOG: true, WP_DEBUG_DISPLAY: false,
		  SH_DEV_WORKTREE_PATH: $path, SH_DEV_HELPER_PORT: $hport, SH_DEV_HELPER_TOKEN: $token,
		  SH_DEV_APP_USER: $app_user, SH_DEV_APP_PASSWORD: $app_password}
		 + (if $set_env then {WP_ENVIRONMENT_TYPE: "local"} else {} end)
		 + (if $named then {WP_HOME: $url, WP_SITEURL: $url} else {} end)
		 + (if $issue != "" then {SH_DEV_ISSUE_URL: $issue} else {} end)
		 + (if $branch != "" then {SH_DEV_BRANCH: $branch} else {} end)')"

	jq --argjson consts "$extra_consts" \
		'.steps += [{"step": "defineWpConfigConsts", "consts": $consts}]' \
		"$blueprint" > "$blueprint.tmp" && mv "$blueprint.tmp" "$blueprint"

	# Provision a fixed application password so the REST API accepts Basic
	# auth (curl -u "$APP_PASSWORD_USER:$APP_PASSWORD"). The step reads the
	# SH_DEV_APP_* constants injected above; see the provisioning file for
	# how the password is created through the core API.
	jq --rawfile code "$PROVISION_PHP_FILE" \
		'.steps += [{"step": "runPHP", "code": $code}]' \
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

	# Record state immediately — if the wait below is interrupted or times
	# out, the running server must still be visible to `status` and `down`.
	jq -n \
		--arg slug "$slug" \
		--arg branch "$branch" \
		--argjson port "$port" \
		--argjson pid "$pid" \
		--arg url "$site_url" \
		--arg premium "$premium" \
		--arg started "$(date '+%Y-%m-%d %H:%M:%S')" \
		--arg app_user "$APP_PASSWORD_USER" \
		--arg app_password "$APP_PASSWORD" \
		'{slug: $slug, branch: $branch, port: $port, pid: $pid, url: $url, premium: $premium, started: $started, app_user: $app_user, app_password: $app_password}' \
		> "$dir/.playground.json"

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

	[ "$ready" = 1 ] || err "Playground did not respond within ${waited}s — see $dir/.playground.log (instance is recorded; stop it with: scripts/parallel-dev.sh down $slug)"

	echo ""
	echo "Ready: $site_url"
	echo "  worktree: $dir"
	echo "  branch:   $branch"
	if [ -n "$premium" ]; then
		echo "  premium:  $premium"
	else
		echo "  premium:  (not mounted)"
	fi
	echo "  log:      $dir/.playground.log"
	echo "  rest:     curl -u '$APP_PASSWORD_USER:$APP_PASSWORD' '$site_url/wp-json/simple-history/v1/events?per_page=5'"
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

		port="" pid="" url="" premium=""
		if [ -f "$dir/.playground.json" ]; then
			IFS=$'\t' read -r port pid url premium <<< "$(jq -r \
				'[.port, .pid, .url, .premium] | map(. // "") | @tsv' \
				"$dir/.playground.json" 2>/dev/null)" || true
		fi

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

	local pid_was_alive=0

	if pid_alive "$pid"; then
		pid_was_alive=1
		echo "==> [$slug] stopping Playground (pid $pid)"
		kill "$pid" 2>/dev/null || true

		# Wait for the process to actually die — a dying server can still
		# flush output into .playground.log, and a file recreated while
		# 'git worktree remove' deletes the directory fails the removal
		# with "Directory not empty".
		local pid_waited=0

		while [ $pid_waited -lt 10 ] && pid_alive "$pid"; do
			sleep 1
			pid_waited=$((pid_waited + 1))
		done

		if pid_alive "$pid"; then
			kill -9 "$pid" 2>/dev/null || true
		fi
	fi

	# Belt and braces: kill remaining LISTENERS on the recorded port — but
	# only when the recorded pid was ours and alive. A dead pid means stale
	# state, and the port may have been reassigned to something else since.
	if [ "$pid_was_alive" = 1 ] && [ -n "$port" ]; then
		local listener
		listener="$(lsof -ti tcp:"$port" -sTCP:LISTEN 2>/dev/null || true)"
		[ -n "$listener" ] && kill $listener 2>/dev/null || true

		# Wait for the port to actually free so an immediate re-up doesn't
		# race the dying process and end up on a different port.
		local waited=0
		while [ $waited -lt 10 ] && lsof -ti tcp:"$port" -sTCP:LISTEN >/dev/null 2>&1; do
			sleep 1
			waited=$((waited + 1))
		done
	fi

	rm -f "$dir/.playground.json"

	if [ "$remove" = 1 ]; then
		if [ -n "$(worktree_dirty_output "$dir")" ]; then
			err "worktree has uncommitted changes (or git failed) — resolve first: $dir"
		fi

		echo "==> [$slug] removing worktree"
		rm -f "$dir/.playground.log" "$dir/.playground-blueprint.json"

		# No --force: after the dirty check and state-file cleanup a clean
		# worktree removes fine, and git's own safety stays as a backstop.
		if ! git -C "$MAIN_REPO" worktree remove "$dir" 2>/dev/null; then
			# A straggling server process can recreate .playground.log
			# mid-delete ("Directory not empty"), sometimes leaving the
			# directory orphaned with its git metadata already gone. The
			# dirty check above passed and commits live on the branch, so
			# nothing is lost: re-clean and retry, then remove by hand.
			sleep 2
			rm -f "$dir/.playground.log" "$dir/.playground-blueprint.json" "$dir/.playground.json"

			if ! git -C "$MAIN_REPO" worktree remove "$dir" 2>/dev/null; then
				rm -rf "$dir"
				git -C "$MAIN_REPO" worktree prune
			fi
		fi

		if [ -d "$dir" ]; then
			err "could not remove $dir — remove it manually, then run: git -C $MAIN_REPO worktree prune"
		fi

		issue_clear_worktree "$slug"
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
