# WordPress Simple History Plugin

This file provides guidance to AI agents (Claude Code, GitHub Copilot, Cursor, etc.) working with code in this repository.

@.cursor/rules/
@code.md

## Project Overview

**Simple History** is a WordPress plugin that logs user activity and system events.

-   **Core Version**: Free, fully-featured version in this repository
-   **Premium Version**: Additional plugin with extended features (both must be installed together).
-   **Documentation**: See readme.txt for detailed plugin information
-   **Upsell Philosophy**: Core version must be fully usable for free users with non-intrusive upgrade prompts. However, the premium version should be a "must-have" for most users. Convince users to upgrade to the premium version by "nudging" them discreetly in different places throughout the plugin. But don't be too pushy, don't annoy users! Win over users in the long run and make them happy to use the premium version.

### Simple History's Freemium Approach

**Free Version** (This Repository):

-   Must be fully functional for all core features
-   No artificial limitations
-   No license key requirements
-   No trial periods or usage limits
-   Can include non-intrusive upgrade prompts
-   Premium feature teasers (clearly marked)

**Premium Version** (Separate Plugin):

-   Extended functionality (more retention, filters)
-   Premium-only integrations
-   Advanced features
-   Priority support

**Philosophy**: "Free is great, Premium is a must-have"

-   Make users **want** to upgrade, not **have** to upgrade
-   Provide real value in premium, not just unlocking free features
-   Be helpful and friendly, not pushy or annoying

See the **wordpress-org-compliance** skill for detailed guidelines on implementing this approach while maintaining WordPress.org compliance.

### Non-Obvious Technical Constraints

-   PHP 7.4+ compatibility required
-   Supports both MySQL/MariaDB and SQLite databases
-   Use `Log_Query::get_db_engine()` to check database type (`'mysql'` or `'sqlite'`)
-   Avoid MySQL-specific SQL (e.g., `OPTIMIZE TABLE`, `SHOW TABLE STATUS`) without a database type guard

## Quick Start

See CLAUDE.local.md for local development setup including Docker configuration, WP-CLI commands, and REST API access.

### Viewing the Event Log

**Preferred Method: WP-CLI**

Use WP-CLI commands to view the event log directly from the command line. This is faster than opening a browser and navigating to the admin interface.

```bash
# View latest events
docker compose run --rm wpcli_mariadb simple-history list

# View available Simple History commands
docker compose run --rm wpcli_mariadb simple-history --help
```

See @CLAUDE.local.md for specific commands for stable and nightly WordPress installations.

## Code Standards

### Quick Reference

-   **WordPress Way**: Follow WordPress best practices and conventions
-   **Prefixes**: Use `sh`, `simplehistory`, or `simple_history`
-   **Text Domain**: `simple-history`
-   **PHP**: 7.4+ compatibility, WordPress Coding Standards
-   **Escaping**: Always escape output properly
-   **JavaScript**: Follow @wordpress/scripts conventions

## Project Management

### GitHub Project Board

Use the **github-project** skill for project board automation, IDs, and GraphQL queries.

### Git Workflow

-   Create a new branch for each GitHub issue or feature
-   Branch naming: `issue-NUMBER-brief-description`
-   Follow OneFlow model (see code.md for details)
-   Use GitHub CLI to fetch GitHub issues
-   When working with branches a readme file is created for most branches, called `readme.<branch-or-issue>.md`. See and use that file for findings, progress, and todos. Never add any sensitive information to this document, like API keys or passwords, since this document will be commited to GIT and can be shown on GitHub.
-   Don't add to git or commit without user explicitly saying so
-   Never add auth tokens or api keys to code or documents in /docs folder
