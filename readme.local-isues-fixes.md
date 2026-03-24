# Branch: local-isues-fixes

Miscellaneous fixes and improvements collected from local issues.

## Changes

- Fix phpstan level 3 errors: update return type annotations and trivial code fixes
- Fix phpcs warnings: init undefined var, suppress intentional patterns
- Fix phpcs errors: unused variable, count in loop, multi-line ignores
- Add premium-version-update and premium-translate skills
- Fix welcome message on dashboard to be self-contained (no "Here's what to expect" teaser that only shows on the dedicated page)
- Improve dashboard widget UX: clarify stats copy ("events logged today"), update footer link ("View full activity log"), and reorder footer (action link before tip)
- Improve History Insights sidebar chart UX: mark today with visible dot, add "(today)" to end date label, reduce y-axis clutter, add accessible aria-label with avg/peak summary
- Improve History Insights sidebar footer: separate database stats with top border, move cache freshness info into tooltip
- Add "/" keyboard shortcut to focus search input, Escape to exit, with visual kbd hint

## Files changed

- `src/components/DashboardEventsWidget.jsx` — Dashboard widget copy and layout improvements
- `inc/services/class-setup-database.php` — Welcome message text fix
- `inc/class-event.php`, `inc/class-events-stats.php`, `inc/class-helpers.php`, `inc/class-log-initiators.php`, `inc/class-menu-manager.php`, `inc/class-simple-history.php` — phpstan/phpcs fixes
- `inc/class-wp-rest-events-controller.php`, `inc/class-wp-rest-searchoptions-controller.php` — phpstan/phpcs fixes
- `loggers/class-logger.php`, `loggers/class-plugin-logger.php`, and other loggers — phpstan/phpcs fixes
- `.claude/skills/premium-translate/SKILL.md`, `.claude/skills/premium-version-update/SKILL.md` — New skills
- `js/simple-history-insights-sidebar.js` — Chart today marker, reduced y-axis ticks
- `inc/services/class-history-insights-sidebar-service.php` — Accessible chart summary, "(today)" label, footer refactor
- `css/simple-history-insights-sidebar.css` — Footer separator styles
- `src/components/DefaultFilters.jsx` — "/" shortcut to focus search, Escape to restore previous focus
- `css/styles.css` — Keyboard shortcut badge styling
