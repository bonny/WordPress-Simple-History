import apiFetch from '@wordpress/api-fetch';
import { Button, Popover, Tooltip } from '@wordpress/components';
import { useCallback, useRef, useState } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { SVG, Path } from '@wordpress/primitives';
import { useEventsSettings } from './EventsSettingsContext';
import { getTrackingUrl } from '../functions';

/**
 * All supported reaction types. Only thumbsup is free in core.
 * A couple of premium types are shown as teasers.
 */
const REACTIONS = [
	{ type: 'thumbsup', emoji: '👍', label: 'Thumbs up', premium: false },
	{ type: 'heart', emoji: '❤️', label: 'Heart', premium: true },
	{ type: 'surprised', emoji: '😮', label: 'Surprised', premium: true },
	{ type: 'tada', emoji: '🎉', label: 'Celebrate', premium: true },
	{ type: 'eyes', emoji: '👀', label: 'Looking into this', premium: true },
];

/**
 * "Add reaction" icon — Google Material Symbols (Apache 2.0 license), outlined variant.
 */
const addReactionIcon = (
	<SVG xmlns="http://www.w3.org/2000/svg" viewBox="0 -960 960 960">
		<Path d="M480-480Zm.07 380q-78.84 0-148.21-29.92t-120.68-81.21q-51.31-51.29-81.25-120.63Q100-401.1 100-479.93q0-78.84 29.93-148.21 29.92-69.37 81.22-120.68t120.65-81.25Q401.15-860 480-860q41.46 0 80.31 8.31 38.84 8.31 74.3 24.31v67.3q-34.23-18.84-73.23-29.38Q522.38-800 480-800q-133 0-226.5 93.5T160-480q0 133 93.5 226.5T480-160q133 0 226.5-93.5T800-480q0-30.46-5.73-59.12-5.73-28.65-15.96-55.49h64.46q8.61 27.46 12.92 55.71Q860-510.65 860-480q0 78.85-29.92 148.2t-81.21 120.65q-51.29 51.3-120.63 81.22Q558.9-100 480.07-100ZM810-690v-80h-80v-60h80v-80h60v80h80v60h-80v80h-60ZM616.24-527.69q21.84 0 37.03-15.29 15.19-15.28 15.19-37.11t-15.28-37.02q-15.28-15.2-37.12-15.2-21.83 0-37.02 15.29-15.19 15.28-15.19 37.11t15.28 37.02q15.28 15.2 37.11 15.2Zm-272.3 0q21.83 0 37.02-15.29 15.19-15.28 15.19-37.11t-15.28-37.02q-15.28-15.2-37.11-15.2-21.84 0-37.03 15.29-15.19 15.28-15.19 37.11t15.28 37.02q15.28 15.2 37.12 15.2Zm250.71 220.34q51.66-35.04 76.27-92.65H289.08q24.61 57.61 76.27 92.65Q417-272.31 480-272.31q63 0 114.65-35.04Z" />
	</SVG>
);

/**
 * Trigger a pop animation on an element by toggling a CSS class.
 *
 * @param {Object} ref       React ref to the DOM element.
 * @param {string} className CSS class that contains the animation.
 */
function triggerPopAnimation( ref, className ) {
	const el = ref.current;
	if ( ! el ) {
		return;
	}

	el.classList.remove( className );
	void el.offsetWidth;
	el.classList.add( className );
}

// Build filtered reactions list and emoji lookup at module level.
const FILTERED_REACTIONS = applyFilters(
	'SimpleHistory.reactions.types',
	REACTIONS
);
const FREE_REACTIONS = FILTERED_REACTIONS.filter( ( r ) => ! r.premium );
const PREMIUM_REACTIONS = FILTERED_REACTIONS.filter( ( r ) => r.premium );
const EMOJI_MAP = Object.fromEntries(
	FILTERED_REACTIONS.map( ( r ) => [ r.type, r.emoji ] )
);

/**
 * Hook to manage reaction state and API calls for an event.
 *
 * @param {Object} event The event object with optional reactions data.
 * @return {Object} Reaction state and toggle handler.
 */
