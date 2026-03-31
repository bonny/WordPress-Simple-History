import { Button, Tooltip } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { share } from '@wordpress/icons';

/**
 * Button that copies the current filtered view URL to clipboard.
 * Only rendered when filters are active (parent controls visibility).
 * Enables team collaboration: "look at these failed logins."
 */
export function ShareFilteredViewButton() {
	const [ copied, setCopied ] = useState( false );
	const timerRef = useRef( null );

	// Clean up timer on unmount.
	useEffect( () => {
		return () => {
			if ( timerRef.current ) {
				clearTimeout( timerRef.current );
			}
		};
	}, [] );

	const handleClick = () => {
		navigator.clipboard
			.writeText( window.location.href )
			.then( () => {
				setCopied( true );
				timerRef.current = setTimeout( () => {
					setCopied( false );
				}, 2000 );
			} )
			.catch( () => {
				// Fallback: select text in a temporary input.
				const input = document.createElement( 'input' );
				input.value = window.location.href;
				document.body.appendChild( input );
				input.select();
				document.execCommand( 'copy' );
				document.body.removeChild( input );
				setCopied( true );
				timerRef.current = setTimeout( () => {
					setCopied( false );
				}, 2000 );
			} );
	};

	const tooltipText = copied
		? __( 'Link copied!', 'simple-history' )
		: __( 'Copy link to this filtered view', 'simple-history' );

	return (
		<Tooltip text={ tooltipText } delay={ 400 }>
			<Button
				icon={ share }
				variant="tertiary"
				size="compact"
				onClick={ handleClick }
				className={ `sh-ControlBarButton sh-ControlBarButton--share${
					copied ? ' is-copied' : ''
				}` }
				label={ __( 'Share filtered view', 'simple-history' ) }
			>
				{ copied
					? __( 'Copied!', 'simple-history' )
					: __( 'Share view', 'simple-history' ) }
			</Button>
		</Tooltip>
	);
}
