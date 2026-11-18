# Slots and slotfills in Simple History

This document describes how slots and slotfills work in Simple History and its add-ons, and lists the JavaScript filters (`applyFilters`) that add-ons can hook into.

The code contains several slots. Add-ons fill them through one shared empty component.
This empty component (`EmptyFilteredComponent` in `src/EmptyFilteredComponent.js`) is wrapped with the `withFilters` HOC from `@wordpress/components`, and rendered once inside the `SlotFillProvider` in `src/index.js`.
Other code then uses `addFilter` on that filter to render its own `<Fill>` components.

(I would like to use fills directly without the need for the empty component and the HOC, but I have not managed to do that yet.)

The slots only exist on the main history page. The dashboard widget (`DashboardEventsWidget`) is rendered without a `SlotFillProvider`, so fills do not show up there.

## Available slots in core plugin

### Control bar buttons

Inline buttons in the control bar above the events list. Premium fills it with the real Export, Create Alert and Create Log Entry buttons. File: `src/components/EventsControlBar.jsx`.

```jsx
<Slot
	name="SimpleHistorySlotControlBarButtons"
	fillProps={ {
		eventsQueryParams,
		eventsTotal,
		hasAnyActiveFilters,
		alertsPageURL,
		userCanManageOptions,
		eventsView,
	} }
/>
```

`eventsView` is the view on screen: `'detailed'`, `'compact'` or `'table'`. A fill can use it to hide a button in the table view, which has its own export. It was added later, so older fills read it as `undefined`.

### Event control bar menu

The older extension point for control bar actions, used by premium versions from before the inline buttons. File: `src/components/EventsControlBarOverflowMenu.jsx`.

```jsx
<Slot
	name="SimpleHistorySlotEventsControlBarMenu"
	fillProps={ {
		onClose,
		eventsQueryParams,
		eventsTotal,
	} }
/>
```

The slot sits inside a "More actions" dropdown that only renders when an old premium is detected: `SimpleHistory.showPremiumAddonsMenuGroup` returns `false` and `SimpleHistory.EventsControlBar.showOverflowMenu` still returns its default `true`. New premium returns `false` from `showOverflowMenu`, so the dropdown is not shown.

`src/components/EventsControlBarActionsDropdownMenu.jsx` also renders a slot with this name, but nothing imports that component any more.

### Event actions menu

The actions dropdown on each event. File: `src/components/EventActionsButton.jsx`.

```jsx
<Slot
	name="SimpleHistorySlotEventActionsMenu"
	fillProps={ {
		onClose,
		event,
		eventVariant,
		userCanManageOptions,
	} }
/>
```

The actions button is not rendered for events with `eventVariant` `'modal'` or `'dashboard'`, so the slot is not either.

### Table view

The body of the table view. File: `src/components/EventsGui.jsx`.

```jsx
<Slot
	name="SimpleHistorySlotTableView"
	fillProps={ {
		eventsQueryParams,
		eventsTotal,
		hasAnyActiveFilters,
		eventsIsLoading,
		eventsReloadTime,
	} }
>
	{ ( fills ) => ( fills.length > 0 ? fills : <TablePreview /> ) }
</Slot>
```

Premium fills it with the real table. With no fill (no premium, or a premium too old to know the slot) core renders `TablePreview`, a teaser, so the view is never blank. `eventsTotal` here is `eventsMeta.total`.

## Available filters in core plugin

These use `applyFilters` from `@wordpress/hooks`. Hook into them with `addFilter`.

### Events page

