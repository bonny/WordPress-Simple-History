#!/usr/bin/env bash
# Capture user-card screenshots: { premium, free } × { closeup, context },
# plus the premium details crop embedded in the free teaser.
#
# Drives the Playwright spec twice with SH_TEASER_MODE, toggling the premium
# plugin via WP-CLI between runs. Logs in as "sally" so the captured cards
# don't show a real maintainer's avatar/email — sally is a stock admin user
# kept on the stable install for marketing screenshots.
#
# On exit (trap): re-activates premium AND restores the previous Playwright
# admin auth cache so other Playwright tests keep using their normal admin
# session.
#
#   npm run screenshots:teaser-user-card
#
# Pre-flight: stable docker WP is running, premium plugin is installed
# (active or inactive — script handles both), and the sally/sally admin
# user exists on stable with at least one browser login (the premium card
# needs an IP + browser session to be captured). The script checks the user
# and stops with setup instructions if it is missing.
#
# The docker stack is looked up next to this repo (or one level further up,
# which is the laptop layout). Set SH_DOCKER_DIR to override.

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SPEC="tests/playwright/screenshot-teaser-user-card.spec.js"
AUTH_FILE="tests/playwright/.auth/admin.json"
AUTH_BACKUP="${AUTH_FILE}.bak"
PLAYWRIGHT_BIN="node_modules/.bin/playwright"

if [[ ! -x "${PLAYWRIGHT_BIN}" ]]; then
	PLAYWRIGHT_BIN="npx playwright"
fi

DOCKER_DIR="${SH_DOCKER_DIR:-}"

if [[ -z "${DOCKER_DIR}" ]]; then
	for candidate in \
		"${REPO_DIR}/../_docker-compose-to-run-on-system-boot" \
		"${REPO_DIR}/../../_docker-compose-to-run-on-system-boot"; do
		if [[ -f "${candidate}/compose.yaml" || -f "${candidate}/docker-compose.yml" || -f "${candidate}/compose.yml" ]]; then
			DOCKER_DIR="$(cd "${candidate}" && pwd)"
			break
		fi
	done
fi

if [[ -z "${DOCKER_DIR}" || ! -d "${DOCKER_DIR}" ]]; then
	echo "✗ Could not find the docker stack. Set SH_DOCKER_DIR to the _docker-compose-to-run-on-system-boot directory." >&2
	exit 1
fi

wp_cli() {
	# Run a wp-cli command in the wpcli_mariadb service. Plugins and themes
	# are skipped: a third-party plugin fatal (Rank Math on the stable site)
	# must not break a plugin toggle. Output is shown only on failure, and a
	# failure stops the script — a silently failed toggle captures the wrong
	# mode and overwrites the assets with it.
	local output
	if ! output=$( cd "${DOCKER_DIR}" \
		&& docker compose run --rm wpcli_mariadb "$@" --skip-plugins --skip-themes 2>&1 ); then
		echo "✗ wp $*" >&2
		echo "${output}" >&2
		return 1
	fi
	printf '%s' "${output}"
}

if ! wp_cli user get sally --field=ID >/dev/null 2>&1; then
	cat >&2 <<'MSG'
✗ The "sally" admin user does not exist on the stable site.

Create it, then log in once as sally/sally in a browser so the card has an
IP + browser session to show:

  cd _docker-compose-to-run-on-system-boot
  docker compose run --rm wpcli_mariadb user create sally sally@example.com \
    --role=administrator --user_pass=sally --display_name="Sally Andersson" \
    --skip-plugins --skip-themes
MSG
	exit 1
fi

cleanup() {
	echo "→ Restoring premium-active state…"
	wp_cli plugin activate simple-history-premium >/dev/null \
		|| echo "  ⚠ Could not re-activate premium — activate it manually." >&2
	if [[ -f "${AUTH_BACKUP}" ]]; then
		echo "→ Restoring previous Playwright admin auth cache…"
		mv -f "${AUTH_BACKUP}" "${AUTH_FILE}"
	else
		# No backup to restore — drop the sally session so the next normal
		# Playwright run re-authenticates as its configured admin instead of
		# silently running as sally.
		rm -f "${AUTH_FILE}"
	fi
}
trap cleanup EXIT

# Park the existing admin auth (likely claude) and swap in sally for this run.
if [[ -f "${AUTH_FILE}" ]]; then
	mv "${AUTH_FILE}" "${AUTH_BACKUP}"
fi

export WP_ADMIN_USER="sally"
export WP_ADMIN_PASSWORD="sally"

echo "→ Activating premium…"
wp_cli plugin activate simple-history-premium >/dev/null

echo "→ Capturing premium screenshots (logged in as sally)…"
SH_TEASER_MODE=premium ${PLAYWRIGHT_BIN} test "${SPEC}" --project=teaser

echo "→ Deactivating premium…"
wp_cli plugin deactivate simple-history-premium >/dev/null

echo "→ Capturing free-version screenshots (logged in as sally)…"
SH_TEASER_MODE=free ${PLAYWRIGHT_BIN} test "${SPEC}" --project=teaser

PNGS=(
	"assets/images/user-card-with-premium.png"
	"assets/images/user-card-premium-details.png"
	"assets/images/user-card-with-premium-context.png"
	"assets/images/user-card-without-premium.png"
	"assets/images/user-card-without-premium-context.png"
)

# Optimize the captured PNG files. pngquant is lossy but visually clean for UI
# screenshots and typically halves file size. `--skip-if-larger` keeps the
# original if quantization would somehow grow the file. Skipped silently if
# pngquant isn't installed — the captures still ship, just larger.
if command -v pngquant >/dev/null 2>&1; then
	echo "→ Optimizing PNG files with pngquant…"
	for png in "${PNGS[@]}"; do
		before=$(stat -f%z "${png}" 2>/dev/null || stat -c%s "${png}")
		pngquant --quality=80-95 --strip --skip-if-larger --force \
			--ext .png "${png}" 2>/dev/null || true
		after=$(stat -f%z "${png}" 2>/dev/null || stat -c%s "${png}")
		saved=$(( ( before - after ) * 100 / before ))
		printf "    %s  %d → %d bytes (-%d%%)\n" \
			"$(basename "${png}")" "${before}" "${after}" "${saved}"
	done
else
	echo "→ pngquant not found — skipping image optimization."
	echo "    Install: brew install pngquant"
fi

echo "✓ Done. Five PNG files written to assets/images/:"
for png in "${PNGS[@]}"; do
	echo "    $(basename "${png}")"
done
