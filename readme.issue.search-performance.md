# Search Performance Issue

## Problem

REST API requests with the `search` parameter are extremely slow compared to requests without search.

**Fast request (no search):**

```
/wp-json/simple-history/v1/events?page=1&per_page=20&dates=lastdays:30
```

**Slow request (with search):**

```
/wp-json/simple-history/v1/events?page=1&per_page=20&search=updated&dates=lastdays:7
```

## Root Cause

The search functionality in `inc/class-log-query.php` (lines 2128-2208) searches the contexts table using subqueries with `LIKE '%...%'`:

```sql
id IN (SELECT history_id FROM wp_simple_history_contexts AS c WHERE c.value LIKE '%updated%')
```

This is slow because:

1. **No index on `value` column** - The contexts table only has indexes on `context_id` (PRIMARY), `history_id`, and `key`
2. **Leading wildcard prevents index use** - Even with an index, `LIKE '%...%'` cannot use B-tree indexes
3. **Large table** - 153,768 rows in contexts table (avg ~19 rows per event)
4. **Subquery per word** - Each search word triggers a separate subquery

### Table Statistics

| Metric                 | Value   |
| ---------------------- | ------- |
| Total context rows     | 153,768 |
| Unique events          | 8,227   |
| Avg contexts per event | ~19     |

### Current Indexes on `wp_simple_history_contexts`

| Index      | Column     |
| ---------- | ---------- |
| PRIMARY    | context_id |
| history_id | history_id |
| key        | key        |

## Possible Solutions

### Option 1: Add FULLTEXT Index

Add a FULLTEXT index on the `value` column and use `MATCH() AGAINST()` syntax.

**Pros:**

-   Significant performance improvement
-   Works with existing table structure

**Cons:**

-   Minimum word length (default 3-4 chars)
-   Doesn't work with partial word matches
-   Requires MySQL/MariaDB FULLTEXT support

### Option 2: Limit Context Search Scope

Only search specific context keys (like `_message_key`) instead of all values.

**Pros:**

-   Reduces search scope dramatically
-   No schema changes needed

**Cons:**

-   May miss some relevant results
-   Requires identifying which keys are searchable

### Option 3: Denormalized Search Column

Add a `search_text` column to the main history table with concatenated searchable content.

**Pros:**

-   Single column to search
-   Can add FULLTEXT index
-   Fast searches

**Cons:**

-   Data duplication
-   Requires migration
-   Must keep in sync on insert

### Option 4: Disable Context Search by Default

Only search main table columns (message, logger, level) by default. Add option to include context search.

**Pros:**

-   Immediate performance fix
-   No schema changes
-   Backward compatible with parameter

**Cons:**

-   Reduces search coverage
-   May confuse users expecting full search

## Files Involved

-   `inc/class-log-query.php` - `add_search_to_inner_where_query()` method (line 2128)
-   `inc/class-wp-rest-events-controller.php` - REST API endpoint
-   Database table: `wp_simple_history_contexts`

## Related

-   Search is also used by WP-CLI commands in `inc/services/wp-cli-commands/`
