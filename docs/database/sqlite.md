# SQLite

Simple History supports MySQL/MariaDB and SQLite. On SQLite, WordPress runs through the [SQLite Database Integration](https://github.com/WordPress/sqlite-database-integration) plugin, which takes the MySQL queries WordPress and plugins send and runs them on SQLite. WordPress Playground uses it too.

Check the engine with `Log_Query::get_db_engine()`, which returns `'mysql'` or `'sqlite'`.

## What works differently on SQLite

-   **No occasion grouping.** `Log_Query::query_overview()` sends SQLite to `query_overview_simple()`, which returns every event ungrouped. The grouping query counts consecutive rows with MySQL session variables, which SQLite doesn't have. That's why Playground shows events as separate rows.
-   **No MySQL-only statements** without a guard, for example `OPTIMIZE TABLE` or `SHOW TABLE STATUS`.

## Gotcha: no `#` comments in SQL that SQLite runs

MySQL accepts `#` as a line comment. SQLite only understands `--` and `/* */`.

The 2.x versions of the integration plugin have a query translator that recognizes `#` comments but copies them into the SQLite query unchanged. SQLite then fails with:

```
SQLSTATE[HY000]: General error: 1 unrecognized token: "#"
```

The query returns no rows, and nothing else tells you why. This hid a real bug: the occasions query ("similar events") had `#` comments in its SQL template and returned nothing on SQLite. It was found when `OccasionsLoggerCapabilityTest` failed in the SQLite CI job, and fixed in commit `e3c0ca79` (October 2026).

Version 3.0 of the plugin replaced the translator with a driver built on a full MySQL lexer, which skips `#` comments (checked in the v3.1.0 code). Sites still on 2.x, and our own test suite (see below), still hit it.

**Rule:** keep comments out of SQL strings that can run on SQLite. Put them in PHP comments above the query. `-- ` (with the space) works on both engines too. The overview query in `query_overview_mysql()` still has `#` comments, which is fine because only MySQL runs it.

## Testing on SQLite

-   Run the suite on SQLite with `--env sqlite`:

    ```bash
    docker compose run --rm php-cli vendor/bin/codecept run wpunit --env sqlite
    ```

    CI runs this as the "wpunit · WP 6.8 · PHP 8.1 · SQLite" job.

-   The SQLite support in the test suite comes from wp-browser, which bundles the integration plugin. As of October 2026 that is **2.2.17**, the old translator with the `#` comment problem (`vendor/lucatume/wp-browser/includes/sqlite-database-integration/load.php` has the version).
-   Use the `SkipsOnSqlite` test helper (`tests/_support/Helper/SkipsOnSqlite.php`) only for behaviour SQLite really doesn't have, like occasion grouping. Don't use it to silence a failing query; that is how the `#` comment bug would have been hidden.
-   For a quick manual check, a WordPress Playground site uses SQLite. See [wp-playground-testing.md](../wp-playground-testing.md).

## Debugging a query that returns nothing on SQLite

The plugin doesn't throw. It puts an HTML error page in `$wpdb->last_error`, so a failing query looks like an empty result. Strip the tags and look for `SQLSTATE` to get the real reason:

```php
global $wpdb;
$wpdb->get_results( $sql );
error_log( wp_strip_all_tags( html_entity_decode( $wpdb->last_error ) ) );
```

Then cut the query down until it works. Removing comments is a good first step.
