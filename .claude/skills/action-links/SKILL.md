---
name: action-links
description: Guides implementation of structured action links on log events. Use when adding get_action_links() to a logger or migrating from get_log_row_details_output().
allowed-tools: Read, Grep, Glob
---

# Action Links

Structured navigational links rendered below log events. Added in 5.24.0.

## UX Principle

Icons represent **action type**, not destination. Use a small, consistent icon vocabulary so users learn the pattern once. The label text describes where the link goes — the icon just reinforces _what kind of action_ it is.

## Label Wording

The verb is supplied by the **icon** + link styling. The label encodes the destination, not the action.

**Rule:** if the verb describes "navigate to" — drop it. If the verb describes a **state change** or a **non-obvious operation** — keep it.

| Drop the verb (destination labels)        | Keep the verb (state-change / non-obvious) |
| ----------------------------------------- | ------------------------------------------ |
| About this version _(not "View About …")_ | Restore from Trash                         |
| Site Health _(not "View Site Health")_    | Approve comment                            |
| Plugin info _(not "View plugin info")_    | Trash comment                              |
| All updates _(not "View all updates")_    | Stick post                                 |
| Changelog _(not "View changelog")_        | Revert revision                            |

**Edit · View · Preview · Revisions on the same noun is the one exception** — those verbs differ from each other and the verb is the whole differentiator, so keep them (e.g. on a post: `Edit post`, `View post`, `Preview post`, `Revisions`). The icons already match the verbs; the labels still need the verbs to disambiguate intent across the row.

**Describe the content, not the page name.** Sometimes WP's own wording for a destination is generic (e.g. the admin bar's "About WordPress" item points to a version-specific page). A label that describes _what the user will find there_ — like "About this version" instead of "About WordPress" — beats a literal mirror of WP's vocabulary when the literal mirror is less informative.

**Self-describing in REST.** Action links also appear in REST JSON. The bare-noun label + the structured `action` field (`"view"`, `"edit"`) is enough — consumers that want a verbose string concatenate them. Don't smuggle the verb into the label to compensate.

## Overview / Destination Links

In addition to per-item links (Edit/View/Preview the specific thing this event is about), consider adding a **second link that points to the admin list for that item type**. Examples:

| Item type       | Overview link                       | URL                         |
| --------------- | ----------------------------------- | --------------------------- |
| Users           | All users                           | `users.php`                 |
| Plugins         | All plugins                         | `plugins.php`               |
| Posts (per-CPT) | All posts / All pages / All `<cpt>` | `edit.php?post_type=<type>` |
| Media           | All media                           | `upload.php`                |
| Comments        | All comments                        | `edit-comments.php`         |

**Why include them:**

-   **Admin shortcut.** From an event card the user often wants to "go act on the related thing" — the overview link saves the trip through the left nav.
-   **REST / CLI / agent consumers.** Action links also appear in the REST response. Consumers outside wp-admin (CLI, dashboards, AI agents) can't fall back on the admin menu at all; the overview link is the only navigable hand-off into wp-admin they get.
-   **Delete events.** When the item no longer exists, the per-item link is dead. Always include the overview link on delete events — it's the only useful destination. (Post and Media loggers do this: even when the per-post/per-attachment block is suppressed for `post_deleted` / `attachment_deleted`, the overview link still renders.)

**Label pattern: `All <plural>`** — bare noun per the Label Wording rule. For post types use the registered plural label (e.g. `strtolower( $post_type_obj->labels->name )`) so custom post types render correctly. Cap-check against the destination's required capability (`list_users`, `activate_plugins`, `$post_type_obj->cap->edit_posts`, `upload_files`, etc.), not the logger's general capability.

**Drift-aware gating.** Some destinations only make sense for the current state of the site. The clearest example: `wp-admin/about.php` always shows the _current_ WP version's content, so linking to it from a 2-year-old "Updated to 6.4" event would land the user on info about 6.9 — misleading. Solution in `class-core-updates-logger.php`: compare the event's `new_version` against the current install; only render the local about-page link when they match. Apply the same pattern any time a link's destination drifts out of sync with the historical state the event captured.

## Action Types

Use only these five types. Do not invent new ones unless truly necessary.

| Action      | Icon             | When to use                                                                         |
| ----------- | ---------------- | ----------------------------------------------------------------------------------- |
| `view`      | Eye (visibility) | Navigate to see/inspect something                                                   |
| `edit`      | Pencil           | Navigate to modify something                                                        |
| `preview`   | Preview          | View a draft or unpublished item                                                    |
| `revisions` | History clock    | Compare versions or view change history                                             |
| `details`   | Info             | Open the event details modal (auto-appended via `Logger::event_has_more_details()`) |

### External vs internal links

The action type (`view`/`edit`/etc.) describes intent. The icon you actually see depends on **where the link goes**:

-   **Internal URL** (same host as the admin) → renders the action's icon (`view` → eye, etc.).
-   **External URL** (different host) → renders the **external-link icon** (box with arrow exiting) and is opened in a new tab with `rel="noopener noreferrer"`. This swap is automatic in `EventActionLinks.jsx` via `isExternalUrl()` — loggers don't set a flag.

Keep `action: 'view'` on external links anyway. The action describes the user's intent (read more); the external indicator is a UI concern handled at render time.

Most links are `view`. When in doubt, use `view`. Don't return `details` from `get_action_links()` directly — opt in via `event_has_more_details()` instead so the modal-link wiring stays consistent.

