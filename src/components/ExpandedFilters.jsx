import apiFetch from '@wordpress/api-fetch';
import {
	CheckboxControl,
	Flex,
	FlexBlock,
	FlexItem,
	FormTokenField,
	Icon,
	TextareaControl,
	__experimentalInputControl as InputControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { help } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';
import { LOGLEVELS_OPTIONS, SUBITEM_PREFIX } from '../constants';
import { getTrackingUrl } from '../functions';

/**
 * More filters that are hidden by default.
 * Includes log levels, message types and users.
 *
 * @param {Object} props
 */
export function ExpandedFilters( props ) {
	const {
		selectedLogLevels,
		setSelectedLogLevels,
		selectedMessageTypes,
		setSelectedMessageTypes,
		selectedUsersWithId,
		setSelectedUsersWithId,
		selectedInitiator,
		setSelectedInitiator,
		selectedContextFilters,
		setSelectedContextFilters,
		enteredMetadataSearch,
		setEnteredMetadataSearch,
		searchOptions,
		hideOwnEvents,
		setHideOwnEvents,
	} = props;

	// Array with objects that contains message types suggestions, used in the message types select control.
	// Keys are "slug" for search and "value".
	const [ messageTypesSuggestions, setMessageTypesSuggestions ] = useState(
		[]
	);

	// User suggestions is the list of users that are loaded from the API
	// and that is used to display user suggestions in the FormTokenField component.
	// userSuggestions is an array of objects with properties "id" (user id) and "value" (name email).
	const [ userSuggestions, setUserSuggestions ] = useState( [] );

	// Generate initiator suggestions for the FormTokenField.
	const [ initiatorSuggestions, setInitiatorSuggestions ] = useState( [] );

	// Update initiator suggestions when searchOptions changes
	useEffect( () => {
		if ( searchOptions?.initiators ) {
			const suggestions = searchOptions.initiators
				.filter( Boolean )
				.map( ( initiator ) => ( {
					value: initiator.label,
					initiator_key: initiator.value,
					search_options: [ initiator.label ],
				} ) );

			setInitiatorSuggestions( suggestions );
		}
	}, [ searchOptions ] );

	const handleInitiatorsChange = ( nextValues ) => {
		nextValues.map( ( value, index ) => {
			if ( typeof value === 'string' ) {
				// This is a new entry, we need to replace the string with an object.
				// Find the initiator suggestion that has the same label as the value.
				const initiatorSuggestion = initiatorSuggestions.find(
					( suggestion ) => {
						return suggestion.value.trim() === value.trim();
					}
				);

				if ( initiatorSuggestion ) {
					nextValues[ index ] = initiatorSuggestion;
				}
			} else {
				// This is an existing entry that already is an object.
				// No need to do anything.
			}

			return value;
		} );

		setSelectedInitiator( nextValues );
	};

	// Generate loglevels suggestions based on LOGLEVELS_OPTIONS.
	// This way we can find the original untranslated label.
	const LOGLEVELS_SUGGESTIONS = LOGLEVELS_OPTIONS.map( ( logLevel ) => {
		return logLevel.label;
	} );

	// Update message types suggestions when searchOptions changes
	useEffect( () => {
		if ( ! searchOptions?.loggers ) {
			return;
		}

		const nextMessageTypesSuggestions = [];
		searchOptions.loggers.forEach( ( logger ) => {
			const searchData = logger.search_data || {};

			if ( ! searchData.search ) {
				return;
			}

			// "WordPress och tilläggsuppdateringar"
			nextMessageTypesSuggestions.push( {
				value: searchData.search.label,
				search_options: searchData.search.options,
				// key: logger.slug,
			} );

			// "Alla hittade uppdateringar"
			if ( searchData?.search_all?.label ) {
				nextMessageTypesSuggestions.push( {
					value: SUBITEM_PREFIX + searchData.search_all.label,
					// key: `${logger.slug}:all`,
					search_options: searchData.search_all.options,
				} );
			}

			// Each single message.
			if ( searchData?.search_options ) {
				searchData.search_options.forEach( ( option ) => {
					nextMessageTypesSuggestions.push( {
						value: SUBITEM_PREFIX + option.label,
						// key: `${logger.slug}:${option.key}`,
						search_options: option.options,
					} );
				} );
			}
		} );

		setMessageTypesSuggestions( nextMessageTypesSuggestions );
	}, [ searchOptions ] );

	const searchUsers = async ( searchText ) => {
		if ( searchText.length < 2 ) {
			return;
		}

		apiFetch( {
			path: addQueryArgs( '/simple-history/v1/search-user', {
				q: searchText,
			} ),
		} )
			.then( ( searchUsersResponse ) => {
				const userSuggestionsLocal = [];

				searchUsersResponse.forEach( ( user ) => {
					userSuggestionsLocal.push( {
						id: user.id,
						value: user.display_name + ' (' + user.user_email + ')',
					} );
				} );

				setUserSuggestions( userSuggestionsLocal );
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.error(
					'Simple History: Failed to search users',
					error
				);
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

	const gridUnit = 'var(--grid-unit, 8px)';
	const filterFieldStyle = { maxWidth: '310px', backgroundColor: 'white' };
	const filterRowStyle = {
		margin: `${ gridUnit } 0`,
	};
	const labelMarginStyle = {
		margin: `calc(${ gridUnit }) 0`,
	};

	return (
		<div>
			{ /* Group: Who & What */ }
			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Users', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ filterFieldStyle }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							label={ __( 'Users', 'simple-history' ) }
							placeholder={ __( 'All users', 'simple-history' ) }
							onChange={ ( nextValues ) => {
								handleUserChange( nextValues );
							} }
							onInputChange={ ( value ) => {
								searchUsers( value );
							} }
							suggestions={ userSuggestions.map(
								( suggestion ) => {
									return suggestion.value;
								}
							) }
							value={ selectedUsersWithId }
						/>
					</div>
					<div
						style={ {
							marginTop: `calc(${ gridUnit } / 2)`,
							marginBottom: `calc(${ gridUnit } / 2 + 4px)`,
							paddingLeft: '1px',
						} }
					>
						<CheckboxControl
							__nextHasNoMarginBottom
							label={ __(
								'Hide my own events',
								'simple-history'
							) }
							checked={ hideOwnEvents }
							onChange={ setHideOwnEvents }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Message types', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ filterFieldStyle }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							label={ __( 'Message types', 'simple-history' ) }
							placeholder={ __(
								'All message types',
								'simple-history'
							) }
							onChange={ ( nextValues ) => {
								handleMessageTypesChange( nextValues );
							} }
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
							__experimentalRenderItem={ ( localProps ) => {
								if (
									! localProps.item.startsWith(
										SUBITEM_PREFIX
									)
								) {
									return <strong>{ localProps.item }</strong>;
								}

								return localProps.item;
							} }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Log levels', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ filterFieldStyle }
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

			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Initiators', 'simple-history' ) }{ ' ' }
						<a
							href={ getTrackingUrl(
								'https://simple-history.com/support/what-is-an-initiator/',
								'docs_filters_initiator'
							) }
							target="_blank"
							rel="noopener noreferrer"
							aria-label={ __(
								'About initiators and how they work (opens in new tab)',
								'simple-history'
							) }
							style={ {
								color: '#1e1e1e',
								verticalAlign: 'middle',
							} }
						>
							<Icon icon={ help } size={ 16 } />
						</a>
					</div>
				</FlexItem>
				<FlexBlock>
					<div
						className="SimpleHistory__filters__loglevels__select"
						style={ filterFieldStyle }
					>
						<FormTokenField
							__experimentalAutoSelectFirstMatch
							__experimentalExpandOnFocus
							__experimentalShowHowTo={ false }
							label={ __( 'Initiators', 'simple-history' ) }
							placeholder={ __(
								'All initiators',
								'simple-history'
							) }
							onChange={ ( nextValues ) => {
								handleInitiatorsChange( nextValues );
							} }
							value={ selectedInitiator }
							suggestions={ initiatorSuggestions.map(
								( suggestion ) => {
									return suggestion.value;
								}
							) }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Event metadata', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div style={ { maxWidth: '310px' } }>
						<InputControl
							value={ enteredMetadataSearch }
							onChange={ ( value ) =>
								setEnteredMetadataSearch( value || '' )
							}
							placeholder={ __(
								'IP address, email, username…',
								'simple-history'
							) }
							help={ __(
								'Searches IP addresses, emails, and hidden metadata. May be slower on large sites.',
								'simple-history'
							) }
						/>
					</div>
				</FlexBlock>
			</Flex>

			<Flex align="top" gap="0" style={ filterRowStyle }>
				<FlexItem style={ labelMarginStyle }>
					<div className="SimpleHistory__filters__filterLabel">
						{ __( 'Context', 'simple-history' ) }
					</div>
				</FlexItem>
				<FlexBlock>
					<div style={ { maxWidth: '310px' } }>
						<TextareaControl
							__nextHasNoMarginBottom
							value={ selectedContextFilters }
							onChange={ ( value ) =>
								setSelectedContextFilters( value )
							}
							placeholder={ __( '_user_id:1', 'simple-history' ) }
							rows={ 2 }
							help={ __(
								'Advanced: filter by raw event context. One key:value pair per line.',
								'simple-history'
							) }
							style={ {
								fontFamily: 'monospace',
								fieldSizing: 'content',
							} }
						/>
					</div>
				</FlexBlock>
			</Flex>
		</div>
	);
}
