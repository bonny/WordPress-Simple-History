import { __ } from '@wordpress/i18n';
import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';

/**
 * One compact event for the compact event list.
 * @param {Object} props
 * @return {Object} React element
 */
export const CompactEvent = ( props ) => {
	const { event, variant = 'default' } = props;

	if ( variant === 'sidebar' ) {
		// Simplified version for sidebar panels
		return (
			<li className="sh-GutenbergPanel-event">
				<div className="sh-GutenbergPanel-event__meta">
					<EventInitiatorName
						event={ event }
						eventVariant="compact"
					/>
					<EventDate event={ event } eventVariant="compact" />
				</div>
				<div className="sh-GutenbergPanel-event__message">
					{ event.message }
				</div>
			</li>
		);
	}

	// Default version for admin bar (original implementation)
	return (
		<li role="group" id="wp-admin-bar-simple-history-subnode-1">
			<a
				className="ab-item SimpleHistory-adminBarEventsList-item"
				role="menuitem"
				href={ event.link }
				title={ __( 'View event details', 'simple-history' ) }
			>
				<div className="SimpleHistory-adminBarEventsList-item-dot"></div>
				<div className="SimpleHistory-adminBarEventsList-item-content">
					<div className="SimpleHistory-adminBarEventsList-item-content-meta">
						<EventInitiatorName
							event={ event }
							eventVariant="compact"
						/>
						<EventDate event={ event } eventVariant="compact" />
					</div>
					<div className="SimpleHistory-adminBarEventsList-item-content-message">
						<p>{ event.message }</p>
					</div>
				</div>
			</a>
		</li>
	);
};

/**
 * Compact list of events.
 * @param {Object} props
 * @return {Object} React element
 */
export const EventsCompactList = ( props ) => {
	const { events, isLoading, variant = 'default', maxEvents = null } = props;

	if ( isLoading ) {
		return null; // Let parent handle loading state
	}

	// Events not loaded yet.
	if ( events.length === 0 ) {
		return null;
	}

	const displayEvents = maxEvents ? events.slice( 0, maxEvents ) : events;
	const hasMoreEvents = maxEvents && events.length > maxEvents;

	const listClassName = variant === 'sidebar' 
		? 'sh-GutenbergPanel-events' 
		: 'SimpleHistory-adminBarEventsList';

	return (
		<ul className={ listClassName }>
			{ displayEvents.map( ( event ) => (
				<CompactEvent 
					key={ event.id } 
					event={ event } 
					variant={ variant }
				/>
			) ) }
			{ hasMoreEvents && (
				<li className="sh-GutenbergPanel-more">
					{ `+${ events.length - maxEvents } ${ __(
						'more events',
						'simple-history'
					) }` }
				</li>
			) }
		</ul>
	);
};