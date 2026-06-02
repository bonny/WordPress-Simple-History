#!/usr/bin/env bash
#
# Symlinks private skills from the Obsidian vault into .claude/skills/.
# Safe to re-run.
#
# Private skills live in the Obsidian vault (not in this repo) because they
# contain personal preferences, business data, or sensitive references.
# The symlinks here let Claude Code load them as if they were local skills.
#
# Required env var:
#   SH_PRIVATE_SKILLS_DIR — absolute path to the folder in the Obsidian vault
#                           that holds the private skill subfolders.
#
# Example:
#   export SH_PRIVATE_SKILLS_DIR="$HOME/Documents/nvALT/Simple History/claude-skills"
#   ./scripts/setup-private-skills.sh
#
# Add the export to your shell profile (.zshrc / .bashrc) to make it permanent.

set -euo pipefail

if [ -z "${SH_PRIVATE_SKILLS_DIR:-}" ]; then
  echo "Error: SH_PRIVATE_SKILLS_DIR is not set." >&2
  echo >&2
  echo "Set it to the absolute path of the claude-skills folder in your Obsidian vault:" >&2
  echo "  export SH_PRIVATE_SKILLS_DIR=\"\$HOME/Documents/nvALT/Simple History/claude-skills\"" >&2
  echo >&2
  echo "Add the export to your shell profile (.zshrc / .bashrc) to make it permanent." >&2
  echo >&2
  echo "If you're a contributor and not the maintainer, you can ignore this script." >&2
  exit 1
fi

DEST="$SH_PRIVATE_SKILLS_DIR"

if [ ! -d "$DEST" ]; then
  echo "Error: $DEST not found." >&2
  echo "Has Obsidian Sync finished syncing the vault on this machine?" >&2
  exit 1
fi

SKILLS=(
  release
  local-issues
  premium-upsell-design
  lemonsqueezy-sales
  writing-blog-posts
  analytics-traffic
  freemium-conversion
)

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

linked=0
skipped=0
missing=0

for skill in "${SKILLS[@]}"; do
  src="$DEST/$skill"
  target=".claude/skills/$skill"

  if [ ! -e "$src" ]; then
    echo "skip $skill — not found in vault ($src)" >&2
    missing=$((missing + 1))
    continue
  fi

  if [ -e "$target" ] && [ ! -L "$target" ]; then
    echo "skip $skill — $target exists and is not a symlink (won't overwrite)" >&2
    skipped=$((skipped + 1))
    continue
  fi

  ln -sfn "$src" "$target"
  echo "linked $skill"
  linked=$((linked + 1))
done

echo
echo "Done: $linked linked, $skipped skipped, $missing missing."

if [ "$missing" -gt 0 ]; then
  exit 1
fi
