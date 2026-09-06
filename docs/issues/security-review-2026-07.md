# Security review — July 2026

**Trigger:** An external user emailed Pär reporting that the REST `/events`
endpoint can be read without a `view_history_capability` check. That claim was
verified (Finding 1) and then used as the seed for a broader security review of
the plugin.

**Method:** Manual source review + one **empirical** reproduction against a live
free install (Simple History 5.29.0, WordPress 6.8.3, only `simple-history`
active), plus five parallel focused audits (REST authorization, SQL injection,
XSS/output escaping, CSRF/capabilities, sensitive-data exposure). Every finding
below was re-verified by reading the code path; the load-bearing ones are marked
**confirmed**.

**Headline:** No Critical/High. No SQL injection. Escaping and CSRF are broadly
sound. The two most actionable items are **Finding 1** (the reported endpoints,
Low, patch verified end-to-end) and **Finding 2** (User Card PII/enumeration,
Medium — found during the sweep, arguably more impactful than the original
report).

## Severity summary

| #   | Finding                                                                                | Severity   | Status                 |
| --- | -------------------------------------------------------------------------------------- | ---------- | ---------------------- |
| 1   | REST `/events` + `/search-options` don't enforce `view_history_capability`             | Low        | Fixed in 5.30.0        |
| 2   | User Card endpoint leaks any user's email/login/roles + enables enumeration            | **Medium** | Fixed in 5.30.0        |
| 3   | RSS feed secret compared with `===` instead of `hash_equals()`                         | Low–Med    | Fixed in 5.30.0        |
| 4   | `clear_log` has no capability backstop (nonce-only)                                    | Low        | Fixed in 5.30.0        |
| 5   | Full-log export & RSS-secret regeneration are nonce-only, no cap check                 | Low        | Fixed in 5.30.0        |
| 6   | XSS hardening: unescaped entity names in 3 loggers + HTML export lacks `wp_kses`       | Low        | Fixed in 5.30.0        |
| 7   | Detective Mode masks only `pass*`-prefixed fields                                      | Low        | Fixed in 5.30.0        |
| 8   | Reactions are a write gated by read permission (by design)                             | Low        | Resolved — no change   |
| —   | Dead code: `SearchOptions_Controller::get_item_permissions_check` (login-only, unused) | Info       | Fixed (method deleted) |

**All findings in this review are resolved as of 5.30.0.** The original
recommended fix order was **2 → 1 → 3 → 4/5 → 6**, with 7/8 as follow-ups.

---

## Finding 1 — REST `/events` and `/search-options` don't enforce `view_history_capability`

**Severity:** Low (bounded by per-logger capability filtering) · **Confirmed empirically**

### Summary

`WP_REST_Events_Controller::get_items_permissions_check` and
`WP_REST_SearchOptions_Controller::get_items_permissions_check` gate only on
`is_user_logged_in()`. They do not check `Helpers::get_view_history_capability()`
(default `edit_pages`), unlike the sibling `user-card` controller.

