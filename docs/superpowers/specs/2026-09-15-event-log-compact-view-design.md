# Event log Compact view — design

Date: 2026-09-15
Issue: 240 - Event log views — list, compact, table, calendar (phase 1)
Branch: `issue-240-event-log-views`

## Goal

Let users switch the main event log between the current **Detailed** view and a denser **Compact** view, and remember the choice per user.

## Scope

In:

-   A two-state Detailed | Compact toggle on the event log page, behind the experimental features flag (added 2026-09-16). With the flag off the toggle is not rendered and the log is always detailed, so a stored preference or a `?view=compact` link from when the flag was on does not keep the feature alive.
-   A Compact rendering of events on the main log.
-   Per-user persistence plus a `?view=` URL parameter.

Out, decided during brainstorming:

-   **No generic view registry or premium extension point.** Table (premium) and Calendar (premium, deferred) add their own buttons when they exist.
-   **No Table teaser button.** It would promote something that cannot be bought yet.
-   **No one-line, column-based rows.** That layout belongs to the premium Table view.
-   **The dashboard widget is not changed.**

## Decisions

| Question           | Decision                                                                              |
| ------------------ | ------------------------------------------------------------------------------------- |
| What Compact shows | Dashboard-widget density, but keeps the ⋯ actions menu and action links ("row A")     |
| Toggle placement   | Right end of the control bar, after Share, icon-only segmented toggle ("placement A") |
| Persistence        | User meta, overridden by `?view=` in the URL                                          |
| Implementation     | New `variant="compact"` on `Event`, separate from `variant="dashboard"`               |

`variant="dashboard"` was rejected for the main log because it hides the ⋯ menu (details modal, copy link, stick, hide event type, reactions), links the date to the events page instead of opening the modal, and couples any later widget tweak to the Compact view.

## Components

### `EventsViewToggle` (new, `src/components/EventsViewToggle.jsx`)

-   Props: `view` (`'detailed' | 'compact'`), `onChange( view )`.
-   Markup: `<fieldset>` with a visually hidden `<legend>` ("Event list view") and two visually hidden `<input type="radio" name="sh-events-view">` elements, each wrapped in a `<label>` that renders an icon. Native radios give arrow-key navigation and screen-reader grouping.
-   Accessible names: "Detailed view" and "Compact view", also shown as tooltips.
-   Icons from `@wordpress/icons` (bundled, so no WP 6.3 concern).
-   Checked state: dark background matching the WordPress segmented control; focus ring via `:focus-visible` on the input's label.
-   No hover lifts, shadows or transitions.

### `EventsControlBar`

-   New props `eventsView` and `onEventsViewChange`.
-   Renders a thin divider and `EventsViewToggle` as the last item in the right-hand `HStack`, after `ShareFilteredViewButton`.
-   The control bar is already hidden in surrounding-events mode, so the toggle is too.

### `EventsGui`

```js
const [ urlView, setUrlView ] = useQueryState(
	'view',
	parseAsStringLiteral( [ 'detailed', 'compact' ] ).withOptions(
		useQueryStateOptions
	)
);

const [ storedView, setStoredView ] = useState(
	window.simpleHistoryReactData?.eventsView === 'compact'
		? 'compact'
		: 'detailed'
);

const eventsView = urlView ?? storedView;
```

On toggle:

1. `setUrlView( newView )` — so `ShareFilteredViewButton` copies a link that opens in the same view.
2. `setStoredView( newView )`.
3. Save in the background (see Persistence). No await, no UI on failure.

On page load nothing is written to the URL.

`parseAsStringLiteral` is available in the installed nuqs (2.4.3).

`eventsView` is passed to `EventsControlBar` and to `EventsList` → `EventsListItemsList`, which renders `<Event variant={ eventsView === 'compact' ? 'compact' : 'normal' } />`. `EventsListSkeletonList` is unchanged.

Changing the view does not refetch events: the view is not part of `eventsQueryParams`.

### `Event` and children, `variant="compact"`

