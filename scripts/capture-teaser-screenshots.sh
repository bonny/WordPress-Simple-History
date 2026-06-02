#!/usr/bin/env bash
# Capture four user-card screenshots: { premium, free } × { closeup, context }.
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
# user exists on stable.

set -euo pipefail

DOCKER_DIR="/Users/bonny/Projects/_docker-compose-to-run-on-system-boot"
SPEC="tests/playwright/screenshot-teaser-user-card.spec.js"
AUTH_FILE="tests/playwright/.auth/admin.json"
AUTH_BACKUP="${AUTH_FILE}.bak"
PLAYWRIGHT_BIN="$(npm bin 2>/dev/null || echo node_modules/.bin)/playwright"

if [[ ! -x "${PLAYWRIGHT_BIN}" ]]; then
	PLAYWRIGHT_BIN="npx playwright"
fi

wp_cli() {
	# Quietly run a wp-cli command in the wpcli_mariadb service.
	( cd "${DOCKER_DIR}" \
		&& docker compose run --rm wpcli_mariadb "$@" >/dev/null 2>&1 ) \
		|| true
}

cleanup() {
	echo "→ Restoring premium-active state…"
	wp_cli plugin activate simple-history-premium
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
wp_cli plugin activate simple-history-premium

echo "→ Capturing premium screenshots (logged in as sally)…"
SH_TEASER_MODE=premium ${PLAYWRIGHT_BIN} test "${SPEC}" --project=teaser

echo "→ Deactivating premium…"
wp_cli plugin deactivate simple-history-premium

echo "→ Capturing free-version screenshots (logged in as sally)…"
SH_TEASER_MODE=free ${PLAYWRIGHT_BIN} test "${SPEC}" --project=teaser

PNGS=(
	"assets/images/user-card-with-premium.png"
	"assets/images/user-card-with-premium-context.png"
	"assets/images/user-card-without-premium.png"
	"assets/images/user-card-without-premium-context.png"
)

# Optimize the captured PNGs. pngquant is lossy but visually clean for UI
# screenshots and typically halves file size. `--skip-if-larger` keeps the
# original if quantization would somehow grow the file. Skipped silently if
# pngquant isn't installed — the captures still ship, just larger.
if command -v pngquant >/dev/null 2>&1; then
	echo "→ Optimizing PNGs with pngquant…"
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

echo "✓ Done. Four PNGs written to assets/images/:"
for png in "${PNGS[@]}"; do
	echo "    $(basename "${png}")"
done
