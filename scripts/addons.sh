#!/usr/bin/env bash
#
# Run the add-ons repo's npm scripts without leaving the core folder.
#
# The premium add-on lives in a separate repository, but day-to-day work happens
# with the terminal in core. That meant premium's checks — including the release
# ones that catch premium calling core APIs that are too new — sat in a
# directory nobody was standing in. These delegate instead.
#
# Usage:
#   ./scripts/addons.sh <npm-script> [args...]
#   npm run addons:lint
#   npm run addons:phpstan
#   npm run addons:check
#
# SH_ADDONS_PATH — add-ons checkout. Defaults to the sibling
#                  ../simple-history-add-ons.
#
# The core checkout this script was invoked from is passed through as
# SH_CORE_PATH, so running from a core git worktree analyses the add-ons against
# *that* worktree rather than the main checkout. An existing SH_CORE_PATH wins.

set -euo pipefail

core_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Resolve the sibling default from the MAIN core checkout, not from wherever
# this copy of the script happens to live. A git worktree sits somewhere else
# entirely and has no add-ons repo beside it, so a plain ../ default breaks the
# exact case this script exists to support. The worktree is still what gets
# analysed — that goes through SH_CORE_PATH below, not through this lookup.
main_core="$core_root"
if git -C "$core_root" rev-parse --git-dir >/dev/null 2>&1; then
	main_core="$(git -C "$core_root" worktree list --porcelain | awk '/^worktree /{print $2; exit}')"
fi

addons_input="${SH_ADDONS_PATH:-$main_core/../simple-history-add-ons}"

if [ "$#" -eq 0 ]; then
	echo "usage: ./scripts/addons.sh <npm-script> [args...]" >&2
	echo "       e.g. ./scripts/addons.sh php:phpstan" >&2
	exit 1
fi

if [ ! -d "$addons_input" ]; then
	echo "error: add-ons repository not found at $addons_input" >&2
	echo "" >&2
	echo "The add-ons repo is private and is not required to work on core." >&2
	echo "If you have it, set SH_ADDONS_PATH to its location." >&2
	exit 1
fi

addons_root="$(cd "$addons_input" && pwd)"

if [ ! -f "$addons_root/package.json" ]; then
	echo "error: no package.json in $addons_root — is that the add-ons repo?" >&2
	exit 1
fi

cd "$addons_root"
SH_CORE_PATH="${SH_CORE_PATH:-$core_root}" exec npm run "$@"
