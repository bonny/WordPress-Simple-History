import {
	DropdownMenu,
	Flex,
	FlexItem,
	__experimentalHStack as HStack,
	Icon,
	MenuGroup,
	MenuItem,
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

const MyDropdownMenu = () => (
	<DropdownMenu
		label={ __( 'Actions…', 'simple-history' ) }
		icon={ moreVertical }
	>
		{ ( { onClose } ) => (
			<>
				<MenuGroup>
					{ /* <MenuItem
						onClick={ onClose }
						info="Re-use this search in the future"
					>
						Save search
					</MenuItem> */ }
					<MenuItem onClick={ onClose }>Copy link to search</MenuItem>
				</MenuGroup>
				<MenuGroup label={ __( 'Export', 'simple-history' ) }>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix /> }
					>
						{ __( 'Export as CSV…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix variant="locked" /> }
					>
						{ __( 'Export as CSV…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix variant="unlocked" /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix variant="unlocked2" /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix variant="unlocked3" /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix char="💎" /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix char="✨" /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <Icon icon={ lockSmall } size={ 20 } /> }
					>
						{ __( 'Export as JSON…', 'simple-history' ) }
					</MenuItem>
				</MenuGroup>
				<MenuGroup>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix /> }
					>
						Add event manually
					</MenuItem>
					<MenuItem
						onClick={ onClose }
						suffix={ <PremiumFeatureSuffix /> }
					>
						Send log via email
					</MenuItem>

					<Slot name="SimpleHistorySlotEventsControlBarMenu" />
				</MenuGroup>
			</>
		) }
	</DropdownMenu>
);

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
