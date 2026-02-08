# Issue #618: Placeholders that can be null/empty in RSS (and other contexts)

## How interpolation works

In `inc/class-helpers.php`, `helpers::interpolate()` only replaces placeholders when the value is **string or numeric**. Null, false, and other types are skipped, so the literal placeholder (e.g. `{edit_link}`) stays in the output.

---

## Loggers and link placeholders

### 1. **Media Logger** (`class-media-logger.php`) — **affected**

| Placeholder | Source | RSS impact |
|-------------|--------|------------|
| `{edit_link}` | `get_edit_post_link( $attachment_id )` | **Unresolved** – appears as `href="{edit_link}"` |
| `{attachment_parent_edit_link}` | `get_edit_post_link( $context['attachment_parent_id'] )` | **Unresolved** when attachment has parent post |

Also: details output uses `$edit_link` directly (line 250) for thumbnail link; if null, produces `href=''`.

---

### 2. **User Logger** (`class-user-logger.php`) — **affected**

| Placeholder | Source | RSS impact |
|-------------|--------|------------|
| `{edit_profile_link}` | `get_edit_user_link( $wp_user->ID )` | **Unresolved** – no check before interpolate |

Used in: “Edited your profile”, “Edited their profile”, “Edited the profile for user …”, “Created user …”.

---

### 3. **Post Logger** (`class-post-logger.php`) — **not affected**

Uses `edit_link` only when `$post_is_available && $context['edit_link']`. When `edit_link` is null (e.g. in RSS), it keeps the default `$row->message` (no link template), so no broken placeholder.

---

### 4. **Comments Logger** (`class-comments-logger.php`) — **not affected (plain text)**

Plain text: only does the link `str_replace` when `$edit_post_link` is truthy. When null, no link is injected; `{comment_post_title}` still comes from context. So no unresolved placeholder in the main message.

Details: `get_edit_comment_link()` is used in a conditional and only output when valid.

---

### 5. **Categories Logger** (`class-categories-logger.php`) — **not affected**

Uses link message only when `! empty( $term_edit_link ) && ! empty( $tax_edit_link )`.  
`tax_edit_link` is built with `admin_url()` (never null). When `term_edit_link` is null, the default message is used and no link placeholders are output.

---

### 6. **Plugin Duplicate Post Logger** (`class-plugin-duplicate-post-logger.php`) — **affected (empty href)**

Uses link message when `$post_is_available && $context['duplicated_post_edit_link']`.  
Then forces string context:

- `$context['new_post_edit_link'] = isset(...) ? esc_html(...) : '';`
- `$context['duplicated_post_edit_link'] = isset(...) ? esc_html(...) : '';`

So when one or both edit links are null in RSS, they become `''`. Interpolation runs and produces **`href=""`** (invalid) instead of unresolved placeholders.

---

### 7. **Options Logger** (`class-options-logger.php`) — **affected (empty href)**

In `get_details_output_for_option_page_on_front`:

- `sprintf( '<a href="%1$s">%2$s</a>', get_edit_post_link( $new_value ), ... )`
- Same for `$old_value`

When `get_edit_post_link()` returns null (e.g. in RSS), this yields **`href=""`**.

---

### 8. **Options Logger** – `{option_page_link}`

`$context['option_page_link'] = admin_url( "options-{$option_page}.php" );` – not user-dependent, so not null in RSS.

---

## Summary

| Logger | Issue in RSS / when link is null |
|--------|-----------------------------------|
| **Media** | Unresolved `{edit_link}` and `{attachment_parent_edit_link}`; empty `href` in details thumbnail |
| **User** | Unresolved `{edit_profile_link}` |
| **Post** | OK – uses link message only when edit link exists |
| **Comments** | OK – adds link only when edit link exists |
| **Categories** | OK – uses link message only when term/tax links exist |
| **Duplicate Post** | Empty `href=""` when edit links are null |
| **Options** | Empty `href=""` in “page on front” details when edit link is null |

---

## Recommendation

Fix at **output time** when generating RSS (and optionally other unauthenticated outputs):

1. **Central fix**: In RSS (and similar) context, filter or replace so that:
   - `get_edit_post_link()` and `get_edit_user_link()` (and similar) return a constructed URL instead of null, **or**
   - After getting output from loggers, replace remaining `{edit_link}`-style placeholders or `href=""` with a safe value (e.g. remove the link or use a fallback URL).

2. **Per-logger hardening** (optional): Where we use edit links in messages or details, avoid putting null into `href` (e.g. don’t use link template when link is null, or use a constructed URL in RSS context). This keeps behavior consistent and avoids empty or invalid links in feeds and exports.
