import {
	Button,
	ButtonGroup,
	DropdownMenu,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	Icon,
	MenuGroup,
	MenuItem,
	Modal,
	Slot,
	Spinner,
	__experimentalText as Text,
	withFilters,
} from '@wordpress/components';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import {
	lockSmall,
	moreVertical,
	starEmpty,
	starFilled,
	unlock,
} from '@wordpress/icons';
import { useState } from '@wordpress/element';

// Based on solution here:
// https://nickdiego.com/a-primer-on-wordpress-slotfill-technology/
const EventsControlBarSlotfillsFilter = withFilters( 'SimpleHistory.Settings' )(
	// eslint-disable-next-line no-unused-vars
	( props ) => <></>
);

const PremiumFeatureSuffix = function ( props ) {
	const { variant, char = null } = props;

	let icon;

	switch ( variant ) {
		case 'unlocked':
			icon = starFilled;
			break;
		case 'unlocked2':
			icon = starEmpty;
			break;
		case 'unlocked3':
			icon = unlock;
			break;
		case 'locked':
			icon = lockSmall;
			break;
		default:
			icon = null;
	}

	return (
		<span
			style={ {
				display: 'flex',
				alignItems: 'center',
				color: 'darkgreen',
				fontSize: '0.8em',
				border: '1px solid darkgreen',
				borderRadius: '4px',
				padding: '0 0.5em',
				opacity: '0.75',
				lineHeight: 1,
				margin: '0 0 0 1em',
			} }
		>
			<span
				style={ {
					padding: '.5em 0',
				} }
			>
				{ __( 'Premium', 'simple-history' ) }
			</span>
			{ icon ? <Icon icon={ icon } size={ 20 } /> : null }
			{ char ? (
				<span style={ { padding: '0 0 0 .5em' } }>{ char }</span>
			) : null }
		</span>
	);
};

function MyDropdownMenu( props ) {
	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ premiumFeatureDescription, setPremiumFeatureDescription ] =
		useState( '' );

	const handleOnClickPremiumFeature = ( localProps ) => {
		const { featureDescription = '' } = localProps;
		setIsModalOpen( true );
		setPremiumFeatureDescription( featureDescription );
	};

	const handleModalClose = () => {
		setIsModalOpen( false );
	};

	const handleOpenPremiumLink = () => {
		// Open URL in new tab.
		window.open( 'https://simple-history.com/premium/?utm_source=wpadmin' );
		setIsModalOpen( false );
	};

	return (
		<>
			{ isModalOpen ? (
				<Modal
					icon={ <Icon icon={ unlock } /> }
					title="Unlock premium feature"
					onRequestClose={ handleModalClose }
				>
					{ premiumFeatureDescription }

					<p>
						This is a very nice premium feature that you can get by
						upgrading to Simple History Premium.
					</p>

					<p>Features include:</p>
					<ul>
						<li>Export as CSV</li>
						<li>Export as JSON</li>
						<li>Custom log clear interval</li>
					</ul>

					<HStack spacing={ 3 }>
						<Button
							variant="primary"
							onClick={ handleOpenPremiumLink }
						>
							Upgrade to premium
						</Button>

						<Button variant="tertiary" onClick={ handleModalClose }>
							Maybe later
						</Button>
					</HStack>
				</Modal>
			) : null }

			<DropdownMenu
				label={ __( 'Actions…', 'simple-history' ) }
				icon={ moreVertical }
			>
				{ ( { onClose } ) => (
					<>
						<MenuGroup>
							<MenuItem onClick={ onClose }>
								Copy link to search
							</MenuItem>
						</MenuGroup>
						<PremiumAddonsPromoMenuGroup
							handleOnClickPremiumFeature={
								handleOnClickPremiumFeature
							}
							onClose={ onClose }
						/>
						<Slot name="SimpleHistorySlotEventsControlBarMenu" />
					</>
				) }
			</DropdownMenu>
		</>
	);
}

function PremiumAddonsPromoMenuGroup( props ) {
	const { handleOnClickPremiumFeature, onClose } = props;

	const handleClickExport = () => {
		onClose();
		handleOnClickPremiumFeature( {
			featureDescription: 'Export as CSV',
		} );
	};

	const handleClickAddEventManually = () => {
		onClose();
		handleOnClickPremiumFeature( {
			featureDescription: 'Add event manually',
		} );
	};

	return (
		<MenuGroup>
			<MenuItem
				onClick={ handleClickExport }
				suffix={ <PremiumFeatureSuffix /> }
				info={ __( 'CSV and JSON supported', 'simple-history' ) }
			>
				{ __( 'Export results…', 'simple-history' ) }
			</MenuItem>
			<MenuItem
				onClick={ handleClickAddEventManually }
				suffix={ <PremiumFeatureSuffix /> }
			>
				{ __( 'Add event manually', 'simple-history' ) }
			</MenuItem>
		</MenuGroup>
	);
}

/**
 * Control bar at the top of the events listing
 * with number of events, reload button, more actions like export,
 * and so on.
 *
 * @param {Object} props
 */
export function EventsControlBar( props ) {
	const { eventsIsLoading, eventsTotal } = props;

	const loadingIndicator = eventsIsLoading ? (
		<Text>
			<Spinner />

			{ _x(
				'Loading…',
				'Message visible while waiting for log to load from server the first time',
				'simple-history'
			) }
		</Text>
	) : null;

	const eventsCount = eventsTotal ? (
		<Text>
			{ sprintf(
				/* translators: %s: number of events */
				_n( '%s event', '%s events', eventsTotal, 'simple-history' ),
				eventsTotal
			) }
		</Text>
	) : null;

	return (
		<>
			<EventsControlBarSlotfillsFilter { ...props } />
			<div
				style={ {
					background: 'white',
					padding: '6px 12px',
				} }
			>
				<Flex gap={ 2 }>
					<FlexItem>
						<HStack spacing={ 2 }>
							{ eventsCount }
							{ loadingIndicator }
						</HStack>
					</FlexItem>
					<FlexItem>
						<MyDropdownMenu />
					</FlexItem>
				</Flex>
			</div>
		</>
	);
}
