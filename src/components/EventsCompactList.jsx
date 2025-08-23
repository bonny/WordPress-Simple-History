import {
	__experimentalHStack as HStack,
	__experimentalVStack as VStack,
	__experimentalText as Text,
} from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { EventDate } from './EventDate';
import { EventInitiatorName } from './EventInitiatorName';

/**
 * One compact event for the compact event list.
 *
 * @param {Object} props
 * @return {Object} React element
 */
export const CompactEvent = ( props ) => {
	const { event, variant = 'default' } = props;

	const adminUrl = useSelect( ( select ) => {
		return select( coreStore ).getSite()?.url + '/wp-admin/';
	}, [] );

	if ( variant === 'sidebar' ) {
		// Simplified version for post sidebar panels,
		// ie the Gutenberg post editor history with revision links.
		const revisionLinkUrl = `${ adminUrl }revision.php?revision=${ event.context.post_revision_id }`;
		const revisionLink = event.context.post_revision_id ? (
			<div className="sh-GutenbergPanel-event__meta-revision">
				<a href={ revisionLinkUrl }>View revision</a>
			</div>
		) : null;

		return (
			<li className="sh-GutenbergPanel-event">
				<VStack spacing={ 1 }>
					<HStack justify="flex-start" spacing={ 1 }>
						<EventInitiatorName
							event={ event }
							eventVariant="compact"
						/>
						<span className="sh-GutenbergPanel-event__meta-separator">
							•
						</span>
						<a
							href={ event.link }
							title={ __(
								'View event details',
								'simple-history'
							) }
						>
							<EventDate event={ event } eventVariant="compact" />
						</a>
					</HStack>

					<Text>{ event.message }</Text>

					{ revisionLink }
				</VStack>
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
 *
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

	const listClassName =
		variant === 'sidebar'
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