`event_has_more_details()` returns `string|false`: the label to render (e.g. `__( 'Show error message', ... )`), or `false` to skip. Pick a label that names the actual payload (`Show error message`, `Show all 47 roles`) rather than a generic `Show details` — the icon already signals "more info", the label should sell what's behind it.

**Verify the payload exists before returning a label.** A logger opted into this should still inspect `$row->context` and return `false` when the relevant keys are missing — otherwise older events without the payload would lead users to a modal with nothing extra. Match on both the message key and the presence of the data.

## PHP: Adding Action Links to a Logger

Override `get_action_links()` in your logger class. Always check capabilities.

```php
public function get_action_links( $row ) {
    if ( ! current_user_can( 'required_capability' ) ) {
        return [];
    }

    return [
        [
            'url'    => admin_url( 'about.php' ),
            'label'  => __( 'About this version', 'simple-history' ),
            'action' => 'view',
        ],
    ];
}
```

The single-link example uses a bare-noun label per the **Label Wording** rule above.

### Required Keys

Each link must have all three keys:

-   **`url`** — Full URL (use `admin_url()`, `get_edit_post_link()`, etc.)
-   **`label`** — Translated, human-readable text
-   **`action`** — One of: `view`, `edit`, `preview`, `revisions`

### Multiple Links

Return multiple links when relevant. Order: edit first, then view, then others.

The example below keeps the verbs (`Edit post`, `View post`) because they're the **same-noun disambiguation exception** from the Label Wording rule — when several actions target the same noun, the verb is the differentiator and must stay.

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

### No Links in the Message Body When Action Links Cover the Destination

Action links are the canonical "what can I do?" affordance. The message body's job is "what happened?" — a declarative sentence. Putting an `<a>` inside the message (e.g. wrapping `{post_title}` in the template) re-introduces the visual hierarchy collapse action links were designed to eliminate: the linked title reads as a CTA mid-sentence and competes with the action row below.

**Rule:** Inline links inside message templates are permitted **only when they point somewhere the action row cannot reach**. If `get_action_links()` already returns a link to that destination (Edit, View, Revisions, the overview page, …), the message text must be plain.

**Examples:**

| Message template                                                           | OK?                                                                          |
| -------------------------------------------------------------------------- | ---------------------------------------------------------------------------- |
| `Updated page "<a href='…'>{post_title}</a>"` with Edit/View in action row | ❌ duplicates the Edit link — drop the `<a>`, keep just `"{post_title}"`     |
| `Updated page "{post_title}"`                                              | ✅ plain title, action row handles navigation                                |
| `Mentioned {external_url}` where no action link points there               | ✅ legitimate — the action row can't represent arbitrary external references |

**Deleted-item events stay plain too.** When the per-item link would be dead (post trashed, plugin uninstalled), a plain title beats a broken link. The overview action link (`All pages`, `All plugins`) is the right hand-off.

**A11y:** screen reader users navigating by link list hear fewer, clearer labels — "Edit page" beats an ambiguous quoted title followed by "Edit page".

This is not an urgent migration. Apply opportunistically when touching a logger for other reasons. See the **logger-messages** skill for the corresponding guidance on the message side.

### Migrating from Inline Links

When moving a link from `get_log_row_details_output()` to action links:

1. Add `get_action_links()` with the link
2. Remove the inline `<a>` HTML from `get_log_row_details_output()`
3. Migrate the remaining details HTML to the Event Details API (see **logger-messages** skill)
4. Keep capability checks in the new method

**Common inline links to migrate:** "View/Edit" comment links, "View plugin info" thickbox links, post edit links embedded in detail tables. These are all navigational — they belong here, not in Event Details.

When migrating, **apply the Label Wording rule** — don't carry "View X" labels over verbatim. A legacy "View comment" inline link becomes `Comment` (or `Approve comment` if that's the action you're actually surfacing). The icon supplies the verb.

### Constraints

-   `get_action_links()` runs on **every event** in the REST response — it is not gated behind the experimental-features flag (reactions are; action links are not). Keep the body cheap: short-circuit when no link could possibly apply, prefer cap checks before DB-touching helpers like `get_post()`.
-   The same action type may appear more than once per event when it points to genuinely different destinations (e.g. two `view` links — one to a local admin page, one to an external reference page). React keys are scoped by `url`, not by action.

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

-   **Drift-aware + external link:** `loggers/class-core-updates-logger.php` — local `About this version` link gated by `current install X.Y == event new_version X.Y`, always-shown external `WordPress {version} release notes` link with auto external-link icon.
-   **Overview + per-item:** `loggers/class-user-logger.php`, `loggers/class-plugin-logger.php`, `loggers/class-media-logger.php` — overview link (`All users` / `All plugins` / `All media`) plus per-item action when applicable.
-   **Per-post-type overview + same-noun disambiguation:** `loggers/class-post-logger.php` — `Edit %s` / `View %s` / `Preview %s` / `Revisions` on the existing post plus `All <plural>` overview gated by `$post_type_obj->cap->edit_posts`. Overview link still rendered on `post_deleted` events. Canonical example of the same-noun exception keeping verbs across the row.
-   **Single overview link:** `loggers/class-site-health-logger.php` (`Site Health`), `loggers/class-available-updates-logger.php` (`All updates` / `Changelog`).