| Filter | Default | Arguments | File | What it does |
| ------ | ------- | --------- | ---- | ------------ |
| `SimpleHistory.FilteredComponent` | `EmptyFilteredComponent` | the component (via `withFilters`) | `src/index.js` | Entry point for rendering fills, see the example below. |
| `SimpleHistory.EventsControlBar.showPromoButtons` | `true` | none | `src/components/EventsControlBar.jsx` | Return `false` to hide the promo Export, Create Alert and Create Log Entry buttons. New premium does this and renders its own buttons in `SimpleHistorySlotControlBarButtons`. |
| `SimpleHistory.showPremiumAddonsMenuGroup` | `true` | none | `src/components/EventsControlBar.jsx`, `src/components/EventsControlBarOverflowMenu.jsx`, `src/components/PremiumAddonsPromoMenuGroup.jsx` | Old premium returns `false`. Core reads that as "premium is active": it hides the promo buttons and, together with `showOverflowMenu`, decides whether to show the legacy overflow menu. |
| `SimpleHistory.EventsControlBar.showOverflowMenu` | `true` | none | `src/components/EventsControlBarOverflowMenu.jsx` | New premium returns `false` to hide the legacy "More actions" dropdown. |
| `SimpleHistory.reactions.types` | the `REACTIONS` array | none | `src/components/EventReactions.jsx` | The list of reaction types. Each item has `type`, `emoji`, `label` and `premium` (bool). It runs once when the module loads, so the filter must be added before core's script runs. |

### Admin bar quick view

These run in `src/components/AdminBarQuickView.jsx`. Note the lowercase `simpleHistory` prefix.

| Filter | Default | Context argument | What it does |
| ------ | ------- | ---------------- | ------------ |
| `simpleHistory.adminBar.showThisPageTeaser` | `true` when in "This page" mode with a post ID, otherwise `false` | `{ currentPostId, currentPostTitle }` | Premium returns `false` to show the post's events instead of the teaser. |
| `simpleHistory.adminBar.viewFullHistoryUrl` | the history page URL | `{ filterMode, currentPostId, currentPostTitle }` | The "View full history" link. Premium adds context filters to it. |
| `simpleHistory.adminBar.eventsQueryParams` | `{ per_page: 5, dates: 'lastdays:7' }` | `{ filterMode, currentPostId }` | Query parameters sent to `/simple-history/v1/events`. |
| `simpleHistory.adminBar.infoText` | "Events from the last 7 days" | `{ filterMode, currentPostId, currentPostTitle }` | The info text shown with the list. |

## Example

This slot called `SimpleHistorySlotEventsControlBarMenu` is added in the core plugin:

```jsx
<Slot
	name="SimpleHistorySlotEventsControlBarMenu"
	fillProps={ {
		onClose,
		eventsQueryParams,
		eventsTotal,
	} }
/>
```

By default nothing consumes this slot, so it's empty.

There is however an empty component that is used in a withFilters HOC that adds a filter to the slotfill:

```jsx
/**
 * This will not be rendered/called if there is no filter in use,
 * unless the filter calls the component.
 *
 * @param {Object} props
 */
const EmptyFilteredComponent = ( props ) => {
	return <></>;
};

// Based on solution here:
// https://nickdiego.com/a-primer-on-wordpress-slotfill-technology/
// The filter can be added multiple times, but each callback must render
// `<FilteredComponent { ...props } />` so all of them stack up.
const EventsControlBarSlotfillsFilter = withFilters(
	'SimpleHistory.FilteredComponent'
)( EmptyFilteredComponent );
```

Then in the premium add-on a component with a fill is used like this:

```jsx
addFilter(
	'SimpleHistory.FilteredComponent',
	'SimpleHistoryPremium',
	premiumMenuActions
);

/**
 * Add premium features to the control bar events menu.
 * Only use slots because the filtered component is placed in the root.
 *
 * @param {Function} FilteredComponent
 */
function premiumMenuActions( FilteredComponent ) {
	return ( props ) => {
		return (
			<>
				{ /* Return the filtered component so the filter can be used multiple times. */ }
				<FilteredComponent { ...props } />

				{ /* Props (localProps) here come from fillProps in the <Slot /> component. */ }
				<Fill name="SimpleHistorySlotEventsControlBarMenu">
					{ ( localProps ) => {
						const { eventsTotal } = localProps;

						return (
							<>
								<p>Hello from the premiumMenuActions filter!</p>
								<p>eventsTotal: { eventsTotal }</p>
							</>
						);
					} }
				</Fill>
			</>
		);
	};
}
```
