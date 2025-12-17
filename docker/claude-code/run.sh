#!/bin/bash
# Run Claude Code in Docker with --dangerously-skip-permissions

set -e
cd "$(dirname "$0")"

# Check for .env file
if [ ! -f .env ]; then
    echo "Error: .env file not found. Copy .env.example to .env and add your API key."
    exit 1
fi

# Build the image
docker compose build

# Run Claude Code interactively (using run instead of up + exec)
docker compose run --rm claude-code claude --dangerously-skip-permissions "$@"
