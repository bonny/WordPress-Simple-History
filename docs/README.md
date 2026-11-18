# Simple History developer docs

Reference docs for working on the Simple History code. User documentation is on [simple-history.com/docs](https://simple-history.com/docs/). These files are not part of the plugin package.

## Architecture

-   [Event Details](architecture/event-details.md) - The Event Details API: how loggers describe what changed in an event
-   [Add-on Licenses](architecture/licenses.md) - How license keys are activated, refreshed from update checks, stored and read
-   [Folder structure](folder-structure/overview.md) - What each top-level folder holds

## Database

-   [Schema](database/schema.md) - Tables, columns and indexes
-   [SQLite](database/sqlite.md) - What differs on SQLite, the `#` comment gotcha, and testing with `--env sqlite`

## APIs

-   [REST API](rest-api.md) - Endpoints and their parameters
-   [Filters usage examples](filters-usage-examples.md) - Including and excluding events from PHP, REST and WP-CLI
-   [Filtered events URL helper](filtered-events-url-helper.md) - Building links to a filtered event log
-   [Slots and filters in the React app](readme.slot-slotfills-filters.md) - JavaScript extension points

## Tooling

-   [Composer](composer.md) - Running Composer here and the PHP 7.4 resolver pin
-   [Upgrading @wordpress/scripts](upgrading-wordpress-scripts.md) - Why it is pinned to 27 and how to move off it
-   [wp-env and WordPress Playground](wp-playground-testing.md) - Local test environments
-   [SPX profiling](spx-profiling-reference.md) - Profiling requests with PHP-SPX

Testing is described in [tests/readme.md](../tests/readme.md), and code examples for loggers and dropins are in [examples/](../examples/).

## Requirements

-   WordPress 6.3 or newer
-   PHP 7.4 or newer

## Support

-   [WordPress.org support forum](https://wordpress.org/support/plugin/simple-history/)
-   [GitHub Issues](https://github.com/bonny/WordPress-Simple-History/issues)

## License

Simple History is licensed under the GPL v2 or later.
