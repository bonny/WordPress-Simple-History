#!/usr/bin/env bash
#
# Build the database fixture and download the files the functional and
# acceptance suites need, so a fresh checkout (or a CI runner) can run them.
#
# Why this exists
# ---------------
# Both suites import tests/_data/dump.sql into the test site before every test
# (WPDb `populate` + `cleanup`). The dump is gitignored and used to be made by
# hand from the recipe in tests/readme.md, so every machine had its own copy
# and it drifted: bump WORDPRESS_VERSION in compose.yaml and the dump's
# db_version is stale, WordPress redirects every admin request to upgrade.php,
# and dozens of tests fail with "Form field not found".
#
# Generating the dump from the WordPress that actually runs the tests removes
# that failure mode. The same script serves a developer machine and CI.
#
# The zips are what the tests upload through the admin UI (plugin and theme
# install tests). They are public wordpress.org downloads at pinned versions.
#
# Usage
# -----
#   scripts/build-test-fixture.sh            # zips + dump
#   scripts/build-test-fixture.sh --zips     # only download the zips
#   scripts/build-test-fixture.sh --dump     # only rebuild the dump
#
# Rebuilding the dump RESETS the wp_test_site database in the local docker
# stack and EMPTIES its uploads directory (data/wp-uploads) — that is the
# whole point. An existing tests/_data/dump.sql is backed up next to itself
# as dump-prev-<date>.sql first.
#
# What the dump contains (see tests/readme.md "Required state")
# -------------------------------------------------------------
#   - a fresh install of the WordPress in the `wordpress` service
#   - site url http://wordpress, title wp-tests, admin/admin/test@example.com
#   - no content, no uploads
#   - only simple-history active, on the image's default theme
#   - the auto-backfill marked completed, so it does not run during tests
#   - empty Simple History tables, so event ids start at 1

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DATA="$ROOT/tests/_data"

DO_ZIPS=1
DO_DUMP=1

case "${1:-}" in
	"") ;;
	--zips) DO_DUMP=0 ;;
	--dump) DO_ZIPS=0 ;;
	-h | --help)
		sed -n '2,40p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
		exit 0
		;;
	*)
		echo "Unknown argument: $1" >&2
		exit 1
		;;
esac

# kind|file|url
ZIPS=(
	"theme|twentysixteen.2.6.zip|https://downloads.wordpress.org/theme/twentysixteen.2.6.zip"
	"theme|twentysixteen.2.7.zip|https://downloads.wordpress.org/theme/twentysixteen.2.7.zip"
	"plugin|limit-login-attempts-reloaded.2.25.5.zip|https://downloads.wordpress.org/plugin/limit-login-attempts-reloaded.2.25.5.zip"
	"plugin|classic-widgets.0.3.zip|https://downloads.wordpress.org/plugin/classic-widgets.0.3.zip"
)

download_zips() {
	command -v curl >/dev/null || {
		echo "Required tool not found: curl" >&2
		exit 1
	}

	local entry kind file url
	for entry in "${ZIPS[@]}"; do
		IFS='|' read -r kind file url <<<"$entry"

		if [ -f "$DATA/$file" ]; then
			echo "  = $file"
			continue
		fi

		echo "  + $file"
		curl -fsSL "$url" -o "$DATA/$file.part"
		mv "$DATA/$file.part" "$DATA/$file"
	done
}

# All WordPress work goes through the wp-cli service, which shares the
# `wordpress` volume and database with the site the tests drive. -T because
# this may run without a terminal (CI), and stdout must stay clean for the
# export below.
wp() {
	docker compose run --rm -T wp-cli wp "$@"
}

build_dump() {
	cd "$ROOT"

	docker compose up -d --wait db wordpress

	if [ -f "$DATA/dump.sql" ]; then
		local backup="$DATA/dump-prev-$(date +%F).sql"
		cp "$DATA/dump.sql" "$backup"
		echo "Backed up existing dump to ${backup#$ROOT/}"
	fi

	echo "Installing WordPress fresh into wp_test_site"
	wp db reset --yes
	wp core install \
		--url=http://wordpress \
		--title=wp-tests \
		--admin_user=admin \
		--admin_email=test@example.com \
		--admin_password=admin \
		--skip-email

	# Removes the default post, page and comment. Not --uploads: that deletes
	# and recreates the uploads directory in the shared volume, which is the
	# mount point of the ./data/wp-uploads bind mount in the wordpress
	# service, and the mount detaches. Clear the real directory instead.
	wp site empty --yes

	wp plugin deactivate --all
	wp plugin activate simple-history

	# Empty the uploads directory the site serves and make sure Apache
	# (www-data) can write to it. On a fresh checkout Docker creates the
	# bind-mounted directory as root, and the media tests upload files.
	docker compose exec -T -u root wordpress sh -c \
		'find /var/www/html/wp-content/uploads -mindepth 1 -delete && chown -R www-data:www-data /var/www/html/wp-content/uploads'

	# The auto-backfill runs on the first admin_init after activation and
	# would otherwise insert events in the middle of a test run. The welcome
	# notice likewise shows once per fresh install, which with the dump
	# re-imported before every test would mean on every test's first page.
	wp option update simple_history_auto_backfill_status '{"completed":true}' --format=json
	wp option delete simple_history_auto_backfill_pending || true
	wp option update simple_history_welcome_message_seen seen

	# Make the fixture look like a site that has had one admin visit, which
	# is what a hand-made dump always was. A never-visited install does its
	# one-time work (update checks, cron lock, fresh_site) on the first admin
	# request of every test, and that slow first request is where the
	# acceptance suite races itself. The mu-plugin keeps the update checks
	# off the network, so this is quick.
	wp eval 'wp_version_check(); wp_update_plugins(); wp_update_themes();'
	wp option update fresh_site 0
	wp transient delete doing_cron || true

	# Drop the events logged by the steps above (activation, backfill, ...).
	# Tests expect event ids to start at 1.
	wp db query "TRUNCATE TABLE wp_simple_history; TRUNCATE TABLE wp_simple_history_contexts;"

	local tmp="$DATA/dump.sql.part"
	wp db export - >"$tmp"

	# A compose warning or a failed export must not become the fixture.
	if ! grep -q "CREATE TABLE \`wp_options\`" "$tmp"; then
		echo "Export does not look like a WordPress dump, keeping the old one:" >&2
		head -5 "$tmp" >&2
		rm -f "$tmp"
		exit 1
	fi

	mv "$tmp" "$DATA/dump.sql"
	echo "Wrote tests/_data/dump.sql ($(wc -c <"$DATA/dump.sql" | tr -d ' ') bytes, WordPress $(wp core version))"
}

mkdir -p "$DATA"

if [ "$DO_ZIPS" -eq 1 ]; then
	echo "Fixture zips -> tests/_data/"
	download_zips
fi

if [ "$DO_DUMP" -eq 1 ]; then
	build_dump
fi
