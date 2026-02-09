#!/bin/bash
# Block git push commands during release skill.
# Release pushes must be done manually by the user.

INPUT=$(cat)
COMMAND=$(echo "$INPUT" | jq -r '.tool_input.command // empty')

if [[ "$COMMAND" == *"git push"* ]]; then
  echo "Blocked: git push must be done manually by the user." >&2
  exit 2
fi

exit 0