| Component            | Compact behaviour                                                                                                                                                                        |
| -------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Event`              | Hides `EventDetails` (condition becomes `variant !== 'dashboard' && variant !== 'compact'`). Everything else renders.                                                                    |
| `EventInitiatorName` | Replace the existing unused `compact` branch: name in `<strong>` wrapped in `UserCard`, no email.                                                                                        |
| `EventDate`          | Replace the existing unused `compact` branch: relative time only, as a link-variant `Button` that opens the event modal (same handler as the normal branch), full date in the `Tooltip`. |
| `EventIPAddresses`   | No "IP address:" label, same as `dashboard`.                                                                                                                                             |
| `EventActionsButton` | Unchanged — shown, because it only hides for `modal` and `dashboard`.                                                                                                                    |
| `EventOccasionsList` | Unchanged — passes the variant on, so similar events render compact too. Normal-variant margins are kept.                                                                                |
| `EventSeparator`     | Unchanged — the pill label stays on the main log.                                                                                                                                        |

Before replacing the `compact` branches, confirm with a search that no caller passes `eventVariant="compact"` or `variant="compact"` today (brainstorming found none; the admin bar uses `EventDateCompact` and `EventInitiatorNameCompact`).

### CSS (`css/styles.css`)

New rules scoped to `.SimpleHistoryLogitem--variant-compact`, modelled on the existing `.is-dashboard` and `.postbox` rules but not sharing selectors with them:

-   Vertical padding `8px`, horizontal padding unchanged.
-   Avatar `24px`, initiator icon font-size `24px`, second column `margin-left: 40px`.
-   Tighter action links: small gap and top margin.
-   Reduced extra padding before a labelled date separator.

Toggle styles under `.sh-EventsViewToggle`.

## Persistence

### PHP

Register the user meta in the existing `REST_API` service (`inc/services/class-rest-api.php`), in a new `register_user_meta()` method hooked to `init` — not `rest_api_init`, so the registered default also applies when the React dropin reads the value on the admin page:

```php
register_meta(
	'user',
	'simple_history_events_view',
	[
		'type'              => 'string',
		'single'            => true,
		'default'           => 'detailed',
		'sanitize_callback' => function ( $value ) {
			return $value === 'compact' ? 'compact' : 'detailed';
		},
	]
);
```

No `show_in_rest` and no `auth_callback`: the meta is not exposed through `/wp/v2/users` at all. It's saved through a dedicated route instead (see below), so there is nothing for the core users endpoint to read or write.

The dedicated route, registered in the same service's `register_routes()`:

```php
register_rest_route(
	'simple-history/v1',
	'/events-view',
	[
		'methods'             => WP_REST_Server::EDITABLE,
		'callback'            => [ $this, 'save_events_view' ],
		'permission_callback' => [ $this, 'events_view_permissions_check' ],
		'args'                => [
			'view' => [
				'type'     => 'string',
				'required' => true,
				'enum'     => [ 'detailed', 'compact' ],
			],
		],
	]
);
```

`events_view_permissions_check()` requires `Helpers::get_view_history_capability()` — the same capability the history page itself requires. `save_events_view()` writes `update_user_meta( get_current_user_id(), ..., $view )` for the current user only; there's no user-id parameter, so it can never touch another user's meta.

In `dropins/class-react-dropin.php`, add to the existing `simpleHistoryReactData` localize call:

```php
'eventsView' => get_user_meta( get_current_user_id(), 'simple_history_events_view', true ) === 'compact' ? 'compact' : 'detailed',
```

### JS save

```js
apiFetch( {
	path: '/simple-history/v1/events-view',
	method: 'POST',
	data: { view: newView },
} ).catch( () => {} );
```

A dedicated route, not the core `/wp/v2/users/me` endpoint. That endpoint always calls `wp_update_user()`, which fires `profile_update` on every save. Third-party plugins act on that hook — on the local dev site, the Stream activity logger logged "claude's profile was updated" on every toggle, Rank Math bumped the author sitemap's lastmod, and Jetpack queued a user sync. None of that is true: switching the event log view is not profile activity. The dedicated route only ever runs `update_user_meta()`, so none of those side effects fire.

## Error handling

-   Save fails (network, permissions, REST disabled): silently ignored. The toggle keeps working for the visit; the next visit falls back to the last saved value.
-   Unknown `?view=` value: the parser ignores it, so the stored view is used.
-   `simpleHistoryReactData` missing: falls back to `detailed`.

## Testing

Playwright, `tests/playwright/events-view-toggle.spec.js`:

1. Default is Detailed; the details table is visible.
2. Click Compact: details table gone, ⋯ actions button still present, URL contains `view=compact`.
3. Reload the page without the parameter: still Compact (persisted).
4. Open with `?view=detailed`: Detailed, despite the stored Compact.
5. Keyboard: focus the toggle, press an arrow key, view changes.
6. Clean up: reset the stored preference to `detailed`.

wpunit, `tests/wpunit/EventsViewUserMetaTest.php`:

-   The meta is registered (single, default `detailed`), not exposed via `show_in_rest`, and wired on `init`.
-   A user with the view-history capability can save `compact` through `POST /simple-history/v1/events-view`.
-   An invalid value is rejected (400), a user without the capability is forbidden (403), a logged-out request is unauthorized (401) — none of these change the stored meta.
-   Saving does not fire `profile_update`.
-   `GET /wp/v2/users/<id>` does not expose the meta.

Manual, on the local WP 7.1 site:

-   Compact at desktop width and in a narrow window (control bar wrapping).
-   Dashboard widget unchanged.
-   Similar events expanded inside Compact.
-   No "profile updated" event logged by toggling.

Checks: `npm run build`, phpcs, `./vendor/bin/phpstan analyse --memory-limit=2G` with no path argument.

## Changelog

`readme.txt` Unreleased, Added: a Compact view for the event log, remembered per user.
