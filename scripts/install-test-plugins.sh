#!/usr/bin/env bash
#
# Install the third-party WordPress plugins that the test suites and phpstan
# need, at pinned versions, into tests/plugins/.
#
# Why this exists
# ---------------
# tests/plugins/ is gitignored, so every directory in it was put there by hand
# on one machine. Two things silently depend on that:
#
#   - tests/wpunit.suite.yml lists six of them under WPLoader `plugins:`, and
#     WPLoader throws "Plugin file X does not exist" if any is missing, so the
#     whole suite dies at bootstrap on a fresh checkout.
#   - phpstan.neon has `scanDirectories: tests/plugins/`, so these plugins are
#     where the function declarations for ACF, limit-login-attempts and Jetpack
#     come from. Without them phpstan reports "Function acf_get_field not
#     found" and friends.
#
# Versions are pinned rather than "latest" on purpose: phpstan analyses this
# code, so a newer Jetpack can change what it reports, and the acceptance
# tests assert on plugin names and version numbers (Akismet 5.5, say). Pinning
# keeps CI and a developer machine looking at identical source.
#
# Pins the tests depend on:
#   - akismet 5.5: SimplePluginLoggerCest asserts plugin_version 5.5.
#   - duplicate-post 4.6: the logger listens for duplicate_post_after_duplicated,
#     which 4.5 and older never fire (PluginDuplicatePostLoggerCest).
#   - redirection 5.10.0: the namespaced REST API the logger was fixed for.
#
# Usage
# -----
#   scripts/install-test-plugins.sh              # into tests/plugins/
#   scripts/install-test-plugins.sh --dest DIR   # somewhere else
#   scripts/install-test-plugins.sh --force      # re-download even if present
#
# Idempotent: a plugin already present at the pinned version is left alone, so
# running this on a machine that already has them does nothing. Bumping a
# version below makes the next run replace just that one.

set -euo pipefail

# slug|version|alternative directory name that also satisfies this entry
#
# The ACF alternative is not cosmetic: only the free build is downloadable, but
# a developer machine may hold Advanced Custom Fields *Pro* instead. Both
# declare the functions phpstan needs, and installing the free copy next to Pro
# would give phpstan two declarations of the same function.
PLUGINS=(
	"advanced-custom-fields|6.1.7|advanced-custom-fields-pro"
	"akismet|5.5|"
	"duplicate-post|4.6|"
	"enable-media-replace|3.6.3|"
	"jetpack|12.2|"
	"limit-login-attempts|1.7.2|"
	"redirection|5.10.0|"
	"user-switching|1.7.0|"
	"wp-crontrol|1.12.1|"
)

DEST="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/tests/plugins"
FORCE=0

while [ $# -gt 0 ]; do
	case "$1" in
		--dest)
			DEST="$2"
			shift 2
			;;
		--force)
			FORCE=1
			shift
			;;
		-h | --help)
			sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
			exit 0
			;;
		*)
			echo "Unknown argument: $1" >&2
			exit 1
			;;
	esac
done

for tool in curl unzip; do
	command -v "$tool" >/dev/null || {
		echo "Required tool not found: $tool" >&2
		exit 1
	}
done

mkdir -p "$DEST"
# One file per plugin recording the version that was installed, so a version
# bump is detected without re-reading the plugin headers (which differ in
# format between plugins).
STAMPS="$DEST/.installed-versions"
mkdir -p "$STAMPS"

installed=0
skipped=0

for entry in "${PLUGINS[@]}"; do
	IFS='|' read -r slug version alt <<<"$entry"

	stamp="$STAMPS/$slug"
	have=""
	[ -f "$stamp" ] && have="$(cat "$stamp")"

	if [ "$FORCE" -eq 0 ]; then
		# Present at the pinned version already.
		if [ -d "$DEST/$slug" ] && [ "$have" = "$version" ]; then
			skipped=$((skipped + 1))
			continue
		fi

		# Present without a stamp: put there by hand before this script
		# existed. Leave it alone rather than overwrite someone's checkout.
		if [ -d "$DEST/$slug" ] && [ -z "$have" ]; then
			echo "  = $slug (already present, unmanaged - leaving as is)"
			skipped=$((skipped + 1))
			continue
		fi

		# An alternative build satisfies this entry (ACF Pro for ACF).
		if [ -n "$alt" ] && [ -d "$DEST/$alt" ]; then
			echo "  = $slug (satisfied by $alt)"
			skipped=$((skipped + 1))
			continue
		fi
	fi

	url="https://downloads.wordpress.org/plugin/${slug}.${version}.zip"
	echo "  + $slug $version"

	tmp="$(mktemp -d)"
	trap 'rm -rf "$tmp"' EXIT

	if ! curl -fsSL "$url" -o "$tmp/plugin.zip"; then
		echo "Failed to download $url" >&2
		exit 1
	fi

	unzip -q "$tmp/plugin.zip" -d "$tmp/unpacked"

	# Unpack to a temporary name and swap, so an interrupted run cannot leave
	# a half-extracted plugin that then looks installed.
	rm -rf "$DEST/$slug"
	mv "$tmp/unpacked/$slug" "$DEST/$slug"
	echo "$version" >"$stamp"

	rm -rf "$tmp"
	trap - EXIT

	installed=$((installed + 1))
done

echo "Test plugins: $installed installed, $skipped already present -> $DEST"