export function useEventReactions( event ) {
	const [ reactions, setReactions ] = useState( event.reactions || {} );
	const [ isUpdating, setIsUpdating ] = useState( false );
	const initialReactionsRef = useRef( event.reactions || {} );

	const toggleReaction = useCallback(
		async ( type = 'thumbsup' ) => {
			if ( isUpdating ) {
				return;
			}

			setIsUpdating( true );

			// Determine endpoint before optimistic update.
			const hasReacted = reactions[ type ]?.reacted ?? false;
			const endpoint = hasReacted ? 'unreact' : 'react';

			setReactions( ( prev ) => {
				const current = prev[ type ] || {
					count: 0,
					reacted: false,
				};
				return {
					...prev,
					[ type ]: {
						...current,
						count: current.reacted
							? current.count - 1
							: current.count + 1,
						reacted: ! current.reacted,
					},
				};
			} );

			try {
				const response = await apiFetch( {
					path: `/simple-history/v1/events/${ event.id }/${ endpoint }`,
					method: 'POST',
					data: { type },
				} );

				if ( response.reactions ) {
					setReactions( response.reactions );
				}
			} catch ( error ) {
				setReactions( initialReactionsRef.current );
			} finally {
				setIsUpdating( false );
			}
		},
		[ event.id, isUpdating, reactions ]
	);

	return {
		reactions,
		isUpdating,
		toggleReaction,
	};
}

/**
 * Single reaction pill button with tooltip.
 *
 * @param {Object}   props
 * @param {string}   props.type            Reaction type key.
 * @param {Object}   props.data            Reaction data { count, reacted, user_ids, user_names }.
 * @param {boolean}  props.isUpdating      Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction  Callback to toggle the reaction.
 * @param {number}   props.currentUserId   Current user's ID.
 */
function ReactionPill( {
	type,
	data,
	isUpdating,
	toggleReaction,
	currentUserId,
} ) {
	const buttonRef = useRef( null );
	const emoji = EMOJI_MAP[ type ] || type;

	const handleClick = () => {
		triggerPopAnimation(
			buttonRef,
			'SimpleHistoryLogitem__reactionButton--animating'
		);
		toggleReaction( type );
	};

	const youLabel = __( 'You', 'simple-history' );
	const userIds = data.user_ids || [];
	const userNames = ( data.user_names || [] ).map( ( name, i ) =>
		userIds[ i ] === currentUserId ? youLabel : name
	);
	const sortedNames = [ ...userNames ].sort( ( a, b ) =>
		a === youLabel ? -1 : b === youLabel ? 1 : 0
	);
	const tooltipText = sortedNames.length > 0 ? sortedNames.join( ', ' ) : '';

	return (
		<Tooltip text={ tooltipText } placement="top">
			<Button
				ref={ buttonRef }
				className={ `SimpleHistoryLogitem__reactionButton ${
					data.reacted
						? 'SimpleHistoryLogitem__reactionButton--active'
						: ''
				}` }
				onClick={ handleClick }
				disabled={ ! currentUserId }
				aria-pressed={ data.reacted }
				size="small"
			>
				<span className="SimpleHistoryLogitem__reactionEmoji">
					{ emoji }
				</span>
				<span className="SimpleHistoryLogitem__reactionCount">
					{ data.count }
				</span>
			</Button>
		</Tooltip>
	);
}

/**
 * Shared emoji picker popover used by both the hover action bar
 * button and the inline "+" button next to reaction pills.
 *
 * @param {Object}   props
 * @param {Object}   props.anchor          DOM element to anchor the popover to.
 * @param {string}   props.placement       Popover placement (e.g. 'bottom-start').
 * @param {boolean}  props.isUpdating      Whether a reaction API call is in progress.
 * @param {Function} props.onEmojiClick    Called with reaction type when an emoji is clicked.
 * @param {Function} props.onClose         Called when the popover should close.
 */
function ReactionPickerPopover( {
	anchor,
	placement,
	isUpdating,
	onEmojiClick,
	onClose,
} ) {
	const { hasPremiumAddOn } = useEventsSettings();
	const showPremiumTeaser = ! hasPremiumAddOn && PREMIUM_REACTIONS.length > 0;

	return (
		<Popover
			anchor={ anchor }
			noArrow={ false }
			offset={ 8 }
			placement={ placement }
			shift={ true }
			animate={ true }
			className="sh-ReactionPicker"
			onFocusOutside={ onClose }
			onClose={ onClose }
		>
			<div className="sh-ReactionPicker__content">
				<div className="sh-ReactionPicker__freeSection">
					{ FREE_REACTIONS.map( ( reaction ) => (
						<button
							key={ reaction.type }
							className="sh-ReactionPicker__emoji"
							onClick={ () => onEmojiClick( reaction.type ) }
							disabled={ isUpdating }
							title={ reaction.label }
							type="button"
						>
							<span>{ reaction.emoji }</span>
						</button>
					) ) }
				</div>
				{ showPremiumTeaser && (
					<a
						href={ getTrackingUrl(
							'https://simple-history.com/add-ons/premium/',
							'premium_reactions'
						) }
						className="sh-ReactionPicker__premiumSection"
						target="_blank"
						rel="noopener noreferrer"
					>
						<span className="sh-ReactionPicker__premiumEmojis">
							{ PREMIUM_REACTIONS.map( ( reaction ) => (
								<span
									key={ reaction.type }
									className="sh-ReactionPicker__premiumEmoji"
								>
									{ reaction.emoji }
								</span>
							) ) }
						</span>
						<span className="sh-ReactionPicker__premiumText">
							{ __( 'More with Premium →', 'simple-history' ) }
						</span>
					</a>
				) }
			</div>
		</Popover>
	);
}

