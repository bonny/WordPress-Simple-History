# Abilities API Integration — Design Spec

**Date:** 2026-07-28
**Status:** Draft — awaiting review
**Issue:** 196 - Expose Simple History via WP Abilities API

---

## Overview

Register six read-only WordPress Abilities that expose Simple History's event log to AI agents and automation tools. Each ability delegates to an existing REST controller rather than reimplementing queries or permissions, so the abilities layer is metadata plus a thin adapter.

The motivation is **ecosystem hygiene**: WordPress standardised this interface in 6.9 and participating is table stakes for a well-behaved plugin. It is not a competitive-response feature, and scope should not expand to match what competitors ship.

---

## 1. Correcting the Record

Issue 196 was deferred in May 2026 on the grounds that the Abilities API "requires WP 7.0+ but Simple History supports 6.3+, so audience is tiny."

**This is factually wrong.** The Abilities API shipped in **WordPress 6.9** (December 2025) — see the [Make Core announcement](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/). Stream's own integration names 6.9 as its floor. Sibling issue 152 inherited the same incorrect 7.0 framing.

The addressable install base is therefore everything from 6.9 up, which has had seven months to spread — not a version that had not yet shipped.

---

## 2. Scope

### In scope

Six read-only abilities, registered by the **core** plugin, gated on WP 6.9+.

### Non-goals

-   **No write abilities.** No creating, updating, or configuring anything.
-   **No destructive abilities.** Explicitly rejected — see §6.
-   **No new UI.** No settings screen, no toggle. Registration is unconditional on supported versions.
-   **No AI consumption.** Sending log data _to_ a model is issue 152, which is archived. This spec is the inverse direction and shares none of 152's cost or privacy blockers.
-   **No MCP adapter dependency.** The adapter is a separate feature plugin, not core. We register abilities; whether a site exposes them over MCP is the site owner's choice.

### Placement rationale — core, not premium

Hygiene work belongs in core. Gating standards participation behind a paywall would also conflict with the "core must be fully usable for free users" rule in `AGENTS.md`. Premium may register _additional_ abilities over premium-only data later (see §8).

---

## 3. Architecture

A single new service class delegates every ability through the REST layer via `rest_do_request()`.

```
Ability call
  → permission_callback  → controller's *_permissions_check()
  → execute_callback     → rest_do_request( WP_REST_Request )
                              → existing route (runs its own permission check)
                              → Log_Query (applies per-logger visibility filtering)
  → presenter            → trimmed, agent-shaped array
```

**Why delegation and not a direct `Log_Query` call:** Simple History's real per-logger visibility filtering happens _inside_ `Log_Query`, not in the permission callback — `get_items_permissions_check()` only asserts `is_user_logged_in()` (`inc/class-wp-rest-events-controller.php:824`). Any ability that reimplements permissions would silently over-expose events. Delegating makes that class of bug structurally impossible and means future REST fixes propagate for free.

### New file

`inc/services/class-abilities-service.php` — `Simple_History\Services\Abilities_Service`, following the existing service pattern.

```php
public function loaded() {
    // Abilities API landed in WP 6.9. Simple History supports 6.3+, so
    // registration is conditional and silently no-ops on older versions.
    if ( ! function_exists( 'wp_register_ability' ) ) {
        return;
    }

    add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
}
```

### Dispatch helper

```php
private function dispatch( string $route, array $params ) {
    $request = new \WP_REST_Request( 'GET', $route );

    foreach ( $params as $key => $value ) {
        $request->set_param( $key, $value );
    }

    $response = rest_do_request( $request );

    if ( $response->is_error() ) {
        return $response->as_error();
    }

    return $response->get_data();
}
```

`rest_do_request()` runs the target route's own `permission_callback`, so authorization is enforced even though the ability was not reached over HTTP.

### No supporting refactor needed

