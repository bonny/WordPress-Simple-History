import apiFetch from '@wordpress/api-fetch';
import {
	BaseControl,
	Flex,
	FlexBlock,
	FlexItem,
	FormTokenField,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { LOGLEVELS_OPTIONS, SUBITEM_PREFIX } from '../constants';

/**
 * More filters that are hidden by default.
 * Includes log levels, message types and users.
 *
 * @param {Object} props
 */
export function ExpandedFilters( props ) {
	const {
		messageTypesSuggestions,
		selectedLogLevels,
		setSelectedLogLevels,
		selectedMessageTypes,
		setSelectedMessageTypes,
		selectedUsersWithId,
		setSelectedUsersWithId,
	} = props;

	// User suggestions is the list of users that are loaded from the API
	// and that is used to display user suggestions in the FormTokenField component.
	// userSuggestions is an array of objects with properties "id" (user id) and "value" (name email).
	const [ userSuggestions, setUserSuggestions ] = useState( [] );

	// Generate loglevels suggestions based on LOGLEVELS_OPTIONS.
	// This way we can find the original untranslated label.
	const LOGLEVELS_SUGGESTIONS = LOGLEVELS_OPTIONS.map( ( logLevel ) => {
		return logLevel.label;
	} );

	const searchUsers = async ( searchText ) => {
		if ( searchText.length < 2 ) {
			return;
		}

		apiFetch( {
			path: addQueryArgs( '/simple-history/v1/search-user', {
				q: searchText,
			} ),
		} ).then( ( searchUsersResponse ) => {
			const userSuggestionsLocal = [];

			searchUsersResponse.forEach( ( user ) => {
				userSuggestionsLocal.push( {
					id: user.id,
					value: user.display_name + ' (' + user.user_email + ')',
				} );
			} );

			setUserSuggestions( userSuggestionsLocal );
		} );
	};

	/**
	 * Fired when user changes the users in the FormTokenField.
	 *
	 * From docs:
	 * "Function to call when the tokens have changed. An array of new tokens is passed to the callback.""
	 *
	 * @param {*} nextValues
	 */
	const handleUserChange = ( nextValues ) => {
		// checkValues in an array. The values are:
		// - a string (the user name and email) when the entry is new.
		// - an object with the user id and name+email when the entry is already in the list. Keys are "id" and "value".
		// For new entries we need to replace the string with an object.
		// For existing entries we don't need to do anything.
		nextValues.map( ( value, index ) => {
			if ( typeof value === 'string' ) {
				// This is a new entry, we need to replace the string with an object.
				// Find the user suggestion that has the same label as the value.
				const userSuggestion = userSuggestions.find( ( suggestion ) => {
					return suggestion.value === value;
				} );

				if ( userSuggestion ) {
					nextValues[ index ] = userSuggestion;
				}
			} else {
				// This is an existing entry that already is an object with id and label.
				// No need to do anything.
			}

			return value;
		} );

		setSelectedUsersWithId( nextValues );
	};

	/**
	 * Fired when user changes the message types in the FormTokenField.
	 * Works the same way as handleUserChange.
	 *
	 * @param {*} nextValues
	 */
	const handleMessageTypesChange = ( nextValues ) => {
		nextValues.map( ( value, index ) => {
			if ( typeof value === 'string' ) {
				// This is a new entry, we need to replace the string with an object.
				// Find the user suggestion that has the same label as the value.
				const userSuggestion = messageTypesSuggestions.find(
					( suggestion ) => {
						return suggestion.value.trim() === value.trim();
					}
				);

				if ( userSuggestion ) {
					nextValues[ index ] = userSuggestion;
				}
			} else {
				// This is an existing entry that already is an object with id and label.
				// No need to do anything.
			}

			return value;
		} );

		setSelectedMessageTypes( nextValues );
	};

	return (
		<div>
			<Flex align="top" gap="0" style={ { margin: '0.5em 0' } }>
				<FlexItem style={ { margin: '.5em 0' } }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Log levels', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ {
							width: '310px',
							backgroundColor: 'white',
						} }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							placeholder={ __(
								'All log levels',
								'simple-history'
							) }
							onChange={ ( nextValue ) => {
								setSelectedLogLevels( nextValue );
							} }
							suggestions={ LOGLEVELS_SUGGESTIONS }
							value={ selectedLogLevels }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ { margin: '0.5em 0' } }>
				<FlexItem style={ { margin: '.5em 0' } }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Message types', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ {
							width: '310px',
							backgroundColor: 'white',
						} }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							label=""
							placeholder={ __(
								'All message types',
								'simple-history'
							) }
							onChange={ ( nextValues ) => {
								handleMessageTypesChange( nextValues );
							} }
							// An array of strings or objects to display as tokens in the field. If objects are present in the array, they must have a property of value.
							// Transform to remove the prefix, if any.
							value={ selectedMessageTypes.map( ( value ) => {
								value.value = value.value.replace(
									SUBITEM_PREFIX,
									''
								);
								return value;
							} ) }
							suggestions={ messageTypesSuggestions.map(
								( suggestion ) => {
									return suggestion.value;
								}
							) }
							/**
							 * Custom renderer for suggestions.
							 * props.item is string. Examples:
							 * item: 'Tillägg'}
							 * item: ' - All tilläggsaktivitet'
							 * item: ' - Aktiverade tillägg'
							 *
							 * @param {*} localProps
							 */
							__experimentalRenderItem={ ( localProps ) => {
								// Items that does not begin with prefix should be modified to use bold text.
								// Items that begin with prefix should not be modified.
								if (
									! localProps.item.startsWith(
										SUBITEM_PREFIX
									)
								) {
									return <strong>{ localProps.item }</strong>;
								}

								// Unmodified item.
								return localProps.item;
							} }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ { margin: '0.5em 0' } }>
				<FlexItem style={ { margin: '.5em 0' } }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Users', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ {
							width: '310px',
							backgroundColor: 'white',
						} }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							label=""
							placeholder={ __( 'All users', 'simple-history' ) }
							onChange={ ( nextValues ) => {
								handleUserChange( nextValues );
							} }
							onInputChange={ ( value ) => {
								searchUsers( value );
							} }
							// Suggestions:
							// An array of strings to present to the user as suggested tokens.
							suggestions={ userSuggestions.map(
								( suggestion ) => {
									return suggestion.value;
								}
							) }
							value={ selectedUsersWithId }
						/>
					</div>
					<BaseControl
						__nextHasNoMarginBottom
						help={ __(
							'Enter 2 or more characters to search for users.',
							'simple-history'
						) }
					/>
				</FlexBlock>
			</Flex>
		</div>
	);
}
