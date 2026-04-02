import {
	Button,
	Popover,
	Tooltip,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { share, check, closeSmall } from '@wordpress/icons';

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
	const [ copyFailed, setCopyFailed ] = useState( false );
	const buttonRef = useRef( null );
	const timerRef = useRef( null );

	const handleClose = useCallback( () => {
		setShowPopover( false );
		setCopied( false );
		setCopyFailed( false );
	}, [] );

	// Auto-dismiss after 5s when copy succeeded (no interactive content).
	useEffect( () => {
		if ( showPopover && ! copyFailed ) {
			timerRef.current = setTimeout( handleClose, 5000 );
		}
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [ showPopover, copyFailed, handleClose ] );

	const copyToClipboard = ( text ) => {
		if ( navigator.clipboard?.writeText ) {
			return navigator.clipboard.writeText( text ).then(
				() => true,
				() => false
			);
		}

		try {
			const input = document.createElement( 'input' );
			input.value = text;
			document.body.appendChild( input );
			input.select();
			const success = document.execCommand( 'copy' );
			document.body.removeChild( input );
			return Promise.resolve( success );
		} catch {
			return Promise.resolve( false );
		}
	};

	const handleClick = () => {
		copyToClipboard( window.location.href ).then( ( success ) => {
			setCopied( success );
			setCopyFailed( ! success );
			setShowPopover( true );
		} );
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
				>
					{ __( 'Share view', 'simple-history' ) }
				</Button>
			</Tooltip>

			{ showPopover && (
				<Popover
					anchor={ buttonRef.current }
					noArrow={ false }
					offset={ 8 }
					placement="bottom"
					shift={ true }
					animate={ true }
					className="sh-SharePopover"
					onFocusOutside={ handleClose }
					onClose={ handleClose }
				>
					<div
						className="sh-SharePopover-content"
						role="status"
						aria-live="polite"
					>
						<Button
							icon={ closeSmall }
							label={ __( 'Close', 'simple-history' ) }
							onClick={ handleClose }
							size="small"
							className="sh-SharePopover-close"
						/>
						<VStack spacing={ 2 }>
							<Text weight={ 600 } size={ 13 }>
								{ copyFailed
									? __(
											'Could not copy automatically',
											'simple-history'
									  )
									: __(
											'Link copied to clipboard!',
											'simple-history'
									  ) }
							</Text>
							<Text
								size={ 12 }
								color="var(--sh-color-black-2, #50575e)"
							>
								{ copyFailed
									? __(
											'Copy the link below and share it to show your current log view.',
											'simple-history'
									  )
									: __(
											'Paste it in an email or chat to share your current log view.',
											'simple-history'
									  ) }
							</Text>
							{ copyFailed && (
								<InputControl
									value={ window.location.href }
									readOnly
									size="small"
									className="sh-SharePopover-url"
									aria-label={ __(
										'Shareable link',
										'simple-history'
									) }
									onClick={ ( e ) => e.target.select() }
								/>
							) }
						</VStack>
					</div>
				</Popover>
			) }
		</>
	);
}
