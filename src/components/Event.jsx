import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { info, link, moreVertical } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';
import { clsx } from 'clsx';
import { getEventPermalink, navigateToEventPermalink } from '../functions';
import { EventDetails } from './EventDetails';
import { EventHeader } from './EventHeader';
import { EventInitiatorImage } from './EventInitiator';
import { EventOccasions } from './EventOccasions';
import { EventText } from './EventText';
import { useState } from '@wordpress/element';

function CopyLinkMenuItem( { event } ) {
	const { createInfoNotice } = useDispatch( noticesStore );
	const permalink = getEventPermalink( { event } );

	const copyText = __( 'Copy link to event', 'simple-history' );
	const copiedText = __( 'Link copied to clipboard', 'simple-history' );

	const [ dynamicCopyText, setDynamicCopyText ] = useState( copyText );

	const ref = useCopyToClipboard( permalink, () => {
		setDynamicCopyText( copiedText );
		setTimeout( () => {
			setDynamicCopyText( copyText );
		}, 2000 );

		// FIXME: A notice would be better but this does not work for some reason.
		createInfoNotice( 'info', __( 'Copied URL to clipboard.' ), {
			isDismissible: false,
		} );
	} );

	return (
		<MenuItem icon={ link } iconPosition="left" ref={ ref }>
			{ dynamicCopyText }
		</MenuItem>
	);
}

function ViewEventDetailsMenuItem( { event, eventVariant, onClose } ) {
	// Don't show the "View event details" menu item if the event is already
	// displayed in a modal.
	if ( eventVariant === 'modal' ) {
		return null;
	}

	return (
		<MenuItem
			icon={ info }
			iconPosition="left"
			onClick={ () => {
				navigateToEventPermalink( { event } );
				onClose();
			} }
		>
			{ __( 'View event details', 'simple-history' ) }
		</MenuItem>
	);
}

function EventActions( props ) {
	const { event } = props;
	const eventVariant = props.eventVariant;

	return (
		<div className="SimpleHistoryLogitem__actions">
			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreVertical }
				popoverProps={ {
					placement: 'left-start',
					inline: true,
				} }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<ViewEventDetailsMenuItem
								event={ event }
								eventVariant={ eventVariant }
								onClose={ onClose }
							/>
							<CopyLinkMenuItem event={ event } />
						</MenuGroup>
					</>
				) }
			</DropdownMenu>
		</div>
	);
}
/*
				{ [

					{
						title: __( 'Copy as text', 'simple-history' ),
						icon: copy,
						onClick: () => {
							navigator.clipboard.writeText( event.message );
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
				*/
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

				<EventActions event={ event } eventVariant={ variant } />
			</div>
		</li>
	);
}
