# Branch: local-isues-fixes

Miscellaneous fixes and improvements collected from local issues.

## Changes

- Fix phpstan level 3 errors: update return type annotations and trivial code fixes
- Fix phpcs warnings: init undefined var, suppress intentional patterns
- Fix phpcs errors: unused variable, count in loop, multi-line ignores
- Add premium-version-update and premium-translate skills
- Fix welcome message on dashboard to be self-contained (no "Here's what to expect" teaser that only shows on the dedicated page)

## Files changed

- `inc/services/class-setup-database.php` — Welcome message text fix
- `inc/class-event.php`, `inc/class-events-stats.php`, `inc/class-helpers.php`, `inc/class-log-initiators.php`, `inc/class-menu-manager.php`, `inc/class-simple-history.php` — phpstan/phpcs fixes
- `inc/class-wp-rest-events-controller.php`, `inc/class-wp-rest-searchoptions-controller.php` — phpstan/phpcs fixes
- `loggers/class-logger.php`, `loggers/class-plugin-logger.php`, and other loggers — phpstan/phpcs fixes
- `.claude/skills/premium-translate/SKILL.md`, `.claude/skills/premium-version-update/SKILL.md` — New skills