/**
 * Small inline "+" button shown after existing reaction pills.
 * Opens the emoji picker so users can add reactions without
 * discovering the hover action bar first.
 *
 * @param {Object}   props
 * @param {boolean}  props.isUpdating      Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction  Callback to toggle the reaction.
 */
function InlineAddReactionButton( { isUpdating, toggleReaction } ) {
	const { currentUserId } = useEventsSettings();
	const [ isOpen, setIsOpen ] = useState( false );
	const buttonRef = useRef( null );

	const handleEmojiClick = ( type ) => {
		toggleReaction( type );
		setIsOpen( false );
	};

	return (
		<>
			<button
				ref={ buttonRef }
				className="SimpleHistoryLogitem__reactionAddInline"
				onClick={ () => setIsOpen( ! isOpen ) }
				disabled={ ! currentUserId }
				aria-expanded={ isOpen ? 'true' : 'false' }
				aria-label={ __( 'Add reaction…', 'simple-history' ) }
				type="button"
			>
				<span aria-hidden="true">+</span>
			</button>

			{ isOpen && (
				<ReactionPickerPopover
					anchor={ buttonRef.current }
					placement="bottom-start"
					isUpdating={ isUpdating }
					onEmojiClick={ handleEmojiClick }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</>
	);
}

/**
 * Display reaction counts below an event. Only shows when reactions exist.
 *
 * @param {Object}   props
 * @param {Object}   props.reactions       Full reactions state object.
 * @param {boolean}  props.isUpdating      Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction  Callback to toggle the reaction.
 */
export function EventReactions( { reactions, isUpdating, toggleReaction } ) {
	const { experimentalFeaturesEnabled, currentUserId } = useEventsSettings();

	if ( ! experimentalFeaturesEnabled ) {
		return null;
	}

	// Get reaction types that have at least one reaction.
	const activeTypes = Object.entries( reactions ).filter(
		( [ , data ] ) => data.count > 0
	);

	if ( activeTypes.length === 0 ) {
		return null;
	}

	return (
		<div className="SimpleHistoryLogitem__reactions">
			{ activeTypes.map( ( [ type, data ] ) => (
				<ReactionPill
					key={ type }
					type={ type }
					data={ data }
					isUpdating={ isUpdating }
					toggleReaction={ toggleReaction }
					currentUserId={ currentUserId }
				/>
			) ) }
			<InlineAddReactionButton
				isUpdating={ isUpdating }
				toggleReaction={ toggleReaction }
			/>
		</div>
	);
}

/**
 * Quick-action button that opens an emoji picker popover.
 * Shows in the hover actions bar alongside the fullscreen button.
 *
 * @param {Object}   props
 * @param {boolean}  props.isUpdating       Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction   Callback to toggle the reaction.
 */
export function EventReactionQuickButton( { isUpdating, toggleReaction } ) {
	const { experimentalFeaturesEnabled, currentUserId } = useEventsSettings();
	const [ isOpen, setIsOpen ] = useState( false );
	const buttonRef = useRef( null );

	if ( ! experimentalFeaturesEnabled ) {
		return null;
	}

	const handleEmojiClick = ( type ) => {
		toggleReaction( type );
		setIsOpen( false );
	};

	return (
		<>
			<Button
				ref={ buttonRef }
				className="SimpleHistoryLogitem__reactionQuickButton"
				icon={ addReactionIcon }
				label={ __( 'Add reaction…', 'simple-history' ) }
				size="small"
				onClick={ () => setIsOpen( ! isOpen ) }
				disabled={ ! currentUserId }
				aria-expanded={ isOpen ? 'true' : 'false' }
			/>

			{ isOpen && (
				<ReactionPickerPopover
					anchor={ buttonRef.current }
					placement="bottom-end"
					isUpdating={ isUpdating }
					onEmojiClick={ handleEmojiClick }
					onClose={ () => setIsOpen( false ) }
				/>
			) }
		</>
	);
}
