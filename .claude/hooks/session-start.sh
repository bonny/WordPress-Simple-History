#!/bin/bash
# SessionStart hook - injects environment context into Claude's session

if [ "$DEVCONTAINER" = "true" ]; then
    echo "Running in DevContainer environment."
    echo "- Docker-in-Docker is NOT available"
    echo "- Use host.docker.internal instead of .test domains for API access"
    echo "- WP-CLI commands via docker compose are not available"
fi

# Link the maintainer's private skills from the Obsidian vault on any machine
# where they are missing, so a skill added to scripts/setup-private-skills.sh
# shows up everywhere without anyone remembering to run it. Silent for
# contributors without the vault, and silent when every link already exists.
PROJECT_DIR="${CLAUDE_PROJECT_DIR:-$(pwd)}"
SETUP_SCRIPT="$PROJECT_DIR/scripts/setup-private-skills.sh"

if [ -z "$SH_PRIVATE_SKILLS_DIR" ] && [ -n "$SH_NOTES_DIR" ]; then
    SH_PRIVATE_SKILLS_DIR="$SH_NOTES_DIR/Simple History/claude-skills"
fi

if [ -n "$SH_PRIVATE_SKILLS_DIR" ] && [ -d "$SH_PRIVATE_SKILLS_DIR" ] && [ -f "$SETUP_SCRIPT" ]; then
    # The skill names, read from the SKILLS=( ... ) list in the setup script.
    skills=$(sed -n '/^SKILLS=(/,/^)/p' "$SETUP_SCRIPT" | sed '1d;$d' | tr -d ' \t')
    missing_links=""

    for skill in $skills; do
        if [ -d "$SH_PRIVATE_SKILLS_DIR/$skill" ] && [ ! -e "$PROJECT_DIR/.claude/skills/$skill" ]; then
            missing_links="$missing_links$skill "
        fi
    done

    if [ -n "$missing_links" ]; then
        SH_PRIVATE_SKILLS_DIR="$SH_PRIVATE_SKILLS_DIR" bash "$SETUP_SCRIPT" >/dev/null 2>&1
        echo "Linked private skills from the vault: $missing_links(a newly linked skill may only load in the next session)."
    fi
fi

exit 0
