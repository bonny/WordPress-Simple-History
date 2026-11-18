# Folder Structure Overview

What each top-level folder in the repository is for. Generated and installed folders (`vendor/`, `node_modules/`, `build/`) are not in git.

## Plugin code

| Folder | Purpose |
| ------ | ------- |
| `inc/` | Core PHP classes: `Simple_History`, `Log_Query`, REST controllers, helpers. Subfolders: `services/` (one class per feature, including `class-setup-database.php`), `event-details/` (Event Details API), `channels/` (log forwarding, such as the file channel), `deprecated/` (old class names kept for backwards compatibility). |
| `loggers/` | One logger class per area that gets logged (posts, users, plugins, options, and some third-party plugins). `class-logger.php` is the base class. |
| `dropins/` | Optional features built as drop-ins (RSS feed, export, sidebar boxes, IP info, reactions and more). `class-dropin.php` is the base class. |
| `templates/` | PHP templates for the settings pages and the email summary report (HTML and text versions). |
| `src/` | Source for the React admin UI (event log, admin bar, command palette). Compiled into `build/` with `npm run build`. |
| `js/` | Plain JavaScript loaded without a build step: `scripts.js`, the stats and insights pages, notices, and a bundled Chart.js. |
| `css/` | Stylesheets and the SVG/PNG icons they reference. |
| `assets/` | Images used inside the plugin, such as premium teasers. |
| `vendor-prefixed/` | Third-party PHP libraries (php-diff and its dependencies) copied in with Strauss under the plugin's own namespace, so they do not clash with other plugins. |

## Tests

| Folder | Purpose |
| ------ | ------- |
| `tests/` | Codeception suites `wpunit/`, `functional/` and `acceptance/`, run in Docker through `npm run test:*`. `playwright/` holds the Playwright browser tests and the screenshot specs. `screenshot/` has the fixtures for the WordPress.org screenshots. See `tests/readme.md`. |
| `docker/` | Database init script for the test database, and a container setup for running Claude Code. |

## Tooling and project files

| Folder | Purpose |
| ------ | ------- |
| `docs/` | Developer documentation (this folder). |
| `scripts/` | Shell and helper scripts: parallel dev sites, test fixture and test plugin setup, test zip, benchmarks, teaser screenshots. |
| `phpcs-rules/` | Custom PHP_CodeSniffer sniff used by `phpcs.xml.dist`. |
| `examples/` | Only a readme pointing to https://simple-history.com/docs/. |
| `.github/` | GitHub Actions workflows (tests, lint, spelling, WordPress.org deploy, readme assets) and the issue template. |
| `.wordpress-org/` | Banners, icons, screenshots and the Playground blueprint for the WordPress.org plugin page. |
| `.claude/`, `.agents/` | Skills, agents, hooks and settings for AI coding agents. |

## Files in the root

- `index.php`: main plugin file (plugin header, version constant, bootstrap).
- `uninstall.php`: removes tables and options when the plugin is deleted.
- `readme.txt`: WordPress.org readme, including the changelog. `CHANGELOG.md` is a changelog file too, with release summaries and links to release posts.
- `composer.json`, `package.json`: PHP and npm dependencies and scripts.
- `codeception.dist.yml`, `phpunit.xml`, `playwright.config.js`, `compose.yaml`, `.env.testing`: test configuration.
- `phpcs.xml.dist`, `phpstan.neon`, `rector.php`, `.eslintrc.js`, `.typos.toml`, `.editorconfig`: linting and static analysis.
- `gruntfile.js`: Grunt tasks for bumping the version number in `index.php`, `readme.txt` and `package.json`.
- `.distignore`: files left out of the release zip.
- `AGENTS.md`, `CLAUDE.md`, `code.md`: guidance for AI agents and the code standard.
