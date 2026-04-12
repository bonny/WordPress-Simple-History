---
name: action-links
description: Guides implementation of structured action links on log events. Use when adding get_action_links() to a logger or migrating from get_log_row_details_output().
allowed-tools: Read, Grep, Glob
---

# Action Links

Structured navigational links rendered below log events. Added in 5.24.0.

## UX Principle

Icons represent **action type**, not destination. Use a small, consistent icon vocabulary so users learn the pattern once. The label text describes where the link goes — the icon just reinforces _what kind of action_ it is.

## Action Types

Use only these four types. Do not invent new ones unless truly necessary.

| Action      | Icon             | When to use                             |
| ----------- | ---------------- | --------------------------------------- |
| `view`      | Eye (visibility) | Navigate to see/inspect something       |
| `edit`      | Pencil           | Navigate to modify something            |
| `preview`   | Preview          | View a draft or unpublished item        |
| `revisions` | History clock    | Compare versions or view change history |

Most links are `view`. When in doubt, use `view`.

## PHP: Adding Action Links to a Logger

Override `get_action_links()` in your logger class. Always check capabilities.

```php
public function get_action_links( $row ) {
    if ( ! current_user_can( 'required_capability' ) ) {
        return [];
    }

    return [
        [
            'url'    => admin_url( 'page.php' ),
            'label'  => __( 'View thing', 'simple-history' ),
            'action' => 'view',
        ],
    ];
}
```

### Required Keys

Each link must have all three keys:

-   **`url`** — Full URL (use `admin_url()`, `get_edit_post_link()`, etc.)
-   **`label`** — Translated, human-readable text
-   **`action`** — One of: `view`, `edit`, `preview`, `revisions`

### Multiple Links

Return multiple links when relevant. Order: edit first, then view, then others.

```php
$action_links = [];

if ( current_user_can( 'edit_post', $post_id ) ) {
    $action_links[] = [
        'url'    => get_edit_post_link( $post_id, 'raw' ),
        'label'  => __( 'Edit post', 'simple-history' ),
        'action' => 'edit',
    ];
}

if ( get_post_status( $post_id ) === 'publish' ) {
    $action_links[] = [
        'url'    => get_permalink( $post_id ),
        'label'  => __( 'View post', 'simple-history' ),
        'action' => 'view',
    ];
}

return $action_links;
```

### Migrating from Inline Links

When moving a link from `get_log_row_details_output()` to action links:

1. Add `get_action_links()` with the link
2. Remove the inline `<a>` HTML from `get_log_row_details_output()`
3. Migrate the remaining details HTML to the Event Details API (see **logger-messages** skill)
4. Keep capability checks in the new method

**Common inline links to migrate:** "View/Edit" comment links, "View plugin info" thickbox links, post edit links embedded in detail tables. These are all navigational — they belong here, not in Event Details.

### Constraints

-   Each action type may appear **at most once** per event (used as React `key`).
-   Action links are gated behind experimental features (`Helpers::experimental_features_is_enabled()`).

## Architecture

```
REST Controller (prepare_item_for_response)
  → Simple_History::get_action_links($row)
    → Logger::get_action_links($row)
    → filter: simple_history/get_action_links
      → REST response (action_links field)
        → EventActionLinks.jsx renders with icons
```

### Key Files

| File                                      | Role                                           |
| ----------------------------------------- | ---------------------------------------------- |
| `loggers/class-logger.php`                | Base class, default empty `get_action_links()` |
| `inc/class-simple-history.php`            | Routes to logger, applies filter               |
| `inc/class-wp-rest-events-controller.php` | REST schema and response                       |
| `src/components/EventActionLinks.jsx`     | Frontend rendering with icons                  |
| `css/styles.css`                          | Icon mask-image rules for action links         |

### Adding a New Action Type (Rare)

Only if the four standard types truly don't fit:

1. Add SVG to `css/icons/` (Material Symbols, 48px, FILL0, wght400)
2. Add CSS mask rule in `css/styles.css` under the action links section
3. Add mapping in `ACTION_ICONS` in `src/components/EventActionLinks.jsx`
4. Update this skill document

## Examples in Codebase

-   **Simple:** `loggers/class-site-health-logger.php` — single view link
-   **Simple:** `loggers/class-available-updates-logger.php` — single view link
-   **Complex:** `loggers/class-post-logger.php` — edit, view, preview, revisions
