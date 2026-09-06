---
name: teaser-screenshots
description: Reproducibly capture in-product UI screenshots (admin popovers, settings teasers, dashboard widgets) used as embedded images in premium upsell teasers. Use whenever a teaser image needs refreshing after the underlying UI changes.
allowed-tools: Read, Bash, Edit, Write
disable-model-invocation: false
---

# In-product teaser screenshots

These screenshots are embedded _inside the free version's UI_ (e.g. the user card teaser block) to show "this is what the same surface looks like with premium." Real screenshots beat blurred placeholders for honesty, conversion, and WP.org compliance (see `freemium_upsell_strategy.md` §5).

## Captured screenshots

A single capture run produces four PNGs in `assets/images/`. The orchestrator (`scripts/capture-teaser-screenshots.sh`) toggles the premium plugin between the two passes and trap-restores it on exit.

| File                                    | Mode                | Variant                | Used in                                                                 |
| --------------------------------------- | ------------------- | ---------------------- | ----------------------------------------------------------------------- |
| `user-card-with-premium.png`            | premium active      | close-up               | `src/components/UserCard.jsx` — `PremiumTeaserBlurred` embedded preview |
| `user-card-with-premium-context.png`    | premium active      | with event-row context | Marketing / blog / docs                                                 |
| `user-card-without-premium.png`         | premium deactivated | close-up               | Marketing / docs ("what free users see")                                |
| `user-card-without-premium-context.png` | premium deactivated | with event-row context | Marketing / blog                                                        |

Add a row whenever a new in-product screenshot is wired up.

## Pre-flight

1. **Premium add-on must be installed** on the stable WP install (the orchestrator handles activate/deactivate itself, but the plugin files need to exist). Verify:

    ```bash
    cd /Users/bonny/Projects/_docker-compose-to-run-on-system-boot
    docker compose run --rm wpcli_mariadb plugin list | grep simple-history-premium
    ```

2. **At least one user in the visible log needs real IP/UA history.** On Pär's stable install this is user 1 (par) — that's the user the spec ends up clicking. If you wipe and reseed the log, use `wp simple-history dev populate` to repopulate.

3. **JS bundle must be current** if you changed the `PremiumTeaserBlurred` component — `npm run build` first so the free-mode capture sees the latest teaser markup.

4. **Auth cache:** delete `tests/playwright/.auth/admin.json` to force a fresh login (otherwise re-uses cached session). Usually fine to leave alone.

## Capture command

```bash
npm run screenshots:teaser-user-card
```

Runs in ~30 seconds (two Playwright passes + plugin toggles). For each mode the spec iterates the first ~15 user-card triggers on the events page, picks the first whose popover meets the mode-specific ready condition — premium: the `"From X · …"` meta line; free: the embedded `.sh-UserCard__teaserScreenshot` figure — then captures two PNGs:

-   a tight clip of just the popover
-   a wider clip that includes the event row the trigger anchors to (so the screenshot shows _where_ the popover lives on the page)

After capture, the orchestrator runs each PNG through `pngquant` (`--quality=80-95 --strip --skip-if-larger`) — typically shrinks files ~65–70% with no visible quality loss. If `pngquant` isn't installed (`brew install pngquant`), the step is skipped with a hint and the un-optimized PNGs still ship.

Run the orchestrator instead of invoking Playwright directly — the script handles the premium activate/deactivate dance, the optimization step, and trap-restores premium on exit even if a test fails. Manual single-mode runs are still possible via `SH_TEASER_MODE=premium|free playwright test tests/playwright/screenshot-teaser-user-card.spec.js --project=teaser` (but you'll need to run `pngquant` yourself afterwards).

## When to re-run

-   Any time the user card layout, styling, or copy changes — keep the embedded teaser in sync with reality
-   After updating premium card details (new field, removed field, reordered)
-   Before tagging a release that touches `src/components/UserCard.jsx` or the premium `User_Card_Module`
-   If a screenshot starts looking stale (old timestamps, mismatched palette)

## Adding a new in-product screenshot pair

1. **Write a Playwright spec** at `tests/playwright/screenshot-teaser-<name>.spec.js`. Pattern to follow: copy `screenshot-teaser-user-card.spec.js`. Key bits:
    - `test.use({ viewport, deviceScaleFactor: 2 })` for retina output
    - Use the cached admin session (default `chromium` project handles auth via `auth.setup.js`)
    - Read `SH_TEASER_MODE` (or whatever flag makes sense) to pick filenames
    - Wait for the target UI to be fully populated before clipping
    - Capture both a tight popover/element clip AND a wider context clip
2. **Add a bash orchestrator** at `scripts/capture-<name>-screenshots.sh` if the capture needs plugin/setting state toggled between runs. Copy `capture-teaser-screenshots.sh` for the activate/deactivate + trap pattern.
3. **Add an npm script** in `package.json`:
    ```json
    "screenshots:teaser-<name>": "bash scripts/capture-<name>-screenshots.sh"
    ```
4. **Add rows** to the table at the top of this file.
5. **Reference the image** from the relevant component using the established convention: `'/wp-content/plugins/simple-history/assets/images/<name>.png'`.

## Troubleshooting

-   **"No popover yielded premium meta data"** — premium is not active, or claude user has no events with rich data. Activate premium; verify with `curl /wp-json/simple-history/v1/users/1/card` (must show `has_premium_add_on: true` and a `last_session` detail).
-   **"No popover yielded a usable free popover"** — premium is _still active_ (deactivate failed) or the React bundle is out of date and the new `.sh-UserCard__teaserScreenshot` element doesn't render. Run `npm run build`, then verify by toggling premium manually and clicking a user avatar in the admin.
-   **Popover anchors at the top of the viewport** — the upward-anchored popover shifts to fit. The spec iterates triggers; usually a later trigger lands in the middle of the page with room above for the popover. If still problematic, scroll the page first.
-   **Wrong card content captured** — the spec clicks the _first_ trigger that yields a usable popover. If the wrong user's data ends up in the shot, narrow the ready locator (e.g. filter by `[data-user-id="1"]` or row text matching a specific user).
-   **Context shot doesn't include the event row** — the spec walks up from the trigger to the closest `.SimpleHistoryLogitem` ancestor. If the markup changes, update that selector or the fallback PAD-based clip kicks in (still useful, but loses the row context).

## Why on-stable, not Playground

Considered using `wp-playground` for full reproducibility (deterministic fixtures, no dependency on a running docker stack). Decided against for now because:

-   Stable docker is always running locally during dev work — fast feedback
-   Real Pär data on stable already produces a more authentic-looking teaser than synthetic fixtures
-   The screenshot doesn't need to be byte-identical between runs — just visually correct

If we later want byte-deterministic builds (e.g. CI-generated), the Playground path is a clean migration: same Playwright spec, swap the `baseURL`, add a blueprint that imports a known-state fixture, drop the WP-CLI plugin-toggle calls from the bash orchestrator (the blueprint can pre-set the desired state per run).
</content>
