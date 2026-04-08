import { createContext, useContext } from '@wordpress/element';

const EventsSettingsContext = createContext( null );

export function EventsSettingsProvider( { children, value } ) {
	return (
		<EventsSettingsContext.Provider value={ value }>
			{ children }
		</EventsSettingsContext.Provider>
	);
}

export function useEventsSettings() {
	const context = useContext( EventsSettingsContext );
	if ( ! context ) {
		// Return safe defaults when used outside a provider (e.g. tests, Storybook).
		return {
			hasExtendedSettingsAddOn: false,
			hasPremiumAddOn: false,
			hasFailedLoginLimit: false,
			experimentalFeaturesEnabled: false,
			currentUserId: null,
		};
	}
	return context;
}
