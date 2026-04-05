import apiFetch from '@wordpress/api-fetch';
import { Button, Popover, Tooltip } from '@wordpress/components';
import { useCallback, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
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

/**
 * Hook to manage reaction state and API calls for an event.
 *
 * @param {Object} event The event object with optional reactions data.
 * @return {Object} Reaction state and toggle handler.
 */
export function useEventReactions( event ) {
	const initialReactions = event.reactions || {};
	const [ reactions, setReactions ] = useState( initialReactions );
	const [ isUpdating, setIsUpdating ] = useState( false );

	const thumbsup = reactions.thumbsup || { count: 0, reacted: false };

	const toggleReaction = useCallback(
		async ( type = 'thumbsup' ) => {
			if ( isUpdating ) {
				return;
			}

			setIsUpdating( true );

			const current = reactions[ type ] || {
				count: 0,
				reacted: false,
			};
			const newReacted = ! current.reacted;
			const newCount = current.reacted
				? current.count - 1
				: current.count + 1;

			// Optimistic update.
			setReactions( ( prev ) => ( {
				...prev,
				[ type ]: {
					...( prev[ type ] || { count: 0, reacted: false } ),
					count: newCount,
					reacted: newReacted,
				},
			} ) );

			try {
				const endpoint = newReacted ? 'react' : 'unreact';
				const response = await apiFetch( {
					path: `/simple-history/v1/events/${ event.id }/${ endpoint }`,
					method: 'POST',
					data: { type },
				} );

				if ( response.reactions ) {
					setReactions( response.reactions );
				}
			} catch ( error ) {
				setReactions( initialReactions );
			} finally {
				setIsUpdating( false );
			}
		},
		[ event.id, isUpdating, reactions, initialReactions ]
	);

	return {
		reactions,
		thumbsup,
		isUpdating,
		toggleReaction,
	};
}

/**
 * Display reaction counts below an event. Only shows when reactions exist.
 * Clicking a reaction pill toggles the current user's reaction.
 *
 * @param {Object}   props
 * @param {Object}   props.reactions       Full reactions state object.
 * @param {Object}   props.thumbsup        Thumbsup reaction data { count, reacted }.
 * @param {boolean}  props.isUpdating      Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction  Callback to toggle the reaction.
 */
export function EventReactions( {
	reactions,
	thumbsup,
	isUpdating,
	toggleReaction,
} ) {
	const { experimentalFeaturesEnabled, currentUserId } = useEventsSettings();
	const buttonRef = useRef( null );

	if ( ! experimentalFeaturesEnabled ) {
		return null;
	}

	// Only render when there are reactions to show.
	if ( thumbsup.count === 0 ) {
		return null;
	}

	const handleClick = () => {
		triggerPopAnimation(
			buttonRef,
			'SimpleHistoryLogitem__reactionButton--animating'
		);
		toggleReaction( 'thumbsup' );
	};

	const userIds = thumbsup.user_ids || [];
	const userNames = ( thumbsup.user_names || [] ).map( ( name, i ) =>
		userIds[ i ] === currentUserId ? __( 'You', 'simple-history' ) : name
	);
	// Put "You" first.
	userNames.sort( ( a, b ) =>
		a === __( 'You', 'simple-history' )
			? -1
			: b === __( 'You', 'simple-history' )
			? 1
			: 0
	);
	const tooltipText = userNames.length > 0 ? userNames.join( ', ' ) : '';

	return (
		<div className="SimpleHistoryLogitem__reactions">
			<Tooltip text={ tooltipText } placement="top">
				<Button
					ref={ buttonRef }
					className={ `SimpleHistoryLogitem__reactionButton ${
						thumbsup.reacted
							? 'SimpleHistoryLogitem__reactionButton--active'
							: ''
					}` }
					onClick={ handleClick }
					disabled={ isUpdating || ! currentUserId }
					aria-label={
						thumbsup.reacted
							? __( 'Remove reaction', 'simple-history' )
							: __( 'React with thumbs up', 'simple-history' )
					}
					aria-pressed={ thumbsup.reacted }
					size="small"
				>
					<span className="SimpleHistoryLogitem__reactionEmoji">
						👍
					</span>
					<span className="SimpleHistoryLogitem__reactionCount">
						{ thumbsup.count }
					</span>
				</Button>
			</Tooltip>
		</div>
	);
}

/**
 * Quick-action button that opens an emoji picker popover.
 * Shows in the hover actions bar alongside the fullscreen button.
 *
 * @param {Object}   props
 * @param {Object}   props.thumbsup        Thumbsup reaction data { count, reacted }.
 * @param {boolean}  props.isUpdating       Whether a reaction API call is in progress.
 * @param {Function} props.toggleReaction   Callback to toggle the reaction.
 */
export function EventReactionQuickButton( {
	thumbsup,
	isUpdating,
	toggleReaction,
} ) {
	const { experimentalFeaturesEnabled, currentUserId, hasPremiumAddOn } =
		useEventsSettings();
	const [ isOpen, setIsOpen ] = useState( false );
	const buttonRef = useRef( null );

	if ( ! experimentalFeaturesEnabled ) {
		return null;
	}

	const handleEmojiClick = ( type, isPremium ) => {
		if ( isPremium ) {
			return;
		}

		toggleReaction( type );
		setIsOpen( false );
	};

	const premiumUrl = getTrackingUrl(
		'https://simple-history.com/add-ons/premium/',
		'premium_reactions'
	);

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
				aria-expanded={ isOpen }
			/>

			{ isOpen && (
				<Popover
					anchor={ buttonRef.current }
					noArrow={ false }
					offset={ 8 }
					placement="bottom-end"
					shift={ true }
					animate={ true }
					className="sh-ReactionPicker"
					onFocusOutside={ () => setIsOpen( false ) }
					onClose={ () => setIsOpen( false ) }
				>
					<div className="sh-ReactionPicker__content">
						<div className="sh-ReactionPicker__freeSection">
							{ REACTIONS.filter( ( r ) => ! r.premium ).map(
								( reaction ) => (
									<button
										key={ reaction.type }
										className="sh-ReactionPicker__emoji"
										onClick={ () =>
											handleEmojiClick(
												reaction.type,
												false
											)
										}
										disabled={ isUpdating }
										title={ reaction.label }
										type="button"
									>
										<span>{ reaction.emoji }</span>
									</button>
								)
							) }
						</div>
						{ ! hasPremiumAddOn && (
							<a
								href={ premiumUrl }
								className="sh-ReactionPicker__premiumSection"
								target="_blank"
								rel="noopener noreferrer"
							>
								<span className="sh-ReactionPicker__premiumEmojis">
									{ REACTIONS.filter(
										( r ) => r.premium
									).map( ( reaction ) => (
										<span
											key={ reaction.type }
											className="sh-ReactionPicker__premiumEmoji"
										>
											{ reaction.emoji }
										</span>
									) ) }
								</span>
								<span className="sh-ReactionPicker__premiumText">
									{ __(
										'More with Premium →',
										'simple-history'
									) }
								</span>
							</a>
						) }
					</div>
				</Popover>
			) }
		</>
	);
}
