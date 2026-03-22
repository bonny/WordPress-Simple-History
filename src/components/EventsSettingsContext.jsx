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
		throw new Error(
			'useEventsSettings must be used within an EventsSettingsProvider'
		);
	}
	return context;
}
