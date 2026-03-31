import { Button, Tooltip } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { link, check } from '@wordpress/icons';

/**
 * Button that copies the current view URL to clipboard.
 * Always visible — works with or without active filters.
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

	const copyToClipboard = ( text ) => {
		// Clipboard API requires secure context (HTTPS).
		// Fall back to execCommand for HTTP environments.
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
			timerRef.current = setTimeout( () => {
				setCopied( false );
			}, 2000 );
		} );
	};

	const tooltipText = copied
		? __( 'Link copied!', 'simple-history' )
		: __( 'Copy link to this view', 'simple-history' );

	return (
		<Tooltip text={ tooltipText } delay={ 400 }>
			<Button
				icon={ copied ? check : link }
				variant="tertiary"
				size="compact"
				onClick={ handleClick }
				className={ `sh-ControlBarButton sh-ControlBarButton--share${
					copied ? ' is-copied' : ''
				}` }
				label={ __( 'Copy link', 'simple-history' ) }
			>
				{ __( 'Copy link', 'simple-history' ) }
			</Button>
		</Tooltip>
	);
}