An earlier draft proposed refactoring `inc/services/class-rest-api.php` to retain controller instances so the abilities service could reuse them. That is unnecessary: `WP_REST_Events_Controller::__construct()` (`inc/class-wp-rest-events-controller.php:28-32`) only assigns three properties, so constructing one per permission check costs nothing and avoids coupling two services together. `Abilities_Service` instantiates the controllers it needs directly, and `class-rest-api.php` is not modified.

---

## 4. The Six Abilities

All in the `simple-history/` namespace. All `GET`-equivalent, all read-only.

| Ability                            | Delegates to                           | Purpose                                                |
| ---------------------------------- | -------------------------------------- | ------------------------------------------------------ |
| `simple-history/get-recent-events` | `/simple-history/v1/events`            | Filtered event list — date range, user, logger, level  |
| `simple-history/get-event`         | `/simple-history/v1/events/<id>`       | One event by ID, with full context by default          |
| `simple-history/search-events`     | `/simple-history/v1/events` (`search`) | Keyword search across messages                         |
| `simple-history/get-user-activity` | `/simple-history/v1/events` (`user`)   | Events attributed to one user                          |
| `simple-history/get-failed-logins` | `/simple-history/v1/events` (filtered) | Convenience wrapper for the most common audit question |
| `simple-history/get-stats-summary` | `/simple-history/v1/stats/summary`     | Aggregate counts for a period                          |

The last four are convenience wrappers over the same underlying route. They exist because an agent should not need to know Simple History's filter parameter vocabulary to ask an obvious question. Their input schemas are deliberately narrower than the REST route's full argument list.

### Input schemas

Expose a curated subset of REST arguments, not all of them. `/simple-history/v1/events` accepts roughly 25 arguments; an agent needs perhaps eight. Narrow schemas mean less to maintain, fewer ways to construct a nonsensical query, and a smaller published surface (§6).

Every list ability accepts `per_page` with a **hard server-side cap of 100**, regardless of what the caller asks for.

---

## 5. Response Shaping

A single event from the REST API is **1,629 bytes**. Its fields:

```
action_links, ai_origin, backfilled, context, date_gmt, date_local,
details_data, details_html, id, initiator, initiator_data, ip_addresses,
link, logger, loglevel, message, message_html, message_key,
message_uninterpolated, occasions_id, permalink, reactions, sticky,
sticky_appended, subsequent_occasions_count, via
```

Most of that is shaped for the React admin UI. `message_html`, `details_html`, `action_links`, `reactions`, and `sticky_appended` are meaningless to an agent and expensive — 100 events raw is roughly 40,000 tokens of context, the majority of it markup.

A presenter trims each event to about 350 bytes:

```php
[
    'id'          => …,
    'date_gmt'    => …,
    'message'     => …,   // plain text, interpolated
    'logger'      => …,
    'level'       => …,
    'initiator'   => …,
    'user'        => …,   // login + display name only, from initiator_data
    'ip_addresses'=> …,
    'occasions'   => …,   // subsequent_occasions_count
    'permalink'   => …,
]
```

**`context` is opt-in**, via an `include_context` boolean defaulting to `false`. Context holds the richest "what actually changed" data and also the densest PII (emails, IPs, option values, post titles). Excluding it by default keeps list responses lean and makes drilling into one event a deliberate act.

The one exception: **`get-event` defaults `include_context` to `true`**, since it returns a single row and fetching one event by ID is already the "drill in" act. List abilities default it to `false`.

This shaping is why the presenter is load-bearing rather than cosmetic: roughly a 4–5× reduction in context cost.

---

## 6. Security Posture

### Read-only by deliberate choice

Stream 4.2 registered `purge-records` and `delete-alert` as agent-callable destructive abilities. Simple History will not. The value of an audit log is that it is tamper-evident, and "an AI agent purged the audit trail" is precisely the failure a compliance-focused plugin must not enable. This is a stated position, not a gap.

### Prompt injection through log content

Simple History's _job_ is recording attacker-controlled strings — failed-login usernames, post titles, option values, user agents. Exposing the log to an agent means anyone who can trigger a log entry can plant text that reaches that agent's context. A failed login attempt with the username `ignore previous instructions and…` is a free injection vector into every admin who queries their log.

