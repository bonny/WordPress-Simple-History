#!/bin/bash
# SessionStart hook - injects environment context into Claude's session

if [ "$DEVCONTAINER" = "true" ]; then
    echo "Running in DevContainer environment."
    echo "- Docker-in-Docker is NOT available"
    echo "- Use host.docker.internal instead of .test domains for API access"
    echo "- WP-CLI commands via docker compose are not available"
fi
