# WordPress Simple History Plugin

This file provides guidance to AI agents (Claude Code, GitHub Copilot, Cursor, etc.) working with code in this repository.

@.cursor/rules/
@code.md

## Project Overview

**Simple History** is a WordPress plugin that logs user activity and system events.

- **Core Version**: Free, fully-featured version in this repository
- **Premium Version**: Additional plugin with extended features (both must be installed together)
- **Documentation**: See readme.txt for detailed plugin information
- **Philosophy**: Core version must be fully usable for free users with non-intrusive upgrade prompts

## Quick Start

### Installation & Development

```bash
# Install dependencies
composer install && npm install

# Development
npm run start        # Watch mode for development
npm run build        # Production build

# Code Quality
npm run php:lint     # Lint PHP code
npm run php:lint-fix # Auto-fix PHP issues
npm run php:phpstan  # Static analysis
npm run lint:js      # Lint JavaScript
npm run lint:css     # Lint CSS

# Testing
npm run test         # Run all tests
# Or run specific test suites:
docker compose run --rm php-cli vendor/bin/codecept run wpunit:TestName
docker compose run --rm php-cli vendor/bin/codecept run functional:TestName
docker compose run --rm php-cli vendor/bin/codecept run acceptance:TestName
```

### Local Development Environment

See @CLAUDE.local.md for local development setup including Docker configuration, WP-CLI commands, and REST API access.

## Code Standards

### General Principles

- **WordPress Way**: Follow WordPress best practices and conventions
- **Prefixes**: Use `sh`, `simplehistory`, or `simple_history`
- **Text Domain**: `simple-history`
- **Escaping**: Always escape output properly
- **JavaScript**: Follow @wordpress/scripts conventions

### PHP Guidelines

#### Requirements
- PHP 7.4+ compatibility
- WordPress Coding Standards (see phpcs.xml.dist)
- No `mb_*` string functions
- Use short array syntax (`[]` not `array()`)
- WordPress hooks must use prefixes

#### Code Style
- **Happy path last**: Handle errors first, success last
- **Avoid else**: Use early returns
- **Separate conditions**: Multiple if statements over compound conditions
- **Always use curly brackets**: Even for single statements
- **Ternary operators**: Multi-line unless very short

```php
// Happy path last
if (! $user) {
    return null;
}

if (! $user->isActive()) {
    return null;
}

// Process active user...

// Short ternary
$name = $isFoo ? 'foo' : 'bar';

// Multi-line ternary
$result = $object instanceof Model ?
    $object->name :
    'A default value';

// Ternary instead of else
$condition
    ? $this->doSomething()
    : $this->doSomethingElse();
```

### CSS Guidelines

- **Naming Convention**: Suit CSS
- **Prefix**: `sh`
- **Examples**:
  - Components: `sh-HelpSection`, `sh-LogEntry`
  - Subparts: `sh-HelpSection-subpart`, `sh-LogEntry-author`

### Logger Messages

Write messages in **active tone** as if someone is telling you what happened:
- ✅ "Activated plugin"
- ✅ "Created menu"
- ✅ "Detected modifications"
- ❌ "Plugin was activated"
- ❌ "Menu has been created"

Messages should be easily understood by regular users, not just developers.

## Project Management

### GitHub Project Board

**Project**: Simple History Kanban
**URL**: https://github.com/users/bonny/projects/4/views/1

#### Board Columns
- **Backlog**: Items for future consideration
- **To Do**: Next items to work on
- **In Progress**: Currently being worked on
- **Done**: Completed items

#### GitHub CLI Commands

```bash
# List open issues
gh issue list --state open

# View specific issue
gh issue view NUMBER

# Access project board (requires read:project scope)
gh api graphql -f query='
  query {
    user(login: "bonny") {
      projectV2(number: 4) {
        title
        items(first: 50) {
          nodes {
            content {
              ... on Issue {
                title
                number
                state
              }
            }
          }
        }
      }
    }
  }
'
```

### Git Workflow

- Create a new branch for each GitHub issue or feature
- Branch naming: `issue-NUMBER-brief-description`
- Follow OneFlow model (see code.md for details)
- Use GitHub CLI to fetch GitHub issues
- When working with branches a readme file is created for most branches, called `readme.<branch-or-issue>.md`. See and use that file for findings, progress, and todos.
