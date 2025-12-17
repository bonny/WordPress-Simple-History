#!/bin/bash
# Fix ownership of .claude directory (volume may be owned by root)
sudo chown -R $(id -u):$(id -g) /home/node/.claude 2>/dev/null || true

# Execute the passed command
exec "$@"
