// Entrypoint used by wp-scripts start and build.
import { SlotFillProvider, withFilters } from '@wordpress/components';
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import EventsGUI from './components/EventsGui';
import { DashboardEventsWidget } from './components/DashboardEventsWidget';
import { EmptyFilteredComponent } from './EmptyFilteredComponent';
import { NuqsAdapter } from 'nuqs/adapters/react';
import { PremiumFeaturesModalProvider } from './components/PremiumFeaturesModalContext';

// Filter that can be used by other plugins as a gateway to add content to different areas of
// the core plugin, using slots.
// Based on solution here:
// https://nickdiego.com/a-primer-on-wordpress-slotfill-technology/
// Filter can only be called called multiple times, but make sure to add
// `<FilteredComponent { ...props } />` in each call, so all calls "stack up".
const EventsControlBarSlotfillsFilter = withFilters(
	'SimpleHistory.FilteredComponent'
)( EmptyFilteredComponent );

const isDashboard = window.pagenow === 'dashboard';

domReady( () => {
	const target = document.getElementById( 'simple-history-react-root' );

	if ( ! target ) {
		return;
	}

	if ( ! createRoot ) {
		return;
	}

	// On the dashboard, render the simplified widget.
	// On the full admin page, render the full GUI with filters and controls.
	if ( isDashboard ) {
		createRoot( target ).render( <DashboardEventsWidget /> );
	} else {
		createRoot( target ).render(
			<NuqsAdapter>
				<PremiumFeaturesModalProvider>
					<SlotFillProvider>
						<EventsControlBarSlotfillsFilter />
						<EventsGUI />
					</SlotFillProvider>
				</PremiumFeaturesModalProvider>
			</NuqsAdapter>
		);
	}
} );
