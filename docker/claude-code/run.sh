#!/bin/bash
# Run Claude Code in Docker with --dangerously-skip-permissions
# Uses host's ~/.claude config for authentication and settings

set -e
cd "$(dirname "$0")"

# Build the image
docker compose build

# Run Claude Code interactively (using run instead of up + exec)
docker compose run --rm claude-code claude --dangerously-skip-permissions "$@"
