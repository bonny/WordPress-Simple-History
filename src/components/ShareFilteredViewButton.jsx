import {
	Button,
	Popover,
	Tooltip,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { share, check } from '@wordpress/icons';

/**
 * Button that copies the current view URL to clipboard and shows
 * a transient popover confirming the action.
 *
 * Always visible — works with or without active filters.
 * Enables team collaboration: "look at these failed logins."
 */
export function ShareFilteredViewButton() {
	const [ showPopover, setShowPopover ] = useState( false );
	const [ copied, setCopied ] = useState( false );
	const buttonRef = useRef( null );
	const timerRef = useRef( null );
	const inputRef = useRef( null );

	// Clean up timer on unmount.
	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	// Select the URL text when popover opens.
	useEffect( () => {
		if ( showPopover && inputRef.current ) {
			inputRef.current.select();
		}
	}, [ showPopover ] );

	const copyToClipboard = ( text ) => {
		if ( navigator.clipboard?.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		const input = document.createElement( 'input' );
		input.value = text;
		document.body.appendChild( input );
		input.select();
		document.execCommand( 'copy' );
		document.body.removeChild( input );
		return Promise.resolve();
	};

	const handleClick = () => {
		copyToClipboard( window.location.href ).then( () => {
			setCopied( true );
			setShowPopover( true );

			// Auto-dismiss after 5 seconds.
			timerRef.current = setTimeout( () => {
				setShowPopover( false );
				setCopied( false );
			}, 5000 );
		} );
	};

	const handleClose = () => {
		setShowPopover( false );
		setCopied( false );
		if ( timerRef.current ) {
			clearTimeout( timerRef.current );
		}
	};

	return (
		<>
			<Tooltip
				text={ __(
					'Copy a link to this filtered view',
					'simple-history'
				) }
				delay={ 400 }
			>
				<Button
					ref={ buttonRef }
					icon={ copied ? check : share }
					variant="tertiary"
					size="compact"
					onClick={ handleClick }
					className={ `sh-ControlBarButton sh-ControlBarButton--share${
						copied ? ' is-copied' : ''
					}` }
					label={ __( 'Share view', 'simple-history' ) }
				>
					{ __( 'Share view', 'simple-history' ) }
				</Button>
			</Tooltip>

			{ showPopover && (
				<Popover
					anchorRef={ buttonRef }
					noArrow={ false }
					offset={ 8 }
					placement="bottom"
					shift={ true }
					animate={ true }
					className="sh-SharePopover"
					onFocusOutside={ handleClose }
				>
					<div
						className="sh-SharePopover-content"
						role="status"
						aria-live="polite"
					>
						<VStack spacing={ 2 }>
							<Text weight={ 600 } size={ 13 }>
								{ __(
									'Link copied to clipboard!',
									'simple-history'
								) }
							</Text>
							<Text
								size={ 12 }
								color="var(--sh-color-black-2, #50575e)"
							>
								{ __(
									'Share this URL to show others this exact view.',
									'simple-history'
								) }
							</Text>
							<InputControl
								ref={ inputRef }
								value={ window.location.href }
								readOnly
								size="small"
								className="sh-SharePopover-url"
								onClick={ ( e ) => e.target.select() }
							/>
						</VStack>
					</div>
				</Popover>
			) }
		</>
	);
}
