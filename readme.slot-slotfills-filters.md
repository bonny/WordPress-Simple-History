# Slots and slotfills in Simple History

This document describres how slots and slotfills work in Simple History and add-on.

The code contains several slots and each slot have a slotfill in an empty component.
This empty component is then wrapped in a HOC that uses the `withFilters` HOC to add filters to the component.
Then other code can use the `addFilter` function to add filters to the slotfill.

(I would like to to use fills directly without the need for the empty component and the HOC, but I have not managed to do that yet.)

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
 * This will no be rendered/called if there is a filter in use,
 * unless the filter calls the component.
 *
 * @param {Object} props
 */
const EmptySettingsFilterComponent = ( props ) => {
	return <></>;
};

// Based on solution here:
// https://nickdiego.com/a-primer-on-wordpress-slotfill-technology/
// Filter can only be called one time.
const EventsControlBarSlotfillsFilter = withFilters(
	'SimpleHistory.FilteredComponent'
)( EmptySettingsFilterComponent );
```

Then in the premium add-on a component with a slot is used like this:

```jsx
addFilter(
	'SimpleHistory.FilteredComponent',
	'SimpleHistoryPremium',
	premiumMenuActions
);

/**
 * Add premium features to the control bar events menu.
 *
 * @param {Function} FilteredComponent
 */
function premiumMenuActions( FilteredComponent ) {
	return ( props ) => {
		return (
			<>
				{ /* Return the filtered component so the filter can be used multiple times. */ }
				<FilteredComponent { ...props } />

				{ /* Props (localProps) here comes from fillProps in the <Slot /> component. */ }
				<Fill name="SimpleHistorySlotEventsControlBarMenu">
					{ ( localProps ) => {
						const { propOne } = localProps;

						return (
							<>
								<p>Hello from the premiumMenuActions filter!</p>
								<p>propOne: { propOne }</p>
							</>
						);
					} }
				</Fill>
			</>
		);
	};
}
```
