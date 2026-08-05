# Stealth Mode Header Indicator — Design Spec

**Date:** 2026-08-05  
**Status:** Approved

---

## Overview

When Stealth Mode is on, Simple History hides itself from users whose email address is not on an allow list. It does its job well enough that the person who enabled it forgets — and later cannot explain why a colleague, often another administrator, sees no Simple History at all.

Add a persistent indicator to the Simple History page header, shown only while email-based Stealth Mode is active, that names the state and its consequence.

---

## 1. Why not the existing discovery bar

The header already has a status bar (`Status_Box_Service`), and it is the obvious place to put this. It is the wrong place.

That bar is a feature-discovery aid that deletes itself once the features it advertises are configured (`inc/services/class-status-box-service.php:29`):

```php
// This is a feature-discovery aid, not a permanent status readout.
// Once the discoverable features are configured it has done its job,
// so hide it rather than let it become permanent header chrome.
if ( $this->all_discoverable_features_configured() ) {
    return;
}
```

An indicator placed there disappears once email reports, log forwarding and alerts are all configured — that is, on mature installs, run by experienced admins, who are the people most likely to have had Stealth Mode on for months and forgotten. The indicator would vanish exactly when it is most needed.

The settings gear and the premium marker are rendered independently of that bar and do persist (`inc/class-helpers.php:1140`). This indicator belongs with them.

---

## 2. Placement

A new element in the header's right-hand zone, beside the existing premium marker echoed at `inc/services/class-admin-pages.php:193`.

```
┌────────────────────────────────────────────────────────────────┐
│ (>) Simple History │ ⚙ Settings                                │
│  ⏱ History kept: 125 days   ✉ Email reports: weekly            │
│                        👁 Stealth mode   ★ Premium active      │
└────────────────────────────────────────────────────────────────┘
```

It renders independently of the discovery bar, so it survives that bar self-hiding.

---

## 3. What it says

**Label:** `Stealth mode — hidden from others`

**Tooltip:** "Simple History is hidden from everyone whose email address isn't on the allow list — including other administrators."

**No count.** An earlier draft read "Stealth mode: 4 allowed". The allow list mixes exact addresses with `@domain` wildcards (`Stealth_Mode::is_user_email_allowed_in_stealth_mode()`, `inc/services/class-stealth-mode.php:122`), so a list like

```
@simple-history.com
@localhost
par.thernstrom@gmail.com
par@earthpeople.se
```

is two individuals plus two entire domains — potentially hundreds of people. "4 allowed" reads as four people and is actively misleading. The list is also assembled from a constant _plus_ the `simple_history/stealth_mode_allowed_emails` filter, so third-party code contributes entries and the number is not derivable from the stored option alone.

The consequence is the part people forget. State it, and leave the membership question to the settings page.

---

## 4. Where the code lives: core, not premium

Stealth Mode's mechanism is already core — `Stealth_Mode` (`inc/services/class-stealth-mode.php`) owns `is_stealth_mode_enabled()`, `is_full_stealth_mode_enabled()`, `get_allowed_email_addresses()` and `is_gui_visible_to_user()`. The premium add-on supplies only the settings UI and the allow-list entries, via the `simple_history/stealth_mode_allowed_emails` filter.

So the indicator goes in core. It then also works on installs that enable Stealth Mode through the `SIMPLE_HISTORY_STEALTH_MODE_ALLOWED_EMAILS` constant with no premium plugin present.

---

## 5. Render conditions

The indicator renders when **all** of these hold:

1. `Stealth_Mode::is_stealth_mode_enabled()` is true.
2. `Stealth_Mode::is_full_stealth_mode_enabled()` is false.
3. `current_user_can( Helpers::get_view_settings_capability() )`.

**On condition 2** — full Stealth Mode hides the GUI from everyone (`is_gui_visible_to_user()` returns false unconditionally, line 154), so no header is rendered to anyone and there is nobody to inform. Checking it explicitly keeps the intent legible rather than relying on the page never rendering.

**On condition 3** — this is a settings-state readout that links to settings, so it uses the same gate as the settings gear. The two agree by construction: a user who cannot see the gear does not see the indicator either. Under partial Stealth Mode an allow-listed editor can reach the log; configuration state is not their concern.

---

## 6. Link target

-   **Premium active:** links to the premium settings section that hosts the Stealth Mode field (`shp-misc-settings-section`, under Settings → Premium Settings).
-   **Premium not active:** renders as plain text with no link. Stealth Mode is set by constant or filter in that case, and there is no settings screen to point at.

---

## 7. Components

| Unit                                      | Responsibility                                                               | Depends on                                                                                       |
| ----------------------------------------- | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| `Helpers::get_header_stealth_indicator()` | Returns the indicator markup, or `''` when the render conditions are not met | `Stealth_Mode`, `Helpers::get_view_settings_capability()`, `Helpers::is_premium_add_on_active()` |
| `Admin_Pages::header_output()`            | Echoes it beside the premium marker                                          | the helper above                                                                                 |

This mirrors `get_header_premium_link()` exactly: a static helper returning a string, echoed by the header. Returning `''` rather than printing directly keeps the "should this show at all" decision testable without output buffering, and matches the pattern used by the settings-link gate.

No new CSS zone is needed — the element sits in the existing right-hand flex row and reuses the `sh-PageHeader-headerBtn` styling with its own modifier class.

---

## 8. Testing

| Test                                                           | Asserts                             |
| -------------------------------------------------------------- | ----------------------------------- |
| Renders under partial stealth for a settings-capable user      | markup contains the indicator class |
| Absent when Stealth Mode is off                                | returns `''`                        |
| Absent under full Stealth Mode                                 | returns `''`                        |
| Absent for a user below the settings capability                | returns `''`                        |
| Links to settings when premium is active, plain text otherwise | presence/absence of `<a href>`      |

Stealth Mode state is set in tests through the `simple_history/full_stealth_mode_enabled` and `simple_history/stealth_mode_allowed_emails` filters, so no options need writing and no premium plugin is required.

Each test must fail without the corresponding branch — verify by reverting the condition, not by assuming.

---

## 9. Out of scope

**Empty allow list lockout.** Enabling Stealth Mode with an empty allow list makes `is_gui_visible_to_user()` return false for everyone, including whoever just enabled it, recoverable only through WP-CLI or a filter. This indicator makes the state legible but does not prevent the lockout. Worth its own issue.

**Changelog.** This is a user-facing addition and needs an `Added` entry when implemented, via the `changelog` skill.
