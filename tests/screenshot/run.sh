#!/usr/bin/env bash
# Capture wordpress.org marketing assets — screenshot-1.png … -11.png plus
# banner-1544x500.png / -772x250.png — by booting a clean WordPress Playground,
# populating curated events via the blueprint, and running Playwright specs
# against it. Each spec under tests/playwright/screenshot-*.spec.js owns one
# image. The banner spec doesn't need the Playground (it renders a local HTML
# mockup over file://); when only the banner is requested, Playground boot
# is skipped to save ~30s.
#
# Usage:
#   bash tests/screenshot/run.sh                  # capture everything
#   bash tests/screenshot/run.sh banner           # only the banner pair
#   bash tests/screenshot/run.sh banner playground  # specific specs
#   npm run screenshot -- banner                  # same, via npm
#
# Spec names match the bit after `screenshot-` in the file name:
#   playground, banner, inline-diff, user-events, plugin-install,
#   event-details, ip-popover, insights-widget, stats-page,
#   dashboard-widget, email-settings, email-preview

set -euo pipefail

PORT="${SCREENSHOT_PORT:-9445}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$PROJECT_ROOT"

# Requested specs (positional args). Empty array means "all specs".
REQUESTED=( "$@" )

contains() {
	local needle="$1"; shift
	for x in "$@"; do
		[ "$x" = "$needle" ] && return 0
	done
	return 1
}

# Which subsystems do we need? Banner-only runs can skip the Playground.
SKIP_PLAYGROUND=0
if [ "${#REQUESTED[@]}" -gt 0 ]; then
	all_banner=1
	for s in "${REQUESTED[@]}"; do
		if [ "$s" != "banner" ]; then
			all_banner=0
			break
		fi
	done
	if [ "$all_banner" -eq 1 ]; then
		SKIP_PLAYGROUND=1
	fi
fi

if [ "$SKIP_PLAYGROUND" -eq 0 ]; then
	echo "==> Starting playground on port $PORT..."
	npx @wp-playground/cli server \
		--mount=.:/wordpress/wp-content/plugins/simple-history \
		--blueprint=tests/screenshot/blueprint.json \
		--port="$PORT" &
	PLAYGROUND_PID=$!

	cleanup() {
		echo "==> Stopping playground (pid $PLAYGROUND_PID)..."
		kill "$PLAYGROUND_PID" 2>/dev/null || true
		wait "$PLAYGROUND_PID" 2>/dev/null || true
	}
	trap cleanup EXIT INT TERM

	echo "==> Waiting for playground to come up..."
	for i in {1..60}; do
		if curl -sf "http://127.0.0.1:$PORT/wp-login.php" >/dev/null 2>&1; then
			echo "==> Playground ready"
			break
		fi
		if [ "$i" = "60" ]; then
			echo "ERROR: Playground did not become ready in 60s"
			exit 1
		fi
		sleep 1
	done

	# Give the blueprint runPHP a beat to finish populating events.
	sleep 3
else
	echo "==> Banner-only run — skipping Playground boot."
fi

# Build Playwright filter args. Without positional input, Playwright runs
# every spec the `screenshot` project's testMatch regex picks up.
PW_FILTERS=()
if [ "${#REQUESTED[@]}" -gt 0 ]; then
	echo "==> Capturing requested specs: ${REQUESTED[*]}"
	for spec in "${REQUESTED[@]}"; do
		PW_FILTERS+=( "screenshot-${spec}.spec.js" )
	done
else
	echo "==> Capturing all screenshots (12 specs incl. banner)..."
fi

# --workers=1 forces the specs to run sequentially against the single
# playground instance — parallel runs race on the SQLite log and time out.
PLAYWRIGHT_BASE_URL="http://127.0.0.1:$PORT" \
	WP_ADMIN_USER=admin \
	WP_ADMIN_PASSWORD=password \
	npx playwright test --project=screenshot --workers=1 "${PW_FILTERS[@]}"

# Banner downscale + pngquant only when the banner was (re)captured.
banner_was_run=0
if [ "${#REQUESTED[@]}" -eq 0 ] || contains "banner" "${REQUESTED[@]}"; then
	banner_was_run=1
fi

if [ "$banner_was_run" -eq 1 ]; then
	if command -v magick >/dev/null 2>&1; then
		echo "==> Downscaling banner to 772×250..."
		magick .wordpress-org/banner-1544x500.png \
			-filter Lanczos -resize 772x250 \
			.wordpress-org/banner-772x250.png
	else
		echo "==> ImageMagick (magick) not installed — skipping banner downscale (brew install imagemagick)"
	fi
fi

# Optimize whatever was just captured. Build the file list from what's on
# disk so a partial run doesn't try to compress files that didn't exist
# before this invocation.
if command -v pngquant >/dev/null 2>&1; then
	echo "==> Optimizing PNGs with pngquant..."
	PNG_TARGETS=()
	if [ "${#REQUESTED[@]}" -eq 0 ]; then
		PNG_TARGETS=( .wordpress-org/screenshot-*.png .wordpress-org/banner-*.png )
	else
		# Map spec name → output file(s). Associative arrays would be cleaner
		# but require bash 4+ — and macOS still ships bash 3.x by default.
		for spec in "${REQUESTED[@]}"; do
			case "$spec" in
				playground)       PNG_TARGETS+=( .wordpress-org/screenshot-1.png ) ;;
				inline-diff)      PNG_TARGETS+=( .wordpress-org/screenshot-2.png ) ;;
				user-events)      PNG_TARGETS+=( .wordpress-org/screenshot-3.png ) ;;
				plugin-install)   PNG_TARGETS+=( .wordpress-org/screenshot-4.png ) ;;
				ip-popover)       PNG_TARGETS+=( .wordpress-org/screenshot-5.png ) ;;
				event-details)    PNG_TARGETS+=( .wordpress-org/screenshot-6.png ) ;;
				insights-widget)  PNG_TARGETS+=( .wordpress-org/screenshot-7.png ) ;;
				stats-page)       PNG_TARGETS+=( .wordpress-org/screenshot-8.png ) ;;
				dashboard-widget) PNG_TARGETS+=( .wordpress-org/screenshot-9.png ) ;;
				email-settings)   PNG_TARGETS+=( .wordpress-org/screenshot-10.png ) ;;
				email-preview)    PNG_TARGETS+=( .wordpress-org/screenshot-11.png ) ;;
				banner)           PNG_TARGETS+=( .wordpress-org/banner-1544x500.png .wordpress-org/banner-772x250.png ) ;;
			esac
		done
	fi

	# --skip-if-larger keeps the original if compression doesn't help.
	# --quality=80-95 is near-imperceptible on UI screenshots, ~60-80% smaller.
	# Exit 99 means quality dropped below the floor — keep the original.
	# Anything else (real failure: missing file, bad PNG, OOM) is surfaced.
	set +e
	pngquant --skip-if-larger --strip --force --ext .png --quality=80-95 \
		"${PNG_TARGETS[@]}"
	rc=$?
	set -e
	if [ "$rc" -ne 0 ] && [ "$rc" -ne 99 ]; then
		echo "ERROR: pngquant exited with status $rc"
		exit "$rc"
	fi
else
	echo "==> pngquant not installed — skipping optimization (brew install pngquant)"
fi

echo "==> Done. Updated files:"
ls -lh .wordpress-org/screenshot-*.png .wordpress-org/banner-*.png | awk '{ printf "    %s  %s\n", $5, $NF }'
