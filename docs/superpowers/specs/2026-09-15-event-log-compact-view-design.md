# Event log Compact view — design

Date: 2026-09-15
Issue: 240 - Event log views — list, compact, table, calendar (phase 1)
Branch: `issue-240-event-log-views`

## Goal

Let users switch the main event log between the current **Detailed** view and a denser **Compact** view, and remember the choice per user.

## Scope

In:

-   A two-state Detailed | Compact toggle on the event log page.
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
		'type'          => 'string',
		'single'        => true,
		'default'       => 'detailed',
		'show_in_rest'  => [
			'schema' => [
				'type' => 'string',
				'enum' => [ 'detailed', 'compact' ],
			],
		],
		'auth_callback' => function ( $allowed, $meta_key, $object_id, $user_id ) {
			return (int) $object_id === (int) $user_id;
		},
	]
);
```

In `dropins/class-react-dropin.php`, add to the existing `simpleHistoryReactData` localize call:

```php
'eventsView' => get_user_meta( get_current_user_id(), 'simple_history_events_view', true ) === 'compact' ? 'compact' : 'detailed',
```

### JS save

```js
apiFetch( {
	path: '/wp/v2/users/me',
	method: 'POST',
	data: { meta: { simple_history_events_view: newView } },
} ).catch( () => {} );
```

Uses the core users endpoint instead of a new route. Any logged-in user may edit their own user object, and the `auth_callback` blocks writing another user's value. The meta is only a layout preference, so exposing it in REST responses carries no privacy cost.

The core endpoint always calls `wp_update_user()`, so `profile_update` fires. The User logger's `on_profile_update_commit()` bails when nothing in its tracked context changed, so no event is expected — verify it, because changing a view is not profile activity and must not be logged.

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

wpunit:

-   The meta is registered with `show_in_rest` and the enum.
-   A user can update their own `simple_history_events_view` through `/wp/v2/users/me`.
-   A user cannot update another user's value; an invalid value is rejected.

Manual, on the local WP 7.1 site:

-   Compact at desktop width and in a narrow window (control bar wrapping).
-   Dashboard widget unchanged.
-   Similar events expanded inside Compact.
-   No "profile updated" event logged by toggling.

Checks: `npm run build`, phpcs, `./vendor/bin/phpstan analyse --memory-limit=2G` with no path argument.

## Changelog

`readme.txt` Unreleased, Added: a Compact view for the event log, remembered per user.