The events query is still filtered per-logger by
`Simple_History::get_loggers_that_user_can_read()`, so this is **not** a
"dump the whole log" hole — a caller only receives events from loggers whose
capability they hold. But some core loggers declare a capability _below_ the
`edit_pages` view floor. The **Notes logger**
(`loggers/class-notes-logger.php` → `capability => 'edit_posts'`) is the clear
case: `edit_posts` is held by **Author**/**Contributor**, who lack `edit_pages`.
So an Author can read Notes-logger events over REST despite being blocked from
the history admin page. The blast radius grows if any third-party/premium logger
declares a capability `<= edit_posts`.

### Empirical proof

Created a plain Author user and drove the real REST stack (`rest_do_request()`)
as that user after seeding one `NotesLogger` event (`edit_posts`) and one
`SimplePostLogger` event (`edit_pages`):

```
user_can edit_posts .............. true
user_can edit_pages .............. false
view_history_capability .......... edit_pages
user_can(view_history_capability). false   <-- not allowed to see the history page

get_items_permissions_check() ..... true    <-- endpoint let the Author in
GET /simple-history/v1/events ..... HTTP 200
  [NotesLogger] Added a note to post "Secret note ..."   <-- leaked
Notes event visible to Author? .... YES
Post  event visible to Author? .... no      <-- per-logger filter still works
```

After applying the patch below, the same Author gets **HTTP 403** on both
endpoints while an Administrator still gets **200** — verified the same way.

### Patch

```diff
--- a/inc/class-wp-rest-events-controller.php
+++ b/inc/class-wp-rest-events-controller.php
@@ public function get_items_permissions_check( $request ) {
 		if ( ! is_user_logged_in() ) {
 			return new WP_Error(
 				'rest_forbidden_context',
 				__( 'Sorry, you are not allowed to view events.', 'simple-history' ),
 				array( 'status' => rest_authorization_required_code() )
 			);
 		}

+		// User must be allowed to view the history log.
+		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_history_capability().
+		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
+			return new WP_Error(
+				'rest_forbidden_context',
+				__( 'Sorry, you are not allowed to view events.', 'simple-history' ),
+				array( 'status' => rest_authorization_required_code() )
+			);
+		}
+
 		// Surrounding events feature requires administrator privileges.
 		if ( isset( $request['surrounding_event_id'] ) && ! current_user_can( 'manage_options' ) ) {
```

```diff
--- a/inc/class-wp-rest-searchoptions-controller.php
+++ b/inc/class-wp-rest-searchoptions-controller.php
@@ public function get_items_permissions_check( $request ) {
 		if ( ! is_user_logged_in() ) {
 			return new WP_Error( ... );
 		}

+		// User must be allowed to view the history log.
+		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_history_capability().
+		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
+			return new WP_Error(
+				'rest_forbidden_context',
+				__( 'Sorry, you are not allowed to view events.', 'simple-history' ),
+				[ 'status' => rest_authorization_required_code() ]
+			);
+		}
+
 		return true;
 	}
```

Notes: the events controller already imports `Simple_History\Helpers`; the
searchoptions controller is in the `Simple_History` namespace and already
references `Helpers::` unqualified, so no new `use` is needed. The
`get_has_updates` route shares this permission callback, so it is covered too.
Keep the per-logger `Log_Query` filter as defense-in-depth.

The `search-options` payload also returns some site-wide, non-per-logger data to
any logged-in user today (event counts, activity date range, feature flags,
add-on list, `current_user_id`, and — on premium sites — `maps_api_key`). This
patch closes all of that.

---

## Finding 2 — User Card endpoint leaks any user's PII and enables enumeration

**Severity: Medium · Confirmed**

`GET /simple-history/v1/users/<id>/card`
(`inc/class-wp-rest-user-card-controller.php:37`) is gated only by
`get_view_history_capability()` (default `edit_pages` → **Editor**), but
`get_user_card()` (`:105`) accepts **any** user id and returns `user_email`,
`user_login`, `display_name`, `roles`, and the edit-profile URL — for any user,
including administrators and users who never appear in any log the caller can
read. Iterating the id enumerates every account's email and login.

In stock WordPress an Editor cannot list users or read others' emails (that needs
`list_users`/`edit_users`). This endpoint hands out that PII a full capability
tier lower. It is also **inconsistent with the plugin's own `/search-user`
route** (`inc/class-wp-rest-searchoptions-controller.php:133`), which correctly
gates the same email/login fields behind `list_users`.

### Fix (recommended: field-level gate, preserves the card for editors)

Keep the endpoint viewable at the history-capability level, but only return the
sensitive identity fields when the caller can actually list users. `display_name`
and `avatar` stay (they're already exposed elsewhere); `profile_url` via
`get_edit_user_link()` is self-gating (returns `''` when the caller can't edit
the user).

```diff
--- a/inc/class-wp-rest-user-card-controller.php
+++ b/inc/class-wp-rest-user-card-controller.php
@@ public function get_user_card( $request ) {
 		$avatar_data = get_avatar_data( $user_id, [ 'size' => 96 ] );

+		// Email / login / roles are PII: only expose to users who may list users,
+		// matching the /search-user route and WordPress core behaviour.
+		$can_list_users = current_user_can( 'list_users' );
+
 		// Core identity fields.
 		$data = [
 			'user_id'            => $user->ID,
 			'display_name'       => $user->display_name,
-			'user_login'         => $user->user_login,
-			'user_email'         => $user->user_email,
+			'user_login'         => $can_list_users ? $user->user_login : '',
+			'user_email'         => $can_list_users ? $user->user_email : '',
 			'avatar_url'         => $avatar_data['url'] ?? '',
 			'profile_url'        => get_edit_user_link( $user->ID ),
-			'roles'              => array_values( $user->roles ),
+			'roles'              => $can_list_users ? array_values( $user->roles ) : [],
 			'has_premium_add_on' => Helpers::is_premium_add_on_active(),
 		];
```

**Stricter alternative** (if the card is only ever shown to admins/editors who
manage users anyway): require `list_users` in
`get_user_card_permissions_check()` outright, exactly like `/search-user`. This
is simpler but removes the name/avatar card for editors who can view the log but
not list users — confirm the UX before choosing.

---

## Finding 3 — RSS feed secret compared with `===`, not `hash_equals()`

**Severity: Low–Medium · Confirmed**

`dropins/class-rss-dropin.php:263` validates the feed secret with
`if ( $rss_secret_option === $rss_secret_get )`. A match then unlocks the
**entire** log (`add_filter( 'simple_history/loggers_user_can_read/can_read_single_logger', '__return_true' )`,
line 277). `===` is a non-constant-time, short-circuiting comparison; the correct
primitive for a bearer secret is `hash_equals()`, which always compares the whole
string so response time leaks nothing about how many leading characters matched.

Real-world exploitability is low — a timing attack over HTTP against a ~94-bit
secret is drowned out by network jitter and has no repeatable oracle — but the
fix is a one-line, standard hardening change.

```diff
--- a/dropins/class-rss-dropin.php
+++ b/dropins/class-rss-dropin.php
@@ public function output_rss() {
-		if ( $rss_secret_option === $rss_secret_get ) {
+		if ( hash_equals( (string) $rss_secret_option, (string) $rss_secret_get ) ) {
```

(Secret generation is fine: `random_int()` CSPRNG, 20 chars. Regeneration is
nonce-gated — see Finding 5 for the missing cap check there.)

---

## Finding 4 — `clear_log` has no capability backstop

**Severity: Low · Confirmed**

`Helpers::user_can_clear_log()` (`inc/class-helpers.php:1234`) returns
`apply_filters( 'simple_history/user_can_clear_log', true )` — it defaults
**open**, with no `current_user_can()`. `Helpers::clear_log()` then `TRUNCATE`s
the events + contexts tables. The handler
(`inc/services/class-setup-settings-page.php:731`) runs on `admin_menu` and is
gated solely by `wp_verify_nonce( ..., 'simple_history_clear_log' )`.

Not exploitable today because the nonce is only printed on the settings page,
which is behind `manage_options`. But wiping the whole audit trail should not be
authorized by nonce-possession alone — add an explicit capability default:

```diff
--- a/inc/class-helpers.php
+++ b/inc/class-helpers.php
@@ public static function user_can_clear_log() {
-		return apply_filters( 'simple_history/user_can_clear_log', true );
+		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability.
+		return apply_filters( 'simple_history/user_can_clear_log', current_user_can( 'manage_options' ) );
```

Then also _enforce_ it in the handler (defense in depth) before clearing:
`if ( ! self::user_can_clear_log() ) { return; }`.

---

## Finding 5 — Full-log export & RSS-secret regeneration are nonce-only

**Severity: Low · Confirmed**

Two more privileged actions verify a nonce but no capability. Same containment as
Finding 4 (the nonce only appears on `manage_options` pages), so these are
hardening backstops, not live holes.

-   **Export** — `dropins/class-export-dropin.php:63` `download_export()` runs on
    `admin_init`, checks page + action + `check_admin_referer( self::class . '-action-export' )`
    (line 82), then streams the whole log (all events, IPs, activity) as CSV/JSON.
    Add a cap check right after the nonce check:

    ```diff
    		check_admin_referer( self::class . '-action-export' );
    +
    +		if ( ! current_user_can( 'manage_options' ) ) {
    +			wp_die( esc_html__( 'You do not have permission to export the history.', 'simple-history' ) );
    +		}
    ```

-   **RSS secret regeneration** — `dropins/class-rss-dropin.php:116` regenerates the
    feed secret after a nonce check only. Gate the mutation on capability:

    ```diff
    -		if ( $create_nonce_ok ) {
    +		if ( $create_nonce_ok && current_user_can( 'manage_options' ) ) {
    			$this->update_rss_secret();
    ```

(If a dedicated settings capability helper exists, e.g.
`Helpers::get_view_settings_capability()`, prefer it over the literal
`manage_options` in both spots for consistency with the rest of the settings UI.)

---

## Finding 6 — XSS hardening: unescaped entity names + HTML export lacks `wp_kses`

**Severity: Low · Confirmed (media logger spot-checked)**

Output escaping is broadly correct — the base
`Logger::get_log_row_plain_text_output()` wraps interpolated messages in
`esc_html()`, REST/React use the escaped paths, and the RSS dropin has a
`wp_kses()` safety net. The exceptions are loggers that **override** the output
and interpolate an entity _name_ into `message_html` (rendered by React via
`dangerouslySetInnerHTML`) **without** `esc_html()`, unlike the post logger which
does escape. Each is gated by WordPress kses on save, so only an actor holding
`unfiltered_html` (single-site admin/editor) could plant a payload, which then
fires for an admin viewing the log. Real but low-severity and fragile.

Escape the interpolated name/title in these overrides (mirror the post logger):

-   **Media** — `loggers/class-media-logger.php:434` interpolates `{attachment_title}`
    raw (only `post_type`, `attachment_filename`, and `attachment_parent_title` are
    escaped). Add `$context['attachment_title'] = esc_html( $context['attachment_title'] ?? '' );`
    before the `interpolate()` call.
-   **Categories** — `loggers/class-categories-logger.php:347` interpolates
    `{term_name}` / `{to_term_name}` unescaped. `esc_html()` them into `$context`
    before interpolating.
-   **User** — `loggers/class-user-logger.php:898,917` interpolate
    `{edited_user_login}`/`{edited_user_email}`/`{created_user_login}`/`{created_user_email}`
    unescaped. Structurally low-risk (`user_login` is `sanitize_user( …, strict )`,
    emails are validated) but escape for consistency. Note: the unauthenticated
    failed-login `{login}` path is **not** affected — it uses the base `esc_html`
    path and is `sanitize_text_field`'d.
-   **Comments** — `loggers/class-comments-logger.php:588,679` feed raw
    comment/pingback/trackback content through the RAW details formatter. Consider
    `wp_kses_post()` before rendering so `unfiltered_html`-authored script can't
    reach the admin viewer.

**HTML export has no kses net** — `inc/class-export.php:314` writes the header,
plain-text, and details outputs **raw** into a downloadable `.html`, unlike the
RSS dropin which `wp_kses()`-filters the same three. This is where the logger
gaps above would actually land (the admin opens the exported file). Single fix
that neutralizes all of the above for the export surface:

```diff
--- a/inc/class-export.php
+++ b/inc/class-export.php
@@ protected function output_html_row( $fp, $one_row ) {
-			$this->simple_history->get_log_row_header_output( $one_row ),
-			$this->simple_history->get_log_row_plain_text_output( $one_row ),
-			$this->simple_history->get_log_row_details_output( $one_row )
+			wp_kses_post( $this->simple_history->get_log_row_header_output( $one_row ) ),
+			wp_kses_post( $this->simple_history->get_log_row_plain_text_output( $one_row ) ),
+			wp_kses_post( $this->simple_history->get_log_row_details_output( $one_row ) )
```

(CSV and JSON export paths are already safe — `esc_csv_field` / `json_encode`.)

---

## Finding 7 — Detective Mode masks only `pass*`-prefixed fields

**Severity: Low · Per audit**

`dropins/class-detective-mode-dropin.php:188` `mask_sensitive_data()` masks only
keys beginning with `pwd`/`pass`/`confirm_pass`/`user_pass`, so `token`, `secret`,
`client_secret`, `authorization`, credit-card fields, and even
`old_password`/`current_password` are stored in the DB context in plaintext —
despite the UI claiming "Common password fields are automatically masked."
Mitigations that keep this Low: the feature is **off by default**, is documented
as capturing sensitive data, the data is only readable by users with the logger's
`manage_options` read cap, and it does **not** capture `HTTP_COOKIE` /
`HTTP_AUTHORIZATION`. Fix: broaden the masked-key list (substring match on
`pass`/`pwd`/`token`/`secret`/`auth`/`key`/`card`) and/or soften the UI claim.

---

## Finding 8 — Reactions are a write gated by read permission

**Severity: Low · Resolved — by design, no change made**

`inc/class-wp-rest-events-controller.php:199,219` — the `react`/`unreact`
`CREATABLE` routes use `get_item_permissions_check` (logged-in + can-view-event)
rather than `update_item_permissions_check` (`manage_options`). So any user who
can view an event can toggle a reaction on it (mutating a `_reactions` context
blob).

Confirmed intended: reacting to an event you can already read is the whole
point of the feature, so read permission is the correct gate. The write is
bounded in both directions — `type` is `enum`-validated against
`get_allowed_reaction_types()`, so arbitrary keys can't be injected, and
reactions are stored per `(type, user_id)`, so a user can add at most one of
each type per event. No unbounded growth, no spam vector.

Note for readers of the original review: this finding's first write-up leaned
on "reactions are default-disabled" as a mitigating factor. That is no longer
true — reactions graduated out of experimental and are **on by default** as of
5.30.0. The reasoning above does not depend on it.

---

## Info — dead code

`WP_REST_SearchOptions_Controller::get_item_permissions_check`
(`inc/class-wp-rest-searchoptions-controller.php:95`, login-only) is referenced
by no registered route (both routes use `get_items_permissions_check` /
`..._for_search_user`). Harmless now, but a future route wiring to it would
inherit a login-only gate. Delete it.

---

## Verified clean (with evidence)

-   **SQL injection: none.** `inc/class-log-query.php` was read in full. Every REST
    param (`search`, `loggers`, `loglevels`, `users`, `messages`, `date_*`,
    `dates`/`months`, `ip_address`, `context_filters`, `metadata_search`,
    `include`/`post__in`, etc.) reaches SQL via `$wpdb->prepare()` placeholders,
    `intval` casts, strict date validation, or a fixed whitelist. `initiator` uses
    `esc_sql()` **and** is whitelisted against `Log_Initiators::get_valid_initiators()`
    (a 5-constant set). No `orderby`/`order` REST param exists, so no ORDER BY
    vector; all `ORDER BY` clauses are hardcoded. Table names are prefix-derived.
-   **Output escaping** is defended in depth outside Finding 6 (base `esc_html`,
    React auto-escaping except the two `dangerouslySetInnerHTML` sinks fed by PHP
    escaping paths, RSS `wp_kses` net).
-   **Credential handling** — passwords never stored (`user_pass` skipped; app
    passwords log the name, not the value); options logger redacts license keys and
    logs oversized/structured values as "(changed)".
-   **Privacy `ignore_logger_capabilities` bypass** is only reachable from WP's
    admin-gated, email-confirmed export/erase flow, scoped to the specific data
    subject — not user-facing.
-   **CSRF** — every state-changing non-REST handler verifies a nonce; import
    handlers, dismiss-notice AJAX, and settings save also check `manage_options`.
-   **stats / support-info / devtools** REST controllers all require
    `manage_options` (or dev-mode + `activate_plugins` + an allowlist).
-   **IP handling** — display off by default, anonymization on by default.

## Suggested regression tests

-   Assert an **Author** gets `403` from `/events` and `/search-options`, an
    **Editor/Admin** gets `200` (Finding 1).
-   Assert the **User Card** endpoint omits `user_email`/`user_login`/`roles` for a
    caller without `list_users` (Finding 2).
-   Assert `user_can_clear_log()` is `false` for a non-`manage_options` user
    (Finding 4).
