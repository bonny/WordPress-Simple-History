import { DropdownMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { copy, info, link, moreVertical, page, send } from '@wordpress/icons';
import { clsx } from 'clsx';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventText } from './EventText';
import { navigateToEventPermalink } from '../functions';

function EventActions( props ) {
	const { event } = props;

	return (
		<div className="SimpleHistoryLogitem__actions">
			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreVertical }
				popoverProps={ {
					placement: 'left-start',
					inline: true,
				} }
				controls={ [
					{
						title: __( 'View details', 'simple-history' ),
						icon: info,
						onClick: () => {
							navigateToEventPermalink( { event } );
						},
					},
					{
						title: __( 'Copy as text', 'simple-history' ),
						icon: copy,
						onClick: () => {
							navigator.clipboard.writeText( event.message );
						},
					},
					{
						title: __( 'Copy link', 'simple-history' ),
						icon: link,
						onClick: () => {
							console.log( 'Copy link to event', event );
						},
					},
					{
						title: __( 'Send via email', 'simple-history' ),
						icon: send,
						onClick: () => {
							console.log( 'Send via email', event );
						},
					},
				] }
			/>
		</div>
	);
}

/**
 * Component for a single event in the list of events.
 *
 * @param {Object} props
 */
export function Event( props ) {
	const {
		event,
		variant = 'normal',
		mapsApiKey,
		hasExtendedSettingsAddOn,
		isNewAfterFetchNewEvents,
	} = props;

	const containerClassNames = clsx(
		'SimpleHistoryLogitem',
		`SimpleHistoryLogitem--variant-${ variant }`,
		`SimpleHistoryLogitem--loglevel-${ event.loglevel }`,
		`SimpleHistoryLogitem--logger-${ event.logger }`,
		`SimpleHistoryLogitem--initiator-${ event.initiator }`,
		{
			'SimpleHistoryLogitem--newRowSinceReload': isNewAfterFetchNewEvents,
		}
	);

	return (
		<li key={ event.id } className={ containerClassNames }>
			<div className="SimpleHistoryLogitem__firstcol">
				<EventInitiatorImage event={ event } />
			</div>

			<div className="SimpleHistoryLogitem__secondcol">
				<EventHeader
					event={ event }
					eventVariant={ variant }
					mapsApiKey={ mapsApiKey }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				/>

				<EventText event={ event } eventVariant={ variant } />

				<EventDetails event={ event } eventVariant={ variant } />

				<EventOccasions
					event={ event }
					eventVariant={ variant }
					hasExtendedSettingsAddOn={ hasExtendedSettingsAddOn }
				/>

				<EventActions event={ event } />
			</div>
		</li>
	);
}
