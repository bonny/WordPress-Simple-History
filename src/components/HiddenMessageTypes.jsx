import { Button } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { closeSmall } from '@wordpress/icons';

/**
 * Chips for the event types hidden from the current list, so the hidden
 * state is never invisible. Each chip removes its own type; "Show all"
 * clears them all.
 *
 * @param {Object}   props
 * @param {Array}    props.excludeMessages    Hidden types, same shape as selectedMessageTypes.
 * @param {Function} props.setExcludeMessages Setter for the hidden types.
 */
export function HiddenMessageTypes( { excludeMessages, setExcludeMessages } ) {
	if ( ! excludeMessages || excludeMessages.length === 0 ) {
		return null;
	}

	const removeMessageType = ( index ) => {
		setExcludeMessages( excludeMessages.filter( ( _, i ) => i !== index ) );
	};

	return (
		<div className="SimpleHistory-hiddenTypes">
			<span className="SimpleHistory-hiddenTypes__label">
				{ sprintf(
					/* translators: %d: number of hidden event types */
					_n(
						'Hiding %d event type:',
						'Hiding %d event types:',
						excludeMessages.length,
						'simple-history'
					),
					excludeMessages.length
				) }
			</span>

			{ excludeMessages.map( ( messageType, index ) => (
				<span
					key={ messageType.search_options.join( ',' ) }
					className="SimpleHistory-hiddenTypes__chip"
				>
					<span className="SimpleHistory-hiddenTypes__chipText">
						{ messageType.value }
					</span>
					<Button
						icon={ closeSmall }
						size="small"
						className="SimpleHistory-hiddenTypes__chipRemove"
						label={ sprintf(
							/* translators: %s: event type label */
							__( 'Show %s again', 'simple-history' ),
							messageType.value
						) }
						onClick={ () => removeMessageType( index ) }
					/>
				</span>
			) ) }

			{ excludeMessages.length > 1 && (
				<Button
					variant="link"
					size="small"
					className="SimpleHistory-hiddenTypes__showAll"
					onClick={ () => setExcludeMessages( [] ) }
				>
					{ __( 'Show all', 'simple-history' ) }
				</Button>
			) }
		</div>
	);
}
