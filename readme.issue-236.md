# Issue 236 — WP-CLI event search always returns zero results

Branch: `issue-236-wp-cli-search-consolidation`

## Background

`wp simple-history event search <term>` always returned zero results since the
Oct 2025 timezone fix (`7d300e1f`): the command passed empty-string
`date_from`/`date_to` defaults to `Log_Query`, where `new DateTimeImmutable('')`
parses as "now", silently turning every query into `date >= now`.

Decision: make `list --search` the canonical search (matches WP-CLI core
conventions — filtering local entities is done with flags on `list`, dedicated
`search` subcommands in core are for other things like wordpress.org lookups).

## Changes

-   `inc/class-log-query.php` — empty/whitespace-only string `date_from`/`date_to`
    are now treated as null (no date filter). Protects all callers (REST, CLI,
    third parties).
-   `inc/services/wp-cli-commands/class-wp-cli-search-command.php` — rewritten as
    a thin deprecated alias: prints a `WP_CLI::warning` and delegates to the list
    command (`<term>` → `--search`, `--newer_than` → `--date_from`,
    `--older_than` → `--date_to`). Plan: remove entirely a few major versions out.
-   `inc/services/wp-cli-commands/class-wp-cli-list-command.php` —
    -   new `--metadata_search=<text>` option (GUI metadata-search parity; searches
        all context values: IPs, emails, user agents…)
    -   new `--ai_only` flag (GUI AI-filter parity)
    -   `fields` default added to `wp_parse_args` so the method works when called
        directly (WP-CLI only injects docblock defaults on shell invocation)
-   `tests/wpunit/LogQueryTest.php` — regression test: empty/whitespace dates
    behave like omitted dates.
-   `tests/wpunit/SearchTest.php` — new `ai_only` test (this Log_Query arg had
    no coverage at all before).
-   `tests/functional/WPCliCest.php` — tests for `list --search`,
    `list --metadata_search`, `list --ai_only` (negative path; positive AI
    matches covered in wpunit since functional tests can't create AI-context
    events), the deprecated alias, and its `--newer_than`/`--older_than`
    translation. Note: the deprecation warning goes to STDERR, which
    Codeception's `$I->cli()` does not capture, so only results are asserted.
-   `readme.txt` — changelog entries (Added / Deprecated / Fixed).

## Docs

Live docs page https://simple-history.com/features/wp-cli-commands/ (page id 2866) updated 2026-06-12: `event search` marked deprecated, search section now
shows `wp simple-history list --search=WooCommerce`.

## Verification

-   `wp simple-history event search test` → warns + returns matching rows
-   `wp simple-history event search test --newer_than=2026-06-01` → date filter works via alias
-   `wp simple-history list --metadata_search=claude@example.com` → finds events by context value
-   `wp simple-history list --ai_only` → only AI-attributed events
-   `codecept run wpunit:LogQueryTest:test_empty_string_dates_are_ignored` → OK
-   `codecept run functional WPCliCest:test_list_search` / `test_event_search_deprecated_alias` → OK
-   phpcs clean on changed files; phpstan clean on the three changed PHP files
    (full phpstan run crashes with a parallel-worker error on unmodified develop
    too — pre-existing environment issue, not from this branch)

## Todo

-   [ ] Human review + commit
-   [ ] Future: remove `event search` entirely (a few major versions out; changelog note each release until then)