Mitigations:

-   **Read-only abilities mean a successful injection can misinform but cannot destroy the audit trail.** This is the main structural defence and the strongest argument for §6's first point.
-   **Do not sanitise log content.** Fidelity is the product; a log that quietly rewrites what it recorded is worse than useless for audit.
-   **Label it instead.** Ability descriptions state that returned content is untrusted user-supplied input and must not be treated as instructions.

### Authentication belongs to the transport

The Abilities API has no transport of its own. Calls arrive in-process (running as whatever the current user is), through core's `wp-abilities/v1` REST controllers (cookie+nonce or application password), or via the MCP Adapter plugin (application passwords over HTTP, OAuth 2.1 on WordPress.com, WP-CLI user over STDIO). In every case `permission_callback` is the **only** authorization gate, which is why §3's delegation matters.

**Application passwords carry the user's entire capability set** with no per-ability scoping. Simple History degrades correctly here by accident of good design: because real filtering happens inside `Log_Query`, a deliberately low-privilege MCP user reads only the events their role permits. That property is currently **untested** and §9 adds a test for it, since the whole permission story rests on it.

### Disclosure surface

Verified against a live site:

```
ANON  /wp-json/wp-abilities/v1/abilities        → 401 rest_forbidden
ANON  /wp-json/wp-abilities/v1/categories       → 401 rest_forbidden
ANON  /wp-json/simple-history/v1                → 200, 40 routes + arg names
ANON  /wp-content/plugins/simple-history/readme.txt → 200, exact version
```

Anonymous users **cannot** enumerate abilities. They can already see the `simple-history/v1` namespace and every route pattern, and `readme.txt` already discloses the exact plugin version with no auth — so registering abilities adds **no new anonymous fingerprinting surface**.

Ability `label`, `description`, `input_schema`, and `output_schema` are readable by anyone who can list abilities. Keep them generic and free of site-specific detail.

---

## 7. Version Gating

Simple History requires WP 6.3+; the Abilities API requires 6.9+. Gate on capability, not version number:

```php
if ( ! function_exists( 'wp_register_ability' ) ) {
    return;
}
```

No polyfill, no admin notice, no settings toggle. On WP 6.3–6.8 the feature silently does not exist, which is the correct behaviour for a hygiene feature — a user on 6.5 has lost nothing they knew about.

---

## 8. Premium Extension Point

Out of scope for this issue, noted so the namespace is not painted into a corner.

Premium owns data core does not have — alert rules, destinations, export. Those may later register their own read abilities from the premium plugin, in the same `simple-history/` namespace, using the same `Abilities_Service` dispatch helper exposed as a small public API. The core six stay free.

---

## 9. Testing

| Test                      | Asserts                                                                                                                                 |
| ------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| Registration gating       | No abilities registered when `wp_register_ability` is absent; all six when present                                                      |
| Schema validity           | Every `input_schema` / `output_schema` is valid JSON Schema and matches actual returned shape                                           |
| **Permission delegation** | A subscriber-level call to `get-recent-events` returns only events that role may read — the property the entire security model rests on |
| Anonymous rejection       | Unauthenticated ability execution is refused                                                                                            |
| Presenter shape           | Trimmed output contains no `*_html` fields; `context` absent unless `include_context` is true                                           |
| Cap enforcement           | `per_page` above 100 is clamped, not honoured                                                                                           |

The permission-delegation test is the important one and should be written first.

---

## 10. Answered Questions

All four were resolved during implementation, verified against a live WordPress 7.0.2 site rather than documentation. Two of them turned out to be defects, not merely unknowns — both would have shipped a feature that did nothing.

