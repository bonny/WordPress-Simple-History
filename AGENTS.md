# WordPress Simple History Plugin

This file provides guidance to AI agents (Claude Code, GitHub Copilot, Cursor, etc.) working with code in this repository.

@code.md

## Project Overview

**Simple History** is a WordPress plugin that logs user activity and system events.

-   **Core Version**: Free, fully-featured version in this repository
-   **Premium Version**: Additional plugin with extended features (both must be installed together).
-   **Payment Processor**: Premium is sold through **Lemon Squeezy** (merchant of record). See the **lemonsqueezy-sales** skill for billing, tax/VAT, and refund specifics.
-   **Documentation**: See readme.txt for detailed plugin information
-   **Upsell Philosophy**: Core version must be fully usable for free users with non-intrusive upgrade prompts. However, the premium version should be a "must-have" for most users. Convince users to upgrade to the premium version by "nudging" them discreetly in different places throughout the plugin. But don't be too pushy, don't annoy users! Win over users in the long run and make them happy to use the premium version.

See the **wordpress-org-compliance** skill for detailed guidelines on implementing this approach while maintaining WordPress.org compliance.

### Non-Obvious Technical Constraints

-   PHP 7.4+ compatibility required
-   Supports both MySQL/MariaDB and SQLite databases
-   Use `Log_Query::get_db_engine()` to check database type (`'mysql'` or `'sqlite'`)
-   Avoid MySQL-specific SQL (e.g., `OPTIMIZE TABLE`, `SHOW TABLE STATUS`) without a database type guard
-   Some vendor packages are patched via `cweagans/composer-patches`. Patches live in `patches/` and are auto-applied on `composer install`. See [patches/README.md](patches/README.md) before editing anything under `vendor/`.

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

### Private Skills

Some skills live in the maintainer's Obsidian vault rather than in this repo. Run `scripts/setup-private-skills.sh` to wire them up; see the comments at the top of that script for setup details. Contributors without the vault can ignore this.

### GitHub Project Board

Use the **github-project** skill for project board automation, IDs, and GraphQL queries.

### Git Workflow

-   Create a new branch for each GitHub issue or feature
-   Branch naming: `issue-NUMBER-brief-description`
-   Follow OneFlow model (see code.md for details)
-   Issues are tracked locally in Obsidian (use the `local-issues` skill), not on GitHub
-   When working with branches a readme file is created for most branches, called `readme.<branch-or-issue>.md`. See and use that file for findings, progress, and todos. Never add any sensitive information to this document, like API keys or passwords, since this document will be commited to GIT and can be shown on GitHub.
-   Don't add to git or commit without user explicitly saying so
-   Never add auth tokens or api keys to code or documents in /docs folder