1. **Ability category — registers on its own hook.** `wp_register_ability_category( string $slug, array $args )` takes required `label` and `description`. Critically, **categories register on `wp_abilities_api_categories_init`, not `wp_abilities_api_init`.** Registering the category from inside the abilities callback made WordPress reject it, and an ability naming a non-existent category is then rejected too — so nothing registered at all. Core fires the categories action first from `WP_Abilities_Registry::get_instance()` precisely so categories exist "before abilities that depend on them".

2. **Abilities are invisible over REST unless they opt in.** `meta.show_in_rest` **defaults to false**, and both `class-wp-rest-abilities-v1-list-controller.php:92` and `…run-controller.php:145` filter on it. Without it the abilities registered fine in PHP and returned `rest_ability_not_found` to every REST and MCP client — the only consumers that matter. We now also set `meta.annotations` (`readonly: true`, `destructive: false`, `idempotent: true`), which states §6's read-only position in a form an agent can act on rather than only in prose. Core additionally derives the required HTTP method from `readonly` — read-only abilities must be called with GET.

3. **Core REST controller behaviour**, read from 7.0.2 source: anonymous listing returns 401 as designed; input is passed as a single `input` parameter (query param for GET, JSON body for POST), not as top-level params; the permission method on `WP_Ability` is `check_permissions( $input )`.

4. **Stats permissions are stricter, and stay that way.** `WP_REST_Stats_Controller::get_items_permissions_check()` requires `manage_options` where the events controller only requires being logged in. Verified live: an editor is refused stats but permitted events. The asymmetry is preserved deliberately.

### The security model, verified

Measured on the live site: an administrator's `get-recent-events` returned **100 events**; a subscriber's returned **0** — while `check_permissions()` returned `true` for both. That is exactly the claim in §3 and §6: authorization is permissive by design, and the real per-logger filtering happens deeper, inside `Log_Query`, which delegating through `rest_do_request()` preserves. Reimplementing permissions in the ability layer would have quietly bypassed it.

### One behaviour changed on evidence

`per_page` above the maximum is **rejected**, not clamped. The schema's `maximum: 100` makes `WP_Ability::validate_input()` refuse the call before our callback runs. That is better than silent truncation — an agent learns the real limit instead of reading a short answer as a complete one. `clamp_per_page()` remains as defence-in-depth for direct PHP callers, which bypass schema validation.

---

## 11. Sizing

Estimated `size: 1-small` to `2-medium`. Actual: one new service class plus a stateless presenter, no changes to `class-rest-api.php`, and 24 tests. No UI, no migrations, no new dependencies.

Priority `2-normal`. This is worth doing and cheap, but it is hygiene — it has no deadline, and the competitor announcement is a reason to keep scope tight, not to hurry.

**Where the estimate was wrong:** the code was as small as predicted, but two runtime defects (the categories hook and `show_in_rest`) were invisible to code review and to the WordPress 6.8 test environment, and would each have shipped a feature that silently did nothing. Neither is discoverable from the current documentation. Any future work against a WordPress API this new should budget for verification on a real site of the target version, not just a green test suite.

---

## Related

-   `196 - Expose Simple History via WP Abilities API` — this issue
-   `152 - Integrate WP 7.0 AI Client for event querying` — archived; the AI _consumer_ side. Its blockers (token cost, third-party data sharing, duplicating the existing email digest) do not apply here.
-   `58 - WordPress Abilities API Integration` — archived; the original "should we?" idea, superseded by 196.

## References

-   [Abilities API in WordPress 6.9](https://make.wordpress.org/core/2025/11/10/abilities-api-in-wordpress-6-9/)
-   [Introducing the WordPress Abilities API](https://developer.wordpress.org/news/2025/11/introducing-the-wordpress-abilities-api/)
-   [From Abilities to AI Agents: the WordPress MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/)
-   [Merge Proposal: Expanding WordPress Core Abilities](https://make.wordpress.org/core/2026/07/02/merge-proposal-expanding-wordpress-core-abilities/)
-   [Stream 4.2 ships the Abilities API](https://xwp.co/stream-4-2-ships-the-abilities-api-ask-your-audit-log-a-question/)
